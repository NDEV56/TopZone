<?php
/**
 * proses_tambah_game.php — HARDENED v3.1
 *   • require_admin (anti-RCE: sebelumnya tanpa auth)
 *   • CSRF check
 *   • File upload validator (whitelist ext+MIME, double-ext block, randomized name)
 *   • Save ke Home/uploads/ (sudah ada .htaccess yang matikan PHP exec)
 *   • Whitelist kategori
 *   • Prepared statements
 *   • Tipe paket di-whitelist
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['simpan'])) {
    tz_safe_redirect('/Home/admin_tambah_game.php');
}
tz_csrf_verify();

$nama_game    = trim((string)($_POST['nama_game'] ?? ''));
$slug_input   = trim((string)($_POST['slug'] ?? ''));
$kategori     = (string)($_POST['kategori'] ?? '');
$tipe_voucher = trim((string)($_POST['tipe_voucher'] ?? ''));

// Whitelist kategori
$allowedKategori = ['Game','MOBA','FPS','Open World'];
if (!in_array($kategori, $allowedKategori, true)) $kategori = 'Game';

// Sanitasi nama_game & tipe_voucher (panjang max)
if ($nama_game === '' || strlen($nama_game) > 64)        die('Nama game tidak valid.');
if (strlen($tipe_voucher) > 64)                          $tipe_voucher = substr($tipe_voucher, 0, 64);

// Slug — hanya huruf-angka-strip
$slug = strtolower($slug_input);
$slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
$slug = trim((string)$slug, '-');
if ($slug === '' || strlen($slug) > 64) die('Slug tidak valid.');

// Validasi & simpan upload
if (!isset($_FILES['gambar']) || !is_array($_FILES['gambar'])) die('File gambar tidak ada.');
$safe_name = tz_validate_upload($_FILES['gambar'], ['png','jpg','jpeg','webp','gif'], 5 * 1024 * 1024);
if ($safe_name === null) die('File gambar tidak valid (harus png/jpg/jpeg/webp/gif, max 5MB).');

$uploads = tz_uploads_dir();          // pastikan .htaccess ada
$dest    = $uploads . DIRECTORY_SEPARATOR . $safe_name;
if (!@move_uploaded_file($_FILES['gambar']['tmp_name'], $dest)) {
    error_log('[topzone-upload] move_uploaded_file gagal');
    die('Gagal menyimpan file. Coba lagi.');
}
@chmod($dest, 0644);

// Path relatif untuk disimpan di DB → "uploads/<file>"
$gambar_rel = 'uploads/' . $safe_name;

// Cek slug unik
try {
    $exists = tz_db()->fetchOne('SELECT id FROM games WHERE slug = ?', [$slug]);
    if ($exists) {
        @unlink($dest);
        die('Slug sudah dipakai. Pilih yang lain.');
    }
} catch (\Throwable $e) {
    error_log('[topzone-upload] ' . $e->getMessage());
    @unlink($dest);
    die('Database tidak tersedia.');
}

// Insert game + paket dalam satu transaksi (tipe_voucher tidak ada di schema)
try {
    // Build deskripsi dari tipe_voucher (kalau diisi)
    $deskripsi = $tipe_voucher !== '' ? 'Tipe: ' . $tipe_voucher : '';
    $id_game_baru = tz_db()->transaction(function ($db) use ($nama_game, $slug, $kategori, $deskripsi, $gambar_rel) {
        $db->exec(
            'INSERT INTO games (nama_game, slug, kategori, deskripsi, gambar, terjual, is_active)
             VALUES (?, ?, ?, ?, ?, 0, 1)',
            [$nama_game, $slug, $kategori, $deskripsi, $gambar_rel]
        );
        $newId = (int)$db->lastInsertId();

        // Paket
        $p_nama  = $_POST['p_nama']  ?? [];
        $p_harga = $_POST['p_harga'] ?? [];
        $p_tipe  = $_POST['p_tipe']  ?? [];
        $allowedTipe = ['umum','login','5hari','roblox_login','roblox_5hari'];

        if (is_array($p_nama)) {
            foreach ($p_nama as $i => $val) {
                $nm   = trim((string)$val);
                if ($nm === '' || strlen($nm) > 64) continue;
                $rawHarga = (string)($p_harga[$i] ?? '0');
                $hg = (int)preg_replace('/[^0-9]/', '', $rawHarga);
                if ($hg < 0 || $hg > 100000000) continue;
                $tp = (string)($p_tipe[$i] ?? 'umum');
                if (!in_array($tp, $allowedTipe, true)) $tp = 'umum';

                $db->exec(
                    'INSERT INTO produk_game (id_game, nama_produk, harga, tipe)
                     VALUES (?, ?, ?, ?)',
                    [$newId, $nm, $hg, $tp]
                );
            }
        }
        return $newId;
    });

    echo "<script>alert('Game & Paket berhasil ditambah!'); window.location='admin_tambah_game.php';</script>";
} catch (\Throwable $e) {
    error_log('[topzone-tambah-game] ' . $e->getMessage());
    @unlink($dest);
    die('Gagal menyimpan ke database. Coba lagi.');
}
