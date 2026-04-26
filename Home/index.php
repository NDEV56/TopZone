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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css" />
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
<div id="profileSidebar" class="profile-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
        <h3 style="margin:0;">Profil Lu mprruy 🔥</h3>
        <span onclick="toggleProfileSidebar()" style="cursor:pointer; font-size:28px;">&times;</span>
    </div>
    
    <form action="update_profile.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="foto_base64" id="foto_base64">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="position: relative; display: inline-block;">
                <img src="uploads/<?php echo $_SESSION['foto'] ?? 'Default.jpeg'; ?>" id="prev_foto" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #007bff;">
                <label for="input_foto" style="position: absolute; bottom: 5px; right: 5px; background: #000; color: #fff; width: 25px; height: 25px; border-radius: 50%; cursor: pointer; text-align: center; line-height: 25px;">✎</label>
            </div>
            <input type="file" id="input_foto" style="display:none;" accept="image/*">
            <div id="crop_area" style="display:none; margin-top:15px;"></div>
            <button type="button" id="btn_crop" style="display:none; background:#28a745; color:white; border:none; padding:8px 15px; border-radius:20px; margin-top:10px; cursor:pointer;">Pas-in Foto!</button>
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-size:12px; font-weight:bold;">Nama Lengkap</label>
            <input type="text" name="nama_user" value="<?php echo $_SESSION['nama_user'] ?? ''; ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;">
        </div>
        <button type="submit" style="width:100%; padding:12px; background:#007bff; color:white; border:none; border-radius:50px; font-weight:bold; cursor:pointer;">SIMPAN</button>
    </form>
    <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
    <a href="logout.php" style="color:red; text-decoration:none; font-weight:bold; display:block; text-align:center;">Logout</a>
</div>
<div id="panelOverlay" class="panel-overlay" onclick="toggleProfileSidebar()"></div>

<script src="javascript.js"></script>
<script>
// --- LOGIC PROFILE SIDEBAR & CROPPIE ---
let croppie_instance;

// 1. Fungsi Buka/Tutup Sidebar
function toggleProfileSidebar() {
    const sidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("panelOverlay");
    
    sidebar.classList.toggle("active");
    overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";

    // Bersihin croppie kalau sidebar ditutup biar gak berat
    if (!sidebar.classList.contains("active") && croppie_instance) {
        croppie_instance.destroy();
        document.getElementById('crop_area').style.display = 'none';
        document.getElementById('btn_crop').style.display = 'none';
        document.getElementById('prev_foto').style.display = 'inline-block';
    }
}

// 2. Logic Pas Pilih File Foto
document.getElementById('input_foto').addEventListener('change', function() {
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('crop_area').style.display = 'block';
        document.getElementById('btn_crop').style.display = 'inline-block';
        document.getElementById('prev_foto').style.display = 'none';

        if (croppie_instance) croppie_instance.destroy();

        // Inisialisasi alat potong (lingkaran)
        croppie_instance = new Croppie(document.getElementById('crop_area'), {
            viewport: { width: 150, height: 150, type: 'circle' },
            boundary: { width: 250, height: 250 },
            showZoomer: true
        });

        croppie_instance.bind({ url: e.target.result });
    }
    reader.readAsDataURL(this.files[0]);
});

// 3. Pas Klik Tombol "Pas-in Foto"
document.getElementById('btn_crop').addEventListener('click', function() {
    croppie_instance.result({
        type: 'base64',
        size: 'viewport',
        circle: true
    }).then(function(hasil) {
        // Tampilkan hasil potong ke preview
        document.getElementById('prev_foto').src = hasil;
        document.getElementById('prev_foto').style.display = 'inline-block';
        
        // Sembunyiin area potong
        document.getElementById('crop_area').style.display = 'none';
        document.getElementById('btn_crop').style.display = 'none';
        
        // Simpen data base64 ke input hidden buat dikirim ke update_profile.php
        document.getElementById('foto_base64').value = hasil;
    });
});
</script>
</body>
</html>