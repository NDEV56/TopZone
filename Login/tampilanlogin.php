<?php
/**
 * tampilanlogin.php — Login form (HARDENED v3.1)
 * ─────────────────────────────────────────────
 *   • Hapus blok update-profile yang misplaced (sumber SQLi)
 *   • Tambah CSRF token
 *   • Tetap pakai tampilan & UX lama
 */
require_once __DIR__ . '/../Home/_security.php';
tz_security_init();

// Kalau sudah login, langsung ke beranda
if (tz_is_logged_in()) {
    tz_safe_redirect('/Home/index.php');
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

        <form action="login_proses.php" method="POST" autocomplete="on">
            <?= tz_csrf_field() ?>
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username"
                       required autocomplete="username" maxlength="64"
                       pattern="[A-Za-z0-9_.-]{3,64}">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password"
                       required autocomplete="current-password" maxlength="200">
            </div>
            <div class="btn-wrap">
                <button type="submit" name="btn_login" class="btn-login">LOGIN</button>
            </div>
        </form>

        <div class="footer-link">
            Masuk sebagai <a href="masuk_guest.php">Guest</a>
        </div>

        <div class="footer-link">
            Belum punya akun? <a href="tampilandaftar.php">Daftar di sini</a>
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
