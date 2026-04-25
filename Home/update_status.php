<?php
include 'koneksi.php';
$id = $_POST['id'];
$st = $_POST['st'];
mysqli_query($conn, "UPDATE orders SET status='$st' WHERE id_order='$id'");
header("Location: admin.php");
?>