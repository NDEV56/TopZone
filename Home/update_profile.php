<?php
session_start();
include 'koneksi.php';

if (isset($_POST['btn_simpan'])) {

    if (!isset($_SESSION['id_user'])) {

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Silahkan login dulu!'
        ];

        header('Location: ../Login/tampilanlogin.php');
        exit;
    }

    $id_user = $_SESSION['id_user'];

    // =========================
    // INPUT
    // =========================
    $nama_baru  = trim($_POST['nama_user']);
    $user_baru  = trim($_POST['username']);
    $email_baru = trim($_POST['email']);
    $pass_baru  = $_POST['password'];

    // =========================
    // ANTI XSS
    // =========================
    $nama_baru  = htmlspecialchars($nama_baru, ENT_QUOTES, 'UTF-8');
    $user_baru  = htmlspecialchars($user_baru, ENT_QUOTES, 'UTF-8');
    $email_baru = htmlspecialchars($email_baru, ENT_QUOTES, 'UTF-8');

    // =========================
    // VALIDASI KOSONG
    // =========================
    if (
        empty($nama_baru) ||
        empty($user_baru) ||
        empty($email_baru)
    ) {

        $_SESSION['toast'] = [
            'icon' => 'error',
            'title' => 'Data wajib diisi!'
        ];

        header('Location: index.php');
        exit;
    }

    // =========================
    // PASSWORD MINIMAL
    // =========================
    if (!empty($pass_baru) && strlen($pass_baru) < 8) {

        $_SESSION['toast'] = [
            'icon' => 'warning',
            'title' => 'Password minimal 8 karakter!'
        ];

        header('Location: index.php');
        exit;
    }

    // =========================
    // FOTO DEFAULT
    // =========================
    $nama_file = $_SESSION['foto'] ?? 'Default.jpg';

    if (!empty($foto_data)) {

        // Hapus foto lama kecuali default
        if (
            !empty($nama_file) &&
            $nama_file !== 'Default.jpg' &&
            file_exists('uploads/' . $nama_file)
        ) {
            unlink('uploads/' . $nama_file);
        }

        // Validasi format base64
        if (strpos($foto_data, ';base64,') === false) {
            die("Format foto tidak valid!");
        }

        list($type, $foto_data) = explode(';', $foto_data);
        list(, $foto_data) = explode(',', $foto_data);

        $decoded = base64_decode($foto_data);

        if ($decoded === false) {
            die("Foto rusak!");
        }

        // Validasi gambar asli
        $image = imagecreatefromstring($decoded);

        if (!$image) {
            die("Format gambar tidak valid!");
        }

        // Nama random aman
        $nama_file = 'pp_' . bin2hex(random_bytes(8)) . '.jpg';

        // Ambil ukuran asli
        $width  = imagesx($image);
        $height = imagesy($image);

        $max = 500;

        // Resize otomatis
        if ($width > $max || $height > $max) {

            if ($width > $height) {
                $new_width = $max;
                $new_height = ($height / $width) * $max;
            } else {
                $new_height = $max;
                $new_width = ($width / $height) * $max;
            }

            $tmp = imagecreatetruecolor($new_width, $new_height);

            imagecopyresampled(
                $tmp,
                $image,
                0,
                0,
                0,
                0,
                $new_width,
                $new_height,
                $width,
                $height
            );

            imagedestroy($image);
            $image = $tmp;
        }

        // Compress JPG quality 75
        imagejpeg($image, 'uploads/' . $nama_file, 75);

        imagedestroy($image);

        $_SESSION['foto'] = $nama_file;
    }

    // =========================
    // QUERY
    // =========================
    if (!empty($pass_baru)) {

        $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET
            nama_user=?,
            username=?,
            email=?,
            foto=?,
            password=?
            WHERE id=?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssssi",
            $nama_baru,
            $user_baru,
            $email_baru,
            $nama_file,
            $hashed_pass,
            $id_user
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET
            nama_user=?,
            username=?,
            email=?,
            foto=?
            WHERE id=?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssi",
            $nama_baru,
            $user_baru,
            $email_baru,
            $nama_file,
            $id_user
        );
    }

    // =========================
    // EXECUTE
    // =========================
    try {

        mysqli_stmt_execute($stmt);

        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username']  = $user_baru;
        $_SESSION['email']     = $email_baru;

        $_SESSION['toast'] = [
            'icon' => 'success',
            'title' => 'Profil berhasil diupdate 🔥'
        ];

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
    }

    header('Location: index.php');
    exit;
}
?>