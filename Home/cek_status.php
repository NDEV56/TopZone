<?php
include 'koneksi.php';
$id = $_GET['id'];
$q  = mysqli_query($conn, "SELECT status, game_name, paket FROM orders WHERE id_order='$id'");
$row = mysqli_fetch_assoc($q);

if ($row) {
    tz_log('common', 'ORDER_STATUS_CHECK', "Cek status order #{$id} → {$row['status']}", [
        'id_order' => $id,
        'status'   => $row['status'],
        'game'     => $row['game_name'],
        'paket'    => $row['paket'],
    ]);
} else {
    tz_log('uncommon', 'ORDER_STATUS_NOT_FOUND', "Cek status order #{$id} — tidak ditemukan", [
        'id_order' => $id,
    ]);
}
echo json_encode($row);
?>
