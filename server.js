/**
 * TopZone - Universal Local Server + Ngrok Tunnel
 * ════════════════════════════════════════════════
 * Mendukung berbagai aplikasi server lokal:
 *
 *   MODE         SERVER_MODE=    Keterangan
 *   ──────────── ─────────────── ────────────────────────────────────
 *   auto         auto            Deteksi otomatis server yang jalan
 *   XAMPP        xampp           Apache XAMPP (port 80 / 8080)
 *   Laragon      laragon         Laragon (port 80)
 *   WAMP         wamp            WampServer (port 80)
 *   MAMP         mamp            MAMP (port 8888)
 *   PHP Built-in php             Spawn PHP dev server sendiri
 *   Custom       custom          Tunnel ke LOCAL_PORT bebas
 *
 * CARA PAKAI:
 *   1. npm install
 *   2. cp .env.example .env  → isi NGROK_AUTHTOKEN & SERVER_MODE
 *   3. Jalankan server dulu (XAMPP dll), lalu: node server.js
 */

require("dotenv").config();
const { spawn } = require("child_process");
const net       = require("net");
const ngrok     = require("@ngrok/ngrok");
const path      = require("path");
const fs        = require("fs");

// ══════════════════════════════════════════════
//  KONFIGURASI
// ══════════════════════════════════════════════
const CONFIG = {
  ngrokToken  : process.env.NGROK_AUTHTOKEN,
  ngrokDomain : process.env.NGROK_DOMAIN  || null,
  serverMode  : (process.env.SERVER_MODE  || "auto").toLowerCase(),
  localPort   : parseInt(process.env.LOCAL_PORT || "0", 10),
  phpPort     : parseInt(process.env.PHP_PORT   || "8080", 10),
  phpRoot     : process.env.PHP_ROOT || path.join(__dirname, "Home"),
};

// Port default tiap aplikasi (urutan = prioritas cek)
const APP_PROFILES = {
  xampp   : { name: "XAMPP",        ports: [80, 8080] },
  laragon : { name: "Laragon",       ports: [80, 8080] },
  wamp    : { name: "WampServer",    ports: [80, 8080] },
  mamp    : { name: "MAMP",          ports: [8888, 80] },
  php     : { name: "PHP Built-in",  ports: []         },
  custom  : { name: "Custom Server", ports: []         },
};

const AUTO_DETECT_PORTS = [80, 8080, 8888, 3000, 5000, 8000];

// ══════════════════════════════════════════════
//  VALIDASI AWAL
// ══════════════════════════════════════════════
function validateConfig() {
  if (!CONFIG.ngrokToken) {
    console.error("\n❌  NGROK_AUTHTOKEN belum diisi di .env !");
    console.error("    Daftar gratis: https://dashboard.ngrok.com/get-started/your-authtoken\n");
    process.exit(1);
  }
  if (CONFIG.serverMode === "php" && !fs.existsSync(CONFIG.phpRoot)) {
    console.error(`\n❌  Folder PHP tidak ditemukan: ${CONFIG.phpRoot}`);
    console.error("    Set PHP_ROOT di .env ke path folder project PHP.\n");
    process.exit(1);
  }
  if (CONFIG.serverMode === "custom" && !CONFIG.localPort) {
    console.error("\n❌  Mode 'custom' butuh LOCAL_PORT di .env (contoh: LOCAL_PORT=3000)\n");
    process.exit(1);
  }
}

// ══════════════════════════════════════════════
//  UTILITAS: cek apakah port sedang dipakai
// ══════════════════════════════════════════════
function isPortOpen(port, host = "127.0.0.1", timeoutMs = 800) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    socket.setTimeout(timeoutMs);
    socket
      .connect(port, host, () => { socket.destroy(); resolve(true); })
      .on("error",   () => { socket.destroy(); resolve(false); })
      .on("timeout", () => { socket.destroy(); resolve(false); });
  });
}

// ══════════════════════════════════════════════
//  MODE: AUTO
// ══════════════════════════════════════════════
async function autoDetectServer() {
  console.log("🔍 Mode AUTO — mendeteksi server lokal yang aktif...\n");

  for (const port of AUTO_DETECT_PORTS) {
    const open = await isPortOpen(port);
    if (open) {
      const guess =
        port === 80   ? "XAMPP / Laragon / WAMP / Apache" :
        port === 8080 ? "XAMPP Alt / PHP Built-in" :
        port === 8888 ? "MAMP" :
        `Server di port ${port}`;
      console.log(`   ✔  Terdeteksi: ${guess} (port ${port})`);
      return port;
    }
  }

  console.error("\n❌  Tidak ada server lokal yang terdeteksi di port:", AUTO_DETECT_PORTS.join(", "));
  console.error("    Hidupkan XAMPP / Laragon / WAMP / MAMP terlebih dahulu.");
  console.error("    Atau pakai SERVER_MODE=php di .env untuk PHP built-in.\n");
  process.exit(1);
}

// ══════════════════════════════════════════════
//  MODE: EXTERNAL APP (XAMPP, Laragon, WAMP, dll)
// ══════════════════════════════════════════════
async function resolveExternalPort(mode) {
  const profile  = APP_PROFILES[mode];
  const tryPorts = CONFIG.localPort ? [CONFIG.localPort] : profile.ports;

  console.log(`🔍 Mencari ${profile.name} di port: ${tryPorts.join(", ")}...`);

  for (const port of tryPorts) {
    const open = await isPortOpen(port);
    if (open) {
      console.log(`   ✔  ${profile.name} aktif di port ${port}`);
      return port;
    }
  }

  console.error(`\n❌  ${profile.name} tidak terdeteksi di port ${tryPorts.join(", ")}`);
  console.error(`    Pastikan ${profile.name} sudah dijalankan (Apache/Nginx harus ON).\n`);
  process.exit(1);
}

// ══════════════════════════════════════════════
//  MODE: PHP BUILT-IN
// ══════════════════════════════════════════════
function startPhpBuiltIn() {
  return new Promise((resolve, reject) => {
    const port = CONFIG.phpPort;
    const root = CONFIG.phpRoot;

    console.log("🐘 Menjalankan PHP built-in server...");
    console.log(`   Port : ${port}`);
    console.log(`   Root : ${root}\n`);

    const php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", root], {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    php.stderr.on("data", (data) => {
      const msg = data.toString().trim();
      if (/Development Server|started|Failed|Error/i.test(msg)) {
        console.log(`   [PHP] ${msg}`);
      }
    });

    php.on("error", (err) => {
      console.error("\n❌  Gagal spawn PHP:", err.message);
      console.error("    Cek apakah PHP sudah terinstall: php -v\n");
      reject(err);
    });

    // Beri waktu PHP startup
    setTimeout(() => resolve({ process: php, port }), 1200);
  });
}

// ══════════════════════════════════════════════
//  NGROK TUNNEL
// ══════════════════════════════════════════════
async function startNgrok(port) {
  console.log(`\n🚇 Membuka ngrok tunnel ke port ${port}...`);
  const options = { addr: port, authtoken: CONFIG.ngrokToken };
  if (CONFIG.ngrokDomain) options.domain = CONFIG.ngrokDomain;
  const listener = await ngrok.forward(options);
  return listener.url();
}

// ══════════════════════════════════════════════
//  TAMPILKAN RINGKASAN
// ══════════════════════════════════════════════
function printSummary(publicUrl, localPort, serverLabel) {
  const sep = "═".repeat(60);

  const folderHints = {
    xampp   : "Windows: C:\\xampp\\htdocs\\TopZone\n      Mac:     /Applications/XAMPP/htdocs/TopZone",
    laragon : "C:\\laragon\\www\\TopZone",
    wamp    : "C:\\wamp64\\www\\TopZone",
    mamp    : "/Applications/MAMP/htdocs/TopZone",
    php     : CONFIG.phpRoot,
    custom  : `http://localhost:${localPort}`,
    auto    : `http://localhost:${localPort}`,
  };

  const folderInfo = folderHints[CONFIG.serverMode] || `http://localhost:${localPort}`;

  console.log(`\n${sep}`);
  console.log(`  ✅  TopZone ONLINE!\n`);
  console.log(`  🖥️   Server        : ${serverLabel}`);
  console.log(`  💻  Lokal          : http://localhost:${localPort}`);
  console.log(`  🌐  Publik (ngrok) : ${publicUrl}`);
  console.log(`  📊  Ngrok UI       : http://localhost:4040\n`);
  console.log(`  📁  Letakkan folder project di:`);
  console.log(`      ${folderInfo}\n`);
  console.log(`  📌  URL untuk Webhook / Payment Gateway:`);
  console.log(`      Callback   : ${publicUrl}/callback.php`);
  console.log(`      Ambil Token: ${publicUrl}/Checkout/ambil_token.php`);
  console.log(`\n  ⚠️   Ctrl+C untuk berhenti`);
  console.log(`${sep}\n`);
}

// ══════════════════════════════════════════════
//  GRACEFUL SHUTDOWN
// ══════════════════════════════════════════════
function setupShutdown(phpProc) {
  async function shutdown(sig) {
    console.log(`\n🛑  ${sig} — mematikan server...`);
    try { await ngrok.disconnect(); console.log("   ✔  Ngrok ditutup"); } catch (_) {}
    if (phpProc && !phpProc.killed) {
      phpProc.kill("SIGTERM");
      console.log("   ✔  PHP server dihentikan");
    }
    console.log("   👋  Sampai jumpa!\n");
    process.exit(0);
  }
  process.on("SIGINT",  () => shutdown("SIGINT"));
  process.on("SIGTERM", () => shutdown("SIGTERM"));
}

// ══════════════════════════════════════════════
//  MAIN
// ══════════════════════════════════════════════
(async () => {
  console.log("╔════════════════════════════════════════════╗");
  console.log("║   TopZone — Universal Ngrok Launcher       ║");
  console.log("╚════════════════════════════════════════════╝\n");

  validateConfig();

  let localPort  = CONFIG.localPort;
  let phpProcess = null;
  let serverLabel = "";
  const mode = CONFIG.serverMode;

  try {
    if (mode === "auto") {
      localPort   = await autoDetectServer();
      serverLabel = `Auto-detected (port ${localPort})`;

    } else if (mode === "php") {
      const result = await startPhpBuiltIn();
      localPort    = result.port;
      phpProcess   = result.process;
      serverLabel  = "PHP Built-in Server";

    } else if (mode === "custom") {
      const open = await isPortOpen(localPort);
      if (!open) {
        console.error(`\n❌  Tidak ada server di port ${localPort}. Jalankan dulu aplikasimu.\n`);
        process.exit(1);
      }
      serverLabel = `Custom (port ${localPort})`;

    } else if (APP_PROFILES[mode]) {
      localPort   = await resolveExternalPort(mode);
      serverLabel = APP_PROFILES[mode].name;

    } else {
      console.error(`\n❌  SERVER_MODE tidak dikenal: "${mode}"`);
      console.error("    Pilihan: auto | xampp | laragon | wamp | mamp | php | custom\n");
      process.exit(1);
    }

    const publicUrl = await startNgrok(localPort);
    setupShutdown(phpProcess);
    printSummary(publicUrl, localPort, serverLabel);

  } catch (err) {
    console.error("\n❌  Error:", err.message || err);
    process.exit(1);
  }
})();
