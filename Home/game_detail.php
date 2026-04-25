<?php include 'koneksi.php'; $game = $_GET['game'] ?? 'game'; ?>
<!DOCTYPE html>
<html>
<head><link rel="stylesheet" href="style.css"><title>Detail <?php echo $game; ?></title></head>
<body>
    <div class="container detail-box">
        <center>
            <img src="Roblox.jpg" width="150" style="border-radius:20px;">
            <h1>Top Up <?php echo ucfirst($game); ?></h1>
            <p>Harga: Rp 15.000</p>
            <button class="btn-login" onclick="addToCart('<?php echo $game; ?>', 15000)">Beli Sekarang</button>
        </center>
    </div>
    <script src="javascript.js"></script>
</body>
</html>