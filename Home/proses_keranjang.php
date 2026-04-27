<?php 
session_start();
include 'koneksi.php'; 

if (isset($_POST['nama_produk'])) {
    // Cek dulu apakah session ada, biar gak error kalau session mati
    if (!isset($_SESSION['id_user'])) {
        echo "Gagal: Session User Hilang";
        exit;
    }

    $id_user = $_SESSION['id_user'];
    $nama_produk = $_POST['nama_produk'];
    $harga = $_POST['harga'];
    $qty = $_POST['qty'];
    $tanggal = date('Y-m-d H:i:s');
    $id_game_tujuan = $_POST['id_game_tujuan'] ?? '-'; // Tambahin ini mprruy biar DB gak nolak

    // Tambahkan kolom id_game_tujuan di query
    $query = "INSERT INTO keranjang (id_user, nama_produk, harga, qty, tanggal, id_game_tujuan) 
              VALUES ('$id_user', '$nama_produk', '$harga', '$qty', '$tanggal', '$id_game_tujuan')";
    
    if (mysqli_query($conn, $query)) {
        echo "Sukses";
    } else {
        // Ini bakal muncul di Console F12 kalau gagal
        echo "Gagal: " . mysqli_error($conn);
    }
}
?>