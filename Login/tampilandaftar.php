<?php
/**
 * tampilandaftar.php — Register form (HARDENED v3.1)
 * ───────────────────────────────────────────────────
 *   • Prepared statements
 *   • CSRF + rate-limit
 *   • Validasi panjang/format username, password min 8
 *   • Tidak bocor mysqli_error ke user
 */
require_once __DIR__ . '/../Home/_security.php';
tz_security_init();

if (tz_is_logged_in()) tz_safe_redirect('/Home/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tz_csrf_verify();
    $ip = tz_client_ip();
    if (!tz_rate_limit('register:' . $ip, 4, 600)) {
        echo "<script>alert('Terlalu banyak percobaan daftar dari IP ini. Coba lagi nanti.'); window.location='tampilandaftar.php';</script>";
        exit;
    }

    $nama  = trim((string)($_POST['nama']     ?? ''));
    $user  = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email']    ?? ''));
    $pass  =      (string)($_POST['password'] ?? '');

    // Validasi
    $errs = [];
    if ($nama === '' || strlen($nama) > 64)                          $errs[] = 'Nama tidak valid';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user))               $errs[] = 'Username harus 3-32 karakter (huruf, angka, _, ., -)';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) $errs[] = 'Email tidak valid';
    if (strlen($pass) < 8 || strlen($pass) > 200)                    $errs[] = 'Password minimal 8 karakter';

    if (!empty($errs)) {
        $msg = addslashes(implode(' • ', $errs));
        echo "<script>alert('$msg'); window.location='tampilandaftar.php';</script>";
        exit;
    }

    $cek = tz_db()->fetchOne('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1', [$user, $email]);
    if ($cek) {
        echo "<script>alert('Username atau email sudah dipakai.'); window.location='tampilandaftar.php';</script>";
        exit;
    }

    try {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        tz_db()->exec(
            'INSERT INTO users (nama_user, username, email, password, foto)
             VALUES (?, ?, ?, ?, ?)',
            [$nama, $user, $email, $hashed, 'Default.jpg']
        );
        echo "<script>alert('Daftar berhasil! Silakan login.'); window.location='tampilanlogin.php';</script>";
        exit;
    } catch (\Throwable $e) {
        error_log('[topzone-register] ' . $e->getMessage());
        echo "<script>alert('Terjadi kendala. Coba lagi.'); window.location='tampilandaftar.php';</script>";
        exit;
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
            <div class="orb o1"></div>
            <div class="orb o2"></div>
            <div class="orb o3"></div>
            <div class="orb o4"></div>
            <div class="orb o5"></div>
            <div class="orb o6"></div>
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

        <form action="tampilandaftar.php" method="POST" autocomplete="on">
            <?= tz_csrf_field() ?>
            <div class="field">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" placeholder="Masukkan nama lengkap"
                       required maxlength="64" autocomplete="name">
            </div>
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Minimal 3 karakter"
                       required minlength="3" maxlength="32"
                       pattern="[A-Za-z0-9_.\-]{3,32}" autocomplete="username">
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" placeholder="contoh@email.com"
                       required maxlength="128" autocomplete="email">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Minimal 8 karakter"
                       required minlength="8" maxlength="200" autocomplete="new-password">
            </div>
            <div class="btn-wrap">
                <button type="submit" class="btn-daftar">DAFTAR SEKARANG</button>
            </div>
        </form>

       <div class="footer-link">
            Masuk sebagai <a href="masuk_guest.php">Guest</a>
        </div>

        <div class="footer-link">
            Sudah punya akun? <a href="tampilanlogin.php">Masuk di sini</a>
        </div>

        <script>
            let cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.onmousemove = function(e) {
                   let x = e.pageX - card.offsetLeft;
                   let y = e.pageY - card.offsetTop;

                   card.style.setProperty('--x', x + 'px');
                   card.style.setProperty('--y', y + 'px');
                };
            })
        </script>

    </div>
</body>
</html>
