/**
 * lib/firewall.js — Unified WAF / DDoS / Lockdown middleware
 * ──────────────────────────────────────────────────────────
 * Fungsi tunggal `firewall.handle(req, res, opts)` — return
 *   true  → request boleh diteruskan ke router
 *   false → response sudah ditulis (denied)
 *
 * Pipeline (urut, fail-fast):
 *   1. Lockdown gate (path allowlist saat lockdown)
 *   2. Anti-DDoS connection accept + per-IP onRequest
 *   3. Origin / Host check
 *   4. Method whitelist
 *   5. URL & header bound check (sudah di antiDdos)
 *   6. WAF pattern scan (security.scanSuspicious) → kalau ketemu, report ke lockdown
 *   7. Body size guard (caller harus pass maxBytes ke readBody)
 *
 * Setiap deteksi berbahaya akan:
 *   - Ditolak dengan response yang ramah (tanpa bocor info)
 *   - Dicatat ke logger.security
 *   - Dilaporkan ke lockdown.reportIncident untuk eskalasi
 *   - Optionally bumpSuspicion di antiDdos
 */

"use strict";

const { buildSecurityHeaders, getIp } = require("./security");

const ALLOWED_METHODS = new Set(["GET", "POST", "PUT", "DELETE", "PATCH", "HEAD", "OPTIONS"]);

class Firewall {
  constructor({ cfg, logger, security, antiDdos, lockdown }) {
    if (!cfg || !logger || !security || !antiDdos || !lockdown) {
      throw new Error("Firewall butuh: cfg, logger, security, antiDdos, lockdown");
    }
    this.cfg     = cfg;
    this.logger  = logger;
    this.security = security;
    this.antiDdos = antiDdos;
    this.lockdown = lockdown;

    // Wire suspicion event
    this.antiDdos.on("graylist", ({ ip, reason }) => {
      this.lockdown.reportIncident({ ip, weight: 4, reason: "graylist:" + reason });
    });
    this.antiDdos.on("block", ({ ip, reason }) => {
      this.lockdown.reportIncident({ ip, weight: 8, reason: "block:" + reason });
    });
    this.antiDdos.on("attack-start", ({ rps }) => {
      // Auto-report ke lockdown supaya bisa eskalasi
      this.lockdown.reportIncident({ ip: "*", weight: 30, reason: `rps-spike:${rps}` });
    });
  }

  /** Pasang security headers ke response. */
  _setBaseHeaders(res, options = {}) {
    const headers = buildSecurityHeaders(options);
    for (const k of Object.keys(headers)) {
      try { res.setHeader(k, headers[k]); } catch (_) {}
    }
  }

  /** Tolak request dengan response JSON ringkas. */
  _deny(res, status, error, extra = {}) {
    if (res.headersSent) {
      try { res.end(); } catch (_) {}
      return;
    }
    this._setBaseHeaders(res);
    res.setHeader("Content-Type", "application/json; charset=utf-8");
    res.setHeader("Cache-Control", "no-store");
    if (extra.retryAfter) res.setHeader("Retry-After", String(extra.retryAfter));
    res.statusCode = status;
    try {
      res.end(JSON.stringify({ error, t: Date.now() }));
    } catch (_) {
      try { res.end(); } catch (_) {}
    }
  }

  /**
   * Handle SATU request. Return true bila boleh lanjut.
   * Auto-pasang base security headers walau request OK.
   */
  handle(req, res, opts = {}) {
    const ip = getIp(req);
    const method = (req.method || "GET").toUpperCase();
    const url    = req.url || "/";
    const pathOnly = url.split("?")[0];

    // 0. Pasang security headers default (boleh ditimpa caller)
    this._setBaseHeaders(res);

    // 1. Method whitelist
    if (!ALLOWED_METHODS.has(method)) {
      this.lockdown.reportIncident({ ip, weight: 3, reason: "weird-method:" + method });
      this._deny(res, 405, "Method tidak diizinkan");
      return false;
    }

    // 2. Lockdown gate
    if (!this.lockdown.isAllowed(pathOnly, method)) {
      this.logger.security(`Ditolak oleh lockdown (${this.lockdown.level}): ${method} ${pathOnly}`,
        { ip, level: this.lockdown.level });
      this._deny(res, 503,
        `Panel sedang dalam mode ${this.lockdown.level} — sebagian fitur ditangguhkan untuk keamanan.`);
      return false;
    }

    // 3. Anti-DDoS request check
    const ddos = this.antiDdos.onRequest(req, ip);
    if (!ddos.ok) {
      this._deny(res, ddos.code || 429, ddos.error || "Rate limited",
        { retryAfter: ddos.retryAfter });
      return false;
    }

    // 4. Origin / Host check (skip untuk GET sederhana)
    const ho = this.security.checkOrigin(req);
    if (!ho.ok) {
      this.lockdown.reportIncident({ ip, weight: 4, reason: "bad-origin" });
      this.antiDdos.bumpSuspicion(ip, 3, "bad-origin");
      this._deny(res, 400, "Host/Origin tidak diizinkan.");
      return false;
    }

    // 5. WAF pattern scan
    const sc = this.security.scanSuspicious(req);
    if (sc.suspicious) {
      this.lockdown.reportIncident({ ip, weight: sc.weight, reason: "waf:" + sc.pattern });
      this.antiDdos.bumpSuspicion(ip, Math.min(8, Math.ceil(sc.weight / 2)), "waf:" + sc.pattern);
      this._deny(res, 403, "Request mengandung pola berbahaya: " + sc.pattern);
      return false;
    }

    // 6. CSRF check untuk state-changing methods
    if (opts.csrfRequired) {
      const session = opts.session;
      const tok = req.headers["x-csrf-token"];
      const ok = this.security.validateCsrf(session, tok);
      if (!ok) {
        this.lockdown.reportIncident({ ip, weight: 2, reason: "csrf-fail" });
        this._deny(res, 403, "CSRF token tidak valid. Refresh halaman dan login lagi.");
        return false;
      }
    }

    return true;
  }

  /** Wrapper untuk readBody — pakai lib/security.safeParseJson + size cap. */
  async readJsonBody(req, opts = {}) {
    const maxBytes = Math.min(opts.maxBytes || this.antiDdos.opt.maxBodyBytes, this.antiDdos.opt.maxBodyBytes);
    return new Promise((resolve, reject) => {
      let total = 0;
      let aborted = false;
      const chunks = [];
      let timeout = null;
      const reset = () => { if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => {
          aborted = true;
          this.antiDdos.bumpSuspicion(getIp(req), 3, "slow-body-read");
          // Lebih sopan: berhenti baca, biarkan handler outer kirim 413/408
          req.removeAllListeners("data");
          req.removeAllListeners("end");
          req.resume(); // drain biar tidak nyangkut
          reject(new Error("Body read timeout"));
        }, this.antiDdos.opt.bodyTimeoutMs);
      };
      reset();

      req.on("data", (chunk) => {
        if (aborted) return;
        total += chunk.length;
        if (total > maxBytes) {
          if (timeout) clearTimeout(timeout);
          aborted = true;
          this.antiDdos.bumpSuspicion(getIp(req), 4, "huge-body");
          this.lockdown.reportIncident({ ip: getIp(req), weight: 5, reason: "huge-body" });
          // Berhenti memproses tapi tetap drain socket supaya client dapat response
          req.removeAllListeners("data");
          req.removeAllListeners("end");
          req.resume();
          reject(new Error("Body terlalu besar"));
          return;
        }
        chunks.push(chunk);
        reset();
      });
      req.on("end", () => {
        if (aborted) return;
        if (timeout) clearTimeout(timeout);
        const raw = Buffer.concat(chunks).toString("utf8");
        const { safeParseJson } = require("./security");
        const parsed = safeParseJson(raw, { maxBytes, maxDepth: 8, maxKeys: 200 });
        if (!parsed.ok) {
          this.antiDdos.bumpSuspicion(getIp(req), 3, "bad-json:" + parsed.error.slice(0, 30));
          reject(new Error(parsed.error));
          return;
        }
        resolve(parsed.value || {});
      });
      req.on("error", (e) => {
        if (timeout) clearTimeout(timeout);
        if (!aborted) reject(e);
      });
    });
  }

  /** Connection-level guard (untuk dipasang ke 'connection' event). */
  guardConnection(socket) {
    const ip = (socket && socket.remoteAddress) || "?";
    const result = this.antiDdos.onConnect(ip);
    if (!result.ok) {
      this.logger.security(`Koneksi ditolak (${result.error}): ${ip}`, { ip, reason: result.error });
      try {
        const body = `HTTP/1.1 ${result.code || 503} ${result.error}\r\n` +
                     `Connection: close\r\nContent-Length: 0\r\n\r\n`;
        socket.write(body);
      } catch (_) {}
      try { socket.destroy(); } catch (_) {}
      return false;
    }

    // Slowloris guard
    const cancelSlow = this.antiDdos.attachSlowGuard(socket, ip);

    socket.once("close", () => {
      try { cancelSlow(); } catch (_) {}
      this.antiDdos.onDisconnect(ip);
    });
    return true;
  }

  /** Snapshot statistik untuk dashboard. */
  stats() {
    return {
      ddos    : this.antiDdos.stats(),
      security: this.security.stats(),
      lockdown: this.lockdown.status(),
    };
  }
}

module.exports = { Firewall };
