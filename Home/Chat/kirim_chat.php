<?php
session_start();
include '../koneksi.php'; // Sesuaikan path

if(isset($_POST['pesan']) && isset($_SESSION['id_user'])) {
    $id_user = $_SESSION['id_user'];
    $pesan = mysqli_real_escape_string($koneksi, $_POST['pesan']);
    
    // Gunakan nama tabel 'chat' sesuai DB lo mprruy
    mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES ('$id_user', '$pesan', 'user', 0)");
    // Cukup, jangan pake header location!
}
?>