<?php
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_slug = mysqli_real_escape_string($conn, $_POST['game_slug']);
    $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $rating = (int)$_POST['rating'];
    $komentar = mysqli_real_escape_string($conn, $_POST['komentar']);

    $query = "INSERT INTO reviews (game_slug, user_name, rating, komentar) VALUES ('$game_slug', '$name', '$rating', '$komentar')";
    
    if (mysqli_query($conn, $query)) {
        header("Location: game_detail.php?game=" . $game_slug);
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>