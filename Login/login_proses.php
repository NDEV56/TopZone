<?php
session_start();
include '../Home/koneksi.php';

if (isset($_POST['btn_login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['id_user']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama_user'] = $user['nama_user'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['foto']      = $user['foto'];

            // ── LOG: Login sukses ──
            tz_log('critical', 'LOGIN_SUCCESS', "User '{$user['username']}' berhasil login", [
                'id_user'   => $user['id'],
                'username'  => $user['username'],
                'nama_user' => $user['nama_user'],
                'email'     => $user['email'],
            ]);

            header("Location: ../Home/index.php");
            exit();
        } else {
            // ── LOG: Password salah ──
            tz_log('warning', 'LOGIN_FAILED_WRONG_PASSWORD', "Login gagal — password salah untuk username '{$username}'", [
                'username' => $username,
                'attempt'  => 'wrong_password',
            ]);
            echo "<script>alert('Password salah mprruy!'); window.location='tampilanlogin.php';</script>";
        }
    } else {
        // ── LOG: Username tidak ditemukan ──
        tz_log('warning', 'LOGIN_FAILED_USER_NOT_FOUND', "Login gagal — username '{$username}' tidak terdaftar", [
            'username' => $username,
            'attempt'  => 'user_not_found',
        ]);
        echo "<script>alert('Username tidak terdaftar!'); window.location='tampilanlogin.php';</script>";
    }
}
?>
