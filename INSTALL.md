# 📥 PANDUAN INSTALASI TopZone (Untuk Pemula)

> File ini menjelaskan cara install **semua tool yang dibutuhkan** TopZone (Node.js, XAMPP, dll) — **OTOMATIS** atau **MANUAL**, sesuai pilihan kamu.

---

## ⚡ Cara Cepat (Recommended)

### Windows
**Klik 2x file `install.bat`** di folder TopZone.

Akan muncul menu:
```
[1] AUTO INSTALL semua via winget        ← paling cepat
[2] DOWNLOAD installer ke Downloads      ← klik manual setelah download
[3] BUKA LINK DOWNLOAD di browser        ← paling aman
[4] PILIH PER TOOL                        ← kontrol penuh
[5] BATAL
```

Pilih sesuai kebutuhan. Untuk pemula, **pilih [4] PILIH PER TOOL** — kamu bisa pilih cara install yang berbeda untuk setiap tool.

### Linux/Mac
```bash
chmod +x install.sh
./install.sh
```

---

## 🔧 Tool yang Dibutuhkan TopZone

| Tool | Wajib? | Untuk Apa | Link Download |
|------|--------|-----------|---------------|
| **Node.js 16+** | ✅ Wajib | Menjalankan server.js & gui.js | [nodejs.org](https://nodejs.org/en/download) |
| **XAMPP** (Apache + MySQL + PHP 8.1+) | ✅ Wajib | Web server + database untuk website PHP | [apachefriends.org](https://www.apachefriends.org/download.html) |
| **Git** | ⚪ Opsional | Auto-update dari GitHub | [git-scm.com](https://git-scm.com/download/win) |
| **ngrok** | ⚪ Opsional | Tunnel — website lokal jadi online | [ngrok.com](https://ngrok.com/download) |
| **Cloudflared** | ⚪ Opsional | Tunnel alternatif gratis | [cloudflare.com](https://github.com/cloudflare/cloudflared/releases) |

> ⚪ Opsional artinya kamu bisa skip; TopZone tetap jalan tanpa mereka.

---

## 🚀 Cara INSTALL OTOMATIS (Pilihan 1)

**Syarat:** Windows 10/11 dengan **winget** (App Installer dari Microsoft Store). 99% Windows modern sudah punya.

1. Klik 2x **`install.bat`**
2. Pilih **`[1] AUTO INSTALL`**
3. Tunggu — installer akan jalan otomatis. Mungkin minta izin admin (UAC) → klik **Yes**
4. Setelah selesai, klik 2x **`start.bat`** untuk jalankan TopZone

**Yang akan diinstall:**
- ✓ Node.js LTS
- ✓ XAMPP (Apache + MySQL + PHP)
- ✓ Git
- ✓ ngrok (opsional)
- ✓ Cloudflared (opsional)

---

## 📥 Cara DOWNLOAD ke Folder (Pilihan 2)

Kalau winget gak jalan / lo gak mau auto-install:

1. Klik 2x **`install.bat`**
2. Pilih **`[2] DOWNLOAD ke Downloads`**
3. Skrip akan download semua installer ke:
   ```
   C:\Users\<nama>\Downloads\TopZone-Setup\
   ```
4. Buka folder Downloads → **klik 2x setiap installer** → ikuti wizard
5. Selesai → kembali ke folder TopZone → klik 2x **`start.bat`**

---

## 🌐 Cara MANUAL via Browser (Pilihan 3)

Kalau lo mau download manual dari website resmi:

1. Klik 2x **`install.bat`** → pilih **`[3] BUKA LINK`**
2. Browser akan buka tiap link otomatis
3. Download installer dari website resmi → install

**Atau langsung dari tabel di atas** — klik link tabel.

---

## 🎯 Cara PER-TOOL (Pilihan 4) — RECOMMENDED untuk Pemula

Buat setiap tool, lo dikasih 4 pilihan:
```
[1] AUTO install (winget)
[2] DOWNLOAD ke Downloads folder
[3] BUKA LINK manual di browser
[4] Skip
```

Contoh skenario:
- **Node.js** → pilih [1] AUTO (kecil, cepat)
- **XAMPP** → pilih [2] DOWNLOAD (besar, mau install manual)
- **Git** → pilih [4] Skip (gak butuh)
- **ngrok** → pilih [3] BUKA LINK (mau daftar dulu)

---

## 🚨 Troubleshooting

### "winget tidak ditemukan"
- Update Windows ke versi terbaru
- Install **App Installer** dari Microsoft Store
- Atau pakai opsi [2] / [3] (manual)

### "Download gagal"
- Cek koneksi internet
- Pakai opsi [3] BUKA LINK → download manual dari browser

### "XAMPP butuh izin admin (UAC)"
- Klik **Yes** saat dialog UAC muncul
- Kalau gagal → klik kanan **`install.bat`** → **Run as administrator**

### "Setelah install, perintah masih not found"
- **Tutup terminal & buka ulang** (PATH baru perlu reload)
- Restart komputer kalau masih belum ke-detect

### "Permission denied" (Linux/Mac)
```bash
chmod +x install.sh
sudo ./install.sh   # kalau butuh sudo untuk apt/dnf/pacman
```

---

## 📦 Setelah Semua Tool Terinstall

1. **Buka XAMPP Control Panel** → klik **Start** di baris **Apache** + **MySQL**
2. **Buat database** di phpMyAdmin (URL: <http://localhost/phpmyadmin>) → buat database `topzone`
3. **Klik 2x `start.bat`** di folder TopZone → pilih `[1] GUI` mode
4. Browser otomatis kebuka ke panel kontrol di `http://127.0.0.1:4747`
5. Klik **▶ Mulai Server** → URL publik akan muncul!

---

## 📚 Bantuan Lain
- `PANDUAN_HOSTING.md` — cara host di local / cPanel / VPS
- `SECURITY.md` — penjelasan security model
- `README.md` — overview fitur
- `docs/PANDUAN.md` — panduan beginner step-by-step

---

## 💁 Tetap Bingung?

Jalankan **`install.bat`** dengan opsi **[4] PILIH PER TOOL** — paling fleksibel dan ramah pemula. Skrip akan tanya 1-1 untuk setiap tool, kamu tinggal pilih cara yang nyaman.

Atau kasih file `INSTALL.md` ini ke teman developer-mu — mereka bisa bantu sesuai pilihan yang ada.
