<?php
session_start();
include 'koneksi.php'; 

// 1. CEK STATUS USER
$is_real_user = isset($_SESSION['id_user']); 
$is_logged_in = isset($_SESSION['nama_user']); 
$id_user_skrg = $_SESSION['id_user'] ?? 0;

// 2. LOGIKA CALLBACK / UPDATE STATUS (Pending -> proses) DI HALAMAN HOME
if (isset($_GET['status']) && $_GET['status'] == 'success' && $is_real_user) {
    
    // Ambil external_id spesifik dari url redirect xendit bray
    $ext_id_skrg = mysqli_real_escape_string($koneksi, $_GET['ext_id'] ?? '');

    if (!empty($ext_id_skrg)) {
        
        // =========================================================================
        // CARA PAMUNGKAS ANTI-GAGAL:
        // Hapus semua item di keranjang milik user ini, yang ID GAME-nya terdaftar
        // di dalam invoice orders yang barusan dibayar!
        // =========================================================================
        $sql_bersih_keranjang = "DELETE FROM keranjang 
                                 WHERE id_user = '$id_user_skrg' 
                                 AND id_game IN (
                                     SELECT g.id FROM games g
                                     INNER JOIN orders o ON (o.game_name COLLATE utf8mb4_general_ci) = (g.nama_game COLLATE utf8mb4_general_ci)
                                     WHERE o.external_id = '$ext_id_skrg'
                                 )";
                                   
        mysqli_query($koneksi, $sql_bersih_keranjang);

        // Update status orders menjadi proses
        $update_status = "UPDATE orders SET status = 'proses' 
                          WHERE id_user = '$id_user_skrg' AND external_id = '$ext_id_skrg'";
        mysqli_query($koneksi, $update_status);
    } else {
        // Fallback cadangan
        $update_status = "UPDATE orders SET status = 'proses' WHERE id_user = '$id_user_skrg' AND status = 'pending'";
        mysqli_query($koneksi, $update_status);
    }
    
    // Reset URL biar rapi dan tetep stay di dalam folder /Home/
    header("Location: index.php");
    exit();
}
// 3. QUERY PRODUK UTAMA
$query = "SELECT * FROM games"; 
$result = mysqli_query($koneksi, $query);

// 4. FUNGSI SAKTI NYOCOKKIN TEKS (Tetap aman dengan paksaan Collation)
if (!function_exists('getOrders')) {
    function getOrders($koneksi, $id_user, $status) {
        $sql = "SELECT o.*, 
                COALESCE(
                    (SELECT g.nama_game FROM games g WHERE (o.game_name COLLATE utf8mb4_general_ci) LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    'TopZone Product'
                ) as nama_game_asli,
                COALESCE(
                    (SELECT g.gambar FROM games g WHERE (o.game_name COLLATE utf8mb4_general_ci) LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    'Default.jpg'
                ) as gambar_game_asli
                FROM orders o 
                WHERE o.id_user = '$id_user' AND o.status = '$status' 
                ORDER BY o.id_order DESC";
                
        return mysqli_query($koneksi, $sql);
    }
}

// 5. AMBIL DATA ORDER USER
$jumlah_keranjang = 0;
$count_pending = $count_proses = $count_dikirim = $count_selesai = 0;
$q_pending = $q_proses = $q_dikirim = $q_selesai = null;

if ($is_real_user) {
    $res_keranjang = mysqli_query($koneksi, "SELECT SUM(qty) as total FROM keranjang WHERE id_user = '$id_user_skrg'");
    $data_keranjang = mysqli_fetch_assoc($res_keranjang);
    $jumlah_keranjang = $data_keranjang['total'] ?? 0;
    
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="javascript.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Taruh ini di paling atas file atau di dalam <head> -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Sidebar & Overlay Logic */
        .profile-panel { position: fixed; top: 0; right: -400px; width: 350px; height: 100%;  background: rgba(2, 11, 99, 0.479);
                         backdrop-filter: blur(4px) saturate(100%);
                         -webkit-backdrop-filter: blur(4px) saturate(100%);
                         border: 1px solid rgba(255, 255, 255, 0.25);
                         border-top-color: rgba(255, 255, 255, 0.5);
                         box-shadow:
                         0 8px 32px rgba(0, 0, 0, 0.4),
                         inset 0 1px 0 rgba(255,255,255,0.3); z-index: 10000; box-shadow: -5px 0 20px rgba(0,0,0,0.2); transition: 0.4s; padding: 25px; box-sizing: border-box; overflow-y: auto; border-radius: 18px 0 0 18px;}
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
                    <td><h2 style="color:#fff; -webkit-text-stroke: 4px #1900f7; paint-order: stroke fill;font-size:28px;">TOPZONE</h2></td>
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
                             style="width:40px; height:40px; border-radius:50%; border: 2px solid #fff; object-fit:cover;">
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
            <li onclick="filterKategori('', this)" class="active">Semua</li>
            <li onclick="filterKategori('MOBA', this)">MOBA</li>
            <li onclick="filterKategori('FPS', this)">FPS</li>
            <li onclick="filterKategori('Open World', this)">Open World</li>
        </ul>
    </aside>

    <main class="tp-main">
        <div class="tz-hero-wrap" id="tzHeroWrap">
            <div class="tz-hero-container">
                <button class="tz-hero-arrow tz-prev" id="tzPrevBtn">❮</button>
                <button class="tz-hero-arrow tz-next" id="tzNextBtn">❯</button>

                <div class="tz-hero-track" id="tzHeroTrack">
                    <div class="tz-hero-slide"><img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?w=1200"></div>
                    <div class="tz-hero-slide"><img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?w=1200"></div>
                    <div class="tz-hero-slide"><img src="https://images.unsplash.com/photo-1605901309584-818e25960a8f?w=1200"></div>
                    <div class="tz-hero-slide"><img src="https://cms-media.roblox.com/resize=width:1280,fit:max/GnaqqRBTcCuoRELL938w"></div>
                    <div class="tz-hero-slide"><img src="https://assets.xboxservices.com/assets/2b/d2/2bd239ef-b3a5-4b50-b5a5-ba6418012534.jpg?n=Xbox-360-Games_Feature-0_Back-Compat_1040x585_01.jpg"></div>
                </div>
            </div>
            
            <div class="tz-hero-dots" id="tzHeroDots"></div>
        </div>

        <?php 
        if ($is_real_user): 
            // Ambil 4 teratas untuk beranda
            $query_beli_lagi = "
                SELECT o.game_name, o.total_price, o.catatan,
                COALESCE(
                    (SELECT g.id FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    (SELECT g.id FROM games g WHERE g.harga = o.total_price LIMIT 1),
                    0
                ) as id_game_asli,
                COALESCE(
                    (SELECT g.nama_game FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    'TopZone Product'
                ) as nama_game_asli,
                COALESCE(
                    (SELECT g.gambar FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                    (SELECT g.gambar FROM games g WHERE g.harga = o.total_price LIMIT 1),
                    'Default.jpg'
                ) as gambar_game_asli
                FROM orders o
                WHERE o.id_user = '$id_user_skrg' AND o.status = 'selesai'
                AND o.id_order IN (
                    SELECT MAX(id_order)
                    FROM orders
                    WHERE id_user = '$id_user_skrg' AND status = 'selesai'
                    GROUP BY game_name
                )
                ORDER BY o.id_order DESC
                LIMIT 4
            ";
            $result_beli_lagi = mysqli_query($koneksi, $query_beli_lagi);
        ?>
        <div class="tz-repeat-order-section" id="tzRepeatOrderSection">
            <div class="tz-repeat-header">
                <div class="tz-repeat-title-wrap">
                    <span class="tz-repeat-icon"></span>
                    <h2>Beli Lagi Yuk</h2>
                </div>
                <?php if ($result_beli_lagi && mysqli_num_rows($result_beli_lagi) > 0): ?>
                    <button type="button" class="tz-btn-lihat-semua" onclick="bukaModalHistory()">Lihat Semua ❯</button>
                <?php endif; ?>
            </div>

            <?php if ($result_beli_lagi && mysqli_num_rows($result_beli_lagi) > 0): ?>
                <div class="tz-repeat-grid">
                    <?php while($row_ulang = mysqli_fetch_assoc($result_beli_lagi)): ?>
                        <div class="tz-repeat-card">
                            <div class="tz-repeat-info-wrap">
                                <div class="tz-repeat-img-container">
                                    <img src="<?php echo $row_ulang['gambar_game_asli']; ?>" alt="<?php echo $row_ulang['nama_game_asli']; ?>" class="tz-repeat-img">
                                </div>
                                <div class="tz-repeat-details">
                                    <h4 class="tz-game-name"><?php echo $row_ulang['nama_game_asli']; ?></h4>
                                    <p class="tz-game-paket"><?php echo $row_ulang['game_name']; ?></p>
                                    <p class="tz-game-price">Rp <?php echo number_format($row_ulang['total_price'], 0, ',', '.'); ?></p>
                                </div>
                            </div>
                            
                            <div class="tz-repeat-action-wrap">
                                <a href="Checkout/pembayaran.php?id_game=<?php echo $row_ulang['id_game_asli']; ?>&paket=<?php echo urlencode($row_ulang['game_name']); ?>&harga=<?php echo $row_ulang['total_price']; ?>&user=<?php echo urlencode($row_ulang['catatan']); ?>" class="tz-btn-beli-lagi">
                                    Beli Lagi
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="tz-empty-repeat-container">
                    <p class="tz-empty-repeat-text">Kosong mpruyyy, beli dulu sono...</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="overlayHistoryBeli">
            <div class="modal-history-container">
                <span class="modal-history-close" onclick="tutupModalHistory()">&times;</span>
                <h3 class="modal-history-title">Seluruh Riwayat Pembelian Selesai</h3>
                
                <div class="modal-history-grid">
                    <?php
                    // Query meload seluruh history dengan status selesai bray
                    $query_all_history = "
                        SELECT o.game_name, o.total_price, o.catatan,
                        COALESCE(
                            (SELECT g.id FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                            (SELECT g.id FROM games g WHERE g.harga = o.total_price LIMIT 1),
                            0
                        ) as id_game_asli,
                        COALESCE(
                            (SELECT g.nama_game FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                            'TopZone Product'
                        ) as nama_game_asli,
                        COALESCE(
                            (SELECT g.gambar FROM games g WHERE o.game_name LIKE CONCAT('%', g.nama_game, '%') LIMIT 1),
                            (SELECT g.gambar FROM games g WHERE g.harga = o.total_price LIMIT 1),
                            'Default.jpg'
                        ) as gambar_game_asli
                        FROM orders o
                        WHERE o.id_user = '$id_user_skrg' AND o.status = 'selesai'
                        ORDER BY o.id_order DESC
                    ";
                    $result_all = mysqli_query($koneksi, $query_all_history);

                    if ($result_all && mysqli_num_rows($result_all) > 0):
                        while($all_row = mysqli_fetch_assoc($result_all)):
                    ?>
                        <div class="modal-history-card">
                            <div class="tz-repeat-info-wrap">
                                <div class="tz-repeat-img-container">
                                    <img src="<?php echo $all_row['gambar_game_asli']; ?>" class="tz-repeat-img">
                                </div>
                                <div class="tz-repeat-details">
                                    <h4 class="tz-game-name"><?php echo $all_row['nama_game_asli']; ?></h4>
                                    <p class="tz-game-paket"><?php echo $all_row['game_name']; ?></p>
                                    <p class="tz-game-price">Rp <?php echo number_format($all_row['total_price'], 0, ',', '.'); ?></p>
                                </div>
                            </div>
                            <div class="tz-repeat-action-wrap">
                                <a href="Checkout/pembayaran.php?id_game=<?php echo $all_row['id_game_asli']; ?>&paket=<?php echo urlencode($all_row['game_name']); ?>&harga=<?php echo $all_row['total_price']; ?>&user=<?php echo urlencode($all_row['catatan']); ?>" class="tz-btn-beli-lagi">
                                    Beli Lagi
                                </a>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="tz-empty-repeat-container" style="grid-column: span 2;">
                            <p class="tz-empty-repeat-text">Belum ada riwayat transaksi selesai bray.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php 
        endif; 
        ?>

        <div class="tz-main-products-box">
            <div class="tp-header-wrap">
                <span class="tp-header-icon"></span>
                <h2 class="tp-title" id="mainTitle">Semua Produk</h2>
            </div>
            
            <div id="productList" class="tp-grid-six">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row_game = mysqli_fetch_assoc($result)): ?>
                    <?php 
                        $id_ini = $row_game['id'];
                        $ambil_ulasan = mysqli_query($koneksi, "SELECT AVG(rating) as hasil_rata FROM reviews WHERE id_game = '$id_ini'");
                        $data_ulasan = mysqli_fetch_assoc($ambil_ulasan);
                        $angka_bintang = ($data_ulasan['hasil_rata'] > 0) ? round($data_ulasan['hasil_rata'], 1) : 0;
                    ?>

                    <a href="game_detail.php?game=<?php echo $row_game['slug']; ?>" class="tp-card-premium">
                        <div class="tp-img-container">
                            <div class="tp-img" style="background-image:url('<?php echo $row_game['gambar']; ?>')"></div>
                        </div>
                        <div class="tp-info">
                            <h4><?php echo $row_game['nama_game']; ?></h4>
                            <div class="tp-meta">
                                <span class="tp-star">⭐ <?php echo number_format($angka_bintang, 1); ?></span>
                                <span class="tp-divider">|</span>
                                <span class="tp-sold"><?php echo $row_game['terjual']; ?> terjual</span>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </div>
        
        <div id="notFound" class="tz-wrapper-notfound-baru" style="
            display: none; 
            grid-column: 1 / -1; 
            width: 100% !important; 
            justify-content: center !important; 
            align-items: center !important; 
            padding: 60px 0 !important; 
            box-sizing: border-box !important;
        ">
            <div class="tz-poros-notfound-baru" style="
                width: 100% !important; 
                max-width: 480px !important; 
                box-sizing: border-box !important; 
                padding: 0 20px !important;
            ">
                <div class="tz-card-notfound-baru" style="
                    background: rgba(255, 77, 77, 0.04) !important; 
                    backdrop-filter: blur(20px) saturate(180%) !important; 
                    -webkit-backdrop-filter: blur(20px) saturate(180%) !important; 
                    border: 2px solid rgba(255, 77, 77, 0.2) !important; 
                    border-top-color: rgba(255, 150, 150, 0.4) !important; 
                    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5), 
                                inset 0 0 25px rgba(255, 77, 77, 0.1) !important; 
                    padding: 40px 30px !important; 
                    border-radius: 20px !important; 
                    text-align: center !important; 
                    box-sizing: border-box !important;
                    animation: tzSmoothPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.1) forwards !important;
                ">
                    <div style="font-size: 45px; margin-bottom: 12px; filter: drop-shadow(0 0 10px rgba(255,77,77,0.6));">⚠️</div>
                    
                    <h3 style="
                        color: #ff4d4d; 
                        margin: 0 0 10px 0; 
                        font-size: 21px; 
                        font-weight: 800; 
                        letter-spacing: 0.5px;
                        text-shadow: 0 0 12px rgba(255, 77, 77, 0.5);
                        word-break: break-all;
                    ">
                        Waduh mprruy, game gak ketemu!
                    </h3>
                    
                    <p style="color: #cbd5e1; margin: 0; font-size: 14px; font-weight: 500;">
                        Coba ketik kata kunci game lain bray...
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
<div id="profileSidebar" class="profile-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ffe600; padding-bottom:15px; margin-bottom:20px;">
        <h3 style="margin:0; color:#fff;">Profil Lu mprruy</h3>
        <span onclick="toggleProfileSidebar()" style="cursor:pointer; font-size:28px; color:#fff;">&times;</span>
    </div>

    <?php if (!$is_real_user): // JIKA STATUSNYA BUKAN USER ASLI (GUEST) ?>
        
        <div style="text-align: center; margin-top: 50px;">
            <div style="font-size: 60px; margin-bottom: 20px;">🔒</div>
            <h2 style="color: #ffffff;">Fitur Terbatas</h2>
            <p style="color: #aca9a9; font-size: 14px;">Login dulu biar fitur kebuka semua mprruy!</p>
            <br>
            <a href="../Login/tampilanlogin.php" style=" background: rgba(255, 255, 255, 0.08);
               backdrop-filter: blur(24px) saturate(180%);
               -webkit-backdrop-filter: blur(24px) saturate(180%);
               border: 1px solid rgba(255, 255, 255, 0.25);
               border-top-color: rgba(255, 255, 255, 0.5);
               box-shadow:
               0 8px 32px rgba(0, 0, 0, 0.4),
               inset 0 1px 0 rgba(255,255,255,0.3); color:white; padding:10px 20px; border-radius:20px; text-decoration:none;">LOGIN SEKARANG</a>
        </div>

    <?php else: ?>
        <!-- FORM UPDATE PROFIL -->
        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            <div style="text-align:center; margin-bottom:20px; color:white;">
                <div style="position: relative; display: inline-block;">
                    <img src="uploads/<?php echo (!empty($_SESSION['foto'])) ? $_SESSION['foto'] : 'Default.jpg'; ?>?t=<?php echo time(); ?>" 
                        id="prev_foto" 
                        style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid #ffee00;">
                    
                    <label for="input_foto" class="edit-mode" style="display:none; position: absolute; bottom: 5px; right: 5px; background: #ffee00; color: #000000; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; text-align:center; line-height:30px; z-index:10;">✎</label>
                </div>

                <div id="crop_wrapper" style="display:none; margin-top:15px;">
                    <div id="crop_area"></div>
                    <button type="button" id="btn_crop" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer; margin-top:10px; font-size:12px;">PASIN FOTO</button>
                </div>
                
                <input type="file" id="input_foto" style="display:none;" accept="image/*">
                <input type="hidden" name="foto_base64" id="foto_base64">
            </div>

            <div class="view-mode" style="text-align:center; margin-bottom:25px;">
                <h2 style="margin:0; color:#ffffff; font-size:22px;">@<?php echo $_SESSION['username']; ?></h2>
                <p style="margin:5px 0 0; color:#fff; font-size:13px;">Member Loyal TopZone</p>
            </div>

            <!-- INPUT EDIT PROFILE (HIDDEN BY DEFAULT) -->
            <div class="edit-mode" style="display:none; margin-bottom:20px; background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-top-color: rgba(255, 255, 255, 0.5);
                box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.3); padding:15px; border-radius:15px;">
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:bold; color:#fff;">Nama Lengkap</label>
                    <input type="text" name="nama_user" value="<?php echo $_SESSION['nama_user']; ?>" style="width:100%; color:#fff; padding:10px;  background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-top-color: rgba(255, 255, 255, 0.5);
                box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.3); border-radius:10px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:bold; color:#fff;">Username</label>
                    <input type="text" name="username" value="<?php echo $_SESSION['username']; ?>" style="width:100%; color:#fff; padding:10px;  background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-top-color: rgba(255, 255, 255, 0.5);
                box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.3); border-radius:10px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:bold; color:#fff;">Email</label>
                    <input type="email" name="email" value="<?php echo $_SESSION['email'] ?? ''; ?>" style="width:100%; padding:10px; color:#fff; background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-top-color: rgba(255, 255, 255, 0.5);
                box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.3); border-radius:10px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:5px;">
                    <label style="font-size:11px; font-weight:bold; color:#fff;">Sandi Baru</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tetap" style="width:100%; color:#fff; padding:10px;  background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-top-color: rgba(255, 255, 255, 0.5);
                box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.3); border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <!-- TOMBOL ATUR / SIMPAN -->
            <div style="padding:0 10px;">
                <button type="button" onclick="enableEditMode()" class="view-mode" style="width:100%; padding:12px; background: rgba(255, 255, 255, 0.08);
                        backdrop-filter: blur(24px) saturate(180%);
                        -webkit-backdrop-filter: blur(24px) saturate(180%);
                        border: 1px solid rgba(255, 255, 255, 0.25);
                        border-top-color: rgba(255, 255, 255, 0.5);
                        box-shadow:
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        inset 0 1px 0 rgba(255,255,255,0.3); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer;">Atur Profil</button>
                <div class="edit-mode" style="display:none;">
                    <button type="submit" name="btn_simpan" style="width:100%; padding:12px; background:#28a745; color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; margin-bottom:10px;">Simpan Perubahan</button>
                    <button type="button" onclick="disableEditMode()" style="width:100%; padding:10px;  background: rgba(255, 255, 255, 0.08);
                        backdrop-filter: blur(24px) saturate(180%);
                        -webkit-backdrop-filter: blur(24px) saturate(180%);
                        border: 1px solid rgba(255, 255, 255, 0.25);
                        border-top-color: rgba(255, 255, 255, 0.5);
                        box-shadow:
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        inset 0 1px 0 rgba(255,255,255,0.3); border:1px solid #ccc; border-radius:12px; cursor:pointer; font-size:13px; color: white;">Kembali</button>
                </div>
            </div>
        </form>

        <!-- SEKSI STATUS PESANAN (Tampil di View Mode) -->
        <!-- SEKSI STATUS PESANAN -->
        <!-- HTML BAGIAN SIDEBAR STATUS PESANAN -->
        <!-- 2. BAGIAN TAMPILAN HTML -->
        <div class="view-mode" style="margin-top: 30px; padding: 0 10px;">
            <h4 style="margin: 0 0 15px 5px; font-size: 13px; color: #ffffff; text-transform: uppercase; letter-spacing: 1px;">Status Pesanan 🛒</h4>
            
            <div style="display: grid; gap: 10px;">
                
            <div class="status-container" style="font-family: sans-serif; max-width: 400px;">

                <?php
                // Array buat ngerender box status secara otomatis
                $status_list = [
                    ['id' => 'pending', 'label' => 'Belum Bayar', 'icon' => '⏳', 'bg' => 'rgba(255,255,255,0.08)', 'border' => 'rgba(255,255,255,0.2)', 'backdrop-filter' => 'blur(18px) saturate(180%)', 'color' => '#ffffff', 'q' => $q_pending, 'count' => $count_pending],
                    ['id' => 'proses', 'label' => 'Diproses', 'icon' => '📦', 'bg' => 'rgba(255,255,255,0.08)', 'border' => 'rgba(255,255,255,0.2)', 'backdrop-filter' => 'blur(24px) saturate(180%);', 'color' => '#ffffff', 'q' => $q_proses, 'count' => $count_proses],
                    ['id' => 'dikirim', 'label' => 'Sudah Dikirim', 'icon' => '🚀', 'bg' => 'rgba(255,255,255,0.08)', 'border' => 'rgba(255,255,255,0.2)', 'backdrop-filter' => 'blur(24px) saturate(180%);', 'color' => '#ffffff', 'q' => $q_dikirim, 'count' => $count_dikirim],
                    ['id' => 'selesai', 'label' => 'Selesai', 'icon' => '🏁', 'bg' => 'rgba(255,255,255,0.08)', 'border' => 'rgba(255,255,255,0.2)', 'backdrop-filter' => 'blur(24px) saturate(180%);', 'color' => '#ffffff', 'q' => $q_selesai, 'count' => $count_selesai],
                ];

                foreach ($status_list as $st): ?>
                    <div onclick="toggleDetail('det_<?= $st['id'] ?>')" style="cursor:pointer; background: <?= $st['bg'] ?>; padding: 12px; border-radius: 12px; border: 1px solid <?= $st['border'] ?>; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                            <!-- <span style="font-size: 20px;"><?= $st['icon'] ?></span> -->
                                <span style="font-size: 13px; font-weight: 600; color: <?= $st['color'] ?>;"><?= $st['label'] ?></span>
                            </div>
                            <!-- Anggka di kirinya -->
                            <span style="background: <?= $st['color'] ?>; color: #000000; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?= $st['count'] ?></span>
                        </div>

                        <!-- Detail List Order -->
<div id="det_<?= $st['id'] ?>" style="display:none; padding-top: 10px; margin-top: 8px; border-top: 1px dashed <?= $st['border'] ?>;">
                            <?php if($st['count'] > 0): 
                                mysqli_data_seek($st['q'], 0); 
                                while($d = mysqli_fetch_assoc($st['q'])): ?>
                                
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px; background: rgba(16, 28, 70, 0.45); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); padding: 10px; border-radius: 10px; border: 1px solid rgba(56, 189, 248, 0.15); box-shadow: inset 0 0 10px rgba(56, 189, 248, 0.05);">
                                    
                                    <img src="<?= !empty($d['gambar_game_asli']) ? $d['gambar_game_asli'] : 'Default.jpg' ?>" 
                                        onerror="this.src='./Default.jpg'" 
                                        style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(56, 189, 248, 0.3);">

                                    <div style="flex: 1;">
                                        <div style="font-size: 12px; font-weight: 700; color: #ffffff; letter-spacing: 0.3px; text-shadow: 0 0 10px rgba(255,255,255,0.1);">
                                            <?= $d['nama_game_asli'] ?>
                                        </div>
                                        
                                        <div style="font-size: 10px; color: #94a3b8; margin-top: 2px; font-weight: 500;">
                                            Paket: <span style="color: #38bdf8;"><?= $d['paket'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if($st['id'] == 'dikirim'): ?>
                                        <button onclick="event.stopPropagation(); window.location.href='konfirmasi.php?id=<?= $d['id_order'] ?>'" 
                                                style="background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 9px; font-weight: bold; cursor: pointer; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3); transition: all 0.2s ease;"
                                                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 15px rgba(124, 58, 237, 0.5)';"
                                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(124, 58, 237, 0.3)';">
                                            TERIMA
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                            <?php endwhile; else: ?>
                                <div style="font-size: 11px; color: #94a3b8; text-align: center; padding: 10px 0; font-weight: 500;">Belum ada pesanan nih bre.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <hr class="view-mode" style="margin:25px 0 15px; border:0; border-top:1px solid #eeff00;">
                <a href="../Login/tampilanlogin.php" class="view-mode" style="color:white;padding: 15px;text-decoration:none; font-weight:bold; display:block; text-align:center; font-size:20px; margin-bottom: 20px; background: rgba(255,255,255,0.08) ; backdrop-filter: blur(24px) saturate(180%);-webkit-backdrop-filter: blur(24px) saturate(180%);border: 1px solid rgba(255,255,255,0.25);border-top-color: rgba(255,255,255,0.5);box-shadow: 0 8px 32px rgba(0,0,0,0.4),inset 0 0 0 1px rgba(255,255,255,0.3); border-radius: 15px;">Logout</a>
            </div>
        </div>
        </div>
    </div>

        

    <?php endif; ?>

</div>
<div id="cartSidebar" class="profile-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #fffb00; padding-bottom:15px;">
        <h3 style="color:#fff;">Keranjang Lu mprruy</h3>
        <span onclick="toggleCartSidebar()" style="cursor:pointer; font-size:28px; color:#fff;">&times;</span>
    </div>
    <div id="cartItemsList" style="margin-top:20px; collor:#fff;">
        <p style="text-align:center; color:#fff;">Keranjang Kosong</p>
    </div>
</div>
<footer class="tp-footer">
    <div class="footer-inner">
        <div class="container footer-grid">
            <div class="footer-brand">
               <table>
                <tr>
                    <td><img src="logotopzone.png" alt="Topzone Logo" width="60" height="65"></td>
                    <td><h2 style="color:#fff; -webkit-text-stroke: 4px #1900f7; paint-order: stroke fill;font-size:28px;">TOPZONE</h2></td>
                 </tr>
                </table>
            </div>
            
            <div>
                <h4>Menu</h4>
                <ul><li>Home</li><li>Semua Game</li>
            <li class="promo-text">
                <span id="btnPromo" class="promo-btn" style="z-index: 999; position: relative;">P</span>romo
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
<!-- AREA MODAL & FLOATING BUTTON CHAT -->
<?php if(isset($_SESSION['id_user'])): ?>
<div id="btnChatFloating" style="position:fixed; bottom:20px; right:20px; z-index:9999;">
    <button onclick="bukaModalChat()" style="width:60px; height:60px; background:#007bff; border-radius:50%; color:white; border:none; cursor:pointer; font-size:24px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; transition: 0.3s;">
        💬
    </button>
</div>
<?php endif; ?>

<div id="modalChatMprruy" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background:white; width:95%; max-width:450px; border-radius:15px; overflow:hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.4); animation: zoomIn 0.2s ease-out;">
        
        <!-- Header Chat -->
        <div style="padding:15px; border-bottom:1px solid rgba(0, 210, 255, 0.3); display:flex; align-items:center; gap:10px; background:rgba(10, 25, 47, 0.2);">
            <div style="position:relative;">
                <img src="../Login/logotopzone.png" style="width:40px; height:40px; border-radius:50%;">
                <div id="onlineIndicator" style="position:absolute; bottom:0; right:0; width:12px; height:12px; background:#ccc; border:2px solid #0a192f; border-radius:50%; transition: 0.5s;"></div>
            </div>
            <div style="flex:1;">
                <div style="font-weight:bold; font-size:14px; color:#fff;">TOPZONE OFFICIAL</div>
                <div id="onlineText" style="font-size:11px; color:#888;">Memuat status...</div>
            </div>
            <button onclick="tutupModalChat()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#00d2ff;">&times;</button>
        </div>

        <!-- Body Chat -->
        <div id="chatBodyContainer" style="height:400px; overflow-y:auto; padding:15px; background:#f9f9f9;">
            <!-- Pesan akan dimuat di sini secara live -->
        </div>

        <!-- Area Preview Gambar -->
        <div id="previewPanel" style="display:none; padding:10px; background:#4400ff09; border-top:1px solid #4400ff09; position:relative;">
            <span onclick="cancelPreview()" style="position:absolute; top:5px; right:15px; color:#ff4444; cursor:pointer; font-size:20px; font-weight:bold;">&times;</span>
            <img id="imgPreview" style="max-height:80px; border-radius:8px; border:1px solid #007bff; display:block; margin:auto;">
        </div>

        <!-- Input Area dengan Tombol + Animasi -->
        <!-- Input Area dengan Tombol + Animasi & Tombol Kirim -->
        <div style="padding:10px; background:#fff; border-top:1px solid #d0ff00; display:flex; gap:8px; align-items:center;">
            
            <!-- Tombol Plus -->
            <div style="position:relative;">
                <button type="button" id="btnPlusMenu" onclick="toggleMenuPlus()" style="background:#f0f0f0; color:#007bff; border:1px solid #4400ff09; width:38px; height:38px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-plus" id="plusIcon" style="font-size: 18px; color:#007bff !important;"></i>
                </button>
                
                <!-- Menu Melayang -->
                <div id="menuOptionsPlus">
                    <!-- Ikon Galeri -->
                    <button onclick="document.getElementById('fileInput').click(); toggleMenuPlus()" 
                            style="background:none; border:none; cursor:pointer; padding:5px; transition: transform 0.2s;">
                        <i class="fa-solid fa-image" style="color: #007bff; font-size: 20px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"></i>
                    </button>
                    
                    <!-- Divider Halus -->
                    <div style="width: 20px; height: 1px; background: rgba(255,255,255,0.1);"></div>

                    <!-- Ikon Kamera -->
                    <button onclick="openWebcamModal(); toggleMenuPlus()" 
                            style="background:none; border:none; cursor:pointer; padding:5px; transition: transform 0.2s;">
                        <i class="fa-solid fa-camera" style="color: #28a745; font-size: 20px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"></i>
                    </button>
                </div>
            </div>

            <!-- Input File (Hidden) -->
            <input type="file" id="fileInput" accept="image/*" style="display:none;" onchange="handleImageSelectModal(this)">
            
            <!-- Input Teks -->
            <input type="text" id="inputPesanAjax" placeholder="Tulis pesan..." style="flex:1; padding:10px 15px; border-radius:20px; border:1px solid #ddd; outline:none;">

            <!-- TOMBOL KIRIM (Gue Tambahin di Sini) -->
            <!-- GANTI WARNA TOMBOL KIRIM DI background:#007bff -->
            <button onclick="kirimPesanAjax()" style="background:#007bff; color:white; border:none; width:38px; height:38px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-paper-plane" style="color:white !important; font-size: 16px;"></i>
            </button>
        </div>
    </div>
</div>
<!-- Modal Zoom - Harus di luar container chat manapun -->
<div id="imageModal" onclick="$(this).fadeOut(200)" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0, 0, 0, 0.25); align-items:center; justify-content:center; cursor:zoom-out;">
    <span style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; cursor:pointer;">&times;</span>
    <!-- Pastikan id imgZoom ini yang dipanggil di JS -->
    <img id="imgZoom" style="max-width:90%; max-height:90%; border-radius:10px; border: 2px solid #00ff88; box-shadow: 0 0 30px rgba(0, 0, 0, 0.09);">
</div>
<!-- Modal Kamera -->
<div id="cameraModal" style="display:none; position:fixed; z-index:10001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; flex-direction:column;">
    <div style="background:rgba(8, 0, 255, 0.09); padding:15px; border-radius:15px; text-align:center;">
        <video id="webcam" autoplay playsinline style="width:100%; max-width:400px; border-radius:10px; background:#000;"></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div style="margin-top:15px; display:flex; gap:10px; justify-content:center;">
            <button onclick="takeSnapshot()" style="background:#28a745; color:; border:none; padding:10px 20px; border-radius:20px; font-weight:bold; cursor:pointer;">FOTO</button>
            <button onclick="closeCamera()" style="background:#dc3545; color:#fff; border:none; padding:10px 20px; border-radius:20px; font-weight:bold; cursor:pointer;">BATAL</button>
        </div>
    </div>
</div>

<style>
/* --- ANIMASI PREMIUM --- */
@keyframes liquidShow {
    from { transform: scale(0.9) translateY(20px); opacity: 0; filter: blur(10px); }
    to { transform: scale(1) translateY(0); opacity: 1; filter: blur(0); }
}

@keyframes floatAnim {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
    100% { transform: translateY(0px); }
}

/* --- ANIMASI TAMBAHAN --- */
@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(0, 210, 255, 0.4); }
    70% { box-shadow: 0 0 0 15px rgba(0, 210, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 210, 255, 0); }
}

/* --- STATUS INDICATOR CSS --- */
#onlineIndicator {
    background: #ccc; /* Default Abu-abu */
    box-shadow: 0 0 5px rgba(0,0,0,0.2);
}

/* Class ini bakal ditambahin via JS pas admin Online */
.online-glow {
    background: #00ff88 !important; /* Hijau Liquid */
    box-shadow: 0 0 10px rgba(0, 255, 136, 0.8) !important;
    position: relative;
}

/* Efek Denyut (Pulse) Pas Online */
.online-glow::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: 50%;
    background: rgba(0, 255, 136, 0.4);
    animation: pulseLiquid 1.5s infinite;
}

@keyframes pulseLiquid {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(2.5); opacity: 0; }
}

/* Biar transisi warna halus */
#onlineIndicator, #onlineText {
    transition: all 0.4s ease-in-out;
}

#btnChatFloating button {
    background: rgba(10, 25, 47, 0.75) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(0, 210, 255, 0.3) !important;
    color: #00d2ff !important;
    
    /* Gabungan Animasi: Melayang + Denyut Cahaya */
    animation: floatAnim 3s ease-in-out infinite, pulseGlow 2s infinite;
    
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: pointer;
    outline: none;
}

/* Efek saat kursor mendekat (Hover) */
#btnChatFloating button:hover {
    transform: scale(1.1) rotate(5deg); /* Membesar & miring dikit */
    background: rgba(0, 210, 255, 0.2) !important; /* Warna biru liquid naik */
    border-color: rgba(0, 210, 255, 0.8) !important;
    color: #fff !important;
    box-shadow: 0 12px 35px rgba(0, 210, 255, 0.3);
}

/* Efek saat diklik (Active) */
#btnChatFloating button:active {
    transform: scale(0.9); /* Efek membal/mendekat saat ditekan */
}

/* --- MODAL CHAT (TRUE NAVY LIQUID) --- */
#modalChatMprruy > div {
    /* Gradasi Navy Gelap, bukan Hitam */
    background: linear-gradient(165deg, rgba(10, 25, 47, 0.9), rgba(8, 0, 255, 0.35)) !important;
    backdrop-filter: blur(35px) saturate(210%);
    -webkit-backdrop-filter: blur(35px) saturate(210%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 24px;
    box-shadow: 0 25px 80px rgba(8, 0, 255, 0.35);
    animation: liquidShow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    color: #2200ff7c;
}

/* Header & Body Glass Effect */
#modalChatMprruy div[style*="background:#fff"], 
#modalChatMprruy div[style*="background:white"],
#chatBodyContainer {
    background: rgba(8, 0, 255, 0.09) !important;
    color: #0000ff40 !important;
}

/* --- INPUT AREA --- */
#inputPesanAjax {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    border-radius: 20px !important;
}

#inputPesanAjax::placeholder { color: rgb(255, 255, 255); }

/* --- MENU PLUS (VERTICAL UP) --- */
#menuOptionsPlus {
    display: none; /* Dikontrol JS */
    position: absolute;
    bottom: 55px; /* Di atas tombol plus */
    left: 0;
    flex-direction: column; /* VERTIKAL */
    align-items: center;
    gap: 12px;
    padding: 15px 8px;
    width: 42px;
    background: rgba(10, 25, 47, 0.85) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    border: 1px solid rgba(0, 210, 255, 0.25) !important;
    border-radius: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    z-index: 1001;
    animation: liquidShow 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

#menuOptionsPlus button {
    background: rgba(255, 255, 255, 0.05) !important;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

#menuOptionsPlus button:hover {
    transform: scale(1.2);
    background: rgba(0, 210, 255, 0.2) !important;
    border-color: rgba(0, 210, 255, 0.5) !important;
}

/* Tombol Utama (+) */
#btnPlusMenu {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.fa-rotate-45 { 
    transform: rotate(135deg); 
    color: #ff4d4d !important; 
}

/* Scrollbar Style */
#chatBodyContainer::-webkit-scrollbar { width: 3px; }
#chatBodyContainer::-webkit-scrollbar-thumb { 
    background: rgba(0, 210, 255, 0.3); 
    border-radius: 10px; 
}

/* ==========================================================================
   ANIMASI ENTRY & EXIT UNTUK OVERLAY PINK (MUTLAK SMOOTH)
   ========================================================================== */
@keyframes auraMemudar {
    0% { opacity: 1; }
    100% { opacity: 0; }
}

/* Ketika modal mulai ditutup (SweetAlert menambahkan class .swal2-hide pada body/container),
   kita paksa aura pink menjalankan animasi memudar selama 0.5 detik seirama dengan modal bray */
.swal2-container.swal2-hide ~ .valentine-overlay,
.swal2-container.swal2-hide .valentine-overlay {
    animation: auraMemudar 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards !important;
}

/* Ini animasi exit modal lu yang lama, biarkan tetap ada bray */
@keyframes smoothPopOut {
    0% { opacity: 1; transform: scale(1) translateY(0); }
    100% { opacity: 0; transform: scale(0.85) translateY(20px); }
}
.swal2-hide {
    animation: smoothPopOut 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards !important;
}

/* ==========================================================================
   2. ANIMASI HUJAN SAKURA BIASA (DI BELAKANG MODAL)
   ========================================================================== */
.sakura-leaf-fall {
    position: fixed;
    background: linear-gradient(135deg, #ffb7c5, #ff9ebb);
    border-radius: 20px 0px 20px 20px; 
    
    /* LAYER BACKGROUND: Diturunkan agar berada di balik semua elemen bray */
    z-index: 9990 !important; 
    
    pointer-events: none;
    box-shadow: 0 2px 5px rgba(255, 182, 197, 0.4);
    animation: sakuraHujanBiasa 6s linear forwards;
}

@keyframes sakuraHujanBiasa {
    0% { 
        top: -40px; 
        transform: translateX(0) rotate(0deg) scale(1); 
    }
    100% { 
        top: 105vh; 
        transform: translateX(calc(var(--leftOffset, 50px) * 2)) rotate(360deg) scale(0.6); 
    }
}

/* ==========================================================================
   3. SETTINGAN LAYER MODAL & TEXT OVERLAY
   ========================================================================== */

/* Background Overlay Aura Pink */
.valentine-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; 
    z-index: 9995; 
    background: radial-gradient(circle, rgba(255, 116, 141, 0.1) 25%, rgba(255, 20, 147, 0.6) 100%);
    box-shadow: inset 0 0 300px rgba(255, 20, 147, 0.9);
    
    /* Kondisi awal: Tersembunyi */
    opacity: 0; 
    /* Efek transisi smooth yang sama rata saat masuk & keluar bray */
    transition: opacity 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
}

/* KETIKA MASUK (Aktif) */
.valentine-overlay.v-active { 
    opacity: 1 !important; 
}

/* KETIKA KELUAR (Reverse - Efek Memudar Mulus) */
.valentine-overlay.v-exit {
    opacity: 0 !important;
}

/* Jendela Pop-up Modal (SweetAlert CONTAINER) */
.swal2-container {
    z-index: 10000 !important; /* Standar dasar layer modal */
}

/* Struktur Jendela Pop-up Modal Lu bray */
.promo-pw-modal {
    background: rgba(255, 116, 141, 0.2) !important;
    backdrop-filter: blur(25px) saturate(180%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
    border: 2px solid rgba(255, 255, 255, 0.4) !important;
    border-radius: 30px !important;
    padding: 35px 30px 30px 30px !important; 
    box-shadow: 0 20px 50px rgba(255, 20, 147, 0.3), inset 0 0 20px rgba(255,255,255,0.2) !important;
    width: 450px !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    box-sizing: border-box !important;
}

/* Penataan Judul: DIKUNCI BIAR SATU BARIS LURUS */
.promo-pw-title {
    font-family: 'Poppins', serif !important;
    color: #ffffff !important;
    font-size: 20px !important; 
    font-weight: 800 !important;
    text-shadow: 0 0 10px rgba(255, 255, 255, 0.6) !important;
    margin-bottom: 25px !important;
    padding: 0 15px !important;
    text-align: center !important;
    width: 100% !important;
    white-space: nowrap !important; 
    display: block !important;
    box-sizing: border-box !important;
}

/* Tombol Close 'x' Kecil Rapi di Pojok */
.swal2-popup .swal2-close {
    position: absolute !important;
    top: 15px !important;
    right: 15px !important;
    color: rgba(255, 255, 255, 0.6) !important;
    font-size: 20px !important; 
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1 !important;
    background: transparent !important;
    transition: color 0.2s !important;
}
.swal2-popup .swal2-close:hover {
    color: #ffffff !important;
}

/* Kotak Form & Password Input */
.promo-pw-container {
    position: relative;
    width: 100% !important;
    margin-bottom: 15px !important;
}

.promo-pw-input {
    width: 100% !important;
    height: 55px !important;
    background: rgba(255, 255, 255, 0.15) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 15px !important;
    color: #fff !important;
    font-size: 22px !important;
    font-weight: bold !important;
    text-align: center !important;
    letter-spacing: 6px !important;
    transition: all 0.3s ease !important;
    box-sizing: border-box !important;
}
.promo-pw-input:focus {
    border-color: #ffffff !important;
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.5) !important;
    outline: none !important;
}

/* Notifikasi Pesan Error Merah */
.promo-error-mid {
    width: 100% !important;
    background: rgba(255, 20, 147, 0.7);
    color: #ffffff;
    border-radius: 12px;
    padding: 10px;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 15px;
    border: 1px solid rgba(255, 255, 255, 0.4);
    text-align: center;
    box-shadow: 0 4px 15px rgba(255, 20, 147, 0.3);
    animation: shakeGoyang 0.3s ease-in-out;
    box-sizing: border-box !important;
}
@keyframes shakeGoyang {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
}

/* Tombol Submit Unlock */
.promo-pw-btn {
    width: 100% !important;
    height: 50px !important;
    background: linear-gradient(135deg, #ff1493, #ff69b4);
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 15px;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(255, 20, 147, 0.4);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.promo-pw-btn:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 25px rgba(255, 20, 147, 0.7);
}

/* ==========================================================================
   4. KUNCI JANTUNG & ORBIT LOVE DI LAYER PALING DEPAN (MUTLAK)
   ========================================================================== */
.v-side-heart {
    position: fixed; 
    top: 50%; 
    transform: translateY(-50%) scale(0);
    width: 250px; 
    height: 250px; 
    
    /* GANTI DISINI BRAY: Kita naikkan jadi 10005 supaya nindih di depan box modal */
    z-index: 10005 !important; 
    
    pointer-events: none;
    opacity: 0; 
    transition: opacity 1s ease, transform 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; 
    align-items: center; 
    justify-content: center;
}
.heart-left { left: 20px; }
.heart-right { right: 20px; }
.v-side-heart.v-active { opacity: 1 !important; transform: translateY(-50%) scale(1) !important; }

/* Efek Detak Jantung */
.heart-organ {
    font-size: 150px; 
    filter: drop-shadow(0 0 30px #ff1493);
    animation: organicBeat 0.8s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
}

@keyframes organicBeat {
    0% { transform: scale(0.95); } 
    5% { transform: scale(1.15); } 
    39% { transform: scale(0.85); }
    45% { transform: scale(1.05); } 
    60% { transform: scale(0.95); } 
    100% { transform: scale(0.9); }
}

/* Rotasi Satelit Love Kecil Mengitari Jantung */
.orbit-love { 
    position: absolute; 
    font-size: 25px; 
    z-index: 1; 
    animation: loveRotation var(--d) infinite linear; 
}

@keyframes loveRotation {
    from { transform: rotate(var(--r)) translateX(120px) rotate(calc(-1 * var(--r))); }
    to { transform: rotate(calc(var(--r) + 360deg)) translateX(120px) rotate(calc(-1 * (var(--r) + 360deg))); }
}

/* Efek Ledakan Partikel Hati saat Input Diketik */
.input-heart-particle {
    position: fixed; 
    pointer-events: none; 
    font-size: 20px;
    
    /* GANTI DISINI BRAY: Set paling tinggi dari segalanya agar muncul di atas input text */
    z-index: 10010 !important; 
    
    animation: heartExplode 0.6s cubic-bezier(0.1, 0.8, 0.3, 1) forwards;
}

@keyframes heartExplode {
    0% { opacity: 1; transform: translate(-50%, -50%) scale(0.5); }
    100% { opacity: 0; transform: translate(calc(-50% + var(--mx)), calc(-50% + var(--my))) scale(1.4) rotate(var(--rot)); }
}

/* Animasi centang meletup */
@keyframes tickPop {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.5); }
    100% { transform: scale(1); opacity: 1; }
}

.tick-anim {
    animation: tickPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: inline-block;
}

/* Container buat centang di dalam bubble chat */
.tick-container {
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
}
</style>

<!-- JAVASCRIPT MASTER -->
<script>
// ==========================================
// 1. VARIABLE GLOBAL & STATE MANAGEMENT
// ==========================================
let croppie_instance;
let selectedFile = null;
let stream = null;
let isPromoOpen = false; 
let sakuraInterval = null; // Menyimpan timer efek daun gugur

// --- FUNGSI UTAMA GENERAL & SIDEBAR ---
function toggleDetail(id) {
    var x = document.getElementById(id);
    x.style.display = (x.style.display === "none") ? "block" : "none";
}

function closeSidebar(id) {
    const el = document.getElementById(id);
    if(el) el.classList.remove("active");
}

function closeAllSidebars() {
    closeSidebar("profileSidebar");
    closeSidebar("cartSidebar");
    const overlay = document.getElementById("panelOverlay");
    if(overlay) overlay.style.display = "none";
    resetCroppie();
    disableEditMode();
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
}

// --- FUNGSI PROFILE & CROPPIE ---
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
    const wrapper = document.getElementById('crop_wrapper');
    if(wrapper) wrapper.style.display = 'none';
    const prev = document.getElementById('prev_foto');
    if(prev) prev.style.display = 'inline-block';
}


// ==========================================
// 2. SECRET EASTER EGG PROMO (VALENTINE GOD-TIER & FRONT)
// ==========================================

// Fungsi Generator Daun Sakura Gugur Dinamis (Paling Depan bray)
function startSakuraRain() {
    if (sakuraInterval) clearInterval(sakuraInterval);
    
    sakuraInterval = setInterval(() => {
        if (!isPromoOpen) return;
        const leaf = document.createElement('div');
        leaf.classList.add('sakura-leaf-fall'); // Class CSS baru gua di bawah bray
        
        // Acak posisi, ukuran, dan durasi jatuh daun
        leaf.style.left = Math.random() * 100 + 'vw';
        
        // Perbaikan: Set ke CSS Variable biar dibaca sama keyframes CSS lu bray
        const offset = Math.random() * 150 - 75;
        leaf.style.setProperty('--leftOffset', offset + 'px'); 

        const size = Math.random() * 12 + 8;
        leaf.style.width = size + 'px';
        leaf.style.height = size + 'px';
        leaf.style.animationDuration = (Math.random() * 3 + 4) + 's'; // Antara 4-7 detik
        leaf.style.opacity = Math.random() * 0.7 + 0.3;
        
        document.body.appendChild(leaf);
        
        // Hapus elemen saat animasi selesai biar gak membebani DOM/RAM bray
        setTimeout(() => { leaf.remove(); }, 7000);
    }, 250);
}

function stopSakuraRain() {
    clearInterval(sakuraInterval);
    document.querySelectorAll('.sakura-leaf-fall').forEach(el => el.remove());
}

// NEW LOGIC: EFEK LEDAKAN LOVE SAAT INPUT DI KETIK
function triggerInputHeartExplosion() {
    const inputEl = document.getElementById('customSecretInput');
    if (!inputEl) return;

    // Ambil posisi koordinat element input di layar komputer bray
    const rect = inputEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    // Ledakkan 5 partikel love acak setiap ketikan tombol bray
    for (let i = 0; i < 5; i++) {
        const p = document.createElement('div');
        p.innerHTML = Math.random() > 0.5 ? '💕' : '💖';
        p.classList.add('input-heart-particle'); // Class CSS baru gua di bawah bray
        
        // Atur posisi awal pas di tengah input bar bray
        p.style.left = centerX + 'px';
        p.style.top = centerY + 'px';
        
        // Acak arah lemparan partikel (X, Y, dan Rotasi Putar) bray
        const moveX = (Math.random() * 200 - 100) + 'px';
        const moveY = (Math.random() * 200 - 100) + 'px';
        const rotation = (Math.random() * 360) + 'deg';
        
        p.style.setProperty('--mx', moveX);
        p.style.setProperty('--my', moveY);
        p.style.setProperty('--rot', rotation);
        
        document.body.appendChild(p);
        
        // Singkirkan dari DOM biar memori enteng bray
        setTimeout(() => { p.remove(); }, 600);
    }
}

// ==========================================
// 2. SECRET EASTER EGG PROMO (VALENTINE RENEWAL)
// ==========================================

function startSakuraRain() {
    if (sakuraInterval) clearInterval(sakuraInterval);
    
    sakuraInterval = setInterval(() => {
        if (!isPromoOpen) return;
        
        const leaf = document.createElement('div');
        leaf.classList.add('sakura-leaf-fall');
        
        // Mengatur posisi horizontal spawn daun secara acak di layar
        leaf.style.left = Math.random() * 100 + 'vw';
        leaf.style.top = '-40px'; 
        
        // Mengirim nilai ayunan angin ke variabel CSS
        const offset = Math.random() * 160 - 80;
        leaf.style.setProperty('--leftOffset', offset + 'px'); 

        // Set ukuran daun bervariasi acak (8px - 20px)
        const size = Math.random() * 12 + 8;
        leaf.style.width = size + 'px';
        leaf.style.height = size + 'px';
        
        // Set kecepatan jatuh bervariasi biar estetik alami (4s sampai 7s)
        const duration = Math.random() * 3 + 4;
        leaf.style.animationDuration = duration + 's';
        
        leaf.style.opacity = Math.random() * 0.7 + 0.3;
        
        document.body.appendChild(leaf);
        
        // Hapus otomatis dari DOM setelah durasi jatuhnya selesai lewat bray
        setTimeout(() => { leaf.remove(); }, duration * 1000);
    }, 200); // Daun baru muncul mengalir deras tiap 200ms
}

function stopSakuraRain() {
    // 1. Matikan interval biar daun baru gak muncul lagi bray
    if (typeof sakuraInterval !== 'undefined') {
        clearInterval(sakuraInterval);
    }
    
    // 2. KUNCI UTAMA: Ambil elemen overlay aura pink
    const overlay = document.querySelector('.valentine-overlay');
    if (overlay) {
        // Paksa transisinya lewat inline style JS biar gak ketimpa CSS lain
        overlay.style.transition = "opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1)";
        overlay.style.opacity = "0"; // Meredup halus sampai habis
        
        // Setelah 800ms (pas bener-bener pudar), baru cabut class v-active-nya bray
        setTimeout(() => {
            overlay.classList.remove('v-active');
            overlay.style.opacity = ""; // Reset inline style biar pas dibuka lagi gak bug
        }, 800);
    }

    // 3. Daun yang masih sisa di layar juga kita pudar halus biar gak ilang kaget
    document.querySelectorAll('.sakura-leaf-fall').forEach(leaf => {
        leaf.style.transition = "opacity 0.6s ease";
        leaf.style.opacity = "0";
        setTimeout(() => leaf.remove(), 600);
    });
}

function triggerInputHeartExplosion() {
    const inputEl = document.getElementById('customSecretInput');
    if (!inputEl) return;

    const rect = inputEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    for (let i = 0; i < 5; i++) {
        const p = document.createElement('div');
        p.innerHTML = Math.random() > 0.5 ? '💕' : '💖';
        p.classList.add('input-heart-particle');
        
        p.style.left = centerX + 'px';
        p.style.top = centerY + 'px';
        
        const moveX = (Math.random() * 200 - 100) + 'px';
        const moveY = (Math.random() * 200 - 100) + 'px';
        const rotation = (Math.random() * 360) + 'deg';
        
        p.style.setProperty('--mx', moveX);
        p.style.setProperty('--my', moveY);
        p.style.setProperty('--rot', rotation);
        
        document.body.appendChild(p);
        setTimeout(() => { p.remove(); }, 600);
    }
}

// Trigger Utama Buka Input Sandi
function bukaPromoSecret(e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }

    isPromoOpen = true;

    // Hidupkan aura overlay background sejak awal
    $('#v-overlay').css('display', 'block').hide().fadeIn(400, function() {
        $(this).addClass('v-active');
    });

    Swal.fire({
        title: '🌸 ENTER SECRET CODE 🌸',
        html: `
            <div class="promo-pw-container">
                <input type="password" id="customSecretInput" class="promo-pw-input" placeholder="••••••••" maxlength="12">
            </div>
            <div id="customMidError" class="promo-error-mid" style="display: none;"></div>
            
            <button id="customUnlockBtn" class="promo-pw-btn">UNLOCK NOW 🌸</button>
        `,
        customClass: {
            popup: 'promo-pw-modal',
            title: 'promo-pw-title'
        },
        buttonsStyling: false, 
        showConfirmButton: false, 
        showCloseButton: true,
        allowOutsideClick: true,
        didOpen: () => {
            const inputSandi = document.getElementById('customSecretInput');
            const btnUnlock = document.getElementById('customUnlockBtn');

            if(inputSandi) {
                inputSandi.focus();

                // Pas diketik, trigger efek ledakan love + sembunyiin tulisan wrong-nya otomatis bray
                inputSandi.addEventListener('input', () => {
                    $('#customMidError').hide(); 
                    triggerInputHeartExplosion();
                });

                inputSandi.onkeypress = (event) => {
                    if (event.key === 'Enter') {
                        prosesVerifikasiSandi(inputSandi.value);
                    }
                };
            }

            if(btnUnlock) {
                btnUnlock.onclick = () => {
                    prosesVerifikasiSandi(inputSandi.value);
                };
            }
        },
        willClose: () => {
            if ($('.pink-main-popup').length === 0) {
                isPromoOpen = false;
                $('#v-overlay').removeClass('v-active').fadeOut(500);
            }
        }
    });
}

// Validasi Sandi
function prosesVerifikasiSandi(sandi) {
    if (sandi === '09022010') {
        showMainPromoPopup();
    } else {
        // Tampilkan pesan error custom di tengah secara halus dengan animasi shake CSS
        const errorDiv = $('#customMidError');
        errorDiv.hide().text('⚠️ Wrong Code, Try Again! 🌸').fadeIn(150);
        
        // Auto block isi teks biar user langsung ketik ulang tanpa perlu backspace manual
        const inputSandi = document.getElementById('customSecretInput');
        if(inputSandi) {
            inputSandi.focus();
            inputSandi.select();
        }
    }
}

// Tampilkan Jendela Utama Iframe
function showMainPromoPopup() {
    // Tampilkan detak jantung di depan murni
    $('.v-side-heart').css('display', 'flex').addClass('v-active');
    
    // Mulai hujan kelopak daun sakura di depan murni
    startSakuraRain();

    Swal.fire({
        title: '<span style="font-family: serif; letter-spacing: 2px;">🌸 WHEN DID YOU KNOW? 🌸</span>',
        html: `
            <div style="position:relative;">
                <div style="position:relative; overflow:hidden; border-radius:35px; border: 3px solid rgba(255,255,255,0.5);">
                    <iframe src="../Home/806/index.html" style="width:100%; height:460px; border:none; border-radius:30px;"></iframe>
                </div>
            </div>
        `,
        showConfirmButton: false, 
        showCloseButton: true,
        width: '800px',
        background: 'rgba(255, 116, 141, 0.9)',
        backdropFilter: 'blur(35px) saturate(200%)',
        color: '#fff',
        padding: '30px',
        allowOutsideClick: true,
        customClass: {
            popup: 'pink-main-popup'
        },
        willClose: () => {
            isPromoOpen = false;
            $('#v-overlay, .v-side-heart').removeClass('v-active').fadeOut(500);
            stopSakuraRain();
        }
    });
}


// ==========================================
// 3. BACKEND CORE & LIVE CHAT AJAX
// ==========================================
function muatChatLive() {
    if($('#modalChatMprruy').is(':visible')) {
        $.ajax({
            url: 'Chat/load_chat.php',
            type: 'GET',
            success: function(data) { $('#chatBodyContainer').html(data); }
        });
    }
}

function kirimPesanAjax() {
    var pesan = $('#inputPesanAjax').val();
    if(pesan.trim() == "" && !selectedFile) return; 

    let formData = new FormData();
    formData.append('pesan', pesan);
    if(selectedFile) formData.append('gambar', selectedFile);

    $.ajax({
        url: 'Chat/kirim_chat.php', 
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            $('#inputPesanAjax').val('');
            cancelPreview(); 
            muatChatLive();
            setTimeout(scrollKeBawah, 200);
        },
        error: function(xhr, status, error) { console.error(error); }
    });
}

function bukaModalChat() {
    document.getElementById("modalChatMprruy").style.display = "flex";
    document.body.style.overflow = "hidden";
    scrollKeBawah();
    muatChatLive();
}

function tutupModalChat() {
    document.getElementById("modalChatMprruy").style.display = "none";
    document.body.style.overflow = "auto";
    closeCamera();
}

function toggleMenuChat() {
    $('#menuOptionsModal').fadeToggle(150).css('display', 'flex');
    $('#plusIconModal').toggleClass('fa-rotate-45');
}

function scrollKeBawah() {
    var chatBox = document.getElementById("chatBodyContainer");
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
}

function toggleMenuPlus() {
    $('#menuOptionsPlus').fadeToggle(150).css('display', 'flex');
    $('#plusIcon').toggleClass('fa-rotate-45');
}

function zoomImage(src) {
    $('#imgZoom').attr('src', src);
    $('#imageModal').css('display', 'flex').hide().fadeIn(200);
}

function handleImageSelectModal(input) {
    const file = input.files[0];
    if (file) {
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = e => {
            $('#imgPreview').attr('src', e.target.result);
            $('#previewPanel').slideDown();
        };
        reader.readAsDataURL(file);
    }
}

function cancelPreview() {
    selectedFile = null;
    $('#previewPanel').hide();
    $('#fileInput').val('');
}

function openWebcamModal() {
    $('#cameraModal').css('display', 'flex');
    navigator.mediaDevices.getUserMedia({ video: true })
    .then(s => { stream = s; document.getElementById('webcam').srcObject = stream; })
    .catch(err => { alert("Kamera error: " + err); closeCamera(); });
}

function takeSnapshot() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        selectedFile = new File([blob], "snap.jpg", {type:"image/jpeg"});
        $('#imgPreview').attr('src', canvas.toDataURL('image/jpeg'));
        $('#previewPanel').slideDown();
        closeCamera();
    }, 'image/jpeg');
}

function closeCamera() {
    if(stream) stream.getTracks().forEach(t => t.stop());
    $('#cameraModal').hide();
}

function confirmSelesai(idOrder) {
    // Menggunakan Toast untuk konfirmasi dengan timer/durasi di bawahnya
    Toast.fire({
        icon: 'question',
        html: `
            <span class="tz-toast-title">YAKIN BRAY?</span>
            <p class="tz-toast-content" style="margin-bottom: 8px;">Pastikan pesanan emang udah masuk ke akun lu!</p>
            <div style="display: flex; gap: 5px; justify-content: flex-end;">
                <button id="btn-toast-yes" class="swal2-confirm swal2-styled" style="padding: 4px 10px; font-size: 12px; background-color: #3085d6; margin: 0;">Ya</button>
                <button id="btn-toast-no" class="swal2-cancel swal2-styled" style="padding: 4px 10px; font-size: 12px; background-color: #d33; margin: 0;">Gak</button>
            </div>
        `,
        timer: 7000, // Durasi 7 detik (bisa diatur sesuai keinginan)
        timerProgressBar: true, // Garis durasi berjalan di bagian rada bawah toast
        showConfirmButton: false, // Sembunyikan tombol bawaan agar pas dengan layout toast
        didOpen: (toast) => {
            // Pasang event listener manual untuk tombol di dalam Toast
            const btnYes = toast.querySelector('#btn-toast-yes');
            const btnNo = toast.querySelector('#btn-toast-no');

            if (btnYes) {
                btnYes.addEventListener('click', () => {
                    Swal.close(); // Tutup toast konfirmasi

                    // Jalankan proses update data
                    fetch('update_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id_order=' + idOrder
                    })
                    .then(response => response.text())
                    .then(data => {
                        if (data.trim() === "success") {
                            Toast.fire({ 
                                icon: 'success', 
                                html: `<span class="tz-toast-title">MANTAP!</span><p class="tz-toast-content">Status pesanan berhasil diupdate.</p>` 
                            }).then(() => { 
                                location.reload(); 
                            });
                        } else {
                            Toast.fire({ 
                                icon: 'error', 
                                html: `<span class="tz-toast-title">GAGAL!</span><p class="tz-toast-content">${data}</p>` 
                            });
                        }
                    })
                    .catch(err => console.error(err));
                });
            }

            if (btnNo) {
                btnNo.addEventListener('click', () => {
                    Swal.close(); // Tutup jika batal bray
                });
            }
        }
    });
}

function cekStatusAdminLive() {
    fetch('Chat/Admin_Chat/update_status.php') 
        .then(res => res.text())
        .then(status => {
            const currentStatus = status.trim().toLowerCase();
            const indicator = document.getElementById('onlineIndicator');
            const statusText = document.getElementById('onlineText');
            if (currentStatus === 'online') {
                if(indicator) indicator.classList.add('online-glow');
                if(statusText) {
                    statusText.innerText = 'Online'; statusText.style.color = '#00ff88';
                    statusText.style.textShadow = '0 0 5px rgba(0, 255, 136, 0.3)';
                }
            } else {
                if(indicator) { indicator.classList.remove('online-glow'); indicator.style.background = '#ff4444'; }
                if(statusText) { statusText.innerText = 'Offline (Slow Respon)'; statusText.style.color = '#888'; }
            }
        }).catch(() => {});
}

function getTickHtml(statusAdmin, isRead) {
    if (isRead == 1) return '<i class="fa-solid fa-check-double tick-anim" style="color: #00d2ff; font-size: 10px; margin-left: 5px;"></i>';
    if (statusAdmin === 'online') return '<i class="fa-solid fa-check-double tick-anim" style="color: #888; font-size: 10px; margin-left: 5px;"></i>';
    return '<i class="fa-solid fa-check tick-anim" style="color: #888; font-size: 10px; margin-left: 5px;"></i>';
}


// ==========================================
// 4. JQUERY SINGLE READY BLOCK
// ==========================================
$(document).ready(function() {
    // Interval Chat Engine & Admin Status Tracker
    setInterval(muatChatLive, 2000);
    setInterval(cekStatusAdminLive, 3000);
    cekStatusAdminLive();

    // Suntik Struktur Kosong Atmosfer ke DOM (Awalnya tersembunyi / display:none)
    $('body').append(`
        <div id="v-overlay" class="valentine-overlay" style="display:none;"></div>
        <div id="v-s-left" class="sakura-branch sakura-left" style="display:none;"></div>
        <div id="v-s-right" class="sakura-branch sakura-right" style="display:none;"></div>
        <div class="v-side-heart heart-left" id="l-heart-cont" style="display:none;"><span class="heart-organ">🫀</span></div>
        <div class="v-side-heart heart-right" id="r-heart-cont" style="display:none;"><span class="heart-organ">🫀</span></div>
    `);

    // Inisialisasi Orbit Hati Berputar di Sekeliling Organ Jantung
    const createOrbit = (target) => {
        for (let i = 0; i < 8; i++) {
            const angle = i * 45; 
            const duration = 3 + Math.random() * 2; 
            $(target).append(`<span class="orbit-love" style="--r:${angle}deg; --d:${duration}s;">💕</span>`);
        }
    };
    createOrbit('#l-heart-cont');
    createOrbit('#r-heart-cont');

    // Efek Parallax Ranting Pohon Sakura Mengikuti Kursor Mouse
    $(document).on('mousemove', function(e) {
        if (!isPromoOpen) return;
        let moveX = (e.pageX - window.innerWidth / 2) / 50;
        let moveY = (e.pageY - window.innerHeight / 2) / 50;
        $('.sakura-branch').css({'--x': moveX + 'px', '--y': moveY + 'px'});
    });

    // Event Handler Input Enter Pesan Chat
    $(document).on('keypress', '#inputPesanAjax', function(e) {
        if (e.which === 13) { e.preventDefault(); kirimPesanAjax(); }
    });

    // Croppie Image File Loader
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

    // Eksekusi Pangkas Foto Profil
    document.getElementById('btn_crop')?.addEventListener('click', function() {
        croppie_instance.result({ type: 'base64', size: 'viewport', circle: true }).then(function(hasil) {
            document.getElementById('prev_foto').src = hasil;
            document.getElementById('nav_avatar').src = hasil;
            document.getElementById('foto_base64').value = hasil;
            document.getElementById('crop_wrapper').style.display = 'none';
            document.getElementById('prev_foto').style.display = 'inline-block';
        });
    });

    // Ikat Trigger klik manual elemen khusus Promo di halaman jika diperlukan
    const btnP = document.getElementById('btnPromo');
    if (btnP) { btnP.onclick = function(e) { bukaPromoSecret(e); }; }
});

// Penutup Event di luar area komponen aktif
document.addEventListener('click', function(event) {
    let menu = $('#menuOptionsPlus'); let btnPlus = $('#btnPlusMenu');
    if (menu.is(':visible')) {
        if (!btnPlus.is(event.target) && btnPlus.has(event.target).length === 0 && !menu.is(event.target) && menu.has(event.target).length === 0) {
            menu.fadeOut(150); $('#plusIcon').removeClass('fa-rotate-45');
        }
    }
});
document.addEventListener('keydown', (e) => { if(e.key === "Escape") closeAllSidebars(); });

function bukaModalHistory() {
    const overlay = document.getElementById('overlayHistoryBeli');
    overlay.style.display = "flex"; 
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 20);
    }

function tutupModalHistory() {
    const overlay = document.getElementById('overlayHistoryBeli');
    overlay.style.opacity = '0';
    setTimeout(() => {
        overlay.style.display = "none";
    }, 300);
    }

    // Auto close kalau user sembarang klik di area luar kotak bray
window.addEventListener('click', function(e) {
    const overlay = document.getElementById('overlayHistoryBeli');
    if (e.target === overlay) {
        tutupModalHistory();
    }
    });
</script>
</body>
</html>