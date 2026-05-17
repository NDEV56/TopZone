<?php
/**
 * simpan_ulasan.php — HARDENED v3.1
 *   • require_login (cuma user yang login boleh review)
 *   • CSRF
 *   • Prepared statements
 *   • Validasi rating 1-5
 *   • Length cap
 *   • Tidak pakai user_name dari client (ambil dari session)
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tz_safe_redirect('/Home/index.php');
}
tz_csrf_verify();

$id_game  = (int)($_POST['id_game'] ?? 0);
$rating   = (int)($_POST['rating']  ?? 0);
$komentar = trim((string)($_POST['komentar'] ?? ''));
$slug     = preg_replace('/[^a-z0-9\-]/i', '', (string)($_POST['slug'] ?? ''));

// Ambil nama dari session — bukan dari $_POST!
$nama_user = (string)($_SESSION['nama_user'] ?? 'User');

if ($id_game <= 0)               $err = 'Game tidak valid';
elseif ($rating < 1 || $rating > 5) $err = 'Rating harus 1-5';
elseif ($komentar === '' || strlen($komentar) > 500) $err = 'Komentar tidak valid (max 500 karakter)';
else $err = null;

if ($err !== null) {
    echo "<script>alert(" . tz_js($err) . "); history.back();</script>";
    exit;
}

try {
    tz_db()->exec(
        'INSERT INTO reviews (id_game, user_name, rating, komentar) VALUES (?, ?, ?, ?)',
        [$id_game, substr($nama_user, 0, 64), $rating, $komentar]
    );
    tz_safe_redirect('/Home/game_detail.php?game=' . rawurlencode((string)$slug));
} catch (\Throwable $e) {
    error_log('[topzone-ulasan] ' . $e->getMessage());
    echo "<script>alert('Gagal simpan'); history.back();</script>";
}
