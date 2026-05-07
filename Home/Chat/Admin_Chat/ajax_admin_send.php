<?php
session_start();
include '../../koneksi.php';

if(isset($_POST['id_user']) && isset($_POST['pesan'])) {
    $id_user = $_POST['id_user'];
    $pesan = mysqli_real_escape_string($koneksi, $_POST['pesan']);
    
    // Simpan dengan pengirim 'admin'
    mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES ('$id_user', '$pesan', 'admin', 1)");
}
?>