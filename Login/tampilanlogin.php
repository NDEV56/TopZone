<?php
session_start();
include '../Home/koneksi.php';

// Saya pertahankan blok PHP update ini dari kodemu.
// Tapi form di bawah menggunakan action="login_proses.php"
if (isset($_POST['btn_simpan'])) {
    $id_user = $_SESSION['id_user']; 
    if (empty($id_user)) {
        die("Waduh mprruy, ID lo gak kebaca. Coba login ulang!");
    }

    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['username']);
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $pass_baru = $_POST['password'];
    $foto_data = $_POST['foto_base64'];

    $nama_file = $_SESSION['foto'] ?? 'Home/Default.jpg';

    if (!empty($foto_data)) {
        if ($nama_file != 'Default.jpg' && file_exists('uploads/' . $nama_file)) {
            unlink('uploads/' . $nama_file);
        }

        list($type, $foto_data) = explode(';', $foto_data);
        list(, $foto_data)      = explode(',', $foto_data);
        $data = base64_decode($foto_data);
        
        $nama_file = 'pp_' . $id_user . '_' . time() . '.png';
        file_put_contents('uploads/' . $nama_file, $data);
        $_SESSION['foto'] = $nama_file;
    }

    $sql_update = "UPDATE users SET nama_user = '$nama_baru', username = '$user_baru', email = '$email_baru', foto = '$nama_file'";
    if (!empty($pass_baru)) {
        $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
        $sql_update .= ", password = '$hashed_pass'";
    }

    $sql_update .= " WHERE id = '$id_user'"; 
    
    if (mysqli_query($conn, $sql_update)) {
        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username'] = $user_baru;
        $_SESSION['email'] = $email_baru;
        $_SESSION['foto'] = $nama_file; 
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

            <form action="login_proses.php" method="POST">
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Masukkan username" required>
                </div>
                <div class="field">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" id="login_pw" name="password" placeholder="Masukkan password" required>
                        <div class="toggle-pw" onclick="togglePw('login_pw', this)">
                            <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        </div>
                    </div>
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
                // Script efek senter mouse
                let cards = document.querySelectorAll('.card');
                cards.forEach(card => {
                    card.onmousemove = function(e) {
                       let x = e.pageX - card.offsetLeft;
                       let y = e.pageY - card.offsetTop;
                       card.style.setProperty('--x', x + 'px');
                       card.style.setProperty('--y', y + 'px');
                    };
                })

                // Fungsi Mata untuk halaman login (Biar nggak usah panggil js eksternal jika tak mau)
                function togglePw(id, el) {
                    const input = document.getElementById(id);
                    const isText = input.type === 'text';
                    input.type = isText ? 'password' : 'text';
                    el.style.opacity = isText ? '0.4' : '0.9';
                }
            </script>
        </div> 
    </div>
</body>
</html>