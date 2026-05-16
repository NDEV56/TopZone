# 🛡️ SECURITY.md — TopZone v3.1 (Hardened)

Dokumen ini merangkum hasil audit keamanan, patch yang diterapkan, dan playbook respon insiden.

---

## 1. Threat Model

| Aktor | Kemampuan | Mitigasi utama |
|-------|-----------|---------------|
| Internet attacker (via tunnel ngrok/cloudflared) | Akses HTTP ke website PHP & GUI | WAF firewall, rate limit, lockdown |
| Pengguna terdaftar yang nakal | Akses fitur user (review, keranjang) | Server-side validation, harga dari DB |
| Tetangga LAN (kalau GUI bind 0.0.0.0) | Akses panel admin | Password panel + brute-force block |
| Admin lain di jaringan ngrok | Lihat dashboard | Auth wajib + CSRF + IP-binding session |
| Client hijack via XSS | Jalankan JS untuk hijack admin | CSP ketat, HttpOnly cookie, output escape |

---

## 2. Hasil Audit (sebelum patch)

### 2.1 PHP — 33 vulnerability ditemukan

| # | File | Severity | Issue |
|---|------|----------|-------|
| 1 | `Home/proses_tambah_game.php` | 🔴 **CRITICAL** | Unrestricted file upload + no auth → **RCE** |
| 2 | `Home/callback.php` | 🔴 **CRITICAL** | No webhook signature verification → **payment fraud** |
| 3 | `Home/admin*.php` (8 file) | 🔴 CRITICAL | No authentication check → semua orang bisa akses panel admin |
| 4 | `Home/migrasi_produk.php` | 🔴 CRITICAL | Web-accessible mass DB modify |
| 5 | `Home/search.php` | 🟠 HIGH | SQL injection di `$search` & `$kategori` |
| 6 | `Home/game_detail.php` | 🟠 HIGH | SQL injection di `$slug` |
| 7 | `Home/simpan_ulasan.php` | 🟠 HIGH | SQL injection (semua field) + IDOR |
| 8 | `Home/tambah_keranjang_db.php` | 🟠 HIGH | SQL injection + price tampering |
| 9 | `Home/proses_keranjang.php` | 🟠 HIGH | SQL injection + price tampering |
| 10 | `Home/cek_status.php` | 🟠 HIGH | SQL injection + IDOR |
| 11 | `Home/Konfirmasi.php` | 🟠 HIGH | SQL injection di `LIKE CONCAT` |
| 12 | `Home/index.php` | 🟠 HIGH | SQL injection di status update |
| 13 | `Home/update_status.php` | 🟠 HIGH | SQL injection + no auth |
| 14 | `Home/hapus_game.php` | 🟠 HIGH | No auth + path-traversal di unlink |
| 15 | `Home/admin_paket.php` | 🟠 HIGH | SQL injection + GET-DELETE (CSRF) |
| 16 | `Home/admin_edit_paket.php` | 🟠 HIGH | SQL injection + no auth |
| 17 | `Home/admin_tambah_paket.php` | 🟠 HIGH | SQL injection + no auth |
| 18 | `Home/admin_orders.php` | 🟠 HIGH | SQL injection + no auth + XSS di output |
| 19 | `Home/Chat/Admin_Chat/ajax_admin_send.php` | 🟠 HIGH | SQL injection + no auth + IDOR |
| 20 | `Home/Chat/Admin_Chat/ajax_admin_load_chat.php` | 🟠 HIGH | SQL injection + no auth |
| 21 | `Home/Chat/Admin_Chat/ajax_admin_list_user.php` | 🟠 HIGH | XSS via `username` di `onclick=` + no auth |
| 22 | `Home/Chat/kirim_chat.php` | 🟡 MED | No rate limit (chat flood) |
| 23 | `Login/login_proses.php` | 🟡 MED | User enumeration + no rate limit |
| 24 | `Login/tampilandaftar.php` | 🟡 MED | SQL info disclosure + no rate limit |
| 25 | `Login/tampilanlogin.php` | 🟡 MED | Dead-code SQLi (`btn_simpan` block) |
| 26 | `Home/update_profile.php` | 🟡 MED | Base64 image tanpa MIME validation |
| 27 | `Home/Checkout/ambil_token.php` | 🟡 MED | SQL injection + no auth |
| 28 | `Home/Checkout/pembayaran.php` | 🟡 MED | SQL injection di `id_game` |
| 29 | `Home/admin*.php` | 🟡 MED | XSS di output `nama_game`, `slug` |
| 30 | `Home/admin_orders.php` | 🟡 MED | Status field tidak di-whitelist |
| 31 | Semua POST forms | 🟡 MED | Tidak ada CSRF token |
| 32 | `Login/login_proses.php` | 🟡 MED | No session_regenerate_id (fixation) |
| 33 | `Home/koneksi.php` | 🟢 LOW | Default `root` / kosong (XAMPP, OK lokal) |

### 2.2 Node.js — 4 vulnerability ditemukan

| # | File | Severity | Issue |
|---|------|----------|-------|
| 1 | `lib/utils.js:hasCommand()` | 🟠 MED | Command injection via shell template |
| 2 | `lib/tunnels.js:_startNgrokBinary()` | 🟠 MED | Command injection di `ngrok config add-authtoken` |
| 3 | `lib/config.js:parseEnv()` | 🟠 MED | Prototype pollution via `__proto__=...` di `.env` |
| 4 | `gui.js` | 🟡 MED | Tidak ada SSE per-IP cap (resource exhaustion) |

---

## 3. Mitigasi yang Diterapkan

### 3.1 Helper PHP terpusat: `Home/_security.php`

Semua file PHP sekarang `require_once '_security.php'` di atas. Helper menyediakan:

| Fungsi | Tujuan |
|--------|--------|
| `tz_security_init()` | Pasang security headers (CSP, X-Frame-Options, dll), session aman, UA fingerprint binding |
| `tz_db()` | PDO singleton dengan **prepared statements wajib** |
| `tz_require_login()` / `tz_require_admin()` | Auth gate |
| `tz_csrf_field()` / `tz_csrf_verify()` | CSRF protection |
| `tz_e()` / `tz_attr()` / `tz_js()` / `tz_url()` | Output escape per-context |
| `tz_validate_upload()` | Validasi upload file (whitelist ext + MIME via `getimagesize`, double-ext block, size cap) |
| `tz_rate_limit()` | Rate limit file-based (untuk login/register/chat) |
| `tz_verify_webhook()` | Cek `X-CALLBACK-TOKEN` Xendit |
| `tz_safe_redirect()` | Redirect aman (tolak URL eksternal) |

### 3.2 Konfigurasi Apache: `Home/.htaccess` & `Home/uploads/.htaccess`

- Tutup `.env`, `_security.php`, `migrasi_produk.php`, `*.bak`, `*.log`
- **Folder `uploads/` matikan PHP engine total** + `<FilesMatch>` block ext berbahaya
- Disable directory listing
- Hapus `Server`/`X-Powered-By` headers
- Hard rewrite rule: tolak request langsung ke `.php` di `uploads/`

### 3.3 Stack Security Node.js

#### `lib/security.js` — Application-layer
- Scrypt password hashing (16384/8/1)
- Session: random 24-byte ID + IP-binding + UA fingerprint + 12-jam TTL
- CSRF token per-session (32 char hex, timing-safe compare)
- Brute-force: **exponential backoff** (5 fails → 5 min, 6 → 10 min, …, max 24 jam)
- WAF pattern set 30+ regex (SQLi, XSS, RCE, JNDI/Log4Shell, PHP wrappers, scanner UA, dll) + URL decode loop
- `safeParseJson()`: max bytes/depth/keys + reject `__proto__`/`constructor`/`prototype`
- `parseCookies()`: pakai `Object.create(null)` (anti proto pollution)
- `buildSecurityHeaders()`: CSP, X-Frame-Options, COOP, CORP, HSTS

#### `lib/antiDdos.js` — 11 lapisan DDoS protection
1. Connection cap per-IP
2. Connection cap per-subnet (/24 IPv4, /64 IPv6)
3. Token bucket per-IP (8 rps steady, 25 burst)
4. Token bucket per-subnet
5. Sliding window per-IP (240 req / 60s)
6. Slowloris detector (header timeout 8s, body timeout 15s)
7. Body size hard cap (96 KB)
8. Header count/size cap (60 headers, 16 KB total)
9. URL length cap (2 KB)
10. Suspicion score → auto graylist (score 6) → block (score 12)
11. **Adaptive throttling**: kalau global RPS ≥ 200 → semua limit dipotong setengah

#### `lib/lockdown.js` — Emergency Lockdown 4 level
- `none` — normal
- `guarded` — limit ketat + CSRF mandatory
- `restricted` — hanya GET + login/logout
- `lockdown` — hanya `/api/health`

**Auto-trigger**: jendela 60 detik mengakumulasi skor incident. Threshold:
- `guarded` saat skor ≥ 25
- `restricted` saat skor ≥ 80 atau ≥ 8 IP berbeda menyerang
- `lockdown` saat skor ≥ 200 atau ≥ 16 IP berbeda

**Auto-deescalate**: setelah 120 detik tenang, turun otomatis.

#### `lib/firewall.js` — Pipeline tunggal
Setiap request lewat pipeline urut, fail-fast:
1. Method whitelist
2. Lockdown gate (path allowlist saat aktif)
3. Anti-DDoS request check
4. Origin/Host check (anti DNS rebinding)
5. WAF pattern scan
6. CSRF (untuk state-change)

### 3.4 GUI Hardening
- Default bind `127.0.0.1` (loopback only)
- HTTP `requestTimeout: 30s`, `headersTimeout: 15s`, `keepAliveTimeout: 5s`
- Connection-level guard (slowloris detector)
- `clientError` handler (drop malformed cepat)
- SSE: max 50 client global, 3 per IP
- Cookie: `HttpOnly + SameSite=Strict` (kalau ada password)
- Login delay 250-500ms random (anti timing)

---

## 4. Hasil Smoke Test (3-pass)

```
PASS 1: FUNCTIONAL TESTS                         13/13 ✓
  ✓ Health 200                       ✓ Index page
  ✓ Login page                        ✓ styles.css / app.js
  ✓ Whoami / Status / Lockdown        ✓ Diagnose / Logs
  ✓ Firewall stats / Security stats   ✓ 15 normal reqs (15/15)

PASS 2: ATTACK SCENARIOS (fresh server each)     13/13 ✓
  ✓ SQLi probe blocked (URL-encoded)
  ✓ XSS in URL blocked (URL-encoded)
  ✓ Path traversal blocked
  ✓ JNDI/Log4Shell blocked
  ✓ PHP wrapper blocked
  ✓ Proto-pollution rejected (JSON body)
  ✓ Oversized body rejected (200 KB → 413)
  ✓ WP-admin / phpMyAdmin probe blocked
  ✓ Sqlmap UA blocked
  ✓ Bad Host header blocked
  ✓ CRLF injection blocked
  ✓ Burst RPS triggers throttle (53/60 throttled)

PASS 3: NORMAL FLOW POST-ATTACK                  4/4 ✓
  ✓ Health / Index / Static / Diagnose
```

PHP: **41/41 file lint clean** (`php -l`).

---

## 5. Konfigurasi Wajib Pengguna

Tambahkan ke `.env`:

```ini
# Daftar admin (untuk Home/admin*.php)
ADMIN_USERNAMES=username_admin_kamu
# atau pakai ID:
ADMIN_IDS=1

# Webhook callback Xendit — wajib agar callback.php menerima request:
XENDIT_CALLBACK_TOKEN=tokenrahasiadarixendit

# Optional: kalau pakai ambil_token.php
XENDIT_SECRET_KEY=xnd_development_xxx
BASE_URL=https://abc.ngrok-free.app

# DB connection (default cocok untuk XAMPP lokal):
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=topzone
```

---

## 6. Runbook Insiden

### 6.1 Ada lonjakan request mencurigakan
1. Buka GUI → tab **Keamanan**.
2. Lihat angka **RPS Saat Ini**, **IP Diblokir**, **Pola Mencurigakan**.
3. Kalau **Mode Adaptif = AKTIF**, sistem sudah otomatis memperketat limit.
4. Kalau perlu lebih ketat, klik tombol **Guarded** atau **Restricted**.
5. Cek tab **Log** → filter **Keamanan** untuk lihat pola serangan.

### 6.2 Panel kena DDoS
1. Buka tab Keamanan → klik **🔴 Full Lockdown** (durasi 30 menit).
2. Saat lockdown, hanya `/api/health` boleh — endpoint lain dikunci.
3. Strip merah muncul di header sampai dimatikan.
4. Setelah serangan reda, klik **↩ Nonaktifkan**.

### 6.3 Suspect kompromi sesi admin
1. Edit `.env` → ubah `GUI_PASSWORD=` (kosongkan, atau ganti)
2. Restart server (CLI/GUI) → semua sesi expired
3. Cek log Keamanan → `SID dipakai dari IP berbeda` warning

### 6.4 File `.env` bocor
- Token ngrok / DB password / GUI password → **rotate semua**.
- Cek log Keamanan apakah ada login dari IP asing.
- Re-run `node server.js --setup` untuk wizard ulang.

### 6.5 Audit upload file
```bash
ls -la Home/uploads/
# Cek apakah ada file .php yang nyusup
find Home/uploads -name "*.php" -o -name "*.phtml"
# Harus kosong. Kalau ada → hapus, audit log webserver.
```

---

## 7. Backward Compatibility

Semua patch dirancang **tidak merusak fungsi yang sudah jalan**:

- `koneksi.php` masih export `$koneksi` & `$conn` (mysqli) — file lama yang belum dimigrasi tetap jalan
- Format URL & nama field form **tidak diubah**
- Session keys (`id_user`, `username`, `nama_user`, `email`, `foto`) **tetap sama**
- `index.php`, `game_detail.php` masih pakai `mysqli_fetch_assoc`-style loop di template (compat)

Yang berubah dari sisi user:
- Form sekarang butuh CSRF token (sudah otomatis lewat `tz_csrf_field()`)
- Halaman admin minta `ADMIN_USERNAMES` di `.env` — tampil pesan ramah kalau belum dikonfigurasi
- File upload disimpan ke `Home/uploads/` (path baru: `uploads/<random>.png`)
- Migrasi DB hanya dari CLI: `php Home/migrasi_produk.php`

---

## 8. Perubahan API GUI (untuk plugin/integrasi)

Endpoint baru:
- `GET  /api/firewall/stats` — statistik DDoS + lockdown + WAF
- `GET  /api/lockdown/status` — public, untuk banner UI
- `POST /api/lockdown/activate` — body `{ level, durationMs?, permanent?, reason? }`
- `POST /api/lockdown/deactivate`

SSE event baru:
- `lockdown` — saat level berubah
- `ddos` — saat attack-start / attack-end terdeteksi

---

## 9. Versi & Atribusi

- Versi: **TopZone v3.1 (Security Hardened)**
- Tanggal patch: 2026-05-08
- Patch oleh: audit otomatis
- Lisensi: MIT
