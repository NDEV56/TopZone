<?php
session_start();
include 'koneksi.php';

if (isset($_POST['btn_simpan'])) {
    $id_user = $_SESSION['id_user']; 
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['username']);
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $pass_baru = $_POST['password'];
    $foto_data = $_POST['foto_base64'];

    // Ambil foto lama buat jaga-jaga
    $nama_file = $_SESSION['foto'] ?? 'Default.jpg';

    // 1. Cek urusan Foto
    if (!empty($foto_data)) {
        list($type, $foto_data) = explode(';', $foto_data);
        list(, $foto_data)      = explode(',', $foto_data);
        $data = base64_decode($foto_data);
        $nama_file = 'pp_' . $id_user . '_' . time() . '.png';
        file_put_contents('uploads/' . $nama_file, $data);
        $_SESSION['foto'] = $nama_file;
    }

    // 2. Cek urusan Password (di-hash biar aman mprruy)
    $sql_update = "UPDATE users SET nama_user = '$nama_baru', username = '$user_baru', email = '$email_baru', foto = '$nama_file'";
    
    if (!empty($pass_baru)) {
        $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
        $sql_update .= ", password = '$hashed_pass'";
    }

    // Gunakan 'id' sesuai error database lo sebelumnya
    $sql_update .= " WHERE id = '$id_user'";
    
    if (mysqli_query($conn, $sql_update)) {
        // Update Session biar di index.php langsung berubah
        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username'] = $user_baru;
        $_SESSION['email'] = $email_baru;
        
        echo "<script>alert('Profil Lengkap Berhasil Diupdate!'); window.location='index.php';</script>";
    } else {
        die("Waduh Error DB mprruy: " . mysqli_error($conn));
    }
}
?>