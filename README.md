# 🎛️ TopZone — Universal Server Launcher

> Server lokal PHP-mu, online di internet dalam 30 detik.
> Multi-tunnel (ngrok / Cloudflare / LocalTunnel / Serveo / Pinggy) + GUI control panel beginner-friendly + auto-update GitHub.

---

## ⚡ Cara Cepat (Pemula — 3 Langkah)

1. **Install Node.js** dari <https://nodejs.org/en/download> (LTS).
2. **Klik dua kali** file `start.bat` (Windows) atau `bash start.sh` (Mac/Linux).
3. **Pilih `1`** untuk GUI, ikuti wizard 5 langkah di browser.

Selesai. Server lokal akan terdeteksi otomatis dan tunnel publik dibuka.

> 📖 Panduan lengkap untuk pemula: **[docs/PANDUAN.md](docs/PANDUAN.md)**

---

## 🎯 Fitur Utama

### Multi-Tunnel Provider
- **ngrok** — paling stabil, gratis dengan daftar
- **cloudflared** — Cloudflare Tunnel (tanpa daftar)
- **localtunnel** — alternatif gratis npm
- **serveo** — via SSH, tanpa install
- **pinggy** — via SSH alternatif
- **none** — local-only

Auto-fallback: kalau provider pilihan gagal, otomatis coba yang lain.

### Deteksi Server Lokal Lengkap
XAMPP · Laragon · WAMP · MAMP · AMPPS · OpenServer · USBWebserver · EasyPHP · IIS · Caddy · Nginx · PHP Built-in · Node Dev Server.

### GUI Control Panel
Web panel di `http://127.0.0.1:4747` dengan:
- Setup wizard 5 step (untuk pemula)
- Tombol Start/Stop/Restart yang besar dan jelas
- Live log streaming dengan filter 6 kategori
- Diagnostik sistem
- Update GitHub satu klik
- Pengaturan terlindungi validasi

### Logger 6 Kategori
`common` · `uncommon` · `warning` · `critical` · `error` · `security` — ditulis ke file harian + buffer in-memory + SSE stream ke GUI.

### Keamanan Bawaan
- Session + CSRF token
- Rate limit per-IP
- Brute-force protection (auto-block setelah N login gagal)
- Deteksi pola berbahaya (SQLi, XSS, path traversal, RCE)
- Anti DNS rebinding (origin/host check)
- Password disimpan dengan scrypt hash

### Auto-Update GitHub
- Cek versi di `origin/main`
- Backup otomatis (`.env`, `package.json`, `lib/`, `server.js`, `gui.js`) ke `backups/<tanggal>/`
- Stash perubahan lokal sebelum pull
- `npm install` otomatis kalau dependency berubah
- Rollback otomatis kalau pull gagal

---

## 🚀 Cara Pakai (CLI)

```bash
node server.js                    # Mode normal
node server.js --gui              # Buka GUI di browser
node server.js --setup            # Wizard ulang
node server.js --doctor           # Diagnosa lingkungan
node server.js --update           # Cek & tarik update GitHub
node server.js --no-tunnel        # Local-only
node server.js --provider=cloudflared
node server.js --mode=php
node server.js --port=8080
node server.js --help
```

Via npm:
```bash
npm start          # CLI normal
npm run gui        # GUI
npm run setup      # Wizard
npm run doctor     # Diagnosa
npm run update     # Update
npm run check      # Verifikasi sintaks semua file
```

---

## 📁 Struktur Project

```
TopZone/
├── start.bat / start.ps1 / start.sh   ← One-click launcher
├── server.js                          ← CLI launcher
├── gui.js                             ← Web GUI server
├── package.json
├── lib/
│   ├── colors.js, utils.js, config.js
│   ├── logger.js, detector.js, tunnels.js
│   ├── security.js, updater.js
│   ├── phpServer.js, controller.js
├── public/
│   ├── index.html, login.html
│   ├── styles.css, app.js, wizard.js, logs.js
├── docs/PANDUAN.md                    ← Panduan lengkap (ID)
├── logs/                              ← Auto-rotated daily logs
├── backups/                           ← Pre-update snapshots
└── Home/                              ← Folder website PHP
```

---

## 🛡️ Keamanan & Privacy

- Token & password disimpan **hanya di komputermu** (file `.env`).
- `.env` otomatis di-`.gitignore` — tidak ikut commit.
- Tidak ada telemetri, tidak ada analytics.
- Default GUI bind `127.0.0.1` — tidak terbuka ke jaringan kecuali kamu ubah `GUI_BIND`.

---

## 📋 Dukungan

| Item | Status |
|------|--------|
| Node.js minimum | 16+ |
| OS | Windows 10/11, macOS, Linux |
| Tunnel | ngrok, cloudflared, localtunnel, serveo, pinggy |
| App Server | XAMPP, Laragon, WAMP, MAMP, AMPPS, OpenServer, USBWebserver, EasyPHP, IIS, PHP built-in |

---

## 📚 Dokumentasi

- 📖 **[docs/PANDUAN.md](docs/PANDUAN.md)** — panduan lengkap step-by-step (Bahasa Indonesia)
- ⚙️  **[.env.example](.env.example)** — semua opsi konfigurasi

---

## 📝 Lisensi

MIT — bebas dipakai dan dimodifikasi.
