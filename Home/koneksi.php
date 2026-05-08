<?php
/**
 * ════════════════════════════════════════════════════════════════════
 *  TOPZONE — Koneksi Database (legacy entrypoint)
 * ════════════════════════════════════════════════════════════════════
 *  File ini dipertahankan untuk backward-compat dengan kode lama yang
 *  pakai variabel $koneksi / $conn (mysqli).
 *
 *  Kode baru sebaiknya pakai includes/db.php (PDO + prepared statement).
 *
 *  Konfigurasi diambil dari .env (di root project), fallback ke default
 *  XAMPP/Laragon kalau .env belum ada.
 * ════════════════════════════════════════════════════════════════════
 */

// Coba load helper baru kalau ada
$helper = __DIR__ . '/../includes/db.php';
if (file_exists($helper)) {
    require_once $helper;
    return; // db.php sudah expose $koneksi & $conn
}

// ─── Fallback: load .env manual (kalau includes/db.php belum ada) ──
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? 'topzone';
$port = (int) ($_ENV['DB_PORT'] ?? 3306);

$koneksi = @mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
    if ($debug) {
        die('Koneksi gagal: ' . mysqli_connect_error());
    }
    error_log('[DB] ' . mysqli_connect_error());
    die('Database connection error. Coba lagi nanti.');
}

mysqli_set_charset($koneksi, 'utf8mb4');

// Alias agar kompatibel dengan kode yang pakai $conn
$conn = $koneksi;
