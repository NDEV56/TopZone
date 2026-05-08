# 📖 Panduan TopZone — Lengkap Untuk Pemula

> **Untuk siapa panduan ini?** Untuk kamu yang **belum pernah** atau **baru sekali** menjalankan server lokal. Setiap langkah di sini ditulis pelan-pelan, dengan asumsi kamu hanya bisa pakai mouse, browser, dan ikut petunjuk satu per satu.

---

## 📋 Daftar Isi

1. [Apa itu TopZone?](#apa-itu-topzone)
2. [Persiapan Awal — sekali saja](#persiapan-awal)
3. [Cara Pertama Kali Jalanin Server](#pertama-kali-jalanin)
4. [Memakai GUI Control Panel](#gui-panel)
5. [Setup Wizard — Step by Step](#setup-wizard)
6. [Tab Dashboard — Apa Saja Yang Bisa Dilihat](#tab-dashboard)
7. [Tab Kontrol — Tombol Start/Stop/Restart](#tab-kontrol)
8. [Tab Log — Membaca Apa Yang Terjadi](#tab-log)
9. [Tab Diagnostik — Cek Kesehatan Komputer](#tab-diagnostik)
10. [Tab Update — Tarik Versi Baru dari GitHub](#tab-update)
11. [Tab Keamanan — Memantau Ancaman](#tab-keamanan)
12. [Tab Pengaturan — Mengubah Konfigurasi](#tab-pengaturan)
13. [Mode CLI — Untuk Yang Suka Terminal](#mode-cli)
14. [Solusi Masalah Umum (Troubleshooting)](#troubleshooting)
15. [Penjelasan Setiap File Penting](#penjelasan-file)
16. [FAQ Tambahan](#faq)

---

<a id="apa-itu-topzone"></a>
## 1. Apa itu TopZone?

TopZone adalah **launcher** (alat yang menyalakan) untuk website PHP-mu. Tugasnya **3 hal sederhana**:

1. **Cek apakah XAMPP / Laragon / WAMP / MAMP / dll sudah jalan** di komputermu.
2. **Buka pintu publik** lewat tunnel (ngrok, Cloudflare, dll) supaya HP / komputer lain bisa akses websitemu via internet.
3. **Tampilkan log dan kontrol** lewat panel di browser — kamu cukup klik-klik tombol.

> 💡 **Analogi:** Bayangkan komputermu adalah rumah, dan website kamu adalah TV di dalam rumah. Tunnel itu seperti antena TV besar yang membuat siaran TV-mu bisa ditonton dari rumah tetangga (tanpa pindahkan TV-nya).

---

<a id="persiapan-awal"></a>
## 2. Persiapan Awal — Sekali Saja

Cuma perlu sekali. Setelah ini, tinggal klik dua kali file `start.bat`.

### 2.1. Install Node.js

Node.js adalah "mesin" yang menjalankan TopZone.

1. Buka link ini di browser: <https://nodejs.org/en/download>
2. Klik tombol besar yang ada tulisan **"LTS Recommended For Most Users"**.
3. Klik dua kali file installer yang sudah didownload.
4. Klik **Next → Next → Next → Install → Finish**. Centangan default tidak usah diubah.
5. Untuk memastikan berhasil:
   - Tekan tombol **Windows + R** di keyboard
   - Ketik `cmd` lalu tekan Enter
   - Di jendela hitam yang muncul, ketik: `node -v`
   - Kalau muncul angka seperti `v20.10.0`, berarti **berhasil**.

### 2.2. (Pilih salah satu) Install Server PHP

Pilih **satu** saja dari pilihan di bawah. Yang paling populer:

#### Pilihan A: XAMPP (paling banyak dipakai pemula)

1. Buka <https://www.apachefriends.org/download.html>
2. Download versi untuk Windows (sesuai PHP yang kamu butuh — biasanya yang paling baru OK).
3. Klik dua kali installer, klik Next-Next-Install. Default folder `C:\xampp` — JANGAN diubah.
4. Buka **XAMPP Control Panel** dari Start Menu.
5. Klik tombol **Start** di baris **Apache**. Tunggu sampai warna biru.
6. Klik tombol **Start** di baris **MySQL** (kalau kamu pakai database).
7. Buka <http://localhost> di browser → kalau muncul halaman dashboard XAMPP, **berhasil**.

#### Pilihan B: Laragon (lebih ringan, modern)

1. Buka <https://laragon.org/download/>
2. Download Laragon Full.
3. Install seperti biasa, default folder `C:\laragon`.
4. Buka **Laragon** dari Start Menu → klik **Start All**.

#### Pilihan C: WAMP, MAMP, AMPPS, OpenServer

TopZone juga support semua ini. Install seperti biasa, klik Start, selesai.

### 2.3. (Opsional) Install Git

Hanya kalau kamu mau pakai fitur **auto-update dari GitHub**.

1. Buka <https://git-scm.com/download/win>
2. Download installer dan install dengan default.

---

<a id="pertama-kali-jalanin"></a>
## 3. Cara Pertama Kali Jalanin Server

### 3.1. Buka folder TopZone

Folder ini berisi semua file: `start.bat`, `server.js`, `gui.js`, dst.

### 3.2. Klik dua kali file `start.bat`

Akan muncul jendela hitam yang melakukan **4 langkah otomatis**:

```
[1/4] Cek Node.js...        → ✓ Node v20.10.0
[2/4] Cek dependency...     → ✓ Dependency sudah ada
[3/4] Cek konfigurasi...    → .env belum ada — wizard akan muncul
[4/4] Mempersiapkan panel...
```

### 3.3. Pilih mode

Akan muncul menu:

```
1. GUI (panel di browser)  ← rekomendasi pemula
2. CLI (jalan di terminal ini)
3. Doctor (diagnosa lingkungan)
4. Update (tarik versi terbaru dari GitHub)
```

**Tekan angka `1` lalu Enter.** Browser akan terbuka otomatis ke panel kontrol.

---

<a id="gui-panel"></a>
## 4. Memakai GUI Control Panel

Panel TopZone adalah halaman web di alamat <http://127.0.0.1:4747>.

> ⚠️ **Penting:** Halaman ini **hanya bisa dibuka di komputermu sendiri**. Tidak bisa dibuka dari HP atau komputer lain (kecuali kamu sengaja ubah `GUI_BIND` di `.env`). Ini untuk keamanan.

---

<a id="setup-wizard"></a>
## 5. Setup Wizard — Step by Step

Pertama kali buka panel, kamu akan disambut wizard 5 langkah.

### Langkah 1 — Pilih Tunnel

Tunnel = teknologi yang membuat server lokal bisa diakses dari luar.

| Pilihan | Penjelasan | Cocok untuk |
|---------|-----------|-------------|
| 🟢 **ngrok** | Paling stabil. Butuh daftar gratis 5 menit. URL: `xxx.ngrok-free.app` | Pemula, production-test |
| 🔶 **cloudflared** | Tidak perlu daftar. Harus install `cloudflared`. URL: `xxx.trycloudflare.com` | Yang gak mau daftar |
| 🟣 **localtunnel** | Gratis, tidak perlu daftar. Install: `npm i -g localtunnel`. URL: `xxx.loca.lt` | Quick & dirty |
| 🔵 **serveo** | Pakai SSH (sudah ada di Windows 10+). Tidak install apa pun | Backup |
| ⚪ **none** | Tidak ada tunnel — hanya komputer ini | Cuma test sendiri |

**Pemula: pilih `ngrok`.**

### Langkah 2 — Token Ngrok

Kalau pilih ngrok, butuh token.

**Cara dapat token (5 menit, gratis):**

1. Buka <https://dashboard.ngrok.com/signup>
2. Daftar pakai email atau Google/GitHub.
3. Setelah masuk, buka <https://dashboard.ngrok.com/get-started/your-authtoken>
4. Klik tombol **Copy** untuk salin token (panjang ±40-50 karakter).
5. Tempel di kotak Token Ngrok di wizard.

Kotak akan langsung warna hijau ✅ kalau format token benar.

### Langkah 3 — Pilih Server Lokal

Pilih sesuai aplikasi yang kamu install di Langkah 2.2 di atas:
- Pakai XAMPP? → pilih `xampp`
- Pakai Laragon? → pilih `laragon`
- Bingung? → pilih `auto-detect`

Di tengah halaman, panel akan otomatis pindai dan beri tahu apa yang terdeteksi.

### Langkah 4 — Keamanan

- **Password Panel** (opsional): isi kalau kamu mau panel-mu butuh password sebelum buka. Kosongkan kalau tidak perlu (default aman karena hanya bisa diakses dari komputermu sendiri).
- **Catat request**: biarkan ON.
- **Fallback tunnel**: biarkan ON (kalau tunnel pilihan gagal, otomatis coba yang lain).
- **Auto-update**: pilih `ask` (paling aman — selalu tanya dulu sebelum pull).

### Langkah 5 — Konfirmasi

Cek ringkasan. Klik **Simpan & Mulai Server**. Halaman akan refresh otomatis ke Dashboard.

---

<a id="tab-dashboard"></a>
## 6. Tab Dashboard

Halaman utama setelah wizard.

### Status Server (paling atas)

Indikator besar:
- ⚪ **Server Belum Dijalankan** — perlu klik tombol Mulai
- 🟡 **Mempersiapkan…** — sedang booting
- 🟢 **Server ONLINE!** — siap dipakai
- 🔴 **Terjadi Error** — lihat tab Log atau Diagnostik

### 3 Kartu Besar

- **URL Lokal** — alamat di komputermu (`http://localhost:80`)
- **URL Publik (Tunnel)** — alamat yang bisa dibagikan ke teman / Midtrans
- **Server Lokal** — apa yang terdeteksi (XAMPP / Laragon / dll)

Tombol **📋 Salin** dan **🌐 Buka** di setiap kartu.

### URL Webhook (untuk Payment Gateway)

Kalau kamu pakai Midtrans / API Games:

- **Callback Pembayaran**: `https://xxx.ngrok-free.app/callback.php`
- **Ambil Token Midtrans**: `https://xxx.ngrok-free.app/Checkout/ambil_token.php`

Klik 📋 untuk salin, lalu tempel di dashboard Midtrans.

### Log Mini

Live log paling baru. Klik "Lihat semua log →" untuk halaman penuh.

---

<a id="tab-kontrol"></a>
## 7. Tab Kontrol

Tiga tombol BESAR:

- **▶ Mulai Server** — deteksi server lokal, lalu buka tunnel publik
- **■ Matikan Server** — tutup tunnel & PHP built-in
- **⟳ Restart Server** — matikan lalu nyalakan lagi

> 💡 Tombol Stop dan Restart **selalu meminta konfirmasi** — supaya tidak salah klik.

---

<a id="tab-log"></a>
## 8. Tab Log

Kategori log:

| Kategori | Warna | Isi |
|----------|-------|-----|
| **Biasa** | abu | Hal normal: request masuk, server start, dll. |
| **Jarang** | cyan | Kejadian tidak biasa (tunnel switch, port konflik, dll). |
| **Peringatan** | kuning | Sesuatu yang perlu diperhatikan tapi tidak fatal. |
| **Error** | merah | Ada yang gagal. Server mungkin masih bisa lanjut. |
| **Kritis** | merah pekat | Masalah berat — server bisa berhenti. |
| **Keamanan** | ungu | Aktivitas mencurigakan, brute force, IP block, dll. |

### Filter & Search

- Klik filter di atas untuk hanya tampilkan kategori tertentu.
- Kotak Search untuk cari kata kunci di pesan log.
- 💾 **Ekspor** unduh log sebagai JSON.
- 🧹 **Bersihkan** kosongkan buffer (file di disk tetap aman).

### File Log di Disk

Folder `logs/` berisi:
- `common-2026-05-08.log`
- `warning-2026-05-08.log`
- `error-2026-05-08.log`
- `critical-2026-05-08.log`
- `security-2026-05-08.log`
- `uncommon-2026-05-08.log`
- `all-2026-05-08.log` (gabungan semua)

Format: setiap baris = 1 entry JSON. Bisa dibaca pakai Notepad atau VS Code.

Log lebih dari 30 hari otomatis dihapus (bisa diubah di Pengaturan).

---

<a id="tab-diagnostik"></a>
## 9. Tab Diagnostik

Halaman cek kesehatan komputer. **Buka kalau server gagal start.**

Akan muncul list:
- ✅ Hijau = OK, lanjutkan
- ⚠️ Kuning = perlu perhatian (ada solusi di sebelahnya)
- ❌ Merah = wajib diperbaiki

Klik **🔄 Pindai Ulang** untuk refresh.

---

<a id="tab-update"></a>
## 10. Tab Update

Bagian paling penting kalau temanmu suka push perubahan ke GitHub.

### Cara Pakai

1. Klik **🔎 Cek Update Sekarang**.
2. Kalau ada commit baru, akan muncul ringkasan.
3. Klik **⬇️ Tarik Update Sekarang**.
4. Sistem akan:
   - Backup file penting → folder `backups/<tanggal>/`
   - Stash perubahan lokal kamu (kalau ada)
   - Pull dari GitHub
   - Jalankan `npm install` kalau dependency berubah
   - Tulis log update di `logs/update-log.txt`
5. Restart server.

### Pengaman Otomatis

- File `.env` (config + token) **tidak akan tertimpa** — di-stash dulu.
- Folder `logs/` aman.
- Kalau pull gagal merge, **otomatis rollback** ke commit sebelumnya.
- Backup tersimpan untuk kembali manual kapan saja.

### Pull Otomatis Tanpa Klik

Edit `.env`:
```
AUTO_UPDATE=true
```
Pilihan:
- `ask` (default) — sistem cek otomatis tapi selalu tanya dulu
- `true` — pull otomatis tanpa tanya
- `false` — jangan pernah cek

---

<a id="tab-keamanan"></a>
## 11. Tab Keamanan

Statistik real-time:
- **Sesi Aktif** — berapa user login ke panel
- **IP Diblokir** — IP yang gagal login berkali-kali
- **Pola Mencurigakan** — request mengandung SQL injection, XSS, path traversal, dll

### Auto-Block

Kalau ada IP gagal login 5x → otomatis diblokir 5 menit (bisa diubah di Pengaturan).

### Daftar Pola Yang Dideteksi

- Path traversal (`../../etc/passwd`)
- SQL injection (`UNION SELECT`, `OR 1=1`)
- XSS (`<script>`, `javascript:`)
- RCE (`; rm -rf`, `; wget`)
- Probe: `wp-admin`, `phpmyadmin`, `.env`, `.git`

Setiap deteksi ditulis ke log Keamanan.

---

<a id="tab-pengaturan"></a>
## 12. Tab Pengaturan

Edit nilai di sini — sama dengan edit `.env` manual, tapi lebih aman karena ada validasi.

Setelah klik **💾 Simpan**, restart server dari tab Kontrol.

### Bagian Tunnel

- **Provider** — ngrok / cloudflared / localtunnel / serveo / pinggy / none
- **Token Ngrok** — kosongkan kalau tidak diubah
- **Custom Domain Ngrok** — untuk paid plan

### Bagian Server Lokal

- **Mode** — auto / xampp / laragon / wamp / mamp / ampps / openserver / usbwebserver / easyphp / php / custom
- **Port Lokal** — kalau mode `custom`
- **Port PHP** — kalau mode `php`
- **PHP_ROOT** — folder webroot

### Bagian Keamanan & Log

- **Password Panel** — kosongkan kalau tidak diubah
- **Rate Limit** — req/menit per IP (default 60)
- **Block setelah login gagal** — default 5
- **Durasi block** — detik (default 300)
- **Level log minimum** — common / uncommon / warning / error / critical
- **Retensi log** — hari (default 30)

### Bagian Auto-Update

- **Mode** — ask / true / false

---

<a id="mode-cli"></a>
## 13. Mode CLI — Untuk Yang Suka Terminal

```bash
node server.js                  # Mode normal
node server.js --gui            # Buka GUI di browser
node server.js --setup          # Wizard ulang
node server.js --doctor         # Diagnosa
node server.js --update         # Cek update GitHub
node server.js --no-tunnel      # Local-only
node server.js --provider=cloudflared  # Override tunnel
node server.js --mode=php       # Override mode
node server.js --port=8080      # Override port
node server.js --help           # Bantuan
```

### Via npm

```bash
npm start          # = node server.js
npm run gui        # = node gui.js
npm run setup      # = node server.js --setup
npm run doctor     # = node server.js --doctor
npm run update     # = node server.js --update
npm run check      # Verifikasi semua file JS bisa di-parse
```

---

<a id="troubleshooting"></a>
## 14. Solusi Masalah Umum

### ❌ "Node.js belum terinstall"

Lihat [bagian 2.1](#persiapan-awal) di atas.

### ❌ "Token ngrok tidak valid"

1. Buka <https://dashboard.ngrok.com/get-started/your-authtoken>
2. Pastikan kamu copy SELURUH token (panjang ±40-50 karakter, boleh ada underscore)
3. Tempel di Pengaturan → Token Ngrok → Simpan.

### ❌ "Tidak ada server yang aktif terdeteksi"

Berarti XAMPP/Laragon/WAMP belum START.

1. Buka XAMPP Control Panel.
2. Klik tombol **Start** di Apache.
3. Tunggu warna jadi hijau (atau muncul angka PID).
4. Buka <http://localhost> di browser. Kalau muncul halaman → OK.
5. Coba lagi tombol Mulai di TopZone.

### ❌ Port 80 dipakai aplikasi lain (Skype/IIS/Apache lain)

Solusi 1 — pakai port lain:
1. Buka XAMPP Control Panel → tombol **Config** di Apache → klik `httpd.conf`.
2. Cari baris `Listen 80` → ganti jadi `Listen 8080`.
3. Cari baris `ServerName localhost:80` → ganti `ServerName localhost:8080`.
4. Save → restart Apache.
5. Di TopZone Pengaturan: **Mode** = `xampp`, **Port Lokal** = `8080`.

Solusi 2 — matikan IIS:
1. Tekan Win+R → ketik `services.msc` → Enter.
2. Cari **World Wide Web Publishing Service** → klik kanan → Stop.

### ❌ "Batas tunnel ngrok gratis tercapai"

Kamu pernah jalankan ngrok di tempat lain dan belum di-disconnect.

1. Buka <https://dashboard.ngrok.com/tunnels/agents>
2. Klik **Disconnect** di session yang lama.
3. Coba lagi.

### ❌ Halaman tidak terbuka di tunnel ngrok-free.app

Ngrok free menampilkan warning page. Klik tombol **Visit Site** sekali → setelah itu langsung ke websitemu.

### ❌ "GUI port 4747 sudah dipakai"

Edit `.env` → ubah `GUI_PORT=4748` → klik dua kali `start.bat` lagi.

### ❌ Update gagal (git pull error)

Lihat folder `backups/<tanggal>/` — file lama ada di sana.

Untuk rollback manual:
```bash
git log --oneline -10        # cari commit sebelum update
git reset --hard <commit>    # rollback
```

### ❌ Tidak bisa akses dari HP

URL yang bisa diakses dari HP adalah **URL Publik (Tunnel)**, bukan `localhost`.

Buka HP → ketik `https://xxx.ngrok-free.app` (URL dari Dashboard kartu kanan atas).

---

<a id="penjelasan-file"></a>
## 15. Penjelasan Setiap File Penting

```
TopZone/
├── start.bat              ← Klik ini untuk Windows
├── start.ps1              ← Versi PowerShell
├── start.sh               ← Versi Mac/Linux
│
├── server.js              ← CLI launcher
├── gui.js                 ← Web GUI control panel
├── package.json           ← Daftar dependency
│
├── lib/                   ← Modul-modul internal
│   ├── colors.js          ← ANSI colors
│   ├── utils.js           ← Helper umum
│   ├── config.js          ← Manajemen .env
│   ├── logger.js          ← Logger 6 kategori
│   ├── detector.js        ← Deteksi server lokal
│   ├── tunnels.js         ← Multi-tunnel manager
│   ├── security.js        ← CSRF, rate limit, brute-force
│   ├── updater.js         ← Auto-update GitHub
│   ├── phpServer.js       ← Bungkus PHP built-in
│   └── controller.js      ← Orchestrator utama
│
├── public/                ← Frontend GUI
│   ├── index.html         ← Halaman utama (tabs)
│   ├── login.html         ← Halaman login
│   ├── styles.css         ← Stylesheet
│   ├── app.js             ← Logic GUI
│   ├── wizard.js          ← Setup wizard 5 step
│   └── logs.js            ← Log viewer & filter
│
├── docs/
│   └── PANDUAN.md         ← File ini
│
├── logs/                  ← Otomatis dibuat
│   ├── common-YYYY-MM-DD.log
│   ├── warning-YYYY-MM-DD.log
│   ├── error-YYYY-MM-DD.log
│   ├── critical-YYYY-MM-DD.log
│   ├── security-YYYY-MM-DD.log
│   ├── uncommon-YYYY-MM-DD.log
│   ├── all-YYYY-MM-DD.log
│   └── update-log.txt
│
├── backups/               ← Backup auto sebelum update
│   └── YYYY-MM-DD_xxxx/
│
├── .env                   ← KONFIGURASI KAMU (jangan share!)
├── .env.example           ← Template
├── .gitignore             ← .env tidak ikut commit
├── .topzone-state.json    ← State runtime (autotulis)
│
├── Home/                  ← Folder website PHP
├── Login/                 ← Folder login PHP
└── midtrans-php-master/   ← Library Midtrans
```

---

<a id="faq"></a>
## 16. FAQ Tambahan

**Q: Apakah ngrok aman?**
A: Ya, ngrok mengenkripsi semua traffic via HTTPS. Token-mu tersimpan di `.env` lokal, tidak dikirim ke mana pun.

**Q: Apakah TopZone mengirim data saya ke developer?**
A: Tidak. Tidak ada telemetri, tidak ada analytics. Cek file `lib/` — semua kode terbuka.

**Q: Bisa pakai domain sendiri?**
A: Bisa, dengan ngrok paid plan (`NGROK_DOMAIN=mysite.com`) atau cloudflared dengan tunnel terdaftar.

**Q: Tunnel-nya lambat, gimana?**
A: Coba ganti provider di Pengaturan: cloudflared → localtunnel → serveo. Salah satu pasti lebih cepat di lokasi-mu.

**Q: Bagaimana share URL ke teman tanpa expired?**
A: Ngrok free URL berubah setiap restart. Solusi:
- Daftar Static Domain gratis di ngrok.
- Pakai Cloudflare Tunnel dengan domain sendiri.
- Atau kirim ulang URL setiap mulai server.

**Q: Bisa di hosting permanent?**
A: TopZone untuk **dev/testing**. Untuk production: deploy ke VPS (DigitalOcean, Vultr, Hostinger) atau hosting PHP biasa.

**Q: Aman dijalankan 24/7?**
A: Iya, tapi:
- Pastikan komputer tidak mati / sleep.
- Aktifkan password panel kalau bind ke 0.0.0.0.
- Cek log Keamanan secara berkala.
- Update versi terbaru.

**Q: Bagaimana hentikan totally?**
A: Tekan **Ctrl + C** di terminal yang menjalankan TopZone. Atau klik tombol **Matikan** di tab Kontrol GUI.

---

## 🆘 Masih Bingung?

Kalau panduan ini belum cukup, tanya teman developer-mu, atau buka Issue di repository GitHub temanmu.

Selamat coding! 🎮
