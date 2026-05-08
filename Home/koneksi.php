<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "topzone"; // Sesuaikan nama DB lo

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// TRIK SAKTI:
$conn = $koneksi; 
?>