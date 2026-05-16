<?php
/**
 * masuk_guest.php — Guest mode (HARDENED v3.1)
 * Sebelumnya double session_start memicu warning. Sekarang dipusatkan.
 */
require_once __DIR__ . '/../Home/_security.php';
tz_security_init();

// Bersihkan sesi user lama
$_SESSION = [];
session_regenerate_id(true);

// Set sesi guest
$_SESSION['nama_user'] = 'Guest_' . random_int(1000, 9999);
$_SESSION['foto']      = 'Default.jpg';
// id_user TIDAK di-set — guest tidak punya akses fitur user

tz_safe_redirect('/Home/index.php');
