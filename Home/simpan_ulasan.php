<?php
// simpan_ulasan.php
include 'koneksi.php'; // Pastikan file koneksi.php benar

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_game = $_POST['id_game'];
    $slug = $_POST['slug'];
    $rating = $_POST['rating'];
    $komentar = $_POST['komentar'];
    // Ambil dari input hidden form yang benar
    $nama_user = $_POST['user_name']; 

    // Gunakan Prepared Statement untuk keamanan
    $stmt = $koneksi->prepare("INSERT INTO reviews (id_game, nama_user, rating, komentar, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $id_game, $nama_user, $rating, $komentar);

    if ($stmt->execute()) {
        header("Location: game_detail.php?game=" . urlencode($slug));
        exit();
    } else {
        echo "Gagal menyimpan ulasan: " . $koneksi->error;
    }
}
?>