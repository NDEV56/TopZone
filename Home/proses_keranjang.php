<?php
session_start();
include 'koneksi.php';

if (isset($_POST['nama_produk'])) {
    $id_user     = $_SESSION['id_user'] ?? 0;
    $id_game     = $_POST['id_game'];
    $nama_produk = $_POST['nama_produk'];
    $harga       = $_POST['harga'];
    $qty         = $_POST['qty'];
    $tanggal     = date('Y-m-d H:i:s');

    if (!$id_user) {
        tz_log('warning', 'CART_UNAUTHORIZED', "Guest mencoba tambah keranjang tanpa login", [
            'produk' => $nama_produk,
        ]);
        echo "Unauthorized";
        exit;
    }

    $query = "INSERT INTO keranjang (id_user, id_game, nama_produk, harga, qty, tanggal)
              VALUES ('$id_user', '$id_game', '$nama_produk', '$harga', '$qty', '$tanggal')";

    if (mysqli_query($conn, $query)) {
        tz_log('common', 'CART_ADD', "Produk ditambahkan ke keranjang", [
            'id_user'  => $id_user,
            'id_game'  => $id_game,
            'produk'   => $nama_produk,
            'harga'    => $harga,
            'qty'      => $qty,
        ]);
        echo "Sukses";
    } else {
        tz_log('error', 'CART_ADD_DB_ERROR', "Gagal tambah keranjang ke database", [
            'id_user'  => $id_user,
            'produk'   => $nama_produk,
            'db_error' => mysqli_error($conn),
        ]);
        echo "Gagal: " . mysqli_error($conn);
    }
}
?>
