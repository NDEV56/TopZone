/**
 * lib/tunnels.js — Multi-tunnel manager
 * ─────────────────────────────────────
 * Provider yang didukung:
 *   1. ngrok       (utama, butuh @ngrok/ngrok atau binary 'ngrok' di PATH)
 *   2. cloudflared (Cloudflare Tunnel — quick tunnel ngarah ke localhost)
 *   3. localtunnel (binary 'lt')
 *   4. serveo      (lewat SSH, tidak butuh install)
 *   5. pinggy      (lewat SSH alternatif)
 *   6. none        (matikan tunnel — local only)
 *
 * API:
 *   const tm = new TunnelManager(cfg, logger);
 *   const url = await tm.start(localPort);
 *   await tm.stop();
 *   tm.providers — list provider tersedia
 */

"use strict";

const { spawn, execSync } = require("child_process");
const http = require("http");
const path = require("path");
const fs   = require("fs");
const { sleep, hasCommand, looksLikeNgrokToken } = require("./utils");

const NGROK_API_BASE = "http://127.0.0.1:4040";

class TunnelManager {
  constructor(cfg, logger) {
    this.cfg    = cfg;
    this.logger = logger;
    this.active = null;          // { provider, url, stop }
    this.requestPoller = null;
    this.requestSeen   = new Set();
    this.requestCount  = 0;
  }

  /** Cek provider mana yang siap dipakai. */
  availability() {
    const out = {};
    out.ngrok       = hasCommand("ngrok") || this._sdkAvailable("@ngrok/ngrok");
    out.cloudflared = hasCommand("cloudflared");
    out.localtunnel = hasCommand("lt");
    out.serveo      = hasCommand("ssh");
    out.pinggy      = hasCommand("ssh");
    out.none        = true;
    return out;
  }

  _sdkAvailable(name) {
    try { require.resolve(name); return true; } catch { return false; }
  }

  /** Provider order kalau fallback aktif. */
  _fallbackOrder() {
    const primary = this.cfg.TUNNEL_PROVIDER;
    const all = ["ngrok", "cloudflared", "localtunnel", "serveo", "pinggy"];
    return [primary, ...all.filter((p) => p !== primary)];
  }

  /** Start tunnel dengan fallback otomatis bila enabled. */
  async start(localPort) {
    if (this.cfg.TUNNEL_PROVIDER === "none") {
      this.logger.uncommon("Tunnel mode = none, hanya local-only.");
      return { url: `http://localhost:${localPort}`, provider: "none" };
    }

    const order = this.cfg.TUNNEL_FALLBACK ? this._fallbackOrder() : [this.cfg.TUNNEL_PROVIDER];
    const avail = this.availability();
    let lastErr = null;

    for (const provider of order) {
      if (!avail[provider]) {
        this.logger.uncommon(`Skip provider ${provider} — tidak terinstall.`);
        continue;
      }
      try {
        this.logger.common(`Mencoba tunnel via ${provider}...`);
        const result = await this._startOne(provider, localPort);
        this.active = result;
        this.logger.common(`Tunnel ${provider} aktif: ${result.url}`, { provider });
        // Mulai polling request log untuk ngrok
        if (provider === "ngrok") this._startNgrokRequestLogger();
        return result;
      } catch (e) {
        lastErr = e;
        this.logger.warning(`Gagal start tunnel ${provider}: ${e.message}`, { provider });
      }
    }

    const err = new Error(`Semua tunnel gagal. Error terakhir: ${lastErr ? lastErr.message : "?"}`);
    err.cause = lastErr;
    throw err;
  }

  async _startOne(provider, localPort) {
    switch (provider) {
      case "ngrok"      : return this._startNgrok(localPort);
      case "cloudflared": return this._startCloudflared(localPort);
      case "localtunnel": return this._startLocalTunnel(localPort);
      case "serveo"     : return this._startServeo(localPort);
      case "pinggy"     : return this._startPinggy(localPort);
      default: throw new Error(`Provider tidak dikenal: ${provider}`);
    }
  }

  // ─────────────────────────────────────────────────
  //  NGROK
  // ─────────────────────────────────────────────────
  async _startNgrok(localPort) {
    const token = this.cfg.NGROK_AUTHTOKEN;
    if (!looksLikeNgrokToken(token)) {
      throw new Error("Token ngrok kosong / tidak valid. Daftar di dashboard.ngrok.com lalu isi NGROK_AUTHTOKEN.");
    }

    // Coba pakai SDK dulu (lebih mulus)
    let useSdk = false;
    try { require.resolve("@ngrok/ngrok"); useSdk = true; } catch (_) {}

    if (useSdk) return this._startNgrokSdk(localPort, token);
    return this._startNgrokBinary(localPort, token);
  }

  async _startNgrokSdk(localPort, token) {
    const ngrok = require("@ngrok/ngrok");
    const opts = { addr: localPort, authtoken: token };
    if (this.cfg.NGROK_DOMAIN) opts.domain = this.cfg.NGROK_DOMAIN;

    const RETRY = 3;
    let lastErr = null;
    for (let attempt = 1; attempt <= RETRY; attempt++) {
      try {
        const listener = await ngrok.forward(opts);
        const url = listener.url();
        return {
          provider: "ngrok",
          url,
          stop: async () => { try { await ngrok.disconnect(); } catch (_) {} },
        };
      } catch (e) {
        lastErr = e;
        const msg = String(e.message || "");
        if (/authtoken|authentication/i.test(msg)) {
          throw new Error("Token ngrok tidak valid. Cek di dashboard.ngrok.com/get-started/your-authtoken");
        }
        if (/tunnel session.*limit|free.*plan/i.test(msg)) {
          throw new Error("Batas sesi ngrok gratis tercapai. Tutup sesi lain di dashboard.ngrok.com/tunnels.");
        }
        if (attempt < RETRY) await sleep(1500 * attempt);
      }
    }
    throw lastErr || new Error("Ngrok SDK gagal setelah 3 percobaan.");
  }

  async _startNgrokBinary(localPort, token) {
    // Validasi token — token ngrok hanya alnum + underscore. Tolak shell metachar.
    if (!/^[A-Za-z0-9_]{20,200}$/.test(String(token))) {
      throw new Error("Token ngrok mengandung karakter tidak sah (kemungkinan injection).");
    }
    // Set authtoken sekali (idempotent) — pakai spawnSync array args (NO shell)
    try {
      const { spawnSync } = require("child_process");
      spawnSync("ngrok", ["config", "add-authtoken", token],
        { stdio: "ignore", timeout: 5000, shell: false });
    } catch (_) {}

    const args = ["http", String(localPort), "--log=stdout", "--log-format=json"];
    if (this.cfg.NGROK_DOMAIN) args.push("--domain", this.cfg.NGROK_DOMAIN);
    const proc = spawn("ngrok", args, {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    proc.on("error", (e) => this.logger.error(`ngrok proc error: ${e.message}`));

    // Tunggu API ngrok :4040 aktif → ambil URL public
    const url = await this._waitForNgrokApiUrl(8000);
    return {
      provider: "ngrok",
      url,
      stop: async () => { try { proc.kill("SIGTERM"); } catch (_) {} },
      _proc: proc,
    };
  }

  _waitForNgrokApiUrl(timeoutMs) {
    return new Promise((resolve, reject) => {
      const deadline = Date.now() + timeoutMs;
      const tick = () => {
        if (Date.now() > deadline) return reject(new Error("Timeout menunggu API ngrok"));
        http.get(`${NGROK_API_BASE}/api/tunnels`, (res) => {
          let buf = "";
          res.on("data", (d) => (buf += d));
          res.on("end", () => {
            try {
              const data = JSON.parse(buf);
              const tunnel = (data.tunnels || []).find((t) => /^https/.test(t.public_url || ""))
                          || (data.tunnels || [])[0];
              if (tunnel && tunnel.public_url) return resolve(tunnel.public_url);
              setTimeout(tick, 400);
            } catch (_) { setTimeout(tick, 400); }
          });
        }).on("error", () => setTimeout(tick, 400));
      };
      tick();
    });
  }

  // ─────────────────────────────────────────────────
  //  CLOUDFLARE TUNNEL (Quick Tunnel — tidak butuh login)
  // ─────────────────────────────────────────────────
  async _startCloudflared(localPort) {
    const args = ["tunnel", "--url", `http://localhost:${localPort}`, "--no-autoupdate"];
    if (this.cfg.CLOUDFLARED_TOKEN) {
      args.push("--token", this.cfg.CLOUDFLARED_TOKEN);
    }

    const proc = spawn("cloudflared", args, {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let url = null;
    const urlPromise = new Promise((resolve, reject) => {
      const onData = (chunk) => {
        const text = chunk.toString();
        const m = text.match(/https:\/\/[a-z0-9.\-]+\.trycloudflare\.com/i);
        if (m && !url) {
          url = m[0];
          resolve(url);
        }
      };
      proc.stdout.on("data", onData);
      proc.stderr.on("data", onData);
      proc.on("exit", (code) => {
        if (!url) reject(new Error(`cloudflared keluar dengan code ${code} sebelum tunnel siap.`));
      });
      setTimeout(() => {
        if (!url) reject(new Error("Timeout menunggu URL cloudflared (15s)."));
      }, 15000);
    });

    const finalUrl = await urlPromise;
    return {
      provider: "cloudflared",
      url     : finalUrl,
      stop    : async () => { try { proc.kill("SIGTERM"); } catch (_) {} },
      _proc   : proc,
    };
  }

  // ─────────────────────────────────────────────────
  //  LOCALTUNNEL
  // ─────────────────────────────────────────────────
  async _startLocalTunnel(localPort) {
    const args = ["--port", String(localPort)];
    const proc = spawn("lt", args, {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let url = null;
    const urlPromise = new Promise((resolve, reject) => {
      const onData = (chunk) => {
        const text = chunk.toString();
        const m = text.match(/https:\/\/[a-z0-9.\-]+\.loca\.lt/i);
        if (m && !url) {
          url = m[0];
          resolve(url);
        }
      };
      proc.stdout.on("data", onData);
      proc.stderr.on("data", onData);
      proc.on("exit", (c) => { if (!url) reject(new Error(`localtunnel keluar code ${c}`)); });
      setTimeout(() => { if (!url) reject(new Error("Timeout localtunnel (15s)")); }, 15000);
    });

    const finalUrl = await urlPromise;
    return {
      provider: "localtunnel",
      url: finalUrl,
      stop: async () => { try { proc.kill("SIGTERM"); } catch (_) {} },
      _proc: proc,
    };
  }

  // ─────────────────────────────────────────────────
  //  SERVEO (SSH tunnel — gratis, tidak butuh akun)
  // ─────────────────────────────────────────────────
  async _startServeo(localPort) {
    const args = ["-o", "StrictHostKeyChecking=no", "-R", `80:localhost:${localPort}`, "serveo.net"];
    const proc = spawn("ssh", args, {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let url = null;
    const urlPromise = new Promise((resolve, reject) => {
      const onData = (chunk) => {
        const text = chunk.toString();
        const m = text.match(/https?:\/\/[a-z0-9.\-]+\.serveo\.net/i);
        if (m && !url) { url = m[0]; resolve(url); }
      };
      proc.stdout.on("data", onData);
      proc.stderr.on("data", onData);
      proc.on("exit", (c) => { if (!url) reject(new Error(`serveo SSH keluar code ${c}`)); });
      setTimeout(() => { if (!url) reject(new Error("Timeout serveo (20s)")); }, 20000);
    });

    const finalUrl = await urlPromise;
    return {
      provider: "serveo",
      url: finalUrl,
      stop: async () => { try { proc.kill("SIGTERM"); } catch (_) {} },
      _proc: proc,
    };
  }

  // ─────────────────────────────────────────────────
  //  PINGGY (SSH tunnel alternatif)
  // ─────────────────────────────────────────────────
  async _startPinggy(localPort) {
    const args = ["-p", "443", "-o", "StrictHostKeyChecking=no",
                  "-R", `0:localhost:${localPort}`, "tcp@a.pinggy.io"];
    const proc = spawn("ssh", args, {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let url = null;
    const urlPromise = new Promise((resolve, reject) => {
      const onData = (chunk) => {
        const text = chunk.toString();
        const m = text.match(/https?:\/\/[a-z0-9.\-]+\.(pinggy\.link|pinggy\.io|free\.pinggy\.link)/i);
        if (m && !url) { url = m[0]; resolve(url); }
      };
      proc.stdout.on("data", onData);
      proc.stderr.on("data", onData);
      proc.on("exit", (c) => { if (!url) reject(new Error(`pinggy SSH keluar code ${c}`)); });
      setTimeout(() => { if (!url) reject(new Error("Timeout pinggy (20s)")); }, 20000);
    });

    const finalUrl = await urlPromise;
    return {
      provider: "pinggy",
      url: finalUrl,
      stop: async () => { try { proc.kill("SIGTERM"); } catch (_) {} },
      _proc: proc,
    };
  }

  // ─────────────────────────────────────────────────
  //  NGROK request logger (live)
  // ─────────────────────────────────────────────────
  _startNgrokRequestLogger() {
    if (this.requestPoller) return;
    if (!this.cfg.LOG_REQUESTS) return;

    this.requestPoller = setInterval(() => {
      http.get(`${NGROK_API_BASE}/api/requests/http?limit=20`, (res) => {
        let raw = "";
        res.on("data", (d) => (raw += d));
        res.on("end", () => {
          try {
            const { requests } = JSON.parse(raw);
            if (!requests || !requests.length) return;
            for (const r of requests.reverse()) {
              if (this.requestSeen.has(r.id)) continue;
              this.requestSeen.add(r.id);
              this.requestCount++;
              const method = r.request?.method || "?";
              const uri    = r.request?.uri    || "/";
              const status = r.response?.status || 0;
              const remote = r.remote_addr     || "?";
              const lvl = status >= 500 ? "error"
                        : status >= 400 ? "warning"
                        : "common";
              this.logger.log(lvl, `${method} ${uri} → ${status}`, {
                ip: remote, ua: r.request?.headers?.["User-Agent"] || "",
              });
            }
            // Trim memory
            if (this.requestSeen.size > 500) {
              this.requestSeen = new Set([...this.requestSeen].slice(-200));
            }
          } catch (_) {}
        });
      }).on("error", () => {});
    }, 1500);
  }

  /** Stop tunnel aktif. */
  async stop() {
    if (this.requestPoller) {
      clearInterval(this.requestPoller);
      this.requestPoller = null;
    }
    if (this.active && typeof this.active.stop === "function") {
      try { await this.active.stop(); } catch (_) {}
    }
    this.active = null;
  }

  /** Return jumlah request yang sudah tercatat (untuk display). */
  getStats() {
    return {
      requestCount: this.requestCount,
      provider    : this.active ? this.active.provider : null,
      url         : this.active ? this.active.url      : null,
    };
  }
}

module.exports = { TunnelManager, NGROK_API_BASE };
