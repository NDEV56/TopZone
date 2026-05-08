#!/usr/bin/env node
/**
 * gui.js — TopZone Web Control Panel
 * ══════════════════════════════════
 * GUI berbasis browser yang sangat ramah pemula:
 *   • Wizard step-by-step kalau belum dikonfigurasi
 *   • Tombol besar Start/Stop server
 *   • Live log streaming (SSE) dengan filter kategori
 *   • Halaman update GitHub satu tombol
 *   • Halaman diagnostik "System Check"
 *   • Halaman pengaturan dengan validasi input
 *
 * Default bind: 127.0.0.1:4747 (tidak diakses dari luar kecuali user
 * mengubah GUI_BIND di .env). Tetap dibungkus security manager.
 *
 *   node gui.js
 *   node server.js --gui
 */

"use strict";

const http     = require("http");
const fs       = require("fs");
const path     = require("path");
const url      = require("url");
const crypto   = require("crypto");
const { spawn, exec } = require("child_process");

const config         = require("./lib/config");
const { Controller } = require("./lib/controller");
const detector       = require("./lib/detector");
const { c, badge }   = require("./lib/colors");
const {
  parseCookies, buildCookie, getIp, SecurityManager,
} = require("./lib/security");
const {
  parseArgs, isValidPort, looksLikeNgrokToken, formatDuration,
  truncate, getHostInfo, ensureDir,
} = require("./lib/utils");

const { flags } = parseArgs();

// ─────────────────────────────────────────────────
//  Setup controller
// ─────────────────────────────────────────────────
const controller = new Controller({ echoConsole: false }).bootstrap();
const cfg        = controller.cfg;
const logger     = controller.logger;
const security   = controller.security;
const updater    = controller.updater;

// ─────────────────────────────────────────────────
//  MIME map
// ─────────────────────────────────────────────────
const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css" : "text/css; charset=utf-8",
  ".js"  : "application/javascript; charset=utf-8",
  ".mjs" : "application/javascript; charset=utf-8",
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
  ".map" : "application/json",
};

// ─────────────────────────────────────────────────
//  Helpers HTTP
// ─────────────────────────────────────────────────
function sendJson(res, status, data, extraHeaders = {}) {
  res.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
    ...extraHeaders,
  });
  res.end(JSON.stringify(data));
}

function sendText(res, status, text, contentType = "text/plain; charset=utf-8") {
  res.writeHead(status, { "Content-Type": contentType, "Cache-Control": "no-store",
                          "X-Content-Type-Options": "nosniff" });
  res.end(text);
}

function sendFile(res, file, status = 200) {
  fs.stat(file, (err, st) => {
    if (err || !st.isFile()) return sendText(res, 404, "Not found");
    const ext = path.extname(file).toLowerCase();
    const ct  = MIME[ext] || "application/octet-stream";
    res.writeHead(status, {
      "Content-Type": ct,
      "Cache-Control": "no-cache",
      "X-Content-Type-Options": "nosniff",
      "Content-Security-Policy": "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'",
      "Content-Length": st.size,
    });
    fs.createReadStream(file).pipe(res);
  });
}

function readBody(req, maxBytes = 256 * 1024) {
  return new Promise((resolve, reject) => {
    let total = 0;
    const chunks = [];
    req.on("data", (chunk) => {
      total += chunk.length;
      if (total > maxBytes) { req.destroy(); reject(new Error("Body terlalu besar")); return; }
      chunks.push(chunk);
    });
    req.on("end", () => {
      const raw = Buffer.concat(chunks).toString("utf8");
      try { resolve(raw ? JSON.parse(raw) : {}); }
      catch (_) { resolve({}); }
    });
    req.on("error", reject);
  });
}

// ─────────────────────────────────────────────────
//  Static dir
// ─────────────────────────────────────────────────
const PUBLIC_DIR = path.join(__dirname, "public");

function serveStatic(req, res, p) {
  // Security: cegah path traversal
  const safe = path.normalize(p).replace(/^([\\/]+)/, "");
  if (safe.includes("..")) return sendText(res, 400, "Bad path");
  const file = path.join(PUBLIC_DIR, safe);
  if (!file.startsWith(PUBLIC_DIR)) return sendText(res, 400, "Bad path");
  if (!fs.existsSync(file)) return sendText(res, 404, "Not found");
  return sendFile(res, file);
}

// ─────────────────────────────────────────────────
//  Auth helpers
// ─────────────────────────────────────────────────
function getSession(req) {
  const cookies = parseCookies(req.headers.cookie || "");
  if (!cookies.tz_sid) return null;
  return security.validateSession(cookies.tz_sid, getIp(req));
}

function requireAuth(req, res) {
  // Kalau GUI_PASSWORD kosong dan bind hanya 127.0.0.1, auth opsional
  const localOnly = cfg.GUI_BIND === "127.0.0.1" || cfg.GUI_BIND === "localhost";
  if (!cfg.GUI_PASSWORD && localOnly) return { ok: true, anon: true };

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

function broadcastSse(eventName, payload) {
  const data = `event: ${eventName}\ndata: ${JSON.stringify(payload)}\n\n`;
  for (const client of sseClients) {
    try { client.write(data); } catch (_) {}
  }
}

logger.on("entry", (e) => broadcastSse("log", e));
controller.on("phase", (phase, state) => broadcastSse("phase", { phase, state }));

// Heartbeat tiap 25s biar koneksi gak ditutup proxy
setInterval(() => broadcastSse("ping", { t: Date.now() }), 25000);

// ─────────────────────────────────────────────────
//  ROUTES
// ─────────────────────────────────────────────────
const routes = {};

function route(method, p, handler) {
  routes[`${method} ${p}`] = handler;
}

// --- Static & root ---
route("GET", "/", (req, res) => sendFile(res, path.join(PUBLIC_DIR, "index.html")));
route("GET", "/login", (req, res) => sendFile(res, path.join(PUBLIC_DIR, "login.html")));
route("GET", "/favicon.ico", (req, res) => {
  // Tampilkan logo TopZone kalau ada
  const ico = path.join(PUBLIC_DIR, "favicon.ico");
  const png = path.join(__dirname, "Login", "logotopzone.png");
  if (fs.existsSync(ico)) return sendFile(res, ico);
  if (fs.existsSync(png)) return sendFile(res, png);
  return sendText(res, 204, "");
});

// --- Health (no auth) ---
route("GET", "/api/health", (req, res) => sendJson(res, 200, {
  ok: true, version: "3.0.0", t: Date.now(),
}));

// --- Login ---
route("POST", "/api/login", async (req, res) => {
  const ip = getIp(req);
  if (security.isBlocked(ip)) {
    return sendJson(res, 429, {
      error: "IP kamu diblokir sementara karena terlalu banyak login gagal. Tunggu beberapa menit.",
    });
  }
  const body = await readBody(req);
  const password = String(body.password || "");
  // Cek OTP token (CLI auto-login)
  if (body.otp && security.consumeOtp(body.otp)) {
    const { sid, csrf } = security.createSession(ip);
    res.setHeader("Set-Cookie", buildCookie("tz_sid", sid, { maxAge: 12 * 3600 }));
    return sendJson(res, 200, { ok: true, csrf });
  }
  if (!cfg.GUI_PASSWORD) {
    return sendJson(res, 400, { error: "GUI_PASSWORD belum diset, tapi auth diminta. Edit .env." });
  }
  const ok = SecurityManager.verifyPassword(password, cfg.GUI_PASSWORD)
          || password === cfg.GUI_PASSWORD; // backward-compat plain
  if (!ok) {
    security.recordFailedLogin(ip);
    return sendJson(res, 401, { error: "Password salah." });
  }
  security.recordSuccessLogin(ip);
  const { sid, csrf } = security.createSession(ip);
  res.setHeader("Set-Cookie", buildCookie("tz_sid", sid, { maxAge: 12 * 3600 }));
  logger.security(`Login GUI sukses dari ${ip}`, { ip });
  return sendJson(res, 200, { ok: true, csrf });
});

route("POST", "/api/logout", async (req, res) => {
  const cookies = parseCookies(req.headers.cookie || "");
  if (cookies.tz_sid) security.destroySession(cookies.tz_sid);
  res.setHeader("Set-Cookie", buildCookie("tz_sid", "", { maxAge: 0 }));
  return sendJson(res, 200, { ok: true });
});

// --- Whoami ---
route("GET", "/api/whoami", (req, res) => {
  const session = getSession(req);
  const localOnly = cfg.GUI_BIND === "127.0.0.1" || cfg.GUI_BIND === "localhost";
  return sendJson(res, 200, {
    authenticated: !!session || (!cfg.GUI_PASSWORD && localOnly),
    needPassword : !!cfg.GUI_PASSWORD,
    csrf         : session ? session.csrf : null,
    bind         : cfg.GUI_BIND,
    isConfigured : config.isConfigured(),
    host         : getHostInfo(),
  });
});

// --- Status snapshot ---
route("GET", "/api/status", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, controller.snapshot());
});

// --- Logs ---
route("GET", "/api/logs", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const u = url.parse(req.url, true);
  const level = u.query.level || "all";
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
  const level = u.query.level || "common";
  const date  = u.query.date  || undefined;
  return sendJson(res, 200, { entries: logger.readArchive(level, date, 1000) });
});

route("POST", "/api/logs/clear", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  logger.reset();
  logger.uncommon("Buffer log dibersihkan oleh user.");
  return sendJson(res, 200, { ok: true });
});

route("POST", "/api/logs/prune", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const body = await readBody(req);
  const days = Math.max(1, parseInt(body.days, 10) || cfg.LOG_RETENTION_DAYS);
  const removed = logger.prune(days);
  logger.uncommon(`Pruning log ${days} hari: ${removed} file dihapus.`);
  return sendJson(res, 200, { ok: true, removed });
});

// --- SSE log stream ---
route("GET", "/api/stream", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  res.writeHead(200, {
    "Content-Type"     : "text/event-stream",
    "Cache-Control"    : "no-cache, no-transform",
    "Connection"       : "keep-alive",
    "X-Accel-Buffering": "no",
  });
  res.write("retry: 5000\n\n");
  res.write(`event: hello\ndata: ${JSON.stringify({ t: Date.now() })}\n\n`);
  sseClients.add(res);
  req.on("close", () => sseClients.delete(res));
});

// --- Diagnose ---
route("GET", "/api/diagnose", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const d = await detector.diagnose();
  return sendJson(res, 200, d);
});

// --- Server lifecycle ---
route("POST", "/api/server/start", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  if (controller.state.phase === "online") {
    return sendJson(res, 409, { error: "Server sudah online." });
  }
  // Jangan blok HTTP — jalankan async, klien dapat update via SSE
  controller.startAll().catch((e) => {
    logger.error("startAll gagal: " + e.message);
  });
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

// --- Setup wizard ---
route("POST", "/api/setup/save", async (req, res) => {
  // Ini boleh dipanggil sebelum auth karena belum dikonfigurasi
  const ip = getIp(req);
  const body = await readBody(req);

  // Validasi
  const errs = [];
  const provider = String(body.provider || "ngrok").toLowerCase();
  if (!["ngrok","cloudflared","localtunnel","serveo","pinggy","none"].includes(provider))
    errs.push("Provider tidak dikenal.");
  if (provider === "ngrok" && !looksLikeNgrokToken(body.ngrokToken || "")) {
    errs.push("Token ngrok tidak valid (panjang >= 30, hanya huruf/angka/underscore).");
  }
  const mode = String(body.mode || "auto").toLowerCase();
  if (!["auto","xampp","laragon","wamp","mamp","ampps","openserver","usbwebserver","easyphp","php","custom"].includes(mode))
    errs.push("Mode server tidak dikenal.");
  if (mode === "custom" && !isValidPort(body.localPort)) errs.push("Port custom tidak valid.");

  if (errs.length) return sendJson(res, 400, { error: errs.join(" ") });

  // Hash password kalau diisi
  let pwd = body.guiPassword ? String(body.guiPassword) : "";
  if (pwd && pwd.length < 6) return sendJson(res, 400, { error: "Password minimal 6 karakter." });
  if (pwd) pwd = SecurityManager.hashPassword(pwd);

  // Tulis .env
  const updates = {
    NGROK_AUTHTOKEN : body.ngrokToken || "",
    NGROK_DOMAIN    : body.ngrokDomain || "",
    SERVER_MODE     : mode,
    LOCAL_PORT      : mode === "custom" ? String(body.localPort) : "0",
    PHP_PORT        : body.phpPort || "8080",
    PHP_ROOT        : body.phpRoot || path.join(__dirname, "Home"),
    TUNNEL_PROVIDER : provider,
    TUNNEL_FALLBACK : body.tunnelFallback ? "true" : "false",
    GUI_PASSWORD    : pwd,
    GUI_PORT        : isValidPort(body.guiPort) ? String(body.guiPort) : String(cfg.GUI_PORT),
    AUTO_UPDATE     : ["ask","true","false"].includes(body.autoUpdate) ? body.autoUpdate : "ask",
    LOG_REQUESTS    : body.logRequests === false ? "false" : "true",
  };
  config.writeEnv(updates);
  config.writeEnvExample();

  logger.security(`Setup wizard disimpan dari ${ip}`, { provider, mode });
  return sendJson(res, 200, { ok: true, restart: true });
});

// --- Settings ---
route("GET", "/api/settings", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, controller._publicConfig());
});

route("POST", "/api/settings", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const body = await readBody(req);

  // Hanya keys yang di-whitelist boleh diubah dari GUI
  const allowed = ["NGROK_AUTHTOKEN","NGROK_DOMAIN","SERVER_MODE","LOCAL_PORT",
                   "PHP_PORT","PHP_ROOT","TUNNEL_PROVIDER","TUNNEL_FALLBACK",
                   "AUTO_UPDATE","LOG_REQUESTS","LOG_MIN_LEVEL","LOG_RETENTION_DAYS",
                   "GUI_AUTO_OPEN","SEC_RATE_LIMIT","SEC_BLOCK_ON_FAIL","SEC_BLOCK_DURATION"];
  const patch = {};
  for (const k of allowed) if (body[k] !== undefined) patch[k] = String(body[k]);

  // Token sensitif
  if (body.ngrokToken && looksLikeNgrokToken(body.ngrokToken)) patch.NGROK_AUTHTOKEN = body.ngrokToken;
  if (body.guiPassword && String(body.guiPassword).length >= 6) {
    patch.GUI_PASSWORD = SecurityManager.hashPassword(String(body.guiPassword));
  }

  config.writeEnv(patch);
  logger.uncommon(`Settings diubah dari GUI (${Object.keys(patch).length} field).`);
  return sendJson(res, 200, { ok: true, restart: true });
});

// --- Update via GitHub ---
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

// --- Active sessions / security ---
route("GET", "/api/security", (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  return sendJson(res, 200, security.stats());
});

route("POST", "/api/security/unblock", async (req, res) => {
  const auth = requireAuth(req, res); if (!auth.ok) return;
  const body = await readBody(req);
  const ip = String(body.ip || "");
  if (!ip) return sendJson(res, 400, { error: "ip required" });
  security.failedLogins.delete(ip);
  logger.security(`IP ${ip} di-unblock manual`, { ip });
  return sendJson(res, 200, { ok: true });
});

// ─────────────────────────────────────────────────
//  HTTP server
// ─────────────────────────────────────────────────
function handler(req, res) {
  const startTs = Date.now();
  const u  = url.parse(req.url, true);
  const ip = getIp(req);

  // Security: rate limit
  const rl = security.rateLimit(ip);
  if (!rl.ok) {
    res.setHeader("Retry-After", String(rl.retryAfter || 60));
    return sendJson(res, 429, { error: "Terlalu banyak request. Coba lagi sebentar." });
  }

  // Security: cek host header (anti DNS rebinding)
  const ho = security.checkOrigin(req);
  if (!ho.ok) {
    return sendJson(res, 400, { error: "Host/Origin tidak diizinkan." });
  }

  // Security: scan pola mencurigakan
  const sc = security.scanSuspicious(req);
  if (sc.suspicious) {
    return sendJson(res, 403, { error: "Request mengandung pola berbahaya: " + sc.pattern });
  }

  // CSRF: untuk POST/PUT/DELETE wajib X-CSRF-Token (kecuali endpoint setup awal & login)
  if (["POST","PUT","DELETE","PATCH"].includes(req.method)) {
    const exempt = ["/api/login", "/api/logout", "/api/setup/save", "/api/health"];
    if (!exempt.includes(u.pathname)) {
      const session = getSession(req);
      const localOnly = cfg.GUI_BIND === "127.0.0.1" || cfg.GUI_BIND === "localhost";
      if (cfg.GUI_PASSWORD || !localOnly) {
        const tok = req.headers["x-csrf-token"];
        if (!session || !security.validateCsrf(session, tok)) {
          return sendJson(res, 403, { error: "CSRF token tidak valid. Refresh halaman dan login lagi." });
        }
      }
    }
  }

  // Routing
  const key = `${req.method} ${u.pathname}`;
  const handlerFn = routes[key];
  if (handlerFn) {
    Promise.resolve(handlerFn(req, res, u))
      .catch((e) => {
        logger.error(`Route ${key} error: ${e.message}`, { stack: e.stack });
        if (!res.headersSent) sendJson(res, 500, { error: "Internal: " + e.message });
      })
      .finally(() => {
        const dur = Date.now() - startTs;
        if (dur > 1000) logger.uncommon(`Slow GUI route ${key} (${dur}ms)`);
      });
    return;
  }

  // Static fallback
  if (req.method === "GET") {
    const safe = u.pathname.replace(/^\/+/, "");
    const candidate = path.join(PUBLIC_DIR, safe);
    if (candidate.startsWith(PUBLIC_DIR) && fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
      return sendFile(res, candidate);
    }
  }

  sendText(res, 404, "Not found");
}

// ─────────────────────────────────────────────────
//  Boot
// ─────────────────────────────────────────────────
function openInBrowser(url) {
  const cmd = process.platform === "win32" ? `start "" "${url}"`
            : process.platform === "darwin" ? `open "${url}"`
            : `xdg-open "${url}"`;
  exec(cmd, () => {});
}

const port = isValidPort(flags.port || cfg.GUI_PORT) ? parseInt(flags.port || cfg.GUI_PORT, 10) : 4747;
const bind = flags.bind || cfg.GUI_BIND || "127.0.0.1";

const server = http.createServer(handler);
server.on("error", (err) => {
  if (err.code === "EADDRINUSE") {
    console.error(badge.fail(`Port ${port} sudah dipakai. Edit GUI_PORT di .env atau pakai --port=N.`));
    process.exit(1);
  }
  console.error(badge.fail("HTTP server error: " + err.message));
});

server.listen(port, bind, () => {
  // Generate one-time token, lalu print URL auto-login
  let openUrl = `http://${bind}:${port}/`;
  if (cfg.GUI_PASSWORD) {
    const otp = security.oneTimeToken();
    openUrl = `http://${bind}:${port}/login?otp=${otp}`;
  }

  const sep = c.cyan("═".repeat(64));
  console.log("\n" + sep);
  console.log("  " + c.bgGreen(c.bold("  🎛️  TopZone GUI Control Panel ONLINE  ")));
  console.log("");
  console.log("  " + c.bold("URL          ") + ": " + c.cyan(c.bold(openUrl)));
  console.log("  " + c.bold("Bind         ") + ": " + bind + ":" + port);
  if (cfg.GUI_PASSWORD) console.log("  " + c.bold("Auth         ") + ": " + c.green("password aktif (token sekali pakai sudah di-URL)"));
  else                  console.log("  " + c.bold("Auth         ") + ": " + c.yellow("tanpa password (aman karena 127.0.0.1)"));
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
  console.log(`\n${c.yellow(`🛑 ${sig} — shutdown GUI...`)}`);
  for (const c of sseClients) { try { c.end(); } catch (_) {} }
  sseClients.clear();
  try { server.close(); } catch (_) {}
  try { await controller.shutdown(sig); } catch (_) {}
  logger.common(`GUI dimatikan (${sig}).`);
  setTimeout(() => process.exit(0), 300);
}
process.on("SIGINT",  () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("uncaughtException", (e) => { logger.critical("uncaught: " + e.message); shutdown("uncaught"); });
process.on("unhandledRejection", (r) => { logger.error("unhandled: " + (r && r.message || r)); });
