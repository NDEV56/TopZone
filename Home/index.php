<?php
session_start();
include 'koneksi.php'; 

// 1. CEK STATUS USER
$is_real_user = isset($_SESSION['id_user']); 
$is_logged_in = isset($_SESSION['nama_user']); 
$id_user_skrg = $_SESSION['id_user'] ?? 0;

// 2. LOGIKA CALLBACK / UPDATE STATUS (Pending -> proses)
if (isset($_GET['status']) && $_GET['status'] == 'success' && $is_real_user) {
    $update_status = "UPDATE orders SET status = 'proses' 
                      WHERE id_user = '$id_user_skrg' AND status = 'pending'";
    mysqli_query($koneksi, $update_status);
    header("Location: index.php");
    exit();
}

// 3. QUERY PRODUK UTAMA
$query = "SELECT * FROM games"; 
$result = mysqli_query($koneksi, $query);

// 4. AMBIL DATA ORDER (DENGAN LOGIKA NAMA GAME ASLI)
$jumlah_keranjang = 0;
$count_pending = $count_proses = $count_dikirim = $count_selesai = 0;
$q_pending = $q_proses = $q_dikirim = $q_selesai = null;

if ($is_real_user) {
    // Hitung Keranjang
    $res_keranjang = mysqli_query($koneksi, "SELECT SUM(qty) as total FROM keranjang WHERE id_user = '$id_user_skrg'");
    $data_keranjang = mysqli_fetch_assoc($res_keranjang);
    $jumlah_keranjang = $data_keranjang['total'] ?? 0;

    // Fungsi Sakti: Nyocokkin teks di orders dengan nama game di tabel games
    function getOrders($koneksi, $id_user, $status) {
        $sql = "SELECT o.*, 
                COALESCE(
                    -- Cara 1: Cek apakah nama game ada di dalam teks paket
                    (SELECT g.nama_game FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    -- Cara 2: Cek berdasarkan harga (SANGAT PENTING buat yang namanya 'Paket Utama')
                    (SELECT g.nama_game FROM games g WHERE g.harga = o.total_price LIMIT 1),
                    -- Cara 3: Cadangan kalau tetep gak ketemu
                    'TopZone Product'
                ) as nama_game_asli,
                COALESCE(
                    (SELECT g.gambar FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    (SELECT g.gambar FROM games g WHERE g.harga = o.total_price LIMIT 1),
                    'Default.jpg'
                ) as gambar_game_asli
                FROM orders o 
                WHERE o.id_user = '$id_user' AND o.status = '$status' 
                ORDER BY o.id_order DESC";
        return mysqli_query($koneksi, $sql);
    }
    
    // Eksekusi Query per Status
    $q_pending = getOrders($koneksi, $id_user_skrg, 'pending');
    $count_pending = mysqli_num_rows($q_pending);

    $q_proses = getOrders($koneksi, $id_user_skrg, 'proses');
    $count_proses = mysqli_num_rows($q_proses);

    $q_dikirim = getOrders($koneksi, $id_user_skrg, 'dikirim');
    $count_dikirim = mysqli_num_rows($q_dikirim);

    $q_selesai = getOrders($koneksi, $id_user_skrg, 'selesai');
    $count_selesai = mysqli_num_rows($q_selesai);
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
    <!-- Taruh ini di paling atas file atau di dalam <head> -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <div class="tp-left">
            <table>
                <tr>
                    <td><img src="logotopzone.png" alt="Topzone Logo" width="60" height="65"></td>
                    <td><h2 style="color:#0d2480; font-size:28px;">TOPZONE</h2></td>
                 </tr>
            </table>
        </div>
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

    <?php else: ?>
        <!-- FORM UPDATE PROFIL -->
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

            <!-- INPUT EDIT PROFILE (HIDDEN BY DEFAULT) -->
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

            <!-- TOMBOL ATUR / SIMPAN -->
            <div style="padding:0 10px;">
                <button type="button" onclick="enableEditMode()" class="view-mode" style="width:100%; padding:12px; background:#007bff; color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer;">⚙️ Atur Profil</button>
                <div class="edit-mode" style="display:none;">
                    <button type="submit" name="btn_simpan" style="width:100%; padding:12px; background:#28a745; color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; margin-bottom:10px;">💾 Simpan Perubahan</button>
                    <button type="button" onclick="disableEditMode()" style="width:100%; padding:10px; background:none; color:#666; border:1px solid #ccc; border-radius:12px; cursor:pointer; font-size:13px;">⬅️ Kembali</button>
                </div>
            </div>
        </form>

        <!-- SEKSI STATUS PESANAN (Tampil di View Mode) -->
        <!-- SEKSI STATUS PESANAN -->
        <!-- HTML BAGIAN SIDEBAR STATUS PESANAN -->
        <!-- 2. BAGIAN TAMPILAN HTML -->
        <div class="view-mode" style="margin-top: 30px; padding: 0 10px;">
            <h4 style="margin: 0 0 15px 5px; font-size: 13px; color: #555; text-transform: uppercase; letter-spacing: 1px;">Status Pesanan 🛒</h4>
            
            <div style="display: grid; gap: 10px;">
                
            <div class="status-container" style="font-family: sans-serif; max-width: 400px;">

                <?php
                // Array buat ngerender box status secara otomatis
                $status_list = [
                    ['id' => 'pending', 'label' => 'Belum Bayar', 'icon' => '⏳', 'bg' => '#fffde7', 'border' => '#fff9c4', 'color' => '#fbc02d', 'q' => $q_pending, 'count' => $count_pending],
                    ['id' => 'proses', 'label' => 'Diproses', 'icon' => '📦', 'bg' => '#e3f2fd', 'border' => '#bbdefb', 'color' => '#1976d2', 'q' => $q_proses, 'count' => $count_proses],
                    ['id' => 'dikirim', 'label' => 'Sudah Dikirim', 'icon' => '🚀', 'bg' => '#f3e5f5', 'border' => '#e1bee7', 'color' => '#4a148c', 'q' => $q_dikirim, 'count' => $count_dikirim],
                    ['id' => 'selesai', 'label' => 'Selesai', 'icon' => '🏁', 'bg' => '#e8f5e9', 'border' => '#c8e6c9', 'color' => '#1b5e20', 'q' => $q_selesai, 'count' => $count_selesai]
                ];

                foreach ($status_list as $st): ?>
                    <div onclick="toggleDetail('det_<?= $st['id'] ?>')" style="cursor:pointer; background: <?= $st['bg'] ?>; padding: 12px; border-radius: 12px; border: 1px solid <?= $st['border'] ?>; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;"><?= $st['icon'] ?></span>
                                <span style="font-size: 13px; font-weight: 600; color: <?= $st['color'] ?>;"><?= $st['label'] ?></span>
                            </div>
                            <span style="background: <?= $st['color'] ?>; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?= $st['count'] ?></span>
                        </div>

                        <!-- Detail List Order -->
                        <div id="det_<?= $st['id'] ?>" style="display:none; padding-top: 10px; margin-top: 8px; border-top: 1px dashed <?= $st['border'] ?>;">
                            <?php if($st['count'] > 0): 
                                mysqli_data_seek($st['q'], 0); 
                                while($d = mysqli_fetch_assoc($st['q'])): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; background: white; padding: 8px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <!-- Gunakan 'gambar_game_asli' hasil JOIN dari Fungsi Sakti lo -->
                                    <img src="<?= !empty($d['gambar_game_asli']) ? $d['gambar_game_asli'] : 'Default.jpg' ?>" 
                                        onerror="this.src='./Default.jpg'" 
                                        style="width: 35px; height: 35px; border-radius: 6px; object-fit: cover;">

                                    <div style="flex: 1;">
                                        <!-- Pake nama_game_asli biar gak muncul 'TopZone Product' -->
                                        <div style="font-size: 11px; font-weight: bold; color: #333;">
                                            <?= $d['nama_game_asli'] ?>
                                        </div>
                                        
                                        <div style="font-size: 9px; color: #777;">
                                            Paket: <?= $d['paket'] ?>
                                        </div>
                                    </div>
                                    <?php if($st['id'] == 'dikirim'): ?>
                                        <button onclick="event.stopPropagation(); window.location.href='konfirmasi.php?id=<?= $d['id_order'] ?>'" 
                                                style="background: #9c27b0; color: white; border: none; padding: 5px 8px; border-radius: 5px; font-size: 9px; cursor: pointer;">
                                            TERIMA
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; else: ?>
                                <div style="font-size: 10px; color: #888; text-align: center;">Belum ada pesanan nih bre.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <hr class="view-mode" style="margin:25px 0 15px; border:0; border-top:1px solid #eee;">
                <a href="../Login/tampilanlogin.php" class="view-mode" style="color:#dc3545; text-decoration:none; font-weight:bold; display:block; text-align:center; font-size:14px; margin-bottom: 20px;">🚪 Logout</a>
            </div>
        </div>
        </div>
    </div>

        

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
                <ul><li>Home</li><li>Semua Game</li>
            <li class="promo-text">
                <span id="btnPromo" class="promo-btn">P</span>romo
            </li>
            </ul>
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
// Tambahkan ini di deretan fungsi JS lo
function toggleDetail(id) {
    var x = document.getElementById(id);
    if (x.style.display === "none") {
        x.style.display = "block";
    } else {
        x.style.display = "none";
    }
}
function confirmSelesai(idOrder) {
    if (confirm("Beneran pesanan ini udah masuk, bre?")) {
        fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_order=' + idOrder
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === "success") {
                alert("Status Berhasil Diupdate 🔥");
                location.reload(); // Ini kuncinya biar berubah otomatis di layar
            } else {
                alert("Gagal update: " + data);
            }
        });
    }
}
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

document.addEventListener('DOMContentLoaded', function() {
    const btnP = document.getElementById('btnPromo');
    
    if (btnP) {
        btnP.addEventListener('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '🔥 Promo Spesial mprruy! 🔥',
                    // Path folder sesuai gambar yang lo kasih
                    html: '<iframe src="../Home/806/index.html" style="width:100%; height:450px; border:none; border-radius:10px;"></iframe>',
                    
                    // BAGIAN TIMER DIHAPUS BIAR GAK NUTUP OTOMATIS
                    showConfirmButton: false, 
                    showCloseButton: true, // Tombol silang (X) tetep ada biar user bisa tutup manual
                    
                    width: '700px',
                    background: '#ff748d',
                    color: '#fff',
                    
                    // Biar user bisa tutup dengan klik di luar kotak (opsional)
                    allowOutsideClick: true 
                });
            } else {
                console.error("SweetAlert2 belum muat mprruy!");
                alert("Sabar mprruy, lagi loading library-nya!");
            }
        });
    }
});
</script>

<script src="javascript.js"></script>
</body>
</html>