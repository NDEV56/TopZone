/**
 * lib/logger.js — Logger berkategori (6 level + security)
 * ───────────────────────────────────────────────────────
 * Kategori:
 *   common     — request normal, info biasa
 *   uncommon   — kejadian jarang (cache miss tinggi, tunnel switch, dll)
 *   warning    — sesuatu mungkin salah tapi belum fatal
 *   critical   — masalah berat, server bisa berhenti
 *   error      — error di runtime / exception
 *   security   — aktivitas mencurigakan, brute force, IP block
 *
 * Setiap kategori punya file log harian sendiri di logs/:
 *   logs/common-2026-05-08.log
 *   logs/security-2026-05-08.log
 *   ...
 *
 * Plus 1 file gabungan: logs/all-2026-05-08.log
 *
 * Logger juga emit event ('entry') sehingga GUI bisa stream live.
 */

"use strict";

const fs   = require("fs");
const path = require("path");
const { EventEmitter } = require("events");
const { c } = require("./colors");
const { ensureDir, dateStamp, timeStamp, safeName } = require("./utils");

const CATEGORIES = ["common", "uncommon", "warning", "critical", "error", "security"];

const LEVEL_COLOR = {
  common  : c.gray,
  uncommon: c.cyan,
  warning : c.yellow,
  critical: (s) => c.bold(c.red(s)),
  error   : c.red,
  security: c.magenta,
};

const LEVEL_ICON = {
  common  : "•",
  uncommon: "◆",
  warning : "⚠",
  critical: "‼",
  error   : "✖",
  security: "🛡",
};

const LEVEL_PRIORITY = {
  common  : 0,
  uncommon: 1,
  warning : 2,
  error   : 3,
  critical: 4,
  security: 5,
};

class Logger extends EventEmitter {
  constructor(options = {}) {
    super();
    this.setMaxListeners(50);

    this.dir          = options.dir || path.join(process.cwd(), "logs");
    this.maxBufferLen = options.maxBufferLen || 500;
    this.echoConsole  = options.echoConsole !== false;
    this.minLevel     = options.minLevel || "common";
    this.maxFileBytes = options.maxFileBytes || 5 * 1024 * 1024; // 5 MB
    this.appName      = options.appName || "TopZone";

    this.streams = {};
    this.buffer  = [];     // entri terbaru untuk GUI / live tail
    this._counters = {};
    for (const cat of CATEGORIES) this._counters[cat] = 0;

    ensureDir(this.dir);
    this._openStreams();
    this._installCloseHandler();
  }

  _logFile(category, date = dateStamp()) {
    return path.join(this.dir, `${safeName(category)}-${date}.log`);
  }

  _allFile(date = dateStamp()) {
    return path.join(this.dir, `all-${date}.log`);
  }

  _openStreams() {
    this._closeStreams();
    for (const cat of CATEGORIES) {
      this.streams[cat] = fs.createWriteStream(this._logFile(cat), { flags: "a" });
      this.streams[cat].on("error", (e) => {
        // Logger gak boleh crash app
        process.stderr.write(`[logger] gagal tulis ${cat}: ${e.message}\n`);
      });
    }
    this.streams.all = fs.createWriteStream(this._allFile(), { flags: "a" });
    this.streams.all.on("error", () => {});
    this._currentDate = dateStamp();
  }

  _closeStreams() {
    for (const k of Object.keys(this.streams)) {
      try { this.streams[k].end(); } catch (_) {}
      delete this.streams[k];
    }
  }

  _rotateIfNeeded() {
    const today = dateStamp();
    if (today !== this._currentDate) this._openStreams();

    // Rotasi by-size
    for (const cat of CATEGORIES) {
      const f = this._logFile(cat);
      try {
        const st = fs.statSync(f);
        if (st.size > this.maxFileBytes) {
          this.streams[cat].end();
          const archive = f + "." + Date.now() + ".bak";
          fs.renameSync(f, archive);
          this.streams[cat] = fs.createWriteStream(f, { flags: "a" });
        }
      } catch (_) {}
    }
  }

  _installCloseHandler() {
    const flushAndExit = () => {
      try { this.flush(); } catch (_) {}
      this._closeStreams();
    };
    process.once("exit", flushAndExit);
  }

  _shouldEmit(level) {
    return (LEVEL_PRIORITY[level] ?? 0) >= (LEVEL_PRIORITY[this.minLevel] ?? 0);
  }

  /** Tulis log entry ke file & buffer + emit event. */
  log(level, message, meta = {}) {
    if (!CATEGORIES.includes(level)) level = "common";
    if (!this._shouldEmit(level)) return;

    this._rotateIfNeeded();
    this._counters[level]++;

    const ts    = timeStamp();
    const entry = { ts, level, message: String(message), meta };

    // 1) Buffer (untuk GUI)
    this.buffer.push(entry);
    if (this.buffer.length > this.maxBufferLen) {
      this.buffer.splice(0, this.buffer.length - this.maxBufferLen);
    }

    // 2) Tulis ke file
    const line = JSON.stringify(entry) + "\n";
    try {
      this.streams[level]?.write(line);
      this.streams.all?.write(line);
    } catch (_) {}

    // 3) Echo ke console
    if (this.echoConsole) this._echo(entry);

    // 4) Emit untuk listener (GUI WebSocket/SSE)
    this.emit("entry", entry);
  }

  _echo(entry) {
    const colorFn = LEVEL_COLOR[entry.level] || ((s) => s);
    const icon    = LEVEL_ICON[entry.level] || "•";
    const tag     = colorFn(`[${entry.level.toUpperCase().padEnd(8)}]`);
    const tsShort = entry.ts.slice(11, 19);
    let metaPart  = "";
    if (entry.meta && Object.keys(entry.meta).length) {
      try {
        const flat = Object.entries(entry.meta)
          .map(([k, v]) => `${k}=${truncForLog(v)}`)
          .join(" ");
        metaPart = c.gray("  " + flat);
      } catch (_) {}
    }
    const stream = (entry.level === "error" || entry.level === "critical")
      ? process.stderr : process.stdout;
    stream.write(`${c.gray(tsShort)} ${icon} ${tag} ${entry.message}${metaPart}\n`);
  }

  // Convenience methods
  common  (msg, meta) { this.log("common",   msg, meta); }
  uncommon(msg, meta) { this.log("uncommon", msg, meta); }
  warn    (msg, meta) { this.log("warning",  msg, meta); }
  warning (msg, meta) { this.log("warning",  msg, meta); }
  error   (msg, meta) { this.log("error",    msg, meta); }
  critical(msg, meta) { this.log("critical", msg, meta); }
  security(msg, meta) { this.log("security", msg, meta); }
  info    (msg, meta) { this.log("common",   msg, meta); }

  /** Ambil buffer terakhir (untuk GUI initial load). */
  recent(count = 100, levelFilter = null) {
    let arr = this.buffer;
    if (levelFilter && levelFilter !== "all") {
      arr = arr.filter((e) => e.level === levelFilter);
    }
    return arr.slice(-count);
  }

  /** Statistik counter per kategori. */
  stats() {
    return { ...this._counters, total: Object.values(this._counters).reduce((a, b) => a + b, 0) };
  }

  /** Reset counter dan buffer. */
  reset() {
    for (const k of Object.keys(this._counters)) this._counters[k] = 0;
    this.buffer = [];
  }

  /** Flush ke disk (best effort). */
  flush() {
    for (const k of Object.keys(this.streams)) {
      try { this.streams[k]._writableState && this.streams[k].cork && this.streams[k].uncork(); }
      catch (_) {}
    }
  }

  /** Read file log lama (untuk panel "history" di GUI). */
  readArchive(level, date = dateStamp(), maxLines = 500) {
    const file = this._logFile(level, date);
    if (!fs.existsSync(file)) return [];
    try {
      const raw = fs.readFileSync(file, "utf8");
      const lines = raw.split("\n").filter(Boolean);
      const slice = lines.slice(-maxLines);
      return slice.map((l) => {
        try { return JSON.parse(l); }
        catch (_) { return { ts: "?", level, message: l, meta: {} }; }
      });
    } catch (_) { return []; }
  }

  /** Daftar tanggal log yang ada. */
  listArchiveDates() {
    try {
      const files = fs.readdirSync(this.dir);
      const dates = new Set();
      for (const f of files) {
        const m = f.match(/-(\d{4}-\d{2}-\d{2})\.log$/);
        if (m) dates.add(m[1]);
      }
      return Array.from(dates).sort().reverse();
    } catch (_) { return []; }
  }

  /** Bersihkan log lama (lebih dari `days` hari). */
  prune(days = 30) {
    let removed = 0;
    try {
      const files = fs.readdirSync(this.dir);
      const cutoff = Date.now() - days * 24 * 3600 * 1000;
      for (const f of files) {
        const full = path.join(this.dir, f);
        try {
          const st = fs.statSync(full);
          if (st.mtimeMs < cutoff) {
            fs.unlinkSync(full);
            removed++;
          }
        } catch (_) {}
      }
    } catch (_) {}
    return removed;
  }
}

function truncForLog(v) {
  try {
    const s = typeof v === "string" ? v : JSON.stringify(v);
    return s.length > 80 ? s.slice(0, 79) + "…" : s;
  } catch (_) { return String(v); }
}

module.exports = { Logger, CATEGORIES, LEVEL_COLOR, LEVEL_ICON };
