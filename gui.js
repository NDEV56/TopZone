#!/usr/bin/env node
/**
 * gui.js — TopZone Web Control Panel (HARDENED v3.1)
 * ══════════════════════════════════════════════════
 * Versi ini di-harden dengan:
 *   • lib/firewall.js   — unified WAF + DDoS + lockdown
 *   • lib/antiDdos.js   — multi-layer rate limiting & connection cap
 *   • lib/lockdown.js   — emergency lockdown (auto + manual)
 *   • lib/security.js   — CSRF, session, scrypt password, WAF patterns
 *
 * Default bind 127.0.0.1 + lockdown otomatis bila threshold serangan
 * tertembus + body-size cap 96 KB + SSE max-clients-per-IP + JSON-bomb-safe
 * + path-traversal-proof static + security headers default-deny.
 */

"use strict";

const http     = require("http");
const fs       = require("fs");
const path     = require("path");
const url      = require("url");
const crypto   = require("crypto");
const { exec } = require("child_process");

const config         = require("./lib/config");
const { Controller } = require("./lib/controller");
const detector       = require("./lib/detector");
const { c, badge }   = require("./lib/colors");
const {
  parseCookies, buildCookie, buildSecurityHeaders, getIp, SecurityManager,
} = require("./lib/security");
const {
  parseArgs, isValidPort, looksLikeNgrokToken, formatDuration,
  truncate, getHostInfo, ensureDir,
} = require("./lib/utils");

const { flags } = parseArgs();

// ─────────────────────────────────────────────────
//  Setup controller + security stack
// ─────────────────────────────────────────────────
const controller = new Controller({ echoConsole: false }).bootstrap();
const cfg        = controller.cfg;
const logger     = controller.logger;
const security   = controller.security;
const updater    = controller.updater;
const antiDdos   = controller.antiDdos;
const lockdown   = controller.lockdown;
const firewall   = controller.firewall;

// SSE: cap berapa client per IP (anti DoS via SSE)
const SSE_MAX_CLIENTS_TOTAL  = 50;
const SSE_MAX_CLIENTS_PER_IP = 3;

// ─────────────────────────────────────────────────
//  MIME map (whitelist ketat)
// ─────────────────────────────────────────────────
const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css" : "text/css; charset=utf-8",
  ".js"  : "application/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg" : "image/svg+xml",
  ".png" : "image/png",
  ".jpg" : "image/jpeg",
  ".jpeg": "image/jpeg",
  ".webp": "image/webp",
  ".ico" : "image/x-icon",
  ".woff": "font/woff",
  ".woff2": "font/woff2",
  ".txt" : "text/plain; charset=utf-8",
};

// ─────────────────────────────────────────────────
//  Helpers HTTP
// ─────────────────────────────────────────────────
function sendJson(res, status, data, extraHeaders = {}) {
  if (res.headersSent) return;
  // Always re-apply security headers
  const headers = {
    ...buildSecurityHeaders(),
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store, no-cache, must-revalidate, private",
    "Pragma": "no-cache",
    "X-Content-Type-Options": "nosniff",
    ...extraHeaders,
  };
  res.writeHead(status, headers);
  let body;
  try { body = JSON.stringify(data); }
  catch (_) { body = '{"error":"serialize"}'; }
  res.end(body);
}

function sendText(res, status, text, contentType = "text/plain; charset=utf-8") {
  if (res.headersSent) return;
  res.writeHead(status, {
    ...buildSecurityHeaders(),
    "Content-Type": contentType,
    "Cache-Control": "no-store",
  });
  res.end(text);
}

function sendFile(res, file, status = 200) {
  fs.stat(file, (err, st) => {
    if (err || !st.isFile()) return sendText(res, 404, "Not found");
    if (st.size > 8 * 1024 * 1024) return sendText(res, 413, "File too large");
    const ext = path.extname(file).toLowerCase();
    const ct  = MIME[ext];
    if (!ct) return sendText(res, 415, "Unsupported");
    res.writeHead(status, {
      ...buildSecurityHeaders({
        // CSP: izinkan inline style untuk halaman index.html (kita butuh)
        csp: "default-src 'self'; style-src 'self' 'unsafe-inline'; " +
             "script-src 'self'; img-src 'self' data:; " +
             "connect-src 'self'; font-src 'self' data:; " +
             "object-src 'none'; base-uri 'self'; " +
             "frame-ancestors 'none'; form-action 'self'",
      }),
      "Content-Type" : ct,
      "Cache-Control": "no-cache, must-revalidate",
      "Content-Length": st.size,
    });
    fs.createReadStream(file).pipe(res);
  });
}

// ─────────────────────────────────────────────────
//  Static dir — path traversal proof
// ─────────────────────────────────────────────────
const PUBLIC_DIR = path.resolve(path.join(__dirname, "public"));

function serveStatic(req, res, p) {
  // Reject suspect early
  if (typeof p !== "string" || p.length > 256) return sendText(res, 400, "Bad path");
  if (p.includes("\0")) return sendText(res, 400, "Bad path");
  // Strip leading slash (jangan biarkan absolute path masuk)
  const safe = p.replace(/^[\\/]+/, "");
  // Kompak: gabung lalu resolve, lalu cek prefix
  const candidate = path.resolve(path.join(PUBLIC_DIR, safe));
  if (!candidate.startsWith(PUBLIC_DIR + path.sep) && candidate !== PUBLIC_DIR) {
    logger.security(`Path traversal attempt: ${p}`, { ip: getIp(req) });
    return sendText(res, 400, "Bad path");
  }
  return sendFile(res, candidate);
}

// ─────────────────────────────────────────────────
//  Auth helpers
// ─────────────────────────────────────────────────
function getSession(req) {
  const cookies = parseCookies(req.headers.cookie || "");
  if (!cookies.tz_sid) return null;
  return security.validateSession(cookies.tz_sid, getIp(req), req.headers["user-agent"] || "");
}

function isLocalOnly() {
  return cfg.GUI_BIND === "127.0.0.1" || cfg.GUI_BIND === "localhost" || cfg.GUI_BIND === "::1";
}

function requireAuth(req, res) {
  // Kalau GUI_PASSWORD kosong dan bind hanya ke loopback → boleh anonim
  if (!cfg.GUI_PASSWORD && isLocalOnly()) return { ok: true, anon: true };

  const session = getSession(req);
  if (!session) {
    sendJson(res, 401, { error: "Auth diperlukan", needLogin: true });
    return { ok: false };
  }
  return { ok: true, session };
}

// ─────────────────────────────────────────────────
//  SSE clients
// ─────────────────────────────────────────────────
const sseClients = new Set();
const sseByIp    = new Map(); // ip -> count

function broadcastSse(eventName, payload) {
  let data;
  try {
    data = `event: ${eventName}\ndata: ${JSON.stringify(payload)}\n\n`;
  } catch (_) { return; }
  for (const client of sseClients) {
    try { client.write(data); } catch (_) {}
  }
}

logger.on("entry",        (e) => broadcastSse("log",      e));
controller.on("phase",    (phase, state) => broadcastSse("phase",  { phase, state }));
controller.on("lockdown", (entry) => broadcastSse("lockdown", entry));
controller.on("ddos",     (info)  => broadcastSse("ddos",     info));

// Heartbeat
const heartbeat = setInterval(() => broadcastSse("ping", { t: Date.now() }), 25000);
if (heartbeat.unref) heartbeat.unref();

// ─────────────────────────────────────────────────
//  ROUTES
// ─────────────────────────────────────────────────
const routes = Object.create(null);

function route(method, p, handler) {
  routes[`${method} ${p}`] = handler;
}

// ─── Static & root ──────────────────────────────
route("GET", "/", (req, res) => sendFile(res, path.join(PUBLIC_DIR, "index.html")));
route("GET", "/login", (req, res) => sendFile(res, path.join(PUBLIC_DIR, "login.html")));
route("GET", "/favicon.ico", (req, res) => {
  const ico = path.join(PUBLIC_DIR, "favicon.ico");
  if (fs.existsSync(ico)) return sendFile(res, ico);
  return sendText(res, 204, "");
});

// ─── Health (always-on) ─────────────────────────
route("GET", "/api/health", (req, res) => sendJson(res, 200, {
  ok: true, version: "3.1.0", t: Date.now(),
  lockdownLevel: lockdown.level,
}));

// ─── Whoami ─────────────────────────────────────
route("GET", "/api/whoami", (req, res) => {
  const session = getSession(req);
  return sendJson(res, 200, {
    authenticated: !!session || (!cfg.GUI_PASSWORD && isLocalOnly()),
    needPassword : !!cfg.GUI_PASSWORD,
    csrf         : session ? session.csrf : null,
    bind         : cfg.GUI_BIND,
    isConfigured : config.isConfigured(),
    lockdownLevel: lockdown.level,
    // Sembunyikan host info kalau panel terbuka ke jaringan tanpa auth
    host         : (!session && !isLocalOnly()) ? null : getHostInfo(),
  });
});

// ─── Login (no CSRF — login itu sendiri yang membuat sesi) ─
route("POST", "/api/login", async (req, res) => {
  const ip = getIp(req);
  if (security.isBlocked(ip)) {
    return sendJson(res, 429, {
      error: "IP kamu diblokir sementara karena terlalu banyak login gagal.",
    });
  }
  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 8 * 1024 }); }
  catch (e) { return sendJson(res, 413, { error: "Body invalid: " + e.message }); }

  const password = String(body.password || "");
  // OTP token (auto-login dari CLI)
  if (body.otp && security.consumeOtp(body.otp)) {
    const ua = req.headers["user-agent"] || "";
    const { sid, csrf } = security.createSession(ip, ua);
    res.setHeader("Set-Cookie", buildCookie("tz_sid", sid, {
      maxAge: 12 * 3600, sameSite: "Strict",
    }));
    return sendJson(res, 200, { ok: true, csrf });
  }
  if (!cfg.GUI_PASSWORD) {
    return sendJson(res, 400, { error: "GUI_PASSWORD belum diset, tapi auth diminta. Edit .env." });
  }
  // Constant-time delay (anti timing attack & throttle login)
  const delayMs = 250 + Math.floor(Math.random() * 250);
  await new Promise((r) => setTimeout(r, delayMs));

  const ok = SecurityManager.verifyPassword(password, cfg.GUI_PASSWORD);
  if (!ok) {
    security.recordFailedLogin(ip);
    controller.lockdown.reportIncident({ ip, weight: 2, reason: "login-fail" });
    // Generic error — jangan beda antara user-not-found & wrong-password
    return sendJson(res, 401, { error: "Password salah." });
  }
  security.recordSuccessLogin(ip);
  const ua = req.headers["user-agent"] || "";
  const { sid, csrf } = security.createSession(ip, ua);
  res.setHeader("Set-Cookie", buildCookie("tz_sid", sid, {
    maxAge: 12 * 3600, sameSite: "Strict",
  }));
  logger.security(`Login GUI sukses dari ${ip}`, { ip });
  return sendJson(res, 200, { ok: true, csrf });
});

route("POST", "/api/logout", async (req, res) => {
  const cookies = parseCookies(req.headers.cookie || "");
  if (cookies.tz_sid) security.destroySession(cookies.tz_sid);
  res.setHeader("Set-Cookie", buildCookie("tz_sid", "", { maxAge: 0 }));
  return sendJson(res, 200, { ok: true });
});

// ─── Status snapshot ────────────────────────────
route("GET", "/api/status", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, controller.snapshot());
});

// ─── Logs ───────────────────────────────────────
route("GET", "/api/logs", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const u = url.parse(req.url, true);
  const level = String(u.query.level || "all");
  const count = Math.min(parseInt(u.query.count, 10) || 200, 1000);
  return sendJson(res, 200, {
    entries: logger.recent(count, level === "all" ? null : level),
    stats  : logger.stats(),
    dates  : logger.listArchiveDates(),
  });
});

route("GET", "/api/logs/archive", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const u = url.parse(req.url, true);
  const level = String(u.query.level || "common").replace(/[^a-z]/g, "").slice(0, 20);
  const date  = String(u.query.date || "").match(/^\d{4}-\d{2}-\d{2}$/)?.[0];
  return sendJson(res, 200, { entries: logger.readArchive(level || "common", date, 1000) });
});

route("POST", "/api/logs/clear", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  logger.reset();
  logger.uncommon("Buffer log dibersihkan oleh user.");
  return sendJson(res, 200, { ok: true });
});

route("POST", "/api/logs/prune", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 1024 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }
  const days = Math.max(1, Math.min(365, parseInt(body.days, 10) || cfg.LOG_RETENTION_DAYS));
  const removed = logger.prune(days);
  logger.uncommon(`Pruning log ${days} hari: ${removed} file dihapus.`);
  return sendJson(res, 200, { ok: true, removed });
});

// ─── SSE log stream ─────────────────────────────
route("GET", "/api/stream", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;

  const ip = getIp(req);
  if (sseClients.size >= SSE_MAX_CLIENTS_TOTAL) {
    return sendJson(res, 503, { error: "Terlalu banyak SSE client global." });
  }
  const ipCount = sseByIp.get(ip) || 0;
  if (ipCount >= SSE_MAX_CLIENTS_PER_IP) {
    return sendJson(res, 429, { error: "Terlalu banyak SSE client dari IP ini." });
  }

  res.writeHead(200, {
    "Content-Type"     : "text/event-stream",
    "Cache-Control"    : "no-cache, no-transform",
    "Connection"       : "keep-alive",
    "X-Accel-Buffering": "no",
    "X-Content-Type-Options": "nosniff",
  });
  res.write("retry: 5000\n\n");
  res.write(`event: hello\ndata: ${JSON.stringify({ t: Date.now() })}\n\n`);
  sseClients.add(res);
  sseByIp.set(ip, ipCount + 1);
  req.on("close", () => {
    sseClients.delete(res);
    const cur = (sseByIp.get(ip) || 1) - 1;
    if (cur <= 0) sseByIp.delete(ip);
    else sseByIp.set(ip, cur);
  });
});

// ─── Diagnose ───────────────────────────────────
route("GET", "/api/diagnose", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const d = await detector.diagnose();
  return sendJson(res, 200, d);
});

// ─── Server lifecycle ───────────────────────────
route("POST", "/api/server/start", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  if (controller.state.phase === "online") {
    return sendJson(res, 409, { error: "Server sudah online." });
  }
  controller.startAll().catch((e) => logger.error("startAll gagal: " + e.message));
  return sendJson(res, 202, { ok: true, phase: controller.state.phase });
});

route("POST", "/api/server/stop", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  await controller.shutdown("user-request");
  return sendJson(res, 200, { ok: true });
});

route("POST", "/api/server/restart", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  await controller.shutdown("restart");
  setTimeout(() => {
    controller.startAll().catch((e) => logger.error("Restart gagal: " + e.message));
  }, 500);
  return sendJson(res, 202, { ok: true });
});

// ─── Setup wizard ───────────────────────────────
route("POST", "/api/setup/save", async (req, res) => {
  // Boleh dipanggil sebelum auth karena belum dikonfigurasi
  const ip = getIp(req);
  // Reject kalau sudah configured + tidak ada session — anti hijack
  if (config.isConfigured()) {
    const auth = requireAuth(req, res); if (!auth.ok) return;
  }

  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 16 * 1024 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }

  const errs = [];
  const provider = String(body.provider || "ngrok").toLowerCase();
  if (!["ngrok","cloudflared","localtunnel","serveo","pinggy","none"].includes(provider))
    errs.push("Provider tidak dikenal.");
  if (provider === "ngrok" && !looksLikeNgrokToken(body.ngrokToken || "")) {
    errs.push("Token ngrok tidak valid.");
  }
  const mode = String(body.mode || "auto").toLowerCase();
  if (!["auto","xampp","laragon","wamp","mamp","ampps","openserver","usbwebserver","easyphp","php","custom"].includes(mode))
    errs.push("Mode server tidak dikenal.");
  if (mode === "custom" && !isValidPort(body.localPort)) errs.push("Port custom tidak valid.");

  if (errs.length) return sendJson(res, 400, { error: errs.join(" ") });

  let pwd = body.guiPassword ? String(body.guiPassword) : "";
  if (pwd && pwd.length < 8) return sendJson(res, 400, { error: "Password minimal 8 karakter." });
  if (pwd) pwd = SecurityManager.hashPassword(pwd);

  // Sanitasi PHP_ROOT — harus folder relatif/absolut, bukan URL
  let phpRoot = body.phpRoot ? String(body.phpRoot).trim() : path.join(__dirname, "Home");
  if (/^https?:\/\//i.test(phpRoot)) phpRoot = path.join(__dirname, "Home");

  const updates = {
    NGROK_AUTHTOKEN : String(body.ngrokToken || "").slice(0, 200),
    NGROK_DOMAIN    : String(body.ngrokDomain || "").slice(0, 200),
    SERVER_MODE     : mode,
    LOCAL_PORT      : mode === "custom" ? String(parseInt(body.localPort, 10) || 0) : "0",
    PHP_PORT        : String(parseInt(body.phpPort, 10) || 8080),
    PHP_ROOT        : phpRoot,
    TUNNEL_PROVIDER : provider,
    TUNNEL_FALLBACK : body.tunnelFallback ? "true" : "false",
    GUI_PASSWORD    : pwd,
    GUI_PORT        : isValidPort(body.guiPort) ? String(parseInt(body.guiPort, 10)) : String(cfg.GUI_PORT),
    AUTO_UPDATE     : ["ask","true","false"].includes(body.autoUpdate) ? body.autoUpdate : "ask",
    LOG_REQUESTS    : body.logRequests === false ? "false" : "true",
  };
  config.writeEnv(updates);
  config.writeEnvExample();

  logger.security(`Setup wizard disimpan dari ${ip}`, { provider, mode });
  return sendJson(res, 200, { ok: true, restart: true });
});

// ─── Settings ───────────────────────────────────
route("GET", "/api/settings", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, controller._publicConfig());
});

route("POST", "/api/settings", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 16 * 1024 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }

  const allowed = new Set(["NGROK_AUTHTOKEN","NGROK_DOMAIN","SERVER_MODE","LOCAL_PORT",
                   "PHP_PORT","PHP_ROOT","TUNNEL_PROVIDER","TUNNEL_FALLBACK",
                   "AUTO_UPDATE","LOG_REQUESTS","LOG_MIN_LEVEL","LOG_RETENTION_DAYS",
                   "GUI_AUTO_OPEN","SEC_RATE_LIMIT","SEC_BLOCK_ON_FAIL","SEC_BLOCK_DURATION"]);
  const patch = Object.create(null);
  for (const k of Object.keys(body)) {
    if (k === "__proto__" || k === "constructor") continue;
    if (!allowed.has(k)) continue;
    patch[k] = String(body[k] ?? "").slice(0, 256);
  }
  if (body.ngrokToken && looksLikeNgrokToken(body.ngrokToken)) {
    patch.NGROK_AUTHTOKEN = String(body.ngrokToken).slice(0, 200);
  }
  if (body.guiPassword && String(body.guiPassword).length >= 8) {
    patch.GUI_PASSWORD = SecurityManager.hashPassword(String(body.guiPassword));
  }

  config.writeEnv(patch);
  logger.uncommon(`Settings diubah dari GUI (${Object.keys(patch).length} field).`);
  return sendJson(res, 200, { ok: true, restart: true });
});

// ─── Update GitHub ──────────────────────────────
route("GET", "/api/update/check", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  if (!updater.isGitRepo()) {
    return sendJson(res, 200, { available: false, reason: "Folder bukan git repo." });
  }
  try {
    const result = await updater.check();
    return sendJson(res, 200, { ...result, status: updater.status() });
  } catch (e) {
    return sendJson(res, 500, { error: e.message });
  }
});

route("POST", "/api/update/pull", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  if (!updater.isGitRepo()) return sendJson(res, 400, { error: "Bukan git repo." });
  try {
    const result = await updater.pull({ allowDirty: false });
    return sendJson(res, 200, result);
  } catch (e) {
    return sendJson(res, 500, { error: e.message });
  }
});

route("GET", "/api/update/backups", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, { backups: updater.listBackups() });
});

// ─── Security stats ─────────────────────────────
route("GET", "/api/security", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, security.stats());
});

route("GET", "/api/firewall/stats", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, firewall.stats());
});

route("POST", "/api/security/unblock", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 1024 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }
  const ip = String(body.ip || "").slice(0, 64);
  if (!ip || !/^[0-9a-f.:\[\]]+$/i.test(ip)) return sendJson(res, 400, { error: "IP invalid" });
  security.failedLogins.delete(ip);
  antiDdos.unblock(ip);
  logger.security(`IP ${ip} di-unblock manual`, { ip });
  return sendJson(res, 200, { ok: true });
});

// ─── Storage Janitor ────────────────────────────
const cleanup = require("./lib/cleanup");
const mysql   = (() => {
  // Lazy require — mysql2 mungkin tidak terinstall (DB optional)
  try { return require("mysql2/promise"); } catch { return null; }
})();

/**
 * Bangun set file uploads yang masih direference di DB.
 * Tanpa DB / mysql2 → return Set kosong (orphan upload tidak akan dihapus).
 */
async function buildUploadsReferenceSet() {
  // Coba pakai mysql2 (kalau ada) — lebih ringan dari spawn PHP
  try {
    if (!mysql) return new Set();
    const conn = await mysql.createConnection({
      host: process.env.DB_HOST || "localhost",
      user: process.env.DB_USER || "root",
      password: process.env.DB_PASS || "",
      database: process.env.DB_NAME || "topzone",
      connectTimeout: 3000,
    });
    const [users]  = await conn.execute("SELECT foto FROM users");
    const [games]  = await conn.execute("SELECT gambar FROM games");
    await conn.end();
    return cleanup.buildReferencedSet([...users, ...games]);
  } catch (e) {
    logger.uncommon("DB tidak tersedia untuk cleanup orphan upload: " + e.message);
    return null; // null = skip orphan scan (safer)
  }
}

route("GET", "/api/storage/scan", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  try {
    const referencedUploads = await buildUploadsReferenceSet();
    const report = cleanup.analyze({
      logsRetentionDays   : cfg.LOG_RETENTION_DAYS || 30,
      referencedUploads   : referencedUploads || undefined,
    });
    return sendJson(res, 200, {
      ...report,
      dbAvailable: referencedUploads !== null,
    });
  } catch (e) {
    logger.error("storage scan: " + e.message);
    return sendJson(res, 500, { error: "Scan gagal: " + e.message });
  }
});

route("POST", "/api/storage/clean", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  let body = {};
  try { body = await firewall.readJsonBody(req, { maxBytes: 4096 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }

  const dryRun = body.dryRun !== false; // DEFAULT TRUE — paksa user explicit
  try {
    const referencedUploads = await buildUploadsReferenceSet();
    const opts = {
      dryRun,
      logsRetentionDays: cfg.LOG_RETENTION_DAYS || 30,
    };
    if (referencedUploads) opts.referencedUploads = referencedUploads;

    const result = cleanup.clean(opts);
    logger.uncommon(
      `Storage cleanup ${dryRun ? "(dry-run)" : "APPLIED"}: ` +
      `${result.removedCount} item, ${result.removedHuman}`
    );
    return sendJson(res, 200, result);
  } catch (e) {
    logger.error("storage clean: " + e.message);
    return sendJson(res, 500, { error: "Cleanup gagal: " + e.message });
  }
});

// ─── Lockdown control ───────────────────────────
route("GET", "/api/lockdown/status", (req, res) => {
  // Public — supaya UI banner masih bisa baca level walau request lain di-deny
  return sendJson(res, 200, lockdown.status());
});

route("POST", "/api/lockdown/activate", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  let body;
  try { body = await firewall.readJsonBody(req, { maxBytes: 1024 }); }
  catch (e) { return sendJson(res, 413, { error: e.message }); }
  const level = String(body.level || "lockdown");
  const result = lockdown.activate(level, {
    reason: String(body.reason || "manual").slice(0, 200),
    durationMs: parseInt(body.durationMs, 10) || 0,
    by: "admin@" + getIp(req),
  });
  if (!result.ok) return sendJson(res, 400, result);
  return sendJson(res, 200, { ok: true, status: lockdown.status() });
});

route("POST", "/api/lockdown/deactivate", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const result = lockdown.deactivate("admin@" + getIp(req));
  return sendJson(res, 200, { ok: true, ...result, status: lockdown.status() });
});

// ─────────────────────────────────────────────────
//  HTTP request handler
// ─────────────────────────────────────────────────
function handler(req, res) {
  const startTs = Date.now();

  // 1) Firewall: handles connection caps, WAF, lockdown gate, host check
  if (!firewall.handle(req, res, {})) return;

  const u  = url.parse(req.url, true);
  const ip = getIp(req);
  const pathOnly = u.pathname || "/";

  // 2) CSRF check untuk state-changing (kecuali endpoint yang exempt)
  if (["POST","PUT","DELETE","PATCH"].includes(req.method)) {
    const exempt = new Set(["/api/login", "/api/logout", "/api/setup/save", "/api/health"]);
    if (!exempt.has(pathOnly)) {
      const session = getSession(req);
      // CSRF wajib KALAU kita pakai sesi (password aktif). Tanpa password & local-only,
      // CSRF dilewati karena tidak ada cookie sesi.
      if (cfg.GUI_PASSWORD || !isLocalOnly()) {
        const tok = req.headers["x-csrf-token"];
        // Sesi kosong (server restart, cookie stale) → 401, biar frontend redirect ke /login
        if (!session) {
          logger.security(`State-change tanpa session: ${req.method} ${pathOnly} dari ${ip}`, { ip });
          return sendJson(res, 401, {
            error: "Sesi habis. Silakan login lagi.",
            needLogin: true,
          });
        }
        // CSRF token salah/kosong → 419 + csrfStale, frontend re-fetch + retry
        if (!security.validateCsrf(session, tok)) {
          logger.security(`CSRF fail: ${req.method} ${pathOnly} dari ${ip} (tok=${tok ? "ada" : "kosong"})`, { ip });
          return sendJson(res, 419, {
            error: "CSRF token kedaluwarsa. Refresh otomatis…",
            csrfStale: true,
          });
        }
      }
    }
  }

  // 3) Routing
  const key = `${req.method} ${pathOnly}`;
  const handlerFn = routes[key];
  if (handlerFn) {
    Promise.resolve(handlerFn(req, res, u))
      .catch((e) => {
        logger.error(`Route ${key} error: ${e.message}`, { stack: (e.stack || "").slice(0, 800) });
        if (!res.headersSent) sendJson(res, 500, { error: "Terjadi kesalahan internal." });
      })
      .finally(() => {
        const dur = Date.now() - startTs;
        if (dur > 1000) logger.uncommon(`Slow GUI route ${key} (${dur}ms)`);
      });
    return;
  }

  // 4) Static fallback (path-traversal-proof)
  if (req.method === "GET") {
    return serveStatic(req, res, pathOnly);
  }

  sendText(res, 404, "Not found");
}

// ─────────────────────────────────────────────────
//  Boot
// ─────────────────────────────────────────────────
function openInBrowser(target) {
  // Validasi URL — hanya boleh http://localhost / 127.0.0.1
  if (!/^https?:\/\/(?:127\.0\.0\.1|localhost|\[::1\]):\d+(\/|$)/i.test(target)) {
    logger.warning("openInBrowser: URL tidak valid untuk auto-open.");
    return;
  }
  // Pakai args array, bukan template-shell
  const { spawn } = require("child_process");
  const cmd = process.platform === "win32" ? "cmd"
            : process.platform === "darwin" ? "open"
            : "xdg-open";
  const args = process.platform === "win32" ? ["/c", "start", "", target] : [target];
  try {
    const child = spawn(cmd, args, { stdio: "ignore", detached: true, shell: false });
    child.unref();
  } catch (_) {}
}

const port = isValidPort(flags.port || cfg.GUI_PORT) ? parseInt(flags.port || cfg.GUI_PORT, 10) : 4747;
const bind = flags.bind || cfg.GUI_BIND || "127.0.0.1";

// Sinkronisasi cfg.GUI_PORT/BIND ke port aktual supaya host-check di
// security.checkOrigin tidak salah tolak request yang sah.
cfg.GUI_PORT = port;
cfg.GUI_BIND = bind;

const server = http.createServer({
  // Header timeouts — anti slowloris di tingkat HTTP server
  requestTimeout : 30000,
  headersTimeout : 15000,
  keepAliveTimeout: 5000,
  maxHeadersCount: 60,
  // Body limit at protocol level (Node 18+: receivedShutdown handled separately)
}, handler);

// Connection-level guard: anti SYN flood / DDoS connection burst
server.on("connection", (socket) => {
  if (!firewall.guardConnection(socket)) return;
  socket.setNoDelay(true);
  // Defense in depth: kill idle long-running socket
  socket.setTimeout(60000);
});

server.on("clientError", (err, socket) => {
  // Slowloris / malformed request — tutup socket cepat
  try {
    socket.end("HTTP/1.1 400 Bad Request\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
  } catch (_) {}
  const ip = socket?.remoteAddress || "?";
  const isLoopback = ip === "127.0.0.1" || ip === "::1" || ip === "::ffff:127.0.0.1" || ip.startsWith("127.");
  // EXEMPT loopback: keep-alive browser bikin clientError wajar (ECONNRESET dll)
  if (!isLoopback) {
    logger.security(`clientError: ${err.code || err.message}`, { ip, code: err.code });
    antiDdos.bumpSuspicion(ip, 2, "client-error");
  }
});

server.on("error", (err) => {
  if (err.code === "EADDRINUSE") {
    console.error(badge.fail(`Port ${port} sudah dipakai. Edit GUI_PORT di .env atau pakai --port=N.`));
    process.exit(1);
  }
  console.error(badge.fail("HTTP server error: " + err.message));
});

server.listen(port, bind, () => {
  let openUrl = `http://${bind}:${port}/`;
  if (cfg.GUI_PASSWORD) {
    const otp = security.oneTimeToken();
    openUrl = `http://${bind}:${port}/login?otp=${otp}`;
  }

  const sep = c.cyan("═".repeat(64));
  console.log("\n" + sep);
  console.log("  " + c.bgGreen(c.bold("  🛡️  TopZone GUI Control Panel ONLINE  ")));
  console.log("");
  console.log("  " + c.bold("URL          ") + ": " + c.cyan(c.bold(openUrl)));
  console.log("  " + c.bold("Bind         ") + ": " + bind + ":" + port);
  if (cfg.GUI_PASSWORD) console.log("  " + c.bold("Auth         ") + ": " + c.green("password aktif (token sekali pakai sudah di-URL)"));
  else                  console.log("  " + c.bold("Auth         ") + ": " + c.yellow("tanpa password (aman karena 127.0.0.1)"));
  console.log("  " + c.bold("Firewall     ") + ": " + c.green("AKTIF") +
                       " (anti-DDoS, WAF, lockdown otomatis)");
  console.log("  " + c.bold("Log folder   ") + ": " + path.join(__dirname, "logs"));
  console.log(sep);
  console.log(c.dim("\n  Ctrl+C untuk berhenti.\n"));

  if (cfg.GUI_AUTO_OPEN && !flags["no-open"]) {
    setTimeout(() => openInBrowser(openUrl), 800);
  }

  logger.common(`GUI listening on http://${bind}:${port}`);
});

// ─────────────────────────────────────────────────
//  Shutdown
// ─────────────────────────────────────────────────
let shutting = false;
async function shutdown(sig) {
  if (shutting) return;
  shutting = true;
  process.stderr.write(`\n${c.yellow(`🛑 ${sig} — shutdown GUI...`)}\n`);
  for (const cl of sseClients) { try { cl.end(); } catch (_) {} }
  sseClients.clear();
  sseByIp.clear();
  try { server.close(); } catch (_) {}
  try { await controller.shutdown(sig); } catch (_) {}
  logger.common(`GUI dimatikan (${sig}).`);
  setTimeout(() => process.exit(0), 300);
}
process.on("SIGINT",  () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("uncaughtException", (e) => { logger.critical("uncaught: " + e.message, { stack: (e.stack||"").slice(0,500) }); shutdown("uncaught"); });
process.on("unhandledRejection", (r) => { logger.error("unhandled: " + (r && r.message || r)); });
