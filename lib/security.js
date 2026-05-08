/**
 * lib/security.js — keamanan untuk GUI control panel
 * ──────────────────────────────────────────────────
 * Fitur:
 *   - CSRF token + session cookie sederhana
 *   - Login dengan password + brute-force protection (block IP)
 *   - Rate limit per-IP per-menit
 *   - Validasi origin/host header (anti DNS rebinding)
 *   - Suspicious request detection (path traversal, SQLi-pattern, XSS-pattern)
 *   - Audit log via logger.security
 *
 * Tujuan: GUI default bind ke 127.0.0.1, jadi serangan jaringan langsung jarang.
 * Tapi kita tetap defense-in-depth supaya kalau user expose ke LAN tetap aman.
 */

"use strict";

const crypto = require("crypto");
const { sleep } = require("./utils");

const SUSPICIOUS_PATTERNS = [
  { name: "path-traversal", re: /\.\.[\\/]/ },
  { name: "sql-injection",  re: /\b(union\s+select|or\s+1=1|--\s*$|drop\s+table)\b/i },
  { name: "xss-script",     re: /<script[^>]*>|javascript:|onerror\s*=/i },
  { name: "rce-shell",      re: /;\s*(rm\s+-rf|wget\s|curl\s|nc\s+-e|cmd\.exe|powershell)/i },
  { name: "env-leak",       re: /\.env(\W|$)|\.git\/(config|HEAD)/i },
  { name: "wp-admin-probe", re: /wp-(admin|login|content|includes)/i },
  { name: "phpmyadmin-probe", re: /phpmyadmin|adminer|setup\.php/i },
];

class SecurityManager {
  constructor(cfg, logger) {
    this.cfg     = cfg;
    this.logger  = logger;

    // Session & CSRF
    this.sessions = new Map();    // sid -> { ip, createdAt, lastSeen, csrf }
    this.sessionTTL = 12 * 3600 * 1000; // 12 jam

    // Rate limit
    this.requestBuckets = new Map();  // ip -> { count, resetAt }

    // Brute force protection
    this.failedLogins = new Map();    // ip -> { count, blockUntil }

    // Honeypot pattern hits
    this.suspicionLog = [];

    // Token akses (dipakai sebagai backup kalau password kosong)
    this.localToken = crypto.randomBytes(32).toString("hex");
  }

  /** Generate session ID baru. */
  _newSid() { return crypto.randomBytes(24).toString("hex"); }

  /** Hash password dengan salt → simpan di config. */
  static hashPassword(password, salt = null) {
    salt = salt || crypto.randomBytes(16).toString("hex");
    const hash = crypto.scryptSync(password, salt, 32).toString("hex");
    return `scrypt:${salt}:${hash}`;
  }

  /** Verifikasi password terhadap stored hash. */
  static verifyPassword(password, stored) {
    if (!stored) return false;
    if (!stored.startsWith("scrypt:")) {
      // Plain password fallback (NOT recommended) — bandingkan langsung
      return constantTimeEqual(stored, password);
    }
    try {
      const [, salt, hashHex] = stored.split(":");
      const want = Buffer.from(hashHex, "hex");
      const got  = crypto.scryptSync(password, salt, 32);
      return crypto.timingSafeEqual(want, got);
    } catch (_) { return false; }
  }

  /** Cek apakah IP saat ini lagi diblokir karena brute force. */
  isBlocked(ip) {
    const f = this.failedLogins.get(ip);
    if (!f) return false;
    if (f.blockUntil && Date.now() < f.blockUntil) return true;
    if (f.blockUntil && Date.now() >= f.blockUntil) {
      this.failedLogins.delete(ip);
    }
    return false;
  }

  /** Catat percobaan login gagal & block kalau terlalu banyak. */
  recordFailedLogin(ip) {
    const f = this.failedLogins.get(ip) || { count: 0, blockUntil: 0 };
    f.count++;
    if (f.count >= this.cfg.SEC_BLOCK_ON_FAIL) {
      f.blockUntil = Date.now() + this.cfg.SEC_BLOCK_DURATION * 1000;
      this.logger.security(
        `IP ${ip} diblokir ${this.cfg.SEC_BLOCK_DURATION}s setelah ${f.count} login gagal.`,
        { ip, until: new Date(f.blockUntil).toISOString() }
      );
    } else {
      this.logger.security(`Login gagal dari ${ip} (${f.count}/${this.cfg.SEC_BLOCK_ON_FAIL})`, { ip });
    }
    this.failedLogins.set(ip, f);
  }

  /** Reset counter untuk IP setelah login sukses. */
  recordSuccessLogin(ip) {
    this.failedLogins.delete(ip);
  }

  /** Cek + tambah counter rate limit. Return { ok, remaining }. */
  rateLimit(ip) {
    const limit = this.cfg.SEC_RATE_LIMIT;
    const now   = Date.now();
    let bucket  = this.requestBuckets.get(ip);
    if (!bucket || now > bucket.resetAt) {
      bucket = { count: 0, resetAt: now + 60000 };
    }
    bucket.count++;
    this.requestBuckets.set(ip, bucket);

    if (bucket.count > limit) {
      this.logger.security(`Rate limit exceeded oleh ${ip} (${bucket.count}/${limit})`, { ip });
      return { ok: false, remaining: 0, retryAfter: Math.ceil((bucket.resetAt - now) / 1000) };
    }
    return { ok: true, remaining: limit - bucket.count };
  }

  /** Buat session baru → return sid + csrf. */
  createSession(ip) {
    const sid  = this._newSid();
    const csrf = crypto.randomBytes(16).toString("hex");
    this.sessions.set(sid, { ip, createdAt: Date.now(), lastSeen: Date.now(), csrf });
    this._gcSessions();
    return { sid, csrf };
  }

  /** Validasi sid + return session atau null. */
  validateSession(sid, ip) {
    if (!sid) return null;
    const s = this.sessions.get(sid);
    if (!s) return null;
    if (Date.now() - s.lastSeen > this.sessionTTL) {
      this.sessions.delete(sid);
      return null;
    }
    if (s.ip !== ip) {
      this.logger.security(`SID ${sid.slice(0,8)} dipakai dari IP berbeda (orig=${s.ip}, now=${ip}). Sesi dibatalkan.`, { ip });
      this.sessions.delete(sid);
      return null;
    }
    s.lastSeen = Date.now();
    return s;
  }

  /** Hapus session. */
  destroySession(sid) {
    this.sessions.delete(sid);
  }

  _gcSessions() {
    const now = Date.now();
    for (const [sid, s] of this.sessions) {
      if (now - s.lastSeen > this.sessionTTL) this.sessions.delete(sid);
    }
  }

  /** Validasi CSRF token untuk POST/PUT/DELETE. */
  validateCsrf(session, headerToken) {
    if (!session) return false;
    if (!headerToken) return false;
    if (session.csrf.length !== headerToken.length) return false;
    return constantTimeEqual(session.csrf, headerToken);
  }

  /** Cek Host/Origin header — anti DNS rebinding. */
  checkOrigin(req) {
    const host   = req.headers.host || "";
    const origin = req.headers.origin || "";
    const allow  = [
      `localhost:${this.cfg.GUI_PORT}`,
      `127.0.0.1:${this.cfg.GUI_PORT}`,
      `[::1]:${this.cfg.GUI_PORT}`,
    ];
    // Bind 0.0.0.0? Allow LAN host juga
    if (this.cfg.GUI_BIND === "0.0.0.0") {
      // Best effort — terima semua, tapi log
      return { ok: true, host };
    }
    const okHost   = allow.some((h) => host === h || host.startsWith(h.split(":")[0] + ":"));
    if (!okHost) {
      this.logger.security(`Host header mencurigakan: ${host}`, { host, origin });
      return { ok: false, host };
    }
    if (origin) {
      const okOrigin = allow.some((h) => origin.endsWith(h));
      if (!okOrigin) {
        this.logger.security(`Origin tidak diizinkan: ${origin}`, { host, origin });
        return { ok: false, host, origin };
      }
    }
    return { ok: true, host };
  }

  /** Scan request untuk pola berbahaya. */
  scanSuspicious(req) {
    const url = req.url || "";
    const ua  = req.headers["user-agent"] || "";
    const target = url + " " + ua;

    for (const pat of SUSPICIOUS_PATTERNS) {
      if (pat.re.test(target)) {
        this.logger.security(`Pola "${pat.name}" terdeteksi: ${url}`, {
          ip: getIp(req), ua, pattern: pat.name,
        });
        this.suspicionLog.push({ at: Date.now(), ip: getIp(req), pattern: pat.name, url });
        if (this.suspicionLog.length > 200) this.suspicionLog.shift();
        return { suspicious: true, pattern: pat.name };
      }
    }
    return { suspicious: false };
  }

  /** Apakah path adalah whitelist (tidak butuh auth)? */
  isPublicPath(p) {
    const pub = ["/login", "/api/login", "/api/health", "/api/whoami",
                 "/style.css", "/app.js", "/wizard.js", "/logs.js",
                 "/topzone-logo.svg", "/favicon.ico"];
    return pub.includes(p) || p.startsWith("/static/") || p === "/";
  }

  /** Generate token sekali pakai (untuk auto-login dari CLI). */
  oneTimeToken() {
    const t = crypto.randomBytes(24).toString("hex");
    if (!this._otp) this._otp = new Map();
    this._otp.set(t, Date.now() + 5 * 60 * 1000);
    return t;
  }
  consumeOtp(t) {
    if (!this._otp) return false;
    const exp = this._otp.get(t);
    if (!exp) return false;
    this._otp.delete(t);
    return Date.now() < exp;
  }

  /** Statistik untuk dashboard. */
  stats() {
    return {
      activeSessions: this.sessions.size,
      blockedIps    : [...this.failedLogins.entries()]
                        .filter(([, v]) => v.blockUntil > Date.now())
                        .map(([ip, v]) => ({ ip, until: v.blockUntil })),
      suspicionCount: this.suspicionLog.length,
      lastSuspicions: this.suspicionLog.slice(-10),
    };
  }
}

function constantTimeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string") return false;
  if (a.length !== b.length) {
    // Hindari short-circuit — masih lakukan compare dummy
    crypto.timingSafeEqual(Buffer.from(a.padEnd(32, "x")), Buffer.from("x".repeat(32)));
    return false;
  }
  return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));
}

function getIp(req) {
  const xfwd = req.headers["x-forwarded-for"];
  if (xfwd) return String(xfwd).split(",")[0].trim();
  return (req.socket && req.socket.remoteAddress) || "?";
}

/** Helper untuk parse cookies. */
function parseCookies(header) {
  const out = {};
  if (!header) return out;
  for (const part of String(header).split(";")) {
    const eq = part.indexOf("=");
    if (eq === -1) continue;
    const k = part.slice(0, eq).trim();
    const v = part.slice(eq + 1).trim();
    if (k) out[k] = decodeURIComponent(v);
  }
  return out;
}

/** Build Set-Cookie header (HttpOnly, SameSite=Lax). */
function buildCookie(name, value, options = {}) {
  const parts = [`${name}=${encodeURIComponent(value)}`];
  parts.push("Path=" + (options.path || "/"));
  parts.push("HttpOnly");
  parts.push("SameSite=Lax");
  if (options.maxAge) parts.push("Max-Age=" + options.maxAge);
  if (options.expires) parts.push("Expires=" + options.expires);
  if (options.secure) parts.push("Secure");
  return parts.join("; ");
}

module.exports = {
  SecurityManager,
  SUSPICIOUS_PATTERNS,
  parseCookies,
  buildCookie,
  getIp,
  constantTimeEqual,
};
