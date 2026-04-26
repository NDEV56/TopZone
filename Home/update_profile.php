<?php
session_start();
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_SESSION['id_user'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama_user']);
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Handle foto yang sudah dipotong (Base64)
    if (!empty($_POST['foto_base64'])) {
        $data = $_POST['foto_base64'];
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $nama_file = 'user_' . $id . '_' . time() . '.png';
        file_put_contents('uploads/' . $nama_file, $data);
        
        // Update database
        mysqli_query($conn, "UPDATE users SET foto='$nama_file' WHERE id='$id'");
        $_SESSION['foto'] = $nama_file;
    }

    $sql = "UPDATE users SET nama_user='$nama', username='$user', email='$email' WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['nama_user'] = $nama;
        $_SESSION['email'] = $email;
        echo "<script>alert('Berhasil diupdate mprruy!'); window.location.href='index.php';</script>";
    }
    // Di bagian akhir update_profile.php
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Profil Terupdate mprruy! 🔥'); window.location.href='index.php';</script>";
    }
}

?>