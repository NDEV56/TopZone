/**
 * lib/detector.js — deteksi web server lokal yang sedang jalan
 * ────────────────────────────────────────────────────────────
 * Mendukung deteksi: XAMPP, Laragon, WAMP, MAMP, AMPPS, OpenServer,
 * USBWebserver, EasyPHP, IIS, PHP built-in, Caddy, Nginx, Node dev server.
 *
 * Strategi:
 *   1. Cek port-port khas tiap server (TCP open?)
 *   2. Lakukan HTTP HEAD untuk konfirmasi server merespons
 *   3. Identifikasi via Server header bila tersedia
 *   4. Cek folder install khas untuk hint tambahan
 */

"use strict";

const fs   = require("fs");
const path = require("path");
const { isPortOpen, httpHealthCheck, hasCommand } = require("./utils");

/**
 * Daftar profil server yang dikenali.
 * Setiap profil:
 *   id      — kunci internal
 *   name    — nama display
 *   ports   — port khas (urut prioritas)
 *   paths   — path instalasi tipikal di OS yang relevan
 *   needsMySQL — apakah biasanya pakai MySQL/MariaDB juga
 *   tip     — instruksi singkat untuk user pemula
 */
const PROFILES = {
  xampp: {
    name: "XAMPP",
    ports: [80, 8080, 443],
    paths: {
      win32 : ["C:\\xampp", "D:\\xampp", "E:\\xampp"],
      linux : ["/opt/lampp"],
      darwin: ["/Applications/XAMPP", "/Applications/XAMPP/xamppfiles"],
    },
    needsMySQL: true,
    tip: "Buka XAMPP Control Panel → klik tombol Start di baris Apache (dan MySQL jika perlu).",
  },
  laragon: {
    name: "Laragon",
    ports: [80, 8080],
    paths: {
      win32: ["C:\\laragon", "D:\\laragon"],
    },
    needsMySQL: true,
    tip: "Buka Laragon → klik tombol Start All. Lalu pastikan icon Apache di taskbar berwarna hijau.",
  },
  wamp: {
    name: "WampServer",
    ports: [80, 8080],
    paths: {
      win32: ["C:\\wamp64", "C:\\wamp", "D:\\wamp64"],
    },
    needsMySQL: true,
    tip: "Klik kanan icon WampServer di taskbar → Start All Services. Tunggu icon berwarna hijau.",
  },
  mamp: {
    name: "MAMP",
    ports: [8888, 80, 8080],
    paths: {
      darwin: ["/Applications/MAMP"],
      win32 : ["C:\\MAMP"],
    },
    needsMySQL: true,
    tip: "Buka MAMP → klik tombol Start Servers di pojok kanan atas.",
  },
  ampps: {
    name: "AMPPS",
    ports: [80, 8080, 4406],
    paths: {
      win32 : ["C:\\Program Files\\Ampps", "C:\\Ampps"],
      darwin: ["/Applications/AMPPS"],
      linux : ["/usr/local/ampps"],
    },
    needsMySQL: true,
    tip: "Buka AMPPS → klik tombol Start di Apache.",
  },
  openserver: {
    name: "OpenServer Panel",
    ports: [80, 8080],
    paths: {
      win32: ["C:\\OpenServer", "D:\\OpenServer", "C:\\OSPanel"],
    },
    needsMySQL: true,
    tip: "Klik icon OpenServer di taskbar → Start (bendera hijau).",
  },
  usbwebserver: {
    name: "USBWebserver",
    ports: [8080, 80],
    paths: {
      win32: ["C:\\USBWebserver", "D:\\USBWebserver"],
    },
    needsMySQL: true,
    tip: "Buka USBWebserver.exe → tab General → pastikan kedua tombol Start di-klik.",
  },
  easyphp: {
    name: "EasyPHP",
    ports: [80, 8080],
    paths: {
      win32: ["C:\\Program Files (x86)\\EasyPHP", "C:\\EasyPHP", "C:\\Program Files\\EasyPHP"],
    },
    needsMySQL: true,
    tip: "Buka EasyPHP Dashboard → klik Start All.",
  },
  iis: {
    name: "Microsoft IIS",
    ports: [80, 443],
    paths: {
      win32: ["C:\\inetpub\\wwwroot"],
    },
    needsMySQL: false,
    tip: "Buka IIS Manager (Run → inetmgr) → pastikan Default Web Site sudah Started.",
  },
  caddy: {
    name: "Caddy",
    ports: [80, 443, 2015, 8080],
    paths: {},
    needsMySQL: false,
    tip: "Jalankan: caddy run di folder yang berisi Caddyfile.",
  },
  nginx: {
    name: "Nginx",
    ports: [80, 8080, 443],
    paths: {
      win32 : ["C:\\nginx"],
      linux : ["/etc/nginx", "/usr/local/nginx"],
      darwin: ["/usr/local/etc/nginx", "/opt/homebrew/etc/nginx"],
    },
    needsMySQL: false,
    tip: "Jalankan: sudo systemctl start nginx (Linux) atau buka layanan Nginx (Windows).",
  },
  php_builtin: {
    name: "PHP Built-in Server",
    ports: [8000, 8080, 8888, 3000],
    paths: {},
    needsMySQL: false,
    tip: "Jalankan: php -S 127.0.0.1:8080 -t Home (server sederhana untuk testing).",
  },
  node_dev: {
    name: "Node Dev Server",
    ports: [3000, 5000, 5173, 8000, 8080, 8081, 4200, 4321, 9000],
    paths: {},
    needsMySQL: false,
    tip: "Jalankan: npm run dev / npm start dari folder app Node-mu.",
  },
};

/** Cek folder instalasi untuk OS saat ini. */
function findInstalledProfiles() {
  const installed = [];
  const platform = process.platform;
  for (const id of Object.keys(PROFILES)) {
    const p = PROFILES[id];
    const candidates = (p.paths && p.paths[platform]) || [];
    for (const dir of candidates) {
      try {
        if (fs.existsSync(dir)) {
          installed.push({ id, name: p.name, path: dir, profile: p });
          break;
        }
      } catch (_) {}
    }
  }
  return installed;
}

/** Identifikasi server berdasarkan response header HTTP. */
function identifyByHeader(headers, port) {
  const server = String((headers && headers.server) || "").toLowerCase();
  if (server.includes("apache")) {
    // Apache umum (XAMPP/WAMP/Laragon/MAMP/AMPPS) — tidak bisa dibedakan dari header saja
    return port === 8888 ? "MAMP (Apache)" : "Apache (XAMPP/WAMP/Laragon)";
  }
  if (server.includes("microsoft-iis")) return "Microsoft IIS";
  if (server.includes("nginx"))         return "Nginx";
  if (server.includes("caddy"))         return "Caddy";
  if (server.includes("lighttpd"))      return "lighttpd";
  if (server.includes("php"))           return "PHP Built-in";
  if (server.startsWith("development server"))   return "PHP Built-in";
  if (headers && headers["x-powered-by"]) {
    const xp = String(headers["x-powered-by"]).toLowerCase();
    if (xp.includes("express")) return "Node.js (Express)";
    if (xp.includes("php"))     return "PHP Server";
    if (xp.includes("asp"))     return "ASP.NET / IIS";
  }
  return null;
}

/**
 * Scan port-port umum & laporkan server yang aktif.
 * Returns: array { port, label, headers, status }
 */
async function scanActiveServers(extraPorts = []) {
  const ports = new Set([
    80, 443, 8080, 8443, 8888, 3000, 5000, 5173, 4200, 4321, 8000,
    8001, 8008, 8081, 9000, 9090, 4406, 2015, 8088,
    ...extraPorts,
  ]);

  const results = [];
  await Promise.all(
    [...ports].map(async (port) => {
      const open = await isPortOpen(port);
      if (!open) return;
      const health = await httpHealthCheck(port, { method: "HEAD", timeoutMs: 1500 });
      if (!health.ok) {
        // Mungkin server tidak menerima HEAD, coba GET singkat
        const getH = await httpHealthCheck(port, { method: "GET", timeoutMs: 1500 });
        if (!getH.ok) return;
        results.push({
          port,
          status: getH.status,
          label : identifyByHeader(getH.headers, port) || "HTTP Server",
          headers: getH.headers,
        });
      } else {
        results.push({
          port,
          status: health.status,
          label : identifyByHeader(health.headers, port) || "HTTP Server",
          headers: health.headers,
        });
      }
    })
  );
  // Sort by port (low first)
  results.sort((a, b) => a.port - b.port);
  return results;
}

/**
 * Deteksi server untuk mode tertentu.
 * Returns: { port, label } atau throw error.
 */
async function detectForMode(mode, cfg) {
  // Custom: pakai LOCAL_PORT dari user
  if (mode === "custom") {
    if (!cfg.LOCAL_PORT) throw new Error("Mode 'custom' butuh LOCAL_PORT di .env");
    const open = await isPortOpen(cfg.LOCAL_PORT);
    if (!open) throw new Error(`Tidak ada server di port ${cfg.LOCAL_PORT}. Jalankan dulu lalu coba lagi.`);
    const health = await httpHealthCheck(cfg.LOCAL_PORT);
    return {
      port  : cfg.LOCAL_PORT,
      label : identifyByHeader(health.headers || {}, cfg.LOCAL_PORT) || "Custom Server",
    };
  }

  // PHP built-in: handled di tempat lain (dispawn)
  if (mode === "php") {
    // sentinel — caller akan spawn PHP
    return { port: cfg.PHP_PORT, label: "PHP Built-in", needsSpawn: true };
  }

  // Auto / preset — scan
  const profile = PROFILES[mode] || null;
  const tryPorts = cfg.LOCAL_PORT
    ? [cfg.LOCAL_PORT]
    : profile && profile.ports.length
    ? profile.ports
    : [80, 8080, 8888, 3000, 5000, 8000];

  for (const port of tryPorts) {
    const open = await isPortOpen(port);
    if (!open) continue;
    const h = await httpHealthCheck(port);
    if (!h.ok) continue;
    return {
      port,
      label: profile ? profile.name : (identifyByHeader(h.headers || {}, port) || "HTTP Server"),
    };
  }

  // Tidak ada yang aktif → fallback ke scan luas
  const allActive = await scanActiveServers();
  if (allActive.length) {
    const best = allActive[0];
    return { port: best.port, label: best.label };
  }

  // Beneran kosong
  const tip = profile ? profile.tip
    : "Jalankan XAMPP / Laragon / WAMP / MAMP / AMPPS dulu, atau pakai mode 'php' untuk PHP built-in.";
  const e = new Error("Tidak ada web server lokal yang merespons");
  e.tip = tip;
  throw e;
}

/** Cek apakah MySQL/MariaDB hidup di port khas. */
async function checkMySQL() {
  const ports = [3306, 3307];
  for (const p of ports) {
    if (await isPortOpen(p)) return { running: true, port: p };
  }
  return { running: false };
}

/** Diagnostik lengkap (untuk halaman "System Check" di GUI). */
async function diagnose() {
  const [installed, active, mysql] = await Promise.all([
    findInstalledProfiles(),
    scanActiveServers(),
    checkMySQL(),
  ]);

  return {
    nodeVersion : process.versions.node,
    platform    : process.platform,
    arch        : process.arch,
    hasGit      : hasCommand("git"),
    hasPhp      : hasCommand("php"),
    hasNgrok    : hasCommand("ngrok"),
    hasCloudflared: hasCommand("cloudflared"),
    hasLt       : hasCommand("lt"),
    installedServers: installed,
    activeServers   : active,
    mysql,
    profiles    : Object.keys(PROFILES),
  };
}

module.exports = {
  PROFILES,
  findInstalledProfiles,
  scanActiveServers,
  detectForMode,
  checkMySQL,
  diagnose,
  identifyByHeader,
};
