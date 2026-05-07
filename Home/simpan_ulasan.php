<?php
include 'koneksi.php';

$id_game   = $_POST['id_game'];
$nama_user = $_POST['nama_user'];
$rating    = (int)$_POST['rating'];
$komentar  = $_POST['komentar'];
$slug      = $_POST['slug'];

if ($rating < 1 || $rating > 5) {
    tz_log('warning', 'REVIEW_INVALID_RATING', "Rating tidak valid dari '{$nama_user}'", [
        'id_game' => $id_game,
        'rating'  => $rating,
    ]);
    header("Location: game_detail.php?game=$slug&review=invalid");
    exit;
}

$query = "INSERT INTO reviews (id_game, user_name, rating, komentar)
          VALUES ('$id_game', '$nama_user', '$rating', '$komentar')";

if (mysqli_query($conn, $query)) {
    tz_log('common', 'REVIEW_SUBMITTED', "Ulasan baru dari '{$nama_user}' — rating {$rating}/5", [
        'id_game'  => $id_game,
        'username' => $nama_user,
        'rating'   => $rating,
        'komentar' => substr($komentar, 0, 100),
    ]);
    header("Location: game_detail.php?game=$slug");
} else {
    tz_log('error', 'REVIEW_DB_ERROR', "Gagal simpan ulasan dari '{$nama_user}'", [
        'id_game'  => $id_game,
        'db_error' => mysqli_error($conn),
    ]);
    echo "Gagal: " . mysqli_error($conn);
}
?>
