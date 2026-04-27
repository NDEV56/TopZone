<?php
session_start();
include '../Home/koneksi.php';

if (isset($_POST['btn_simpan'])) {
    // PASTIKAN NAMA SESSION-NYA SAMA (Kalau di login pakai id_user, di sini id_user)
    $id_user = $_SESSION['id_user']; 
    
    if (empty($id_user)) {
        die("Waduh mprruy, ID lo gak kebaca. Coba login ulang!");
    }

    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['username']);
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $pass_baru = $_POST['password'];
    $foto_data = $_POST['foto_base64'];

    // Ambil foto lama dari session
    $nama_file = $_SESSION['foto'] ?? 'Default.jpg';

    // 1. Cek urusan Foto
    if (!empty($foto_data)) {
        // Hapus foto lama biar gak menuh-menuhin storage (Opsional)
        if ($nama_file != 'Default.jpg' && file_exists('uploads/' . $nama_file)) {
            unlink('uploads/' . $nama_file);
        }

        list($type, $foto_data) = explode(';', $foto_data);
        list(, $foto_data)      = explode(',', $foto_data);
        $data = base64_decode($foto_data);
        
        // Nama file baru
        $nama_file = 'pp_' . $id_user . '_' . time() . '.png';
        file_put_contents('uploads/' . $nama_file, $data);
        
        // UPDATE SESSION FOTO
        $_SESSION['foto'] = $nama_file;
    }

    // 2. Build Query Update
    $sql_update = "UPDATE users SET 
                   nama_user = '$nama_baru', 
                   username = '$user_baru', 
                   email = '$email_baru', 
                   foto = '$nama_file'";
    
    if (!empty($pass_baru)) {
        $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
        $sql_update .= ", password = '$hashed_pass'";
    }

    // PASTIKAN 'id' di sini nama kolom di Tabel MySQL lo
    $sql_update .= " WHERE id = '$id_user'"; 
    
    if (mysqli_query($conn, $sql_update)) {
        // UPDATE SEMUA SESSION BIAR SINKRON
        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username'] = $user_baru;
        $_SESSION['email'] = $email_baru;
        $_SESSION['foto'] = $nama_file; // Pastikan ini terupdate
        
        echo "<script>alert('Profil Berhasil Diupdate Permanen!'); window.location='index.php';</script>";
    } else {
        die("Waduh Error DB mprruy: " . mysqli_error($conn));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TopZone</title>
    <link rel="stylesheet" href="tampilanlogin.css">
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="card">
        <div class="logo-wrap">
            <img class="logo-img" src="logotopzone.png" alt="TopZone Logo"/>
            <div class="logo-text">TOPZONE</div>
        </div>

        <form action="login_proses.php" method="POST">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <div class="btn-wrap">
                <button type="submit" name="btn_login" class="btn-login">LOGIN</button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="masuk_guest.php" style="color: #a0a0a0; font-size: 13px; text-decoration: none;">
                Masuk sebagai Guest
            </a>
        </div>
        
        <div class="footer-link">
            Belum punya akun? <a href="tampilandaftar.php">Daftar di sini</a>
        </div>
    </div> 
</body>
</html>