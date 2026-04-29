<?php
session_start();
include 'koneksi.php'; 

// 1. CEK STATUS USER
$is_real_user = isset($_SESSION['id_user']); 
$is_logged_in = isset($_SESSION['nama_user']); 

// 2. QUERY PRODUK
$query = "SELECT * FROM games"; 
$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// 3. HITUNG JUMLAH KERANJANG (REAL-TIME DARI DB)
$jumlah_keranjang = 0;
if ($is_real_user) {
    $id_user_skrg = $_SESSION['id_user'];
    $sql_cek_keranjang = "SELECT SUM(qty) as total FROM keranjang WHERE id_user = '$id_user_skrg'";
    $res_keranjang = mysqli_query($conn, $sql_cek_keranjang);
    if ($res_keranjang) {
        $data_keranjang = mysqli_fetch_assoc($res_keranjang);
        $jumlah_keranjang = $data_keranjang['total'] ?? 0;
    }
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
        /* Sidebar & Overlay Logic */
        .profile-panel { position: fixed; top: 0; right: -400px; width: 350px; height: 100%; background: white; z-index: 10000; box-shadow: -5px 0 20px rgba(0,0,0,0.2); transition: 0.4s; padding: 25px; box-sizing: border-box; overflow-y: auto; }
        .profile-panel.active { right: 0; }
        .panel-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; z-index: 9999; backdrop-filter: blur(2px); }
        .tp-user img { cursor: pointer; border: 2px solid transparent; transition: 0.3s; }
        .tp-user img:hover { border-color: #007bff; }
    </style>
</head>
<body>

<div id="panelOverlay" class="panel-overlay" onclick="closeAllSidebars()"></div>

<header class="tp-header">
    <div class="container tp-nav">
        <div class="tp-left"><div class="tp-logo">TOPZONE</div></div>
        <div class="tp-center">
            <div class="search-box">
                <input type="text" id="searchInput" onkeyup="searchRealtime()" placeholder="Cari game di TOPZONE...">
            </div>
        </div>
        <div class="tp-right">
            <div class="cart-icon" onclick="toggleCartSidebar()" style="position: relative; cursor: pointer; font-size: 26px; margin-right: 15px;">
                <span>🛒</span>
                <?php if ($jumlah_keranjang > 0): ?>
                    <span id="cartCountBadge" style="position: absolute; top: -5px; right: -8px; background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 11px; font-weight: bold;">
                        <?php echo $jumlah_keranjang; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="tp-user">
                <div onclick="toggleProfileSidebar()">
                    <?php if($is_logged_in): ?>
                        <img id="nav_avatar" src="uploads/<?php echo (!empty($_SESSION['foto'])) ? $_SESSION['foto'] : 'Default.jpg'; ?>?t=<?php echo time(); ?>" 
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 2px solid #ccc;">👤</div>
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
                    <div class="tp-slide"><img src="https://lh7-rt.googleusercontent.com/docsz/AD_4nXdaeh5nCCpewFNqlqhUTijdqvjpYhh66tL1vJCxzu0M28-lDOt8T9MQAxKkCfmRXiz90gYhgaA4t2SG8XAk4ntEz5_xxSNriTW04S4qcL36RtuZ6hFdtH0kTV7f6XFAJDCbFZJi?key=13D7PG225SiyZYQv4S1nVg1G"></div>
                </div>
            </div>
        </div>

        <h2 class="tp-title" id="mainTitle">🔥 Semua Produk</h2>
        
        <div id="productList" class="tp-grid">
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row_game = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $id_ini = $row_game['id'];
                    $ambil_ulasan = mysqli_query($conn, "SELECT AVG(rating) as hasil_rata FROM reviews WHERE id_game = '$id_ini'");
                    $data_ulasan = mysqli_fetch_assoc($ambil_ulasan);
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
                <div style="background:#fff3f3; border:2px dashed #ff0000; padding:20px 40px; border-radius:10px; text-align:center;">
                    <h3 style="color:#ff0000; margin:0;">⚠️ GAME LAU GADA MPRUYY!</h3>
                    <p style="color:#666;">Coba kata kunci lain...</p>
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

    <?php else:?>
            
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="position: relative; display: inline-block;">
                        <img src="uploads/<?php echo (!empty($_SESSION['foto'])) ? $_SESSION['foto'] : 'Default.jpg'; ?>?t=<?php echo time(); ?>" 
                            id="prev_foto" 
                            style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:4px solid #007bff;">
                        
                        <label for="input_foto" class="edit-mode" style="display:none; position: absolute; bottom: 5px; right: 5px; background: #333; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; text-align:center; line-height:30px; z-index:10;">✎</label>
                    </div>

                    <div id="crop_wrapper" style="display:none; margin-top:15px;">
                        <div id="crop_area"></div>
                        <button type="button" id="btn_crop" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer; margin-top:10px; font-size:12px;">✅ PASIN FOTO</button>
                    </div>
                    
                    <input type="file" id="input_foto" style="display:none;" accept="image/*">
                    <input type="hidden" name="foto_base64" id="foto_base64">
                </div>

                <div class="view-mode" style="text-align:center; margin-bottom:25px;">
                    <h2 style="margin:0; color:#333; font-size:22px;">@<?php echo $_SESSION['username']; ?></h2>
                    <p style="margin:5px 0 0; color:#888; font-size:13px;">Member Loyal TopZone 🔥</p>
                </div>

                <div class="edit-mode" style="display:none; margin-bottom:20px; background:#f8f9fa; padding:15px; border-radius:15px; border:1px solid #eee;">
                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:bold; color:#666;">Nama Lengkap</label>
                        <input type="text" name="nama_user" value="<?php echo $_SESSION['nama_user']; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:bold; color:#666;">Username</label>
                        <input type="text" name="username" value="<?php echo $_SESSION['username']; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:bold; color:#666;">Email</label>
                        <input type="email" name="email" value="<?php echo $_SESSION['email'] ?? ''; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:5px;">
                        <label style="font-size:11px; font-weight:bold; color:#666;">Sandi Baru</label>
                        <input type="password" name="password" placeholder="Kosongkan jika tetap" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="padding:0 10px;">
                    <button type="button" onclick="enableEditMode()" class="view-mode" style="width:100%; padding:12px; background:#007bff; color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer;">⚙️ Atur Profil</button>
                    <div class="edit-mode" style="display:none;">
                        <button type="submit" name="btn_simpan" style="width:100%; padding:12px; background:#28a745; color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; margin-bottom:10px;">💾 Simpan Perubahan</button>
                        <button type="button" onclick="disableEditMode()" style="width:100%; padding:10px; background:none; color:#666; border:1px solid #ccc; border-radius:12px; cursor:pointer; font-size:13px;">⬅️ Kembali</button>
                    </div>
                </div>
            </form>

            <hr class="view-mode" style="margin:25px 0 15px; border:0; border-top:1px solid #eee;">
            <a href="../Login/tampilanlogin.php" class="view-mode" style="color:#dc3545; text-decoration:none; font-weight:bold; display:block; text-align:center; font-size:14px;">🚪 Logout</a>

        <?php endif; ?>
</div>

<div id="cartSidebar" class="profile-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px;">
        <h3>Keranjang Lu mprruy 🛒</h3>
        <span onclick="toggleCartSidebar()" style="cursor:pointer; font-size:28px;">&times;</span>
    </div>
    <div id="cartItemsList" style="margin-top:20px;">
        <p style="text-align:center;">Memuat...</p>
    </div>
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
<script>
let croppie_instance;

function toggleProfileSidebar() {
    closeSidebar("cartSidebar");
    const sidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("panelOverlay");
    sidebar.classList.toggle("active");
    overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
}

function toggleCartSidebar() {
    closeSidebar("profileSidebar");
    const sidebar = document.getElementById("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    sidebar.classList.toggle("active");
    overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
    if(sidebar.classList.contains("active")) {
        // Panggil fungsi muat keranjang lo di sini (jika ada di javascript.js)
    }
}

function closeSidebar(id) {
    document.getElementById(id).classList.remove("active");
}

function closeAllSidebars() {
    closeSidebar("profileSidebar");
    closeSidebar("cartSidebar");
    document.getElementById("panelOverlay").style.display = "none";
    resetCroppie();
    disableEditMode();
}

function enableEditMode() {
    document.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'block');
}

function disableEditMode() {
    document.querySelectorAll('.view-mode').forEach(el => el.style.display = 'block');
    document.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'none');
    resetCroppie();
}

function resetCroppie() {
    if (croppie_instance) { croppie_instance.destroy(); croppie_instance = null; }
    document.getElementById('crop_wrapper').style.display = 'none';
    document.getElementById('prev_foto').style.display = 'inline-block';
}

// Logic Input File & Croppie
document.getElementById('input_foto')?.addEventListener('change', function() {
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('prev_foto').style.display = 'none';
        document.getElementById('crop_wrapper').style.display = 'block';
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

document.getElementById('btn_crop')?.addEventListener('click', function() {
    croppie_instance.result({ type: 'base64', size: 'viewport', circle: true }).then(function(hasil) {
        document.getElementById('prev_foto').src = hasil;
        document.getElementById('nav_avatar').src = hasil; // Update navbar otomatis
        document.getElementById('foto_base64').value = hasil;
        document.getElementById('crop_wrapper').style.display = 'none';
        document.getElementById('prev_foto').style.display = 'inline-block';
    });
});

document.addEventListener('keydown', (e) => { if(e.key === "Escape") closeAllSidebars(); });
</script>

<script src="javascript.js"></script>
</body>
</html>