<?php
session_start();
include 'koneksi.php';

if (isset($_GET['id'])) {
    // Hapus yang lama, ganti jadi ini (pake variabel $koneksi lo):
    $id_order = mysqli_real_escape_string($koneksi, $_GET['id']);

    // 1. Update status order jadi 'selesai'
    $update_order = "UPDATE orders SET status = 'selesai' WHERE id_order = '$id_order'";
    mysqli_query($koneksi, $update_order);

    if (mysqli_affected_rows($koneksi) > 0) {
        // 2. Ambil data game_name dari orderan tersebut
        $q_order = mysqli_query($koneksi, "SELECT game_name FROM orders WHERE id_order = '$id_order'");
        $d_order = mysqli_fetch_assoc($q_order);
        $nama_paket = $d_order['game_name'];

        // 3. Update jumlah TERJUAL di tabel 'games'
        // Mencari nama game yang ada di dalam teks paket (misal paket "400 Robux" cocok dengan game "Roblox")
        $update_terjual = "UPDATE games SET terjual = terjual + 1 
                           WHERE '$nama_paket' LIKE CONCAT('%', nama_game, '%')";
        mysqli_query($koneksi, $update_terjual);
    }

    // Kembali ke halaman index/status
    header("Location: index.php");
    exit();
}
?>