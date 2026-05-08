<?php
include 'koneksi.php';

$id_game = $_POST['id_game'];
$nama_user = $_POST['nama_user']; // Ini dapet dari variabel $nama_tampil tadi
$rating = $_POST['rating'];
$komentar = $_POST['komentar'];
$slug = $_POST['slug'];

// Masukin ke kolom user_name sesuai screenshot DB lu
$query = "INSERT INTO reviews (id_game, user_name, rating, komentar) 
          VALUES ('$id_game', '$nama_user', '$rating', '$komentar')";

if(mysqli_query($conn, $query)) {
    header("Location: game_detail.php?game=$slug");
} else {
    echo "Gagal: " . mysqli_error($conn);
}
?>