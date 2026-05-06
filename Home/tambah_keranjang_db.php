<?php
session_start();
include 'koneksi.php';

$id_user = $_SESSION['id_user'] ?? 0;

// Di file proses tambah keranjang lu:
$id = $_POST['id']; // ID ini HARUS DIAMBIL dari halaman game
$nama_produk = $_POST['nama_produk'];
$harga = $_POST['harga'];

// Query INSERT-nya harus nyimpen id_game
$sql = "INSERT INTO keranjang (id_user, id, nama_produk, harga, qty) 
        VALUES ('$id_user', '$id', '$nama_produk', '$harga', 1)";
$result = mysqli_query($koneksi, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>