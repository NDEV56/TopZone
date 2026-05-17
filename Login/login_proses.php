<?php
session_start();
include '../Home/koneksi.php'; // Pastikan path ke koneksi bener

if (isset($_POST['btn_login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Cek Password
        if (password_verify($password, $user['password'])) {
            // SET SEMUA SESSION BIAR INDEX KENAL SIAPA LO
            $_SESSION['id_user']   = $user['id']; 
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama_user'] = $user['nama_user'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['foto']      = $user['foto'];
            
            header("Location: ../Home/index.php");
            exit();
        } else {
            echo "<script>alert('Password salah mprruy!'); window.location='tampilanlogin.php';</script>";
        }
    } else {
        echo "<script>alert('Username tidak terdaftar!'); window.location='tampilanlogin.php';</script>";
    }
}
?>