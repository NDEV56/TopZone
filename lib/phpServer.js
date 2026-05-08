/**
 * lib/phpServer.js — Bungkus PHP built-in server
 * ──────────────────────────────────────────────
 * Spawn `php -S 127.0.0.1:<port> -t <root>` dan tunggu siap.
 * Cocok untuk yang belum install XAMPP/Laragon tapi sudah punya PHP di PATH.
 */

"use strict";

const { spawn, execSync } = require("child_process");
const fs   = require("fs");
const path = require("path");
const { isPortOpen, findFreePort, sleep } = require("./utils");

class PhpServer {
  constructor(cfg, logger) {
    this.cfg     = cfg;
    this.logger  = logger;
    this.process = null;
    this.port    = null;
    this.root    = cfg.PHP_ROOT;
  }

  hasPhp() {
    try {
      execSync("php -v", { stdio: "ignore", timeout: 3000 });
      return true;
    } catch (_) { return false; }
  }

  phpVersion() {
    try {
      return execSync("php -r \"echo PHP_VERSION;\"", { timeout: 3000, encoding: "utf8" }).trim();
    } catch (_) { return null; }
  }

  validateRoot() {
    if (!fs.existsSync(this.root)) {
      const e = new Error(`Folder PHP tidak ada: ${this.root}`);
      e.tip = "Edit PHP_ROOT di .env ke path folder project PHP-mu.";
      throw e;
    }
    const stat = fs.statSync(this.root);
    if (!stat.isDirectory()) {
      throw new Error(`PHP_ROOT bukan folder: ${this.root}`);
    }
  }

  async start() {
    if (!this.hasPhp()) {
      const e = new Error("Perintah 'php' tidak ditemukan di PATH.");
      e.tip = process.platform === "win32"
        ? "Install XAMPP/Laragon, lalu tambahkan folder PHP-nya ke PATH (mis. C:\\xampp\\php)."
        : "Install PHP: sudo apt install php (Linux) atau brew install php (Mac).";
      throw e;
    }

    this.validateRoot();

    // Port: pakai cfg.PHP_PORT, kalau dipakai → cari yang kosong
    let port = this.cfg.PHP_PORT;
    if (await isPortOpen(port)) {
      this.logger.uncommon(`Port ${port} sudah dipakai, cari port kosong...`);
      port = await findFreePort(port + 1);
      this.logger.common(`PHP akan pakai port ${port}.`);
    }
    this.port = port;

    return new Promise((resolve, reject) => {
      const args = ["-S", `127.0.0.1:${port}`, "-t", this.root];
      this.logger.common(`Spawn: php ${args.join(" ")}`);
      this.process = spawn("php", args, {
        stdio: ["ignore", "pipe", "pipe"],
        shell: process.platform === "win32",
        cwd: this.root,
      });

      this.process.stdout.on("data", (d) => this._onPhpLine(d.toString(), "stdout"));
      this.process.stderr.on("data", (d) => this._onPhpLine(d.toString(), "stderr"));

      this.process.on("error", (err) => {
        this.logger.error(`PHP spawn error: ${err.message}`);
        reject(err);
      });
      this.process.on("exit", (code, sig) => {
        if (code && code !== 0) {
          this.logger.error(`PHP exit code ${code} (${sig || "?"})`);
        }
      });

      // Polling sampai port aktif (max 6s)
      const start = Date.now();
      const tick = async () => {
        if (await isPortOpen(port)) {
          resolve({ port, process: this.process });
          return;
        }
        if (Date.now() - start > 6000) {
          this.process.kill("SIGTERM");
          reject(new Error("PHP server tidak siap dalam 6 detik."));
          return;
        }
        setTimeout(tick, 200);
      };
      tick();
    });
  }

  _onPhpLine(line, source) {
    line = line.trim();
    if (!line) return;
    // Format request log PHP built-in: [tanggal] 127.0.0.1:port [200]: GET /
    const reqMatch = line.match(/^\[.+?\]\s*\[(\d{3})\]\s*:\s*(\S+)\s+(\S+)/);
    if (reqMatch) {
      const status = parseInt(reqMatch[1], 10);
      const lvl = status >= 500 ? "error" : status >= 400 ? "warning" : "common";
      this.logger.log(lvl, `[PHP] ${reqMatch[2]} ${reqMatch[3]} → ${status}`);
      return;
    }
    if (/Fatal error|Parse error|Uncaught/i.test(line)) {
      this.logger.critical(`[PHP] ${line}`);
    } else if (/Warning|Notice|Deprecated/i.test(line)) {
      this.logger.warning(`[PHP] ${line}`);
    } else if (/Development Server.*started/i.test(line)) {
      this.logger.common(`[PHP] ${line}`);
    } else {
      this.logger.uncommon(`[PHP] ${line}`);
    }
  }

  async stop() {
    if (!this.process) return;
    try {
      this.process.kill("SIGTERM");
      // Beri 1 detik untuk shutdown graceful
      await sleep(1000);
      if (!this.process.killed) {
        this.process.kill("SIGKILL");
      }
    } catch (_) {}
    this.process = null;
  }

  isRunning() {
    return !!(this.process && !this.process.killed);
  }
}

module.exports = { PhpServer };
