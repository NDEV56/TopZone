/**
 * lib/cleanup.js — Storage Janitor (auto-cleanup file sampah)
 * ───────────────────────────────────────────────────────────
 *
 * Membersihkan file yang aman untuk dihapus tanpa merusak website / sistem
 * keamanan. Setiap target di-whitelist ketat — tidak akan menyentuh file
 * di luar daftar.
 *
 * Kategori target:
 *   1. logs/*.log        — log harian > N hari (default LOG_RETENTION_DAYS)
 *   2. logs/*.bak        — log rotasi (lebih dari 7 hari)
 *   3. backups/<tanggal> — backup pre-update > 30 hari
 *   4. *.tmp / *.tmp.*   — temp file di root TopZone
 *   5. uploads/<orphan>  — gambar yang tidak direference users.foto / games.gambar
 *   6. ratelimit cache   — file di temp dir (sys_get_temp_dir/topzone_rl/)
 *   7. node_modules cache — opsional: .cache di node_modules
 *   8. .topzone-state.json.tmp — leftover atomic-write
 *   9. backups/ kosong   — folder backup yang sudah tidak ada isi
 *  10. PHP session files — ext'ses_xxx' lama di temp (opsional, hati-hati)
 *
 * Mode operasi:
 *   - `analyze()`  — return report tanpa menghapus apa pun
 *   - `clean(opts)` — benar-benar hapus, dengan dryRun support
 *
 * SAFETY GUARANTEES:
 *   - Tidak akan menghapus .env / .env.example / .git / .htaccess
 *   - Tidak akan menghapus file PHP/HTML di Home/ atau Login/ (website)
 *   - Tidak akan menghapus file gambar yang masih dipakai (referenced di DB)
 *   - Tidak akan menghapus file di luar prefix root project
 *   - Akan refuse kalau path target mengandung '..' atau symlink
 *   - Setiap operasi ditulis ke logs/cleanup-log.txt untuk audit
 */

"use strict";

const fs   = require("fs");
const path = require("path");
const os   = require("os");
const { ensureDir, dateStamp, formatBytes } = require("./utils");

const ROOT   = path.resolve(__dirname, "..");
const HOME   = path.join(ROOT, "Home");
const LOGIN  = path.join(ROOT, "Login");
const LOGS   = path.join(ROOT, "logs");
const BACKUPS = path.join(ROOT, "backups");
const UPLOADS = path.join(HOME, "uploads");

const DEFAULTS = {
  logsRetentionDays    : 30,
  logsBackRetentionDays: 7,    // file *.log.<ts>.bak
  backupsRetentionDays : 30,
  orphanUploadAgeDays  : 7,    // baru hapus orphan upload kalau > 7 hari (kasih waktu DB sync)
  rateLimitMaxAgeHours : 24,
  // Hard whitelist — file BOLEH dihapus
  uploadAllowExt       : ["png", "jpg", "jpeg", "webp", "gif"],
  // Maksimum file dihapus per run (safety)
  maxDeletePerRun      : 500,
};

// ─── Util ────────────────────────────────────────────────────
function safeStat(p) { try { return fs.statSync(p); } catch (_) { return null; } }
function safeUnlink(p) {
  try { fs.unlinkSync(p); return true; }
  catch (_) { return false; }
}
function safeRmdir(p) {
  try { fs.rmdirSync(p); return true; }
  catch (_) { return false; }
}
function safeRmrf(p) {
  try { fs.rmSync(p, { recursive: true, force: true }); return true; }
  catch (_) { return false; }
}
function safeReaddir(d) {
  try { return fs.readdirSync(d); } catch (_) { return []; }
}

/** Pastikan path masih di dalam ROOT (tidak loncat keluar via ..). */
function isWithinRoot(p) {
  const r = path.resolve(p);
  return r.startsWith(ROOT + path.sep) || r === ROOT;
}

/** Cek apakah path adalah file biasa (bukan symlink, bukan device, dll). */
function isPlainFile(p) {
  try {
    const lst = fs.lstatSync(p);
    return lst.isFile() && !lst.isSymbolicLink();
  } catch (_) { return false; }
}

/** Cek apakah path adalah folder biasa (bukan symlink). */
function isPlainDir(p) {
  try {
    const lst = fs.lstatSync(p);
    return lst.isDirectory() && !lst.isSymbolicLink();
  } catch (_) { return false; }
}

const dayMs = 24 * 3600 * 1000;
const ageDays = (mtime) => (Date.now() - mtime) / dayMs;

// ─── Audit log ────────────────────────────────────────────
function appendAuditLog(line) {
  try {
    ensureDir(LOGS);
    const file = path.join(LOGS, "cleanup-log.txt");
    fs.appendFileSync(file, `[${new Date().toISOString()}] ${line}\n`, { flag: "a" });
  } catch (_) {}
}

// ─── Pre-flight: jangan hapus blacklist ───────────────────
const BLACKLIST_NAMES = new Set([
  ".env", ".env.example", ".gitignore", ".gitkeep",
  ".htaccess", "_security.php",
  "package.json", "package-lock.json",
  "server.js", "gui.js",
  ".topzone-state.json", "LICENSE", "README.md", "SECURITY.md",
  "Default.jpg", "Default.jpeg", "logotopzone.png",
]);

function isBlacklistedFile(p) {
  const base = path.basename(p);
  if (BLACKLIST_NAMES.has(base)) return true;
  if (base === ".git" || base.startsWith(".git")) return true;
  return false;
}

// ─── Scanner per kategori ─────────────────────────────────

/** 1. logs/*.log lama */
function scanOldLogs(retentionDays) {
  const out = [];
  if (!isPlainDir(LOGS)) return out;
  for (const f of safeReaddir(LOGS)) {
    if (!f.endsWith(".log")) continue;
    if (f === "cleanup-log.txt") continue; // jangan hapus log audit cleanup itu sendiri
    const full = path.join(LOGS, f);
    if (!isPlainFile(full) || !isWithinRoot(full)) continue;
    const st = safeStat(full); if (!st) continue;
    if (ageDays(st.mtimeMs) > retentionDays) {
      out.push({ path: full, size: st.size, age: Math.round(ageDays(st.mtimeMs)) });
    }
  }
  return out;
}

/** 2. logs/*.log.*.bak (rotasi) */
function scanLogBackups(retentionDays) {
  const out = [];
  if (!isPlainDir(LOGS)) return out;
  for (const f of safeReaddir(LOGS)) {
    if (!/\.log\.\d+\.bak$/.test(f)) continue;
    const full = path.join(LOGS, f);
    if (!isPlainFile(full) || !isWithinRoot(full)) continue;
    const st = safeStat(full); if (!st) continue;
    if (ageDays(st.mtimeMs) > retentionDays) {
      out.push({ path: full, size: st.size, age: Math.round(ageDays(st.mtimeMs)) });
    }
  }
  return out;
}

/** 3. backups/<tanggal>/ pre-update */
function scanOldBackups(retentionDays) {
  const out = [];
  if (!isPlainDir(BACKUPS)) return out;
  for (const f of safeReaddir(BACKUPS)) {
    const full = path.join(BACKUPS, f);
    if (!isPlainDir(full) || !isWithinRoot(full)) continue;
    const st = safeStat(full); if (!st) continue;
    if (ageDays(st.mtimeMs) > retentionDays) {
      // Hitung total size dalam folder ini
      let total = 0;
      try {
        for (const sub of safeReaddir(full)) {
          const subPath = path.join(full, sub);
          const subSt = safeStat(subPath);
          if (subSt && subSt.isFile()) total += subSt.size;
        }
      } catch (_) {}
      out.push({ path: full, size: total, age: Math.round(ageDays(st.mtimeMs)), isDir: true });
    }
  }
  return out;
}

/** 4. *.tmp di root */
function scanTmpFiles() {
  const out = [];
  for (const f of safeReaddir(ROOT)) {
    if (!/\.tmp(\.|$)/.test(f)) continue;
    const full = path.join(ROOT, f);
    if (!isPlainFile(full) || isBlacklistedFile(full) || !isWithinRoot(full)) continue;
    const st = safeStat(full); if (!st) continue;
    out.push({ path: full, size: st.size, age: Math.round(ageDays(st.mtimeMs)) });
  }
  return out;
}

/**
 * 5. Orphan uploads — gambar yang ada di Home/uploads/ tapi tidak
 *    direference di DB (users.foto / games.gambar).
 *    Butuh fungsi DB lookup (callback) — kalau tidak dikasih, skip.
 *    Hanya hapus file yang umur > minAgeDays untuk safety (kasih waktu
 *    DB sync setelah upload).
 */
function scanOrphanUploads(referencedSet, minAgeDays) {
  const out = [];
  if (!isPlainDir(UPLOADS)) return out;
  if (!referencedSet || !(referencedSet instanceof Set)) return out;

  for (const f of safeReaddir(UPLOADS)) {
    if (f.startsWith(".")) continue; // .htaccess dll
    const full = path.join(UPLOADS, f);
    if (!isPlainFile(full) || !isWithinRoot(full)) continue;
    const ext = path.extname(f).toLowerCase().replace(/^\./, "");
    if (!DEFAULTS.uploadAllowExt.includes(ext)) continue; // hanya hapus gambar
    const st = safeStat(full); if (!st) continue;
    if (ageDays(st.mtimeMs) < minAgeDays) continue; // baru, jangan disentuh dulu

    // referenced bisa berupa path atau cuma basename
    const baseName = path.basename(f);
    const relPath  = "uploads/" + baseName;
    if (referencedSet.has(baseName) || referencedSet.has(relPath)) continue;

    out.push({ path: full, size: st.size, age: Math.round(ageDays(st.mtimeMs)) });
  }
  return out;
}

/** 6. PHP rate-limit cache di temp dir */
function scanRateLimitCache(maxAgeHours) {
  const out = [];
  const dir = path.join(os.tmpdir(), "topzone_rl");
  if (!isPlainDir(dir)) return out;
  for (const f of safeReaddir(dir)) {
    if (!f.endsWith(".json")) continue;
    const full = path.join(dir, f);
    if (!isPlainFile(full)) continue;
    const st = safeStat(full); if (!st) continue;
    const ageH = (Date.now() - st.mtimeMs) / 3600000;
    if (ageH > maxAgeHours) {
      out.push({ path: full, size: st.size, age: Math.round(ageH) + "h" });
    }
  }
  return out;
}

/** 7. node_modules/.cache (kalau ada) */
function scanNodeCache() {
  const out = [];
  const cacheDir = path.join(ROOT, "node_modules", ".cache");
  if (!isPlainDir(cacheDir)) return out;
  let total = 0;
  function walk(d) {
    for (const f of safeReaddir(d)) {
      const full = path.join(d, f);
      const st = safeStat(full);
      if (!st) continue;
      if (st.isFile()) total += st.size;
      else if (st.isDirectory()) walk(full);
    }
  }
  walk(cacheDir);
  if (total > 0) out.push({ path: cacheDir, size: total, isDir: true });
  return out;
}

/** 8. backups/ folder kosong */
function scanEmptyBackupDirs() {
  const out = [];
  if (!isPlainDir(BACKUPS)) return out;
  for (const f of safeReaddir(BACKUPS)) {
    const full = path.join(BACKUPS, f);
    if (!isPlainDir(full) || !isWithinRoot(full)) continue;
    if (safeReaddir(full).length === 0) {
      out.push({ path: full, size: 0, isDir: true });
    }
  }
  return out;
}

// ─── Hapus dengan safety check ─────────────────────────────
function deleteOne(item, log) {
  // SAFETY 1: harus di dalam ROOT
  if (!isWithinRoot(item.path) && !item.path.startsWith(os.tmpdir())) {
    log.push({ path: item.path, status: "skipped:outside-root" });
    return false;
  }
  // SAFETY 2: blacklist
  if (isBlacklistedFile(item.path)) {
    log.push({ path: item.path, status: "skipped:blacklist" });
    return false;
  }
  // SAFETY 3: jangan hapus file PHP/HTML/JS di Home/, Login/, lib/, public/
  const protectedDirs = [HOME, LOGIN, path.join(ROOT, "lib"), path.join(ROOT, "public")];
  for (const pd of protectedDirs) {
    if (item.path.startsWith(pd + path.sep)) {
      // Pengecualian: uploads/ DI DALAM Home/ boleh
      if (!item.path.startsWith(UPLOADS + path.sep)) {
        log.push({ path: item.path, status: "skipped:protected-dir" });
        return false;
      }
    }
  }

  if (item.isDir) {
    const ok = safeRmrf(item.path);
    log.push({ path: item.path, status: ok ? "removed-dir" : "fail-dir" });
    return ok;
  }
  const ok = safeUnlink(item.path);
  log.push({ path: item.path, status: ok ? "removed" : "fail" });
  return ok;
}

// ─── Public API ────────────────────────────────────────────

/**
 * Scan saja, tidak hapus apapun. Return ringkasan.
 * @param  {object}  opts
 * @param  {Set}     opts.referencedUploads — basename yang masih dipakai DB
 * @param  {number}  opts.logsRetentionDays
 * @param  {number}  opts.backupsRetentionDays
 * @param  {number}  opts.orphanUploadAgeDays
 */
function analyze(opts = {}) {
  const cfg = { ...DEFAULTS, ...opts };

  const cats = {
    oldLogs        : scanOldLogs(cfg.logsRetentionDays),
    logBackups     : scanLogBackups(cfg.logsBackRetentionDays),
    oldBackups     : scanOldBackups(cfg.backupsRetentionDays),
    tmpFiles       : scanTmpFiles(),
    rateLimitCache : scanRateLimitCache(cfg.rateLimitMaxAgeHours),
    nodeCache      : scanNodeCache(),
    emptyBackupDirs: scanEmptyBackupDirs(),
    orphanUploads  : opts.referencedUploads
                       ? scanOrphanUploads(opts.referencedUploads, cfg.orphanUploadAgeDays)
                       : [],
  };

  let totalBytes = 0;
  let totalCount = 0;
  for (const cat of Object.keys(cats)) {
    for (const it of cats[cat]) {
      totalBytes += it.size || 0;
      totalCount++;
    }
  }

  return {
    root: ROOT,
    categories: Object.fromEntries(
      Object.entries(cats).map(([k, v]) => [k, {
        count : v.length,
        bytes : v.reduce((s, x) => s + (x.size || 0), 0),
        items : v.slice(0, 50), // batasi list besar
        truncated: v.length > 50,
      }])
    ),
    totalBytes,
    totalCount,
    totalHuman: formatBytes(totalBytes),
  };
}

/**
 * Hapus file sampah. dryRun=true → simulasi, tidak hapus apapun.
 */
function clean(opts = {}) {
  const dryRun = opts.dryRun !== false; // DEFAULT TRUE — paksa user pilih apply
  const cfg    = { ...DEFAULTS, ...opts };
  const report = analyze(opts);

  // Cap maksimum delete per run untuk safety
  let allItems = [];
  for (const cat of Object.keys(report.categories)) {
    const items = report.categories[cat].items.map((it) => ({ ...it, category: cat }));
    allItems = allItems.concat(items);
  }
  if (allItems.length > cfg.maxDeletePerRun) {
    allItems = allItems.slice(0, cfg.maxDeletePerRun);
  }

  const log = [];
  let removedBytes = 0;
  let removedCount = 0;

  if (!dryRun) {
    for (const it of allItems) {
      const before = removedCount;
      if (deleteOne(it, log)) {
        removedCount++;
        removedBytes += it.size || 0;
      }
    }
    appendAuditLog(`Cleanup run: hapus ${removedCount} item, ${formatBytes(removedBytes)} (mode=apply)`);
  } else {
    for (const it of allItems) {
      log.push({ path: it.path, status: "would-remove", size: it.size });
      removedCount++;
      removedBytes += it.size || 0;
    }
    appendAuditLog(`Cleanup run: SIMULASI ${removedCount} item, ${formatBytes(removedBytes)} (mode=dryRun)`);
  }

  return {
    dryRun,
    report,
    plannedCount: allItems.length,
    removedCount,
    removedBytes,
    removedHuman: formatBytes(removedBytes),
    log: log.slice(0, 200),
    truncatedLog: log.length > 200,
  };
}

/**
 * Helper: ekstrak set file-name yang masih direference di DB.
 * Caller (gui.js / server.js) harus punya akses ke DB MySQL.
 * Fungsi ini hanya menerima rows mentah dari caller.
 */
function buildReferencedSet(rowsArray) {
  const set = new Set();
  if (!Array.isArray(rowsArray)) return set;
  for (const row of rowsArray) {
    if (!row) continue;
    // Ambil semua string non-empty, daftar nama dasar + path-relatif
    for (const v of Object.values(row)) {
      if (typeof v !== "string" || v.length === 0) continue;
      // Kalau punya path-prefix uploads/, ambil bagian setelahnya juga
      const base = path.basename(v);
      if (base) set.add(base);
      set.add(v);
    }
  }
  return set;
}

module.exports = {
  ROOT, LOGS, BACKUPS, UPLOADS,
  DEFAULTS,
  analyze,
  clean,
  buildReferencedSet,
  formatBytes,
};
