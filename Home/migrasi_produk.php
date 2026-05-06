<?php
include 'koneksi.php';

// Ambil semua game dari tabel games
$games = mysqli_query($koneksi, "SELECT id, nama_game, harga FROM games");

while($g = mysqli_fetch_assoc($games)) {
    $id_g = $g['id'];
    $nama_p = "Paket Dasar " . $g['nama_game'];
    $harga_p = $g['harga'];
    
    // Cek dulu biar gak double
    $cek = mysqli_query($koneksi, "SELECT * FROM produk_game WHERE id_game = '$id_g'");
    if(mysqli_num_rows($cek) == 0) {
        mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) 
                              VALUES ('$id_g', '$nama_p', '$harga_p', 'umum')");
    }
}
echo "Migrasi Berhasil! Cek halaman Kelola Paket lu.";
?>