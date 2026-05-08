# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2.0.0] — 2026-05-08

### 🎉 Improvements

#### Added
- **Database schema** (`database/schema.sql`) — full DDL untuk semua tabel
- **Seed data** (`database/seed.sql`) — sample game, paket, akun admin/demo
- **Helper modules** (`includes/`):
  - `config.php` — `.env` loader + helper `env()`
  - `db.php` — PDO + mysqli compat, prepared statement helpers
  - `security.php` — CSRF, sanitize, auth, rate-limit
- **`.env.example`** — template lengkap dengan komentar
- **`.htaccess`** — security headers, block sensitive files, compression, caching
- **`.htaccess` per-folder** untuk `Home/uploads/`, `includes/`, `database/`
- **Quick start scripts**:
  - `start.bat` (Windows)
  - `start.sh` (Linux/Mac)
- **NPM scripts baru**: `db:setup`, `db:seed`, `db:reset`, `lint:php`, `clean:logs`
- **Dokumentasi**:
  - `README.md` — full feature overview, struktur, troubleshooting
  - `SETUP.md` — panduan setup step-by-step untuk pemula
  - `CONTRIBUTING.md` — panduan kontribusi
  - `CHANGELOG.md` — file ini

#### Changed
- `.gitignore` di-expand: cover logs, IDE files, uploads, backups
- `package.json` upgrade ke v2.0.0 dengan script lengkap

#### Security
- `.htaccess` blokir akses langsung ke `.env`, `*.sql`, `*.log`, `*.md`
- Folder `uploads/` di-disable PHP execution (mencegah upload shell)
- Folder `includes/` & `database/` di-deny semua akses HTTP langsung
- Helper `csrf_token()` & `csrf_check()` siap pakai
- Cookie session pakai `httponly` + `secure` (kalau HTTPS)

---

## [1.x] — Riwayat Awal

### Versi sebelumnya
- Game catalog dasar (MLBB, FF, PUBG, Roblox, Genshin, dll)
- Login/register sederhana
- Live chat user ↔ admin (polling)
- Integrasi Xendit Payment Gateway
- Server launcher Node.js + ngrok tunnel (v1.0)

---

## 🔮 Roadmap

### Akan datang
- [ ] Migrasi semua query ke prepared statement (PDO)
- [ ] CSRF token di semua form
- [ ] Auth role-based (block non-admin dari `admin.php`)
- [ ] Refresh ulang Xendit webhook URL otomatis tiap restart
- [ ] Notifikasi email untuk order
- [ ] Dashboard admin yang lebih lengkap (statistik, grafik)
- [ ] Multi-language (ID/EN)
- [ ] Dark mode
- [ ] PWA (Progressive Web App)
