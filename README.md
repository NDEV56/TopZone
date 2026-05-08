# 🎮 TOPZONE — Pusat Top Up Game

> Platform top-up game (Mobile Legends, Free Fire, PUBG, Roblox, Genshin Impact, dll)
> dengan integrasi Xendit Payment Gateway, live chat admin, dan ngrok tunnel
> untuk testing webhook lokal.

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Node](https://img.shields.io/badge/Node-16%2B-339933?logo=node.js&logoColor=white)](https://nodejs.org/)
[![License](https://img.shields.io/badge/License-GPL--2.0-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-success)]()

---

## ✨ Fitur Utama

| Modul | Fitur |
|---|---|
| 🛒 **Pembelian** | Katalog game, detail produk, keranjang, checkout multi-qty |
| 💳 **Pembayaran** | Xendit (QRIS, VA, e-Wallet) + callback otomatis |
| 👤 **Akun** | Register, login, login guest, edit profil, upload foto (croppie) |
| 💬 **Chat** | Live chat user ↔ admin (polling 1 detik) |
| 🛠️ **Admin** | CRUD game/paket, kelola order, update status pengiriman |
| ⭐ **Ulasan** | Rating bintang, komentar pembeli, statistik rata-rata |
| 🚀 **Dev Tools** | Auto-launch server + ngrok tunnel via `npm start` |
| 🎯 **Kategori** | MOBA, FPS, Open World, dll. + filter realtime |
| 🔍 **Search** | Pencarian game live (no refresh) |

---

## 📁 Struktur Folder

```
TopZone/
├── Home/                       # Halaman utama user
│   ├── index.php               # Beranda (katalog game)
│   ├── game_detail.php         # Detail game + form top-up
│   ├── admin*.php              # Panel admin
│   ├── Chat/                   # Modul live chat
│   │   ├── chat.php
│   │   ├── kirim_chat.php
│   │   ├── load_chat.php
│   │   └── Admin_Chat/         # Sisi admin chat
│   ├── Checkout/               # Proses pembayaran
│   │   ├── pembayaran.php
│   │   └── ambil_token.php     # Generate Xendit invoice
│   ├── callback.php            # Webhook Xendit
│   ├── koneksi.php             # Koneksi MySQL
│   └── uploads/                # Foto profil user
├── Login/                      # Auth (login, daftar, guest)
├── midtrans-php-master/        # SDK Midtrans (alternatif gateway)
├── database/                   # 🆕 Schema SQL & seeder
├── includes/                   # 🆕 Helper PHP (security, db)
├── server.js                   # Launcher Node + ngrok
├── package.json
├── .env.example                # 🆕 Template konfigurasi
├── .htaccess                   # 🆕 Security headers
├── start.bat / start.sh        # 🆕 Quick start
└── README.md
```

---

## ⚡ Quick Start (5 menit)

### 1️⃣ Prasyarat

| Tool | Versi | Cek |
|---|---|---|
| PHP | 7.4+ | `php -v` |
| Node.js | 16+ | `node -v` |
| MySQL/MariaDB | 5.7+ | `mysql --version` |
| Server lokal | XAMPP / Laragon / WAMP | — |

### 2️⃣ Clone & Install

```bash
git clone https://github.com/<your-username>/TopZone.git
cd TopZone
npm install
```

### 3️⃣ Setup Database

1. Buka phpMyAdmin → **Create Database** → nama: `topzone`
2. Import file [`database/schema.sql`](database/schema.sql)
3. (Opsional) Import [`database/seed.sql`](database/seed.sql) untuk data dummy

```bash
# Atau via CLI
mysql -u root -p topzone < database/schema.sql
mysql -u root -p topzone < database/seed.sql
```

### 4️⃣ Konfigurasi

```bash
cp .env.example .env
```

Edit `.env`:
```env
NGROK_AUTHTOKEN=tokenmu_dari_dashboard.ngrok.com
SERVER_MODE=auto                    # auto/xampp/laragon/wamp/mamp/php/custom
XENDIT_SECRET_KEY=xnd_development_xxxxx
BASE_URL=http://localhost
```

### 5️⃣ Pindahkan Project ke Web Root

| Server | Path |
|---|---|
| XAMPP | `C:\xampp\htdocs\TopZone` |
| Laragon | `C:\laragon\www\TopZone` |
| WAMP | `C:\wamp64\www\TopZone` |
| MAMP | `/Applications/MAMP/htdocs/TopZone` |

### 6️⃣ Jalankan!

**Windows (klik dua kali):** `start.bat`
**Linux/Mac:** `./start.sh`
**Manual:** `npm start`

🎉 Server akan otomatis:
- Detect XAMPP/Laragon yang aktif
- Buat ngrok tunnel
- Tampilkan URL publik untuk webhook Xendit
- Live-log setiap request

---

## 🌐 URL Setelah Server Jalan

```
🏠 Lokal       : http://localhost/TopZone/Home/
🌍 Publik      : https://xxx.ngrok-free.app
📡 Ngrok UI    : http://localhost:4040
🛒 Login       : http://localhost/TopZone/Login/tampilanlogin.php
🛠️  Admin      : http://localhost/TopZone/Home/admin.php
```

---

## 🔧 Konfigurasi Lengkap (.env)

| Variabel | Default | Deskripsi |
|---|---|---|
| `NGROK_AUTHTOKEN` | — | **Wajib.** Token dari [dashboard.ngrok.com](https://dashboard.ngrok.com/get-started/your-authtoken) |
| `NGROK_DOMAIN` | — | Custom domain ngrok (opsional, free plan: random URL) |
| `SERVER_MODE` | `auto` | `auto` / `xampp` / `laragon` / `wamp` / `mamp` / `php` / `custom` |
| `LOCAL_PORT` | auto | Override port (untuk mode `custom`) |
| `PHP_PORT` | `8080` | Port PHP built-in (mode `php`) |
| `PHP_ROOT` | `./Home` | Document root PHP built-in |
| `LOG_REQUESTS` | `true` | Live log request dari ngrok API |
| `XENDIT_SECRET_KEY` | — | Secret key Xendit (test/production) |
| `BASE_URL` | — | URL publik untuk redirect setelah bayar |
| `DB_HOST` | `localhost` | Host database |
| `DB_USER` | `root` | User database |
| `DB_PASS` | `` | Password database |
| `DB_NAME` | `topzone` | Nama database |

---

## 💳 Setup Xendit Payment Gateway

1. Daftar di [dashboard.xendit.co](https://dashboard.xendit.co)
2. **Settings → API Keys** → copy `Secret Key` (mode test: `xnd_development_...`)
3. Masukkan ke `.env`:
   ```env
   XENDIT_SECRET_KEY=xnd_development_xxxxxx
   ```
4. **Settings → Webhooks** → tambah callback URL:
   ```
   https://xxx.ngrok-free.app/Home/callback.php
   ```
   (URL ngrok ditampilkan saat `npm start`)

### Test Pembayaran (mode development)

| Metode | Test Number |
|---|---|
| Virtual Account BCA | `8808 8888 8888 8888` |
| QRIS | Scan via Xendit Simulator |
| Credit Card | `4000 0000 0000 0002` (Visa) |

---

## 🧪 Testing

Tes alur lengkap:

1. ✅ Buka `index.php` — katalog tampil
2. ✅ Klik game → pilih nominal → "Beli Sekarang"
3. ✅ Login (buat akun dulu via "Daftar")
4. ✅ Pilih Xendit → bayar pakai test number
5. ✅ Cek `callback.php` di-trigger → status order jadi `proses`
6. ✅ Login admin → `admin.php` → ubah status ke `dikirim`
7. ✅ User klik **TERIMA** → status `selesai`

---

## 🐛 Troubleshooting

<details>
<summary><strong>❌ "Koneksi gagal" saat buka halaman</strong></summary>

Database belum nyala / config salah. Cek:
```bash
# XAMPP / Laragon: pastikan MySQL service jalan
# Cek di phpMyAdmin: http://localhost/phpmyadmin
```
Lalu sesuaikan `Home/koneksi.php` atau gunakan `.env`.
</details>

<details>
<summary><strong>❌ Ngrok error: "tunnel session limit"</strong></summary>

Free plan hanya 1 session aktif. Tutup semua di [dashboard.ngrok.com/tunnels](https://dashboard.ngrok.com/tunnels).
</details>

<details>
<summary><strong>❌ Server tidak terdeteksi</strong></summary>

Pastikan Apache/Nginx di XAMPP/Laragon sudah **START**. Atau pakai mode PHP built-in:
```env
SERVER_MODE=php
PHP_PORT=8080
```
</details>

<details>
<summary><strong>❌ Foto profil tidak muncul setelah upload</strong></summary>

Cek permission folder `Home/uploads/` (harus writable):
```bash
chmod 755 Home/uploads
```
</details>

<details>
<summary><strong>❌ Callback Xendit tidak masuk</strong></summary>

1. Pastikan URL ngrok sudah didaftarkan di Xendit Dashboard
2. URL ngrok berubah tiap restart (free plan) — **update di Xendit setiap kali launch**
3. Cek log: `Home/callback_error.log`
</details>

---

## 🔐 Catatan Keamanan

> ⚠️ **PROJECT INI MASIH DALAM TAHAP DEVELOPMENT.**
> Beberapa hal yang **WAJIB DIPERBAIKI** sebelum production:

- [ ] Migrasikan SEMUA query ke **Prepared Statements** (lihat [`includes/db.php`](includes/db.php))
- [ ] Tambahkan **CSRF token** di semua form (helper sudah disediakan: [`includes/security.php`](includes/security.php))
- [ ] **Jangan kirim password Roblox via URL GET** — gunakan POST + enkripsi
- [ ] Validasi role admin (saat ini siapapun bisa akses `admin.php`)
- [ ] Rate limiting di endpoint login & chat
- [ ] HTTPS only di production (set cookie secure)

---

## 🛠️ Skrip NPM

```bash
npm start          # Jalankan launcher (server + ngrok)
npm run dev        # Sama dengan start
npm run db:setup   # Import schema + seed (butuh MySQL CLI)
npm run db:reset   # Reset database (HATI-HATI!)
```

---

## 🤝 Kontribusi

1. Fork repo
2. Buat branch: `git checkout -b fitur-keren`
3. Commit: `git commit -m 'Tambah fitur X'`
4. Push: `git push origin fitur-keren`
5. Buka Pull Request

Lihat [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan lengkap.

---

## 👥 Tim Pengembang

Project DPK - Kelompok ABC

---

## 📜 Lisensi

GPL-2.0 — lihat [LICENSE](LICENSE)
