<?php
include 'koneksi.php';

if (isset($_POST['id_order'])) {
    $id = $_POST['id_order'];

    // Ambil info order dulu untuk log
    $qInfo = mysqli_query($koneksi, "SELECT game_name, paket, total_price FROM orders WHERE id_order = '$id'");
    $info  = mysqli_fetch_assoc($qInfo);

    $query = "UPDATE orders SET status = 'selesai' WHERE id_order = '$id'";
    $exec  = mysqli_query($koneksi, $query);

    if ($exec) {
        tz_log('critical', 'ORDER_COMPLETED', "Admin menandai order #{$id} sebagai selesai", [
            'id_order'  => $id,
            'game'      => $info['game_name'] ?? '-',
            'paket'     => $info['paket']     ?? '-',
            'harga'     => $info['total_price']?? 0,
            'new_status'=> 'selesai',
        ]);
        echo "success";
    } else {
        tz_log('error', 'ORDER_STATUS_UPDATE_ERROR', "Gagal update status order #{$id}", [
            'id_order' => $id,
            'db_error' => mysqli_error($koneksi),
        ]);
        echo "error: " . mysqli_error($koneksi);
    }
} else {
    tz_log('warning', 'ORDER_STATUS_NO_ID', "update_status.php dipanggil tanpa id_order", []);
    echo "no_id_received";
}
?>
