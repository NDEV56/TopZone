/**
 * TopZone — Universal Ngrok Launcher v2.0
 * ════════════════════════════════════════
 * Fitur:
 *  • Setup wizard otomatis (first-run)
 *  • Auto-detect server (XAMPP, Laragon, WAMP, MAMP, PHP built-in)
 *  • HTTP health check — verifikasi server benar-benar nyala
 *  • Port fallback otomatis (PHP mode)
 *  • Retry ngrok dengan exponential backoff
 *  • Live request logger dari ngrok API
 *  • Preflight checks (Node.js, PHP, port)
 *  • Error map — setiap error disertai solusi spesifik
 *  • Colored output (tanpa dependency tambahan)
 *  • Graceful shutdown
 *
 * MODE (SERVER_MODE di .env):
 *   auto | xampp | laragon | wamp | mamp | php | custom
 */

// ─────────────────────────────────────────────────
//  DEPENDENCIES
// ─────────────────────────────────────────────────
const path     = require("path");
const fs       = require("fs");
const net      = require("net");
const http     = require("http");
const https    = require("https");
const { spawn, execSync } = require("child_process");
const readline = require("readline");

// Lazy-load agar error lebih jelas kalau belum npm install
let ngrok, dotenv;
try {
  ngrok  = require("@ngrok/ngrok");
  dotenv = require("dotenv");
} catch {
  console.error("\n❌  Dependencies belum diinstall!");
  console.error("    Jalankan: npm install\n");
  process.exit(1);
}

// ─────────────────────────────────────────────────
//  ANSI COLORS (tanpa chalk)
// ─────────────────────────────────────────────────
const c = {
  reset  : "\x1b[0m",
  bold   : "\x1b[1m",
  dim    : "\x1b[2m",
  red    : "\x1b[31m",
  green  : "\x1b[32m",
  yellow : "\x1b[33m",
  blue   : "\x1b[34m",
  magenta: "\x1b[35m",
  cyan   : "\x1b[36m",
  white  : "\x1b[37m",
  bgGreen: "\x1b[42m",
  bgRed  : "\x1b[41m",
};

const ok    = (s) => `${c.green}✔${c.reset}  ${s}`;
const fail  = (s) => `${c.red}✖${c.reset}  ${s}`;
const warn  = (s) => `${c.yellow}⚠${c.reset}  ${s}`;
const info  = (s) => `${c.cyan}ℹ${c.reset}  ${s}`;
const step  = (s) => `${c.blue}→${c.reset}  ${s}`;
const bold  = (s) => `${c.bold}${s}${c.reset}`;
const dim   = (s) => `${c.dim}${s}${c.reset}`;
const hi    = (s) => `${c.cyan}${c.bold}${s}${c.reset}`;

// ─────────────────────────────────────────────────
//  KONSTANTA
// ─────────────────────────────────────────────────
const ENV_FILE      = path.join(__dirname, ".env");
const ENV_EXAMPLE   = path.join(__dirname, ".env.example");
const AUTO_PORTS    = [80, 8080, 8888, 3000, 5000, 8000, 8008];
const NGROK_API     = "http://127.0.0.1:4040/api/requests/http";
const RETRY_MAX     = 3;
const RETRY_DELAY   = 1500; // ms

const APP_PROFILES  = {
  auto    : { name: "Auto-detect",   ports: AUTO_PORTS },
  xampp   : { name: "XAMPP",         ports: [80, 8080], hint: "Pastikan Apache di XAMPP Control Panel sudah START" },
  laragon : { name: "Laragon",       ports: [80, 8080], hint: "Pastikan Laragon sudah dibuka dan service ON" },
  wamp    : { name: "WampServer",    ports: [80, 8080], hint: "Klik icon WampServer → Start All Services" },
  mamp    : { name: "MAMP",          ports: [8888, 80], hint: "Buka MAMP lalu klik Start Servers" },
  php     : { name: "PHP Built-in",  ports: [],         hint: "PHP harus terinstall (cek: php -v)" },
  custom  : { name: "Custom Server", ports: [],         hint: "Pastikan server kamu sudah jalan di LOCAL_PORT" },
};

const FOLDER_HINTS = {
  xampp  : ["C:\\xampp\\htdocs", "/Applications/XAMPP/htdocs", "/opt/lampp/htdocs"],
  laragon: ["C:\\laragon\\www"],
  wamp   : ["C:\\wamp64\\www", "C:\\wamp\\www"],
  mamp   : ["/Applications/MAMP/htdocs"],
};

// ─────────────────────────────────────────────────
//  LOAD CONFIG
// ─────────────────────────────────────────────────
function loadConfig() {
  if (fs.existsSync(ENV_FILE)) dotenv.config({ path: ENV_FILE });
  return {
    ngrokToken  : process.env.NGROK_AUTHTOKEN,
    ngrokDomain : process.env.NGROK_DOMAIN   || null,
    serverMode  : (process.env.SERVER_MODE   || "auto").toLowerCase(),
    localPort   : parseInt(process.env.LOCAL_PORT || "0", 10) || 0,
    phpPort     : parseInt(process.env.PHP_PORT   || "8080", 10),
    phpRoot     : process.env.PHP_ROOT || path.join(__dirname, "Home"),
    logRequests : (process.env.LOG_REQUESTS  || "true") === "true",
  };
}

// ─────────────────────────────────────────────────
//  SETUP WIZARD (first-run)
// ─────────────────────────────────────────────────
async function runSetupWizard() {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const ask = (q) => new Promise((res) => rl.question(q, res));

  console.log(`\n${c.cyan}${c.bold}╔══════════════════════════════════════════════╗`);
  console.log(`║   🧙  Setup Wizard — Konfigurasi Pertama     ║`);
  console.log(`╚══════════════════════════════════════════════╝${c.reset}\n`);
  console.log(info("File .env belum ada. Mari kita buat sekarang!\n"));

  // 1. Ngrok token
  console.log(dim("Daftar gratis di: https://dashboard.ngrok.com/get-started/your-authtoken"));
  const token = (await ask(`${c.yellow}▸ NGROK_AUTHTOKEN${c.reset}: `)).trim();
  if (!token) {
    console.log(fail("Token tidak boleh kosong. Jalankan ulang dan isi token.\n"));
    rl.close(); process.exit(1);
  }

  // 2. Server mode
  console.log(`\n${c.cyan}Server mode:${c.reset}`);
  const modes = ["auto", "xampp", "laragon", "wamp", "mamp", "php", "custom"];
  modes.forEach((m, i) => console.log(`  ${c.dim}${i + 1}.${c.reset} ${m.padEnd(8)} ${dim(APP_PROFILES[m]?.hint || "")}`));
  const modeInput = (await ask(`\n${c.yellow}▸ Pilih mode (1-7) atau ketik nama${c.reset} [default: auto]: `)).trim();

  let serverMode = "auto";
  if (modeInput) {
    const idx = parseInt(modeInput, 10);
    serverMode = (!isNaN(idx) && modes[idx - 1]) ? modes[idx - 1] : modeInput.toLowerCase();
  }

  // 3. Port (untuk custom)
  let localPort = "";
  if (serverMode === "custom") {
    localPort = (await ask(`${c.yellow}▸ LOCAL_PORT${c.reset}: `)).trim();
  }

  // 4. Tulis .env
  const lines = [
    `# TopZone .env — generated by setup wizard`,
    `# Edit kapan saja, lalu restart server.js`,
    ``,
    `NGROK_AUTHTOKEN=${token}`,
    `SERVER_MODE=${serverMode}`,
    localPort ? `LOCAL_PORT=${localPort}` : `# LOCAL_PORT=80`,
    `# PHP_PORT=8080`,
    `# PHP_ROOT=./Home`,
    `# NGROK_DOMAIN=`,
    `# LOG_REQUESTS=true`,
  ];
  fs.writeFileSync(ENV_FILE, lines.join("\n") + "\n");

  rl.close();
  console.log(`\n${ok("File .env berhasil dibuat!")}`);
  console.log(dim("  Edit .env kapan saja untuk mengubah konfigurasi.\n"));

  // Reload config
  dotenv.config({ path: ENV_FILE, override: true });
}

// ─────────────────────────────────────────────────
//  PREFLIGHT CHECKS
// ─────────────────────────────────────────────────
async function preflight(cfg) {
  console.log(`\n${bold("[ Preflight Checks ]")}`);

  // Node.js version
  const nodeVer = parseInt(process.versions.node.split(".")[0], 10);
  if (nodeVer < 16) {
    console.log(fail(`Node.js ${process.versions.node} terlalu lama. Butuh >= 16.`));
    process.exit(1);
  }
  console.log(ok(`Node.js ${process.versions.node}`));

  // PHP check (hanya kalau mode php)
  if (cfg.serverMode === "php") {
    try {
      const ver = execSync("php -r \"echo PHP_VERSION;\"", { timeout: 3000 }).toString().trim();
      console.log(ok(`PHP ${ver}`));
    } catch {
      console.log(fail("PHP tidak ditemukan di PATH!"));
      console.log(info("  Install PHP: https://www.php.net/downloads"));
      console.log(info("  Windows: install XAMPP → tambah C:\\xampp\\php ke PATH"));
      process.exit(1);
    }
  }

  // PHP root folder (mode php)
  if (cfg.serverMode === "php" && !fs.existsSync(cfg.phpRoot)) {
    console.log(fail(`Folder PHP tidak ada: ${cfg.phpRoot}`));
    console.log(info("  Set PHP_ROOT di .env ke path folder project kamu"));
    process.exit(1);
  }

  // Token ngrok
  if (!cfg.ngrokToken || cfg.ngrokToken.includes("isi_token")) {
    console.log(fail("NGROK_AUTHTOKEN belum diisi di .env !"));
    console.log(info("  Daftar gratis: https://dashboard.ngrok.com/get-started/your-authtoken"));
    process.exit(1);
  }
  console.log(ok("Ngrok auth token ditemukan"));

  // Custom port validation
  if (cfg.serverMode === "custom" && !cfg.localPort) {
    console.log(fail("Mode 'custom' butuh LOCAL_PORT di .env (contoh: LOCAL_PORT=3000)"));
    process.exit(1);
  }

  console.log();
}

// ─────────────────────────────────────────────────
//  PORT UTILITIES
// ─────────────────────────────────────────────────
function isPortOpen(port, host = "127.0.0.1", timeoutMs = 900) {
  return new Promise((resolve) => {
    const s = new net.Socket();
    s.setTimeout(timeoutMs);
    s.connect(port, host, () => { s.destroy(); resolve(true); });
    s.on("error",   () => { s.destroy(); resolve(false); });
    s.on("timeout", () => { s.destroy(); resolve(false); });
  });
}

// HTTP health check — pastikan server benar-benar merespons
function httpHealthCheck(port, timeoutMs = 3000) {
  return new Promise((resolve) => {
    const req = http.get(
      { hostname: "127.0.0.1", port, path: "/", timeout: timeoutMs },
      (res) => { resolve({ ok: true, status: res.statusCode }); }
    );
    req.on("error",   () => resolve({ ok: false }));
    req.on("timeout", () => { req.destroy(); resolve({ ok: false }); });
  });
}

// Cari port kosong mulai dari startPort
async function findFreePort(startPort) {
  for (let p = startPort; p < startPort + 20; p++) {
    const inUse = await isPortOpen(p);
    if (!inUse) return p;
  }
  throw new Error("Tidak ada port kosong ditemukan (dicoba 20 port)");
}

// ─────────────────────────────────────────────────
//  SERVER DETECTION
// ─────────────────────────────────────────────────
async function detectServer(mode, cfg) {
  console.log(`${bold("[ Deteksi Server ]")}`);

  const profile  = APP_PROFILES[mode] || APP_PROFILES.auto;
  const tryPorts = cfg.localPort
    ? [cfg.localPort]
    : (mode === "auto" ? AUTO_PORTS : profile.ports);

  for (const port of tryPorts) {
    process.stdout.write(step(`Cek port ${port}... `));
    const tcpOpen = await isPortOpen(port);

    if (!tcpOpen) {
      process.stdout.write(dim("tutup\n"));
      continue;
    }

    // TCP open → HTTP health check
    const health = await httpHealthCheck(port);
    if (health.ok) {
      const label =
        port === 80   ? "XAMPP / Laragon / WAMP / Apache" :
        port === 8080 ? "XAMPP Alt / PHP Built-in" :
        port === 8888 ? "MAMP" :
        profile.name;
      console.log(`${c.green}HTTP ${health.status}${c.reset} → ${c.bold}${label}${c.reset} (port ${port})`);
      console.log();
      return { port, label };
    } else {
      // Port terbuka tapi gak merespons HTTP — mungkin bukan web server
      process.stdout.write(warn(`port ${port} terbuka tapi tidak merespons HTTP, skip\n`));
    }
  }

  // Tidak ada yang cocok — tampilkan solusi
  console.log(fail(`Tidak ada server yang aktif terdeteksi`));
  if (mode !== "auto" && profile.hint) {
    console.log(info(`  ${profile.hint}`));
  } else {
    console.log(info("  Jalankan XAMPP / Laragon / WAMP / MAMP terlebih dahulu"));
    console.log(info("  Atau pakai SERVER_MODE=php di .env untuk PHP built-in\n"));
  }
  process.exit(1);
}

// ─────────────────────────────────────────────────
//  PHP BUILT-IN SERVER
// ─────────────────────────────────────────────────
async function startPhpBuiltIn(cfg) {
  console.log(`${bold("[ PHP Built-in Server ]")}`);

  // Cari port kosong kalau PHP_PORT sudah dipakai
  let port = cfg.phpPort;
  const inUse = await isPortOpen(port);
  if (inUse) {
    console.log(warn(`Port ${port} sudah dipakai, mencari port lain...`));
    port = await findFreePort(port + 1);
    console.log(ok(`Menggunakan port ${port}`));
  }

  console.log(step(`Root  : ${cfg.phpRoot}`));
  console.log(step(`Port  : ${port}\n`));

  return new Promise((resolve, reject) => {
    const php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", cfg.phpRoot], {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let started = false;

    php.stderr.on("data", (data) => {
      const msg = data.toString().trim();
      if (/Development Server.*started/i.test(msg) && !started) {
        started = true;
        console.log(ok("PHP server jalan!"));
      }
      // Log PHP error/warning
      if (/Fatal error|Parse error|Warning/i.test(msg)) {
        console.log(`${c.yellow}[PHP]${c.reset} ${msg}`);
      }
    });

    php.on("error", (err) => {
      if (err.code === "ENOENT") {
        console.log(fail("Perintah 'php' tidak ditemukan di PATH!"));
        console.log(info("  Windows: Install XAMPP → tambah C:\\xampp\\php ke PATH"));
        console.log(info("  Linux  : sudo apt install php"));
        console.log(info("  Mac    : brew install php\n"));
      }
      reject(err);
    });

    php.on("exit", (code) => {
      if (code && code !== 0) {
        console.log(fail(`PHP process berhenti dengan kode ${code}`));
      }
    });

    // Tunggu PHP ready dengan polling
    const maxWait = 5000;
    const interval = 200;
    let waited = 0;
    const poll = setInterval(async () => {
      waited += interval;
      if (await isPortOpen(port)) {
        clearInterval(poll);
        resolve({ process: php, port });
      } else if (waited >= maxWait) {
        clearInterval(poll);
        reject(new Error("PHP server tidak kunjung jalan (timeout 5 detik)"));
      }
    }, interval);
  });
}

// ─────────────────────────────────────────────────
//  NGROK TUNNEL (dengan retry)
// ─────────────────────────────────────────────────
async function startNgrokWithRetry(port, cfg) {
  console.log(`${bold("[ Ngrok Tunnel ]")}`);

  const options = { addr: port, authtoken: cfg.ngrokToken };
  if (cfg.ngrokDomain) options.domain = cfg.ngrokDomain;

  for (let attempt = 1; attempt <= RETRY_MAX; attempt++) {
    try {
      process.stdout.write(step(`Koneksi ke ngrok (percobaan ${attempt}/${RETRY_MAX})... `));
      const listener = await ngrok.forward(options);
      const url = listener.url();
      console.log(`${c.green}OK${c.reset}`);
      console.log();
      return url;
    } catch (err) {
      console.log(`${c.red}Gagal${c.reset}`);
      const errMsg = err.message || "";

      // Error-specific hints
      if (/authtoken|authentication/i.test(errMsg)) {
        console.log(fail("Token ngrok tidak valid!"));
        console.log(info("  Cek token di: https://dashboard.ngrok.com/get-started/your-authtoken"));
        process.exit(1);
      }
      if (/tunnel session.*limit|free.*plan/i.test(errMsg)) {
        console.log(fail("Batas tunnel ngrok gratis tercapai!"));
        console.log(info("  Tutup session ngrok lain di https://dashboard.ngrok.com/tunnels"));
        process.exit(1);
      }
      if (/ECONNREFUSED|network/i.test(errMsg)) {
        console.log(warn("  Tidak bisa konek ke server ngrok. Cek koneksi internet."));
      }

      if (attempt < RETRY_MAX) {
        const delay = RETRY_DELAY * attempt;
        console.log(dim(`  Coba lagi dalam ${delay / 1000} detik...`));
        await new Promise((r) => setTimeout(r, delay));
      } else {
        console.log(fail("Ngrok gagal setelah " + RETRY_MAX + " percobaan"));
        console.log(dim(`  Detail: ${errMsg}`));
        process.exit(1);
      }
    }
  }
}

// ─────────────────────────────────────────────────
//  LIVE REQUEST LOGGER
// ─────────────────────────────────────────────────
let lastReqId = null;
let reqCount  = 0;

function startRequestLogger() {
  const METHOD_COLOR = {
    GET   : c.green,
    POST  : c.cyan,
    PUT   : c.yellow,
    DELETE: c.red,
    PATCH : c.magenta,
  };

  const pollFn = () => {
    http.get(NGROK_API, (res) => {
      let raw = "";
      res.on("data", (d) => (raw += d));
      res.on("end", () => {
        try {
          const { requests } = JSON.parse(raw);
          if (!requests || !requests.length) return;

          const newest = requests[0];
          if (newest.id === lastReqId) return;
          lastReqId = newest.id;
          reqCount++;

          const method  = newest.request?.method || "GET";
          const uri     = newest.request?.uri    || "/";
          const status  = newest.response?.status || "...";
          const ts      = new Date().toLocaleTimeString("id-ID");
          const mColor  = METHOD_COLOR[method] || c.white;
          const sColor  = status < 400 ? c.green : c.red;

          console.log(
            `${c.dim}[${ts}]${c.reset} ` +
            `${mColor}${method.padEnd(6)}${c.reset} ` +
            `${sColor}${status}${c.reset} ` +
            `${uri}`
          );
        } catch (_) {}
      });
    }).on("error", () => {}); // Ngrok API belum siap, abaikan
  };

  return setInterval(pollFn, 1200);
}

// ─────────────────────────────────────────────────
//  PRINT SUMMARY
// ─────────────────────────────────────────────────
function printSummary(publicUrl, localPort, serverLabel, cfg) {
  const sep = "═".repeat(62);

  // Deteksi folder htdocs berdasarkan mode
  const mode        = cfg.serverMode;
  const platform    = process.platform;
  const hintPaths   = FOLDER_HINTS[mode] || [];
  const existingDir = hintPaths.find((p) => fs.existsSync(p));
  const htdocsHint  = existingDir
    ? `${existingDir}${path.sep}TopZone${path.sep}Home`
    : mode === "php"
    ? cfg.phpRoot
    : `(sesuai folder www / htdocs aplikasi kamu)`;

  console.log(`${c.cyan}${sep}${c.reset}`);
  console.log(`  ${c.bgGreen}${c.bold}  ✅  TopZone ONLINE!  ${c.reset}\n`);
  console.log(`  ${c.bold}Server${c.reset}         : ${serverLabel}`);
  console.log(`  ${c.bold}Lokal${c.reset}          : ${hi(`http://localhost:${localPort}`)}`);
  console.log(`  ${c.bold}Publik (ngrok)${c.reset} : ${hi(publicUrl)}`);
  console.log(`  ${c.bold}Ngrok UI${c.reset}       : ${hi("http://localhost:4040")}\n`);
  console.log(`  ${c.bold}📁 Folder project:${c.reset}`);
  console.log(`     ${c.dim}${htdocsHint}${c.reset}\n`);
  console.log(`  ${c.bold}📌 URL Webhook / Payment Gateway:${c.reset}`);
  console.log(`     Callback    : ${c.cyan}${publicUrl}/callback.php${c.reset}`);
  console.log(`     Ambil Token : ${c.cyan}${publicUrl}/Checkout/ambil_token.php${c.reset}`);
  console.log(`${c.cyan}${sep}${c.reset}`);
  console.log(dim("\n  📡 Live request log (Ctrl+C untuk berhenti):\n"));
}

// ─────────────────────────────────────────────────
//  GRACEFUL SHUTDOWN
// ─────────────────────────────────────────────────
function setupShutdown(phpProc, loggerInterval) {
  let shutting = false;

  async function shutdown(sig) {
    if (shutting) return;
    shutting = true;

    console.log(`\n\n${c.yellow}🛑  ${sig} — mematikan server...${c.reset}`);
    clearInterval(loggerInterval);

    try {
      await ngrok.disconnect();
      console.log(ok("Ngrok tunnel ditutup"));
    } catch (_) {}

    if (phpProc && !phpProc.killed) {
      phpProc.kill("SIGTERM");
      console.log(ok("PHP server dihentikan"));
    }

    console.log(`\n${c.dim}Total request diterima: ${reqCount}${c.reset}`);
    console.log(`${c.green}👋  Sampai jumpa!${c.reset}\n`);
    process.exit(0);
  }

  process.on("SIGINT",  () => shutdown("SIGINT"));
  process.on("SIGTERM", () => shutdown("SIGTERM"));

  // Tangkap uncaught errors supaya server tidak langsung crash diam-diam
  process.on("uncaughtException", (err) => {
    console.log(`\n${fail("Uncaught error:")} ${err.message}`);
    console.log(dim(err.stack));
    shutdown("uncaughtException");
  });

  process.on("unhandledRejection", (reason) => {
    console.log(`\n${fail("Unhandled rejection:")} ${reason}`);
    shutdown("unhandledRejection");
  });
}

// ─────────────────────────────────────────────────
//  MAIN
// ─────────────────────────────────────────────────
(async () => {
  // Banner
  console.clear();
  console.log(`${c.cyan}${c.bold}`);
  console.log("  ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗");
  console.log("     ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝");
  console.log("     ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗  ");
  console.log("     ██║   ██║   ██║██╔═══╝    ██╔╝ ██║   ██║██║╚████║██╔══╝  ");
  console.log("     ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗");
  console.log("     ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝");
  console.log(`${c.reset}${c.dim}                    Universal Ngrok Launcher v2.0${c.reset}\n`);

  // Setup wizard kalau .env belum ada / token belum diisi
  const hasDotEnv  = fs.existsSync(ENV_FILE);
  const envContent = hasDotEnv ? fs.readFileSync(ENV_FILE, "utf8") : "";
  const tokenMissing = !hasDotEnv || envContent.includes("isi_token") ||
                       !envContent.includes("NGROK_AUTHTOKEN=") ||
                       envContent.match(/NGROK_AUTHTOKEN=\s*$/m);

  if (tokenMissing) {
    await runSetupWizard();
  }

  const cfg  = loadConfig();
  const mode = cfg.serverMode;

  // Validasi mode
  if (!APP_PROFILES[mode]) {
    console.log(fail(`SERVER_MODE tidak dikenal: "${mode}"`));
    console.log(info(`  Pilihan: ${Object.keys(APP_PROFILES).join(" | ")}`));
    process.exit(1);
  }

  // Preflight
  await preflight(cfg);

  // Resolve server & port
  let localPort  = cfg.localPort;
  let phpProcess = null;
  let serverLabel;

  if (mode === "php") {
    const result = await startPhpBuiltIn(cfg);
    localPort    = result.port;
    phpProcess   = result.process;
    serverLabel  = `PHP Built-in (port ${localPort})`;

  } else if (mode === "custom") {
    console.log(`${bold("[ Custom Server ]")}`);
    const open = await isPortOpen(localPort);
    if (!open) {
      console.log(fail(`Tidak ada server di port ${localPort}`));
      console.log(info(`  Jalankan servermu dulu, lalu coba lagi\n`));
      process.exit(1);
    }
    const health = await httpHealthCheck(localPort);
    if (!health.ok) {
      console.log(warn(`Port ${localPort} terbuka tapi tidak merespons HTTP. Lanjut tetap...`));
    } else {
      console.log(ok(`Server aktif di port ${localPort} (HTTP ${health.status})`));
    }
    serverLabel = `Custom Server (port ${localPort})`;
    console.log();

  } else {
    const detected = await detectServer(mode, cfg);
    localPort   = detected.port;
    serverLabel = detected.label;
  }

  // Ngrok
  const publicUrl     = await startNgrokWithRetry(localPort, cfg);
  const loggerInterval = cfg.logRequests ? startRequestLogger() : null;

  // Shutdown handler
  setupShutdown(phpProcess, loggerInterval);

  // Summary
  printSummary(publicUrl, localPort, serverLabel, cfg);

})();
