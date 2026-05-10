<?php
session_start();
include '../koneksi.php';

if(isset($_SESSION['id_user'])) {
    $id_user = $_SESSION['id_user'];
    $pesan = mysqli_real_escape_string($koneksi, $_POST['pesan']);
    
    // 1. Jika ada Gambar
    if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $nama_file = "IMG_USER_" . time() . "." . $ext;
        $path = "../../uploads/" . $nama_file; // Sesuaikan folder upload lo
        
        if(move_uploaded_file($_FILES['gambar']['tmp_name'], $path)) {
            // Simpan nama file sebagai pesan, tapi tandai pengirim 'user'
            mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) 
                                   VALUES ('$id_user', '$nama_file', 'user', 0)");
        }
    }

    // 2. Jika ada Teks saja (tanpa gambar) atau teks tambahan
    if(!empty(trim($pesan)) && !isset($_FILES['gambar'])) {
        mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) 
                               VALUES ('$id_user', '$pesan', 'user', 0)");
    }
}
?>