<?php
session_start();
include 'koneksi.php';

if (isset($_POST['btn_simpan'])) {
    $id_user    = $_SESSION['id_user'];
    $nama_baru  = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user_baru  = mysqli_real_escape_string($conn, $_POST['username']);
    $email_baru = mysqli_real_escape_string($conn, $_POST['email']);
    $pass_baru  = $_POST['password'];
    $nama_file  = $_SESSION['foto'] ?? 'Default.jpg';
    $ubah_foto  = false;

    if (!empty($_POST['foto_base64'])) {
        $foto_data = $_POST['foto_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $foto_data, $type)) {
            $foto_data = substr($foto_data, strpos($foto_data, ',') + 1);
            $foto_data = str_replace(' ', '+', $foto_data);
            $data      = base64_decode($foto_data);
            $nama_file = 'pp_' . $id_user . '_' . time() . '.png';
            $path      = 'uploads/' . $nama_file;
            if (file_put_contents($path, $data)) {
                $_SESSION['foto'] = $nama_file;
                $ubah_foto = true;
            }
        }
    }

    $sql_update = "UPDATE users SET
                    nama_user = '$nama_baru',
                    username  = '$user_baru',
                    email     = '$email_baru',
                    foto      = '$nama_file'";

    $ubah_password = false;
    if (!empty($pass_baru)) {
        $hashed_pass   = password_hash($pass_baru, PASSWORD_DEFAULT);
        $sql_update   .= ", password = '$hashed_pass'";
        $ubah_password = true;
    }
    $sql_update .= " WHERE id = '$id_user'";

    if (mysqli_query($conn, $sql_update)) {
        $_SESSION['nama_user'] = $nama_baru;
        $_SESSION['username']  = $user_baru;
        $_SESSION['email']     = $email_baru;

        tz_log('critical', 'PROFILE_UPDATED', "User '{$user_baru}' memperbarui profil", [
            'id_user'       => $id_user,
            'nama_baru'     => $nama_baru,
            'username_baru' => $user_baru,
            'email_baru'    => $email_baru,
            'ubah_foto'     => $ubah_foto,
            'ubah_password' => $ubah_password,
        ]);
        echo "<script>alert('Profil Berhasil Diupdate mprruy! 🔥'); window.location='index.php';</script>";
    } else {
        tz_log('error', 'PROFILE_UPDATE_DB_ERROR', "Gagal update profil user '{$user_baru}'", [
            'id_user'  => $id_user,
            'db_error' => mysqli_error($conn),
        ]);
        die("Waduh Error DB mprruy: " . mysqli_error($conn));
    }
}
?>
