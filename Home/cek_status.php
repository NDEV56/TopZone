<?php
include 'koneksi.php';
$id = $_GET['id'];
$q = mysqli_query($conn, "SELECT status FROM orders WHERE id_order='$id'");
echo json_encode(mysqli_fetch_assoc($q));
?>