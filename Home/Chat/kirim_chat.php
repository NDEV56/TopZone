<?php
session_start();
include '../koneksi.php';

if(isset($_SESSION['id_user'])) {
    $id_user = $_SESSION['id_user'];
    $pesan = mysqli_real_escape_string($koneksi, $_POST['pesan']);
    
    // 1. Upload Gambar jika ada
    if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $nama_file = "IMG_USER_" . time() . "." . $ext;
        $path = "../uploads/" . $nama_file; // Pastiin folder 'uploads' ada di luar folder Chat
        
        if(move_uploaded_file($_FILES['gambar']['tmp_name'], $path)) {
            mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES ('$id_user', '$nama_file', 'user', 0)");
        }
    }

    // 2. Simpan Teks jika ada
    if(!empty(trim($pesan))) {
        mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES ('$id_user', '$pesan', 'user', 0)");
    }
}
?>