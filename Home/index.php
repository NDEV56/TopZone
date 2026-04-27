<?php 
session_start();
include 'koneksi.php'; // Pastikan file koneksi.php ada di folder yang sama

// 1. DEFINISIKAN VARIABEL STATUS USER (Biar line 59 gak error)
$is_real_user = isset($_SESSION['id_user']); 
$is_logged_in = isset($_SESSION['nama_user']); // Ini buat fix error line 59

// 2. JALANKAN QUERY PRODUK (Biar line 99 gak error)
$query = "SELECT * FROM games"; // Ganti 'games' sesuai nama tabel lo
$result = mysqli_query($conn, $query);

// Cek kalau query gagal
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOPZONE - Pusat Game</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

    <style>
        /* CSS biar sidebar profile lu melayang dari kanan */
        .profile-panel { position: fixed; top: 0; right: -400px; width: 350px; height: 100%; background: white; z-index: 10000; box-shadow: -5px 0 20px rgba(0,0,0,0.2); transition: 0.4s; padding: 25px; box-sizing: border-box; overflow-y: auto; }
        .profile-panel.active { right: 0; }
        .panel-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; z-index: 9999; backdrop-filter: blur(2px); }
    </style>
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
                🛒 <span id="cartCount" style="position:absolute; top:-5px; right:-10px; background:red; color:white; border-radius:50%; padding:2px 6px; font-size:12px; font-weight:bold;">0</span>
                
                <div id="cartDropdown" style="display:none; position:absolute; top:40px; right:0; width:280px; background:white; color:black; padding:15px; border-radius:10px; box-shadow:0 10px 20px rgba(0,0,0,0.2); border:1px solid #ddd;">
                    <h4 style="margin:0 0 10px 0; color:#333; border-bottom:1px solid #eee; padding-bottom:5px;">Keranjang Lu 🔥</h4>
                    <div id="cartItemsList" style="max-height:250px; overflow-y:auto; color:#555; font-size:13px;">
                        </div>
                    <hr>
                    <button onclick="clearCart()" style="width:100%; background:#eee; border:none; padding:8px; cursor:pointer; border-radius:5px;">Kosongkan</button>
                    <button onclick="checkout()" style="width:100%; margin-top:5px; background:#007bff; color:white; border:none; padding:10px; cursor:pointer; border-radius:5px; font-weight:bold;">Checkout Sekarang</button>
                </div>
            </div>

            <div class="tp-user">
                <div onclick="toggleProfileSidebar()" style="cursor: pointer; display: flex; align-items: center;">
                    <?php if($is_logged_in): ?>
                        <img src="uploads/<?php echo (!empty($_SESSION['foto'])) ? $_SESSION['foto'] : 'Default.jpg'; ?>?t=<?php echo time(); ?>" 
                            style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 2px solid #ccc;">
                            👤
                        </div>
                    <?php endif; ?>
                </div>
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
<div id="profileSidebar" class="profile-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
        <h3 style="margin:0;">Profil Lu mprruy 🔥</h3>
        <span onclick="toggleProfileSidebar()" style="cursor:pointer; font-size:28px;">&times;</span>
    </div>

    <?php if (!$is_real_user): // JIKA STATUSNYA BUKAN USER ASLI (GUEST) ?>
        
        <div style="text-align: center; margin-top: 50px;">
            <div style="font-size: 60px; margin-bottom: 20px;">🔒</div>
            <h2 style="color: #333;">Fitur Terbatas</h2>
            <p style="color: #888; font-size: 14px;">Login dulu biar fitur kebuka semua mprruy!</p>
            <br>
            <a href="../Login/tampilanlogin.php" style="background:#007bff; color:white; padding:10px 20px; border-radius:20px; text-decoration:none;">⚡ LOGIN SEKARANG</a>
        </div>

    <?php else: // JIKA USER ASLI (LOGIN) ?>
        
    <form action="update_profile.php" method="POST">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="position: relative; display: inline-block;">
            <img src="uploads/<?php echo (!empty($_SESSION['foto'])) ? $_SESSION['foto'] : 'Default.jpg'; ?>?t=<?php echo time(); ?>" 
                id="prev_foto" 
                style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #007bff;">
                <label for="input_foto" style="position: absolute; bottom: 5px; right: 5px; background: #333; color: #fff; width: 25px; height: 25px; border-radius: 50%; cursor: pointer; text-align:center; line-height:25px; font-size:12px;">✎</label>
            </div>
            <input type="file" id="input_foto" style="display:none;" accept="image/*">
            <div id="crop_area" style="display:none; margin-top:10px;"></div>
            <button type="button" id="btn_crop" style="display:none; background:#28a745; color:white; border:none; padding:5px 10px; border-radius:15px; margin-top:5px; cursor:pointer;">Pas-in Foto!</button>
            <input type="hidden" name="foto_base64" id="foto_base64">
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:bold;">Nama Lengkap</label>
            <input type="text" name="nama_user" value="<?php echo $_SESSION['nama_user'] ?? ''; ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;" required>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:bold;">Username</label>
            <input type="text" name="username" value="<?php echo $_SESSION['username'] ?? ''; ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;" required>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px; font-weight:bold;">Email</label>
            <input type="email" name="email" value="<?php echo $_SESSION['email'] ?? ''; ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;" required>
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-size:12px; font-weight:bold;">Sandi Baru (Kosongkan jika tidak ganti)</label>
            <input type="password" name="password" placeholder="******" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;">
        </div>

        <button type="submit" name="btn_simpan" style="width:100%; padding:12px; background:#007bff; color:white; border:none; border-radius:50px; font-weight:bold; cursor:pointer;">
            💾 SIMPAN SEMUA
        </button>
    </form>
    
    <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
    <a href="../Login/tampilanlogin.php" style="color:red; text-decoration:none; font-weight:bold; display:block; text-align:center;">🚪 Logout dari TopZone</a>

    <?php endif; ?>
</div>

<div id="panelOverlay" class="panel-overlay" onclick="toggleProfileSidebar()"></div>
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
<script>
    // Jembatan: variabel PHP dioper ke variabel global JS
    const IS_REAL_USER = <?php echo $is_real_user ? 'true' : 'false'; ?>;
</script>

<script src="javascript.js"></script>
<script>
let croppie_instance;

function toggleProfileSidebar() {
    const sidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("panelOverlay");
    sidebar.classList.toggle("active");
    overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";

    // Reset croppie kalau sidebar ditutup tanpa simpan
    if (!sidebar.classList.contains("active") && croppie_instance) {
        croppie_instance.destroy();
        croppie_instance = null;
        document.getElementById('crop_area').style.display = 'none';
        document.getElementById('btn_crop').style.display = 'none';
        document.getElementById('prev_foto').style.display = 'inline-block';
    }
}

document.getElementById('input_foto').addEventListener('change', function() {
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('crop_area').style.display = 'block';
        document.getElementById('btn_crop').style.display = 'inline-block';
        document.getElementById('prev_foto').style.display = 'none';

        if (croppie_instance) croppie_instance.destroy();

        croppie_instance = new Croppie(document.getElementById('crop_area'), {
            viewport: { width: 150, height: 150, type: 'circle' },
            boundary: { width: 250, height: 250 },
            showZoomer: true
        });

        croppie_instance.bind({ url: e.target.result });
    }
    reader.readAsDataURL(this.files[0]);
});

document.getElementById('btn_crop').addEventListener('click', function() {
    croppie_instance.result({
        type: 'base64',
        size: 'viewport',
        circle: true
    }).then(function(hasil) {
        document.getElementById('prev_foto').src = hasil;
        document.getElementById('prev_foto').style.display = 'inline-block';
        document.getElementById('crop_area').style.display = 'none';
        document.getElementById('btn_crop').style.display = 'none';
        document.getElementById('foto_base64').value = hasil;
    });
});
</script>
</body>
</html>