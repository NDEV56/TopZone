<?php
session_start();
include '../Home/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil data dari form (name="username" di HTML)
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password'];

    // 2. Query pake variabel $user yang bener!
    $query = "SELECT * FROM users WHERE username = '$user'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $data = mysqli_fetch_assoc($result);
        
        // 3. Verifikasi Password
        if (password_verify($pass, $data['password'])) {
            // SIMPAN DATA KE SESSION
            $_SESSION['id_user'] = $data['id'];
            $_SESSION['username'] = $data['username']; 
            $_SESSION['nama_user'] = $data['nama_user'];
            $_SESSION['email'] = $data['email']; 
            $_SESSION['foto'] = $data['foto'] ?? 'Default.jpeg';

            // Lempar ke Home
            header("Location: ../Home/index.php"); 
            exit();
        } else {
            echo "<script>alert('Password salah mprruy!'); window.location='tampilanlogin.php';</script>";
        }
    } else {
        // Karena variabel tadi bener, sekarang bagian ini gak bakal salah panggil lagi
        echo "<script>alert('Akun belum terdaftar di database mprruy!'); window.location='tampilandaftar.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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

        <form action="tampilanlogin.php" method="POST">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <div class="btn-wrap">
                <button type="submit" class="btn-login">LOGIN</button>
            </div>
        </form>
        
        <div class="footer-link" style="text-align: center; margin-top: 15px; font-size: 14px; color: #fff;">
            Belum punya akun? 
            <a href="tampilandaftar.php" style="color: #ffcc00; text-decoration: none; font-weight: bold;">Daftar di sini</a>
        </div>
    </div> 
</body>
</html>