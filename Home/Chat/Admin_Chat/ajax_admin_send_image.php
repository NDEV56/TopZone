<?php
include '../../koneksi.php';

if (isset($_POST['id_user'])) {
    $id_user = mysqli_real_escape_string($koneksi, $_POST['id_user']); // Tambah escape
    $pesan = mysqli_real_escape_string($koneksi, $_POST['pesan']);
    
    // 1. Cek Upload Gambar
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $file = $_FILES['gambar'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nama_file = "IMG_" . time() . "_" . rand(100,999) . "." . $ext;
        $path = "../../uploads/" . $nama_file;
        
        if (move_uploaded_file($file['tmp_name'], $path)) {
            // Masukkan gambar sebagai pesan chat
            mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, waktu) 
                                    VALUES ('$id_user', '$nama_file', 'admin', NOW())");
        }
    }

    // 2. Masukkan Pesan Teks (Jika ada pesan dan bukan cuma gambar)
    if (!empty(trim($pesan))) {
        mysqli_query($koneksi, "INSERT INTO chat (id_user, pesan, pengirim, waktu) 
                                VALUES ('$id_user', '$pesan', 'admin', NOW())");
    }
    
    echo "success"; // Beri feedback ke AJAX
}
?>