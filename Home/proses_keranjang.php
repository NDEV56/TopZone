<?php 
session_start();
include 'koneksi.php'; 

if (isset($_POST['nama_produk'])) {
    // Pastikan user sudah login bray
    if (!isset($_SESSION['id_user'])) {
        echo "Gagal: Sesi user tidak ditemukan, silakan login kembali.";
        exit;
    }

    $id_user = $_SESSION['id_user'];
    $id_game = $_POST['id_game']; 
    $nama_produk = $_POST['nama_produk'];
    $harga = $_POST['harga'];
    $qty = $_POST['qty'];
    
    // Tangkap kiriman user_data dari JavaScript, bersihkan dari SQL Injection bray!
    $user_data = isset($_POST['user_data']) ? mysqli_real_escape_string($koneksi, $_POST['user_data']) : '';
    
    $tanggal = date('Y-m-d H:i:s');

    // Memasukkan data akun game ($user_data) ke kolom 'catatan' tabel keranjang lu
    $query = "INSERT INTO keranjang (id_user, id_game, nama_produk, harga, qty, catatan, tanggal) 
              VALUES ('$id_user', '$id_game', '$nama_produk', '$harga', '$qty', '$user_data', '$tanggal')";
    
    if(mysqli_query($koneksi, $query)) {
        echo "Sukses";
    } else {
        echo "Gagal: " . mysqli_error($koneksi);
    }
}
?>