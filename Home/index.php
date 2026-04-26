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

<header class="tp-header">
    <div class="container tp-nav">
        <div class="tp-left"><div class="tp-logo">TOPZONE</div></div>
        <div class="tp-center">
            <div class="search-box">
                <input type="text" id="searchInput" onkeyup="searchRealtime()" placeholder="Cari game di TOPZONE...">
            </div>
        </div>
        <div class="tp-right">
            <div class="tp-cart">🛒 <span id="cartCount">0</span></div>
            <div class="tp-user">👤 <span id="userName">Guest</span></div>
        </div>
    </div>
</header>

<div class="tp-layout">
    <aside class="tp-sidebar">
        <h3>Kategori</h3>
        <ul>
            <li onclick="filterKategori('', this)" class="active">🎮 Semua</li>
            <li onclick="filterKategori('MOBA', this)">⚔️ MOBA</li>
            <li onclick="filterKategori('FPS', this)">🔫 FPS</li>
            <li onclick="filterKategori('Open World', this)">🌍 Open World</li>
        </ul>
    </aside>

    <main class="tp-main">
        <div class="slider-wrap" id="sliderWrap">
            <div class="tp-slider">
                <div class="tp-slides" id="sliderTrack">
                    <div class="tp-slide"><img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?w=1200"></div>
                    <div class="tp-slide"><img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?w=1200"></div>
                    <div class="tp-slide"><img src="https://images.unsplash.com/photo-1605901309584-818e25960a8f?w=1200"></div>
                </div>
            </div>
        </div>

        <h2 class="tp-title">🔥 Semua Produk</h2>

        <div id="productList" class="tp-grid">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($g = mysqli_fetch_assoc($result)): ?>
                <a href="game_detail.php?game=<?php echo $g['slug']; ?>" class="tp-card">
                    <div class="tp-img" style="background-image:url('<?php echo $g['gambar']; ?>')"></div>
                    <div class="tp-info">
                        <h4><?php echo $g['nama_game']; ?></h4>
                        <div class="tp-meta">⭐ <?php echo number_format($g['rating'],1); ?> | <?php echo $g['terjual']; ?> terjual</div>
                        <div class="tp-price">Rp <?php echo number_format($g['harga']); ?></div>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php endif; ?>
            

            <div id="productList" class="tp-grid">
            </div>
            
            <<div id="notFound" style="display:none; text-align:center; color:#888; font-size:13px; padding:100px 0; font-style:italic;">
                  game lau gada mpruyy!
              </div>
        </div>
    </main>
</div>

<footer class="tp-footer">
    <div class="footer-inner">
        <div class="container footer-grid">
            <div class="footer-brand">
                <h2>TOPZONE</h2>
                <p>Top up game cepat, aman, dan terpercaya 24 jam.</p>
            </div>
            <div>
                <h4>Menu</h4>
                <ul><li>Home</li><li>Semua Game</li><li>Promo</li></ul>
            </div>
            <div>
                <h4>Bantuan</h4>
                <ul><li>Kontak</li><li>FAQ</li><li>Kebijakan</li></ul>
            </div>
            <div>
                <h4>Kontak</h4>
                <p>Email: support@topzone.com</p>
                <p>WA: 08xxxxxxxxxx</p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">© 2026 TOPZONE • All Rights Reserved</div>
</footer>

<script src="javascript.js"></script>
</body>
</html>