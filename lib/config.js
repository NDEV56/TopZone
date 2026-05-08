/**
 * lib/config.js — manajemen konfigurasi (.env + state runtime)
 * ────────────────────────────────────────────────────────────
 * Dipisah jadi 2:
 *   1. .env  — preferensi user (token, mode, port). Diedit user.
 *   2. .topzone-state.json — state runtime (last tunnel, last server, dll).
 *      Tidak boleh diedit manual; ditulis otomatis.
 *
 * Loader ini juga ramah kalau dotenv belum keinstall (silent fallback).
 */

"use strict";

const fs   = require("fs");
const path = require("path");
const { ensureDir, safeWrite, safeRead } = require("./utils");

const ROOT       = path.resolve(__dirname, "..");
const ENV_FILE   = path.join(ROOT, ".env");
const STATE_FILE = path.join(ROOT, ".topzone-state.json");
const ENV_SAMPLE = path.join(ROOT, ".env.example");

/** Mini parser .env — tidak butuh dotenv. */
function parseEnv(content) {
  const out = {};
  if (!content) return out;
  const lines = content.split(/\r?\n/);
  for (let raw of lines) {
    raw = raw.trim();
    if (!raw || raw.startsWith("#")) continue;
    const eq = raw.indexOf("=");
    if (eq === -1) continue;
    const key = raw.slice(0, eq).trim();
    let val = raw.slice(eq + 1).trim();
    // Strip surrounding quotes
    if ((val.startsWith('"') && val.endsWith('"')) ||
        (val.startsWith("'") && val.endsWith("'"))) {
      val = val.slice(1, -1);
    }
    if (key) out[key] = val;
  }
  return out;
}

/** Apply parsed env ke process.env (tanpa override yang sudah ada). */
function applyEnv(parsed, override = false) {
  for (const k of Object.keys(parsed)) {
    if (override || process.env[k] === undefined) {
      process.env[k] = parsed[k];
    }
  }
}

/** Default config. */
const DEFAULTS = {
  // Auth
  NGROK_AUTHTOKEN: "",
  NGROK_DOMAIN   : "",
  CLOUDFLARED_TOKEN: "",

  // Server
  SERVER_MODE    : "auto",        // auto|xampp|laragon|wamp|mamp|ampps|openserver|usbwebserver|easyphp|php|custom
  LOCAL_PORT     : "0",
  PHP_PORT       : "8080",
  PHP_ROOT       : path.join(ROOT, "Home"),

  // Tunnel
  TUNNEL_PROVIDER: "ngrok",       // ngrok|cloudflared|localtunnel|serveo|pinggy|none
  TUNNEL_FALLBACK: "true",        // coba tunnel lain kalau yang dipilih gagal
  LOG_REQUESTS   : "true",

  // GUI
  GUI_PORT       : "4747",
  GUI_BIND       : "127.0.0.1",   // jangan expose GUI ke publik
  GUI_PASSWORD   : "",            // kosong = tanpa password (tapi dipaksa ada token random)
  GUI_AUTO_OPEN  : "true",

  // Security
  SEC_RATE_LIMIT     : "60",      // request per menit per IP ke GUI
  SEC_BLOCK_ON_FAIL  : "5",       // berapa kali login gagal sebelum di-block
  SEC_BLOCK_DURATION : "300",     // detik

  // Updater
  AUTO_UPDATE        : "ask",     // ask|true|false
  UPDATE_BRANCH      : "main",
  UPDATE_REMOTE      : "origin",

  // Logger
  LOG_MIN_LEVEL      : "common",
  LOG_RETENTION_DAYS : "30",
};

/** Baca .env + apply ke process.env. */
function loadEnv() {
  const raw    = safeRead(ENV_FILE);
  const parsed = parseEnv(raw);
  applyEnv(parsed, true);
  return parsed;
}

/** Hasilkan object config final (env > default). */
function buildConfig() {
  loadEnv();
  const cfg = {};
  for (const key of Object.keys(DEFAULTS)) {
    cfg[key] = process.env[key] !== undefined && process.env[key] !== ""
      ? process.env[key]
      : DEFAULTS[key];
  }

  // Convert types
  cfg.LOCAL_PORT   = parseInt(cfg.LOCAL_PORT, 10) || 0;
  cfg.PHP_PORT     = parseInt(cfg.PHP_PORT,   10) || 8080;
  cfg.GUI_PORT     = parseInt(cfg.GUI_PORT,   10) || 4747;
  cfg.LOG_REQUESTS = cfg.LOG_REQUESTS === "true";
  cfg.GUI_AUTO_OPEN = cfg.GUI_AUTO_OPEN === "true";
  cfg.TUNNEL_FALLBACK = cfg.TUNNEL_FALLBACK === "true";
  cfg.SEC_RATE_LIMIT  = parseInt(cfg.SEC_RATE_LIMIT, 10) || 60;
  cfg.SEC_BLOCK_ON_FAIL = parseInt(cfg.SEC_BLOCK_ON_FAIL, 10) || 5;
  cfg.SEC_BLOCK_DURATION = parseInt(cfg.SEC_BLOCK_DURATION, 10) || 300;
  cfg.LOG_RETENTION_DAYS = parseInt(cfg.LOG_RETENTION_DAYS, 10) || 30;
  cfg.SERVER_MODE  = String(cfg.SERVER_MODE).toLowerCase();
  cfg.TUNNEL_PROVIDER = String(cfg.TUNNEL_PROVIDER).toLowerCase();

  return cfg;
}

/** Tulis .env baru — pertahankan komentar user kalau memungkinkan. */
function writeEnv(values, options = {}) {
  const merge = options.merge !== false;
  const existing = merge ? parseEnv(safeRead(ENV_FILE)) : {};
  const final = { ...existing, ...values };

  const banner = [
    "# TopZone Universal Launcher — file konfigurasi",
    "# ────────────────────────────────────────────",
    "# Dibuat otomatis oleh setup wizard / GUI.",
    "# Edit baris di bawah lalu restart server.",
    "# Pertanyaan? Lihat docs/PANDUAN.md",
    "",
  ];

  const groups = [
    {
      title: "# === NGROK / TUNNEL ===",
      keys : ["NGROK_AUTHTOKEN", "NGROK_DOMAIN", "TUNNEL_PROVIDER", "TUNNEL_FALLBACK", "CLOUDFLARED_TOKEN"],
    },
    {
      title: "# === SERVER LOKAL ===",
      keys : ["SERVER_MODE", "LOCAL_PORT", "PHP_PORT", "PHP_ROOT"],
    },
    {
      title: "# === GUI CONTROL PANEL ===",
      keys : ["GUI_PORT", "GUI_BIND", "GUI_PASSWORD", "GUI_AUTO_OPEN"],
    },
    {
      title: "# === KEAMANAN ===",
      keys : ["SEC_RATE_LIMIT", "SEC_BLOCK_ON_FAIL", "SEC_BLOCK_DURATION"],
    },
    {
      title: "# === LOG ===",
      keys : ["LOG_REQUESTS", "LOG_MIN_LEVEL", "LOG_RETENTION_DAYS"],
    },
    {
      title: "# === AUTO UPDATE ===",
      keys : ["AUTO_UPDATE", "UPDATE_BRANCH", "UPDATE_REMOTE"],
    },
  ];

  const out = [...banner];
  for (const grp of groups) {
    out.push(grp.title);
    for (const k of grp.keys) {
      const v = final[k] !== undefined ? final[k] : DEFAULTS[k];
      out.push(`${k}=${v}`);
    }
    out.push("");
  }

  // Keys di final tapi gak masuk grup → tambah di akhir
  const knownKeys = new Set(groups.flatMap((g) => g.keys));
  const extras = Object.keys(final).filter((k) => !knownKeys.has(k));
  if (extras.length) {
    out.push("# === LAINNYA ===");
    for (const k of extras) out.push(`${k}=${final[k]}`);
    out.push("");
  }

  safeWrite(ENV_FILE, out.join("\n"));
}

/** Tulis .env.example — versi tanpa value sensitif. */
function writeEnvExample() {
  const sample = { ...DEFAULTS };
  sample.NGROK_AUTHTOKEN = "isi_token_dari_dashboard.ngrok.com";
  sample.GUI_PASSWORD    = "isi_password_kuat_disini_atau_kosong";
  const out = [];
  out.push("# TopZone — contoh .env (copy ke .env lalu edit)");
  out.push("# Tidak akan ikut ke git (.gitignore sudah meng-exclude .env asli).");
  out.push("");
  for (const k of Object.keys(sample)) {
    out.push(`${k}=${sample[k]}`);
  }
  out.push("");
  safeWrite(ENV_SAMPLE, out.join("\n"));
}

/** Baca state runtime. */
function readState() {
  try {
    return JSON.parse(safeRead(STATE_FILE) || "{}");
  } catch (_) {
    return {};
  }
}

/** Tulis state runtime (atomic). */
function writeState(patch) {
  const cur = readState();
  const next = { ...cur, ...patch, updatedAt: new Date().toISOString() };
  safeWrite(STATE_FILE, JSON.stringify(next, null, 2));
  return next;
}

/** Reset state. */
function resetState() {
  try { fs.unlinkSync(STATE_FILE); } catch (_) {}
}

/** Apakah file .env sudah ada dan token sudah diisi. */
function isConfigured() {
  if (!fs.existsSync(ENV_FILE)) return false;
  const cfg = buildConfig();
  // Mode 'none' (no tunnel) tidak butuh token
  if (cfg.TUNNEL_PROVIDER === "none") return true;
  if (cfg.TUNNEL_PROVIDER === "ngrok") {
    return !!cfg.NGROK_AUTHTOKEN && !cfg.NGROK_AUTHTOKEN.includes("isi_token");
  }
  return true;
}

module.exports = {
  ROOT,
  ENV_FILE,
  STATE_FILE,
  DEFAULTS,
  parseEnv,
  applyEnv,
  loadEnv,
  buildConfig,
  writeEnv,
  writeEnvExample,
  readState,
  writeState,
  resetState,
  isConfigured,
};
