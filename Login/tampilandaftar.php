<?php
session_start();
include '../Home/koneksi.php';

// =========================
// REGISTER
// =========================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // =========================
    // INPUT
    // =========================
    $nama  = trim($_POST['nama']);
    $user  = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $konf  = $_POST['konfirmasi'];

    // =========================
    // ANTI XSS
    // =========================
    $nama  = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
    $user  = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    // =========================
    // VALIDASI KOSONG
    // =========================
    if (
        empty($nama) ||
        empty($user) ||
        empty($email) ||
        empty($pass) ||
        empty($konf)
    ) {

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Semua data wajib diisi!'
        ];

        header('Location: tampilandaftar.php');
        exit;
    }

    // =========================
    // VALIDASI USERNAME
    // =========================
    if (strlen($user) < 4) {

        $_SESSION['toast'] = [
            'icon' => 'warning',
            'title' => 'Username minimal 4 karakter!'
        ];

        header('Location: tampilandaftar.php');
        exit;
    }

    // =========================
    // VALIDASI PASSWORD
    // =========================
    if (strlen($pass) < 8) {

        $_SESSION['toast'] = [
            'icon' => 'warning',
            'title' => 'Password minimal 8 karakter!'
        ];

        header('Location: tampilandaftar.php');
        exit;
    }

    // =========================
    // PASSWORD TIDAK COCOK
    // =========================
    if ($pass !== $konf) {

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Konfirmasi password tidak cocok!'
        ];

        header('Location: tampilandaftar.php');
        exit;
    }

    // =========================
    // HASH PASSWORD
    // =========================
    $hashed = password_hash($pass, PASSWORD_DEFAULT);

    try {

        // =========================
        // PREPARED STATEMENT
        // =========================
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users
            (nama_user, username, email, password, foto)
            VALUES (?, ?, ?, ?, ?)"
        );

        $default_foto = 'Default.jpg';

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $nama,
            $user,
            $email,
            $hashed,
            $default_foto
        );

        mysqli_stmt_execute($stmt);

        $_SESSION['toast'] = [
            'icon' => 'success',
            'title' => 'Daftar berhasil 🔥'
        ];

        header('Location: tampilanlogin.php');
        exit;

    } catch (mysqli_sql_exception $e) {

        // DUPLICATE
        if ($e->getCode() == 1062) {

            $_SESSION['toast'] = [
                'icon' => 'warning',
                'title' => 'Nama / Username / Email sudah dipakai!'
            ];

        } else {

            error_log($e->getMessage());

            $_SESSION['toast'] = [
                'icon' => 'error',
                'title' => 'Terjadi kesalahan sistem!'
            ];
        }

        header('Location: tampilandaftar.php');
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
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
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

            <form action="tampilandaftar.php" method="POST" onsubmit="return validate()">
                <div class="field">
                    <label>Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" required>
                    <div id="hintNama" class="hint">Nama tidak boleh kosong</div>
                </div>
                <div class="field">
                    <label>Username</label>
                    <input type="text" id="username" name="username" placeholder="Minimal 4 karakter" required>
                    <div id="hintUsername" class="hint">Minimal 4 karakter</div>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" id="email" name="email" placeholder="contoh@email.com" required>
                    <div id="hintEmail" class="hint">Format email tidak valid</div>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" required oninput="checkStrength(this.value)">
                        <div class="toggle-pw" onclick="togglePw('password', this)">
                            <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        </div>
                    </div>
                    <div id="hintPw" class="hint">Minimal 8 karakter</div>
                    <div class="strength-bar">
                        <div class="strength-seg" id="s1"></div>
                        <div class="strength-seg" id="s2"></div>
                        <div class="strength-seg" id="s3"></div>
                        <div class="strength-seg" id="s4"></div>
                    </div>
                </div>

                <div class="field">
                    <label>Konfirmasi Password</label>
                    <div class="input-wrap">
                        <input type="password" id="konfirmasi" name="konfirmasi" placeholder="Ulangi password" required>
                        <div class="toggle-pw" onclick="togglePw('konfirmasi', this)">
                            <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        </div>
                    </div>
                    <div id="hintKonfirmasi" class="hint">Password tidak cocok</div>
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
            <script src="tampilandaftar.js"></script>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

    .swal2-popup.swal2-toast{
        background:rgba(15,23,42,.78)!important;
        backdrop-filter:blur(20px)!important;
        -webkit-backdrop-filter:blur(20px)!important;

        border:1px solid rgba(255,255,255,.08)!important;

        border-radius:20px!important;

        color:white!important;

        box-shadow:
        0 8px 30px rgba(0,0,0,.35)!important;
    }

    .swal2-title{
        color:white!important;
    }

    .swal2-timer-progress-bar{
        background:#3b82f6!important;
    }

    </style>

    <?php if(isset($_SESSION['toast'])): ?>

    <script>

    document.addEventListener('DOMContentLoaded', function(){

        Swal.fire({
            toast:true,
            position:'top-end',
            icon:'<?= $_SESSION['toast']['icon']; ?>',
            title:'<?= $_SESSION['toast']['title']; ?>',
            showConfirmButton:false,
            timer:2500,
            timerProgressBar:true
        });

    });

    </script>

    <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>
</body>
</html>