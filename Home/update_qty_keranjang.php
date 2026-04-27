<?php
session_start();
include 'koneksi.php';

if (isset($_POST['id']) && isset($_POST['qty'])) {
    $id = $_POST['id'];
    $qty = $_POST['qty'];
    $id_user = $_SESSION['id_user'];

    $query = "UPDATE keranjang SET qty = '$qty' WHERE id_keranjang = '$id' AND id_user = '$id_user'";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'sukses']);
    } else {
        echo json_encode(['status' => 'gagal']);
    }
}
?>