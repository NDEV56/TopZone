<?php
session_start();
include '../koneksi.php';

if(isset($_SESSION['id_user'])) {
    $id_user = $_SESSION['id_user'];
    $pesan = isset($_POST['pesan']) ? mysqli_real_escape_string($koneksi, $_POST['pesan']) : '';
    $nama_file = "";

    // 1. Tentukan folder tujuan & BIKIN KALAU BELUM ADA
    $targetDir = "../uploads/"; // Mundur satu langkah dari folder Chat untuk nemu folder uploads
    $namaFile = "IMG_USER_" . uniqid() . "_" . time() . ".jpg";
    $targetFile = $targetDir . $namaFile;
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true); // Bikin folder otomatis dengan permission penuh
    }

    // 2. Jika ada Gambar
    if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        if(empty($ext)) $ext = "jpg"; 
        
        $nama_file = "IMG_USER_" . uniqid() . "_" . time() . "." . $ext;
        $path = $targetDir . $nama_file; 
        
        if(move_uploaded_file($_FILES['gambar']['tmp_name'], $path)) {
            // Masukin nama file gambar ke DB
            mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) 
                                   VALUES ('$id_user', '$nama_file', 'user', 0)");
        }
    }

    // 3. Jika ada Teks
    if(!empty(trim($pesan))) {
        mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, is_read) 
                               VALUES ('$id_user', '$pesan', 'user', 0)");
    }
}
?>