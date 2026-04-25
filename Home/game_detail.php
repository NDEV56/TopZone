<?php 
include 'koneksi.php';

$game = $_GET['game'] ?? '';

if(!$game){
    echo "Game tidak ditemukan!";
    exit;
}

$query = mysqli_query($conn, "SELECT * FROM games WHERE slug='$game'");
$data = mysqli_fetch_assoc($query);

if(!$data){
    echo "Game tidak ada!";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Top Up <?php echo $data['nama_game']; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="header">
    <div class="container">
        <a href="index.php" class="logo">TOPZONE</a>
    </div>
</header>

<div class="container" style="margin-top:30px;">
    <div class="detail-box">

        <div class="product-image" style="background-image:url('<?php echo $data['gambar']; ?>'); height:200px;"></div>

        <h1><?php echo $data['nama_game']; ?></h1>

        <p>⭐ <?php echo number_format($data['rating'],1); ?> • <?php echo $data['terjual']; ?> terjual</p>

        <h2>Rp <?php echo number_format($data['harga']); ?></h2>

        <button class="btn-login" onclick="addToCart('<?php echo $data['nama_game']; ?>', <?php echo $data['harga']; ?>)">
            Beli Sekarang
        </button>

    </div>
</div>

<script src="javascript.js"></script>
</body>
</html>