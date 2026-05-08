/**
 * lib/controller.js — Controller utama TopZone
 * ─────────────────────────────────────────────
 * Menyatukan logger + detector + tunnel + phpServer + updater
 * jadi satu objek yang mudah dipakai dari CLI (server.js) maupun
 * GUI (gui.js).
 *
 * Lifecycle:
 *   1. controller.bootstrap()  — load config, init logger, daftar event
 *   2. controller.checkPreflight() — validasi env (Node ver, PHP, port, dll)
 *   3. controller.detectServer()   — pilih port + label
 *   4. controller.startTunnel()    — buka tunnel
 *   5. controller.shutdown()       — matikan semuanya rapi
 */

"use strict";

const path = require("path");
const fs   = require("fs");
const { EventEmitter } = require("events");

const { Logger }          = require("./logger");
const { TunnelManager }   = require("./tunnels");
const { PhpServer }       = require("./phpServer");
const { Updater }         = require("./updater");
const { SecurityManager } = require("./security");
const detector            = require("./detector");
const config              = require("./config");
const { sleep, getHostInfo, formatDuration, hasCommand } = require("./utils");

class Controller extends EventEmitter {
  constructor(opts = {}) {
    super();
    this.startedAt = Date.now();
    this.cfg       = null;
    this.logger    = null;
    this.tunnel    = null;
    this.phpServer = null;
    this.updater   = null;
    this.security  = null;

    this.state = {
      phase       : "idle",   // idle|booting|preflight|detecting|tunneling|online|stopping|error
      localPort   : null,
      serverLabel : null,
      tunnelUrl   : null,
      tunnelProvider: null,
      lastError   : null,
    };

    this._opts = opts;
  }

  /** Inisialisasi minimum (logger, config, modul). Tidak mulai server. */
  bootstrap() {
    this.cfg     = config.buildConfig();
    this.logger  = new Logger({
      dir         : path.join(config.ROOT, "logs"),
      echoConsole : this._opts.echoConsole !== false,
      minLevel    : this.cfg.LOG_MIN_LEVEL,
    });
    this.tunnel    = new TunnelManager(this.cfg, this.logger);
    this.phpServer = new PhpServer(this.cfg, this.logger);
    this.updater   = new Updater(this.cfg, this.logger);
    this.security  = new SecurityManager(this.cfg, this.logger);

    // Forward log entries supaya consumer (GUI) bisa subscribe
    this.logger.on("entry", (e) => this.emit("log", e));

    // Catat audit boot
    const host = getHostInfo();
    this.logger.common(`TopZone bootstrap: ${host.platform} ${host.arch} node-${host.nodeVer}`,
      { hostname: host.hostname, user: host.user });
    this.logger.common(`Mode=${this.cfg.SERVER_MODE} Tunnel=${this.cfg.TUNNEL_PROVIDER}`);
    return this;
  }

  setPhase(phase) {
    this.state.phase = phase;
    this.emit("phase", phase, this.state);
  }

  /** Validasi konfigurasi & lingkungan. Throw kalau kurang dari syarat minimum. */
  async checkPreflight() {
    this.setPhase("preflight");
    this.logger.common("Mulai preflight check.");

    // Node.js
    const nodeMajor = parseInt(process.versions.node.split(".")[0], 10);
    if (nodeMajor < 16) {
      const m = `Node.js ${process.versions.node} terlalu lama. Butuh >= 16.`;
      this.logger.critical(m);
      throw new Error(m);
    }

    // PHP (kalau mode = php)
    if (this.cfg.SERVER_MODE === "php") {
      if (!this.phpServer.hasPhp()) {
        const e = new Error("Perintah 'php' tidak ditemukan di PATH.");
        e.tip = "Install PHP atau pakai XAMPP/Laragon. Lihat docs/PANDUAN.md.";
        this.logger.critical(e.message);
        throw e;
      }
    }

    // Token tunnel
    if (this.cfg.TUNNEL_PROVIDER === "ngrok") {
      const tk = (this.cfg.NGROK_AUTHTOKEN || "").trim();
      if (!tk || tk.includes("isi_token")) {
        const e = new Error("NGROK_AUTHTOKEN belum diisi di .env.");
        e.tip = "Daftar gratis di dashboard.ngrok.com → Your Authtoken, lalu isi NGROK_AUTHTOKEN.";
        this.logger.critical(e.message);
        throw e;
      }
    }

    // Custom mode butuh LOCAL_PORT
    if (this.cfg.SERVER_MODE === "custom" && !this.cfg.LOCAL_PORT) {
      const e = new Error("Mode 'custom' butuh LOCAL_PORT di .env.");
      e.tip = "Set LOCAL_PORT=80 (atau port server-mu) di .env.";
      this.logger.critical(e.message);
      throw e;
    }

    this.logger.common("Preflight OK.");
    return true;
  }

  /** Deteksi server lokal yang aktif (atau spawn PHP built-in). */
  async detectServer() {
    this.setPhase("detecting");
    this.logger.common(`Mulai deteksi server (mode=${this.cfg.SERVER_MODE}).`);

    if (this.cfg.SERVER_MODE === "php") {
      try {
        const result = await this.phpServer.start();
        this.state.localPort   = result.port;
        this.state.serverLabel = `PHP Built-in (${this.phpServer.phpVersion() || "?"})`;
        this.logger.common(`PHP built-in jalan di port ${result.port}.`);
        return result;
      } catch (e) {
        this.logger.critical(`Gagal start PHP built-in: ${e.message}`);
        throw e;
      }
    }

    try {
      const det = await detector.detectForMode(this.cfg.SERVER_MODE, this.cfg);
      this.state.localPort   = det.port;
      this.state.serverLabel = det.label;
      this.logger.common(`Deteksi: ${det.label} (port ${det.port}).`);
      return det;
    } catch (e) {
      this.logger.critical(`Tidak ada server aktif: ${e.message}`);
      const wrapped = new Error(e.message);
      wrapped.tip = e.tip || "Jalankan XAMPP/Laragon dulu.";
      throw wrapped;
    }
  }

  /** Buka tunnel. */
  async startTunnel() {
    if (!this.state.localPort) {
      throw new Error("startTunnel dipanggil sebelum detectServer.");
    }
    this.setPhase("tunneling");
    try {
      const t = await this.tunnel.start(this.state.localPort);
      this.state.tunnelUrl      = t.url;
      this.state.tunnelProvider = t.provider;
      this.setPhase("online");
      config.writeState({
        lastTunnelProvider: t.provider,
        lastTunnelUrl     : t.url,
        lastLocalPort     : this.state.localPort,
        lastServerLabel   : this.state.serverLabel,
      });
      return t;
    } catch (e) {
      this.setPhase("error");
      this.state.lastError = e.message;
      throw e;
    }
  }

  /** Full lifecycle: preflight → detect → tunnel. */
  async startAll() {
    this.setPhase("booting");
    await this.checkPreflight();
    await this.detectServer();
    await this.startTunnel();
    return this.state;
  }

  /** Shutdown bersih. */
  async shutdown(reason = "shutdown") {
    if (this.state.phase === "stopping") return;
    this.setPhase("stopping");
    this.logger.common(`Shutdown dimulai: ${reason}`);

    try { await this.tunnel?.stop(); }
    catch (e) { this.logger.warning("Gagal stop tunnel: " + e.message); }

    try { await this.phpServer?.stop(); }
    catch (e) { this.logger.warning("Gagal stop PHP: " + e.message); }

    const uptime = Date.now() - this.startedAt;
    this.logger.common(`Shutdown selesai (uptime ${formatDuration(uptime)}).`);
    this.setPhase("idle");
  }

  /** Snapshot lengkap untuk dikirim ke GUI. */
  snapshot() {
    return {
      ...this.state,
      uptime    : Date.now() - this.startedAt,
      tunnelStat: this.tunnel?.getStats() || null,
      logCount  : this.logger?.stats() || {},
      cfg       : this._publicConfig(),
      host      : getHostInfo(),
    };
  }

  _publicConfig() {
    if (!this.cfg) return {};
    const c = { ...this.cfg };
    // Sembunyikan token
    if (c.NGROK_AUTHTOKEN) c.NGROK_AUTHTOKEN = c.NGROK_AUTHTOKEN.slice(0, 4) + "…" + c.NGROK_AUTHTOKEN.slice(-4);
    if (c.GUI_PASSWORD)    c.GUI_PASSWORD    = c.GUI_PASSWORD ? "[set]" : "";
    if (c.CLOUDFLARED_TOKEN) c.CLOUDFLARED_TOKEN = "[set]";
    return c;
  }
}

module.exports = { Controller };
