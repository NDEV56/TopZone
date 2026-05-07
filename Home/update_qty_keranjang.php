<?php
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

$id_keranjang = $_POST['id_keranjang'] ?? 0;
$qty_baru     = (int)($_POST['qty'] ?? 1);
$id_user      = $_SESSION['id_user'] ?? 0;

if (!$id_user) {
    tz_log('warning', 'CART_QTY_UNAUTHORIZED', "Update qty keranjang ditolak — tidak ada session", []);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$q = "UPDATE keranjang SET qty = '$qty_baru' WHERE id_keranjang = '$id_keranjang' AND id_user = '$id_user'";
if (mysqli_query($conn, $q)) {
    tz_log('common', 'CART_QTY_UPDATED', "Qty keranjang diperbarui", [
        'id_keranjang' => $id_keranjang,
        'id_user'      => $id_user,
        'qty_baru'     => $qty_baru,
    ]);
    echo json_encode(['status' => 'sukses']);
} else {
    tz_log('error', 'CART_QTY_DB_ERROR', "Gagal update qty keranjang", [
        'id_keranjang' => $id_keranjang,
        'db_error'     => mysqli_error($conn),
    ]);
    echo json_encode(['status' => 'gagal']);
}
?>
