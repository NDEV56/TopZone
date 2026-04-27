<?php
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

$id_user = $_SESSION['id_user'];

$query = "SELECT * FROM keranjang WHERE id_user = '$id_user' ORDER BY id_keranjang DESC";
$result = mysqli_query($conn, $query);

$data_keranjang = [];
while ($row = mysqli_fetch_assoc($result)) {
    $produk = strtolower($row['nama_produk']);
    $gambar = "Default.jpg"; // Default kalau gak ketemu


    if (strpos($produk, 'robux') !== false || strpos($produk, 'roblox') !== false) {
        $gambar = "Roblox.jpg";
    } else if (strpos($produk, 'mobile legends') !== false || strpos($produk, 'mlbb') !== false) {
        $gambar = "MLBB.JPEG";
    } else if (strpos($produk, 'free fire') !== false || strpos($produk, 'ff') !== false) {
        $gambar = "FF.jpg";
    } else if (strpos($produk, 'genshin') !== false) {
        $gambar = "Genshin.jpg";
    } else {
        // Kalau nggak ada kata kunci di atas, coba cari berdasarkan harga yang mirip di tabel games
        $harga = $row['harga'];
        $cari = mysqli_query($conn, "SELECT gambar FROM games WHERE harga = '$harga' LIMIT 1");
        $hasil = mysqli_fetch_assoc($cari);
        if ($hasil) {
            $gambar = $hasil['gambar'];
        }
    }

    $row['gambar'] = $gambar;
    $data_keranjang[] = $row;
}

echo json_encode($data_keranjang);
?>