<?php
session_start();

// 1. HAPUS SEMUA SESSION LAMA (Biar gak login otomatis)
session_unset();
session_destroy();

// 2. MULAI SESSION BARU UNTUK GUEST
session_start();
$_SESSION['nama_user'] = "Guest_" . rand(100, 999);
$_SESSION['foto'] = "Default.jpeg";
unset($_SESSION['user_id']); // WAJIB: Biar ID user sebelumnya kehapus total
// 3. JANGAN SET $_SESSION['user_id'] 
// Ini kuncinya! Tanpa user_id, fitur bakal terkunci.

header("Location: ../Home/index.php"); // Sesuaikan folder lo
exit();
?>