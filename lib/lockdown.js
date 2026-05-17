/**
 * lib/lockdown.js — Emergency Lockdown System
 * ────────────────────────────────────────────
 *
 * Tujuan: kalau panel TopZone benar-benar dalam serangan berat,
 * otomatis "kunci" panel — hanya endpoint kritis yang boleh
 * jalan (health, status read-only). Semua state-changing endpoint
 * di-tolak. Lockdown bisa di-trigger:
 *   • Manual oleh admin (tombol di GUI)
 *   • Otomatis kalau threshold tinggi tercapai:
 *      - Suspicion event > X dalam Y detik
 *      - Banyak IP berbeda meng-attack (distributed)
 *      - Lonjakan RPS > Z (DDoS spike)
 *      - Body besar berturut-turut (memory exhaustion)
 *
 * Tingkatan:
 *   none      : normal operation
 *   guarded   : extra logging, body cap dipotong, semua POST minta CSRF
 *   restricted: hanya GET endpoint allowlist + login
 *   lockdown  : tutup total kecuali /api/health (untuk monitoring)
 *
 * Auto-trigger memakai damper: lockdown otomatis tidak akan
 * masuk ke "lockdown" penuh secara langsung — naik bertingkat
 * (guarded → restricted → lockdown). Dan auto-keluar setelah
 * cooldown bila threshold sudah aman selama N detik.
 */

"use strict";

const { EventEmitter } = require("events");

const LEVELS = ["none", "guarded", "restricted", "lockdown"];

const DEFAULTS = {
  // Bobot evidensi
  windowSec       : 60,            // jendela untuk evaluasi
  thresholdGuarded: 25,            // skor agregat → guarded
  thresholdRestrict: 80,           // → restricted
  thresholdLockdown: 200,          // → full lockdown
  // Distinct attacker IPs (DDoS)
  distinctIpsAttack: 8,            // ≥ 8 IP berbeda dalam 60s = distributed
  // Cooldown sebelum auto-deescalate
  cooldownSec     : 120,           // perlu 2 menit "tenang" sebelum turun tingkat
  // Manual lockdown durasi default
  manualDurationMs: 30 * 60 * 1000,
  // Endpoint yang TETAP boleh saat restricted/lockdown
  publicAllow     : ["/api/health"],
  restrictedAllow : ["/api/health", "/api/whoami", "/api/lockdown/status",
                     "/api/login", "/api/logout", "/login",
                     "/", "/styles.css", "/app.js", "/wizard.js",
                     "/logs.js", "/login.html", "/index.html", "/favicon.ico"],
};

class LockdownManager extends EventEmitter {
  constructor(options = {}) {
    super();
    this.opt = { ...DEFAULTS, ...options };
    this.logger = options.logger || null;

    this.level         = "none";
    this.reason        = null;
    this.activatedAt   = 0;
    this.expiresAt     = 0;            // 0 = no expiry (manual until cleared)
    this.activatedBy   = null;
    this.triggerLog    = [];           // ringkasan kejadian

    // Window evidence
    this.events        = [];           // { ts, ip, weight, reason }
    this.lastSafeTs    = Date.now();
    this.deescalateTimer = null;
  }

  /**
   * Catat satu indikator. Caller (firewall) panggil ini setiap deteksi:
   *   - 'sql-pattern' / 'xss' / 'rce' weight: 5
   *   - 'rate-limit-hit' weight: 1
   *   - 'block-promote' weight: 8
   *   - 'huge-body' weight: 6
   *   - 'login-fail' weight: 2
   */
  reportIncident(opts = {}) {
    const { ip = "?", weight = 1, reason = "anomaly" } = opts;
    const ts = Date.now();
    this.events.push({ ts, ip, weight, reason });

    // Trim ke window
    const cutoff = ts - this.opt.windowSec * 1000;
    while (this.events.length && this.events[0].ts < cutoff) this.events.shift();

    this._evaluate();
  }

  /** Hitung agregat kejadian dalam window dan tentukan level otomatis. */
  _evaluate() {
    if (this.level === "lockdown" && this.expiresAt === 0) {
      // Lockdown manual penuh — jangan turunkan otomatis
      return;
    }

    let totalScore = 0;
    const distinctIps = new Set();
    const reasons = {};
    for (const e of this.events) {
      totalScore += e.weight;
      distinctIps.add(e.ip);
      reasons[e.reason] = (reasons[e.reason] || 0) + 1;
    }

    let suggested = "none";
    if (totalScore >= this.opt.thresholdLockdown ||
        distinctIps.size >= this.opt.distinctIpsAttack * 2) {
      suggested = "lockdown";
    } else if (totalScore >= this.opt.thresholdRestrict ||
               distinctIps.size >= this.opt.distinctIpsAttack) {
      suggested = "restricted";
    } else if (totalScore >= this.opt.thresholdGuarded) {
      suggested = "guarded";
    }

    if (LEVELS.indexOf(suggested) > LEVELS.indexOf(this.level)) {
      // Naik level — auto, dengan cooldown reset
      this._setLevel(suggested, {
        auto: true,
        score: totalScore,
        distinctIps: distinctIps.size,
        topReasons: Object.entries(reasons).sort((a,b) => b[1]-a[1]).slice(0, 5),
      });
    } else if (suggested === "none" && this.level !== "none") {
      // Mulai cooldown
      if (!this.deescalateTimer) {
        this.deescalateTimer = setTimeout(() => {
          this.deescalateTimer = null;
          // Re-evaluate after cooldown
          if (this.level === "lockdown" && this.expiresAt === 0) return; // manual
          if (Date.now() - this.lastSafeTs >= this.opt.cooldownSec * 1000) {
            this._setLevel("none", { auto: true, deescalate: true });
          }
        }, this.opt.cooldownSec * 1000);
        if (this.deescalateTimer.unref) this.deescalateTimer.unref();
      }
    }

    this.lastSafeTs = (suggested === "none") ? Date.now() : 0;
  }

  _setLevel(level, meta = {}) {
    if (!LEVELS.includes(level)) return;
    if (level === this.level) return;
    const prev = this.level;
    this.level = level;
    this.reason = meta.reason || (meta.auto ? "auto-trigger" : meta.manualReason || "manual");
    this.activatedAt = Date.now();
    this.activatedBy = meta.activatedBy || (meta.auto ? "system" : "admin");
    this.expiresAt = meta.expiresAt || 0;

    const entry = {
      ts: this.activatedAt,
      from: prev, to: level,
      reason: this.reason, activatedBy: this.activatedBy,
      meta,
    };
    this.triggerLog.push(entry);
    if (this.triggerLog.length > 100) this.triggerLog.shift();

    this.emit("level", entry);

    if (level === "none") {
      this.logger?.uncommon?.(`Lockdown dinonaktifkan (sebelumnya: ${prev})`, { prev });
    } else {
      const fn = level === "lockdown" ? "critical"
               : level === "restricted" ? "critical"
               : "security";
      this.logger?.[fn]?.(
        `Lockdown level: ${level} (${this.reason})`,
        { level, prev, activatedBy: this.activatedBy, ...meta }
      );
    }
  }

  /** Aktivasi manual. */
  activate(level = "lockdown", opts = {}) {
    if (!LEVELS.includes(level) || level === "none") {
      return { ok: false, error: "Level tidak valid" };
    }
    const expiresAt = opts.durationMs > 0
      ? Date.now() + opts.durationMs
      : (opts.permanent ? 0 : Date.now() + this.opt.manualDurationMs);

    this._setLevel(level, {
      auto: false,
      activatedBy: opts.by || "admin",
      manualReason: opts.reason || "manual",
      expiresAt,
    });
    this.expiresAt = expiresAt;

    if (expiresAt > 0) {
      setTimeout(() => {
        if (this.expiresAt && Date.now() >= this.expiresAt && this.level !== "none") {
          this._setLevel("none", { auto: true, deescalate: true, byTimer: true });
          this.expiresAt = 0;
        }
      }, expiresAt - Date.now() + 100).unref?.();
    }
    return { ok: true, level, expiresAt };
  }

  /** Deactivate manual. */
  deactivate(by = "admin") {
    if (this.level === "none") return { ok: true, alreadyOff: true };
    this._setLevel("none", { auto: false, deescalate: true, activatedBy: by });
    this.events = [];
    this.expiresAt = 0;
    return { ok: true };
  }

  /** Cek apakah path diizinkan saat level saat ini. */
  isAllowed(reqPath, method = "GET") {
    if (this.level === "none") return true;
    if (this.level === "guarded") return true; // hanya tighten, tidak block

    // restricted / lockdown
    const list = this.level === "lockdown"
      ? this.opt.publicAllow
      : this.opt.restrictedAllow;

    // GET-only saat restricted
    if (this.level === "restricted" && method !== "GET" &&
        !["/api/login", "/api/logout"].includes(reqPath)) {
      return false;
    }

    return list.includes(reqPath) || list.some((p) => reqPath === p);
  }

  /** Snapshot status. */
  status() {
    return {
      level       : this.level,
      reason      : this.reason,
      activatedAt : this.activatedAt,
      activatedBy : this.activatedBy,
      expiresAt   : this.expiresAt,
      remainingSec: this.expiresAt ? Math.max(0, Math.round((this.expiresAt - Date.now())/1000)) : null,
      eventsInWindow: this.events.length,
      windowScore : this.events.reduce((s, e) => s + e.weight, 0),
      distinctIps : new Set(this.events.map((e) => e.ip)).size,
      recentTriggers: this.triggerLog.slice(-10),
    };
  }
}

module.exports = { LockdownManager, LEVELS };
