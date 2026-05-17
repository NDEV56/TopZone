<?php
session_start();
include 'koneksi.php'; 
header('Content-Type: application/json');

$id_user = $_SESSION['id_user'] ?? 0;

// Query ini ngambil:
// 1. Data belanjaan (nama_produk, harga, qty) dari tabel KERANJANG (k)
// 2. Data Master (nama_game, gambar) dari tabel GAMES (g)
// ... (bagian awal sama)
$query = "SELECT 
            k.id_keranjang, k.nama_produk, k.harga, k.qty, 
            g.nama_game, g.gambar 
          FROM keranjang k 
          LEFT JOIN games g ON k.id_game = g.id 
          WHERE k.id_user = '$id_user' 
          ORDER BY k.id_keranjang DESC";
// ... (sisanya sama)

$result = mysqli_query($conn, $query);
$data_keranjang = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Kalau di table games nggak ada (id gak cocok), kasih default
    if (empty($row['gambar'])) {
        $row['gambar'] = "Default.jpg";
    }
    $data_keranjang[] = $row;
}

echo json_encode($data_keranjang);
?>