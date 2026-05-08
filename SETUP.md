# 🚀 Panduan Setup Lengkap TopZone

Panduan step-by-step dari nol sampai aplikasi jalan. Cocok untuk yang baru pertama kali setup.

---

## 📋 Daftar Isi

1. [Prasyarat](#-1-prasyarat)
2. [Install Tools](#-2-install-tools)
3. [Setup Project](#-3-setup-project)
4. [Setup Database](#-4-setup-database)
5. [Setup Konfigurasi](#-5-setup-konfigurasi)
6. [Jalankan Aplikasi](#-6-jalankan-aplikasi)
7. [Setup Xendit (Payment Gateway)](#-7-setup-xendit)
8. [Common Issues](#-8-common-issues)

---

## 🔧 1. Prasyarat

| Tool | Versi Min | Fungsi |
|---|---|---|
| **PHP** | 7.4 | Backend server |
| **Node.js** | 16 | Launcher + ngrok |
| **MySQL** | 5.7 | Database |
| **Web Server** | XAMPP / Laragon / WAMP | Apache + MySQL bundle |
| **Git** | any | Clone repo |

---

## 💻 2. Install Tools

### A. XAMPP (recommended, all-in-one)

**Windows:**
1. Download: https://www.apachefriends.org/
2. Install di `C:\xampp` (default)
3. Buka **XAMPP Control Panel** → **Start** Apache + MySQL

**macOS:**
```bash
brew install --cask xampp
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt update
sudo apt install apache2 php php-mysql mysql-server
```

### B. Node.js

- Download: https://nodejs.org/ (pilih LTS)
- Cek install: `node -v` & `npm -v`

### C. Git

- Download: https://git-scm.com/

---

## 📦 3. Setup Project

### Clone repo

```bash
git clone https://github.com/<your-username>/TopZone.git
cd TopZone
```

### Pindahkan ke web root

| Server | Lokasi |
|---|---|
| XAMPP (Win) | `C:\xampp\htdocs\TopZone` |
| Laragon | `C:\laragon\www\TopZone` |
| WAMP | `C:\wamp64\www\TopZone` |
| MAMP | `/Applications/MAMP/htdocs/TopZone` |
| Linux | `/var/www/html/TopZone` |

### Install dependencies Node

```bash
npm install
```

---

## 🗄️ 4. Setup Database

### A. Via phpMyAdmin (GUI, recommended)

1. Buka http://localhost/phpmyadmin
2. Klik **New** → nama database: `topzone` → Create
3. Pilih database `topzone` → tab **Import**
4. Pilih file `database/schema.sql` → **Go**
5. (Opsional) Import lagi `database/seed.sql` untuk data dummy

### B. Via CLI

```bash
# Schema
mysql -u root -p < database/schema.sql

# Sample data
mysql -u root -p topzone < database/seed.sql
```

### C. Via npm script (one-liner)

```bash
npm run db:setup
```

### Akun default setelah seed

| Role | Username | Password |
|---|---|---|
| 👑 Admin | `admin` | `admin123` |
| 👤 User | `demo`  | `demo123`  |

---

## ⚙️ 5. Setup Konfigurasi

```bash
# Copy template
cp .env.example .env
```

Edit file `.env`:

```env
# WAJIB: Token ngrok (gratis di https://dashboard.ngrok.com)
NGROK_AUTHTOKEN=2abc...xyz

# Mode server (auto = deteksi otomatis)
SERVER_MODE=auto

# Xendit (untuk testing payment)
XENDIT_SECRET_KEY=xnd_development_xxxxx

# Database (sesuaikan dengan setup MySQL kamu)
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=topzone
```

---

## 🎮 6. Jalankan Aplikasi

### Cara 1: Quick Start Script

**Windows:** Double-click `start.bat`
**Linux/Mac:** `chmod +x start.sh && ./start.sh`

### Cara 2: Manual

```bash
npm start
```

Output yang akan muncul:

```
✅ TopZone ONLINE!
  Server         : XAMPP / Laragon / WAMP / Apache (port 80)
  Lokal          : http://localhost:80
  Publik (ngrok) : https://abcd1234.ngrok-free.app
  Ngrok UI       : http://localhost:4040
```

### Akses aplikasi

| URL | Fungsi |
|---|---|
| http://localhost/TopZone/Home/ | Halaman utama (katalog game) |
| http://localhost/TopZone/Login/tampilanlogin.php | Login |
| http://localhost/TopZone/Home/admin.php | Panel admin |

---

## 💳 7. Setup Xendit

> Xendit adalah payment gateway untuk QRIS, Virtual Account, e-Wallet, dll.

### Langkah-langkah:

1. **Daftar** di https://dashboard.xendit.co
2. **Settings → API Keys → Generate Secret Key**
3. **Mode Test** (gratis, untuk dev): copy yang `xnd_development_...`
4. Paste ke `.env`:
   ```env
   XENDIT_SECRET_KEY=xnd_development_abc123
   ```
5. **Settings → Webhooks → URL Callback Invoice**
6. Paste URL ngrok yang muncul saat `npm start`:
   ```
   https://abcd1234.ngrok-free.app/Home/callback.php
   ```
7. Save

### ⚠️ Penting:

- URL ngrok BERUBAH setiap restart (di free plan)
- **Update URL webhook di Xendit Dashboard SETIAP KALI** kamu start ulang
- Kalau punya custom domain: set di `.env` → `NGROK_DOMAIN=topzone.ngrok.app`

### Test Payment

| Metode | Test Number |
|---|---|
| Virtual Account | `8808 8888 8888 8888` |
| QRIS | Pakai Xendit Simulator |
| Credit Card | `4000 0000 0000 0002` (Visa) |

---

## ❓ 8. Common Issues

### Q: "Koneksi gagal" muncul di browser

**A:** MySQL service belum jalan. Buka XAMPP Control Panel → klik **Start** di MySQL.

### Q: Port 80 sudah dipakai

**A:** Ada IIS/Skype/aplikasi lain yang pake port 80. Solusi:
- Set di `.env` → `SERVER_MODE=php` & `PHP_PORT=8080`
- Atau matikan service yang memblokir port 80

### Q: `npm install` error

**A:** Coba clear cache:
```bash
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

### Q: Ngrok error "tunnel session limit"

**A:** Free plan cuma 1 tunnel aktif. Tutup tunnel lain di:
https://dashboard.ngrok.com/tunnels

### Q: Foto upload tidak muncul

**A:** Folder `Home/uploads/` perlu writable:
```bash
# Linux/Mac
chmod 755 Home/uploads
# Windows: Right-click → Properties → Security → kasih write access
```

### Q: Callback Xendit tidak masuk database

**A:** Cek:
1. URL webhook di Xendit Dashboard sudah benar (pake URL ngrok TERBARU)
2. Cek log: `Home/callback_error.log`
3. Pastikan kolom `external_id` di tabel `orders` ada (cek schema.sql)

---

## 📞 Butuh Bantuan?

- 📖 [README.md](README.md) — overview project
- 🐛 [GitHub Issues](https://github.com/<your-username>/TopZone/issues)
- 💬 Live chat di aplikasi (setelah jalan)

---

**Selamat coding! 🚀**
