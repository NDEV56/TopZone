<?php
include 'koneksi.php';
session_start();

$nama_produk = $_POST['nama_produk'] ?? '';
$harga = $_POST['harga'] ?? 0;
$id_game = $_POST['id_game'] ?? '';

// Masukin ke tabel keranjang lo
$query = "INSERT INTO keranjang (nama_produk, harga, id_game) VALUES ('$nama_produk', '$harga', '$id_game')";

if(mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'sukses']);
} else {
    echo json_encode(['status' => 'error', 'pesan' => mysqli_error($conn)]);
}
?>