/**
 * lib/security.js — Application-layer security (auth, CSRF, WAF patterns)
 * ───────────────────────────────────────────────────────────────────────
 * Lapisan paling dekat ke aplikasi. Untuk DDoS dan rate-limit yang lebih
 * dalam, lihat lib/antiDdos.js. Untuk lockdown, lihat lib/lockdown.js.
 *
 * Fitur:
 *   • Session management (sid + IP-binding + TTL)
 *   • CSRF token (per-session)
 *   • Login dengan password hashing scrypt
 *   • Brute-force protection (block after N fails)
 *   • Soft rate-limit (untuk konsumen yang juga lewat antiDdos)
 *   • WAF pattern matching (SQLi, XSS, RCE, traversal, SSRF, JNDI, dll)
 *   • Cookie parser & builder yang anti prototype-pollution
 *   • Body-size guard (selain antiDdos hard cap)
 *   • Request fingerprinting untuk audit
 */

"use strict";

const crypto = require("crypto");
const { sleep } = require("./utils");

// ─── WAF patterns ────────────────────────────────────────────
const SUSPICIOUS_PATTERNS = [
  // Path traversal
  { name: "path-traversal",   re: /\.\.[\\/]|%2e%2e[%2f%5c]|\.\.;/i,                weight: 4 },
  { name: "double-encode",    re: /%25(?:2f|5c|2e)/i,                                weight: 3 },
  { name: "null-byte",        re: /%00|\x00/,                                        weight: 5 },
  // SQL injection
  { name: "sql-union",        re: /\bunion\s+(all\s+)?select\b/i,                    weight: 6 },
  { name: "sql-tautology",    re: /\b(or|and)\s+\d+=\d+(\s|--|#|$)/i,                 weight: 6 },
  { name: "sql-comment",      re: /(--\s|#|\/\*).*?(drop|insert|update|select)/i,   weight: 4 },
  { name: "sql-dump",         re: /\b(information_schema|sleep\s*\(|benchmark\s*\(|load_file\s*\()/i, weight: 5 },
  { name: "sql-stack",        re: /;\s*(drop|delete|truncate|alter)\s+/i,            weight: 7 },
  // XSS
  { name: "xss-script",       re: /<script[\s>]/i,                                   weight: 5 },
  { name: "xss-handler",      re: /\bon[a-z]+\s*=\s*["']?[^"'>]+/i,                  weight: 4 },
  { name: "xss-protocol",     re: /javascript:|data:text\/html/i,                    weight: 5 },
  { name: "xss-svg",          re: /<svg[^>]*on[a-z]+=/i,                              weight: 5 },
  // RCE / shell
  { name: "rce-shell",        re: /;\s*(rm\s+-rf|wget\s|curl\s|nc\s+(-e|-l)|cmd\.exe|powershell|bash\s+-i)/i, weight: 7 },
  { name: "rce-eval",         re: /\b(?:eval|system|exec|passthru|shell_exec|popen)\s*\(/i, weight: 6 },
  { name: "rce-template",     re: /\$\{[^}]*\}|\{\{[^}]+\}\}|<%[^%]*%>/,             weight: 3 },
  // SSRF / Server probe
  { name: "ssrf-localhost",   re: /(?:^|[^a-z0-9])(?:127\.0\.0\.1|0\.0\.0\.0|169\.254|::1)/i, weight: 4 },
  { name: "ssrf-meta",        re: /169\.254\.169\.254|metadata\.google|169\.254\.170\.2/i, weight: 7 },
  // JNDI / Log4Shell
  { name: "jndi-lookup",      re: /\$\{(?:jndi|env|sys|java|lower|upper):/i,         weight: 9 },
  // PHP probes
  { name: "php-wrapper",      re: /php:\/\/(?:filter|input|memory|expect)/i,        weight: 6 },
  { name: "data-wrapper",     re: /data:\/\/.+;base64/i,                              weight: 5 },
  // Common admin/scanner probes
  { name: "wp-probe",         re: /\/(?:wp-admin|wp-login|wp-content|wp-includes|xmlrpc\.php)/i, weight: 4 },
  { name: "phpmyadmin-probe", re: /\/(?:phpmyadmin|adminer|setup\.php|pma|sqladmin)/i, weight: 4 },
  { name: "env-leak",         re: /\.env(?:\W|$)|\.git\/(?:config|HEAD)|\.aws\/credentials|id_rsa/i, weight: 5 },
  { name: "shell-script",     re: /\.(?:sh|bash|cgi|pl)\?[a-z]+=/i,                  weight: 3 },
  // Header / response splitting
  { name: "crlf-injection",   re: /(?:%0d%0a|%0a|\r\n)\s*(?:set-cookie|content-type|location):/i, weight: 6 },
  // LDAP / XML
  { name: "ldap-injection",   re: /\(\s*(?:\||&|!)\s*\(\s*(?:objectClass|cn|uid)/i, weight: 4 },
  { name: "xxe-entity",       re: /<!ENTITY\s+\w+\s+SYSTEM/i,                        weight: 7 },
  // Prototype-pollution hint
  { name: "proto-pollution",  re: /__proto__|constructor\.prototype|\["__proto__"\]/i, weight: 5 },
  // Bad UA / scanners
  { name: "scanner-ua",       re: /(?:sqlmap|nikto|nessus|acunetix|burpsuite|w3af|fimap|havij|pangolin|zaproxy)/i, weight: 6 },
  // Known exploit IDs
  { name: "exploit-cve",      re: /\bCVE-\d{4}-\d{4,}\b/,                            weight: 2 },
  // Zip-slip / archive traversal
  { name: "zip-slip",         re: /\.\.[\\/]/i,                                      weight: 4 },
  // Backup / config probes
  { name: "config-probe",     re: /\.(?:bak|swp|old|orig|backup|save|tar|gz|zip)$/i, weight: 2 },
];

// Pattern set kompilasi sekali, biar regex test cepat
class SecurityManager {
  constructor(cfg, logger, options = {}) {
    this.cfg     = cfg;
    this.logger  = logger;
    this.opts    = options;

    this.sessions       = new Map();
    this.sessionTTL     = (cfg.SESSION_TTL_HOURS || 12) * 3600 * 1000;

    this.requestBuckets = new Map();
    this.failedLogins   = new Map();
    this.suspicionLog   = [];

    this.localToken     = crypto.randomBytes(32).toString("hex");
    this._otp           = new Map();
  }

  _newSid() { return crypto.randomBytes(24).toString("hex"); }

  static hashPassword(password, salt = null) {
    salt = salt || crypto.randomBytes(16).toString("hex");
    const hash = crypto.scryptSync(password, salt, 32, { N: 16384, r: 8, p: 1 }).toString("hex");
    return `scrypt:${salt}:${hash}`;
  }

  static verifyPassword(password, stored) {
    if (!stored || typeof stored !== "string") return false;
    if (!stored.startsWith("scrypt:")) {
      // Plain fallback (legacy) — banding dengan timing-safe
      if (typeof password !== "string" || password.length !== stored.length) {
        // tetap lakukan timingSafeEqual dummy untuk lawan timing attack
        try { crypto.timingSafeEqual(Buffer.alloc(32), Buffer.alloc(32)); } catch (_) {}
        return false;
      }
      try {
        return crypto.timingSafeEqual(Buffer.from(stored), Buffer.from(password));
      } catch (_) { return false; }
    }
    try {
      const [, salt, hashHex] = stored.split(":");
      const want = Buffer.from(hashHex, "hex");
      const got  = crypto.scryptSync(password, salt, 32, { N: 16384, r: 8, p: 1 });
      if (want.length !== got.length) return false;
      return crypto.timingSafeEqual(want, got);
    } catch (_) { return false; }
  }

  isBlocked(ip) {
    const f = this.failedLogins.get(ip);
    if (!f) return false;
    if (f.blockUntil && Date.now() < f.blockUntil) return true;
    if (f.blockUntil && Date.now() >= f.blockUntil) this.failedLogins.delete(ip);
    return false;
  }

  recordFailedLogin(ip) {
    const f = this.failedLogins.get(ip) || { count: 0, blockUntil: 0 };
    f.count++;
    if (f.count >= this.cfg.SEC_BLOCK_ON_FAIL) {
      // Exponential backoff: tiap kali break, durasi block × 2 (max 24 jam)
      const exponent = Math.min(8, f.count - this.cfg.SEC_BLOCK_ON_FAIL);
      const dur = Math.min(24 * 3600,
        this.cfg.SEC_BLOCK_DURATION * Math.pow(2, exponent));
      f.blockUntil = Date.now() + dur * 1000;
      this.logger.security(
        `IP ${ip} diblokir ${dur}s setelah ${f.count} login gagal (backoff ×${Math.pow(2,exponent)}).`,
        { ip, until: new Date(f.blockUntil).toISOString(), failed: f.count }
      );
    } else {
      this.logger.security(`Login gagal dari ${ip} (${f.count}/${this.cfg.SEC_BLOCK_ON_FAIL})`, { ip });
    }
    this.failedLogins.set(ip, f);
  }

  recordSuccessLogin(ip) {
    this.failedLogins.delete(ip);
  }

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

  createSession(ip, ua = "") {
    const sid  = this._newSid();
    const csrf = crypto.randomBytes(16).toString("hex");
    this.sessions.set(sid, {
      ip, ua: String(ua).slice(0, 256),
      createdAt: Date.now(), lastSeen: Date.now(),
      csrf,
    });
    this._gcSessions();
    return { sid, csrf };
  }

  validateSession(sid, ip, ua = "") {
    if (!sid || typeof sid !== "string") return null;
    if (sid.length > 256) return null;
    const s = this.sessions.get(sid);
    if (!s) return null;
    if (Date.now() - s.lastSeen > this.sessionTTL) {
      this.sessions.delete(sid);
      return null;
    }
    // IP rebinding check
    if (s.ip !== ip) {
      this.logger.security(`SID dipakai dari IP berbeda (orig=${s.ip}, now=${ip}). Sesi dibatalkan.`, { ip });
      this.sessions.delete(sid);
      return null;
    }
    // UA fingerprint binding (longgar — hanya flag, bukan reject)
    const uaShort = String(ua).slice(0, 256);
    if (s.ua && uaShort && s.ua !== uaShort) {
      this.logger.security(`UA mismatch untuk SID. orig="${s.ua.slice(0,40)}…" now="${uaShort.slice(0,40)}…"`, { ip });
    }
    s.lastSeen = Date.now();
    return s;
  }

  destroySession(sid) {
    if (sid) this.sessions.delete(sid);
  }

  _gcSessions() {
    const now = Date.now();
    let purged = 0;
    for (const [sid, s] of this.sessions) {
      if (now - s.lastSeen > this.sessionTTL) {
        this.sessions.delete(sid); purged++;
      }
    }
    // Cap absolute number of sessions
    if (this.sessions.size > 5000) {
      // Remove oldest
      const sorted = [...this.sessions.entries()].sort((a, b) => a[1].lastSeen - b[1].lastSeen);
      const toRemove = sorted.slice(0, this.sessions.size - 5000);
      for (const [sid] of toRemove) this.sessions.delete(sid);
    }
    return purged;
  }

  validateCsrf(session, headerToken) {
    if (!session || !headerToken) return false;
    if (typeof headerToken !== "string") return false;
    if (session.csrf.length !== headerToken.length) return false;
    try {
      return crypto.timingSafeEqual(Buffer.from(session.csrf), Buffer.from(headerToken));
    } catch (_) { return false; }
  }

  /** Cek Host & Origin header. */
  checkOrigin(req) {
    const host   = String(req.headers.host || "");
    const origin = String(req.headers.origin || "");
    const allowedHosts = [
      `localhost:${this.cfg.GUI_PORT}`,
      `127.0.0.1:${this.cfg.GUI_PORT}`,
      `[::1]:${this.cfg.GUI_PORT}`,
    ];
    if (this.cfg.GUI_BIND === "0.0.0.0") {
      return { ok: true, host }; // longgar — tapi kita logger semua
    }
    const okHost = allowedHosts.some((h) => host === h);
    if (!okHost) {
      this.logger.security(`Host header mencurigakan: ${host}`, { host, origin });
      return { ok: false, host };
    }
    if (origin) {
      const okOrigin = allowedHosts.some((h) => origin === "http://" + h || origin === "https://" + h);
      if (!okOrigin) {
        this.logger.security(`Origin tidak diizinkan: ${origin}`, { host, origin });
        return { ok: false, host, origin };
      }
    }
    return { ok: true, host };
  }

  /** Scan request URL+UA untuk pola berbahaya. Decode URL dulu agar
      payload yang ter-encode tetap bisa kena match. */
  scanSuspicious(req) {
    const rawUrl  = String(req.url || "").slice(0, 4096);
    const ua      = String(req.headers["user-agent"] || "").slice(0, 1024);
    const referer = String(req.headers.referer || "").slice(0, 2048);
    // CATATAN: JANGAN sertakan Host header dalam target scan — Host = "127.0.0.1:..."
    // wajar untuk request lokal dan bisa salah match pola SSRF. Host header
    // diperiksa terpisah di checkOrigin().

    // Decode URL beberapa kali (counter double-encoding) — tapi cap supaya
    // tidak loop forever. Kalau decode error, pakai apa adanya.
    let decoded = rawUrl;
    for (let i = 0; i < 3; i++) {
      try {
        const next = decodeURIComponent(decoded);
        if (next === decoded) break;
        decoded = next;
      } catch (_) { break; }
    }

    const target = rawUrl + " " + decoded + " " + ua + " " + referer;

    let highest = null;
    let totalWeight = 0;
    const matched = [];

    for (const pat of SUSPICIOUS_PATTERNS) {
      if (pat.re.test(target)) {
        matched.push(pat.name);
        totalWeight += pat.weight;
        if (!highest || pat.weight > highest.weight) highest = pat;
      }
    }

    if (matched.length) {
      this.logger.security(`Pola berbahaya: ${matched.join(",")} (skor ${totalWeight}) → ${rawUrl.slice(0,80)}`,
        { ip: getIp(req), ua: ua.slice(0,80), patterns: matched, weight: totalWeight });
      this.suspicionLog.push({
        at: Date.now(), ip: getIp(req), patterns: matched,
        weight: totalWeight, url: rawUrl.slice(0, 200),
      });
      if (this.suspicionLog.length > 500) this.suspicionLog.shift();
      return { suspicious: true, pattern: highest.name, weight: totalWeight, all: matched };
    }
    return { suspicious: false };
  }

  /** Public path check — lebih ketat dari versi sebelumnya. */
  isPublicPath(p) {
    if (typeof p !== "string") return false;
    const pub = new Set([
      "/", "/login", "/login.html", "/index.html",
      "/api/login", "/api/logout", "/api/health", "/api/whoami",
      "/styles.css", "/app.js", "/wizard.js", "/logs.js",
      "/topzone-logo.svg", "/favicon.ico",
    ]);
    return pub.has(p) || p === "/";
  }

  oneTimeToken() {
    const t = crypto.randomBytes(24).toString("hex");
    this._otp.set(t, Date.now() + 5 * 60 * 1000);
    if (this._otp.size > 100) {
      // Purge yang expired
      const now = Date.now();
      for (const [k, exp] of this._otp) if (exp <= now) this._otp.delete(k);
    }
    return t;
  }
  consumeOtp(t) {
    if (typeof t !== "string") return false;
    const exp = this._otp.get(t);
    if (!exp) return false;
    this._otp.delete(t);
    return Date.now() < exp;
  }

  stats() {
    return {
      activeSessions: this.sessions.size,
      blockedIps    : [...this.failedLogins.entries()]
                        .filter(([, v]) => v.blockUntil > Date.now())
                        .map(([ip, v]) => ({ ip, until: v.blockUntil, failed: v.count })),
      suspicionCount: this.suspicionLog.length,
      lastSuspicions: this.suspicionLog.slice(-15),
    };
  }
}

function constantTimeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string") return false;
  if (a.length !== b.length) {
    try { crypto.timingSafeEqual(Buffer.alloc(32), Buffer.alloc(32)); } catch (_) {}
    return false;
  }
  try { return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b)); }
  catch (_) { return false; }
}

function getIp(req) {
  // Prefer socket address (real). X-Forwarded-For hanya dipakai kalau
  // GUI dibalik proxy. Default: abaikan untuk hindari spoofing.
  return (req.socket && req.socket.remoteAddress) || "?";
}

/** Parser cookie aman dari prototype pollution. */
function parseCookies(header) {
  // Object.create(null) — tidak ada __proto__ chain
  const out = Object.create(null);
  if (!header || typeof header !== "string") return out;
  if (header.length > 8192) return out;
  for (const part of header.split(";")) {
    const eq = part.indexOf("=");
    if (eq === -1) continue;
    const k = part.slice(0, eq).trim();
    if (!k || k === "__proto__" || k === "constructor" || k === "prototype") continue;
    let v = part.slice(eq + 1).trim();
    try { v = decodeURIComponent(v); } catch (_) { /* keep raw */ }
    out[k] = v;
  }
  return out;
}

/** Build Set-Cookie header. */
function buildCookie(name, value, options = {}) {
  if (!/^[a-zA-Z0-9_-]+$/.test(String(name))) {
    throw new Error("Cookie name tidak valid");
  }
  const parts = [`${name}=${encodeURIComponent(String(value))}`];
  parts.push("Path=" + (options.path || "/"));
  parts.push("HttpOnly");
  parts.push("SameSite=" + (options.sameSite || "Lax"));
  if (options.maxAge !== undefined) parts.push("Max-Age=" + Math.max(0, parseInt(options.maxAge, 10)));
  if (options.expires) parts.push("Expires=" + options.expires);
  if (options.secure) parts.push("Secure");
  return parts.join("; ");
}

/** Header generic security yang dipasang ke semua response. */
function buildSecurityHeaders(opts = {}) {
  return {
    "X-Content-Type-Options" : "nosniff",
    "X-Frame-Options"        : opts.frameOptions || "DENY",
    "Referrer-Policy"        : "no-referrer",
    "Permissions-Policy"     : "interest-cohort=(), camera=(), microphone=(), geolocation=()",
    "Cross-Origin-Opener-Policy": "same-origin",
    "Cross-Origin-Resource-Policy": "same-site",
    "Strict-Transport-Security": opts.hsts || "max-age=31536000; includeSubDomains",
    "Content-Security-Policy": opts.csp ||
      "default-src 'self'; " +
      "style-src 'self' 'unsafe-inline'; " +
      "script-src 'self'; " +
      "img-src 'self' data:; " +
      "connect-src 'self'; " +
      "font-src 'self' data:; " +
      "object-src 'none'; " +
      "base-uri 'self'; " +
      "frame-ancestors 'none'; " +
      "form-action 'self'",
  };
}

/** Validasi JSON body dengan depth & key-count limit (anti JSON bomb). */
function safeParseJson(raw, opts = {}) {
  const maxBytes  = opts.maxBytes || 96 * 1024;
  const maxDepth  = opts.maxDepth || 8;
  const maxKeys   = opts.maxKeys  || 200;

  if (!raw || typeof raw !== "string") return { ok: true, value: null };
  if (raw.length > maxBytes) return { ok: false, error: "Body terlalu besar" };

  // Quick reject prototype-pollution attempts BEFORE parsing
  if (/"__proto__"\s*:|"constructor"\s*:\s*\{|"prototype"\s*:/i.test(raw)) {
    return { ok: false, error: "Body mengandung pola pollution" };
  }

  let parsed;
  try { parsed = JSON.parse(raw); }
  catch (e) { return { ok: false, error: "JSON tidak valid: " + e.message }; }

  // Walk untuk hitung depth & keys
  let keyCount = 0;
  function walk(v, depth) {
    if (depth > maxDepth) throw new Error("Nested terlalu dalam");
    if (Array.isArray(v)) {
      for (const item of v) walk(item, depth + 1);
    } else if (v && typeof v === "object") {
      for (const k of Object.keys(v)) {
        if (k === "__proto__" || k === "constructor" || k === "prototype") {
          throw new Error("Key terlarang: " + k);
        }
        keyCount++;
        if (keyCount > maxKeys) throw new Error("Terlalu banyak key");
        walk(v[k], depth + 1);
      }
    }
  }
  try { walk(parsed, 0); }
  catch (e) { return { ok: false, error: e.message }; }

  return { ok: true, value: parsed };
}

module.exports = {
  SecurityManager,
  SUSPICIOUS_PATTERNS,
  parseCookies,
  buildCookie,
  buildSecurityHeaders,
  safeParseJson,
  getIp,
  constantTimeEqual,
};
