<?php
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

if (isset($_GET['id']) && isset($_SESSION['id_user'])) {
    $id_keranjang = $_GET['id'];
    $id_user      = $_SESSION['id_user'];

    $query = "DELETE FROM keranjang WHERE id_keranjang = '$id_keranjang' AND id_user = '$id_user'";

    if (mysqli_query($conn, $query)) {
        tz_log('common', 'CART_REMOVE', "Item keranjang dihapus", [
            'id_keranjang' => $id_keranjang,
            'id_user'      => $id_user,
        ]);
        echo json_encode(['status' => 'sukses']);
    } else {
        tz_log('error', 'CART_REMOVE_DB_ERROR', "Gagal hapus item keranjang", [
            'id_keranjang' => $id_keranjang,
            'db_error'     => mysqli_error($conn),
        ]);
        echo json_encode(['status' => 'gagal', 'pesan' => mysqli_error($conn)]);
    }
} else {
    tz_log('warning', 'CART_REMOVE_UNAUTHORIZED', "Akses hapus keranjang ditolak — tidak ada session", []);
    echo json_encode(['status' => 'gagal', 'pesan' => 'Akses ditolak mprruy!']);
}
?>
