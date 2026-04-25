<?php 
include 'koneksi.php';

$search = $_GET['search'] ?? '';
$search = mysqli_real_escape_string($conn, $search);

if ($search) {
    $query = "SELECT * FROM games WHERE nama_game LIKE '%$search%'";
} else {
    $query = "SELECT * FROM games";
}

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>TOPZONE</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- HEADER -->
<header class="tp-header">
    <div class="container tp-nav">
        <div class="tp-logo">TOPZONE</div>

        <div class="tp-center">
            <div class="search-box">
                <span class="search-icon"></span>
                <input type="text" id="searchInput" onkeyup="searchRealtime()" placeholder="Cari game di TOPZONE...">
            </div>
        </div>

        <div class="tp-user">🛒</div>
    </div>
</header>

<!-- 🔥 LAYOUT (FULL WIDTH, BUKAN CONTAINER) -->
<div class="tp-layout">

    <!-- SIDEBAR KIRI -->
    <aside class="tp-sidebar">
        <h3>Kategori</h3>
        <ul>
            <li onclick="filterKategori('')">🎮 Semua</li>
            <li onclick="filterKategori('MOBA')">⚔️ MOBA</li>
            <li onclick="filterKategori('FPS')">🔫 FPS</li>
            <li onclick="filterKategori('Open World')">🌍 Open World</li>
        </ul>
    </aside>

    <!-- KANAN (ISI) -->
    <div class="tp-main">
        <div class="container"> <!-- container dipindah ke dalam -->
            
            <h2 class="tp-title">🔥 Semua Produk</h2>

            <div id="productList" class="tp-grid">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($g = mysqli_fetch_assoc($result)): ?>

                <a href="game_detail.php?game=<?php echo $g['slug']; ?>" class="tp-card">
                    
                    <div class="tp-img" style="background-image:url('<?php echo $g['gambar']; ?>')"></div>

                    <div class="tp-info">
                        <h4><?php echo $g['nama_game']; ?></h4>

                        <div class="tp-meta">
                            ⭐ <?php echo number_format($g['rating'],1); ?> | <?php echo $g['terjual']; ?> terjual
                        </div>

                        <div class="tp-price">
                            Rp <?php echo number_format($g['harga']); ?>
                        </div>
                    </div>

                </a>

                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:red;">game lau gada mpruyy!</p>
            <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<script src="javascript.js"></script>
<script>
    loadData();
</script>

<!-- FOOTER -->
<footer class="tp-footer">

    <div class="footer-inner">
        <div class="container footer-grid">

            <div class="footer-brand">
                <h2>TOPZONE</h2>
                <p>Top up game cepat, aman, dan terpercaya 24 jam.</p>
            </div>

            <div>
                <h4>Menu</h4>
                <ul>
                    <li>Home</li>
                    <li>Semua Game</li>
                    <li>Promo</li>
                </ul>
            </div>

            <div>
                <h4>Bantuan</h4>
                <ul>
                    <li>Kontak</li>
                    <li>FAQ</li>
                    <li>Kebijakan</li>
                </ul>
            </div>

            <div>
                <h4>Kontak</h4>
                <p>Email: support@topzone.com</p>
                <p>WA: 08xxxxxxxxxx</p>
            </div>

        </div>
    </div>

    <div class="footer-bottom">
        © 2026 TOPZONE • All Rights Reserved
    </div>

</footer>

</body>
</html>