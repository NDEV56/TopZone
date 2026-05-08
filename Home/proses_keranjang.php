<?php 
session_start();
include 'koneksi.php'; 

if (isset($_POST['nama_produk'])) {
    $id_user = $_SESSION['id_user'];
    $id_game = $_POST['id_game']; // ID yang dikirim dari JS tadi
    $nama_produk = $_POST['nama_produk'];
    $harga = $_POST['harga'];
    $qty = $_POST['qty'];
    $tanggal = date('Y-m-d H:i:s');

    // Gunakan nama kolom 'id_game' sesuai di screenshot phpMyAdmin lu
    $query = "INSERT INTO keranjang (id_user, id_game, nama_produk, harga, qty, tanggal) 
          VALUES ('$id_user', '$id_game', '$nama_produk', '$harga', '$qty', '$tanggal')";
    
    if(mysqli_query($conn, $query)) {
        echo "Sukses";
    } else {
        echo "Gagal: " . mysqli_error($conn);
    }
}
?>