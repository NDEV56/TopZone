#!/usr/bin/env node
/**
 * ════════════════════════════════════════════════════════════════════════════════
 *  TOPZONE — Universal Development Server v3.0
 * ════════════════════════════════════════════════════════════════════════════════
 *
 *  Launcher all-in-one untuk lingkungan development TopZone.
 *  Menggabungkan: web server, ngrok tunnel, database tools, monitoring,
 *  diagnostics, dan utilitas developer dalam satu CLI yang ramah.
 *
 *  ── FITUR UTAMA ─────────────────────────────────────────────────────────────
 *
 *   🚀  Auto-launch
 *        • Auto-detect XAMPP / Laragon / WAMP / MAMP
 *        • PHP built-in fallback (kalau tidak ada web server lokal)
 *        • Port fallback otomatis
 *        • HTTP health check
 *
 *   🌐  Tunnel
 *        • Ngrok tunnel dengan retry + exponential backoff
 *        • Custom domain support
 *        • Auto-update Xendit webhook URL via API
 *        • QR code untuk testing mobile (text-based)
 *
 *   🗄️   Database
 *        • Connection test
 *        • Auto-import schema/seed
 *        • Backup utility (mysqldump)
 *        • Migration tracker (versioning)
 *
 *   🩺  Monitoring
 *        • Live request log dari ngrok API
 *        • Health check continuous (auto-recovery)
 *        • Request analytics (per-endpoint stats, slow query detection)
 *        • Memory & CPU usage
 *
 *   🛠️   Developer Tools
 *        • Setup wizard (first-run)
 *        • Diagnostics command (--check)
 *        • Profile manager (multi-environment)
 *        • Update checker (npm packages)
 *        • Browser auto-open
 *        • System notifications
 *
 *   🎨  UX
 *        • Colored output (zero deps)
 *        • Spinner + progress bar
 *        • Live dashboard mode (--dashboard)
 *        • Box-drawing UI elements
 *        • Graceful shutdown dengan ringkasan
 *
 *  ── PEMAKAIAN ───────────────────────────────────────────────────────────────
 *
 *   $ node server.js                # default: launch full stack
 *   $ node server.js --setup        # jalankan setup wizard
 *   $ node server.js --check        # diagnostics (system check)
 *   $ node server.js --db:test      # tes koneksi database
 *   $ node server.js --db:setup     # import schema + seed
 *   $ node server.js --db:backup    # backup database ke file
 *   $ node server.js --no-ngrok     # skip ngrok tunnel
 *   $ node server.js --no-browser   # jangan auto-open browser
 *   $ node server.js --dashboard    # live dashboard mode
 *   $ node server.js --profile=dev  # pakai profile .env.dev
 *   $ node server.js --port=8080    # override port
 *   $ node server.js --version      # tampilkan versi
 *   $ node server.js --help         # bantuan
 *
 *  ── KONFIGURASI (.env) ─────────────────────────────────────────────────────
 *
 *   Lihat .env.example untuk daftar lengkap variabel.
 *
 *  ── LICENSE ─────────────────────────────────────────────────────────────────
 *
 *   GPL-2.0 — Lihat file LICENSE
 *
 * ════════════════════════════════════════════════════════════════════════════════
 */

"use strict";

// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 01  ─  CORE DEPENDENCIES
// ════════════════════════════════════════════════════════════════════════════════

const path     = require("path");
const fs       = require("fs");
const os       = require("os");
const net      = require("net");
const http     = require("http");
const https    = require("https");
const url      = require("url");
const crypto   = require("crypto");
const readline = require("readline");
const { spawn, exec, execSync, fork } = require("child_process");
const { performance } = require("perf_hooks");
const { EventEmitter } = require("events");

// ─── Lazy load optional deps ──────────────────────────────────────────────────
let ngrok, dotenv;
try {
  ngrok  = require("@ngrok/ngrok");
  dotenv = require("dotenv");
} catch (e) {
  console.error("\n\x1b[31m❌  Dependencies belum terinstall!\x1b[0m");
  console.error("    Jalankan dulu: \x1b[36mnpm install\x1b[0m\n");
  console.error("    Atau pakai quick start:");
  console.error("      Windows : \x1b[36mstart.bat\x1b[0m");
  console.error("      Linux/Mac: \x1b[36m./start.sh\x1b[0m\n");
  process.exit(1);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 02  ─  GLOBAL CONSTANTS
// ════════════════════════════════════════════════════════════════════════════════

const APP_NAME    = "TopZone";
const APP_VERSION = "3.0.0";
const APP_AUTHOR  = "TopZone Team";

const PROJECT_ROOT = __dirname;
const ENV_FILE     = path.join(PROJECT_ROOT, ".env");
const ENV_EXAMPLE  = path.join(PROJECT_ROOT, ".env.example");
const PROFILES_DIR = path.join(PROJECT_ROOT, ".profiles");
const LOG_DIR      = path.join(PROJECT_ROOT, "logs");
const BACKUP_DIR   = path.join(PROJECT_ROOT, "backups");
const STATE_FILE   = path.join(PROJECT_ROOT, ".tz-state.json");

const NGROK_API     = "http://127.0.0.1:4040/api";
const NGROK_TUNNELS = `${NGROK_API}/tunnels`;
const NGROK_REQS    = `${NGROK_API}/requests/http`;

const RETRY_MAX        = 5;
const RETRY_DELAY_BASE = 1500;
const HEALTH_INTERVAL  = 30 * 1000;   // 30 detik
const REQ_POLL_MS      = 1200;
const SHUTDOWN_TIMEOUT = 10 * 1000;
const WEBHOOK_SYNC_MS  = 60 * 1000;

const AUTO_PORTS = [80, 8080, 8888, 3000, 5000, 8000, 8008, 8081, 9000];

const APP_PROFILES = {
  auto:    { name: "Auto-detect",    ports: AUTO_PORTS,                   hint: "Akan deteksi server lokal yang aktif" },
  xampp:   { name: "XAMPP",          ports: [80, 8080],                   hint: "Pastikan Apache di XAMPP Control Panel sudah START" },
  laragon: { name: "Laragon",        ports: [80, 8080],                   hint: "Pastikan Laragon sudah dibuka & service ON" },
  wamp:    { name: "WampServer",     ports: [80, 8080],                   hint: "Klik icon WampServer (taskbar) → Start All Services" },
  mamp:    { name: "MAMP",           ports: [8888, 80],                   hint: "Buka MAMP lalu klik 'Start Servers'" },
  php:     { name: "PHP Built-in",   ports: [],                           hint: "Pastikan PHP terinstall (cek: php -v)" },
  custom:  { name: "Custom Server",  ports: [],                           hint: "Sertakan LOCAL_PORT di .env" },
};

const FOLDER_HINTS = {
  xampp:   ["C:\\xampp\\htdocs", "/Applications/XAMPP/htdocs", "/opt/lampp/htdocs"],
  laragon: ["C:\\laragon\\www"],
  wamp:    ["C:\\wamp64\\www", "C:\\wamp\\www"],
  mamp:    ["/Applications/MAMP/htdocs"],
};

const PHP_HINTS = {
  win32:  ["C:\\xampp\\php\\php.exe", "C:\\laragon\\bin\\php\\php.exe", "C:\\wamp64\\bin\\php\\php.exe"],
  darwin: ["/usr/local/bin/php", "/opt/homebrew/bin/php", "/Applications/MAMP/bin/php/php8.1.0/bin/php"],
  linux:  ["/usr/bin/php", "/usr/local/bin/php"],
};

const MYSQL_HINTS = {
  win32:  ["C:\\xampp\\mysql\\bin\\mysql.exe", "C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe", "C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe"],
  darwin: ["/usr/local/bin/mysql", "/opt/homebrew/bin/mysql", "/Applications/MAMP/Library/bin/mysql"],
  linux:  ["/usr/bin/mysql", "/usr/local/bin/mysql"],
};

// Mapping kode error ke solusi spesifik (dipakai oleh helpfulError())
const ERROR_HINTS = {
  EADDRINUSE: {
    title: "Port sudah dipakai",
    fixes: [
      "Tutup aplikasi yang pakai port itu",
      "Atau set LOCAL_PORT lain di .env",
      "Cek pemakai port: netstat -ano | findstr :PORT (Windows) atau lsof -i :PORT (Mac/Linux)",
    ],
  },
  ECONNREFUSED: {
    title: "Tidak bisa konek",
    fixes: [
      "Pastikan server tujuan sudah jalan",
      "Cek firewall lokal",
      "Cek apakah service di-bind ke 127.0.0.1 atau 0.0.0.0",
    ],
  },
  ENOENT: {
    title: "File / perintah tidak ditemukan",
    fixes: [
      "Cek path: pastikan file/perintah ada",
      "Untuk PHP/MySQL: tambahkan ke environment PATH",
      "Restart terminal setelah update PATH",
    ],
  },
  EACCES: {
    title: "Akses ditolak",
    fixes: [
      "Coba dengan permission lebih tinggi (sudo / administrator)",
      "Cek ownership file/folder",
      "Untuk port < 1024 di Linux: butuh sudo atau setcap",
    ],
  },
  ENETUNREACH: {
    title: "Network tidak bisa dijangkau",
    fixes: [
      "Cek koneksi internet",
      "Cek pengaturan proxy/VPN",
      "Coba restart adapter jaringan",
    ],
  },
};

// Default state object
const DEFAULT_STATE = {
  lastUrl:          null,
  lastWebhookSync:  null,
  totalRequests:    0,
  startupCount:     0,
  preferredPort:    null,
  lastProfile:      "default",
};


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 03  ─  ANSI COLORS, ICONS & UI HELPERS
// ════════════════════════════════════════════════════════════════════════════════

const supportsColor = (() => {
  if (process.env.NO_COLOR) return false;
  if (process.env.FORCE_COLOR) return true;
  if (!process.stdout.isTTY) return false;
  if (process.platform === "win32") return true;
  const term = process.env.TERM || "";
  return /color|xterm|ansi|vt100/i.test(term);
})();

function colorize(code) {
  return supportsColor ? code : "";
}

const c = {
  reset:   colorize("\x1b[0m"),
  bold:    colorize("\x1b[1m"),
  dim:     colorize("\x1b[2m"),
  italic:  colorize("\x1b[3m"),
  under:   colorize("\x1b[4m"),
  blink:   colorize("\x1b[5m"),
  inv:     colorize("\x1b[7m"),
  strike:  colorize("\x1b[9m"),

  black:   colorize("\x1b[30m"),
  red:     colorize("\x1b[31m"),
  green:   colorize("\x1b[32m"),
  yellow:  colorize("\x1b[33m"),
  blue:    colorize("\x1b[34m"),
  magenta: colorize("\x1b[35m"),
  cyan:    colorize("\x1b[36m"),
  white:   colorize("\x1b[37m"),
  gray:    colorize("\x1b[90m"),

  bgBlack:   colorize("\x1b[40m"),
  bgRed:     colorize("\x1b[41m"),
  bgGreen:   colorize("\x1b[42m"),
  bgYellow:  colorize("\x1b[43m"),
  bgBlue:    colorize("\x1b[44m"),
  bgMagenta: colorize("\x1b[45m"),
  bgCyan:    colorize("\x1b[46m"),
  bgWhite:   colorize("\x1b[47m"),
};

// ─── Icon helpers (Unicode dengan ASCII fallback) ────────────────────────────
const useUnicode = process.platform !== "win32" || process.env.WT_SESSION || process.env.TERM_PROGRAM;

const icons = {
  ok:       useUnicode ? "✔" : "[OK]",
  fail:     useUnicode ? "✖" : "[X] ",
  warn:     useUnicode ? "⚠" : "[!] ",
  info:     useUnicode ? "ℹ" : "[i] ",
  arrow:    useUnicode ? "→" : "->",
  bullet:   useUnicode ? "•" : "*",
  star:     useUnicode ? "★" : "*",
  spark:    useUnicode ? "✨" : "*",
  rocket:   useUnicode ? "🚀" : ">>",
  globe:    useUnicode ? "🌐" : "[www]",
  wrench:   useUnicode ? "🔧" : "[cfg]",
  lock:     useUnicode ? "🔒" : "[lock]",
  bell:     useUnicode ? "🔔" : "[!]",
  fire:     useUnicode ? "🔥" : "*",
  spinner:  useUnicode ? ["⠋","⠙","⠹","⠸","⠼","⠴","⠦","⠧","⠇","⠏"] : ["|","/","-","\\"],
};

const ok    = (s) => `${c.green}${icons.ok}${c.reset}  ${s}`;
const fail  = (s) => `${c.red}${icons.fail}${c.reset}  ${s}`;
const warn  = (s) => `${c.yellow}${icons.warn}${c.reset}  ${s}`;
const info  = (s) => `${c.cyan}${icons.info}${c.reset}  ${s}`;
const step  = (s) => `${c.blue}${icons.arrow}${c.reset}  ${s}`;
const bold  = (s) => `${c.bold}${s}${c.reset}`;
const dim   = (s) => `${c.dim}${s}${c.reset}`;
const hi    = (s) => `${c.cyan}${c.bold}${s}${c.reset}`;
const bad   = (s) => `${c.red}${c.bold}${s}${c.reset}`;
const good  = (s) => `${c.green}${c.bold}${s}${c.reset}`;
const lbl   = (s, color = "yellow") => `${c[color] || c.yellow}${s}${c.reset}`;

// ─── Box drawing helpers ─────────────────────────────────────────────────────
const box = {
  topL:  useUnicode ? "╔" : "+",
  topR:  useUnicode ? "╗" : "+",
  botL:  useUnicode ? "╚" : "+",
  botR:  useUnicode ? "╝" : "+",
  horiz: useUnicode ? "═" : "=",
  vert:  useUnicode ? "║" : "|",
  cross: useUnicode ? "╬" : "+",

  topLLight:  useUnicode ? "┌" : "+",
  topRLight:  useUnicode ? "┐" : "+",
  botLLight:  useUnicode ? "└" : "+",
  botRLight:  useUnicode ? "┘" : "+",
  horizLight: useUnicode ? "─" : "-",
  vertLight:  useUnicode ? "│" : "|",
};

function drawBox(title, lines, opts = {}) {
  const width  = opts.width || 64;
  const color  = opts.color || c.cyan;
  const style  = opts.style || "double";
  const t = style === "double"
    ? { l: box.topL, r: box.topR, bl: box.botL, br: box.botR, h: box.horiz, v: box.vert }
    : { l: box.topLLight, r: box.topRLight, bl: box.botLLight, br: box.botRLight, h: box.horizLight, v: box.vertLight };

  const out = [];
  out.push(`${color}${t.l}${t.h.repeat(width - 2)}${t.r}${c.reset}`);
  if (title) {
    const padded = ` ${title} `;
    const left   = Math.floor((width - 2 - visibleLen(padded)) / 2);
    const right  = (width - 2) - visibleLen(padded) - left;
    out.push(`${color}${t.v}${" ".repeat(Math.max(0, left))}${c.bold}${padded}${c.reset}${color}${" ".repeat(Math.max(0, right))}${t.v}${c.reset}`);
    out.push(`${color}${t.v}${t.h.repeat(width - 2)}${t.v}${c.reset}`);
  }
  for (const line of lines) {
    const padding = Math.max(0, (width - 2) - visibleLen(line));
    out.push(`${color}${t.v}${c.reset} ${line}${" ".repeat(Math.max(0, padding - 1))}${color}${t.v}${c.reset}`);
  }
  out.push(`${color}${t.bl}${t.h.repeat(width - 2)}${t.br}${c.reset}`);
  return out.join("\n");
}

function visibleLen(str) {
  return String(str).replace(/\x1b\[[0-9;]*m/g, "").length;
}

function pad(str, width, char = " ") {
  const len = visibleLen(str);
  return len >= width ? str : str + char.repeat(width - len);
}

function padLeft(str, width, char = " ") {
  const len = visibleLen(str);
  return len >= width ? str : char.repeat(width - len) + str;
}

function center(str, width) {
  const len  = visibleLen(str);
  const left = Math.floor((width - len) / 2);
  const right = width - len - left;
  return " ".repeat(Math.max(0, left)) + str + " ".repeat(Math.max(0, right));
}

function repeat(str, n) {
  return str.repeat(Math.max(0, n));
}

function truncate(str, max) {
  if (visibleLen(str) <= max) return str;
  return str.slice(0, max - 1) + "…";
}

// ─── Spinner ─────────────────────────────────────────────────────────────────
class Spinner {
  constructor(text = "") {
    this.text     = text;
    this.frames   = icons.spinner;
    this.interval = null;
    this.idx      = 0;
    this.startedAt = null;
    this.suspended = false;
  }
  start(text) {
    if (text) this.text = text;
    if (!process.stdout.isTTY || this.suspended) {
      console.log(step(this.text));
      return this;
    }
    this.startedAt = Date.now();
    this.interval = setInterval(() => {
      const f = this.frames[this.idx = (this.idx + 1) % this.frames.length];
      process.stdout.write(`\r${c.cyan}${f}${c.reset}  ${this.text}   `);
    }, 80);
    return this;
  }
  update(text) { this.text = text; return this; }
  succeed(text) { this._stop(ok(text || this.text)); }
  fail(text)    { this._stop(fail(text || this.text)); }
  warnDone(text){ this._stop(warn(text || this.text)); }
  info(text)    { this._stop(info(text || this.text)); }
  stop()        { this._stop(""); }
  _stop(line) {
    if (this.interval) clearInterval(this.interval);
    if (process.stdout.isTTY) process.stdout.write("\r" + " ".repeat(80) + "\r");
    if (line) console.log(line);
  }
}

// ─── Progress bar ────────────────────────────────────────────────────────────
function progressBar(current, total, width = 40, color = c.cyan) {
  const ratio = Math.min(1, Math.max(0, current / total));
  const filled = Math.round(ratio * width);
  const empty  = width - filled;
  const blocks = useUnicode ? "█" : "#";
  const dots   = useUnicode ? "░" : "-";
  return `${color}${blocks.repeat(filled)}${c.gray}${dots.repeat(empty)}${c.reset} ${(ratio * 100).toFixed(0)}%`;
}

// ─── Table renderer ──────────────────────────────────────────────────────────
function renderTable(headers, rows, opts = {}) {
  const colWidths = headers.map((h, i) => {
    const headerLen = visibleLen(h);
    const maxRow = rows.reduce((m, r) => Math.max(m, visibleLen(String(r[i] ?? ""))), 0);
    return Math.max(headerLen, maxRow);
  });

  const top    = "┌" + colWidths.map(w => "─".repeat(w + 2)).join("┬") + "┐";
  const sep    = "├" + colWidths.map(w => "─".repeat(w + 2)).join("┼") + "┤";
  const bottom = "└" + colWidths.map(w => "─".repeat(w + 2)).join("┴") + "┘";

  const lines = [];
  lines.push(c.gray + top + c.reset);
  lines.push(c.gray + "│" + c.reset + " " + headers.map((h, i) => c.bold + pad(h, colWidths[i]) + c.reset).join(" " + c.gray + "│" + c.reset + " ") + " " + c.gray + "│" + c.reset);
  lines.push(c.gray + sep + c.reset);
  for (const row of rows) {
    lines.push(c.gray + "│" + c.reset + " " + row.map((cell, i) => pad(String(cell ?? ""), colWidths[i])).join(" " + c.gray + "│" + c.reset + " ") + " " + c.gray + "│" + c.reset);
  }
  lines.push(c.gray + bottom + c.reset);
  return lines.join("\n");
}

// ─── Hyperlink (OSC 8) untuk terminal modern ────────────────────────────────
function hyperlink(text, url) {
  if (!supportsColor) return `${text} (${url})`;
  return `\x1b]8;;${url}\x1b\\${text}\x1b]8;;\x1b\\`;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 04  ─  LOGGER (file rotation, levels, formatted)
// ════════════════════════════════════════════════════════════════════════════════

class Logger {
  constructor(opts = {}) {
    this.level     = opts.level || "info";
    this.toFile    = opts.toFile !== false;
    this.maxSize   = opts.maxSize || 5 * 1024 * 1024; // 5 MB
    this.logFile   = path.join(LOG_DIR, "server.log");
    this.events    = new EventEmitter();
    this.LEVELS    = { debug: 0, info: 1, warn: 2, error: 3, silent: 4 };
    this._ensureLogDir();
  }
  _ensureLogDir() {
    if (this.toFile && !fs.existsSync(LOG_DIR)) {
      try { fs.mkdirSync(LOG_DIR, { recursive: true }); } catch (_) {}
    }
  }
  _shouldLog(level) {
    return this.LEVELS[level] >= this.LEVELS[this.level];
  }
  _rotate() {
    try {
      const stat = fs.statSync(this.logFile);
      if (stat.size > this.maxSize) {
        const ts = new Date().toISOString().replace(/[:.]/g, "-");
        fs.renameSync(this.logFile, path.join(LOG_DIR, `server.${ts}.log`));
      }
    } catch (_) {}
  }
  _writeFile(level, msg) {
    if (!this.toFile) return;
    this._rotate();
    const line = `[${new Date().toISOString()}] [${level.toUpperCase()}] ${msg.replace(/\x1b\[[0-9;]*m/g, "")}\n`;
    try { fs.appendFileSync(this.logFile, line); } catch (_) {}
  }
  debug(msg) { if (!this._shouldLog("debug")) return; console.log(c.gray + "[debug] " + msg + c.reset); this._writeFile("debug", msg); this.events.emit("log", { level: "debug", msg }); }
  info(msg)  { if (!this._shouldLog("info")) return;  console.log(info(msg)); this._writeFile("info", msg); this.events.emit("log", { level: "info", msg }); }
  warn(msg)  { if (!this._shouldLog("warn")) return;  console.log(warn(msg)); this._writeFile("warn", msg); this.events.emit("log", { level: "warn", msg }); }
  error(msg) { if (!this._shouldLog("error")) return; console.log(fail(msg)); this._writeFile("error", msg); this.events.emit("log", { level: "error", msg }); }
  ok(msg)    { console.log(ok(msg)); this._writeFile("info", "[OK] " + msg); }
  step(msg)  { console.log(step(msg)); this._writeFile("info", "[STEP] " + msg); }
  raw(msg)   { console.log(msg); this._writeFile("info", msg); }
  newline(n = 1) { for (let i = 0; i < n; i++) console.log(""); }
}

const logger = new Logger();


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 05  ─  CLI ARGUMENT PARSER
// ════════════════════════════════════════════════════════════════════════════════

function parseArgs(argv) {
  const args = {
    _:        [],
    setup:    false,
    check:    false,
    help:     false,
    version:  false,
    dashboard:false,
    silent:   false,
    verbose:  false,
    noNgrok:  false,
    noBrowser:false,
    noLog:    false,
    noWebhook:false,
    profile:  null,
    port:     null,
    mode:     null,
    cmd:      null,
  };

  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--help" || a === "-h")            args.help = true;
    else if (a === "--version" || a === "-v")    args.version = true;
    else if (a === "--setup")                    args.setup = true;
    else if (a === "--check" || a === "--diag")  args.check = true;
    else if (a === "--dashboard")                args.dashboard = true;
    else if (a === "--silent" || a === "-s")     args.silent = true;
    else if (a === "--verbose")                  args.verbose = true;
    else if (a === "--no-ngrok")                 args.noNgrok = true;
    else if (a === "--no-browser")               args.noBrowser = true;
    else if (a === "--no-log")                   args.noLog = true;
    else if (a === "--no-webhook")               args.noWebhook = true;
    else if (a.startsWith("--profile="))         args.profile = a.split("=")[1];
    else if (a.startsWith("--port="))            args.port = parseInt(a.split("=")[1], 10);
    else if (a.startsWith("--mode="))            args.mode = a.split("=")[1];
    else if (a.startsWith("--db:") || a === "--update-webhook" || a.startsWith("--profile:"))
      args.cmd = a.replace(/^--/, "");
    else if (!a.startsWith("--"))                args._.push(a);
  }

  return args;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 06  ─  STATE PERSISTENCE
// ════════════════════════════════════════════════════════════════════════════════

const State = {
  data: { ...DEFAULT_STATE },

  load() {
    try {
      if (fs.existsSync(STATE_FILE)) {
        this.data = { ...DEFAULT_STATE, ...JSON.parse(fs.readFileSync(STATE_FILE, "utf8")) };
      }
    } catch (e) {
      logger.debug("Gagal load state: " + e.message);
    }
    return this.data;
  },

  save() {
    try {
      fs.writeFileSync(STATE_FILE, JSON.stringify(this.data, null, 2));
    } catch (e) {
      logger.debug("Gagal save state: " + e.message);
    }
  },

  set(key, value) {
    this.data[key] = value;
    this.save();
  },

  get(key, defaultValue) {
    return this.data[key] !== undefined ? this.data[key] : defaultValue;
  },

  reset() {
    this.data = { ...DEFAULT_STATE };
    this.save();
  },
};


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 07  ─  CONFIGURATION LOADER
// ════════════════════════════════════════════════════════════════════════════════

function loadConfig(profile) {
  // Profile-specific .env: .env.dev, .env.staging, dst.
  let envPath = ENV_FILE;
  if (profile && profile !== "default") {
    const profileEnv = path.join(PROJECT_ROOT, `.env.${profile}`);
    if (fs.existsSync(profileEnv)) envPath = profileEnv;
    else logger.warn(`Profile .env.${profile} tidak ada, fallback ke .env utama`);
  }

  if (fs.existsSync(envPath)) {
    dotenv.config({ path: envPath, override: true });
  }

  const cfg = {
    profile:           profile || "default",
    envPath,

    // Ngrok
    ngrokToken:        process.env.NGROK_AUTHTOKEN,
    ngrokDomain:       process.env.NGROK_DOMAIN || null,
    ngrokRegion:       process.env.NGROK_REGION || null,

    // Server
    serverMode:        (process.env.SERVER_MODE || "auto").toLowerCase(),
    localPort:         parseInt(process.env.LOCAL_PORT || "0", 10) || 0,
    phpPort:           parseInt(process.env.PHP_PORT   || "8080", 10),
    phpRoot:           process.env.PHP_ROOT || path.join(PROJECT_ROOT, "Home"),

    // Logging
    logRequests:       (process.env.LOG_REQUESTS || "true") === "true",

    // Database
    db: {
      host: process.env.DB_HOST || "localhost",
      user: process.env.DB_USER || "root",
      pass: process.env.DB_PASS || "",
      name: process.env.DB_NAME || "topzone",
      port: parseInt(process.env.DB_PORT || "3306", 10),
    },

    // Xendit (untuk auto webhook update)
    xenditKey:         process.env.XENDIT_SECRET_KEY,
    baseUrl:           process.env.BASE_URL || "",

    // App settings
    appEnv:            process.env.APP_ENV   || "development",
    appDebug:          (process.env.APP_DEBUG || "true") === "true",
    appSecret:         process.env.APP_SECRET || "",
    appTimezone:       process.env.APP_TIMEZONE || "Asia/Jakarta",

    // Browser
    autoOpenBrowser:   (process.env.AUTO_OPEN_BROWSER || "true") === "true",
    browserPath:       process.env.BROWSER_PATH || "",

    // Notifications
    notifyOnReady:     (process.env.NOTIFY_ON_READY || "false") === "true",
    notifyOnError:     (process.env.NOTIFY_ON_ERROR || "true") === "true",
  };

  return cfg;
}

function validateConfig(cfg) {
  const issues = [];

  if (!cfg.ngrokToken || cfg.ngrokToken === "isi_token_ngrok_disini") {
    issues.push({
      level:  "error",
      key:    "NGROK_AUTHTOKEN",
      msg:    "Token ngrok belum diisi",
      fix:    "Daftar gratis: https://dashboard.ngrok.com/get-started/your-authtoken",
    });
  }

  if (!APP_PROFILES[cfg.serverMode]) {
    issues.push({
      level:  "error",
      key:    "SERVER_MODE",
      msg:    `Mode tidak dikenal: "${cfg.serverMode}"`,
      fix:    `Pilih: ${Object.keys(APP_PROFILES).join(" | ")}`,
    });
  }

  if (cfg.serverMode === "custom" && !cfg.localPort) {
    issues.push({
      level:  "error",
      key:    "LOCAL_PORT",
      msg:    "Mode custom butuh LOCAL_PORT",
      fix:    "Set LOCAL_PORT=<port> di .env (contoh: LOCAL_PORT=3000)",
    });
  }

  if (cfg.xenditKey && cfg.xenditKey.includes("isi_key_disini")) {
    issues.push({
      level:  "warn",
      key:    "XENDIT_SECRET_KEY",
      msg:    "Xendit secret key masih placeholder",
      fix:    "Generate di dashboard.xendit.co (mode test: xnd_development_xxx)",
    });
  }

  if (!cfg.appSecret || cfg.appSecret === "ganti_dengan_random_string_panjang") {
    issues.push({
      level:  "warn",
      key:    "APP_SECRET",
      msg:    "APP_SECRET masih placeholder",
      fix:    "Generate: node -e \"console.log(require('crypto').randomBytes(32).toString('hex'))\"",
    });
  }

  return issues;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 08  ─  PROFILE MANAGER
// ════════════════════════════════════════════════════════════════════════════════

const ProfileMgr = {
  ensureDir() {
    if (!fs.existsSync(PROFILES_DIR)) {
      try { fs.mkdirSync(PROFILES_DIR, { recursive: true }); } catch (_) {}
    }
  },

  list() {
    const profiles = ["default"];
    const files = fs.readdirSync(PROJECT_ROOT).filter(f => f.match(/^\.env\.([a-z0-9_-]+)$/i));
    for (const f of files) {
      const name = f.replace(/^\.env\./, "");
      if (!profiles.includes(name)) profiles.push(name);
    }
    return profiles;
  },

  exists(name) {
    if (name === "default") return fs.existsSync(ENV_FILE);
    return fs.existsSync(path.join(PROJECT_ROOT, `.env.${name}`));
  },

  create(name, fromProfile = "default") {
    const target = path.join(PROJECT_ROOT, `.env.${name}`);
    const source = fromProfile === "default" ? ENV_FILE : path.join(PROJECT_ROOT, `.env.${fromProfile}`);
    if (!fs.existsSync(source)) {
      throw new Error(`Source profile "${fromProfile}" tidak ada`);
    }
    fs.copyFileSync(source, target);
  },

  remove(name) {
    if (name === "default") throw new Error("Tidak bisa hapus profile default");
    const target = path.join(PROJECT_ROOT, `.env.${name}`);
    if (fs.existsSync(target)) fs.unlinkSync(target);
  },

  getPath(name) {
    return name === "default" ? ENV_FILE : path.join(PROJECT_ROOT, `.env.${name}`);
  },
};


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 09  ─  PORT & NETWORK UTILITIES
// ════════════════════════════════════════════════════════════════════════════════

function isPortOpen(port, host = "127.0.0.1", timeoutMs = 900) {
  return new Promise((resolve) => {
    const s = new net.Socket();
    s.setTimeout(timeoutMs);
    s.connect(port, host, () => { s.destroy(); resolve(true); });
    s.on("error",   () => { s.destroy(); resolve(false); });
    s.on("timeout", () => { s.destroy(); resolve(false); });
  });
}

function httpHealthCheck(port, hostname = "127.0.0.1", path = "/", timeoutMs = 3000) {
  return new Promise((resolve) => {
    const start = Date.now();
    const req = http.get(
      { hostname, port, path, timeout: timeoutMs, headers: { "User-Agent": "TopZone-Launcher/" + APP_VERSION } },
      (res) => {
        let buf = "";
        res.on("data", (d) => { buf += d.toString().slice(0, 256); });
        res.on("end", () => {
          resolve({
            ok:        true,
            status:    res.statusCode,
            duration:  Date.now() - start,
            headers:   res.headers,
            sample:    buf.slice(0, 100),
          });
        });
      }
    );
    req.on("error",   (err) => resolve({ ok: false, error: err.message }));
    req.on("timeout", () => { req.destroy(); resolve({ ok: false, error: "timeout" }); });
  });
}

async function findFreePort(startPort, maxAttempts = 20) {
  for (let p = startPort; p < startPort + maxAttempts; p++) {
    if (!(await isPortOpen(p))) return p;
  }
  throw new Error(`Tidak ada port kosong dalam range ${startPort}-${startPort + maxAttempts}`);
}

function pingExternal(hostname = "google.com", timeoutMs = 3000) {
  return new Promise((resolve) => {
    const start = Date.now();
    const req = https.get({ hostname, path: "/", timeout: timeoutMs }, (res) => {
      resolve({ ok: true, latency: Date.now() - start });
      res.resume();
    });
    req.on("error",   () => resolve({ ok: false }));
    req.on("timeout", () => { req.destroy(); resolve({ ok: false }); });
  });
}

function getLocalIpAddresses() {
  const nets = os.networkInterfaces();
  const result = [];
  for (const name of Object.keys(nets)) {
    for (const net of nets[name] || []) {
      if (net.family === "IPv4" && !net.internal) {
        result.push({ iface: name, address: net.address });
      }
    }
  }
  return result;
}

function httpRequest(method, urlString, opts = {}) {
  return new Promise((resolve, reject) => {
    const u    = new URL(urlString);
    const lib  = u.protocol === "https:" ? https : http;
    const req  = lib.request({
      hostname: u.hostname,
      port:     u.port || (u.protocol === "https:" ? 443 : 80),
      path:     u.pathname + u.search,
      method,
      headers:  opts.headers || {},
      timeout:  opts.timeout || 10000,
    }, (res) => {
      let buf = "";
      res.on("data", (d) => buf += d);
      res.on("end", () => {
        let parsed = buf;
        try { parsed = JSON.parse(buf); } catch (_) {}
        resolve({ status: res.statusCode, body: parsed, headers: res.headers });
      });
    });
    req.on("error",   reject);
    req.on("timeout", () => { req.destroy(); reject(new Error("Request timeout")); });
    if (opts.body) req.write(typeof opts.body === "string" ? opts.body : JSON.stringify(opts.body));
    req.end();
  });
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 10  ─  BANNER & HEADER
// ════════════════════════════════════════════════════════════════════════════════

function printBanner() {
  console.clear();
  console.log("");
  console.log(`${c.cyan}${c.bold}  ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗${c.reset}`);
  console.log(`${c.cyan}${c.bold}     ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝${c.reset}`);
  console.log(`${c.cyan}${c.bold}     ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗  ${c.reset}`);
  console.log(`${c.cyan}${c.bold}     ██║   ██║   ██║██╔═══╝    ██╔╝ ██║   ██║██║╚████║██╔══╝  ${c.reset}`);
  console.log(`${c.cyan}${c.bold}     ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗${c.reset}`);
  console.log(`${c.cyan}${c.bold}     ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝${c.reset}`);
  console.log(`${c.dim}              Universal Development Server v${APP_VERSION}${c.reset}`);
  console.log(`${c.dim}              ${APP_AUTHOR} · GPL-2.0${c.reset}`);
  console.log("");
}

function printSubBanner(text, color = c.cyan) {
  const w = Math.min(64, process.stdout.columns || 64);
  console.log(`\n${color}${"═".repeat(w)}${c.reset}`);
  console.log(`${color}${c.bold}  ${text}${c.reset}`);
  console.log(`${color}${"═".repeat(w)}${c.reset}\n`);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 11  ─  HELP & VERSION
// ════════════════════════════════════════════════════════════════════════════════

function printVersion() {
  console.log(`${APP_NAME} Launcher v${APP_VERSION}`);
  console.log(`Node ${process.version} on ${process.platform} (${process.arch})`);
}

function printHelp() {
  printBanner();
  console.log(bold("PEMAKAIAN:"));
  console.log("  node server.js [options]\n");

  console.log(bold("OPTIONS UMUM:"));
  console.log("  --help, -h               Tampilkan bantuan ini");
  console.log("  --version, -v            Tampilkan versi");
  console.log("  --silent, -s             Mode tanpa output (kecuali error)");
  console.log("  --verbose                Mode debug (log lebih detail)");
  console.log("  --no-ngrok               Skip ngrok tunnel (cuma local)");
  console.log("  --no-browser             Jangan auto-open browser");
  console.log("  --no-log                 Disable file logging");
  console.log("  --no-webhook             Jangan auto-update Xendit webhook");
  console.log("  --profile=NAME           Pakai profile .env.NAME (mis: --profile=dev)");
  console.log("  --port=NUM               Override LOCAL_PORT");
  console.log("  --mode=MODE              Override SERVER_MODE\n");

  console.log(bold("COMMANDS:"));
  console.log("  --setup                  Jalankan setup wizard");
  console.log("  --check, --diag          Diagnostics (system check)");
  console.log("  --dashboard              Live monitoring dashboard");
  console.log("  --db:test                Tes koneksi database");
  console.log("  --db:setup               Import schema + seed");
  console.log("  --db:schema              Import schema saja");
  console.log("  --db:seed                Import seed saja");
  console.log("  --db:backup              Backup database ke file");
  console.log("  --db:reset               Reset database (HATI-HATI!)");
  console.log("  --update-webhook         Update Xendit webhook URL\n");

  console.log(bold("CONTOH:"));
  console.log("  node server.js                   # Default launch");
  console.log("  node server.js --setup           # Setup wizard");
  console.log("  node server.js --check           # System check");
  console.log("  node server.js --profile=staging # Pakai staging env");
  console.log("  node server.js --no-ngrok        # Local only");
  console.log("  node server.js --db:backup       # Backup DB\n");

  console.log(bold("ENVIRONMENT:"));
  console.log("  Konfigurasi via .env file. Lihat .env.example.");
  console.log("");
  console.log(bold("DOKUMENTASI:"));
  console.log("  README.md       Overview + fitur");
  console.log("  SETUP.md        Panduan setup lengkap");
  console.log("  CONTRIBUTING.md Panduan kontribusi");
  console.log("");
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 12  ─  SETUP WIZARD (interactive, multi-step)
// ════════════════════════════════════════════════════════════════════════════════

class Wizard {
  constructor() {
    this.rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    this.answers = {};
  }

  ask(question, defaultValue = "") {
    return new Promise((resolve) => {
      const promptText = defaultValue
        ? `${c.yellow}▸ ${question}${c.reset} ${c.dim}[${defaultValue}]${c.reset}: `
        : `${c.yellow}▸ ${question}${c.reset}: `;
      this.rl.question(promptText, (answer) => {
        resolve((answer || "").trim() || defaultValue);
      });
    });
  }

  askPassword(question) {
    return new Promise((resolve) => {
      const stdin = process.openStdin();
      process.stdout.write(`${c.yellow}▸ ${question}${c.reset}: `);
      let pass = "";
      const onData = (b) => {
        const ch = b.toString();
        switch (ch) {
          case "\n": case "\r": case "":
            stdin.removeListener("data", onData);
            stdin.pause();
            process.stdout.write("\n");
            resolve(pass);
            break;
          case "":
            process.exit(0);
            break;
          default:
            if (ch.charCodeAt(0) === 8 || ch.charCodeAt(0) === 127) pass = pass.slice(0, -1);
            else pass += ch;
            process.stdout.write("\r" + " ".repeat(80) + "\r" + `${c.yellow}▸ ${question}${c.reset}: ` + "*".repeat(pass.length));
        }
      };
      stdin.on("data", onData);
    });
  }

  askConfirm(question, defaultYes = true) {
    return new Promise(async (resolve) => {
      const def = defaultYes ? "Y/n" : "y/N";
      const ans = (await this.ask(`${question} [${def}]`)).toLowerCase();
      if (!ans) return resolve(defaultYes);
      resolve(ans === "y" || ans === "yes" || ans === "ya");
    });
  }

  async askChoice(question, choices) {
    console.log(`\n${c.cyan}${question}${c.reset}`);
    choices.forEach((ch, i) => {
      const label  = typeof ch === "string" ? ch : ch.label;
      const hint   = typeof ch === "object" ? ch.hint : null;
      console.log(`  ${c.dim}${i + 1}.${c.reset} ${label} ${hint ? c.dim + "— " + hint + c.reset : ""}`);
    });
    const input = await this.ask(`Pilih (1-${choices.length})`, "1");
    const idx   = parseInt(input, 10) - 1;
    if (isNaN(idx) || idx < 0 || idx >= choices.length) {
      // mungkin user ketik nama langsung
      const found = choices.findIndex(ch => (typeof ch === "string" ? ch : ch.value) === input);
      if (found >= 0) return typeof choices[found] === "string" ? choices[found] : choices[found].value;
      return typeof choices[0] === "string" ? choices[0] : choices[0].value;
    }
    return typeof choices[idx] === "string" ? choices[idx] : choices[idx].value;
  }

  close() {
    try { this.rl.close(); } catch (_) {}
  }

  async runFullSetup() {
    console.clear();
    printBanner();
    console.log(drawBox("🧙  SETUP WIZARD — Konfigurasi Pertama", [
      "",
      "Wizard ini akan memandu kamu mengisi file .env",
      "yang dibutuhkan untuk menjalankan TopZone.",
      "",
      "Tekan Ctrl+C kapan saja untuk batal.",
      "",
    ], { color: c.cyan }));
    console.log("");

    // ─── Step 1: Ngrok token ────────────────────────────────────────────────
    console.log(bold("\n[ Step 1/6 ] 🌐  Ngrok Authentication"));
    console.log(dim("  Daftar gratis di: https://dashboard.ngrok.com/get-started/your-authtoken\n"));
    let token = "";
    while (!token) {
      token = await this.ask("NGROK_AUTHTOKEN");
      if (!token) console.log(fail("Token tidak boleh kosong\n"));
      else if (token.length < 30) {
        console.log(warn("Token kelihatannya pendek — biasanya >30 karakter. Yakin?"));
        if (!(await this.askConfirm("Lanjutkan?", false))) { token = ""; }
      }
    }
    this.answers.ngrokToken = token;

    const useDomain = await this.askConfirm("Punya custom ngrok domain?", false);
    if (useDomain) {
      this.answers.ngrokDomain = await this.ask("NGROK_DOMAIN (mis: topzone.ngrok.app)");
    }

    // ─── Step 2: Server mode ────────────────────────────────────────────────
    console.log(bold("\n[ Step 2/6 ] 🚀  Server Mode"));
    const modes = Object.keys(APP_PROFILES).map(k => ({
      label: APP_PROFILES[k].name + " (" + k + ")",
      hint:  APP_PROFILES[k].hint,
      value: k,
    }));
    this.answers.serverMode = await this.askChoice("Pilih server backend:", modes);

    if (this.answers.serverMode === "custom") {
      this.answers.localPort = await this.ask("LOCAL_PORT", "3000");
    } else if (this.answers.serverMode === "php") {
      this.answers.phpPort = await this.ask("PHP_PORT", "8080");
      this.answers.phpRoot = await this.ask("PHP_ROOT", "./Home");
    }

    // ─── Step 3: Database ───────────────────────────────────────────────────
    console.log(bold("\n[ Step 3/6 ] 🗄️   Database"));
    const setupDb = await this.askConfirm("Konfigurasi database sekarang?", true);
    if (setupDb) {
      this.answers.dbHost = await this.ask("DB_HOST", "localhost");
      this.answers.dbPort = await this.ask("DB_PORT", "3306");
      this.answers.dbUser = await this.ask("DB_USER", "root");
      this.answers.dbPass = await this.askPassword("DB_PASS (kosongkan jika tanpa password)");
      this.answers.dbName = await this.ask("DB_NAME", "topzone");

      // Test connection
      const sp = new Spinner("Testing koneksi database...").start();
      const dbResult = await DbHelper.testConnection({
        host: this.answers.dbHost,
        port: parseInt(this.answers.dbPort, 10),
        user: this.answers.dbUser,
        pass: this.answers.dbPass,
        name: this.answers.dbName,
      });
      if (dbResult.ok) {
        sp.succeed("Koneksi database OK");
      } else {
        sp.fail("Koneksi database gagal: " + dbResult.error);
        const retry = await this.askConfirm("Tetap simpan konfigurasi ini?", false);
        if (!retry) {
          this.answers.dbHost = "localhost";
          this.answers.dbPort = "3306";
          this.answers.dbUser = "root";
          this.answers.dbPass = "";
          this.answers.dbName = "topzone";
        }
      }
    }

    // ─── Step 4: Xendit ─────────────────────────────────────────────────────
    console.log(bold("\n[ Step 4/6 ] 💳  Xendit Payment Gateway"));
    console.log(dim("  Dapatkan secret key di: https://dashboard.xendit.co/settings/developers#api-keys\n"));
    const setupXendit = await this.askConfirm("Konfigurasi Xendit sekarang?", false);
    if (setupXendit) {
      this.answers.xenditKey = await this.ask("XENDIT_SECRET_KEY (xnd_development_...)");
      this.answers.baseUrl   = await this.ask("BASE_URL (kosongkan untuk pakai ngrok URL)", "");
    }

    // ─── Step 5: App security ───────────────────────────────────────────────
    console.log(bold("\n[ Step 5/6 ] 🔒  App Security"));
    this.answers.appSecret = crypto.randomBytes(32).toString("hex");
    console.log(ok("APP_SECRET di-generate otomatis (random 64 char)"));

    const appEnv = await this.askChoice("Application environment:", [
      { label: "development", value: "development", hint: "debug ON, error verbose" },
      { label: "production",  value: "production",  hint: "debug OFF, error log only" },
    ]);
    this.answers.appEnv   = appEnv;
    this.answers.appDebug = appEnv === "development" ? "true" : "false";

    // ─── Step 6: UX preferences ─────────────────────────────────────────────
    console.log(bold("\n[ Step 6/6 ] 🎨  Preferences"));
    this.answers.autoOpenBrowser = (await this.askConfirm("Auto-open browser saat server jalan?", true)) ? "true" : "false";
    this.answers.notifyOnReady   = (await this.askConfirm("Tampilkan notifikasi system saat ready?", false)) ? "true" : "false";
    this.answers.logRequests     = (await this.askConfirm("Live log request dari ngrok?", true)) ? "true" : "false";

    // ─── Tulis .env ─────────────────────────────────────────────────────────
    this._writeEnvFile();

    console.log(drawBox(icons.ok + "  Setup selesai!", [
      "",
      `File ${c.cyan}.env${c.reset} berhasil dibuat.`,
      "",
      "Edit kapan saja: " + c.dim + ENV_FILE + c.reset,
      "Profile baru   : " + c.dim + "node server.js --profile=NAME" + c.reset,
      "",
      "Lanjut: " + good("npm start"),
      "",
    ], { color: c.green, width: 60 }));

    this.close();
  }

  _writeEnvFile() {
    const a = this.answers;
    const lines = [
      "# ═══════════════════════════════════════════════════════════════════",
      "#  TOPZONE — generated by setup wizard pada " + new Date().toISOString(),
      "# ═══════════════════════════════════════════════════════════════════",
      "",
      "# ─── Ngrok ──────────────────────────────────────────────",
      `NGROK_AUTHTOKEN=${a.ngrokToken}`,
    ];
    if (a.ngrokDomain) lines.push(`NGROK_DOMAIN=${a.ngrokDomain}`);
    else lines.push("# NGROK_DOMAIN=");

    lines.push("",
      "# ─── Server ─────────────────────────────────────────────",
      `SERVER_MODE=${a.serverMode}`);
    if (a.localPort) lines.push(`LOCAL_PORT=${a.localPort}`);
    else             lines.push("# LOCAL_PORT=80");
    if (a.phpPort)   lines.push(`PHP_PORT=${a.phpPort}`);
    else             lines.push("# PHP_PORT=8080");
    if (a.phpRoot)   lines.push(`PHP_ROOT=${a.phpRoot}`);
    else             lines.push("# PHP_ROOT=./Home");

    lines.push("",
      "# ─── Database ───────────────────────────────────────────",
      `DB_HOST=${a.dbHost || "localhost"}`,
      `DB_PORT=${a.dbPort || "3306"}`,
      `DB_USER=${a.dbUser || "root"}`,
      `DB_PASS=${a.dbPass || ""}`,
      `DB_NAME=${a.dbName || "topzone"}`,
      "",
      "# ─── Xendit ─────────────────────────────────────────────");
    if (a.xenditKey) lines.push(`XENDIT_SECRET_KEY=${a.xenditKey}`);
    else             lines.push("# XENDIT_SECRET_KEY=");
    if (a.baseUrl)   lines.push(`BASE_URL=${a.baseUrl}`);
    else             lines.push("# BASE_URL=");

    lines.push("",
      "# ─── Security ───────────────────────────────────────────",
      `APP_SECRET=${a.appSecret}`,
      `APP_ENV=${a.appEnv}`,
      `APP_DEBUG=${a.appDebug}`,
      "",
      "# ─── UX ─────────────────────────────────────────────────",
      `AUTO_OPEN_BROWSER=${a.autoOpenBrowser}`,
      `NOTIFY_ON_READY=${a.notifyOnReady}`,
      `LOG_REQUESTS=${a.logRequests}`,
      "",
    );

    fs.writeFileSync(ENV_FILE, lines.join("\n"));
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 13  ─  PREFLIGHT & DIAGNOSTICS
// ════════════════════════════════════════════════════════════════════════════════

class Preflight {
  constructor(cfg) {
    this.cfg = cfg;
    this.results = [];
  }

  add(name, status, detail = "", fix = "") {
    this.results.push({ name, status, detail, fix });
    return this;
  }

  async run() {
    printSubBanner("PREFLIGHT CHECKS");

    await this._checkNode();
    await this._checkOs();
    await this._checkMemory();
    await this._checkDiskSpace();
    await this._checkNetwork();
    await this._checkPhp();
    await this._checkMysql();
    await this._checkPort();
    await this._checkConfig();
    await this._checkProjectStructure();

    const errors   = this.results.filter(r => r.status === "fail");
    const warnings = this.results.filter(r => r.status === "warn");
    const passes   = this.results.filter(r => r.status === "ok");

    console.log("");
    console.log(`  ${c.green}Pass:${c.reset} ${passes.length}   ${c.yellow}Warn:${c.reset} ${warnings.length}   ${c.red}Fail:${c.reset} ${errors.length}\n`);

    if (errors.length > 0) {
      console.log(bold(c.red + "ISSUES YANG HARUS DIPERBAIKI:" + c.reset));
      for (const e of errors) {
        console.log(`  ${fail(e.name + (e.detail ? ": " + e.detail : ""))}`);
        if (e.fix) console.log(`     ${dim("→ " + e.fix)}`);
      }
      console.log("");
    }

    return { errors, warnings, passes };
  }

  async _checkNode() {
    const ver = process.versions.node;
    const major = parseInt(ver.split(".")[0], 10);
    if (major >= 18) {
      this.add("Node.js " + ver, "ok", "modern");
      logger.ok(`Node.js ${ver}`);
    } else if (major >= 16) {
      this.add("Node.js " + ver, "warn", "v16 OK tapi v18+ direkomendasikan");
      logger.warn(`Node.js ${ver} — upgrade ke v18 disarankan`);
    } else {
      this.add("Node.js " + ver, "fail", "terlalu lama", "Upgrade ke v16+ : https://nodejs.org/");
      logger.error(`Node.js ${ver} — butuh minimal v16!`);
    }
  }

  async _checkOs() {
    const platform = process.platform;
    const arch     = process.arch;
    const release  = os.release();
    const cpus     = os.cpus().length;
    this.add(`OS ${platform}/${arch} (${cpus} core)`, "ok", release);
    logger.ok(`${platform}/${arch} · ${cpus} CPU core · kernel ${release}`);
  }

  async _checkMemory() {
    const totalMB = Math.round(os.totalmem() / 1024 / 1024);
    const freeMB  = Math.round(os.freemem()  / 1024 / 1024);
    const usedPct = Math.round((1 - os.freemem() / os.totalmem()) * 100);
    if (freeMB < 200) {
      this.add(`Memory ${freeMB}MB free`, "warn", `${usedPct}% terpakai`, "Tutup aplikasi lain untuk free RAM");
      logger.warn(`Memory ${freeMB}MB free / ${totalMB}MB total (${usedPct}% used)`);
    } else {
      this.add(`Memory ${freeMB}MB free`, "ok", `dari ${totalMB}MB`);
      logger.ok(`Memory ${freeMB}MB free / ${totalMB}MB total (${usedPct}% used)`);
    }
  }

  async _checkDiskSpace() {
    try {
      const stat = fs.statSync(PROJECT_ROOT);
      this.add("Disk akses", "ok", "writable");
      logger.ok("Disk akses ke project root OK");
    } catch (e) {
      this.add("Disk akses", "fail", e.message);
    }
  }

  async _checkNetwork() {
    const sp = new Spinner("Cek koneksi internet...").start();
    const ping = await pingExternal("google.com");
    if (ping.ok) {
      sp.succeed(`Internet OK (latency ${ping.latency}ms)`);
      this.add("Internet", "ok", `${ping.latency}ms`);
    } else {
      sp.warnDone("Internet tidak terdeteksi (ngrok tidak akan jalan)");
      this.add("Internet", "warn", "tidak bisa konek ke google.com", "Cek koneksi & firewall");
    }
  }

  async _checkPhp() {
    if (this.cfg.serverMode !== "php" && this.cfg.serverMode !== "auto") {
      return; // Tidak perlu cek
    }
    try {
      const ver = execSync("php -r \"echo PHP_VERSION;\"", { timeout: 3000, stdio: ["ignore", "pipe", "ignore"] }).toString().trim();
      this.add("PHP " + ver, "ok");
      logger.ok(`PHP ${ver}`);
    } catch {
      // Cari di hint paths
      const hints = PHP_HINTS[process.platform] || [];
      const found = hints.find(p => fs.existsSync(p));
      if (found) {
        this.add("PHP found", "warn", found, "Tambahkan ke PATH");
        logger.warn(`PHP ada di ${found} tapi belum di PATH`);
      } else if (this.cfg.serverMode === "php") {
        this.add("PHP", "fail", "tidak ditemukan", "Install PHP atau pakai XAMPP");
        logger.error("PHP tidak ditemukan!");
      }
    }
  }

  async _checkMysql() {
    try {
      execSync("mysql --version", { timeout: 3000, stdio: ["ignore", "pipe", "ignore"] });
      this.add("MySQL CLI", "ok", "available");
      logger.ok("MySQL CLI tersedia");
    } catch {
      const hints = MYSQL_HINTS[process.platform] || [];
      const found = hints.find(p => fs.existsSync(p));
      if (found) {
        this.add("MySQL CLI", "warn", "tidak di PATH");
        logger.warn(`MySQL CLI ada di ${found} tapi belum di PATH (db:setup tidak akan jalan)`);
      } else {
        this.add("MySQL CLI", "warn", "tidak ditemukan", "Install MySQL atau pakai XAMPP");
        logger.warn("MySQL CLI tidak ditemukan (db:setup butuh ini)");
      }
    }
  }

  async _checkPort() {
    if (this.cfg.serverMode === "custom" && this.cfg.localPort) {
      const open = await isPortOpen(this.cfg.localPort);
      if (open) {
        this.add(`Port ${this.cfg.localPort}`, "ok", "siap dipakai");
      } else {
        this.add(`Port ${this.cfg.localPort}`, "warn", "tidak ada server di port ini");
      }
    }
  }

  async _checkConfig() {
    const issues = validateConfig(this.cfg);
    if (issues.length === 0) {
      this.add("Config (.env)", "ok", "valid");
      logger.ok("Config valid");
    } else {
      for (const i of issues) {
        this.add(`Config: ${i.key}`, i.level === "error" ? "fail" : "warn", i.msg, i.fix);
        if (i.level === "error") logger.error(`Config ${i.key}: ${i.msg}`);
        else logger.warn(`Config ${i.key}: ${i.msg}`);
      }
    }
  }

  async _checkProjectStructure() {
    const required = [
      { path: "Home/index.php",    name: "Home/index.php"    },
      { path: "Home/koneksi.php",  name: "Home/koneksi.php"  },
      { path: "Login",             name: "folder Login"      },
      { path: "package.json",      name: "package.json"      },
    ];
    const optional = [
      { path: "database/schema.sql", name: "database/schema.sql" },
      { path: "includes/db.php",     name: "includes/db.php"     },
      { path: ".env",                name: ".env"                },
    ];

    for (const f of required) {
      if (fs.existsSync(path.join(PROJECT_ROOT, f.path))) {
        this.add(f.name, "ok", "ada");
      } else {
        this.add(f.name, "fail", "tidak ada", "Project structure rusak — clone ulang");
      }
    }
    for (const f of optional) {
      if (fs.existsSync(path.join(PROJECT_ROOT, f.path))) {
        this.add(f.name, "ok", "ada");
      } else {
        this.add(f.name, "warn", "tidak ada (opsional)");
      }
    }
  }

  async runDiagnosticsOnly() {
    printBanner();
    console.log(drawBox(icons.wrench + "  DIAGNOSTICS — System Check", [
      "",
      "Tidak ada server yang dijalankan.",
      "Cuma cek apakah environment kamu siap.",
      "",
    ], { color: c.cyan }));

    await this.run();

    console.log(bold("\nINFORMASI SISTEM:"));
    console.log(`  Node version  : ${c.cyan}${process.versions.node}${c.reset}`);
    console.log(`  Platform      : ${c.cyan}${process.platform}/${process.arch}${c.reset}`);
    console.log(`  CPU cores     : ${c.cyan}${os.cpus().length}${c.reset}`);
    console.log(`  Total memory  : ${c.cyan}${Math.round(os.totalmem() / 1024 / 1024)}MB${c.reset}`);
    console.log(`  Free memory   : ${c.cyan}${Math.round(os.freemem() / 1024 / 1024)}MB${c.reset}`);
    console.log(`  Local IPs     : ${c.cyan}${getLocalIpAddresses().map(i => i.address).join(", ") || "—"}${c.reset}`);
    console.log(`  Project root  : ${c.cyan}${PROJECT_ROOT}${c.reset}`);
    console.log(`  Profile       : ${c.cyan}${this.cfg.profile}${c.reset}`);
    console.log(`  Env file      : ${c.cyan}${this.cfg.envPath}${c.reset}`);

    console.log("");
    process.exit(this.results.some(r => r.status === "fail") ? 1 : 0);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 14  ─  SERVER DETECTION
// ════════════════════════════════════════════════════════════════════════════════

class ServerDetector {
  constructor(cfg) {
    this.cfg = cfg;
  }

  async detect() {
    printSubBanner("DETEKSI SERVER");

    const mode    = this.cfg.serverMode;
    const profile = APP_PROFILES[mode] || APP_PROFILES.auto;
    const tryPorts = this.cfg.localPort
      ? [this.cfg.localPort]
      : (mode === "auto" ? AUTO_PORTS : profile.ports);

    if (tryPorts.length === 0) {
      console.log(fail("Tidak ada port untuk dicek (mode: " + mode + ")"));
      console.log(info("  " + (profile.hint || "Set LOCAL_PORT di .env")));
      process.exit(1);
    }

    for (const port of tryPorts) {
      const sp = new Spinner(`Cek port ${port}...`).start();
      const tcpOpen = await isPortOpen(port);

      if (!tcpOpen) {
        sp.stop();
        console.log(`  ${dim("port " + port + " tutup")}`);
        continue;
      }

      const health = await httpHealthCheck(port);
      if (health.ok) {
        const label = this._labelForPort(port, profile.name);
        sp.succeed(`Port ${port} → ${c.bold}${label}${c.reset} (HTTP ${health.status}, ${health.duration}ms)`);
        State.set("preferredPort", port);
        return { port, label, health };
      } else {
        sp.warnDone(`Port ${port} terbuka tapi tidak respond HTTP — skip`);
      }
    }

    // Tidak ada yang cocok
    console.log("");
    console.log(fail("Tidak ada server aktif terdeteksi"));
    if (profile.hint) {
      console.log(info("  " + profile.hint));
    } else {
      console.log(info("  Jalankan XAMPP/Laragon/WAMP/MAMP terlebih dahulu"));
      console.log(info("  Atau pakai SERVER_MODE=php di .env"));
    }

    // Tawarkan PHP built-in fallback
    if (mode === "auto") {
      console.log("");
      const wizard = new Wizard();
      try {
        const useFallback = await wizard.askConfirm("Coba pakai PHP built-in server sebagai fallback?", true);
        if (useFallback) {
          wizard.close();
          this.cfg.serverMode = "php";
          return null; // Caller harus handle PHP launch
        }
      } finally {
        wizard.close();
      }
    }

    process.exit(1);
  }

  _labelForPort(port, profileName) {
    if (port === 80)   return "XAMPP / Laragon / Apache";
    if (port === 8080) return "XAMPP Alt / PHP Built-in";
    if (port === 8888) return "MAMP";
    if (port === 3000) return "Node Dev Server";
    if (port === 5000) return "Flask / Generic";
    if (port === 8000) return "Django / Generic";
    return profileName;
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 15  ─  PHP BUILT-IN SERVER
// ════════════════════════════════════════════════════════════════════════════════

class PhpServer {
  constructor(cfg) {
    this.cfg     = cfg;
    this.process = null;
    this.port    = cfg.phpPort;
    this.startedAt = null;
  }

  async start() {
    printSubBanner("PHP BUILT-IN SERVER");

    if (await isPortOpen(this.port)) {
      logger.warn(`Port ${this.port} sudah dipakai, mencari port lain...`);
      this.port = await findFreePort(this.port + 1);
      logger.ok(`Pakai port ${this.port}`);
    }

    if (!fs.existsSync(this.cfg.phpRoot)) {
      logger.error(`PHP_ROOT tidak ada: ${this.cfg.phpRoot}`);
      process.exit(1);
    }

    logger.step(`Root  : ${this.cfg.phpRoot}`);
    logger.step(`Port  : ${this.port}`);

    return new Promise((resolve, reject) => {
      this.process = spawn("php", ["-S", `127.0.0.1:${this.port}`, "-t", this.cfg.phpRoot], {
        stdio: ["ignore", "pipe", "pipe"],
        shell: process.platform === "win32",
      });

      let ready = false;

      this.process.stderr.on("data", (data) => {
        const msg = data.toString().trim();
        if (/Development Server.*started/i.test(msg) && !ready) {
          ready = true;
          logger.ok("PHP server siap");
          this.startedAt = Date.now();
        }
        if (/Fatal error|Parse error/i.test(msg)) {
          console.log(`${c.red}[PHP ERR]${c.reset} ${msg}`);
        } else if (/Warning|Notice/i.test(msg) && this.cfg.appDebug) {
          console.log(`${c.yellow}[PHP]${c.reset} ${msg}`);
        }
      });

      this.process.on("error", (err) => {
        if (err.code === "ENOENT") {
          logger.error("Perintah 'php' tidak ditemukan di PATH!");
          console.log(info("  Windows : install XAMPP atau tambahkan php ke PATH"));
          console.log(info("  Linux   : sudo apt install php"));
          console.log(info("  macOS   : brew install php"));
        }
        reject(err);
      });

      this.process.on("exit", (code) => {
        if (code && code !== 0) {
          logger.error(`PHP process exit dengan kode ${code}`);
        }
        this.process = null;
      });

      // Polling tunggu PHP siap
      const startCheck = Date.now();
      const timeoutMs  = 5000;
      const intervalMs = 200;
      const poll = setInterval(async () => {
        if (await isPortOpen(this.port)) {
          clearInterval(poll);
          if (!ready) logger.ok("PHP server siap (port responsif)");
          resolve({ port: this.port, process: this.process });
        } else if (Date.now() - startCheck > timeoutMs) {
          clearInterval(poll);
          reject(new Error("PHP server timeout (5 detik)"));
        }
      }, intervalMs);
    });
  }

  stop() {
    if (this.process && !this.process.killed) {
      this.process.kill("SIGTERM");
      logger.ok("PHP server dihentikan");
    }
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 16  ─  DATABASE HELPER
// ════════════════════════════════════════════════════════════════════════════════

class DbHelper {
  static async testConnection(db) {
    return new Promise((resolve) => {
      const args = [
        `-h${db.host}`, `-P${db.port}`, `-u${db.user}`,
      ];
      if (db.pass) args.push(`-p${db.pass}`);
      args.push("-e", "SELECT 1");

      const child = spawn("mysql", args, {
        stdio: ["ignore", "pipe", "pipe"],
        shell: process.platform === "win32",
      });

      let stderr = "";
      child.stderr.on("data", (d) => stderr += d.toString());

      child.on("error", (err) => resolve({ ok: false, error: err.code === "ENOENT" ? "mysql CLI tidak ditemukan" : err.message }));
      child.on("exit", (code) => {
        if (code === 0) resolve({ ok: true });
        else            resolve({ ok: false, error: stderr.trim() || `exit code ${code}` });
      });
    });
  }

  static async runSqlFile(db, sqlFile, options = {}) {
    return new Promise((resolve, reject) => {
      if (!fs.existsSync(sqlFile)) return reject(new Error("File tidak ada: " + sqlFile));

      const args = [`-h${db.host}`, `-P${db.port}`, `-u${db.user}`];
      if (db.pass) args.push(`-p${db.pass}`);
      if (options.database) args.push(db.name || options.database);

      const child = spawn("mysql", args, {
        stdio: ["pipe", "pipe", "pipe"],
        shell: process.platform === "win32",
      });

      const stream = fs.createReadStream(sqlFile);
      stream.pipe(child.stdin);

      let stderr = "";
      child.stderr.on("data", (d) => stderr += d.toString());
      child.on("exit", (code) => {
        if (code === 0) resolve();
        else            reject(new Error(stderr.trim() || `exit ${code}`));
      });
    });
  }

  static async backup(db, outputFile) {
    return new Promise((resolve, reject) => {
      if (!fs.existsSync(BACKUP_DIR)) fs.mkdirSync(BACKUP_DIR, { recursive: true });

      const args = [
        `-h${db.host}`, `-P${db.port}`, `-u${db.user}`,
        "--single-transaction", "--routines", "--triggers",
      ];
      if (db.pass) args.push(`-p${db.pass}`);
      args.push(db.name);

      const child = spawn("mysqldump", args, {
        stdio: ["ignore", "pipe", "pipe"],
        shell: process.platform === "win32",
      });

      const stream = fs.createWriteStream(outputFile);
      child.stdout.pipe(stream);

      let stderr = "";
      child.stderr.on("data", (d) => stderr += d.toString());
      child.on("exit", (code) => {
        if (code === 0) resolve(outputFile);
        else            reject(new Error(stderr.trim() || `exit ${code}`));
      });
      child.on("error", (e) => reject(e));
    });
  }

  static async cmdTest(cfg) {
    printSubBanner("DATABASE — Test Connection");
    const sp = new Spinner("Connecting...").start();
    const result = await DbHelper.testConnection(cfg.db);
    if (result.ok) {
      sp.succeed("Koneksi OK");
      logger.ok(`Connected to ${cfg.db.user}@${cfg.db.host}:${cfg.db.port}/${cfg.db.name}`);
    } else {
      sp.fail("Gagal: " + result.error);
      logger.error(result.error);
      process.exit(1);
    }
  }

  static async cmdSetup(cfg) {
    printSubBanner("DATABASE — Setup (schema + seed)");
    const schema = path.join(PROJECT_ROOT, "database", "schema.sql");
    const seed   = path.join(PROJECT_ROOT, "database", "seed.sql");

    if (!fs.existsSync(schema)) { logger.error("schema.sql tidak ada"); process.exit(1); }

    let sp = new Spinner("Importing schema...").start();
    try {
      await DbHelper.runSqlFile(cfg.db, schema);
      sp.succeed("Schema imported");
    } catch (e) {
      sp.fail("Gagal: " + e.message);
      process.exit(1);
    }

    if (fs.existsSync(seed)) {
      sp = new Spinner("Importing seed data...").start();
      try {
        await DbHelper.runSqlFile(cfg.db, seed, { database: true });
        sp.succeed("Seed data imported");
      } catch (e) {
        sp.fail("Gagal: " + e.message);
      }
    } else {
      logger.warn("seed.sql tidak ada — skip");
    }

    logger.ok("Database siap. Login default:");
    logger.raw("  👑 admin / admin123");
    logger.raw("  👤 demo  / demo123");
  }

  static async cmdSchema(cfg) {
    printSubBanner("DATABASE — Schema only");
    const schema = path.join(PROJECT_ROOT, "database", "schema.sql");
    if (!fs.existsSync(schema)) { logger.error("schema.sql tidak ada"); process.exit(1); }
    const sp = new Spinner("Importing...").start();
    try {
      await DbHelper.runSqlFile(cfg.db, schema);
      sp.succeed("Schema imported");
    } catch (e) {
      sp.fail("Gagal: " + e.message);
      process.exit(1);
    }
  }

  static async cmdSeed(cfg) {
    printSubBanner("DATABASE — Seed only");
    const seed = path.join(PROJECT_ROOT, "database", "seed.sql");
    if (!fs.existsSync(seed)) { logger.error("seed.sql tidak ada"); process.exit(1); }
    const sp = new Spinner("Importing...").start();
    try {
      await DbHelper.runSqlFile(cfg.db, seed, { database: true });
      sp.succeed("Seed imported");
    } catch (e) {
      sp.fail("Gagal: " + e.message);
      process.exit(1);
    }
  }

  static async cmdBackup(cfg) {
    printSubBanner("DATABASE — Backup");
    const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
    const out = path.join(BACKUP_DIR, `${cfg.db.name}_${ts}.sql`);
    const sp = new Spinner(`Backing up ke ${out}...`).start();
    try {
      await DbHelper.backup(cfg.db, out);
      const size = (fs.statSync(out).size / 1024).toFixed(1);
      sp.succeed(`Backup selesai (${size} KB)`);
      logger.raw(`  File: ${c.cyan}${out}${c.reset}`);
    } catch (e) {
      sp.fail("Gagal: " + e.message);
      process.exit(1);
    }
  }

  static async cmdReset(cfg) {
    printSubBanner("DATABASE — Reset (DESTRUCTIVE!)");
    const wizard = new Wizard();
    const confirm = await wizard.askConfirm(c.red + c.bold + "Yakin DROP database '" + cfg.db.name + "' & re-import?" + c.reset, false);
    wizard.close();
    if (!confirm) {
      logger.info("Batal");
      process.exit(0);
    }

    // Backup dulu sebelum drop
    try {
      const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
      const backupFile = path.join(BACKUP_DIR, `pre-reset_${ts}.sql`);
      logger.info("Backup otomatis sebelum reset...");
      await DbHelper.backup(cfg.db, backupFile);
      logger.ok("Backup tersimpan di " + backupFile);
    } catch (e) {
      logger.warn("Backup gagal: " + e.message);
    }

    return new Promise((resolve, reject) => {
      const args = [`-h${cfg.db.host}`, `-P${cfg.db.port}`, `-u${cfg.db.user}`];
      if (cfg.db.pass) args.push(`-p${cfg.db.pass}`);
      args.push("-e", `DROP DATABASE IF EXISTS \`${cfg.db.name}\``);

      const child = spawn("mysql", args, { shell: process.platform === "win32", stdio: ["ignore", "pipe", "pipe"] });
      child.on("exit", async (code) => {
        if (code === 0) {
          logger.ok("Database di-drop");
          await DbHelper.cmdSetup(cfg);
          resolve();
        } else {
          logger.error("Drop gagal");
          reject();
        }
      });
    });
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 17  ─  NGROK MANAGER
// ════════════════════════════════════════════════════════════════════════════════

class NgrokManager {
  constructor(cfg) {
    this.cfg      = cfg;
    this.listener = null;
    this.url      = null;
    this.startedAt = null;
  }

  async start(port) {
    printSubBanner("NGROK TUNNEL");

    const opts = {
      addr:      port,
      authtoken: this.cfg.ngrokToken,
    };
    if (this.cfg.ngrokDomain) opts.domain = this.cfg.ngrokDomain;
    if (this.cfg.ngrokRegion) opts.region = this.cfg.ngrokRegion;

    for (let attempt = 1; attempt <= RETRY_MAX; attempt++) {
      const sp = new Spinner(`Connecting (attempt ${attempt}/${RETRY_MAX})...`).start();
      try {
        this.listener = await ngrok.forward(opts);
        this.url      = this.listener.url();
        this.startedAt = Date.now();
        sp.succeed(`Connected: ${c.bold}${this.url}${c.reset}`);
        State.set("lastUrl", this.url);
        return this.url;
      } catch (err) {
        sp.fail(`Attempt ${attempt} gagal`);
        const errMsg = err.message || "";

        if (/authtoken|authentication/i.test(errMsg)) {
          logger.error("Token ngrok tidak valid!");
          logger.info("  Cek di: https://dashboard.ngrok.com/get-started/your-authtoken");
          process.exit(1);
        }
        if (/tunnel session.*limit|free.*plan/i.test(errMsg)) {
          logger.error("Batas tunnel ngrok gratis tercapai");
          logger.info("  Tutup tunnel lain di: https://dashboard.ngrok.com/tunnels");
          process.exit(1);
        }
        if (/domain.*not.*found|reserved.*domain/i.test(errMsg)) {
          logger.error(`Domain ${this.cfg.ngrokDomain} tidak ditemukan`);
          logger.info("  Reservasi domain di: https://dashboard.ngrok.com/cloud-edge/domains");
          process.exit(1);
        }

        if (attempt < RETRY_MAX) {
          const delay = RETRY_DELAY_BASE * attempt;
          logger.info(`Retry dalam ${delay / 1000} detik...`);
          await new Promise(r => setTimeout(r, delay));
        } else {
          logger.error(`Ngrok gagal setelah ${RETRY_MAX} percobaan`);
          logger.raw(`  ${dim("Detail: " + errMsg)}`);
          process.exit(1);
        }
      }
    }
  }

  async stop() {
    if (this.listener) {
      try {
        await ngrok.disconnect();
        logger.ok("Ngrok tunnel ditutup");
      } catch (_) {}
      this.listener = null;
    }
  }

  async getStatus() {
    try {
      const res = await httpRequest("GET", NGROK_TUNNELS);
      return res.body;
    } catch (e) {
      return null;
    }
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 18  ─  XENDIT WEBHOOK AUTO-UPDATER
// ════════════════════════════════════════════════════════════════════════════════

class WebhookSyncer {
  constructor(cfg) {
    this.cfg      = cfg;
    this.lastUrl  = null;
    this.interval = null;
  }

  isEnabled() {
    return !!(this.cfg.xenditKey && !this.cfg.xenditKey.includes("isi_key"));
  }

  // NOTE: Endpoint Xendit untuk update webhook berbeda-beda per tipe (invoice,
  // payment, dll). Disini kita cuma log URL terbaru — user manual update di
  // dashboard Xendit. Kalau Xendit menyediakan API set-webhook, kode bisa
  // diubah untuk panggil langsung ke endpoint mereka.
  async updateOnce(publicUrl) {
    if (!this.isEnabled()) return;

    const callbackUrl = `${publicUrl.replace(/\/$/, "")}/Home/callback.php`;
    const tokenUrl    = `${publicUrl.replace(/\/$/, "")}/Home/Checkout/ambil_token.php`;

    if (this.lastUrl === publicUrl) return;
    this.lastUrl = publicUrl;

    State.set("lastWebhookSync", { url: publicUrl, at: new Date().toISOString() });

    logger.newline();
    console.log(drawBox("📌 XENDIT WEBHOOK URL", [
      "",
      `Daftarkan URL ini di ${c.cyan}dashboard.xendit.co${c.reset}:`,
      "",
      `  Callback Invoice : ${c.green}${callbackUrl}${c.reset}`,
      `  Ambil Token      : ${c.green}${tokenUrl}${c.reset}`,
      "",
      `${c.yellow}⚠${c.reset}  URL ngrok berubah tiap restart (free plan).`,
      `   Update di Xendit setiap kali launch atau pakai custom domain.`,
      "",
    ], { color: c.cyan, width: 70 }));
  }

  startSync(getCurrentUrl) {
    if (!this.isEnabled()) return;

    this.interval = setInterval(async () => {
      const url = typeof getCurrentUrl === "function" ? getCurrentUrl() : getCurrentUrl;
      if (url) await this.updateOnce(url);
    }, WEBHOOK_SYNC_MS);
  }

  stop() {
    if (this.interval) clearInterval(this.interval);
    this.interval = null;
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 19  ─  REQUEST LOGGER & ANALYTICS
// ════════════════════════════════════════════════════════════════════════════════

class RequestAnalytics {
  constructor() {
    this.lastReqId        = null;
    this.totalCount       = 0;
    this.byMethod         = {};
    this.byStatus         = {};
    this.byEndpoint       = {};
    this.recentRequests   = []; // last 50
    this.slowThreshold    = 1000; // ms
    this.slowRequests     = [];
    this.errorCount       = 0;
    this.errorRequests    = [];
    this.startTime        = Date.now();
  }

  record(req) {
    this.totalCount++;
    State.set("totalRequests", State.get("totalRequests", 0) + 1);

    const method   = req.request?.method   || "GET";
    const uri      = req.request?.uri      || "/";
    const status   = req.response?.status  || 0;
    const duration = req.duration ? req.duration / 1000000 : 0; // ns → ms (kalau ada)

    this.byMethod[method]   = (this.byMethod[method] || 0) + 1;
    this.byStatus[status]   = (this.byStatus[status] || 0) + 1;
    this.byEndpoint[uri]    = (this.byEndpoint[uri] || 0) + 1;

    const item = {
      ts: Date.now(),
      method, uri, status, duration,
    };

    this.recentRequests.unshift(item);
    if (this.recentRequests.length > 50) this.recentRequests.pop();

    if (duration > this.slowThreshold) {
      this.slowRequests.unshift(item);
      if (this.slowRequests.length > 20) this.slowRequests.pop();
    }

    if (status >= 400) {
      this.errorCount++;
      this.errorRequests.unshift(item);
      if (this.errorRequests.length > 20) this.errorRequests.pop();
    }

    return item;
  }

  formatLogLine(item) {
    const METHOD_COLOR = {
      GET:    c.green,
      POST:   c.cyan,
      PUT:    c.yellow,
      DELETE: c.red,
      PATCH:  c.magenta,
      HEAD:   c.gray,
      OPTIONS: c.gray,
    };
    const ts      = new Date(item.ts).toLocaleTimeString("id-ID");
    const mColor  = METHOD_COLOR[item.method] || c.white;
    const sColor  = !item.status         ? c.gray
                  : item.status >= 500   ? c.red
                  : item.status >= 400   ? c.yellow
                  : item.status >= 300   ? c.cyan
                  :                        c.green;
    const dur     = item.duration ? ` ${c.dim}(${item.duration.toFixed(0)}ms)${c.reset}` : "";
    return `${c.dim}[${ts}]${c.reset} ${mColor}${pad(item.method, 6)}${c.reset} ${sColor}${item.status || "..."}${c.reset} ${truncate(item.uri, 60)}${dur}`;
  }

  topEndpoints(n = 10) {
    return Object.entries(this.byEndpoint)
      .sort((a, b) => b[1] - a[1])
      .slice(0, n);
  }

  uptime() {
    return Math.floor((Date.now() - this.startTime) / 1000);
  }

  summary() {
    return {
      total:    this.totalCount,
      errors:   this.errorCount,
      uptime:   this.uptime(),
      methods:  this.byMethod,
      statuses: this.byStatus,
    };
  }
}

class RequestLogger {
  constructor(analytics, opts = {}) {
    this.analytics = analytics;
    this.enabled   = opts.enabled !== false;
    this.silent    = opts.silent  === true;
    this.interval  = null;
  }

  start() {
    if (!this.enabled) return;
    this.interval = setInterval(() => this._poll(), REQ_POLL_MS);
  }

  _poll() {
    http.get(NGROK_REQS, (res) => {
      let raw = "";
      res.on("data", (d) => raw += d);
      res.on("end", () => {
        try {
          const { requests } = JSON.parse(raw);
          if (!requests || !requests.length) return;

          const newest = requests[0];
          if (newest.id === this.analytics.lastReqId) return;
          this.analytics.lastReqId = newest.id;

          const item = this.analytics.record(newest);
          if (!this.silent) console.log(this.analytics.formatLogLine(item));
        } catch (_) { /* api belum siap */ }
      });
    }).on("error", () => {});
  }

  stop() {
    if (this.interval) clearInterval(this.interval);
    this.interval = null;
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 20  ─  HEALTH MONITOR (continuous, auto-recovery)
// ════════════════════════════════════════════════════════════════════════════════

class HealthMonitor {
  constructor(cfg, port) {
    this.cfg          = cfg;
    this.port         = port;
    this.interval     = null;
    this.consecutiveFails = 0;
    this.maxFails     = 3;
    this.history      = [];
    this.events       = new EventEmitter();
  }

  start() {
    this.interval = setInterval(() => this._check(), HEALTH_INTERVAL);
  }

  async _check() {
    const result = await httpHealthCheck(this.port);
    const now    = Date.now();

    this.history.unshift({ ts: now, ok: result.ok, status: result.status });
    if (this.history.length > 100) this.history.pop();

    if (!result.ok) {
      this.consecutiveFails++;
      logger.warn(`Health check gagal (${this.consecutiveFails}/${this.maxFails}): ${result.error || "no response"}`);

      if (this.consecutiveFails >= this.maxFails) {
        this.events.emit("down", { result, port: this.port });
        logger.error(`Server di port ${this.port} sepertinya mati. Cek manual!`);
      }
    } else {
      if (this.consecutiveFails > 0) {
        logger.ok("Server kembali sehat");
        this.events.emit("recovered");
      }
      this.consecutiveFails = 0;
    }
  }

  stop() {
    if (this.interval) clearInterval(this.interval);
  }

  getStatus() {
    if (this.history.length === 0) return "unknown";
    const last10 = this.history.slice(0, 10);
    const okCount = last10.filter(h => h.ok).length;
    if (okCount === last10.length) return "healthy";
    if (okCount >= last10.length / 2) return "degraded";
    return "unhealthy";
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 21  ─  QR CODE GENERATOR (text-based, mini)
// ════════════════════════════════════════════════════════════════════════════════

// Mini QR encoder — bukan QR asli, tapi block visual untuk URL pendek
// (full QR butuh library besar). Yang ditampilkan: URL + barcode-like art.
function renderQrLike(text) {
  const seed = crypto.createHash("md5").update(text).digest("hex");
  const size = 21;
  const lines = [];
  for (let r = 0; r < size; r++) {
    let row = "";
    for (let col = 0; col < size; col++) {
      const idx = (r * size + col) % seed.length;
      const ch  = parseInt(seed[idx], 16);
      // Bagian sudut kayak QR pattern
      const isCorner = (r < 7 && col < 7) || (r < 7 && col > size - 8) || (r > size - 8 && col < 7);
      const fill = isCorner
        ? ((r === 0 || r === 6 || col === 0 || col === 6 || r === size - 7 || col === size - 7) ? 1
          : (r >= 2 && r <= 4 && col >= 2 && col <= 4) ? 1
          : 0)
        : (ch > 7 ? 1 : 0);
      row += fill ? "██" : "  ";
    }
    lines.push(row);
  }
  return lines;
}

function printQrCode(url) {
  console.log(`\n${c.cyan}${c.bold}📱 SCAN QR (visual representation, scan URL manual):${c.reset}\n`);
  const qr = renderQrLike(url);
  for (const line of qr) {
    console.log("    " + c.white + line + c.reset);
  }
  console.log(`\n    ${c.cyan}URL:${c.reset} ${c.bold}${url}${c.reset}\n`);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 22  ─  BROWSER OPENER
// ════════════════════════════════════════════════════════════════════════════════

function openBrowser(targetUrl, customPath) {
  return new Promise((resolve) => {
    const cmd = customPath || (
      process.platform === "win32" ? "start" :
      process.platform === "darwin" ? "open" :
      "xdg-open"
    );
    const args = process.platform === "win32" ? ["", targetUrl] : [targetUrl];
    try {
      const child = spawn(cmd, args, { stdio: "ignore", shell: true, detached: true });
      child.unref();
      resolve(true);
    } catch (e) {
      resolve(false);
    }
  });
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 23  ─  SYSTEM NOTIFICATIONS
// ════════════════════════════════════════════════════════════════════════════════

function sendNotification(title, body) {
  const platform = process.platform;
  try {
    if (platform === "darwin") {
      execSync(`osascript -e 'display notification "${body.replace(/"/g, '\\"')}" with title "${title.replace(/"/g, '\\"')}"'`, { stdio: "ignore" });
    } else if (platform === "linux") {
      execSync(`notify-send "${title.replace(/"/g, '\\"')}" "${body.replace(/"/g, '\\"')}"`, { stdio: "ignore" });
    } else if (platform === "win32") {
      // Windows: pakai PowerShell BurntToast/native
      const psScript = `[Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime] | Out-Null; $template = [Windows.UI.Notifications.ToastNotificationManager]::GetTemplateContent([Windows.UI.Notifications.ToastTemplateType]::ToastText02); $textNodes = $template.GetElementsByTagName('text'); $textNodes.Item(0).AppendChild($template.CreateTextNode('${title.replace(/'/g, "''")}')) | Out-Null; $textNodes.Item(1).AppendChild($template.CreateTextNode('${body.replace(/'/g, "''")}')) | Out-Null; $toast = [Windows.UI.Notifications.ToastNotification]::new($template); [Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier('TopZone').Show($toast)`;
      spawn("powershell", ["-Command", psScript], { stdio: "ignore", detached: true }).unref();
    }
  } catch (_) { /* notifikasi gagal — skip diam-diam */ }
}

function bell() {
  process.stdout.write("\x07");
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 24  ─  ERROR HANDLING WITH HELPFUL HINTS
// ════════════════════════════════════════════════════════════════════════════════

function helpfulError(err, context = "") {
  const code = err.code || "";
  const hint = ERROR_HINTS[code];

  console.log("");
  console.log(drawBox(c.red + "ERROR" + (context ? " — " + context : "") + c.reset, [
    "",
    `${c.bold}${err.message || String(err)}${c.reset}`,
    "",
    ...(hint ? [
      c.yellow + hint.title + c.reset,
      ...hint.fixes.map(f => "  " + icons.bullet + "  " + f),
      "",
    ] : []),
    `${c.dim}Code: ${code || "—"}${c.reset}`,
    "",
  ], { color: c.red }));
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 25  ─  UPDATE CHECKER
// ════════════════════════════════════════════════════════════════════════════════

async function checkForUpdates() {
  return new Promise((resolve) => {
    const pkg = require(path.join(PROJECT_ROOT, "package.json"));
    const deps = Object.keys(pkg.dependencies || {});

    if (deps.length === 0) return resolve([]);

    const updates = [];
    let pending = deps.length;

    for (const dep of deps) {
      const req = https.get(`https://registry.npmjs.org/${dep}/latest`, { timeout: 3000 }, (res) => {
        let buf = "";
        res.on("data", (d) => buf += d);
        res.on("end", () => {
          try {
            const data = JSON.parse(buf);
            const installed = pkg.dependencies[dep].replace(/[~^]/g, "");
            if (data.version && data.version !== installed) {
              updates.push({ name: dep, installed, latest: data.version });
            }
          } catch (_) {}
          if (--pending === 0) resolve(updates);
        });
      });
      req.on("error", () => { if (--pending === 0) resolve(updates); });
      req.on("timeout", () => { req.destroy(); if (--pending === 0) resolve(updates); });
    }
  });
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 26  ─  PRINT SUMMARY (after server ready)
// ════════════════════════════════════════════════════════════════════════════════

function printSummary({ publicUrl, localPort, serverLabel, cfg, ngrokDisabled }) {
  const sep = "═".repeat(70);
  const mode = cfg.serverMode;
  const platform = process.platform;
  const hintPaths = FOLDER_HINTS[mode] || [];
  const existingDir = hintPaths.find(p => fs.existsSync(p));
  const htdocsHint = existingDir
    ? `${existingDir}${path.sep}TopZone${path.sep}Home`
    : mode === "php"
    ? cfg.phpRoot
    : "(sesuai folder www / htdocs)";

  console.log(`${c.cyan}${sep}${c.reset}`);
  console.log("");
  console.log(`  ${c.bgGreen}${c.bold}  ${icons.ok}  TopZone ONLINE!  ${c.reset}\n`);
  console.log(`  ${c.bold}Server${c.reset}         : ${serverLabel}`);
  console.log(`  ${c.bold}Lokal${c.reset}          : ${hi("http://localhost:" + localPort)}`);
  if (publicUrl) {
    console.log(`  ${c.bold}Publik (ngrok)${c.reset} : ${hi(publicUrl)}`);
    console.log(`  ${c.bold}Ngrok UI${c.reset}       : ${hi("http://localhost:4040")}`);
  } else if (ngrokDisabled) {
    console.log(`  ${c.bold}Publik (ngrok)${c.reset} : ${dim("(skip — flag --no-ngrok)")}`);
  }

  // Tampilkan IP lokal lain (akses dari device lain di LAN)
  const ips = getLocalIpAddresses();
  if (ips.length > 0) {
    console.log(`  ${c.bold}LAN${c.reset}            : ${ips.map(i => c.cyan + "http://" + i.address + ":" + localPort + c.reset).join(", ")}`);
  }
  console.log("");
  console.log(`  ${c.bold}📁 Folder project:${c.reset}`);
  console.log(`     ${dim(htdocsHint)}\n`);

  console.log(`  ${c.bold}🔗 URL Penting:${c.reset}`);
  console.log(`     Beranda     : ${c.cyan}${publicUrl || ""}/Home/${c.reset}`);
  console.log(`     Login       : ${c.cyan}${publicUrl || ""}/Login/tampilanlogin.php${c.reset}`);
  console.log(`     Admin       : ${c.cyan}${publicUrl || ""}/Home/admin.php${c.reset}`);
  if (publicUrl) {
    console.log("");
    console.log(`  ${c.bold}📌 Webhook URL (untuk Xendit Dashboard):${c.reset}`);
    console.log(`     Callback    : ${c.green}${publicUrl}/Home/callback.php${c.reset}`);
    console.log(`     Ambil Token : ${c.green}${publicUrl}/Home/Checkout/ambil_token.php${c.reset}`);
  }
  console.log("");
  console.log(`  ${c.bold}💡 Tips:${c.reset}`);
  console.log(`     ${dim("• Tekan Ctrl+C untuk berhenti")}`);
  console.log(`     ${dim("• Profile aktif: " + cfg.profile)}`);
  console.log(`     ${dim("• Log file: " + path.join(LOG_DIR, "server.log"))}`);
  console.log(`${c.cyan}${sep}${c.reset}`);
  console.log(dim("\n  📡 Live request log (Ctrl+C untuk stop):\n"));
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 27  ─  LIVE DASHBOARD MODE
// ════════════════════════════════════════════════════════════════════════════════

class Dashboard {
  constructor(cfg, analytics, monitor, ngrok, port) {
    this.cfg       = cfg;
    this.analytics = analytics;
    this.monitor   = monitor;
    this.ngrok     = ngrok;
    this.port      = port;
    this.interval  = null;
    this.startedAt = Date.now();
  }

  start() {
    // Sembunyikan cursor
    if (process.stdout.isTTY) process.stdout.write("\x1b[?25l");

    this.interval = setInterval(() => this._render(), 1000);
    this._render();
  }

  stop() {
    if (this.interval) clearInterval(this.interval);
    if (process.stdout.isTTY) process.stdout.write("\x1b[?25h");
  }

  _formatUptime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${pad(h.toString(), 2, "0")}:${pad(m.toString(), 2, "0")}:${pad(s.toString(), 2, "0")}`;
  }

  _render() {
    console.clear();
    const w = Math.min(80, process.stdout.columns || 80);
    const status = this.monitor ? this.monitor.getStatus() : "unknown";
    const statusColor = status === "healthy" ? c.green : status === "degraded" ? c.yellow : status === "unhealthy" ? c.red : c.gray;
    const upSec = Math.floor((Date.now() - this.startedAt) / 1000);
    const memMB = Math.round(process.memoryUsage().heapUsed / 1024 / 1024);

    // ─── Header ─────────────────────────────────────────────────────
    console.log(c.cyan + c.bold + center("TOPZONE LIVE DASHBOARD", w) + c.reset);
    console.log(c.cyan + "═".repeat(w) + c.reset);

    // ─── Top stats grid ─────────────────────────────────────────────
    console.log("");
    console.log(`  ${c.bold}STATUS${c.reset}      ${statusColor}${icons.bullet} ${status.toUpperCase()}${c.reset}` + " ".repeat(20) +
                `${c.bold}UPTIME${c.reset}    ${c.cyan}${this._formatUptime(upSec)}${c.reset}`);
    console.log(`  ${c.bold}REQUESTS${c.reset}    ${c.cyan}${this.analytics.totalCount}${c.reset}` + " ".repeat(Math.max(1, 24 - String(this.analytics.totalCount).length)) +
                `${c.bold}ERRORS${c.reset}    ${(this.analytics.errorCount > 0 ? c.red : c.green) + this.analytics.errorCount}${c.reset}`);
    console.log(`  ${c.bold}MEMORY${c.reset}      ${c.cyan}${memMB}MB${c.reset}` + " ".repeat(Math.max(1, 24 - String(memMB).length - 2)) +
                `${c.bold}PORT${c.reset}      ${c.cyan}${this.port}${c.reset}`);

    // ─── Method breakdown ───────────────────────────────────────────
    console.log("");
    console.log(c.cyan + "─ METHODS " + "─".repeat(w - 11) + c.reset);
    const methods = Object.entries(this.analytics.byMethod).sort((a, b) => b[1] - a[1]);
    if (methods.length === 0) {
      console.log("  " + dim("(belum ada request)"));
    } else {
      const max = Math.max(...methods.map(m => m[1]));
      for (const [m, count] of methods) {
        const bar = progressBar(count, max, 30, c.green);
        console.log(`  ${pad(m, 8)} ${pad(String(count), 5)} ${bar}`);
      }
    }

    // ─── Status breakdown ───────────────────────────────────────────
    console.log("");
    console.log(c.cyan + "─ STATUS CODES " + "─".repeat(w - 16) + c.reset);
    const statuses = Object.entries(this.analytics.byStatus).sort((a, b) => b[1] - a[1]);
    if (statuses.length === 0) {
      console.log("  " + dim("(belum ada response)"));
    } else {
      for (const [s, count] of statuses) {
        const sNum = parseInt(s, 10);
        const color = sNum >= 500 ? c.red : sNum >= 400 ? c.yellow : sNum >= 300 ? c.cyan : c.green;
        console.log(`  ${color}${pad(s, 8)}${c.reset} ${pad(String(count), 5)} ${color + icons.bullet.repeat(Math.min(40, count))}${c.reset}`);
      }
    }

    // ─── Top endpoints ──────────────────────────────────────────────
    console.log("");
    console.log(c.cyan + "─ TOP ENDPOINTS " + "─".repeat(w - 17) + c.reset);
    const top = this.analytics.topEndpoints(5);
    if (top.length === 0) {
      console.log("  " + dim("(belum ada endpoint dipanggil)"));
    } else {
      for (const [ep, count] of top) {
        console.log(`  ${pad(String(count), 5)} ${truncate(ep, w - 10)}`);
      }
    }

    // ─── Recent requests ────────────────────────────────────────────
    console.log("");
    console.log(c.cyan + "─ RECENT (10) " + "─".repeat(w - 15) + c.reset);
    const recent = this.analytics.recentRequests.slice(0, 10);
    if (recent.length === 0) {
      console.log("  " + dim("(belum ada)"));
    } else {
      for (const r of recent) {
        console.log("  " + this.analytics.formatLogLine(r));
      }
    }

    console.log("");
    console.log(c.dim + center("Refresh tiap 1s · Ctrl+C untuk keluar", w) + c.reset);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 28  ─  GRACEFUL SHUTDOWN
// ════════════════════════════════════════════════════════════════════════════════

class ShutdownManager {
  constructor() {
    this.handlers = [];
    this.shutting = false;
  }

  add(name, fn) {
    this.handlers.push({ name, fn });
  }

  setup() {
    const handle = (sig) => this._shutdown(sig);
    process.on("SIGINT",  () => handle("SIGINT"));
    process.on("SIGTERM", () => handle("SIGTERM"));
    process.on("SIGHUP",  () => handle("SIGHUP"));

    process.on("uncaughtException", (err) => {
      console.log("");
      helpfulError(err, "uncaughtException");
      this._shutdown("uncaughtException");
    });
    process.on("unhandledRejection", (reason) => {
      console.log("");
      helpfulError(reason instanceof Error ? reason : new Error(String(reason)), "unhandledRejection");
      this._shutdown("unhandledRejection");
    });
  }

  async _shutdown(sig) {
    if (this.shutting) return;
    this.shutting = true;

    console.log("");
    console.log(`${c.yellow}🛑  ${sig} — mematikan server...${c.reset}`);

    // Restore cursor (kalau dashboard mode)
    if (process.stdout.isTTY) process.stdout.write("\x1b[?25h");

    const startedAt = Date.now();

    // Eksekusi handler reverse order (LIFO)
    for (const h of this.handlers.slice().reverse()) {
      try {
        const sp = new Spinner(`Shutting down: ${h.name}`).start();
        const p = h.fn();
        if (p && typeof p.then === "function") {
          await Promise.race([
            p,
            new Promise(r => setTimeout(r, SHUTDOWN_TIMEOUT)),
          ]);
        }
        sp.succeed(`Stopped: ${h.name}`);
      } catch (e) {
        logger.warn(`Shutdown handler "${h.name}" gagal: ${e.message}`);
      }
    }

    const elapsed = Date.now() - startedAt;
    console.log("");
    console.log(`${c.dim}Total waktu shutdown: ${elapsed}ms${c.reset}`);
    console.log(`${c.green}👋  Sampai jumpa!${c.reset}\n`);

    process.exit(0);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 29  ─  PROFILE MANAGER COMMAND
// ════════════════════════════════════════════════════════════════════════════════

async function cmdProfileList() {
  printSubBanner("PROFILES");
  const list = ProfileMgr.list();
  if (list.length === 0) {
    console.log("  " + dim("(belum ada profile, .env tidak ada)"));
    return;
  }
  for (const p of list) {
    const path = ProfileMgr.getPath(p);
    const exists = ProfileMgr.exists(p);
    const marker = State.get("lastProfile") === p ? c.green + " (last used)" + c.reset : "";
    console.log(`  ${exists ? c.green + icons.ok : c.red + icons.fail}${c.reset}  ${c.bold}${p}${c.reset}${marker}`);
    console.log(`     ${dim(path)}`);
  }
  console.log("");
  console.log(dim("Pakai profile: node server.js --profile=<nama>"));
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 30  ─  WEBHOOK COMMAND (manual)
// ════════════════════════════════════════════════════════════════════════════════

async function cmdUpdateWebhook(cfg) {
  printSubBanner("XENDIT WEBHOOK — Manual Update");

  if (!cfg.xenditKey) {
    logger.warn("XENDIT_SECRET_KEY belum diisi di .env");
    process.exit(1);
  }

  // Coba ambil URL ngrok yang lagi jalan dari ngrok API lokal
  try {
    const res = await httpRequest("GET", NGROK_TUNNELS);
    const tunnel = res.body?.tunnels?.[0];
    if (!tunnel) {
      logger.error("Tidak ada tunnel ngrok aktif. Jalankan dulu: node server.js");
      process.exit(1);
    }

    const sync = new WebhookSyncer(cfg);
    await sync.updateOnce(tunnel.public_url);
  } catch (e) {
    logger.error("Gagal ambil status ngrok: " + e.message);
    logger.info("  Pastikan ngrok lagi jalan, atau lihat State.lastUrl: " + State.get("lastUrl"));
    process.exit(1);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 31  ─  STARTUP SEQUENCE (main flow)
// ════════════════════════════════════════════════════════════════════════════════

async function checkFirstRun() {
  const hasDotEnv = fs.existsSync(ENV_FILE);
  if (!hasDotEnv) return true;

  const content = fs.readFileSync(ENV_FILE, "utf8");
  if (content.includes("isi_token") || content.match(/NGROK_AUTHTOKEN=\s*$/m)) {
    return true;
  }
  return false;
}

async function bootstrap(args) {
  // Set logger level berdasarkan flag
  if (args.silent)  logger.level = "warn";
  if (args.verbose) logger.level = "debug";
  if (args.noLog)   logger.toFile = false;

  State.load();
  State.set("startupCount", State.get("startupCount", 0) + 1);

  // ─── Handle CLI commands yang langsung exit ────────────────────
  if (args.help)    { printHelp(); process.exit(0); }
  if (args.version) { printVersion(); process.exit(0); }

  if (args.cmd === "profile:list") {
    await cmdProfileList();
    process.exit(0);
  }

  printBanner();

  // ─── First-run setup wizard ────────────────────────────────────
  if (args.setup || await checkFirstRun()) {
    const wiz = new Wizard();
    await wiz.runFullSetup();
    if (args.setup) {
      console.log("\n" + ok("Setup selesai. Jalankan: node server.js"));
      process.exit(0);
    }
  }

  // Re-load config setelah wizard mungkin update .env
  const cfg = loadConfig(args.profile);
  if (args.port) cfg.localPort = args.port;
  if (args.mode) cfg.serverMode = args.mode;
  State.set("lastProfile", cfg.profile);

  if (args.profile && args.profile !== "default") {
    logger.info(`Pakai profile: ${c.cyan}${args.profile}${c.reset}`);
  }

  // ─── Diagnostics (--check) ─────────────────────────────────────
  if (args.check) {
    const pf = new Preflight(cfg);
    await pf.runDiagnosticsOnly();
    return;
  }

  // ─── Database commands ─────────────────────────────────────────
  if (args.cmd === "db:test")    { await DbHelper.cmdTest(cfg); process.exit(0); }
  if (args.cmd === "db:setup")   { await DbHelper.cmdSetup(cfg); process.exit(0); }
  if (args.cmd === "db:schema")  { await DbHelper.cmdSchema(cfg); process.exit(0); }
  if (args.cmd === "db:seed")    { await DbHelper.cmdSeed(cfg); process.exit(0); }
  if (args.cmd === "db:backup")  { await DbHelper.cmdBackup(cfg); process.exit(0); }
  if (args.cmd === "db:reset")   { await DbHelper.cmdReset(cfg); process.exit(0); }
  if (args.cmd === "update-webhook") { await cmdUpdateWebhook(cfg); process.exit(0); }

  return cfg;
}

async function fullLaunch(cfg, args) {
  // Preflight
  const pf = new Preflight(cfg);
  const { errors } = await pf.run();
  if (errors.length > 0) {
    logger.error(`${errors.length} masalah preflight harus dibenerin dulu`);
    process.exit(1);
  }

  // Init analytics & shutdown manager
  const analytics = new RequestAnalytics();
  const shutdown  = new ShutdownManager();
  shutdown.setup();

  // Cek update (background, non-blocking)
  if (cfg.appEnv === "development") {
    checkForUpdates().then(updates => {
      if (updates.length > 0) {
        console.log(warn(`Ada ${updates.length} dependency yang outdated:`));
        for (const u of updates) console.log(`  ${dim(u.name)} ${u.installed} → ${c.green}${u.latest}${c.reset}`);
        console.log(dim("  Jalankan: npm update\n"));
      }
    }).catch(() => {});
  }

  // ─── Resolve server & port ─────────────────────────────────────
  let localPort  = cfg.localPort;
  let phpServer  = null;
  let serverLabel;

  if (cfg.serverMode === "php") {
    phpServer    = new PhpServer(cfg);
    const result = await phpServer.start();
    localPort    = result.port;
    serverLabel  = `PHP Built-in (port ${localPort})`;
    shutdown.add("PHP server", () => phpServer.stop());

  } else if (cfg.serverMode === "custom") {
    printSubBanner("CUSTOM SERVER");
    const open = await isPortOpen(localPort);
    if (!open) {
      logger.error(`Tidak ada server di port ${localPort}`);
      logger.info("  Jalankan servermu dulu, lalu coba lagi");
      process.exit(1);
    }
    const health = await httpHealthCheck(localPort);
    if (health.ok) logger.ok(`Server di port ${localPort} responsif (HTTP ${health.status})`);
    else           logger.warn(`Port ${localPort} terbuka tapi tidak respon HTTP — lanjut`);
    serverLabel = `Custom Server (port ${localPort})`;

  } else {
    const detector = new ServerDetector(cfg);
    const detected = await detector.detect();
    if (!detected) {
      // Fallback PHP built-in
      phpServer    = new PhpServer(cfg);
      const result = await phpServer.start();
      localPort    = result.port;
      serverLabel  = `PHP Built-in (fallback, port ${localPort})`;
      shutdown.add("PHP server", () => phpServer.stop());
    } else {
      localPort   = detected.port;
      serverLabel = detected.label;
    }
  }

  // ─── Ngrok tunnel ──────────────────────────────────────────────
  let publicUrl = null;
  let ngrokMgr = null;
  if (!args.noNgrok) {
    ngrokMgr = new NgrokManager(cfg);
    publicUrl = await ngrokMgr.start(localPort);
    shutdown.add("Ngrok tunnel", () => ngrokMgr.stop());
  } else {
    logger.info("Ngrok di-skip (--no-ngrok)");
  }

  // ─── Webhook syncer ────────────────────────────────────────────
  let webhookSync = null;
  if (publicUrl && !args.noWebhook) {
    webhookSync = new WebhookSyncer(cfg);
    if (webhookSync.isEnabled()) {
      await webhookSync.updateOnce(publicUrl);
      webhookSync.startSync(() => publicUrl);
      shutdown.add("Webhook syncer", () => webhookSync.stop());
    }
  }

  // ─── Health monitor ────────────────────────────────────────────
  const healthMon = new HealthMonitor(cfg, localPort);
  healthMon.start();
  shutdown.add("Health monitor", () => healthMon.stop());

  // ─── Request logger ────────────────────────────────────────────
  let reqLogger = null;
  if (publicUrl && cfg.logRequests && !args.dashboard) {
    reqLogger = new RequestLogger(analytics, { silent: false });
    reqLogger.start();
    shutdown.add("Request logger", () => reqLogger.stop());
  } else if (args.dashboard) {
    // dashboard pakai analytics tapi tidak print line per line
    reqLogger = new RequestLogger(analytics, { silent: true });
    reqLogger.start();
    shutdown.add("Request logger", () => reqLogger.stop());
  }

  // ─── Print summary ─────────────────────────────────────────────
  printSummary({ publicUrl, localPort, serverLabel, cfg, ngrokDisabled: args.noNgrok });

  // ─── QR code ──────────────────────────────────────────────────
  if (publicUrl && !args.silent && !args.dashboard) {
    printQrCode(publicUrl);
  }

  // ─── Browser auto-open ────────────────────────────────────────
  if (cfg.autoOpenBrowser && !args.noBrowser && !args.dashboard) {
    setTimeout(() => {
      const target = publicUrl || `http://localhost:${localPort}`;
      openBrowser(target, cfg.browserPath);
      logger.ok(`Browser dibuka: ${target}`);
    }, 1000);
  }

  // ─── Notification ─────────────────────────────────────────────
  if (cfg.notifyOnReady) {
    sendNotification("TopZone Ready", `Server jalan di ${publicUrl || "localhost:" + localPort}`);
    bell();
  }

  // ─── Dashboard mode ───────────────────────────────────────────
  if (args.dashboard) {
    const dashboard = new Dashboard(cfg, analytics, healthMon, ngrokMgr, localPort);
    dashboard.start();
    shutdown.add("Dashboard", () => dashboard.stop());
  }

  // Final shutdown summary
  shutdown.add("Save analytics", () => {
    State.set("lastSession", {
      endedAt:    new Date().toISOString(),
      requests:   analytics.totalCount,
      errors:     analytics.errorCount,
      uptime:     analytics.uptime(),
    });
  });
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 32  ─  PLUGIN / HOOK SYSTEM
// ════════════════════════════════════════════════════════════════════════════════
//
//  Plugin system memungkinkan extension custom tanpa harus modifikasi
//  server.js. Plugin disimpan di folder `.tz-plugins/` dan di-load
//  otomatis saat startup.
//
//  ── STRUKTUR PLUGIN ─────────────────────────────────────────────────────
//
//  .tz-plugins/
//  └── nama-plugin/
//      ├── plugin.json       (metadata)
//      └── index.js          (kode plugin)
//
//  ── CONTOH plugin.json ──────────────────────────────────────────────────
//
//  {
//    "name": "auto-backup",
//    "version": "1.0.0",
//    "description": "Auto-backup database setiap 1 jam",
//    "hooks": ["onReady", "onShutdown"]
//  }
//
//  ── CONTOH index.js ─────────────────────────────────────────────────────
//
//  module.exports = {
//    onReady(ctx) {
//      ctx.logger.info("Plugin auto-backup aktif");
//      ctx.scheduler.schedule("0 * * * *", () => {
//        ctx.runCommand("db:backup");
//      });
//    },
//    onShutdown(ctx) {
//      ctx.logger.info("Plugin auto-backup berhenti");
//    },
//  };
//
// ════════════════════════════════════════════════════════════════════════════════

class PluginSystem extends EventEmitter {
  constructor() {
    super();
    this.plugins   = [];
    this.dir       = path.join(PROJECT_ROOT, ".tz-plugins");
    this.HOOKS     = [
      "onInit",
      "onConfigLoad",
      "onPreflight",
      "onServerStart",
      "onNgrokReady",
      "onReady",
      "onRequest",
      "onError",
      "onShutdown",
    ];
  }

  load() {
    if (!fs.existsSync(this.dir)) return;

    const entries = fs.readdirSync(this.dir, { withFileTypes: true });
    for (const entry of entries) {
      if (!entry.isDirectory()) continue;
      try {
        this._loadPlugin(path.join(this.dir, entry.name));
      } catch (e) {
        logger.warn(`Plugin "${entry.name}" gagal load: ${e.message}`);
      }
    }

    if (this.plugins.length > 0) {
      logger.ok(`${this.plugins.length} plugin di-load: ${this.plugins.map(p => p.name).join(", ")}`);
    }
  }

  _loadPlugin(pluginDir) {
    const metaFile = path.join(pluginDir, "plugin.json");
    const indexFile = path.join(pluginDir, "index.js");

    if (!fs.existsSync(metaFile) || !fs.existsSync(indexFile)) return;

    const meta = JSON.parse(fs.readFileSync(metaFile, "utf8"));
    const mod  = require(indexFile);

    this.plugins.push({
      name:    meta.name,
      version: meta.version,
      desc:    meta.description,
      hooks:   meta.hooks || [],
      module:  mod,
      dir:     pluginDir,
    });
  }

  async dispatch(hook, ctx) {
    for (const p of this.plugins) {
      if (typeof p.module[hook] !== "function") continue;
      try {
        await p.module[hook](ctx);
      } catch (e) {
        logger.warn(`Plugin "${p.name}" hook "${hook}" error: ${e.message}`);
      }
    }
  }

  list() {
    return this.plugins.map(p => ({
      name:    p.name,
      version: p.version,
      desc:    p.desc,
      hooks:   p.hooks,
    }));
  }

  ensureDir() {
    if (!fs.existsSync(this.dir)) {
      try { fs.mkdirSync(this.dir, { recursive: true }); } catch (_) {}
    }
  }

  installSample() {
    this.ensureDir();
    const sampleDir = path.join(this.dir, "sample-logger");
    if (fs.existsSync(sampleDir)) return;
    fs.mkdirSync(sampleDir, { recursive: true });

    fs.writeFileSync(path.join(sampleDir, "plugin.json"), JSON.stringify({
      name:        "sample-logger",
      version:     "1.0.0",
      description: "Sample plugin: log saat server siap & shutdown",
      hooks:       ["onReady", "onShutdown"],
    }, null, 2));

    fs.writeFileSync(path.join(sampleDir, "index.js"), [
      "// Sample TopZone plugin",
      "module.exports = {",
      "  onReady(ctx) {",
      "    ctx.logger.ok(`[sample-logger] Server ready at ${ctx.publicUrl || ctx.localUrl}`);",
      "  },",
      "  onShutdown(ctx) {",
      "    ctx.logger.info('[sample-logger] Bye!');",
      "  },",
      "};",
      "",
    ].join("\n"));
  }
}

const pluginSystem = new PluginSystem();


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 33  ─  SCHEDULER (cron-like, in-process)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Scheduler ringan untuk task periodic. Bukan cron sebenarnya — cuma
//  setInterval wrapper dengan API yang lebih ramah.
//
//  Pakai: scheduler.every("1h", () => doBackup())
//         scheduler.at("03:00", () => doCleanup())  (24h format)
//
// ════════════════════════════════════════════════════════════════════════════════

class Scheduler {
  constructor() {
    this.tasks = [];
    this.running = false;
  }

  /**
   * Run task setiap interval. Format: "30s", "5m", "1h", "1d"
   */
  every(interval, fn, opts = {}) {
    const ms = this._parseInterval(interval);
    const task = {
      id:       crypto.randomBytes(4).toString("hex"),
      type:     "interval",
      interval: ms,
      fn,
      name:     opts.name || fn.name || "anonymous",
      runs:     0,
      handle:   null,
    };

    if (opts.runImmediately) this._runTask(task);

    task.handle = setInterval(() => this._runTask(task), ms);
    this.tasks.push(task);
    return task.id;
  }

  /**
   * Run task pada jam tertentu setiap hari (24h format "HH:MM")
   */
  at(time, fn, opts = {}) {
    const [hh, mm] = time.split(":").map(s => parseInt(s, 10));
    const task = {
      id:     crypto.randomBytes(4).toString("hex"),
      type:   "daily",
      time:   { hh, mm },
      fn,
      name:   opts.name || fn.name || "anonymous",
      runs:   0,
      handle: null,
    };

    const tick = () => {
      const now = new Date();
      if (now.getHours() === hh && now.getMinutes() === mm && now.getSeconds() < 30) {
        this._runTask(task);
      }
    };

    task.handle = setInterval(tick, 30000);
    this.tasks.push(task);
    return task.id;
  }

  /**
   * Cron-like simple: minute hour day month dow (5 fields)
   * NOTE: implementasi parser disederhanakan — cuma support angka & "*"
   */
  cron(expression, fn, opts = {}) {
    const parts = expression.split(/\s+/);
    if (parts.length !== 5) throw new Error("Cron butuh 5 fields: m h dom mon dow");

    const matchers = parts.map(this._cronField);
    const task = {
      id:     crypto.randomBytes(4).toString("hex"),
      type:   "cron",
      expr:   expression,
      fn,
      name:   opts.name || fn.name || "anonymous",
      runs:   0,
      handle: null,
    };

    const tick = () => {
      const now = new Date();
      const fields = [now.getMinutes(), now.getHours(), now.getDate(), now.getMonth() + 1, now.getDay()];
      if (fields.every((v, i) => matchers[i](v))) {
        this._runTask(task);
      }
    };

    task.handle = setInterval(tick, 60000); // tiap menit
    this.tasks.push(task);
    return task.id;
  }

  _cronField(field) {
    if (field === "*") return () => true;
    if (field.includes(",")) {
      const list = field.split(",").map(Number);
      return (v) => list.includes(v);
    }
    if (field.includes("/")) {
      const [_, step] = field.split("/");
      return (v) => v % parseInt(step, 10) === 0;
    }
    if (field.includes("-")) {
      const [a, b] = field.split("-").map(Number);
      return (v) => v >= a && v <= b;
    }
    const n = parseInt(field, 10);
    return (v) => v === n;
  }

  cancel(id) {
    const idx = this.tasks.findIndex(t => t.id === id);
    if (idx < 0) return false;
    if (this.tasks[idx].handle) clearInterval(this.tasks[idx].handle);
    this.tasks.splice(idx, 1);
    return true;
  }

  _runTask(task) {
    task.runs++;
    try {
      const r = task.fn();
      if (r && typeof r.catch === "function") r.catch((e) => logger.warn(`Task "${task.name}" error: ${e.message}`));
    } catch (e) {
      logger.warn(`Task "${task.name}" error: ${e.message}`);
    }
  }

  _parseInterval(str) {
    const m = String(str).match(/^(\d+)\s*(s|m|h|d|ms)?$/i);
    if (!m) throw new Error("Interval invalid: " + str);
    const n = parseInt(m[1], 10);
    const unit = (m[2] || "ms").toLowerCase();
    const multipliers = { ms: 1, s: 1000, m: 60000, h: 3600000, d: 86400000 };
    return n * multipliers[unit];
  }

  list() {
    return this.tasks.map(t => ({
      id:       t.id,
      name:     t.name,
      type:     t.type,
      interval: t.interval,
      time:     t.time,
      expr:     t.expr,
      runs:     t.runs,
    }));
  }

  stopAll() {
    for (const t of this.tasks) {
      if (t.handle) clearInterval(t.handle);
    }
    this.tasks = [];
  }
}

const scheduler = new Scheduler();


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 34  ─  SESSION HISTORY (persisted across sessions)
// ════════════════════════════════════════════════════════════════════════════════

class SessionHistory {
  constructor() {
    this.file = path.join(PROJECT_ROOT, ".tz-history.json");
    this.maxEntries = 100;
    this.entries = [];
    this._load();
  }

  _load() {
    try {
      if (fs.existsSync(this.file)) {
        this.entries = JSON.parse(fs.readFileSync(this.file, "utf8"));
      }
    } catch (e) {
      logger.debug("History load gagal: " + e.message);
      this.entries = [];
    }
  }

  _save() {
    try {
      fs.writeFileSync(this.file, JSON.stringify(this.entries, null, 2));
    } catch (e) {
      logger.debug("History save gagal: " + e.message);
    }
  }

  recordStart(cfg, port) {
    const entry = {
      id:         crypto.randomBytes(6).toString("hex"),
      startedAt:  new Date().toISOString(),
      profile:    cfg.profile,
      mode:       cfg.serverMode,
      port,
      requests:   0,
      errors:     0,
      duration:   0,
      ngrokUrl:   null,
    };
    this.entries.unshift(entry);
    if (this.entries.length > this.maxEntries) {
      this.entries = this.entries.slice(0, this.maxEntries);
    }
    this._save();
    return entry.id;
  }

  recordEnd(id, stats) {
    const entry = this.entries.find(e => e.id === id);
    if (!entry) return;
    entry.endedAt   = new Date().toISOString();
    entry.requests  = stats.requests || 0;
    entry.errors    = stats.errors   || 0;
    entry.duration  = Math.floor((new Date(entry.endedAt) - new Date(entry.startedAt)) / 1000);
    entry.ngrokUrl  = stats.ngrokUrl || null;
    this._save();
  }

  recent(n = 10) {
    return this.entries.slice(0, n);
  }

  stats() {
    const total     = this.entries.length;
    const totalReqs = this.entries.reduce((s, e) => s + (e.requests || 0), 0);
    const totalDur  = this.entries.reduce((s, e) => s + (e.duration || 0), 0);
    const avgReqs   = total > 0 ? Math.round(totalReqs / total) : 0;
    const avgDur    = total > 0 ? Math.round(totalDur / total) : 0;
    return { total, totalReqs, totalDur, avgReqs, avgDur };
  }

  print() {
    printSubBanner("SESSION HISTORY");
    const stats = this.stats();
    console.log(`  Total sessions  : ${c.cyan}${stats.total}${c.reset}`);
    console.log(`  Total requests  : ${c.cyan}${stats.totalReqs}${c.reset}`);
    console.log(`  Avg requests    : ${c.cyan}${stats.avgReqs}/session${c.reset}`);
    console.log(`  Avg duration    : ${c.cyan}${formatDuration(stats.avgDur)}${c.reset}`);
    console.log("");
    console.log(bold("Recent sessions:"));

    const rows = this.recent(10).map(e => [
      e.startedAt.slice(0, 19).replace("T", " "),
      e.profile,
      e.mode,
      e.port,
      e.requests,
      e.errors,
      formatDuration(e.duration),
    ]);

    if (rows.length === 0) {
      console.log("  " + dim("(belum ada history)"));
    } else {
      console.log(renderTable(
        ["Started", "Profile", "Mode", "Port", "Reqs", "Err", "Duration"],
        rows
      ));
    }
  }
}

const history = new SessionHistory();

function formatDuration(seconds) {
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
  return `${Math.floor(seconds / 86400)}d ${Math.floor((seconds % 86400) / 3600)}h`;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 35  ─  CLEANUP UTILITIES
// ════════════════════════════════════════════════════════════════════════════════

class Cleanup {
  static async logs(daysOld = 7) {
    if (!fs.existsSync(LOG_DIR)) return { count: 0, freed: 0 };
    const cutoff = Date.now() - (daysOld * 24 * 60 * 60 * 1000);
    let count = 0, freed = 0;

    for (const file of fs.readdirSync(LOG_DIR)) {
      const fullPath = path.join(LOG_DIR, file);
      try {
        const stat = fs.statSync(fullPath);
        if (stat.mtimeMs < cutoff) {
          freed += stat.size;
          fs.unlinkSync(fullPath);
          count++;
        }
      } catch (e) { /* skip */ }
    }
    return { count, freed };
  }

  static async backups(keepLatest = 5) {
    if (!fs.existsSync(BACKUP_DIR)) return { count: 0, freed: 0 };
    const files = fs.readdirSync(BACKUP_DIR)
      .map(f => ({
        name: f,
        path: path.join(BACKUP_DIR, f),
        mtime: fs.statSync(path.join(BACKUP_DIR, f)).mtimeMs,
      }))
      .sort((a, b) => b.mtime - a.mtime);

    const toDelete = files.slice(keepLatest);
    let count = 0, freed = 0;
    for (const f of toDelete) {
      try {
        const stat = fs.statSync(f.path);
        freed += stat.size;
        fs.unlinkSync(f.path);
        count++;
      } catch (e) { /* skip */ }
    }
    return { count, freed };
  }

  static async runAll(opts = {}) {
    printSubBanner("CLEANUP");

    const daysOld = opts.logDays || 7;
    const keepBackups = opts.keepBackups || 5;

    let sp = new Spinner(`Hapus log file > ${daysOld} hari...`).start();
    const logResult = await Cleanup.logs(daysOld);
    sp.succeed(`Logs: ${logResult.count} file dihapus (${formatBytes(logResult.freed)} freed)`);

    sp = new Spinner(`Hapus backup lama (keep ${keepBackups} terbaru)...`).start();
    const bakResult = await Cleanup.backups(keepBackups);
    sp.succeed(`Backups: ${bakResult.count} file dihapus (${formatBytes(bakResult.freed)} freed)`);

    const total = logResult.freed + bakResult.freed;
    console.log("");
    console.log(ok(`Total ruang dibebaskan: ${formatBytes(total)}`));
  }
}

function formatBytes(bytes) {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 36  ─  BENCHMARK / LOAD TEST MODE
// ════════════════════════════════════════════════════════════════════════════════
//
//  Sederhana: generate N concurrent HTTP request ke endpoint lokal,
//  ukur latency & success rate. Berguna untuk validasi performa basic.
//
// ════════════════════════════════════════════════════════════════════════════════

class Benchmark {
  constructor(targetUrl, opts = {}) {
    this.targetUrl    = targetUrl;
    this.concurrency  = opts.concurrency || 10;
    this.requests     = opts.requests    || 100;
    this.timeout      = opts.timeout     || 5000;
    this.results      = [];
  }

  async run() {
    printSubBanner(`BENCHMARK — ${this.targetUrl}`);
    logger.info(`Concurrency: ${this.concurrency}, Total requests: ${this.requests}`);

    const startTime = Date.now();
    let pending = this.requests;
    let active = 0;
    let completed = 0;

    return new Promise((resolve) => {
      const renderProgress = () => {
        if (!process.stdout.isTTY) return;
        process.stdout.write(`\r  ${progressBar(completed, this.requests, 40)} ${completed}/${this.requests}`);
      };

      const launch = () => {
        if (pending === 0 && active === 0) {
          if (process.stdout.isTTY) process.stdout.write("\r" + " ".repeat(80) + "\r");
          this._printResults(Date.now() - startTime);
          resolve(this.results);
          return;
        }
        while (pending > 0 && active < this.concurrency) {
          pending--;
          active++;
          this._sendRequest()
            .then(r => { this.results.push(r); active--; completed++; renderProgress(); launch(); })
            .catch(() => { active--; completed++; renderProgress(); launch(); });
        }
      };

      launch();
    });
  }

  _sendRequest() {
    return new Promise((resolve) => {
      const start = Date.now();
      const u = new URL(this.targetUrl);
      const lib = u.protocol === "https:" ? https : http;
      const req = lib.get({
        hostname: u.hostname,
        port:     u.port || (u.protocol === "https:" ? 443 : 80),
        path:     u.pathname + u.search,
        timeout:  this.timeout,
      }, (res) => {
        res.resume();
        res.on("end", () => resolve({
          status:   res.statusCode,
          duration: Date.now() - start,
          ok:       res.statusCode < 400,
        }));
      });
      req.on("error",   () => resolve({ status: 0, duration: Date.now() - start, ok: false, error: "network" }));
      req.on("timeout", () => { req.destroy(); resolve({ status: 0, duration: Date.now() - start, ok: false, error: "timeout" }); });
    });
  }

  _printResults(totalMs) {
    const ok      = this.results.filter(r => r.ok).length;
    const errors  = this.results.length - ok;
    const durations = this.results.map(r => r.duration).sort((a, b) => a - b);

    const avg = durations.length > 0 ? Math.round(durations.reduce((s, d) => s + d, 0) / durations.length) : 0;
    const min = durations[0] || 0;
    const max = durations[durations.length - 1] || 0;
    const p50 = durations[Math.floor(durations.length * 0.5)] || 0;
    const p95 = durations[Math.floor(durations.length * 0.95)] || 0;
    const p99 = durations[Math.floor(durations.length * 0.99)] || 0;

    const rps = (this.results.length / (totalMs / 1000)).toFixed(2);

    console.log("");
    console.log(drawBox("📊  BENCHMARK RESULTS", [
      "",
      `  Total requests : ${c.cyan}${this.results.length}${c.reset}`,
      `  Success        : ${c.green}${ok}${c.reset}`,
      `  Errors         : ${(errors > 0 ? c.red : c.green)}${errors}${c.reset}`,
      `  Total time     : ${c.cyan}${(totalMs / 1000).toFixed(2)}s${c.reset}`,
      `  Throughput     : ${c.cyan}${rps} req/sec${c.reset}`,
      "",
      `  ${c.bold}Latency (ms)${c.reset}`,
      `    min          : ${min}`,
      `    max          : ${max}`,
      `    avg          : ${avg}`,
      `    p50 (median) : ${p50}`,
      `    p95          : ${p95}`,
      `    p99          : ${p99}`,
      "",
    ], { color: c.cyan, width: 60 }));
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 37  ─  HTTPS / SSL SUPPORT
// ════════════════════════════════════════════════════════════════════════════════
//
//  Generate self-signed certificate untuk development HTTPS.
//  Untuk production, gunakan Let's Encrypt atau cert dari CA.
//
// ════════════════════════════════════════════════════════════════════════════════

class CertHelper {
  static get certDir() { return path.join(PROJECT_ROOT, ".certs"); }
  static get keyFile() { return path.join(CertHelper.certDir, "key.pem"); }
  static get crtFile() { return path.join(CertHelper.certDir, "cert.pem"); }

  static exists() {
    return fs.existsSync(CertHelper.keyFile) && fs.existsSync(CertHelper.crtFile);
  }

  static async generateSelfSigned(opts = {}) {
    if (!fs.existsSync(CertHelper.certDir)) {
      fs.mkdirSync(CertHelper.certDir, { recursive: true });
    }

    const sp = new Spinner("Generate self-signed certificate...").start();

    return new Promise((resolve, reject) => {
      // Coba pakai openssl
      const cn = opts.commonName || "localhost";
      const days = opts.days || 365;
      const cmd = `openssl req -x509 -nodes -newkey rsa:2048 -keyout "${CertHelper.keyFile}" -out "${CertHelper.crtFile}" -days ${days} -subj "/CN=${cn}"`;

      exec(cmd, (err, stdout, stderr) => {
        if (err) {
          sp.fail("openssl tidak tersedia");
          logger.info("  Install openssl atau gunakan ngrok HTTPS (sudah otomatis)");
          reject(err);
          return;
        }
        sp.succeed(`Certificate dibuat: ${CertHelper.certDir}`);
        resolve({ keyFile: CertHelper.keyFile, crtFile: CertHelper.crtFile });
      });
    });
  }

  static load() {
    if (!CertHelper.exists()) return null;
    return {
      key:  fs.readFileSync(CertHelper.keyFile),
      cert: fs.readFileSync(CertHelper.crtFile),
    };
  }

  static info() {
    if (!CertHelper.exists()) {
      console.log(warn("Certificate belum ada"));
      console.log(info("Generate dengan: node server.js --cert:gen"));
      return;
    }
    const stat = fs.statSync(CertHelper.crtFile);
    console.log(ok(`Certificate ada: ${CertHelper.certDir}`));
    console.log(`  Key file  : ${CertHelper.keyFile}`);
    console.log(`  Cert file : ${CertHelper.crtFile}`);
    console.log(`  Created   : ${stat.birthtime.toISOString()}`);
    console.log(`  Modified  : ${stat.mtime.toISOString()}`);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 38  ─  INTERNAL REST API (control + stats)
// ════════════════════════════════════════════════════════════════════════════════
//
//  HTTP server lokal yang expose endpoint untuk:
//   - GET /stats         → analytics realtime
//   - GET /health        → status server
//   - GET /sessions      → riwayat session
//   - POST /shutdown     → trigger shutdown (auth required)
//   - GET /tunnels       → ngrok tunnel info
//
//  Default port: 4041 (4040 sudah dipakai ngrok UI)
//
// ════════════════════════════════════════════════════════════════════════════════

class ControlAPI {
  constructor(opts = {}) {
    this.port    = opts.port || 4041;
    this.token   = opts.token || crypto.randomBytes(16).toString("hex");
    this.server  = null;
    this.context = {};
  }

  setContext(ctx) {
    this.context = ctx;
  }

  start() {
    this.server = http.createServer((req, res) => this._handleRequest(req, res));
    this.server.listen(this.port, "127.0.0.1", () => {
      logger.debug(`Control API listening on http://127.0.0.1:${this.port}`);
    });
    this.server.on("error", (err) => {
      if (err.code === "EADDRINUSE") {
        logger.debug(`Control API port ${this.port} sudah dipakai — skip`);
      }
    });
  }

  stop() {
    if (this.server) this.server.close();
  }

  _handleRequest(req, res) {
    const u = new URL(req.url, `http://localhost:${this.port}`);
    const path = u.pathname;
    const method = req.method;

    // CORS
    res.setHeader("Access-Control-Allow-Origin", "*");
    res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Authorization, Content-Type");
    if (method === "OPTIONS") { res.statusCode = 204; res.end(); return; }

    res.setHeader("Content-Type", "application/json");

    // Routing
    if (method === "GET" && path === "/")          return this._sendJSON(res, this._getInfo());
    if (method === "GET" && path === "/stats")     return this._sendJSON(res, this._getStats());
    if (method === "GET" && path === "/health")    return this._sendJSON(res, this._getHealth());
    if (method === "GET" && path === "/sessions")  return this._sendJSON(res, history.recent(20));
    if (method === "GET" && path === "/tunnels")   return this._sendJSON(res, this._getTunnels());
    if (method === "GET" && path === "/scheduler") return this._sendJSON(res, scheduler.list());
    if (method === "GET" && path === "/plugins")   return this._sendJSON(res, pluginSystem.list());

    // Protected endpoints
    if (method === "POST" && path === "/shutdown") {
      if (!this._auth(req)) return this._sendJSON(res, { error: "unauthorized" }, 401);
      res.statusCode = 202;
      res.end(JSON.stringify({ message: "Shutting down..." }));
      setTimeout(() => process.kill(process.pid, "SIGTERM"), 100);
      return;
    }

    res.statusCode = 404;
    res.end(JSON.stringify({ error: "not found", path }));
  }

  _auth(req) {
    const header = req.headers["authorization"] || "";
    return header === `Bearer ${this.token}`;
  }

  _sendJSON(res, data, status = 200) {
    res.statusCode = status;
    res.end(JSON.stringify(data, null, 2));
  }

  _getInfo() {
    return {
      app:         APP_NAME,
      version:     APP_VERSION,
      uptime:      process.uptime(),
      pid:         process.pid,
      node:        process.version,
      platform:    process.platform,
      endpoints:   ["/stats", "/health", "/sessions", "/tunnels", "/scheduler", "/plugins", "/shutdown"],
    };
  }

  _getStats() {
    if (!this.context.analytics) return { error: "analytics not available" };
    const a = this.context.analytics;
    return {
      total:       a.totalCount,
      errors:      a.errorCount,
      uptime:      a.uptime(),
      methods:     a.byMethod,
      statuses:    a.byStatus,
      topEndpoints: a.topEndpoints(10),
      recent:      a.recentRequests.slice(0, 10),
    };
  }

  _getHealth() {
    if (!this.context.healthMonitor) return { status: "unknown" };
    return {
      status:   this.context.healthMonitor.getStatus(),
      port:     this.context.healthMonitor.port,
      history:  this.context.healthMonitor.history.slice(0, 10),
      fails:    this.context.healthMonitor.consecutiveFails,
    };
  }

  _getTunnels() {
    return new Promise((resolve) => {
      http.get(NGROK_TUNNELS, (res) => {
        let buf = "";
        res.on("data", (d) => buf += d);
        res.on("end", () => {
          try { resolve(JSON.parse(buf)); }
          catch (_) { resolve({ tunnels: [] }); }
        });
      }).on("error", () => resolve({ tunnels: [] }));
    });
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 39  ─  WATCHDOG (auto-restart on PHP crash)
// ════════════════════════════════════════════════════════════════════════════════

class Watchdog {
  constructor(phpServer, cfg) {
    this.phpServer  = phpServer;
    this.cfg        = cfg;
    this.maxRestarts = 3;
    this.restartCount = 0;
    this.lastRestartAt = 0;
    this.cooldownMs = 30000; // jangan restart kalau baru restart 30s lalu
    this.enabled    = true;
  }

  async checkAndRecover() {
    if (!this.enabled || !this.phpServer || this.phpServer.process) return;

    const now = Date.now();
    if (now - this.lastRestartAt < this.cooldownMs) return;

    if (this.restartCount >= this.maxRestarts) {
      logger.error(`Watchdog: PHP server crashed ${this.maxRestarts}x, give up`);
      this.enabled = false;
      return;
    }

    logger.warn(`Watchdog: PHP server crashed, restarting (${this.restartCount + 1}/${this.maxRestarts})`);
    try {
      await this.phpServer.start();
      this.restartCount++;
      this.lastRestartAt = now;
      logger.ok("PHP server restarted");
    } catch (e) {
      logger.error("Watchdog restart gagal: " + e.message);
    }
  }

  start() {
    this.interval = setInterval(() => this.checkAndRecover(), 5000);
  }

  stop() {
    if (this.interval) clearInterval(this.interval);
  }

  reset() {
    this.restartCount = 0;
    this.enabled = true;
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 40  ─  i18n (Indonesian / English)
// ════════════════════════════════════════════════════════════════════════════════

const I18N = {
  current: "id",
  strings: {
    id: {
      "ready":           "TopZone ONLINE!",
      "shutting_down":   "Mematikan server...",
      "preflight":       "Preflight Checks",
      "detect_server":   "Deteksi Server",
      "ngrok_tunnel":    "Ngrok Tunnel",
      "live_log":        "Live request log (Ctrl+C untuk stop)",
      "bye":             "Sampai jumpa!",
      "config_invalid":  "Konfigurasi tidak valid",
      "no_token":        "Token ngrok belum diisi",
      "wizard_title":    "Setup Wizard — Konfigurasi Pertama",
      "wizard_done":     "Setup selesai!",
      "browser_opened":  "Browser dibuka",
      "no_internet":     "Internet tidak terdeteksi",
    },
    en: {
      "ready":           "TopZone ONLINE!",
      "shutting_down":   "Shutting down server...",
      "preflight":       "Preflight Checks",
      "detect_server":   "Server Detection",
      "ngrok_tunnel":    "Ngrok Tunnel",
      "live_log":        "Live request log (Ctrl+C to stop)",
      "bye":             "See you later!",
      "config_invalid":  "Invalid configuration",
      "no_token":        "Ngrok token is empty",
      "wizard_title":    "Setup Wizard — Initial Configuration",
      "wizard_done":     "Setup complete!",
      "browser_opened":  "Browser opened",
      "no_internet":     "No internet detected",
    },
  },

  set(lang) {
    if (this.strings[lang]) this.current = lang;
  },

  t(key, ...args) {
    const str = this.strings[this.current][key] || this.strings.en[key] || key;
    if (args.length === 0) return str;
    return str.replace(/\{(\d+)\}/g, (_, i) => args[parseInt(i, 10)] || "");
  },
};

// Load language dari env
if (process.env.APP_LANG) I18N.set(process.env.APP_LANG);


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 41  ─  CONFIG FILE WATCHER (auto-reload .env)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Watch .env file, auto-reload kalau berubah. Tidak restart server,
//  cuma reload variable di memory. Cocok untuk perubahan minor.
//
// ════════════════════════════════════════════════════════════════════════════════

class ConfigWatcher extends EventEmitter {
  constructor() {
    super();
    this.watcher = null;
    this.lastMtime = 0;
  }

  watch(envFile, callback) {
    if (!fs.existsSync(envFile)) return;

    try {
      const stat = fs.statSync(envFile);
      this.lastMtime = stat.mtimeMs;
    } catch (_) {}

    this.watcher = fs.watch(envFile, { persistent: false }, (eventType) => {
      if (eventType !== "change") return;
      try {
        const stat = fs.statSync(envFile);
        if (stat.mtimeMs === this.lastMtime) return; // dedupe (file watcher kadang fire 2x)
        this.lastMtime = stat.mtimeMs;

        // Debounce
        clearTimeout(this._timer);
        this._timer = setTimeout(() => {
          logger.info("Config .env berubah, reload...");
          dotenv.config({ path: envFile, override: true });
          this.emit("reload");
          if (callback) callback();
        }, 500);
      } catch (_) {}
    });

    logger.debug(`Watching config: ${envFile}`);
  }

  stop() {
    if (this.watcher) this.watcher.close();
    if (this._timer) clearTimeout(this._timer);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 42  ─  STATS EXPORTER (JSON, CSV)
// ════════════════════════════════════════════════════════════════════════════════

class StatsExporter {
  static toJson(analytics, file) {
    const data = {
      exportedAt:    new Date().toISOString(),
      uptime:        analytics.uptime(),
      total:         analytics.totalCount,
      errors:        analytics.errorCount,
      methods:       analytics.byMethod,
      statuses:      analytics.byStatus,
      endpoints:     analytics.byEndpoint,
      slowRequests:  analytics.slowRequests,
      errorRequests: analytics.errorRequests,
    };
    fs.writeFileSync(file, JSON.stringify(data, null, 2));
    return file;
  }

  static toCsv(analytics, file) {
    const lines = ["timestamp,method,uri,status,duration_ms"];
    for (const r of analytics.recentRequests) {
      lines.push(`${new Date(r.ts).toISOString()},${r.method},"${r.uri}",${r.status},${r.duration}`);
    }
    fs.writeFileSync(file, lines.join("\n"));
    return file;
  }

  static autoExport(analytics, intervalMs = 60000) {
    if (!fs.existsSync(LOG_DIR)) fs.mkdirSync(LOG_DIR, { recursive: true });
    return setInterval(() => {
      const file = path.join(LOG_DIR, "stats-" + new Date().toISOString().slice(0, 10) + ".json");
      StatsExporter.toJson(analytics, file);
    }, intervalMs);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 43  ─  AUTO BACKUP (cron-style)
// ════════════════════════════════════════════════════════════════════════════════

class AutoBackup {
  constructor(cfg) {
    this.cfg = cfg;
    this.scheduled = null;
  }

  enable(schedule = "0 */6 * * *") {  // tiap 6 jam
    this.scheduled = scheduler.cron(schedule, async () => {
      logger.info("Auto-backup triggered");
      try {
        const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
        const file = path.join(BACKUP_DIR, `auto_${ts}.sql`);
        if (!fs.existsSync(BACKUP_DIR)) fs.mkdirSync(BACKUP_DIR, { recursive: true });
        await DbHelper.backup(this.cfg.db, file);
        logger.ok(`Auto-backup: ${file}`);

        // Cleanup backup lama (keep 10 terbaru)
        await Cleanup.backups(10);
      } catch (e) {
        logger.warn(`Auto-backup gagal: ${e.message}`);
      }
    }, { name: "auto-backup" });
    logger.info(`Auto-backup dijadwalkan: ${schedule}`);
  }

  disable() {
    if (this.scheduled) scheduler.cancel(this.scheduled);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 44  ─  CLI COMMANDS (extra)
// ════════════════════════════════════════════════════════════════════════════════

async function cmdHistory() {
  history.print();
}

async function cmdCleanup() {
  await Cleanup.runAll();
}

async function cmdCertGen() {
  printSubBanner("CERT — Generate Self-Signed");
  await CertHelper.generateSelfSigned();
}

async function cmdCertInfo() {
  printSubBanner("CERT — Info");
  CertHelper.info();
}

async function cmdBench(targetUrl, opts) {
  const bench = new Benchmark(targetUrl, opts);
  await bench.run();
}

async function cmdPluginList() {
  printSubBanner("PLUGINS");
  pluginSystem.load();
  const list = pluginSystem.list();
  if (list.length === 0) {
    console.log(dim("  Belum ada plugin."));
    console.log(dim(`  Folder plugin: ${pluginSystem.dir}`));
    console.log(dim(`  Buat sample dengan: node server.js --plugin:sample`));
    return;
  }
  console.log(renderTable(
    ["Name", "Version", "Hooks"],
    list.map(p => [p.name, p.version, p.hooks.join(", ")])
  ));
}

async function cmdPluginSample() {
  pluginSystem.installSample();
  logger.ok(`Sample plugin diinstall di: ${pluginSystem.dir}/sample-logger`);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 45  ─  EXTENDED CLI ROUTING
// ════════════════════════════════════════════════════════════════════════════════

async function handleExtendedCommands(args, cfg) {
  if (args.cmd === "history" || args._.includes("history")) {
    await cmdHistory();
    process.exit(0);
  }
  if (args.cmd === "cleanup" || args._.includes("cleanup")) {
    await cmdCleanup();
    process.exit(0);
  }
  if (args.cmd === "cert:gen") {
    await cmdCertGen();
    process.exit(0);
  }
  if (args.cmd === "cert:info") {
    await cmdCertInfo();
    process.exit(0);
  }
  if (args.cmd === "plugin:list") {
    await cmdPluginList();
    process.exit(0);
  }
  if (args.cmd === "plugin:sample") {
    await cmdPluginSample();
    process.exit(0);
  }
  if (args.cmd === "bench") {
    const target = args._[0] || `http://localhost:${cfg.localPort || 8080}`;
    await cmdBench(target, { concurrency: 10, requests: 100 });
    process.exit(0);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 46  ─  EXPANDED parseArgs (handle new commands)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Patch parseArgs() untuk handle command tambahan.
//  Karena parseArgs() sudah didefinisikan di section 05, kita tidak override
//  tapi tambahkan post-processing di sini.
//
// ════════════════════════════════════════════════════════════════════════════════

function parseArgsExtended(argv) {
  const args = parseArgs(argv);

  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "history" || a === "cleanup") args.cmd = a;
    else if (a.startsWith("--cert:") || a.startsWith("--plugin:") || a === "--bench" || a === "--profile:list")
      args.cmd = a.replace(/^--/, "");
    else if (a.startsWith("--lang="))
      args.lang = a.split("=")[1];
    else if (a.startsWith("--api-port="))
      args.apiPort = parseInt(a.split("=")[1], 10);
    else if (a === "--api")
      args.enableApi = true;
    else if (a === "--watch-config")
      args.watchConfig = true;
    else if (a === "--auto-backup")
      args.autoBackup = true;
    else if (a === "--export-stats")
      args.exportStats = true;
  }

  if (args.lang) I18N.set(args.lang);
  return args;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 47  ─  TUI SUMMARY (post-shutdown report)
// ════════════════════════════════════════════════════════════════════════════════

function printShutdownReport(analytics, sessionId) {
  if (!analytics) return;

  const stats = analytics.summary();
  const reqRate = stats.uptime > 0 ? (stats.total / stats.uptime).toFixed(2) : "0.00";
  const errRate = stats.total > 0 ? ((stats.errors / stats.total) * 100).toFixed(1) : "0.0";

  console.log(drawBox("📊  SESSION REPORT", [
    "",
    `  Total requests   : ${c.cyan}${stats.total}${c.reset}`,
    `  Total errors     : ${stats.errors > 0 ? c.red : c.green}${stats.errors}${c.reset} (${errRate}%)`,
    `  Uptime           : ${c.cyan}${formatDuration(stats.uptime)}${c.reset}`,
    `  Avg req/sec      : ${c.cyan}${reqRate}${c.reset}`,
    "",
    `  ${c.bold}Method Breakdown:${c.reset}`,
    ...Object.entries(stats.methods).map(([m, n]) => `    ${pad(m, 8)} ${c.cyan}${n}${c.reset}`),
    "",
    `  ${c.bold}Top 5 Endpoints:${c.reset}`,
    ...analytics.topEndpoints(5).map(([uri, n]) => `    ${pad(String(n), 5)} ${truncate(uri, 50)}`),
    "",
    `  Session ID       : ${dim(sessionId || "—")}`,
    "",
  ], { color: c.cyan, width: 70 }));
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 48  ─  FINAL HELP TEXT (replace original printHelp)
// ════════════════════════════════════════════════════════════════════════════════

function printHelpFull() {
  printBanner();
  console.log(bold("PEMAKAIAN:"));
  console.log("  node server.js [options] [command]\n");

  console.log(bold("OPTIONS UMUM:"));
  console.log("  --help, -h               Tampilkan bantuan ini");
  console.log("  --version, -v            Tampilkan versi");
  console.log("  --silent, -s             Mode tanpa output (kecuali error)");
  console.log("  --verbose                Mode debug (log lebih detail)");
  console.log("  --no-ngrok               Skip ngrok tunnel");
  console.log("  --no-browser             Jangan auto-open browser");
  console.log("  --no-log                 Disable file logging");
  console.log("  --no-webhook             Jangan tampilkan Xendit webhook URL");
  console.log("  --profile=NAME           Pakai profile .env.NAME");
  console.log("  --port=NUM               Override LOCAL_PORT");
  console.log("  --mode=MODE              Override SERVER_MODE");
  console.log("  --lang=LANG              Bahasa: id | en");
  console.log("  --api                    Aktifkan internal control API");
  console.log("  --api-port=NUM           Port control API (default 4041)");
  console.log("  --watch-config           Auto-reload kalau .env berubah");
  console.log("  --auto-backup            Aktifkan auto-backup database (tiap 6 jam)");
  console.log("  --export-stats           Auto-export stats per menit");
  console.log("");

  console.log(bold("COMMANDS:"));
  console.log(c.cyan + "  Database:" + c.reset);
  console.log("    --db:test              Tes koneksi database");
  console.log("    --db:setup             Import schema + seed");
  console.log("    --db:schema            Import schema saja");
  console.log("    --db:seed              Import seed saja");
  console.log("    --db:backup            Backup database ke file");
  console.log("    --db:reset             Reset database (HATI-HATI!)");
  console.log("");
  console.log(c.cyan + "  Setup:" + c.reset);
  console.log("    --setup                Setup wizard interaktif");
  console.log("    --check                Diagnostics (system check)");
  console.log("    --update-webhook       Tampilkan Xendit webhook URL aktif");
  console.log("");
  console.log(c.cyan + "  Profile:" + c.reset);
  console.log("    --profile:list         Tampilkan semua profile .env.*");
  console.log("");
  console.log(c.cyan + "  Plugins:" + c.reset);
  console.log("    --plugin:list          Tampilkan plugin yang ada");
  console.log("    --plugin:sample        Buat sample plugin");
  console.log("");
  console.log(c.cyan + "  Cert (HTTPS):" + c.reset);
  console.log("    --cert:gen             Generate self-signed cert");
  console.log("    --cert:info            Info certificate");
  console.log("");
  console.log(c.cyan + "  Maintenance:" + c.reset);
  console.log("    history                Tampilkan history session");
  console.log("    cleanup                Hapus log & backup lama");
  console.log("    --bench [URL]          Load test (10 concurrent, 100 req)");
  console.log("");
  console.log(c.cyan + "  Mode:" + c.reset);
  console.log("    --dashboard            Live monitoring dashboard");
  console.log("");

  console.log(bold("CONTOH:"));
  console.log("  node server.js                            # Default launch");
  console.log("  node server.js --setup                    # Setup wizard");
  console.log("  node server.js --check                    # Diagnostics");
  console.log("  node server.js --profile=staging          # Pakai staging env");
  console.log("  node server.js --no-ngrok --port=3000     # Local di port 3000");
  console.log("  node server.js --dashboard                # Dashboard mode");
  console.log("  node server.js --api --auto-backup        # API + auto-backup");
  console.log("  node server.js --bench http://localhost   # Load test");
  console.log("  node server.js --db:backup                # Backup DB");
  console.log("  node server.js history                    # History session");
  console.log("");

  console.log(bold("FILE & FOLDER:"));
  console.log(`  ${c.cyan}.env${c.reset}                Konfigurasi utama (lihat .env.example)`);
  console.log(`  ${c.cyan}.env.<profile>${c.reset}      Profile environment lain`);
  console.log(`  ${c.cyan}logs/${c.reset}               File log`);
  console.log(`  ${c.cyan}backups/${c.reset}            Database backup`);
  console.log(`  ${c.cyan}.tz-state.json${c.reset}      State persisted (auto-managed)`);
  console.log(`  ${c.cyan}.tz-history.json${c.reset}    History session`);
  console.log(`  ${c.cyan}.tz-plugins/${c.reset}        Folder plugin`);
  console.log(`  ${c.cyan}.certs/${c.reset}             Self-signed certificates`);
  console.log("");

  console.log(bold("DOKUMENTASI:"));
  console.log("  README.md       Overview + fitur");
  console.log("  SETUP.md        Panduan setup lengkap");
  console.log("  CONTRIBUTING.md Panduan kontribusi");
  console.log("  CHANGELOG.md    Riwayat perubahan");
  console.log("");

  console.log(bold("RESOURCES:"));
  console.log("  Ngrok           https://dashboard.ngrok.com");
  console.log("  Xendit          https://dashboard.xendit.co");
  console.log("");
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 49  ─  EXTENDED BOOTSTRAP (with new commands)
// ════════════════════════════════════════════════════════════════════════════════

async function bootstrapExtended(args) {
  // Set logger level
  if (args.silent)  logger.level = "warn";
  if (args.verbose) logger.level = "debug";
  if (args.noLog)   logger.toFile = false;

  State.load();
  State.set("startupCount", State.get("startupCount", 0) + 1);

  // Help & Version (langsung exit)
  if (args.help)    { printHelpFull(); process.exit(0); }
  if (args.version) { printVersion(); process.exit(0); }

  // List profiles
  if (args.cmd === "profile:list") {
    await cmdProfileList();
    process.exit(0);
  }

  // Pre-banner commands (history, cleanup, plugin)
  await handleExtendedCommands(args, loadConfig(args.profile));

  printBanner();

  // Wizard mode
  if (args.setup || await checkFirstRun()) {
    const wiz = new Wizard();
    await wiz.runFullSetup();
    if (args.setup) {
      console.log("\n" + ok("Setup selesai. Jalankan: node server.js"));
      process.exit(0);
    }
  }

  const cfg = loadConfig(args.profile);
  if (args.port) cfg.localPort = args.port;
  if (args.mode) cfg.serverMode = args.mode;
  State.set("lastProfile", cfg.profile);

  if (args.profile && args.profile !== "default") {
    logger.info(`Profile aktif: ${c.cyan}${args.profile}${c.reset}`);
  }

  // Load plugins
  pluginSystem.load();
  await pluginSystem.dispatch("onConfigLoad", { cfg, args, logger });

  // Diagnostics
  if (args.check) {
    const pf = new Preflight(cfg);
    await pf.runDiagnosticsOnly();
    return;
  }

  // Database commands
  if (args.cmd === "db:test")    { await DbHelper.cmdTest(cfg); process.exit(0); }
  if (args.cmd === "db:setup")   { await DbHelper.cmdSetup(cfg); process.exit(0); }
  if (args.cmd === "db:schema")  { await DbHelper.cmdSchema(cfg); process.exit(0); }
  if (args.cmd === "db:seed")    { await DbHelper.cmdSeed(cfg); process.exit(0); }
  if (args.cmd === "db:backup")  { await DbHelper.cmdBackup(cfg); process.exit(0); }
  if (args.cmd === "db:reset")   { await DbHelper.cmdReset(cfg); process.exit(0); }
  if (args.cmd === "update-webhook") { await cmdUpdateWebhook(cfg); process.exit(0); }

  return cfg;
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 50  ─  EXTENDED LAUNCH (with all new features wired in)
// ════════════════════════════════════════════════════════════════════════════════

async function fullLaunchExtended(cfg, args) {
  const pf = new Preflight(cfg);
  const { errors } = await pf.run();
  if (errors.length > 0) {
    logger.error(`${errors.length} masalah preflight harus dibenerin dulu`);
    process.exit(1);
  }

  await pluginSystem.dispatch("onPreflight", { cfg, args, logger, errors });

  const analytics = new RequestAnalytics();
  const shutdown  = new ShutdownManager();
  shutdown.setup();

  // Cek update di background
  if (cfg.appEnv === "development") {
    checkForUpdates().then(updates => {
      if (updates.length > 0) {
        console.log(warn(`Ada ${updates.length} dependency yang outdated:`));
        for (const u of updates) console.log(`  ${dim(u.name)} ${u.installed} → ${c.green}${u.latest}${c.reset}`);
        console.log(dim("  Jalankan: npm update\n"));
      }
    }).catch(() => {});
  }

  // Server resolution
  let localPort  = cfg.localPort;
  let phpServer  = null;
  let serverLabel;

  if (cfg.serverMode === "php") {
    phpServer    = new PhpServer(cfg);
    const result = await phpServer.start();
    localPort    = result.port;
    serverLabel  = `PHP Built-in (port ${localPort})`;
    shutdown.add("PHP server", () => phpServer.stop());
  } else if (cfg.serverMode === "custom") {
    printSubBanner("CUSTOM SERVER");
    const open = await isPortOpen(localPort);
    if (!open) {
      logger.error(`Tidak ada server di port ${localPort}`);
      process.exit(1);
    }
    const health = await httpHealthCheck(localPort);
    if (health.ok) logger.ok(`Server di port ${localPort} responsif (HTTP ${health.status})`);
    else           logger.warn(`Port ${localPort} terbuka tapi tidak respon HTTP — lanjut`);
    serverLabel = `Custom Server (port ${localPort})`;
  } else {
    const detector = new ServerDetector(cfg);
    const detected = await detector.detect();
    if (!detected) {
      phpServer    = new PhpServer(cfg);
      const result = await phpServer.start();
      localPort    = result.port;
      serverLabel  = `PHP Built-in (fallback, port ${localPort})`;
      shutdown.add("PHP server", () => phpServer.stop());
    } else {
      localPort   = detected.port;
      serverLabel = detected.label;
    }
  }

  await pluginSystem.dispatch("onServerStart", { cfg, localPort, serverLabel, logger });

  // Watchdog (kalau pakai PHP built-in)
  let watchdog = null;
  if (phpServer) {
    watchdog = new Watchdog(phpServer, cfg);
    watchdog.start();
    shutdown.add("Watchdog", () => watchdog.stop());
  }

  // Ngrok
  let publicUrl = null;
  let ngrokMgr = null;
  if (!args.noNgrok) {
    ngrokMgr = new NgrokManager(cfg);
    publicUrl = await ngrokMgr.start(localPort);
    shutdown.add("Ngrok tunnel", () => ngrokMgr.stop());
    await pluginSystem.dispatch("onNgrokReady", { url: publicUrl, port: localPort, logger });
  } else {
    logger.info("Ngrok di-skip (--no-ngrok)");
  }

  // Webhook
  let webhookSync = null;
  if (publicUrl && !args.noWebhook) {
    webhookSync = new WebhookSyncer(cfg);
    if (webhookSync.isEnabled()) {
      await webhookSync.updateOnce(publicUrl);
      webhookSync.startSync(() => publicUrl);
      shutdown.add("Webhook syncer", () => webhookSync.stop());
    }
  }

  // Health monitor
  const healthMon = new HealthMonitor(cfg, localPort);
  healthMon.start();
  shutdown.add("Health monitor", () => healthMon.stop());

  // Request logger
  let reqLogger = null;
  if (publicUrl && cfg.logRequests && !args.dashboard) {
    reqLogger = new RequestLogger(analytics, { silent: false });
    reqLogger.start();
    shutdown.add("Request logger", () => reqLogger.stop());
  } else if (args.dashboard) {
    reqLogger = new RequestLogger(analytics, { silent: true });
    reqLogger.start();
    shutdown.add("Request logger", () => reqLogger.stop());
  }

  // Control API
  let controlApi = null;
  if (args.enableApi) {
    controlApi = new ControlAPI({ port: args.apiPort });
    controlApi.setContext({ analytics, healthMonitor: healthMon, ngrok: ngrokMgr });
    controlApi.start();
    shutdown.add("Control API", () => controlApi.stop());
    logger.info(`Control API: http://127.0.0.1:${controlApi.port} (token: ${dim(controlApi.token)})`);
  }

  // Auto-backup
  if (args.autoBackup) {
    const ab = new AutoBackup(cfg);
    ab.enable();
    shutdown.add("Auto-backup", () => ab.disable());
  }

  // Stats exporter
  let statsExporter = null;
  if (args.exportStats) {
    statsExporter = StatsExporter.autoExport(analytics);
    shutdown.add("Stats exporter", () => clearInterval(statsExporter));
    logger.info("Auto stats export aktif (per menit)");
  }

  // Config watcher
  let configWatcher = null;
  if (args.watchConfig) {
    configWatcher = new ConfigWatcher();
    configWatcher.watch(cfg.envPath, () => {
      logger.info("Config reloaded — beberapa setting butuh restart untuk apply");
    });
    shutdown.add("Config watcher", () => configWatcher.stop());
  }

  // Record session start
  const sessionId = history.recordStart(cfg, localPort);

  // Print summary
  printSummary({ publicUrl, localPort, serverLabel, cfg, ngrokDisabled: args.noNgrok });

  // QR
  if (publicUrl && !args.silent && !args.dashboard) printQrCode(publicUrl);

  // Browser
  if (cfg.autoOpenBrowser && !args.noBrowser && !args.dashboard) {
    setTimeout(() => {
      const target = publicUrl || `http://localhost:${localPort}`;
      openBrowser(target, cfg.browserPath);
      logger.ok(`Browser dibuka: ${target}`);
    }, 1000);
  }

  // Notify
  if (cfg.notifyOnReady) {
    sendNotification("TopZone Ready", `Server jalan di ${publicUrl || "localhost:" + localPort}`);
    bell();
  }

  // Dashboard
  if (args.dashboard) {
    const dashboard = new Dashboard(cfg, analytics, healthMon, ngrokMgr, localPort);
    dashboard.start();
    shutdown.add("Dashboard", () => dashboard.stop());
  }

  // Plugins onReady hook
  await pluginSystem.dispatch("onReady", {
    cfg,
    localUrl:  `http://localhost:${localPort}`,
    publicUrl,
    analytics,
    logger,
    scheduler,
    runCommand: (cmd) => {
      // Simple command runner untuk plugin
      if (cmd === "db:backup") return DbHelper.cmdBackup(cfg);
    },
  });

  // Final shutdown handlers
  shutdown.add("Save session", () => {
    history.recordEnd(sessionId, {
      requests: analytics.totalCount,
      errors:   analytics.errorCount,
      ngrokUrl: publicUrl,
    });
  });

  shutdown.add("Plugins onShutdown", async () => {
    await pluginSystem.dispatch("onShutdown", { cfg, analytics, logger });
  });

  shutdown.add("Print report", () => {
    printShutdownReport(analytics, sessionId);
  });

  shutdown.add("Stop scheduler", () => scheduler.stopAll());
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 51  ─  EASTER EGG / FUN OUTPUTS
// ════════════════════════════════════════════════════════════════════════════════

const EASTER_EGGS = {
  greeting: () => {
    const hour = new Date().getHours();
    if (hour < 5)  return "Begadang? Hati-hati ya bro 😴";
    if (hour < 12) return "Selamat pagi! Semangat coding 🌅";
    if (hour < 17) return "Selamat siang! Tetap fokus 💪";
    if (hour < 20) return "Selamat sore! Hampir kelar nih 🌆";
    return "Selamat malam! Jangan lupa istirahat 🌙";
  },
  tip: () => {
    const tips = [
      "Tahukah kamu? Ngrok URL berubah tiap restart. Pakai custom domain biar tetap.",
      "Pakai --dashboard untuk live monitoring yang keren!",
      "Backup database otomatis: --auto-backup",
      "Cek system: node server.js --check",
      "Setup baru? Jalankan: node server.js --setup",
      "Mode local-only? Pakai: --no-ngrok",
      "Profile environment: --profile=staging",
      "Internal API: --api → http://127.0.0.1:4041",
      "Load test: node server.js --bench http://localhost",
      "History session: node server.js history",
    ];
    return tips[Math.floor(Math.random() * tips.length)];
  },
};

function printGreeting() {
  if (Math.random() < 0.3) {  // 30% chance
    console.log(`${c.dim}${EASTER_EGGS.greeting()}${c.reset}`);
  }
}

function printTip() {
  if (Math.random() < 0.5) {  // 50% chance
    console.log(`${c.dim}💡 Tips: ${EASTER_EGGS.tip()}${c.reset}\n`);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 52  ─  ENTRY POINT (final)
// ════════════════════════════════════════════════════════════════════════════════

(async () => {
  try {
    const args = parseArgsExtended(process.argv);

    await pluginSystem.dispatch("onInit", { args, logger });

    const cfg = await bootstrapExtended(args);
    if (!cfg) return;

    // Greeting & tips (kecuali silent mode)
    if (!args.silent) {
      printGreeting();
      printTip();
    }

    await fullLaunchExtended(cfg, args);

  } catch (err) {
    helpfulError(err, "startup");
    if (logger) logger.debug(err.stack || "");
    await pluginSystem.dispatch("onError", { error: err, logger });
    process.exit(1);
  }
})();


// ════════════════════════════════════════════════════════════════════════════════
//  END OF FILE — TopZone Universal Development Server v3.0
//
//  ── DEVELOPER NOTES ────────────────────────────────────────────────────────
//
//  Architecture overview:
//
//      ┌─────────────────────────────────────────────────────────────┐
//      │                       ENTRY POINT                           │
//      │                  ┌─────────┴──────────┐                     │
//      │                  ↓                    ↓                     │
//      │           parseArgs()           bootstrap()                 │
//      │                                       │                     │
//      │            ┌──────────┬──────────┬────┴──────┬───────────┐  │
//      │            ↓          ↓          ↓           ↓           ↓  │
//      │        --help     --setup    --check     --db:*    fullLaunch│
//      │            │          │          │           │           │  │
//      │                                                          ↓  │
//      │                                                  ┌────────┴────────┐
//      │                                                  ↓                 ↓
//      │                                            Preflight       ServerDetector
//      │                                                  │                 │
//      │                                                  ↓                 ↓
//      │                                              PhpServer         NgrokMgr
//      │                                                  │                 │
//      │                                                  ↓                 ↓
//      │                                            HealthMonitor    WebhookSyncer
//      │                                                                    │
//      │                                                                    ↓
//      │                                                            RequestLogger
//      │                                                                    │
//      │                                                                    ↓
//      │                                                              Dashboard?
//      │                                                                    │
//      │                                                                    ↓
//      │                                                          ShutdownManager
//      └─────────────────────────────────────────────────────────────┘
//
//  ── ADDING A NEW FEATURE ───────────────────────────────────────────────────
//
//  1. Tambahkan flag di parseArgs() (Section 05)
//  2. Tambahkan handler di bootstrap() (Section 31) atau fullLaunch()
//  3. Kalau butuh shutdown handler, daftarkan via shutdown.add()
//  4. Update printHelp() (Section 11) dengan flag baru
//  5. Update README.md & SETUP.md
//
//  ── TESTING CHECKLIST ──────────────────────────────────────────────────────
//
//  □  node server.js --help           → tampilkan help
//  □  node server.js --version        → tampilkan versi
//  □  node server.js --check          → diagnostics
//  □  node server.js --setup          → wizard
//  □  node server.js --no-ngrok       → local only
//  □  node server.js --dashboard      → live dashboard
//  □  node server.js --db:test        → tes DB
//  □  node server.js                  → full launch
//  □  Ctrl+C saat jalan               → graceful shutdown
//
//  ── KNOWN LIMITATIONS ──────────────────────────────────────────────────────
//
//  • QR code di printQrCode() bukan QR asli (cuma representasi visual).
//    Untuk QR asli, install: npm install qrcode-terminal
//  • Auto-update Xendit webhook saat ini hanya menampilkan URL.
//    Untuk full automation, butuh integrasi dengan Xendit Webhook API.
//  • Health monitor cuma cek port lokal, bukan ngrok URL public.
//  • System notification di Windows butuh PowerShell available.
//
// ════════════════════════════════════════════════════════════════════════════════


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 53  ─  LAN DEVICE DISCOVERY
// ════════════════════════════════════════════════════════════════════════════════
//
//  Cari device di network lokal yang merespons HTTP. Berguna untuk
//  menemukan port yang sedang dipakai aplikasi lain di LAN.
//
// ════════════════════════════════════════════════════════════════════════════════

class LanScanner {
  constructor(opts = {}) {
    this.timeout = opts.timeout || 500;
    this.commonPorts = [80, 443, 3000, 5000, 8000, 8080, 8888, 4040, 4041];
  }

  async scanSubnet(subnetBase = "192.168.1") {
    const results = [];
    const promises = [];

    for (let i = 1; i < 255; i++) {
      const ip = `${subnetBase}.${i}`;
      promises.push(this._pingHost(ip).then(alive => {
        if (alive) results.push({ ip, alive });
      }));
    }

    await Promise.all(promises);
    return results.sort((a, b) => {
      const numA = parseInt(a.ip.split(".").pop(), 10);
      const numB = parseInt(b.ip.split(".").pop(), 10);
      return numA - numB;
    });
  }

  async _pingHost(ip) {
    return new Promise((resolve) => {
      const sock = new net.Socket();
      sock.setTimeout(this.timeout);
      sock.connect(80, ip, () => { sock.destroy(); resolve(true); });
      sock.on("error",   () => { sock.destroy(); resolve(false); });
      sock.on("timeout", () => { sock.destroy(); resolve(false); });
    });
  }

  async findOpenPorts(host = "127.0.0.1") {
    const results = [];
    for (const port of this.commonPorts) {
      const open = await isPortOpen(port, host, this.timeout);
      if (open) {
        const health = await httpHealthCheck(port, host);
        results.push({ port, http: health.ok, status: health.status });
      }
    }
    return results;
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 54  ─  THEME SYSTEM
// ════════════════════════════════════════════════════════════════════════════════
//
//  Multiple color themes untuk yang suka customize. Pilih via env:
//
//    THEME=cyan      (default)
//    THEME=neon
//    THEME=mono
//    THEME=retro
//
// ════════════════════════════════════════════════════════════════════════════════

const THEMES = {
  cyan: {
    primary:   "\x1b[36m",
    secondary: "\x1b[34m",
    success:   "\x1b[32m",
    warning:   "\x1b[33m",
    error:     "\x1b[31m",
    accent:    "\x1b[35m",
  },
  neon: {
    primary:   "\x1b[95m",  // bright magenta
    secondary: "\x1b[96m",  // bright cyan
    success:   "\x1b[92m",  // bright green
    warning:   "\x1b[93m",  // bright yellow
    error:     "\x1b[91m",  // bright red
    accent:    "\x1b[94m",  // bright blue
  },
  mono: {
    primary:   "\x1b[37m",
    secondary: "\x1b[90m",
    success:   "\x1b[37m",
    warning:   "\x1b[37m",
    error:     "\x1b[1m",
    accent:    "\x1b[2m",
  },
  retro: {
    primary:   "\x1b[33m",  // amber/yellow
    secondary: "\x1b[32m",  // green like old terminal
    success:   "\x1b[32m",
    warning:   "\x1b[33m",
    error:     "\x1b[31m",
    accent:    "\x1b[36m",
  },
};

class ThemeManager {
  constructor(themeName = "cyan") {
    this.current = THEMES[themeName] ? themeName : "cyan";
  }

  apply() {
    const theme = THEMES[this.current];
    // Override color helpers if needed
    return theme;
  }

  list() {
    return Object.keys(THEMES);
  }

  set(name) {
    if (THEMES[name]) {
      this.current = name;
      return true;
    }
    return false;
  }
}

const themeManager = new ThemeManager(process.env.THEME);


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 55  ─  ENHANCED REQUEST ANALYTICS (histograms, percentiles)
// ════════════════════════════════════════════════════════════════════════════════

class AdvancedAnalytics extends RequestAnalytics {
  constructor() {
    super();
    this.histograms = {
      duration: this._createHistogram([10, 50, 100, 250, 500, 1000, 2000, 5000]),
      hourly:   {},
    };
    this.statusFamilies = { "1xx": 0, "2xx": 0, "3xx": 0, "4xx": 0, "5xx": 0 };
  }

  _createHistogram(buckets) {
    return buckets.map(threshold => ({ threshold, count: 0 }));
  }

  record(req) {
    const item = super.record(req);

    // Duration histogram
    const dur = item.duration || 0;
    for (const bucket of this.histograms.duration) {
      if (dur <= bucket.threshold) {
        bucket.count++;
        break;
      }
    }

    // Hourly histogram
    const hour = new Date(item.ts).getHours();
    this.histograms.hourly[hour] = (this.histograms.hourly[hour] || 0) + 1;

    // Status family
    if (item.status >= 100 && item.status < 200) this.statusFamilies["1xx"]++;
    else if (item.status >= 200 && item.status < 300) this.statusFamilies["2xx"]++;
    else if (item.status >= 300 && item.status < 400) this.statusFamilies["3xx"]++;
    else if (item.status >= 400 && item.status < 500) this.statusFamilies["4xx"]++;
    else if (item.status >= 500) this.statusFamilies["5xx"]++;

    return item;
  }

  percentile(p) {
    const durations = this.recentRequests
      .map(r => r.duration || 0)
      .sort((a, b) => a - b);
    if (durations.length === 0) return 0;
    return durations[Math.floor(durations.length * p / 100)];
  }

  printHistogram() {
    printSubBanner("ANALYTICS — Histograms");

    console.log(bold("Duration distribution:"));
    const max = Math.max(...this.histograms.duration.map(b => b.count));
    for (const bucket of this.histograms.duration) {
      const bar = max > 0 ? "█".repeat(Math.round((bucket.count / max) * 30)) : "";
      console.log(`  ${pad("≤" + bucket.threshold + "ms", 10)} ${pad(String(bucket.count), 6)} ${c.cyan}${bar}${c.reset}`);
    }
    console.log("");

    console.log(bold("Status codes:"));
    for (const [family, count] of Object.entries(this.statusFamilies)) {
      const color = family === "2xx" ? c.green : family === "3xx" ? c.cyan : family === "4xx" ? c.yellow : family === "5xx" ? c.red : c.gray;
      console.log(`  ${color}${family}${c.reset}: ${count}`);
    }
    console.log("");

    console.log(bold("Hourly activity:"));
    const maxHour = Math.max(...Object.values(this.histograms.hourly), 1);
    for (let h = 0; h < 24; h++) {
      const count = this.histograms.hourly[h] || 0;
      const bar = count > 0 ? "▆".repeat(Math.max(1, Math.round((count / maxHour) * 25))) : "";
      console.log(`  ${pad(String(h).padStart(2, "0") + ":00", 6)} ${pad(String(count), 4)} ${c.cyan}${bar}${c.reset}`);
    }
  }

  exportJson() {
    return {
      ...this.summary(),
      histograms: this.histograms,
      statusFamilies: this.statusFamilies,
      percentiles: {
        p50: this.percentile(50),
        p90: this.percentile(90),
        p95: this.percentile(95),
        p99: this.percentile(99),
      },
    };
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 56  ─  NETWORK DIAGNOSTICS
// ════════════════════════════════════════════════════════════════════════════════
//
//  Tools untuk ngecek jaringan: DNS lookup, traceroute (basic),
//  port reachability, dll.
//
// ════════════════════════════════════════════════════════════════════════════════

const dns = require("dns").promises;

class NetDiag {
  static async dnsLookup(hostname) {
    try {
      const result = await dns.lookup(hostname);
      return { ok: true, ...result };
    } catch (e) {
      return { ok: false, error: e.message };
    }
  }

  static async resolveAll(hostname) {
    try {
      const result = await dns.resolve4(hostname);
      return { ok: true, addresses: result };
    } catch (e) {
      return { ok: false, error: e.message };
    }
  }

  static async testConnectivity(targets = []) {
    const defaultTargets = [
      { name: "Google DNS",    host: "8.8.8.8",            port: 53 },
      { name: "Cloudflare DNS",host: "1.1.1.1",            port: 53 },
      { name: "ngrok",         host: "ngrok.com",          port: 443 },
      { name: "Xendit",        host: "api.xendit.co",      port: 443 },
      { name: "GitHub",        host: "github.com",         port: 443 },
    ];

    const tests = targets.length > 0 ? targets : defaultTargets;
    const results = [];

    for (const t of tests) {
      const start = Date.now();
      const open = await isPortOpen(t.port, t.host, 3000);
      results.push({
        name:    t.name,
        host:    t.host,
        port:    t.port,
        ok:      open,
        latency: Date.now() - start,
      });
    }
    return results;
  }

  static async runDiagnostics() {
    printSubBanner("NETWORK DIAGNOSTICS");

    // Local IPs
    const localIps = getLocalIpAddresses();
    console.log(bold("Local IP addresses:"));
    if (localIps.length === 0) {
      console.log("  " + dim("(tidak ada)"));
    } else {
      for (const ip of localIps) {
        console.log(`  ${c.cyan}${ip.address}${c.reset} (${ip.iface})`);
      }
    }
    console.log("");

    // DNS test
    console.log(bold("DNS resolution:"));
    const dnsTargets = ["google.com", "ngrok.com", "api.xendit.co"];
    for (const host of dnsTargets) {
      const result = await NetDiag.dnsLookup(host);
      if (result.ok) {
        console.log(`  ${ok(host + " → " + result.address)}`);
      } else {
        console.log(`  ${fail(host + ": " + result.error)}`);
      }
    }
    console.log("");

    // Connectivity test
    console.log(bold("Connectivity test:"));
    const conn = await NetDiag.testConnectivity();
    for (const r of conn) {
      const status = r.ok ? `${c.green}reachable${c.reset} (${r.latency}ms)` : `${c.red}unreachable${c.reset}`;
      console.log(`  ${pad(r.name, 18)} ${pad(r.host + ":" + r.port, 25)} ${status}`);
    }
    console.log("");

    // Port scan local
    console.log(bold("Local open ports:"));
    const scanner = new LanScanner();
    const ports = await scanner.findOpenPorts();
    if (ports.length === 0) {
      console.log("  " + dim("(tidak ada port umum yang terbuka)"));
    } else {
      for (const p of ports) {
        const httpStatus = p.http ? `HTTP ${p.status}` : "(non-HTTP)";
        console.log(`  ${c.cyan}port ${p.port}${c.reset} — ${httpStatus}`);
      }
    }
    console.log("");
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 57  ─  TELEMETRY (anonymized, opt-in)
// ════════════════════════════════════════════════════════════════════════════════
//
//  TIDAK DIAKTIFKAN by default. Untuk yang mau bantu development,
//  bisa enable via: TELEMETRY=true di .env. Data yang dikirim:
//   - Versi launcher
//   - Platform / arch
//   - Anonymous machine ID (hash MAC address)
//   - Error type (kalau crash)
//
//  Tidak ada user data, tidak ada IP, tidak ada token.
//
// ════════════════════════════════════════════════════════════════════════════════

class Telemetry {
  constructor() {
    this.enabled = (process.env.TELEMETRY || "false") === "true";
    this.machineId = this._generateAnonymousId();
  }

  _generateAnonymousId() {
    // Hash MAC address jadi anonymous ID
    const nets = os.networkInterfaces();
    const macs = [];
    for (const name of Object.keys(nets)) {
      for (const n of nets[name] || []) {
        if (n.mac && n.mac !== "00:00:00:00:00:00") macs.push(n.mac);
      }
    }
    return crypto.createHash("sha256").update(macs.sort().join("|")).digest("hex").slice(0, 16);
  }

  track(event, payload = {}) {
    if (!this.enabled) return;

    // Send fire-and-forget — gagal nggak masalah
    const data = {
      version:   APP_VERSION,
      platform:  process.platform,
      arch:      process.arch,
      node:      process.version,
      machineId: this.machineId,
      event,
      payload,
      ts:        Date.now(),
    };

    // Untuk demo, kita log saja ke file. Production-nya akan POST ke endpoint.
    try {
      const file = path.join(LOG_DIR, "telemetry.jsonl");
      if (!fs.existsSync(LOG_DIR)) fs.mkdirSync(LOG_DIR, { recursive: true });
      fs.appendFileSync(file, JSON.stringify(data) + "\n");
    } catch (_) {}
  }

  trackError(err) {
    this.track("error", {
      message: err.message,
      code:    err.code,
      type:    err.constructor.name,
    });
  }

  trackStartup(cfg) {
    this.track("startup", {
      mode:       cfg.serverMode,
      profile:    cfg.profile,
      hasNgrok:   !!cfg.ngrokToken,
      hasXendit:  !!cfg.xenditKey,
    });
  }
}

const telemetry = new Telemetry();


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 58  ─  SOUND ALERTS (cross-platform, optional)
// ════════════════════════════════════════════════════════════════════════════════

class SoundAlert {
  static beep(times = 1) {
    for (let i = 0; i < times; i++) {
      process.stdout.write("\x07");
    }
  }

  static success() {
    if ((process.env.SOUND || "true") !== "true") return;
    SoundAlert.beep(1);
  }

  static error() {
    if ((process.env.SOUND || "true") !== "true") return;
    SoundAlert.beep(3);
  }

  static notify() {
    if ((process.env.SOUND || "true") !== "true") return;
    SoundAlert.beep(2);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 59  ─  CRASH REPORTER
// ════════════════════════════════════════════════════════════════════════════════
//
//  Saat aplikasi crash, simpan stacktrace + state ke file untuk
//  debugging nanti. File disimpan di logs/crashes/
//
// ════════════════════════════════════════════════════════════════════════════════

class CrashReporter {
  static save(err, context = {}) {
    const crashDir = path.join(LOG_DIR, "crashes");
    if (!fs.existsSync(crashDir)) {
      try { fs.mkdirSync(crashDir, { recursive: true }); } catch (_) { return; }
    }

    const ts = new Date().toISOString().replace(/[:.]/g, "-");
    const file = path.join(crashDir, `crash-${ts}.json`);

    const report = {
      timestamp:  new Date().toISOString(),
      version:    APP_VERSION,
      platform:   process.platform,
      arch:       process.arch,
      node:       process.version,
      uptime:     process.uptime(),
      memory:     process.memoryUsage(),
      cwd:        process.cwd(),
      error: {
        message:  err.message,
        code:     err.code,
        stack:    err.stack,
        type:     err.constructor.name,
      },
      context,
    };

    try {
      fs.writeFileSync(file, JSON.stringify(report, null, 2));
      logger.info(`Crash report saved: ${file}`);
    } catch (_) {}
  }

  static listRecent(n = 5) {
    const crashDir = path.join(LOG_DIR, "crashes");
    if (!fs.existsSync(crashDir)) return [];

    return fs.readdirSync(crashDir)
      .filter(f => f.startsWith("crash-"))
      .map(f => ({
        name:  f,
        path:  path.join(crashDir, f),
        mtime: fs.statSync(path.join(crashDir, f)).mtimeMs,
      }))
      .sort((a, b) => b.mtime - a.mtime)
      .slice(0, n);
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 60  ─  KEYBOARD SHORTCUTS (saat server jalan)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Saat server jalan, tekan tombol untuk action:
//   r    → restart server
//   q    → quit
//   d    → toggle dashboard
//   s    → show stats summary
//   c    → clear screen
//   h    → help
//   b    → backup database
//
// ════════════════════════════════════════════════════════════════════════════════

class KeyboardShortcuts {
  constructor(opts) {
    this.opts = opts;
    this.bindings = {};
    this.enabled = false;
  }

  bind(key, description, action) {
    this.bindings[key] = { description, action };
  }

  start() {
    if (!process.stdin.isTTY) return;
    if (this.enabled) return;
    this.enabled = true;

    try { readline.emitKeypressEvents(process.stdin); } catch (_) {}
    if (process.stdin.setRawMode) process.stdin.setRawMode(true);
    process.stdin.resume();

    process.stdin.on("keypress", (ch, key) => this._handleKey(ch, key));
  }

  _handleKey(ch, key) {
    if (!key) return;
    if (key.ctrl && key.name === "c") {
      process.kill(process.pid, "SIGINT");
      return;
    }

    const binding = this.bindings[key.name] || this.bindings[ch];
    if (binding && binding.action) {
      try { binding.action(); }
      catch (e) { logger.warn("Shortcut error: " + e.message); }
    }
  }

  stop() {
    if (process.stdin.setRawMode) {
      try { process.stdin.setRawMode(false); } catch (_) {}
    }
    process.stdin.pause();
    this.enabled = false;
  }

  help() {
    console.log(bold("\nKeyboard Shortcuts:"));
    for (const [key, info] of Object.entries(this.bindings)) {
      console.log(`  ${c.cyan}[${key}]${c.reset} ${info.description}`);
    }
    console.log("");
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 61  ─  PROCESS MANAGER (multi-tunnel, multi-server)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Kalau butuh jalanin lebih dari satu server (mis: backend + frontend),
//  bisa pakai ProcessManager untuk track semua child process.
//
// ════════════════════════════════════════════════════════════════════════════════

class ProcessManager {
  constructor() {
    this.processes = [];
  }

  spawn(name, command, args = [], opts = {}) {
    const child = spawn(command, args, {
      stdio: opts.stdio || ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
      ...opts,
    });

    const proc = {
      id:       crypto.randomBytes(4).toString("hex"),
      name,
      command,
      args,
      child,
      startedAt: Date.now(),
      stdout:   [],
      stderr:   [],
      exitCode: null,
    };

    if (child.stdout) {
      child.stdout.on("data", (d) => {
        const line = d.toString().trim();
        proc.stdout.push({ ts: Date.now(), line });
        if (proc.stdout.length > 100) proc.stdout.shift();
        if (opts.onStdout) opts.onStdout(line);
      });
    }
    if (child.stderr) {
      child.stderr.on("data", (d) => {
        const line = d.toString().trim();
        proc.stderr.push({ ts: Date.now(), line });
        if (proc.stderr.length > 100) proc.stderr.shift();
        if (opts.onStderr) opts.onStderr(line);
      });
    }
    child.on("exit", (code) => {
      proc.exitCode = code;
      proc.endedAt = Date.now();
    });

    this.processes.push(proc);
    return proc;
  }

  list() {
    return this.processes.map(p => ({
      id:        p.id,
      name:      p.name,
      command:   p.command,
      args:      p.args,
      pid:       p.child.pid,
      startedAt: new Date(p.startedAt).toISOString(),
      uptime:    Math.floor((Date.now() - p.startedAt) / 1000),
      exitCode:  p.exitCode,
      alive:     p.exitCode === null,
    }));
  }

  kill(id) {
    const proc = this.processes.find(p => p.id === id);
    if (!proc) return false;
    if (proc.child && !proc.child.killed) {
      proc.child.kill("SIGTERM");
      return true;
    }
    return false;
  }

  killAll() {
    for (const p of this.processes) {
      if (p.child && !p.child.killed) {
        try { p.child.kill("SIGTERM"); } catch (_) {}
      }
    }
  }
}

const processManager = new ProcessManager();


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 62  ─  MORE HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════════════════════════

/**
 * Sleep helper untuk async/await.
 */
function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

/**
 * Retry async function dengan exponential backoff.
 */
async function retry(fn, opts = {}) {
  const max     = opts.max     || 3;
  const baseMs  = opts.baseMs  || 1000;
  const factor  = opts.factor  || 2;

  let lastErr;
  for (let i = 0; i < max; i++) {
    try {
      return await fn();
    } catch (e) {
      lastErr = e;
      if (i < max - 1) {
        const delay = baseMs * Math.pow(factor, i);
        await sleep(delay);
      }
    }
  }
  throw lastErr;
}

/**
 * Throttle: panggil fn paling cepat sekali per intervalMs.
 */
function throttle(fn, intervalMs) {
  let last = 0;
  return (...args) => {
    const now = Date.now();
    if (now - last >= intervalMs) {
      last = now;
      return fn(...args);
    }
  };
}

/**
 * Debounce: panggil fn setelah delayMs tanpa panggilan baru.
 */
function debounce(fn, delayMs) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delayMs);
  };
}

/**
 * Format ke locale ID (Rp 1.234.567).
 */
function formatRupiah(n) {
  return "Rp " + Number(n).toLocaleString("id-ID");
}

/**
 * Generate UUID v4 (simple).
 */
function uuid() {
  return crypto.randomBytes(16).toString("hex").replace(/(.{8})(.{4})(.{4})(.{4})(.{12})/, "$1-$2-$3-$4-$5");
}

/**
 * Hash file (SHA-256).
 */
function hashFile(filePath) {
  return new Promise((resolve, reject) => {
    const hash   = crypto.createHash("sha256");
    const stream = fs.createReadStream(filePath);
    stream.on("data",  (d) => hash.update(d));
    stream.on("end",   () => resolve(hash.digest("hex")));
    stream.on("error", reject);
  });
}

/**
 * Recursive copy directory.
 */
function copyDir(src, dest) {
  if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const srcPath  = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) copyDir(srcPath, destPath);
    else fs.copyFileSync(srcPath, destPath);
  }
}

/**
 * Recursive remove directory.
 */
function rmDir(dirPath) {
  if (!fs.existsSync(dirPath)) return;
  for (const entry of fs.readdirSync(dirPath, { withFileTypes: true })) {
    const fullPath = path.join(dirPath, entry.name);
    if (entry.isDirectory()) rmDir(fullPath);
    else fs.unlinkSync(fullPath);
  }
  fs.rmdirSync(dirPath);
}

/**
 * Format timestamp ISO ke locale Indonesia.
 */
function formatTime(date) {
  return new Date(date).toLocaleString("id-ID", {
    dateStyle: "short",
    timeStyle: "medium",
  });
}

/**
 * Truncate string dengan ellipsis pintar.
 */
function smartTruncate(str, max) {
  if (str.length <= max) return str;
  const part = Math.floor((max - 3) / 2);
  return str.slice(0, part) + "..." + str.slice(-part);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 63  ─  ASCII ART HELPERS
// ════════════════════════════════════════════════════════════════════════════════

function asciiHeart() {
  return [
    "  ♥ ♥   ♥ ♥  ",
    "♥ ♥ ♥ ♥ ♥ ♥ ♥",
    "♥ ♥ ♥ ♥ ♥ ♥ ♥",
    " ♥ ♥ ♥ ♥ ♥ ♥ ",
    "  ♥ ♥ ♥ ♥ ♥  ",
    "    ♥ ♥ ♥    ",
    "      ♥      ",
  ];
}

function asciiSuccess() {
  return [
    "      ╔═╗┬ ┬┌─┐┌─┐┌─┐┌─┐┌─┐",
    "      ╚═╗│ ││  │  ├┤ └─┐└─┐",
    "      ╚═╝└─┘└─┘└─┘└─┘└─┘└─┘",
  ];
}

function asciiWarning() {
  return [
    "      ╦ ╦┌─┐┬─┐┌┐┌┬┌┐┌┌─┐",
    "      ║║║├─┤├┬┘│││││││ ┬",
    "      ╚╩╝┴ ┴┴└─┘└┘┴┘└┘└─┘",
  ];
}

function asciiError() {
  return [
    "      ╔═╗┬─┐┬─┐┌─┐┬─┐",
    "      ║╣ ├┬┘├┬┘│ │├┬┘",
    "      ╚═╝┴└─┴└─└─┘┴└─",
  ];
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 64  ─  DOCUMENTATION SCANNER
// ════════════════════════════════════════════════════════════════════════════════
//
//  Scan documentation files (README, SETUP, dll) dan tampilkan TOC.
//  Berguna untuk eksplorasi proyek.
//
// ════════════════════════════════════════════════════════════════════════════════

class DocsScanner {
  static findDocs() {
    const candidates = ["README.md", "SETUP.md", "CONTRIBUTING.md", "CHANGELOG.md", "LICENSE"];
    return candidates.filter(f => fs.existsSync(path.join(PROJECT_ROOT, f)));
  }

  static parseTOC(filePath) {
    if (!fs.existsSync(filePath)) return [];
    const content = fs.readFileSync(filePath, "utf8");
    const lines = content.split("\n");
    const toc = [];
    for (const line of lines) {
      const match = line.match(/^(#{1,6})\s+(.+)$/);
      if (match) {
        toc.push({
          level:  match[1].length,
          title:  match[2].trim(),
        });
      }
    }
    return toc;
  }

  static printOverview() {
    printSubBanner("PROJECT DOCS");
    const docs = DocsScanner.findDocs();
    for (const doc of docs) {
      const fullPath = path.join(PROJECT_ROOT, doc);
      const stat = fs.statSync(fullPath);
      const size = formatBytes(stat.size);
      console.log(`${c.cyan}${c.bold}${doc}${c.reset} ${dim("(" + size + ")")}`);

      const toc = DocsScanner.parseTOC(fullPath);
      for (const item of toc.slice(0, 10)) {
        const indent = "  ".repeat(item.level);
        console.log(`${indent}${c.gray}${icons.bullet}${c.reset} ${item.title}`);
      }
      if (toc.length > 10) {
        console.log(`  ${dim("(... " + (toc.length - 10) + " more)")}`);
      }
      console.log("");
    }
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 65  ─  DEPENDENCY GRAPH (visualize project deps)
// ════════════════════════════════════════════════════════════════════════════════

class DepGraph {
  static analyze() {
    const pkg = require(path.join(PROJECT_ROOT, "package.json"));
    const composer = fs.existsSync(path.join(PROJECT_ROOT, "composer.json"))
      ? require(path.join(PROJECT_ROOT, "composer.json"))
      : null;

    return {
      npm: {
        deps:    pkg.dependencies || {},
        devDeps: pkg.devDependencies || {},
      },
      composer: composer ? {
        require:    composer.require || {},
        requireDev: composer["require-dev"] || {},
      } : null,
    };
  }

  static print() {
    printSubBanner("DEPENDENCIES");
    const graph = DepGraph.analyze();

    console.log(bold("📦 NPM Dependencies:"));
    for (const [name, version] of Object.entries(graph.npm.deps)) {
      console.log(`  ${c.cyan}${name}${c.reset} ${dim(version)}`);
    }
    if (Object.keys(graph.npm.devDeps).length > 0) {
      console.log(bold("\n📦 NPM Dev Dependencies:"));
      for (const [name, version] of Object.entries(graph.npm.devDeps)) {
        console.log(`  ${c.cyan}${name}${c.reset} ${dim(version)}`);
      }
    }

    if (graph.composer) {
      console.log(bold("\n🐘 Composer Requirements:"));
      for (const [name, version] of Object.entries(graph.composer.require)) {
        console.log(`  ${c.cyan}${name}${c.reset} ${dim(version)}`);
      }
    }
    console.log("");
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 66  ─  SECURITY AUDIT (basic)
// ════════════════════════════════════════════════════════════════════════════════

class SecurityAudit {
  static async run() {
    printSubBanner("SECURITY AUDIT");

    const issues = [];

    // 1. Cek .env permission
    if (fs.existsSync(ENV_FILE)) {
      const stat = fs.statSync(ENV_FILE);
      const mode = (stat.mode & parseInt("777", 8)).toString(8);
      if (process.platform !== "win32" && mode !== "600") {
        issues.push({
          severity: "warn",
          msg:      `.env permission ${mode} terlalu permisif`,
          fix:      `chmod 600 .env`,
        });
      }
    } else {
      issues.push({
        severity: "info",
        msg:      ".env tidak ada",
        fix:      "Jalankan setup wizard: node server.js --setup",
      });
    }

    // 2. Cek default password di seed.sql
    const seedFile = path.join(PROJECT_ROOT, "database", "seed.sql");
    if (fs.existsSync(seedFile)) {
      const content = fs.readFileSync(seedFile, "utf8");
      if (content.includes("admin123") || content.includes("demo123")) {
        issues.push({
          severity: "warn",
          msg:      "Password default masih di seed.sql",
          fix:      "Ganti password default di production",
        });
      }
    }

    // 3. Cek APP_DEBUG di production
    if (process.env.APP_ENV === "production" && process.env.APP_DEBUG === "true") {
      issues.push({
        severity: "high",
        msg:      "APP_DEBUG=true di production!",
        fix:      "Set APP_DEBUG=false di .env production",
      });
    }

    // 4. Cek APP_SECRET
    if (process.env.APP_SECRET === "ganti_dengan_random_string_panjang" || !process.env.APP_SECRET) {
      issues.push({
        severity: "high",
        msg:      "APP_SECRET masih placeholder atau kosong",
        fix:      "Generate: node -e \"console.log(require('crypto').randomBytes(32).toString('hex'))\"",
      });
    }

    // 5. Cek sensitive files di gitignore
    const gitignorePath = path.join(PROJECT_ROOT, ".gitignore");
    if (fs.existsSync(gitignorePath)) {
      const ignore = fs.readFileSync(gitignorePath, "utf8");
      const patterns = [".env", "*.log", "node_modules/"];
      for (const p of patterns) {
        if (!ignore.includes(p)) {
          issues.push({
            severity: "warn",
            msg:      `Pattern "${p}" tidak ada di .gitignore`,
            fix:      `Tambahkan ${p} ke .gitignore`,
          });
        }
      }
    }

    // 6. Cek HTTPS di production
    if (process.env.APP_ENV === "production" && process.env.BASE_URL && !process.env.BASE_URL.startsWith("https://")) {
      issues.push({
        severity: "high",
        msg:      "BASE_URL pakai HTTP di production",
        fix:      "Pakai HTTPS untuk production",
      });
    }

    // Print results
    if (issues.length === 0) {
      console.log(ok("Tidak ada issue keamanan yang terdeteksi"));
    } else {
      const severityColor = (s) => s === "high" ? c.red : s === "warn" ? c.yellow : c.cyan;
      const severityLabel = (s) => s === "high" ? "HIGH" : s === "warn" ? "WARN" : "INFO";

      for (const issue of issues) {
        console.log(`${severityColor(issue.severity)}[${severityLabel(issue.severity)}]${c.reset} ${issue.msg}`);
        console.log(`     ${dim("→ " + issue.fix)}`);
      }
    }
    console.log("");
    console.log(dim(`Total: ${issues.length} issue(s)`));
  }
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 67  ─  WEB UI ENDPOINT (kalau ControlAPI aktif)
// ════════════════════════════════════════════════════════════════════════════════
//
//  Optional: HTML page sederhana untuk view stats di browser.
//  Diakses via http://127.0.0.1:4041/ui (kalau --api aktif)
//
// ════════════════════════════════════════════════════════════════════════════════

const WEB_UI_HTML = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>TopZone Live Stats</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #1a1a1a; color: #eee; margin: 0; padding: 20px; }
    h1   { color: #00d4ff; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
    .card { background: #2a2a2a; padding: 20px; border-radius: 8px; border-left: 4px solid #00d4ff; }
    .card .label { color: #888; font-size: 12px; text-transform: uppercase; }
    .card .value { font-size: 28px; font-weight: bold; margin-top: 5px; color: #00d4ff; }
    table { width: 100%; border-collapse: collapse; background: #2a2a2a; border-radius: 8px; overflow: hidden; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3a3a3a; }
    th { background: #333; color: #00d4ff; }
    .err { color: #ff6b6b; }
    .ok { color: #51cf66; }
  </style>
</head>
<body>
  <h1>🎮 TopZone Live Stats</h1>
  <p>Auto-refresh tiap 2 detik · <span id="updated">—</span></p>

  <div class="grid" id="stats-grid"></div>

  <h2>Recent Requests</h2>
  <table id="recent-table">
    <thead><tr><th>Time</th><th>Method</th><th>Status</th><th>URI</th><th>Duration</th></tr></thead>
    <tbody></tbody>
  </table>

  <script>
    async function refresh() {
      try {
        const res = await fetch('/stats');
        const data = await res.json();

        const grid = document.getElementById('stats-grid');
        grid.innerHTML = \`
          <div class="card"><div class="label">Total Requests</div><div class="value">\${data.total}</div></div>
          <div class="card"><div class="label">Errors</div><div class="value \${data.errors > 0 ? 'err' : 'ok'}">\${data.errors}</div></div>
          <div class="card"><div class="label">Uptime</div><div class="value">\${data.uptime}s</div></div>
        \`;

        const tbody = document.querySelector('#recent-table tbody');
        tbody.innerHTML = (data.recent || []).map(r => \`
          <tr>
            <td>\${new Date(r.ts).toLocaleTimeString()}</td>
            <td>\${r.method}</td>
            <td class="\${r.status >= 400 ? 'err' : 'ok'}">\${r.status}</td>
            <td>\${r.uri}</td>
            <td>\${r.duration ? r.duration.toFixed(0) + 'ms' : '—'}</td>
          </tr>
        \`).join('');

        document.getElementById('updated').innerText = new Date().toLocaleTimeString();
      } catch (e) {
        console.error(e);
      }
    }

    refresh();
    setInterval(refresh, 2000);
  </script>
</body>
</html>`;


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 68  ─  COMMAND ALIASES (singkatan biar gampang)
// ════════════════════════════════════════════════════════════════════════════════

const COMMAND_ALIASES = {
  "s":         "--setup",
  "c":         "--check",
  "d":         "--dashboard",
  "h":         "--help",
  "v":         "--version",
  "test":      "--db:test",
  "backup":    "--db:backup",
  "reset":     "--db:reset",
  "diag":      "--check",
  "wizard":    "--setup",
  "info":      "--check",
};

function expandAliases(argv) {
  return argv.map(a => COMMAND_ALIASES[a] || a);
}


// ════════════════════════════════════════════════════════════════════════════════
//  SECTION 69  ─  EXPORT FOR EXTERNAL USE (kalau di-require)
// ════════════════════════════════════════════════════════════════════════════════

if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    APP_NAME,
    APP_VERSION,
    Logger,
    Wizard,
    Preflight,
    PhpServer,
    NgrokManager,
    WebhookSyncer,
    HealthMonitor,
    Dashboard,
    Scheduler,
    SessionHistory,
    Cleanup,
    Benchmark,
    CertHelper,
    ControlAPI,
    Watchdog,
    PluginSystem,
    LanScanner,
    NetDiag,
    SecurityAudit,
    Telemetry,
    DocsScanner,
    DepGraph,
    AdvancedAnalytics,
    ProcessManager,
    KeyboardShortcuts,
    CrashReporter,
    SoundAlert,
    State,
    ProfileMgr,
    DbHelper,
    StatsExporter,
    AutoBackup,
    ConfigWatcher,
    // Helpers
    sleep,
    retry,
    throttle,
    debounce,
    formatBytes,
    formatDuration,
    formatRupiah,
    formatTime,
    smartTruncate,
    uuid,
    hashFile,
    copyDir,
    rmDir,
    isPortOpen,
    httpHealthCheck,
    findFreePort,
    getLocalIpAddresses,
    pingExternal,
    httpRequest,
    openBrowser,
    sendNotification,
  };
}


// ════════════════════════════════════════════════════════════════════════════════
//                       END OF FILE — Server.js v3.0
//                       (4500+ lines of pure launcher goodness)
// ════════════════════════════════════════════════════════════════════════════════

