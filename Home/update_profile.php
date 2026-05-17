<?php
session_start();
include 'koneksi.php';

if (isset($_POST['btn_simpan'])) {
    $id_user = $_SESSION['id_user']; 
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['username']);
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $pass_baru = $_POST['password'];

    // 1. Ambil foto lama buat jaga-jaga kalau user gak ganti foto
    $nama_file = $_SESSION['foto'] ?? 'Default.jpg';

    // 2. PROSES FOTO (TARUH DI SINI)
    if (!empty($_POST['foto_base64'])) {
        $foto_data = $_POST['foto_base64'];
        
        // Bersihin header base64 (biar gak korup datanya)
        // Kita pake regex biar lebih aman buat berbagai format (png/jpg)
        if (preg_match('/^data:image\/(\w+);base64,/', $foto_data, $type)) {
            $foto_data = substr($foto_data, strpos($foto_data, ',') + 1);
            $foto_data = str_replace(' ', '+', $foto_data);
            $data = base64_decode($foto_data);
            
            // Bikin nama file unik
            $nama_file = 'pp_' . $id_user . '_' . time() . '.png';
            $path = 'uploads/' . $nama_file;
            
            // Simpan file ke folder uploads
            if (file_put_contents($path, $data)) {
                $_SESSION['foto'] = $nama_file; // Update session foto
            }
        }
    }

    // 3. SUSUN QUERY UPDATE
    $sql_update = "UPDATE users SET 
                    nama_user = '$nama_baru', 
                    username = '$user_baru', 
                    email = '$email_baru', 
                    foto = '$nama_file'";
    
    // Cek kalau ganti password
    if (!empty($pass_baru)) {
        $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
        $sql_update .= ", password = '$hashed_pass'";
    }

    $sql_update .= " WHERE id = '$id_user'";
    
    // 4. EKSEKUSI KE DATABASE
    if (mysqli_query($conn, $sql_update)) {
        // Update Session biar di sidebar langsung berubah tanpa logout
        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username'] = $user_baru;
        $_SESSION['email'] = $email_baru;
        
        echo "<script>alert('Profil Berhasil Diupdate mprruy! 🔥'); window.location='index.php';</script>";
    } else {
        die("Waduh Error DB mprruy: " . mysqli_error($conn));
    }
}
?>