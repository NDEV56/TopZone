<?php 
include 'koneksi.php';

// Ambil data game
$query = "SELECT * FROM games";
$result = mysqli_query($conn, $query);

// COBA TEST INI: Kalau di layar muncul angka, berarti database aman.
// $test = mysqli_query($conn, "SELECT AVG(rating) as rata FROM reviews WHERE id_game = 1");
// $data_test = mysqli_fetch_assoc($test);
// echo "DEBUG RATING GAME ID 1: " . $data_test['rata']; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOPZONE - Pusat Game</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="toastSuccess" class="toast-success">
    ✅ Mantap mprruy! Berhasil masuk keranjang!
    </div>
<header class="tp-header">
    <div class="container tp-nav">
        <div class="tp-left"><div class="tp-logo">TOPZONE</div></div>
        <div class="tp-center">
            <div class="search-box">
                <input type="text" id="searchInput" onkeyup="searchRealtime()" placeholder="Cari game di TOPZONE...">
            </div>
        </div>
        <div class="tp-right">
        <div class="tp-cart" onclick="toggleCartModal()" style="cursor:pointer; position:relative; z-index: 9999;">
            🛒 <span id="cartCount">0</span>
            
            <div id="cartDropdown" style="display:none; position:absolute; top:40px; right:0; width:260px; background:white; color:black; padding:15px; border-radius:10px; box-shadow:0 10px 20px rgba(0,0,0,0.2); border:1px solid #ddd;">
                <h4 style="margin:0 0 10px 0; color:#333;">Keranjang Lu 🔥</h4>
                <div id="cartItemsList" style="max-height:200px; overflow-y:auto; color:#555; font-size:13px;">
                    </div>
                <button onclick="localStorage.removeItem('topzone_cart'); location.reload();" style="width:100%; margin-top:10px; background:#eee; border:none; padding:5px; cursor:pointer;">Kosongkan</button>
            </div>
        </div>
                    <div class="tp-user">
                <?php if(isset($_SESSION['nama_user'])): ?>
                    <span id="userName">👤 <?php echo $_SESSION['nama_user']; ?></span>
                    <a href="logout.php" style="font-size: 10px; color: red; text-decoration: none;">Logout</a>
                <?php else: ?>
                    <a href="login.html" class="btn-login-nav" style="text-decoration: none; color: #333;">👤 Login / Daftar</a>
                <?php endif; ?>
            </div>
        </div>
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

        <h2 class="tp-title" id="mainTitle">🔥 Semua Produk</h2>
        <div id="productList" class="tp-grid">
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row_game = mysqli_fetch_assoc($result)): ?>
                
                <?php 
                    // HITUNG MANUAL (PERSIS DETAIL GAME)
                    $id_ini = $row_game['id'];
                    $ambil_ulasan = mysqli_query($conn, "SELECT AVG(rating) as hasil_rata FROM reviews WHERE id_game = '$id_ini'");
                    $data_ulasan = mysqli_fetch_assoc($ambil_ulasan);
                    
                    // Variabel baru biar gak ketuker
                    $angka_bintang = ($data_ulasan['hasil_rata'] > 0) ? round($data_ulasan['hasil_rata'], 1) : 0;
                ?>

                <a href="game_detail.php?game=<?php echo $row_game['slug']; ?>" class="tp-card">
                    <div class="tp-img" style="background-image:url('<?php echo $row_game['gambar']; ?>')"></div>
                    <div class="tp-info">
                        <h4><?php echo $row_game['nama_game']; ?></h4>
                        <div class="tp-meta">
                            ⭐ <?php echo number_format($angka_bintang, 1); ?> | <?php echo $row_game['terjual']; ?> terjual
                        </div>
                    </div>
                </a>

            <?php endwhile; ?>
        <?php endif; ?>
        </div>
        
        <div id="notFound" style="display:none; width:100%; padding: 50px 0;">
            <div style="display: flex; justify-content: center; align-items: center; width: 100%;">
                <div style="background:#fff3f3; border:2px dashed #ff0000; padding:20px 40px; border-radius:10px; text-align:center; min-width: 300px;">
                    <h3 style="color:#ff0000; margin:0; font-size: 18px;">⚠️ GAME LAU GADA MPRUYY!</h3>
                    <p style="color:#666; margin-top:5px; font-size: 13px;">Coba kata kunci lain mprruy...</p>
                </div>
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
<script>
function toggleCartModal() {
    const dropdown = document.getElementById("cartDropdown");
    const list = document.getElementById("cartItemsList");
    
    // Cek apakah dropdown-nya ada
    if(!dropdown) {
        alert("ID cartDropdown gak ketemu paok!");
        return;
    }

    // Toggle manual
    if (dropdown.style.display === "none") {
        dropdown.style.display = "block";
        
        // Ambil data
        let keranjang = JSON.parse(localStorage.getItem("topzone_cart")) || [];
        console.log("Isi Keranjang:", keranjang); // Cek di F12 Console

        if (keranjang.length === 0) {
            list.innerHTML = "Belum ada belanjaan mprruy!";
        } else {
            list.innerHTML = keranjang.map(item => `
                <div style="border-bottom:1px solid #eee; padding:5px 0;">
                    <b>${item.produk}</b><br>
                    ID: ${item.id_game}<br>
                    <span style="color:red">Rp ${item.harga.toLocaleString('id-ID')}</span>
                </div>
            `).join('');
        }
    } else {
        dropdown.style.display = "none";
    }
}

// Update angka merah di icon keranjang
function updateCartCount() {
    let keranjang = JSON.parse(localStorage.getItem("topzone_cart")) || [];
    let countEl = document.getElementById("cartCount");
    if(countEl) countEl.innerText = keranjang.length;
}

// Jalankan otomatis pas page load
document.addEventListener("DOMContentLoaded", updateCartCount);
</script>
</body>
</html>