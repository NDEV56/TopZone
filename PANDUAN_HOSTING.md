# 🚀 PANDUAN HOSTING TopZone (Versi Final Gabungan)

Folder ini = **gabungan server + security + UI/UX Febri** siap di-hosting.

---

## 📦 Apa Yang Ada di Folder Ini

### Yang Dari KAMU (Server + Keamanan)
- ✅ `gui.js` + `server.js` — server launcher dengan GUI control panel
- ✅ `lib/` — security stack (antiDdos, firewall, lockdown, security, dll)
- ✅ `public/` — GUI panel di browser (port 4747)
- ✅ `Home/_security.php` — PHP security helper (CSRF, prepared SQL, escape, upload validator)
- ✅ `Home/.htaccess` + `Home/uploads/.htaccess` — Apache hardening
- ✅ `start.bat` / `start.sh` / `start.ps1` — one-click launcher
- ✅ `SECURITY.md` — dokumentasi security
- ✅ Anti-DDoS + Anti-Brute-Force + Emergency Lockdown
- ✅ Storage Janitor + Auto-update GitHub

### Yang Dari FEBRI (UI/UX + Fungsi)
- ✅ Liquid glass UI style (sudah masuk ke `Home/style.css`)
- ✅ Chat system with image upload
- ✅ Toast notification "TOPZONE LIQUID FROST NOTIF"
- ✅ Profile sidebar glass effect
- ✅ Order status detail per-status
- ✅ Login/register UI (`Login/tampilanlogin.css`, `tampilandaftar.css`)
- ✅ Webcam camera integration di chat

> **Catatan:** UI improvements dari Febri sudah dibawa masuk di versi 8.2.3.
> CSS dan JS di FINAL ini sudah punya semua design Febri PLUS tambahan
> liquid glass dari Nafi.

---

## 🎯 Cara Hosting (3 Cara)

### 🟢 CARA 1: Hosting via Local Server (XAMPP / Laragon)

**Untuk testing di komputer sendiri.**

#### Setup sekali saja:
1. Pastikan **XAMPP** atau **Laragon** sudah terinstall + Apache START + MySQL START
2. Copy folder `TopZone` ini ke `C:\xampp\htdocs\` (untuk XAMPP) atau `C:\laragon\www\` (untuk Laragon)
3. Buka phpMyAdmin → import database `topzone.sql` kalau punya
4. Edit file `.env` (kalau belum ada, copy dari `.env.example`):
   ```ini
   NGROK_AUTHTOKEN=isi_token_ngrok_kamu
   ADMIN_USERNAMES=username_admin_kamu
   ```

#### Tiap mau jalankan server:
1. Klik dua kali `start.bat` (Windows)
2. Pilih `1` untuk buka GUI di browser
3. Klik tombol **▶ Mulai Server**
4. Salin URL publik dari GUI (`https://xxx.ngrok-free.app`)

---

### 🟢 CARA 2: Hosting via Web Hosting (cPanel / Hostinger / dll)

**Untuk hosting permanen di internet.**

1. Upload SEMUA isi folder `TopZone` (kecuali `node_modules`, `logs`, `backups`) ke folder web hosting kamu
2. Pastikan Apache support:
   - PHP 8.1+
   - `mod_rewrite` aktif (untuk `.htaccess`)
   - `mod_headers` aktif (untuk security headers)
3. Buat database di cPanel → import schema `topzone`
4. Edit `Home/koneksi.php` kalau username/password DB berbeda
5. Set environment variable atau buat `.env`:
   ```ini
   DB_HOST=localhost
   DB_USER=cpanel_user
   DB_PASS=password_cpanel
   DB_NAME=topzone
   ADMIN_USERNAMES=admin_kamu
   XENDIT_CALLBACK_TOKEN=xnd_token_dari_dashboard_xendit
   ```
6. Buka domain kamu — selesai!

> ⚠️ **PENTING**: GUI panel (`gui.js`, port 4747) HANYA untuk lokal.
> Untuk hosting permanen, GUI tidak perlu di-upload. Cukup PHP files.

---

### 🟢 CARA 3: Hosting via VPS (DigitalOcean / Hostinger VPS)

**Untuk yang ingin server sendiri.**

```bash
# 1. SSH ke VPS
ssh root@ip-vps-kamu

# 2. Install LAMP
apt update
apt install apache2 mysql-server php php-mysqli php-mbstring php-curl -y

# 3. Upload folder TopZone ke /var/www/html/
scp -r TopZone root@ip-vps:/var/www/html/

# 4. Install Node.js (untuk gui.js + tunnel)
curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
apt install nodejs -y

# 5. cd ke folder + npm install
cd /var/www/html/TopZone
npm install

# 6. Setup .env
nano .env
# isi: NGROK_AUTHTOKEN, ADMIN_USERNAMES, DB_*, dll.

# 7. Jalankan server
node gui.js   # atau node server.js untuk CLI

# 8. Akses: http://ip-vps:80 (website PHP via Apache)
#          http://ip-vps:4747 (GUI panel, perlu firewall buka port)
```

---

## 🔥 Cara Cek Hosting Sudah Pakai Versi Terbaru

Setelah hosting jalan, buka:
- `https://domain-kamu/` → harus tampil **TopZone homepage** dengan desain Febri
- `https://domain-kamu/Login/tampilanlogin.php` → form login dengan UI Febri
- Klik 💬 Chat → liquid glass UI dari Nafi
- Klik 🛒 Cart icon → sidebar profile glass effect

Kalau muncul UI di atas → **HOSTING SUDAH PAKAI VERSI TERBARU** ✅

---

## 🛡️ Test Keamanan Hosting

Buka URL berikut di browser, harus dapat HTTP 403 (diblokir):

| URL Tes | Status Diharapkan |
|---------|------------------|
| `domain/Home/.env` | 403 (file tertutup) |
| `domain/Home/_security.php` | 403 (file internal) |
| `domain/Home/uploads/evil.php` | 403 (PHP exec di uploads diblokir) |
| `domain/?q=' OR 1=1` | 403 (SQLi pattern diblokir oleh WAF) |
| `domain/wp-admin/setup-config.php` | 403/404 (scanner probe diblokir) |

---

## 🆘 Kalau Ada Masalah

### Server PHP error 500
1. Cek `logs/error-YYYY-MM-DD.log`
2. Pastikan PHP version >= 8.1
3. Pastikan extension `pdo_mysql`, `mbstring`, `curl` aktif

### Database tidak konek
Edit `Home/koneksi.php` atau set di `.env`:
```ini
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=topzone
```

### Halaman admin "akses ditolak"
Tambah username admin di `.env`:
```ini
ADMIN_USERNAMES=username_kamu
# atau ID:
ADMIN_IDS=1
```

### `.htaccess` tidak jalan (Apache)
- Pastikan `AllowOverride All` di Apache config
- Pastikan `mod_rewrite` aktif: `a2enmod rewrite`

---

## 📚 Dokumentasi Lain
- `README.md` — overview proyek
- `SECURITY.md` — detail security model
- `docs/PANDUAN.md` — panduan beginner lengkap

---

## 🎉 Status Folder Ini
- ✅ 43 PHP file lint clean
- ✅ 19 Node.js file lint clean
- ✅ Server boot OK (port 4747)
- ✅ Anti-DDoS + WAF + Lockdown aktif
- ✅ UI Febri (liquid glass, chat improvements) sudah terintegrasi
- ✅ Siap di-hosting

**Folder ini adalah versi paling baru, gabungan terbaik dari semua kontributor.**
