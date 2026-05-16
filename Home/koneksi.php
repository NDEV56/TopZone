<?php
/**
 * koneksi.php — File ini sekarang adalah BRIDGE ke _security.php.
 *
 * Tujuan tetap sama: include file ini dan kamu dapat $koneksi (mysqli) +
 * $conn (alias). Bedanya, sekarang juga otomatis:
 *   • Inisialisasi session aman (HttpOnly, SameSite, regenerasi)
 *   • Pasang security headers (CSP, X-Frame-Options, dll.)
 *   • Bersihkan output buffer agar header tidak bocor sebelum waktunya
 *   • Sediakan tz_db() + tz_e() + tz_csrf_*() ke seluruh file PHP
 *
 * BACKWARD COMPAT: $koneksi & $conn tetap mysqli supaya semua file lama
 * (yang belum dimigrasi ke prepared) tetap jalan. Tetapi setiap query
 * baru WAJIB pakai tz_db() + parameter binding.
 */

require_once __DIR__ . '/_security.php';
tz_security_init();

// Kompatibilitas variabel lama
$koneksi = tz_legacy_mysqli();
$conn    = $koneksi;
