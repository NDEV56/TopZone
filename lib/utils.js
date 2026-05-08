/**
 * lib/utils.js — utilitas umum (port, timing, fs aman)
 * ────────────────────────────────────────────────────
 * Tidak ada dependency eksternal.
 */

"use strict";

const net  = require("net");
const http = require("http");
const fs   = require("fs");
const path = require("path");
const os   = require("os");
const crypto = require("crypto");
const { execSync } = require("child_process");

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Cek port TCP terbuka di host tertentu (default 127.0.0.1). */
function isPortOpen(port, host = "127.0.0.1", timeoutMs = 800) {
  return new Promise((resolve) => {
    const s = new net.Socket();
    let done = false;
    const finish = (val) => {
      if (done) return;
      done = true;
      try { s.destroy(); } catch (_) {}
      resolve(val);
    };
    s.setTimeout(timeoutMs);
    s.once("connect", () => finish(true));
    s.once("error",   () => finish(false));
    s.once("timeout", () => finish(false));
    try { s.connect(port, host); } catch (_) { finish(false); }
  });
}

/** HTTP HEAD/GET ke localhost — cek server merespons. */
function httpHealthCheck(port, opts = {}) {
  const { path: p = "/", host = "127.0.0.1", timeoutMs = 3000, method = "GET" } = opts;
  return new Promise((resolve) => {
    const req = http.request(
      { hostname: host, port, path: p, method, timeout: timeoutMs,
        headers: { "User-Agent": "TopZone-HealthCheck/1.0" } },
      (res) => {
        // Buang body biar gak nyumbat
        res.on("data", () => {});
        res.on("end", () => resolve({ ok: true, status: res.statusCode || 0,
                                       headers: res.headers }));
      }
    );
    req.on("error",   () => resolve({ ok: false }));
    req.on("timeout", () => { req.destroy(); resolve({ ok: false, timeout: true }); });
    req.end();
  });
}

/** Cari port kosong mulai dari startPort, scan max `range` kali. */
async function findFreePort(startPort, range = 30) {
  for (let p = startPort; p < startPort + range; p++) {
    if (p < 1 || p > 65535) continue;
    if (!(await isPortOpen(p))) return p;
  }
  throw new Error(`Tidak ada port kosong di range ${startPort}-${startPort + range - 1}`);
}

/** Cek beberapa port paralel — kembalikan array hasil. */
async function checkPortsBatch(ports) {
  return Promise.all(ports.map(async (p) => ({
    port: p,
    open: await isPortOpen(p),
  })));
}

/** mkdir -p tapi aman (gak lempar kalau sudah ada). */
function ensureDir(dir) {
  try {
    fs.mkdirSync(dir, { recursive: true });
  } catch (e) {
    if (e.code !== "EEXIST") throw e;
  }
}

/** fs.readFileSync tapi return string kosong kalau file gak ada. */
function safeRead(file) {
  try { return fs.readFileSync(file, "utf8"); }
  catch (_) { return ""; }
}

/** Tulis file atomik: tulis ke .tmp dulu, baru rename. */
function safeWrite(file, content) {
  const tmp = file + ".tmp." + crypto.randomBytes(4).toString("hex");
  fs.writeFileSync(tmp, content);
  fs.renameSync(tmp, file);
}

/** Format byte → KB/MB/GB. */
function formatBytes(bytes) {
  if (!bytes || bytes < 1024) return `${bytes || 0} B`;
  const units = ["KB", "MB", "GB", "TB"];
  let v = bytes / 1024, i = 0;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
  return `${v.toFixed(2)} ${units[i]}`;
}

/** Format ms → "1m 23s" / "456ms". */
function formatDuration(ms) {
  if (ms < 1000) return `${Math.round(ms)}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  const m = Math.floor(ms / 60000);
  const s = Math.round((ms % 60000) / 1000);
  return `${m}m ${s}s`;
}

/** Tanggal ISO untuk nama file log: 2026-05-08 */
function dateStamp(d = new Date()) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Timestamp lengkap: 2026-05-08 14:23:01.456 */
function timeStamp(d = new Date()) {
  const pad = (n, w = 2) => String(n).padStart(w, "0");
  return `${dateStamp(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:` +
         `${pad(d.getSeconds())}.${pad(d.getMilliseconds(), 3)}`;
}

/** Cek perintah ada di PATH (sync, cepat). */
function hasCommand(cmd) {
  try {
    const which = process.platform === "win32" ? "where" : "which";
    execSync(`${which} ${cmd}`, { stdio: "ignore", timeout: 2000 });
    return true;
  } catch (_) { return false; }
}

/** Versi singkat hostname. */
function getHostInfo() {
  return {
    hostname: os.hostname(),
    platform: process.platform,
    arch    : process.arch,
    cpus    : os.cpus().length,
    memMB   : Math.round(os.totalmem() / 1024 / 1024),
    nodeVer : process.versions.node,
    user    : os.userInfo().username,
  };
}

/** Hitung hash file (SHA-256 hex). Untuk verifikasi update. */
function fileHash(file) {
  if (!fs.existsSync(file)) return null;
  const h = crypto.createHash("sha256");
  h.update(fs.readFileSync(file));
  return h.digest("hex");
}

/** Sanitasi string supaya aman dipakai di nama file. */
function safeName(s) {
  return String(s).replace(/[^a-zA-Z0-9._-]/g, "_").slice(0, 128);
}

/** Validasi token ngrok — pastikan string masuk akal. */
function looksLikeNgrokToken(t) {
  if (!t || typeof t !== "string") return false;
  // Token ngrok biasanya 40-50 char base32-ish + underscore
  return t.length >= 30 && /^[A-Za-z0-9_]+$/.test(t);
}

/** Validasi port number (1-65535). */
function isValidPort(p) {
  const n = Number(p);
  return Number.isInteger(n) && n >= 1 && n <= 65535;
}

/** Truncate string untuk display, +"…" kalau dipotong. */
function truncate(s, max = 60) {
  if (!s) return "";
  s = String(s);
  return s.length > max ? s.slice(0, max - 1) + "…" : s;
}

/** Parse argv → { flags, positional }. */
function parseArgs(argv = process.argv.slice(2)) {
  const flags = {};
  const positional = [];
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith("--")) {
      const eq = a.indexOf("=");
      if (eq !== -1) {
        flags[a.slice(2, eq)] = a.slice(eq + 1);
      } else {
        const next = argv[i + 1];
        if (next && !next.startsWith("--")) { flags[a.slice(2)] = next; i++; }
        else flags[a.slice(2)] = true;
      }
    } else {
      positional.push(a);
    }
  }
  return { flags, positional };
}

/** Debounce sederhana. */
function debounce(fn, ms) {
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(null, args), ms);
  };
}

/** Throttle sederhana. */
function throttle(fn, ms) {
  let last = 0, timer = null, lastArgs = null;
  return (...args) => {
    lastArgs = args;
    const now = Date.now();
    const remain = ms - (now - last);
    if (remain <= 0) {
      last = now;
      fn.apply(null, lastArgs);
    } else if (!timer) {
      timer = setTimeout(() => {
        last = Date.now();
        timer = null;
        fn.apply(null, lastArgs);
      }, remain);
    }
  };
}

module.exports = {
  sleep,
  isPortOpen,
  httpHealthCheck,
  findFreePort,
  checkPortsBatch,
  ensureDir,
  safeRead,
  safeWrite,
  formatBytes,
  formatDuration,
  dateStamp,
  timeStamp,
  hasCommand,
  getHostInfo,
  fileHash,
  safeName,
  looksLikeNgrokToken,
  isValidPort,
  truncate,
  parseArgs,
  debounce,
  throttle,
};
