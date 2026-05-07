<?php
session_start();
include '../Home/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
    $user  = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username = '$user'");
    if (mysqli_num_rows($cek) > 0) {
        // ── LOG: Username sudah ada ──
        tz_log('warning', 'REGISTER_DUPLICATE_USERNAME', "Registrasi gagal — username '{$user}' sudah dipakai", [
            'username' => $user,
            'email'    => $email,
        ]);
        echo "<script>alert('Username udah dipake mprruy!'); window.location='tampilandaftar.php';</script>";
    } else {
        $query = "INSERT INTO users (nama_user, username, email, password, foto)
                  VALUES ('$nama', '$user', '$email', '$pass', 'Default.jpg')";

        if (mysqli_query($conn, $query)) {
            $newId = mysqli_insert_id($conn);
            // ── LOG: Registrasi berhasil ──
            tz_log('critical', 'REGISTER_SUCCESS', "Akun baru dibuat untuk '{$user}'", [
                'id_user'  => $newId,
                'username' => $user,
                'nama'     => $nama,
                'email'    => $email,
            ]);
            echo "<script>alert('Daftar berhasil! Login ya mprruy.'); window.location='tampilanlogin.php';</script>";
        } else {
            // ── LOG: DB error saat register ──
            tz_log('error', 'REGISTER_DB_ERROR', "Gagal insert user baru ke database", [
                'username' => $user,
                'db_error' => mysqli_error($conn),
            ]);
            echo "Gagal daftar: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - TopZone</title>
    <link rel="stylesheet" href="tampilandaftar.css">
</head>
<body>
<div id="stage">
    <div class="orb-layer">
        <div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
        <div class="orb o4"></div><div class="orb o5"></div><div class="orb o6"></div>
    </div>
    <div class="noise-overlay"></div>
    <div class="vignette"></div>
    <div class="top-fade"></div>
    <div class="bottom-fade"></div>
    <div class="card">
        <div class="logo-wrap">
            <img class="logo-img" src="logotopzone.png" alt="TopZone Logo"/>
            <div class="logo-text">TOPZONE</div>
        </div>
        <form action="tampilandaftar.php" method="POST">
            <div class="field">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" placeholder="Masukkan nama lengkap" required>
            </div>
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Minimal 4 karakter" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" placeholder="contoh@email.com" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Minimal 8 karakter" required>
            </div>
            <div class="btn-wrap">
                <button type="submit" class="btn-daftar">DAFTAR SEKARANG</button>
            </div>
        </form>
        <div class="footer-link">Masuk sebagai <a href="masuk_guest.php">Guest</a></div>
        <div class="footer-link">Sudah punya akun? <a href="tampilanlogin.php">Masuk di sini</a></div>
    </div>
</div>
</body>
</html>
