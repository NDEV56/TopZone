<?php
session_start();
include 'koneksi.php'; // Ganti sesuai file koneksi lo

header('Content-Type: application/json');

// Cek apakah user sudah login dan ada ID itemnya
if (isset($_GET['id']) && isset($_SESSION['id_user'])) {
    $id_keranjang = $_GET['id'];
    $id_user = $_SESSION['id_user'];

    // Query hapus, pastikan id_user juga dicek biar gak hapus punya orang
    $query = "DELETE FROM keranjang WHERE id_keranjang = '$id_keranjang' AND id_user = '$id_user'";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'sukses']);
    } else {
        echo json_encode(['status' => 'gagal', 'pesan' => mysqli_error($conn)]);
    }
} else {
    echo json_encode(['status' => 'gagal', 'pesan' => 'Akses ditolak mprruy!']);
}
?>