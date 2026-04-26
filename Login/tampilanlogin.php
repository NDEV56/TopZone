<?php
session_start();
include '../Home/koneksi.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            // PENTING: Gunakan nama kolom yang bener di DB (biasanya 'id')
            $_SESSION['id_user']   = $user['id']; 
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama_user'] = $user['nama_user'];
            $_SESSION['email']     = $user['email']; // Tambahin ini biar sidebar sinkron
            $_SESSION['foto']      = $user['foto'];
            
            header("Location: ../Home/index.php");
            exit();
        } else {
            echo "<script>alert('Password salah mprruy!'); window.location='tampilanlogin.php';</script>";
        }
    } else {
        echo "<script>alert('Username gak terdaftar!'); window.location='tampilanlogin.php';</script>";
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
        
        <div class="footer-link">
            Belum punya akun? <a href="tampilandaftar.php">Daftar di sini</a>
        </div>
    </div> 
</body>
</html>