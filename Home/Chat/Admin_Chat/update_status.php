<?php
include '../../koneksi.php';

// Jika admin klik toggle (POST)
if(isset($_POST['status'])) {
    $status = $_POST['status'];
    mysqli_query($koneksi, "UPDATE users SET status_admin = '$status'");
    echo "Success";
    exit;
}

// Jika user ngecek status (GET)
$check = mysqli_query($koneksi, "SELECT status_admin FROM users LIMIT 1");
$row = mysqli_fetch_assoc($check);
echo $row['status_admin'] ?? 'offline';
?>