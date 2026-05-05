/**
 * TopZone - Local Dev Server + Ngrok Tunnel
 * ==========================================
 * Menjalankan PHP built-in server lalu membuka tunnel ngrok
 * supaya bisa diakses publik untuk testing (webhook Midtrans/Xendit, dll).
 *
 * CARA PAKAI:
 *   1. Install dependencies  : npm install
 *   2. Copy env              : cp .env.example .env  (lalu isi NGROK_AUTHTOKEN)
 *   3. Jalankan              : node server.js
 *
 * REQUIREMENTS:
 *   - Node.js >= 16
 *   - PHP >= 7.4 sudah terinstall & ada di PATH  (cek: php -v)
 *   - MySQL sudah jalan, database 'topzone' sudah dibuat
 *   - Daftar ngrok gratis di https://ngrok.com → ambil authtoken
 */

require("dotenv").config();
const { spawn } = require("child_process");
const ngrok     = require("@ngrok/ngrok");
const path      = require("path");
const fs        = require("fs");

// ──────────────────────────────────────────────
//  KONFIGURASI
// ──────────────────────────────────────────────
const PHP_PORT    = process.env.PHP_PORT    || 8080;
const NGROK_TOKEN = process.env.NGROK_AUTHTOKEN;
const NGROK_DOMAIN= process.env.NGROK_DOMAIN || null;   // opsional: custom domain (plan berbayar)
const PHP_ROOT    = path.join(__dirname, "Home");        // root folder PHP kamu

// ──────────────────────────────────────────────
//  VALIDASI AWAL
// ──────────────────────────────────────────────
if (!NGROK_TOKEN) {
  console.error("\n❌  NGROK_AUTHTOKEN belum diisi di file .env !");
  console.error("    Daftar gratis di https://ngrok.com lalu copy auth token-nya.\n");
  process.exit(1);
}

if (!fs.existsSync(PHP_ROOT)) {
  console.error(`\n❌  Folder PHP tidak ditemukan: ${PHP_ROOT}\n`);
  process.exit(1);
}

// ──────────────────────────────────────────────
//  1. JALANKAN PHP BUILT-IN SERVER
// ──────────────────────────────────────────────
function startPhpServer() {
  return new Promise((resolve, reject) => {
    console.log(`\n🐘 Menjalankan PHP built-in server di port ${PHP_PORT}...`);
    console.log(`   Root: ${PHP_ROOT}\n`);

    const php = spawn(
      "php",
      ["-S", `127.0.0.1:${PHP_PORT}`, "-t", PHP_ROOT],
      {
        stdio: ["ignore", "pipe", "pipe"],
        shell: process.platform === "win32", // Windows butuh shell
      }
    );

    // PHP server nulis ke stderr, bukan stdout
    php.stderr.on("data", (data) => {
      const msg = data.toString().trim();
      // Filter: tampilkan hanya baris penting (bukan tiap request log)
      if (
        msg.includes("Development Server") ||
        msg.includes("started") ||
        msg.includes("Failed") ||
        msg.includes("Error")
      ) {
        console.log(`[PHP] ${msg}`);
      }
    });

    php.on("error", (err) => {
      console.error("\n❌  Gagal menjalankan PHP:", err.message);
      console.error("    Pastikan PHP sudah terinstall: php -v\n");
      reject(err);
    });

    php.on("exit", (code, signal) => {
      if (code !== null && code !== 0) {
        console.error(`\n[PHP] Proses berhenti dengan kode ${code}`);
      }
    });

    // Beri waktu PHP server start (biasanya < 1 detik)
    setTimeout(() => resolve(php), 1200);
  });
}

// ──────────────────────────────────────────────
//  2. BUKA TUNNEL NGROK
// ──────────────────────────────────────────────
async function startNgrok() {
  console.log("🚇 Membuka tunnel ngrok...");

  const options = {
    addr    : PHP_PORT,
    authtoken: NGROK_TOKEN,
  };

  // Kalau ada custom domain (akun berbayar), pakai itu
  if (NGROK_DOMAIN) {
    options.domain = NGROK_DOMAIN;
  }

  const listener = await ngrok.forward(options);
  return listener.url();
}

// ──────────────────────────────────────────────
//  3. TAMPILKAN INFO & PETUNJUK
// ──────────────────────────────────────────────
function printInfo(publicUrl) {
  const callbackUrl = `${publicUrl}/callback.php`;
  const checkoutUrl = `${publicUrl}/Checkout/ambil_token.php`;

  console.log("\n" + "═".repeat(55));
  console.log("  ✅  TopZone siap diakses!\n");
  console.log(`  🌐  URL Publik     : ${publicUrl}`);
  console.log(`  💻  Lokal (PHP)    : http://127.0.0.1:${PHP_PORT}`);
  console.log(`  📊  Ngrok Dashboard: http://127.0.0.1:4040\n`);
  console.log("  📌  URL untuk Payment Gateway:");
  console.log(`      Callback/Webhook : ${callbackUrl}`);
  console.log(`      Ambil Token      : ${checkoutUrl}`);
  console.log("\n  ⚠️   Tekan Ctrl+C untuk menghentikan server");
  console.log("═".repeat(55) + "\n");
}

// ──────────────────────────────────────────────
//  4. GRACEFUL SHUTDOWN
// ──────────────────────────────────────────────
function setupShutdown(phpProcess) {
  async function shutdown(signal) {
    console.log(`\n🛑  Menerima ${signal}, mematikan server...`);
    try {
      await ngrok.disconnect();
      console.log("   ✔  Ngrok tunnel ditutup");
    } catch (_) {}
    if (phpProcess && !phpProcess.killed) {
      phpProcess.kill("SIGTERM");
      console.log("   ✔  PHP server dihentikan");
    }
    console.log("   👋  Bye!\n");
    process.exit(0);
  }

  process.on("SIGINT",  () => shutdown("SIGINT"));   // Ctrl+C
  process.on("SIGTERM", () => shutdown("SIGTERM"));   // kill
}

// ──────────────────────────────────────────────
//  MAIN
// ──────────────────────────────────────────────
(async () => {
  try {
    const phpProcess = await startPhpServer();
    const publicUrl  = await startNgrok();

    setupShutdown(phpProcess);
    printInfo(publicUrl);

  } catch (err) {
    console.error("\n❌  Terjadi error saat startup:", err.message || err);
    process.exit(1);
  }
})();
