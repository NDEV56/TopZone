/**
 * TopZone — Universal Ngrok Launcher v3.0
 * ════════════════════════════════════════
 * Full logging system:
 *   • 5 level: COMMON | UNCOMMON | WARNING | CRITICAL | ERROR
 *   • Live tail dari logs/topzone.log (JSON per baris)
 *   • Tulis ke logs/ per level + master log
 *   • Stats counter di terminal
 *   • Preflight, wizard, retry ngrok, health check
 */

require("dotenv").config();
const { spawn, execSync } = require("child_process");
const net      = require("net");
const http     = require("http");
const fs       = require("fs");
const path     = require("path");
const readline = require("readline");

let ngrok;
try   { ngrok = require("@ngrok/ngrok"); }
catch { die("❌  npm install belum dijalankan!\n    Jalankan: npm install"); }

// ═══════════════════════════════════════════════
//  ANSI COLORS
// ═══════════════════════════════════════════════
const C = {
  reset  : "\x1b[0m",  bold : "\x1b[1m",  dim   : "\x1b[2m",
  red    : "\x1b[31m", green: "\x1b[32m",  yellow: "\x1b[33m",
  blue   : "\x1b[34m", cyan : "\x1b[36m",  white : "\x1b[37m",
  magenta: "\x1b[35m",
  bgBlack: "\x1b[40m", bgRed: "\x1b[41m",  bgGreen : "\x1b[42m",
  bgYellow:"\x1b[43m", bgBlue:"\x1b[44m",  bgMagenta:"\x1b[45m",
  bgCyan : "\x1b[46m",
};

function die(msg) { console.error(msg); process.exit(1); }
const bold  = s => `${C.bold}${s}${C.reset}`;
const dim   = s => `${C.dim}${s}${C.reset}`;
const hi    = s => `${C.cyan}${C.bold}${s}${C.reset}`;

// ═══════════════════════════════════════════════
//  LOG LEVEL CONFIG
// ═══════════════════════════════════════════════
const LEVELS = {
  common  : { color: C.green,   bg: C.bgGreen,   icon: "●", label: "COMMON  " },
  uncommon: { color: C.blue,    bg: C.bgBlue,     icon: "◆", label: "UNCOMMON" },
  warning : { color: C.yellow,  bg: C.bgYellow,   icon: "▲", label: "WARNING " },
  critical: { color: C.magenta, bg: C.bgMagenta,  icon: "★", label: "CRITICAL" },
  error   : { color: C.red,     bg: C.bgRed,      icon: "✖", label: "ERROR   " },
};

// ═══════════════════════════════════════════════
//  PATHS & CONFIG
// ═══════════════════════════════════════════════
const ROOT      = __dirname;
const LOG_DIR   = path.join(ROOT, "logs");
const LOG_MAIN  = path.join(LOG_DIR, "topzone.log");    // PHP menulis ke sini (JSON)
const LOG_FILES = {
  common  : path.join(LOG_DIR, "common.log"),
  uncommon: path.join(LOG_DIR, "uncommon.log"),
  warning : path.join(LOG_DIR, "warning.log"),
  critical: path.join(LOG_DIR, "critical.log"),
  error   : path.join(LOG_DIR, "error.log"),
};
const ENV_FILE  = path.join(ROOT, ".env");
const AUTO_PORTS= [80, 8080, 8888, 3000, 5000, 8000];

const APP_PROFILES = {
  auto   : { name: "Auto-detect",  ports: AUTO_PORTS },
  xampp  : { name: "XAMPP",        ports: [80, 8080], hint: "Pastikan Apache di XAMPP Control Panel sudah START" },
  laragon: { name: "Laragon",      ports: [80, 8080], hint: "Pastikan Laragon sudah dibuka dan service ON" },
  wamp   : { name: "WampServer",   ports: [80, 8080], hint: "Klik icon WampServer → Start All Services" },
  mamp   : { name: "MAMP",         ports: [8888, 80], hint: "Buka MAMP lalu klik Start Servers" },
  php    : { name: "PHP Built-in", ports: [],         hint: "PHP harus terinstall: php -v" },
  custom : { name: "Custom",       ports: [],         hint: "Set LOCAL_PORT di .env" },
};

const FOLDER_HINTS = {
  xampp  : ["C:\\xampp\\htdocs", "/Applications/XAMPP/htdocs", "/opt/lampp/htdocs"],
  laragon: ["C:\\laragon\\www"],
  wamp   : ["C:\\wamp64\\www", "C:\\wamp\\www"],
  mamp   : ["/Applications/MAMP/htdocs"],
};

// ═══════════════════════════════════════════════
//  LOG STATS
// ═══════════════════════════════════════════════
const stats = { common:0, uncommon:0, warning:0, critical:0, error:0, total:0 };

// ═══════════════════════════════════════════════
//  SETUP LOG DIRECTORY & FILES
// ═══════════════════════════════════════════════
function setupLogDir() {
  if (!fs.existsSync(LOG_DIR)) fs.mkdirSync(LOG_DIR, { recursive: true });
  // Buat file log kosong kalau belum ada
  for (const f of Object.values(LOG_FILES)) {
    if (!fs.existsSync(f)) fs.writeFileSync(f, "");
  }
  if (!fs.existsSync(LOG_MAIN)) fs.writeFileSync(LOG_MAIN, "");
}

// ═══════════════════════════════════════════════
//  SERVER-SIDE LOGGER (log dari Node sendiri)
// ═══════════════════════════════════════════════
function serverLog(level, event, msg, data = {}) {
  const entry = {
    ts    : new Date().toISOString().replace("T", " ").slice(0, 23),
    level,
    event,
    msg,
    src   : "server",
    data,
  };

  // Tulis ke file level
  const line = JSON.stringify(entry) + "\n";
  if (LOG_FILES[level]) fs.appendFileSync(LOG_FILES[level], line);

  // Tampilkan di terminal
  printLogEntry(entry);
}

// ═══════════════════════════════════════════════
//  PRINT LOG ENTRY (terminal)
// ═══════════════════════════════════════════════
function printLogEntry(entry) {
  const lvl = LEVELS[entry.level] || LEVELS.common;
  const src  = entry.src === "server" ? dim("[SRV]") : dim("[PHP]");
  const time = dim(entry.ts.slice(11, 23));   // HH:MM:SS.mmm
  const badge= `${lvl.color}${C.bold}${lvl.icon} ${lvl.label}${C.reset}`;
  const evt  = `${C.bold}${entry.event}${C.reset}`;

  stats[entry.level] = (stats[entry.level] || 0) + 1;
  stats.total++;

  let line = `${time} ${badge} ${src} ${evt}`;

  // Tampilkan msg kalau berbeda dari event
  if (entry.msg && entry.msg !== entry.event) {
    line += `  ${C.white}${entry.msg}${C.reset}`;
  }

  // Tampilkan data penting secara inline
  const skipKeys = ["user_agent", "referer"];
  const inlineData = Object.entries(entry.data || {})
    .filter(([k]) => !skipKeys.includes(k))
    .map(([k, v]) => `${dim(k + ":")}${v}`)
    .join("  ");
  if (inlineData) line += `\n          ${C.dim}└─${C.reset} ${inlineData}`;

  console.log(line);
}

// ═══════════════════════════════════════════════
//  PRINT STATS BAR
// ═══════════════════════════════════════════════
function printStatsBar() {
  const bar = Object.entries(LEVELS)
    .map(([k, v]) => `${v.color}${v.icon}${k.padEnd(8)}${C.reset}${C.bold}${stats[k]}${C.reset}`)
    .join("  ");
  process.stdout.write(`\r  ${bar}  ${dim("total:")}${C.bold}${stats.total}${C.reset}  `);
}

// ═══════════════════════════════════════════════
//  TAIL LOG FILE (baca logs dari PHP)
// ═══════════════════════════════════════════════
let tailPos = 0;

function startLogTail() {
  // Set posisi awal ke akhir file (abaikan log lama)
  try {
    const st = fs.statSync(LOG_MAIN);
    tailPos = st.size;
  } catch { tailPos = 0; }

  const interval = setInterval(() => {
    try {
      const st = fs.statSync(LOG_MAIN);
      if (st.size <= tailPos) return;

      const buf = Buffer.alloc(st.size - tailPos);
      const fd  = fs.openSync(LOG_MAIN, "r");
      fs.readSync(fd, buf, 0, buf.length, tailPos);
      fs.closeSync(fd);
      tailPos = st.size;

      const lines = buf.toString("utf8").split("\n").filter(Boolean);
      for (const line of lines) {
        try {
          const entry = JSON.parse(line);
          entry.src   = "php";

          // Tulis ke file level yang sesuai
          if (LOG_FILES[entry.level]) {
            fs.appendFileSync(LOG_FILES[entry.level], line + "\n");
          }

          // Tampilkan di terminal
          printLogEntry(entry);
          printStatsBar();

        } catch { /* baris bukan JSON, abaikan */ }
      }
    } catch { /* file belum ada */ }
  }, 400);

  return interval;
}

// ═══════════════════════════════════════════════
//  SETUP WIZARD
// ═══════════════════════════════════════════════
async function runSetupWizard() {
  const rl  = readline.createInterface({ input: process.stdin, output: process.stdout });
  const ask = q => new Promise(res => rl.question(q, res));

  console.log(`\n${C.cyan}${C.bold}╔══════════════════════════════════════════╗`);
  console.log(`║   🧙  Setup Wizard — Konfigurasi Pertama  ║`);
  console.log(`╚══════════════════════════════════════════╝${C.reset}\n`);
  console.log(dim("  Daftar ngrok gratis: https://dashboard.ngrok.com\n"));

  const token = (await ask(`${C.yellow}▸ NGROK_AUTHTOKEN${C.reset}: `)).trim();
  if (!token) { rl.close(); die("\n❌  Token tidak boleh kosong. Jalankan ulang.\n"); }

  console.log(`\n${C.cyan}Server mode:${C.reset}`);
  const modes = Object.keys(APP_PROFILES);
  modes.forEach((m, i) =>
    console.log(`  ${C.dim}${i+1}.${C.reset} ${m.padEnd(10)} ${dim(APP_PROFILES[m].hint || "")}`)
  );
  const modeInput = (await ask(`\n${C.yellow}▸ Pilih mode (1-${modes.length}) atau nama${C.reset} [default: auto]: `)).trim();
  const idx = parseInt(modeInput, 10);
  let serverMode = "auto";
  if (modeInput) serverMode = (!isNaN(idx) && modes[idx-1]) ? modes[idx-1] : modeInput.toLowerCase();

  let localPort = "";
  if (serverMode === "custom") {
    localPort = (await ask(`${C.yellow}▸ LOCAL_PORT${C.reset}: `)).trim();
  }

  const lines = [
    `# TopZone .env — dibuat oleh setup wizard`,
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

  console.log(`\n${C.green}✔${C.reset}  File .env berhasil dibuat!\n`);
  require("dotenv").config({ path: ENV_FILE, override: true });
}

// ═══════════════════════════════════════════════
//  LOAD CONFIG
// ═══════════════════════════════════════════════
function loadConfig() {
  if (fs.existsSync(ENV_FILE)) require("dotenv").config({ path: ENV_FILE });
  return {
    ngrokToken : process.env.NGROK_AUTHTOKEN,
    ngrokDomain: process.env.NGROK_DOMAIN  || null,
    serverMode : (process.env.SERVER_MODE  || "auto").toLowerCase(),
    localPort  : parseInt(process.env.LOCAL_PORT || "0", 10) || 0,
    phpPort    : parseInt(process.env.PHP_PORT   || "8080", 10),
    phpRoot    : process.env.PHP_ROOT || path.join(ROOT, "Home"),
  };
}

// ═══════════════════════════════════════════════
//  PORT UTILS
// ═══════════════════════════════════════════════
function isPortOpen(port, host = "127.0.0.1", ms = 900) {
  return new Promise(res => {
    const s = new net.Socket();
    s.setTimeout(ms);
    s.connect(port, host, () => { s.destroy(); res(true); });
    s.on("error",   () => { s.destroy(); res(false); });
    s.on("timeout", () => { s.destroy(); res(false); });
  });
}

function httpHealthCheck(port, ms = 3000) {
  return new Promise(res => {
    const req = http.get(
      { hostname:"127.0.0.1", port, path:"/", timeout: ms },
      r => res({ ok: true, status: r.statusCode })
    );
    req.on("error",   () => res({ ok: false }));
    req.on("timeout", () => { req.destroy(); res({ ok: false }); });
  });
}

async function findFreePort(start) {
  for (let p = start; p < start + 20; p++) {
    if (!await isPortOpen(p)) return p;
  }
  throw new Error("Tidak ada port kosong di range " + start + "-" + (start+20));
}

// ═══════════════════════════════════════════════
//  PREFLIGHT
// ═══════════════════════════════════════════════
async function preflight(cfg) {
  console.log(`\n${bold("[ ⚙  Preflight Checks ]")}`);

  const nodeVer = parseInt(process.versions.node.split(".")[0], 10);
  if (nodeVer < 16) die(` ✖  Node.js ${process.versions.node} terlalu lama — butuh >= 16`);
  console.log(` ${C.green}✔${C.reset}  Node.js ${process.versions.node}`);

  if (cfg.serverMode === "php") {
    try {
      const ver = execSync("php -r \"echo PHP_VERSION;\"", { timeout:3000 }).toString().trim();
      console.log(` ${C.green}✔${C.reset}  PHP ${ver}`);
    } catch {
      console.log(` ${C.red}✖${C.reset}  PHP tidak ditemukan di PATH`);
      console.log(`    ${dim("Windows: tambah C:\\xampp\\php ke PATH")}`);
      console.log(`    ${dim("Linux  : sudo apt install php")}`);
      console.log(`    ${dim("Mac    : brew install php")}`);
      process.exit(1);
    }
    if (!fs.existsSync(cfg.phpRoot)) {
      die(` ✖  Folder PHP tidak ditemukan: ${cfg.phpRoot}\n    Set PHP_ROOT di .env`);
    }
  }

  if (!cfg.ngrokToken || cfg.ngrokToken.includes("isi_token")) {
    die(` ✖  NGROK_AUTHTOKEN belum diisi di .env\n    https://dashboard.ngrok.com/get-started/your-authtoken`);
  }
  console.log(` ${C.green}✔${C.reset}  Ngrok token OK`);

  if (cfg.serverMode === "custom" && !cfg.localPort)
    die(` ✖  Mode custom butuh LOCAL_PORT di .env`);

  // Pastikan log dir ada
  setupLogDir();
  console.log(` ${C.green}✔${C.reset}  Log directory: ${LOG_DIR}`);
  console.log();
}

// ═══════════════════════════════════════════════
//  SERVER DETECTION
// ═══════════════════════════════════════════════
async function detectServer(mode, cfg) {
  console.log(`${bold("[ 🔍  Deteksi Server ]")}`);

  const profile  = APP_PROFILES[mode] || APP_PROFILES.auto;
  const tryPorts = cfg.localPort ? [cfg.localPort]
    : (mode === "auto" ? AUTO_PORTS : profile.ports);

  for (const port of tryPorts) {
    process.stdout.write(`    Cek port ${String(port).padEnd(5)} ... `);
    const tcpOpen = await isPortOpen(port);
    if (!tcpOpen) { process.stdout.write(dim("tutup\n")); continue; }

    const health = await httpHealthCheck(port);
    if (health.ok) {
      const label =
        port===80   ? "XAMPP / Laragon / WAMP / Apache" :
        port===8080 ? "XAMPP Alt / PHP Built-in" :
        port===8888 ? "MAMP" : profile.name;
      process.stdout.write(`${C.green}HTTP ${health.status}${C.reset} → ${C.bold}${label}${C.reset}\n\n`);
      return { port, label };
    } else {
      process.stdout.write(`${C.yellow}port terbuka, bukan web server — skip${C.reset}\n`);
    }
  }

  console.log(` ${C.red}✖${C.reset}  Tidak ada server yang terdeteksi`);
  if (profile.hint) console.log(`    ${dim(profile.hint)}`);
  console.log(`    ${dim("Atau pakai SERVER_MODE=php di .env")}\n`);
  process.exit(1);
}

// ═══════════════════════════════════════════════
//  PHP BUILT-IN
// ═══════════════════════════════════════════════
async function startPhpBuiltIn(cfg) {
  console.log(`${bold("[ 🐘  PHP Built-in Server ]")}`);
  let port = cfg.phpPort;
  if (await isPortOpen(port)) {
    console.log(`    ${C.yellow}Port ${port} sudah dipakai, mencari port lain...${C.reset}`);
    port = await findFreePort(port + 1);
  }
  console.log(`    Root: ${cfg.phpRoot}`);
  console.log(`    Port: ${port}\n`);

  return new Promise((resolve, reject) => {
    const php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", cfg.phpRoot], {
      stdio: ["ignore","pipe","pipe"],
      shell: process.platform === "win32",
    });

    php.stderr.on("data", data => {
      const msg = data.toString().trim();
      if (/started/i.test(msg)) console.log(` ${C.green}✔${C.reset}  PHP server jalan\n`);
      if (/Fatal|Parse error/i.test(msg))
        serverLog("error", "PHP_STDERR", msg);
    });

    php.on("error", err => {
      if (err.code === "ENOENT") die(" ✖  Perintah 'php' tidak ditemukan di PATH");
      reject(err);
    });

    const poll = setInterval(async () => {
      if (await isPortOpen(port)) { clearInterval(poll); resolve({ process: php, port }); }
    }, 200);
    setTimeout(() => { clearInterval(poll); reject(new Error("PHP timeout")); }, 6000);
  });
}

// ═══════════════════════════════════════════════
//  NGROK (dengan retry)
// ═══════════════════════════════════════════════
async function startNgrok(port, cfg) {
  console.log(`${bold("[ 🚇  Ngrok Tunnel ]")}`);
  const opts = { addr: port, authtoken: cfg.ngrokToken };
  if (cfg.ngrokDomain) opts.domain = cfg.ngrokDomain;

  for (let i = 1; i <= 3; i++) {
    try {
      process.stdout.write(`    Koneksi (percobaan ${i}/3) ... `);
      const listener = await ngrok.forward(opts);
      const url = listener.url();
      console.log(`${C.green}OK${C.reset}\n`);
      return url;
    } catch (err) {
      const msg = err.message || "";
      console.log(`${C.red}Gagal${C.reset}`);
      if (/authtoken|auth/i.test(msg)) {
        serverLog("error","NGROK_AUTH_FAILED","Token ngrok tidak valid",{ hint:"https://dashboard.ngrok.com" });
        process.exit(1);
      }
      if (/limit|session/i.test(msg)) {
        serverLog("error","NGROK_SESSION_LIMIT","Batas session ngrok tercapai",{ hint:"Tutup session lain di dashboard" });
        process.exit(1);
      }
      if (i < 3) {
        const delay = 1500 * i;
        serverLog("warning","NGROK_RETRY",`Retry dalam ${delay/1000}s`,{ attempt: i });
        await new Promise(r => setTimeout(r, delay));
      } else {
        serverLog("error","NGROK_FAILED","Ngrok gagal setelah 3 percobaan",{ detail: msg });
        process.exit(1);
      }
    }
  }
}

// ═══════════════════════════════════════════════
//  PRINT SUMMARY
// ═══════════════════════════════════════════════
function printSummary(publicUrl, localPort, serverLabel, cfg) {
  const sep = "═".repeat(64);

  const hintPaths  = FOLDER_HINTS[cfg.serverMode] || [];
  const existingDir= hintPaths.find(p => fs.existsSync(p));
  const folderHint = existingDir
    ? `${existingDir}${path.sep}TopZone${path.sep}Home`
    : cfg.serverMode === "php" ? cfg.phpRoot : "(folder www/htdocs aplikasi kamu)";

  console.log(`\n${C.cyan}${sep}${C.reset}`);
  console.log(`  ${C.bgGreen}${C.bold}  ✅  TopZone ONLINE & LOGGING AKTIF!  ${C.reset}\n`);
  console.log(`  ${bold("Server")}         : ${serverLabel}`);
  console.log(`  ${bold("Lokal")}          : ${hi("http://localhost:" + localPort)}`);
  console.log(`  ${bold("Publik")}         : ${hi(publicUrl)}`);
  console.log(`  ${bold("Ngrok UI")}       : ${hi("http://localhost:4040")}\n`);
  console.log(`  ${bold("📁 Folder project")} :`);
  console.log(`     ${dim(folderHint)}\n`);
  console.log(`  ${bold("📌 Webhook / Payment")} :`);
  console.log(`     Callback    : ${C.cyan}${publicUrl}/callback.php${C.reset}`);
  console.log(`     Ambil Token : ${C.cyan}${publicUrl}/Checkout/ambil_token.php${C.reset}\n`);
  console.log(`  ${bold("📂 Log files")} (logs/) :`);
  for (const [level, lv] of Object.entries(LEVELS)) {
    const f = path.basename(LOG_FILES[level]);
    console.log(`     ${lv.color}${lv.icon}${C.reset} ${f.padEnd(16)} ${dim("→ " + LOG_FILES[level])}`);
  }
  console.log(`${C.cyan}${sep}${C.reset}\n`);

  // Header kolom log
  console.log(
    `${dim("  TIME        ")}` +
    `${C.bold}LEVEL   ${C.reset}` +
    `${dim("SRC  ")}` +
    `${C.bold}EVENT${C.reset}`
  );
  console.log(dim("  " + "─".repeat(60)));
}

// ═══════════════════════════════════════════════
//  GRACEFUL SHUTDOWN
// ═══════════════════════════════════════════════
function setupShutdown(phpProc, tailInterval) {
  let stopping = false;
  async function shutdown(sig) {
    if (stopping) return;
    stopping = true;
    clearInterval(tailInterval);

    console.log(`\n\n${C.yellow}🛑  ${sig} — mematikan server...${C.reset}`);

    // Final stats
    console.log(`\n${bold("  [ Log Stats Sesi Ini ]")}`);
    for (const [level, lv] of Object.entries(LEVELS)) {
      const n = stats[level];
      if (n > 0) console.log(`  ${lv.color}${lv.icon}${C.reset} ${level.padEnd(10)} : ${C.bold}${n}${C.reset}`);
    }
    console.log(`  ${"─".repeat(28)}`);
    console.log(`  ${C.bold}Total events${C.reset}  : ${C.bold}${stats.total}${C.reset}`);

    serverLog("common","SERVER_STOP","Server dimatikan",{ total_events: stats.total });

    try   { await ngrok.disconnect(); console.log(`\n ${C.green}✔${C.reset}  Ngrok ditutup`); }
    catch { }

    if (phpProc && !phpProc.killed) {
      phpProc.kill("SIGTERM");
      console.log(` ${C.green}✔${C.reset}  PHP server dihentikan`);
    }
    console.log(`\n${C.green}👋  Sampai jumpa!${C.reset}\n`);
    process.exit(0);
  }

  process.on("SIGINT",  () => shutdown("SIGINT"));
  process.on("SIGTERM", () => shutdown("SIGTERM"));
  process.on("uncaughtException", err => {
    serverLog("error","UNCAUGHT_EXCEPTION", err.message, { stack: err.stack?.slice(0,300) });
    shutdown("uncaughtException");
  });
  process.on("unhandledRejection", reason => {
    serverLog("error","UNHANDLED_REJECTION", String(reason));
    shutdown("unhandledRejection");
  });
}

// ═══════════════════════════════════════════════
//  MAIN
// ═══════════════════════════════════════════════
(async () => {
  console.clear();

  // ASCII Banner
  console.log(`${C.cyan}${C.bold}`);
  console.log("  ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗");
  console.log("     ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝");
  console.log("     ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗  ");
  console.log("     ██║   ██║   ██║██╔═══╝    ██╔╝ ██║   ██║██║╚████║██╔══╝  ");
  console.log("     ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗");
  console.log("     ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝");
  console.log(`${C.reset}${C.dim}                   Universal Logger v3.0${C.reset}\n`);

  // Wizard jika .env belum ada / token kosong
  const hasDotEnv = fs.existsSync(ENV_FILE);
  const envRaw    = hasDotEnv ? fs.readFileSync(ENV_FILE, "utf8") : "";
  const needWizard= !hasDotEnv || !envRaw.includes("NGROK_AUTHTOKEN=")
                  || /NGROK_AUTHTOKEN=\s*$|NGROK_AUTHTOKEN=isi_/m.test(envRaw);
  if (needWizard) await runSetupWizard();

  const cfg  = loadConfig();
  const mode = cfg.serverMode;

  if (!APP_PROFILES[mode]) {
    die(`❌  SERVER_MODE tidak dikenal: "${mode}"\n    Pilihan: ${Object.keys(APP_PROFILES).join(" | ")}`);
  }

  await preflight(cfg);

  let localPort = cfg.localPort;
  let phpProcess= null;
  let serverLabel;

  if (mode === "php") {
    const r  = await startPhpBuiltIn(cfg);
    localPort= r.port; phpProcess = r.process;
    serverLabel = `PHP Built-in (port ${localPort})`;
  } else if (mode === "custom") {
    console.log(`${bold("[ 🔌  Custom Server ]")}`);
    if (!await isPortOpen(localPort))
      die(` ✖  Tidak ada server di port ${localPort}. Jalankan dulu.\n`);
    serverLabel = `Custom (port ${localPort})`;
    console.log(` ${C.green}✔${C.reset}  Server aktif di port ${localPort}\n`);
  } else {
    const detected = await detectServer(mode, cfg);
    localPort  = detected.port;
    serverLabel= detected.label;
  }

  const publicUrl  = await startNgrok(localPort, cfg);
  const tailInterval = startLogTail();

  setupShutdown(phpProcess, tailInterval);
  printSummary(publicUrl, localPort, serverLabel, cfg);

  // Log server start
  serverLog("common", "SERVER_START", `Server online — ${serverLabel}`, {
    local  : `http://localhost:${localPort}`,
    public : publicUrl,
    log_dir: LOG_DIR,
  });
})();
