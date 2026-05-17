<?php
include 'koneksi.php';

if (isset($_POST['id_order'])) {
    $id = $_POST['id_order'];
    
    // Kita paksa ubah ke 'selesai'
    $query = "UPDATE orders SET status = 'selesai' WHERE id_order = '$id'";
    $exec = mysqli_query($koneksi, $query);

    if ($exec) {
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
} else {
    echo "no_id_received";
}
?>