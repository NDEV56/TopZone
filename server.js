#!/usr/bin/env node
/**
 * TopZone — Universal Server Launcher v3.0
 * ════════════════════════════════════════
 * CLI ringkas. Semua logika dipindah ke lib/ supaya GUI dan CLI
 * berbagi kode yang sama.
 *
 *   node server.js               → mode interaktif normal
 *   node server.js --gui         → buka GUI control panel
 *   node server.js --doctor      → diagnosa lingkungan
 *   node server.js --setup       → wizard setup ulang
 *   node server.js --update      → cek update dari GitHub
 *   node server.js --no-tunnel   → jalan local-only
 *   node server.js --provider=cloudflared  → override tunnel
 *
 * Untuk PEMULA: jalankan saja `start.bat` (Windows) atau
 * `bash start.sh` (Mac/Linux). Itu otomatis cek Node + npm install.
 */

"use strict";

const path     = require("path");
const fs       = require("fs");
const readline = require("readline");

const config         = require("./lib/config");
const { Controller } = require("./lib/controller");
const { c, badge, header, divider, box } = require("./lib/colors");
const { parseArgs, formatDuration, getHostInfo } = require("./lib/utils");
const detector       = require("./lib/detector");

const { flags } = parseArgs();

// ─────────────────────────────────────────────────
//  Banner
// ─────────────────────────────────────────────────
function printBanner() {
  if (process.env.NO_BANNER) return;
  console.log(c.cyan(c.bold(`
  ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗
     ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝
     ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗
     ██║   ██║   ██║██╔═══╝    ██╔╝ ██║   ██║██║╚████║██╔══╝
     ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗
     ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝`)));
  console.log(c.dim("                    Universal Server Launcher v3.0"));
  console.log(c.dim("                    GUI: node server.js --gui\n"));
}

// ─────────────────────────────────────────────────
//  Setup Wizard (CLI)
// ─────────────────────────────────────────────────
async function runWizard() {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const ask = (q) => new Promise((res) => rl.question(q, (a) => res(a.trim())));

  console.log(c.cyan(c.bold("\n╔══════════════════════════════════════════════════╗")));
  console.log(c.cyan(c.bold(  "║   🧙  Setup Wizard TopZone — Konfigurasi awal    ║")));
  console.log(c.cyan(c.bold(  "╚══════════════════════════════════════════════════╝\n")));
  console.log(badge.info("Wizard akan membuat file .env. Tekan ENTER untuk pakai default."));
  console.log(c.dim("  Bisa diubah lagi nanti — edit .env atau jalankan: node server.js --setup\n"));

  // Step 1: Tunnel provider
  console.log(c.bold("[1/5] Pilih provider tunnel:"));
  console.log(c.dim("  Tunnel = supaya server lokal kamu bisa diakses dari internet."));
  console.log("  1. " + c.cyan("ngrok") + "       — paling stabil (butuh daftar gratis)");
  console.log("  2. " + c.cyan("cloudflared") + " — Cloudflare Tunnel (tidak perlu daftar)");
  console.log("  3. " + c.cyan("localtunnel") + " — alternatif gratis (npm i -g localtunnel)");
  console.log("  4. " + c.cyan("serveo") + "      — pakai SSH (tidak perlu install)");
  console.log("  5. " + c.cyan("none") + "        — local-only, tanpa tunnel");
  const tProv = (await ask(c.yellow("▸ Pilihan (1-5) [1]: "))) || "1";
  const provMap = ["ngrok","cloudflared","localtunnel","serveo","none"];
  const provider = provMap[parseInt(tProv, 10) - 1] || "ngrok";

  // Step 2: Token (kalau ngrok)
  let token = "";
  if (provider === "ngrok") {
    console.log(c.dim("\n  Daftar di: https://dashboard.ngrok.com/get-started/your-authtoken"));
    while (!token) {
      token = (await ask(c.yellow("▸ NGROK_AUTHTOKEN: "))).trim();
      if (!token) console.log(badge.warn("Token kosong. Tunneling ngrok butuh token. Coba lagi."));
    }
  }

  // Step 3: Server mode
  console.log(c.bold("\n[2/5] Pilih server lokal kamu:"));
  const modes = [
    ["auto",         "Deteksi otomatis (XAMPP/Laragon/WAMP/MAMP/...) "],
    ["xampp",        "XAMPP (Apache + MySQL)"],
    ["laragon",      "Laragon"],
    ["wamp",         "WampServer"],
    ["mamp",         "MAMP"],
    ["ampps",        "AMPPS"],
    ["openserver",   "OpenServer Panel (Windows)"],
    ["usbwebserver", "USBWebserver"],
    ["easyphp",      "EasyPHP"],
    ["php",          "PHP Built-in (otomatis spawn 'php -S')"],
    ["custom",       "Custom (server lain — tentukan port sendiri)"],
  ];
  modes.forEach((m, i) => console.log(`  ${String(i+1).padStart(2)}. ${c.cyan(m[0].padEnd(13))} — ${m[1]}`));
  const mInput = (await ask(c.yellow("▸ Pilihan (1-11) [1]: "))) || "1";
  const mode = modes[parseInt(mInput, 10) - 1]?.[0] || "auto";

  // Step 4: Custom port
  let localPort = "";
  if (mode === "custom") {
    localPort = (await ask(c.yellow("▸ LOCAL_PORT (port server-mu): "))).trim() || "80";
  }

  // Step 5: GUI password
  console.log(c.bold("\n[3/5] Password GUI Control Panel:"));
  console.log(c.dim("  Kosong = boleh akses tanpa password (aman karena bind hanya ke 127.0.0.1)."));
  console.log(c.dim("  Disarankan diisi kalau kamu pernah set GUI_BIND=0.0.0.0."));
  const pwd = await ask(c.yellow("▸ GUI_PASSWORD (kosong = skip): "));

  // Step 6 (info)
  console.log(c.bold("\n[4/5] Auto-update:"));
  console.log(c.dim("  ask  = tanya dulu kalau ada update (default, paling aman)"));
  console.log(c.dim("  true = pull otomatis"));
  console.log(c.dim("  false= jangan pernah cek"));
  const au = (await ask(c.yellow("▸ AUTO_UPDATE (ask|true|false) [ask]: "))).toLowerCase() || "ask";

  // Step 7 (info)
  console.log(c.bold("\n[5/5] Selesai. Menulis .env..."));

  config.writeEnv({
    NGROK_AUTHTOKEN : token,
    SERVER_MODE     : mode,
    LOCAL_PORT      : localPort || "0",
    TUNNEL_PROVIDER : provider,
    GUI_PASSWORD    : pwd,
    AUTO_UPDATE     : au,
  });
  config.writeEnvExample();

  rl.close();
  console.log(badge.ok(c.green(c.bold("File .env berhasil dibuat!"))));
  console.log(c.dim("  Kalau mau ubah konfigurasi nanti: edit .env atau --setup ulang.\n"));
}

// ─────────────────────────────────────────────────
//  Print summary online
// ─────────────────────────────────────────────────
function printOnlineSummary(state) {
  const { localPort, serverLabel, tunnelUrl, tunnelProvider } = state;
  const sep = c.cyan("═".repeat(64));
  console.log("\n" + sep);
  console.log("  " + c.bgGreen(c.bold("  ✅ TopZone ONLINE  ")) + "\n");
  console.log("  " + c.bold("Server lokal  ") + ": " + serverLabel);
  console.log("  " + c.bold("Local URL     ") + ": " + c.cyan(c.bold(`http://localhost:${localPort}`)));
  console.log("  " + c.bold("Public URL    ") + ": " + c.cyan(c.bold(tunnelUrl)));
  console.log("  " + c.bold("Provider      ") + ": " + tunnelProvider);
  if (tunnelProvider === "ngrok") {
    console.log("  " + c.bold("Ngrok inspect ") + ": " + c.cyan("http://localhost:4040"));
  }
  console.log("  " + c.bold("GUI panel     ") + ": " + c.cyan(`node server.js --gui`));
  console.log(sep);
  console.log(c.dim("\n  📡 Live request log (Ctrl+C untuk stop):\n"));
}

// ─────────────────────────────────────────────────
//  Doctor (diagnose)
// ─────────────────────────────────────────────────
async function runDoctor() {
  console.log(header("TopZone Doctor", "cyan"));
  const host = getHostInfo();
  console.log(badge.step(`Host: ${host.hostname} (${host.platform}/${host.arch})`));
  console.log(badge.step(`Node ${host.nodeVer}, ${host.cpus} CPU, ${host.memMB} MB RAM`));

  const diag = await detector.diagnose();
  console.log("\n" + c.bold("Tools terinstall:"));
  console.log("  " + (diag.hasGit       ? badge.ok("git")          : badge.warn("git tidak ada")));
  console.log("  " + (diag.hasPhp       ? badge.ok("php")          : badge.warn("php tidak ada")));
  console.log("  " + (diag.hasNgrok     ? badge.ok("ngrok binary") : badge.info("ngrok binary tidak ada (pakai @ngrok/ngrok lewat npm)")));
  console.log("  " + (diag.hasCloudflared ? badge.ok("cloudflared") : badge.info("cloudflared tidak ada (opsional)")));
  console.log("  " + (diag.hasLt        ? badge.ok("localtunnel (lt)") : badge.info("lt tidak ada (opsional)")));

  console.log("\n" + c.bold("App server terinstall:"));
  if (!diag.installedServers.length) {
    console.log("  " + badge.warn("Tidak ada XAMPP/Laragon/WAMP/MAMP/AMPPS/OpenServer/EasyPHP terdeteksi"));
  } else {
    for (const s of diag.installedServers) {
      console.log("  " + badge.ok(`${s.name} → ${s.path}`));
    }
  }

  console.log("\n" + c.bold("Port yang sedang aktif:"));
  if (!diag.activeServers.length) {
    console.log("  " + badge.warn("Tidak ada server HTTP yang merespons di port umum"));
  } else {
    for (const s of diag.activeServers) {
      console.log("  " + badge.ok(`port ${String(s.port).padEnd(5)} → ${s.label} (HTTP ${s.status})`));
    }
  }

  console.log("\n" + c.bold("MySQL/MariaDB:"));
  console.log("  " + (diag.mysql.running
    ? badge.ok(`Running di port ${diag.mysql.port}`)
    : badge.info("Tidak terdeteksi (OK kalau kamu tidak butuh database)")));

  console.log("\n" + c.dim("Selesai. Kalau ada warna kuning: lihat docs/PANDUAN.md untuk solusi."));
}

// ─────────────────────────────────────────────────
//  Update
// ─────────────────────────────────────────────────
async function runUpdate() {
  const ctl = new Controller({ echoConsole: true }).bootstrap();
  const u = ctl.updater;
  console.log(header("Cek Update GitHub", "magenta"));

  if (!u.isGitRepo()) {
    console.log(badge.warn("Folder ini bukan git repository — auto-update tidak tersedia."));
    console.log(c.dim("  Untuk pakai auto-update, clone dengan: git clone <url-repo-teman-mu>"));
    return;
  }

  const st = u.status();
  console.log(badge.step(`Branch lokal : ${st.branch} @ ${st.head.slice(0, 8)}`));
  console.log(badge.step(`Remote       : ${st.remote || "(belum ada)"}`));

  const res = await u.check();
  if (!res.available) {
    console.log("\n" + badge.ok(c.green(res.reason || "Tidak ada update.")));
    return;
  }

  console.log(badge.spark(`Update tersedia: ${res.behind} commit baru di ${res.branch}`));
  console.log("\n" + c.bold("Ringkasan commit baru:"));
  for (const line of res.summary) console.log("  " + c.dim("• ") + line);

  if (st.dirty) {
    console.log("\n" + badge.warn("Ada perubahan lokal yang belum commit. Akan di-stash dulu."));
  }

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const ans = await new Promise((r) => rl.question(c.yellow("\n▸ Lanjut pull? (y/n) [y]: "), (a) => r(a.trim().toLowerCase())));
  rl.close();
  if (ans && ans !== "y" && ans !== "yes") {
    console.log(badge.info("Dibatalkan."));
    return;
  }

  const pullRes = await u.pull();
  if (pullRes.ok) {
    console.log("\n" + badge.ok(c.green(pullRes.message)));
    if (pullRes.depsChanged) console.log(badge.info("npm install sudah dijalankan otomatis."));
    console.log(c.dim("Restart server.js untuk memakai versi baru."));
  } else {
    console.log("\n" + badge.fail(c.red(pullRes.message)));
    console.log(c.dim("Backup ada di: " + (pullRes.backup?.dest || "?")));
  }
}

// ─────────────────────────────────────────────────
//  GUI launcher (delegasi ke gui.js)
// ─────────────────────────────────────────────────
function runGui() {
  // Spawn gui.js sebagai proses sendiri supaya CLI bisa keluar bersih
  const guiPath = path.join(__dirname, "gui.js");
  if (!fs.existsSync(guiPath)) {
    console.log(badge.fail("File gui.js tidak ditemukan."));
    process.exit(1);
  }
  // Forward execution
  require(guiPath);
}

// ─────────────────────────────────────────────────
//  Main
// ─────────────────────────────────────────────────
(async () => {
  try {
    if (flags.help || flags.h) {
      console.log(`Usage:
  node server.js                Jalan normal (deteksi server + tunnel)
  node server.js --gui          Buka GUI Control Panel di browser
  node server.js --setup        Setup wizard ulang
  node server.js --doctor       Diagnosa lingkungan
  node server.js --update       Cek & tarik update dari GitHub
  node server.js --no-tunnel    Jalan local-only (tanpa tunnel)
  node server.js --provider=X   Override tunnel (ngrok|cloudflared|localtunnel|serveo|none)
  node server.js --mode=X       Override SERVER_MODE (auto|xampp|laragon|...)
  node server.js --port=N       Override LOCAL_PORT
`);
      return;
    }

    if (flags.doctor) { await runDoctor(); return; }
    if (flags.update) { await runUpdate(); return; }

    if (flags.setup || !config.isConfigured()) {
      printBanner();
      await runWizard();
      // Setelah wizard, lanjut start? Tanya.
      const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
      const ans = await new Promise((r) => rl.question(c.yellow("Mulai server sekarang? (y/n) [y]: "), (a) => r(a.trim().toLowerCase())));
      rl.close();
      if (ans && ans !== "y" && ans !== "yes") {
        console.log(c.dim("OK. Jalankan lagi dengan: node server.js"));
        return;
      }
    } else {
      printBanner();
    }

    // Override flags ke env
    if (flags.provider) process.env.TUNNEL_PROVIDER = flags.provider;
    if (flags.mode)     process.env.SERVER_MODE     = flags.mode;
    if (flags.port)     process.env.LOCAL_PORT      = flags.port;
    if (flags["no-tunnel"]) process.env.TUNNEL_PROVIDER = "none";

    if (flags.gui) { runGui(); return; }

    // Normal mode
    const ctl = new Controller({ echoConsole: true }).bootstrap();

    // Kalau git repo & AUTO_UPDATE != false, cek update di background
    if (ctl.cfg.AUTO_UPDATE !== "false" && ctl.updater.isGitRepo()) {
      ctl.updater.check().then((res) => {
        if (res.available) {
          console.log("\n" + badge.spark(c.magenta(`Update tersedia: ${res.behind} commit baru.`)));
          console.log("  " + c.dim("Jalankan: node server.js --update"));
        }
      }).catch(() => {});
    }

    // Graceful shutdown
    let shutting = false;
    const onSignal = async (sig) => {
      if (shutting) return;
      shutting = true;
      console.log(`\n\n${c.yellow(`🛑  ${sig} — mematikan server...`)}`);
      try { await ctl.shutdown(sig); } catch (_) {}
      const stats = ctl.tunnel?.getStats() || {};
      console.log(c.dim(`\nTotal request: ${stats.requestCount || 0}`));
      console.log(c.green("👋  Sampai jumpa!\n"));
      process.exit(0);
    };
    process.on("SIGINT",  () => onSignal("SIGINT"));
    process.on("SIGTERM", () => onSignal("SIGTERM"));
    process.on("uncaughtException", (e) => {
      ctl.logger?.critical("uncaughtException: " + e.message, { stack: e.stack });
      onSignal("uncaughtException");
    });
    process.on("unhandledRejection", (r) => {
      ctl.logger?.error("unhandledRejection: " + (r && r.message || r));
    });

    try {
      await ctl.startAll();
      printOnlineSummary(ctl.state);
    } catch (e) {
      console.log("\n" + badge.fail(c.red(e.message)));
      if (e.tip) console.log(c.dim("  💡 " + e.tip));
      console.log(c.dim("  📖 Bantuan: docs/PANDUAN.md"));
      process.exit(1);
    }

  } catch (e) {
    console.error("\n" + badge.fail(c.red("Error fatal: ") + e.message));
    if (e.stack) console.error(c.dim(e.stack));
    process.exit(1);
  }
})();
