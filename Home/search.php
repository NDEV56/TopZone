<?php
session_start();
include 'koneksi.php';

$keyword = trim($_GET['q'] ?? '');

if (empty($keyword)) {
    tz_log('uncommon', 'SEARCH_EMPTY_QUERY', "Pencarian dengan keyword kosong", []);
    header("Location: index.php");
    exit;
}

$kw = mysqli_real_escape_string($conn, $keyword);
$result = mysqli_query($conn, "SELECT * FROM games WHERE nama_game LIKE '%$kw%' OR kategori LIKE '%$kw%'");
$jumlah = mysqli_num_rows($result);

if ($jumlah === 0) {
    tz_log('uncommon', 'SEARCH_NO_RESULT', "Pencarian '{$keyword}' tidak menemukan hasil", [
        'keyword' => $keyword,
        'results' => 0,
    ]);
} else {
    tz_log('common', 'SEARCH', "Pencarian '{$keyword}' menemukan {$jumlah} hasil", [
        'keyword' => $keyword,
        'results' => $jumlah,
    ]);
}
// ... sisa tampilan HTML tetap di file asli (tidak diubah)
?>
