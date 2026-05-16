/**
 * lib/updater.js — Auto-updater dari GitHub
 * ─────────────────────────────────────────
 * Strategi:
 *   1. Cek apakah folder ini git repo (.git ada).
 *   2. Cek `git fetch origin` lalu bandingkan HEAD lokal vs remote.
 *   3. Kalau ada commit baru, tampilkan ringkasan + minta konfirmasi
 *      (kecuali AUTO_UPDATE=true → langsung tarik).
 *   4. Sebelum pull: backup .env, package.json, file penting → folder backups/.
 *   5. Lakukan `git stash --include-untracked` untuk save perubahan lokal.
 *   6. `git pull --ff-only origin <branch>` (no rebase, no merge commit).
 *   7. Kalau gagal (konflik), rollback dengan `git reset --hard <prev>`.
 *   8. Setelah pull: jalankan `npm install` kalau package.json berubah.
 *   9. Tulis update-log.txt.
 *
 * Tidak akan menyentuh file .env user — di-stash atau di-skip.
 */

"use strict";

const fs   = require("fs");
const path = require("path");
const { execSync, spawnSync } = require("child_process");
const { ensureDir, dateStamp, timeStamp, fileHash } = require("./utils");

class Updater {
  constructor(cfg, logger) {
    this.cfg     = cfg;
    this.logger  = logger;
    this.root    = path.resolve(__dirname, "..");
    this.backups = path.join(this.root, "backups");
  }

  isGitRepo() {
    return fs.existsSync(path.join(this.root, ".git"));
  }

  _git(args, opts = {}) {
    return spawnSync("git", args, {
      cwd: this.root,
      encoding: "utf8",
      timeout: opts.timeout || 30000,
      shell: false,
    });
  }

  /** Ambil hash HEAD lokal & nama branch. */
  status() {
    if (!this.isGitRepo()) {
      return { isRepo: false };
    }
    try {
      const head     = this._git(["rev-parse", "HEAD"]).stdout.trim();
      const branch   = this._git(["rev-parse", "--abbrev-ref", "HEAD"]).stdout.trim();
      const remote   = this._git(["remote", "get-url", this.cfg.UPDATE_REMOTE]).stdout.trim();
      const upstream = this._git(["rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{u}"]).stdout.trim();
      const dirty    = this._git(["status", "--porcelain"]).stdout.trim();
      return { isRepo: true, head, branch, remote, upstream, dirty: !!dirty };
    } catch (e) {
      return { isRepo: false, error: e.message };
    }
  }

  /** Cek update tanpa pull. Return { hasUpdate, behind, ahead, summary }. */
  async check() {
    if (!this.isGitRepo()) {
      return { available: false, reason: "Folder bukan git repository." };
    }

    const fetch = this._git(["fetch", this.cfg.UPDATE_REMOTE, this.cfg.UPDATE_BRANCH], { timeout: 20000 });
    if (fetch.status !== 0) {
      this.logger.warning("git fetch gagal: " + (fetch.stderr || "").trim());
      return { available: false, reason: "git fetch gagal: " + (fetch.stderr || "").trim() };
    }

    const local  = this._git(["rev-parse", "HEAD"]).stdout.trim();
    const remote = this._git(["rev-parse", `${this.cfg.UPDATE_REMOTE}/${this.cfg.UPDATE_BRANCH}`]).stdout.trim();

    if (local === remote) {
      return { available: false, reason: "Sudah versi terbaru.", local, remote };
    }

    // Hitung berapa commit di belakang
    const behindOut = this._git(["rev-list", "--count", `HEAD..${this.cfg.UPDATE_REMOTE}/${this.cfg.UPDATE_BRANCH}`]);
    const aheadOut  = this._git(["rev-list", "--count", `${this.cfg.UPDATE_REMOTE}/${this.cfg.UPDATE_BRANCH}..HEAD`]);
    const behind = parseInt(behindOut.stdout.trim(), 10) || 0;
    const ahead  = parseInt(aheadOut.stdout.trim(),  10) || 0;

    const log = this._git(["log", "--oneline", `HEAD..${this.cfg.UPDATE_REMOTE}/${this.cfg.UPDATE_BRANCH}`,
                            "--no-merges", "-n", "20"]);
    const summary = log.stdout.trim().split("\n").filter(Boolean);

    return {
      available: true,
      local,
      remote,
      behind,
      ahead,
      summary,
      branch: this.cfg.UPDATE_BRANCH,
    };
  }

  /** Backup file-file penting sebelum pull. */
  backup() {
    ensureDir(this.backups);
    const stamp = dateStamp() + "_" + Date.now();
    const dest  = path.join(this.backups, stamp);
    ensureDir(dest);

    const items = [".env", "package.json", "package-lock.json", "server.js", "gui.js",
                   ".topzone-state.json"];
    const saved = [];
    for (const it of items) {
      const src = path.join(this.root, it);
      if (fs.existsSync(src)) {
        try {
          fs.copyFileSync(src, path.join(dest, it.replace(/[\\/]/g, "_")));
          saved.push(it);
        } catch (e) {
          this.logger.warning(`Gagal backup ${it}: ${e.message}`);
        }
      }
    }
    // Backup folder lib/ kalau ada
    const libSrc = path.join(this.root, "lib");
    if (fs.existsSync(libSrc)) {
      const libDst = path.join(dest, "lib");
      ensureDir(libDst);
      for (const f of fs.readdirSync(libSrc)) {
        try { fs.copyFileSync(path.join(libSrc, f), path.join(libDst, f)); }
        catch (_) {}
      }
      saved.push("lib/");
    }

    this.logger.uncommon(`Backup tersimpan di backups/${stamp}/ (${saved.length} item)`);
    return { dest, saved };
  }

  /** Lakukan update penuh. Return { ok, message, before, after }. */
  async pull(options = {}) {
    if (!this.isGitRepo()) {
      return { ok: false, message: "Folder bukan git repository." };
    }

    const before = this.status();
    if (before.dirty && !options.allowDirty) {
      this.logger.warning("Working tree kotor — stash dulu.");
      const stash = this._git(["stash", "push", "--include-untracked", "-m",
                               `topzone-autoupdate-${dateStamp()}`]);
      if (stash.status !== 0) {
        return { ok: false, message: "Gagal stash perubahan lokal: " + stash.stderr };
      }
    }

    const backup = this.backup();
    const prevHead = this._git(["rev-parse", "HEAD"]).stdout.trim();

    // Update package.json hash sebelum pull (untuk cek apakah deps berubah)
    const pkgBefore = fileHash(path.join(this.root, "package.json"));

    const pull = this._git(["pull", "--ff-only", this.cfg.UPDATE_REMOTE, this.cfg.UPDATE_BRANCH], {
      timeout: 60000,
    });

    if (pull.status !== 0) {
      const errMsg = (pull.stderr || pull.stdout || "").trim();
      this.logger.error("git pull gagal: " + errMsg);

      // Rollback (HEAD seharusnya sudah di tempat aman karena --ff-only)
      this.logger.warning("Mencoba rollback ke " + prevHead.slice(0, 8));
      this._git(["reset", "--hard", prevHead]);
      return { ok: false, message: "git pull gagal: " + errMsg, backup };
    }

    const after = this.status();
    const pkgAfter = fileHash(path.join(this.root, "package.json"));
    const depsChanged = pkgBefore && pkgAfter && pkgBefore !== pkgAfter;

    if (depsChanged) {
      this.logger.uncommon("package.json berubah — menjalankan npm install...");
      const npm = spawnSync(process.platform === "win32" ? "npm.cmd" : "npm",
                            ["install", "--no-audit", "--no-fund"],
                            { cwd: this.root, encoding: "utf8", timeout: 180000 });
      if (npm.status !== 0) {
        this.logger.warning("npm install ada warning/error: " + (npm.stderr || "").slice(0, 500));
      } else {
        this.logger.common("npm install selesai.");
      }
    }

    // Tulis update log
    const logFile = path.join(this.root, "logs", "update-log.txt");
    ensureDir(path.dirname(logFile));
    fs.appendFileSync(logFile,
      `${timeStamp()} | ${prevHead.slice(0, 8)} → ${after.head.slice(0, 8)} ` +
      `| branch=${after.branch} | depsChanged=${depsChanged ? "yes" : "no"}\n`);

    return {
      ok: true,
      message: `Update sukses: ${prevHead.slice(0, 8)} → ${after.head.slice(0, 8)}`,
      before, after, backup, depsChanged,
    };
  }

  /** Rollback ke commit sebelumnya (manual). */
  rollback(commit) {
    if (!this.isGitRepo()) return { ok: false, message: "Bukan git repo." };
    if (!commit) return { ok: false, message: "Hash commit dibutuhkan." };
    const r = this._git(["reset", "--hard", commit]);
    return r.status === 0
      ? { ok: true,  message: `Rollback ke ${commit.slice(0, 8)}.` }
      : { ok: false, message: "Gagal rollback: " + r.stderr };
  }

  /** List backup yang tersedia. */
  listBackups() {
    if (!fs.existsSync(this.backups)) return [];
    try {
      return fs.readdirSync(this.backups)
        .filter((f) => fs.statSync(path.join(this.backups, f)).isDirectory())
        .sort().reverse();
    } catch (_) { return []; }
  }

  /** Jadwalkan cek otomatis (interval ms). */
  scheduleCheck(intervalMs, onUpdate) {
    return setInterval(async () => {
      try {
        const res = await this.check();
        if (res.available) {
          this.logger.uncommon(`Update tersedia: ${res.behind} commit baru di ${res.branch}.`);
          if (onUpdate) onUpdate(res);
        }
      } catch (_) {}
    }, intervalMs);
  }
}

module.exports = { Updater };
