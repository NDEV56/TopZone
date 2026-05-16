<?php
/**
 * index.php — HARDENED v3.1 (sync NAFI update)
 *   • Fitur UI baru dari NAFI dipertahankan
 *   • SQL injection di-patch dengan prepared statements (via mysqli stmt
 *     supaya kompatibel dengan template yang masih pakai mysqli_fetch_assoc)
 *   • XSS di output di-escape via tz_e()
 */
require_once __DIR__ . '/_security.php';
tz_security_init();

// 1. CEK STATUS USER
$is_real_user = isset($_SESSION['id_user']) && (int)$_SESSION['id_user'] > 0;
$is_logged_in = isset($_SESSION['nama_user']);
$id_user_skrg = (int)($_SESSION['id_user'] ?? 0);

// 2. LOGIKA CALLBACK / UPDATE STATUS (Pending -> proses) — prepared
if (isset($_GET['status']) && $_GET['status'] === 'success' && $is_real_user) {
    try {
        tz_db()->exec(
            "UPDATE orders SET status = 'proses' WHERE id_user = ? AND status = 'pending'",
            [$id_user_skrg]
        );
    } catch (\Throwable $e) {
        error_log('[topzone-index] ' . $e->getMessage());
    }
    tz_safe_redirect('/Home/index.php');
}

// 3. QUERY PRODUK UTAMA — sediakan $koneksi & $result untuk template
$koneksi = tz_legacy_mysqli();
$result  = mysqli_query($koneksi, 'SELECT * FROM games ORDER BY id DESC');

// 4. AMBIL DATA ORDER
$jumlah_keranjang = 0;
$count_pending = $count_proses = $count_dikirim = $count_selesai = 0;
$q_pending = $q_proses = $q_dikirim = $q_selesai = null;

if ($is_real_user) {
    // Hitung keranjang (prepared)
    try {
        $jumlah_keranjang = (int)tz_db()->fetchColumn(
            'SELECT COALESCE(SUM(qty),0) FROM keranjang WHERE id_user = ?',
            [$id_user_skrg]
        );
    } catch (\Throwable $e) { $jumlah_keranjang = 0; }

    // Helper prepared via mysqli supaya hasilnya kompatibel dengan
    // mysqli_fetch_assoc/mysqli_num_rows-loop yang dipakai template.
    if (!function_exists('getOrders')) {
        function getOrders(\mysqli $kon, int $id_user, string $status) {
            $stmt = $kon->prepare(
                "SELECT o.*,
                    COALESCE(
                        (SELECT g.nama_game FROM games g WHERE INSTR(o.game_name, g.nama_game) > 0 LIMIT 1),
                        (SELECT g.nama_game FROM games g WHERE g.harga = o.total_price LIMIT 1),
                        'TopZone Product'
                    ) AS nama_game_asli,
                    COALESCE(
                        (SELECT g.gambar FROM games g WHERE INSTR(o.game_name, g.nama_game) > 0 LIMIT 1),
                        (SELECT g.gambar FROM games g WHERE g.harga = o.total_price LIMIT 1),
                        'Default.jpg'
                    ) AS gambar_game_asli
                FROM orders o
                WHERE o.id_user = ? AND o.status = ?
                ORDER BY o.id_order DESC"
            );
            if (!$stmt) return null;
            $stmt->bind_param('is', $id_user, $status);
            $stmt->execute();
            return $stmt->get_result();
        }
    }

    $q_pending     = getOrders($koneksi, $id_user_skrg, 'pending');
    $count_pending = $q_pending ? mysqli_num_rows($q_pending) : 0;

    $q_proses      = getOrders($koneksi, $id_user_skrg, 'proses');
    $count_proses  = $q_proses ? mysqli_num_rows($q_proses) : 0;

    $q_dikirim     = getOrders($koneksi, $id_user_skrg, 'dikirim');
    $count_dikirim = $q_dikirim ? mysqli_num_rows($q_dikirim) : 0;

    $q_selesai     = getOrders($koneksi, $id_user_skrg, 'selesai');
    $count_selesai = $q_selesai ? mysqli_num_rows($q_selesai) : 0;
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
                        <?= (int)$jumlah_keranjang ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="tp-user">
                <div onclick="toggleProfileSidebar()">
                    <?php if($is_logged_in): ?>
                        <img id="nav_avatar" src="uploads/<?= tz_attr(!empty($_SESSION['foto']) ? basename((string)$_SESSION['foto']) : 'Default.jpg') ?>?t=<?= time() ?>"
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

        <h2 class="tp-title" id="mainTitle">Semua Produk</h2>
        
        <div id="productList" class="tp-grid">
        <?php if($result && mysqli_num_rows($result) > 0): ?>
            <?php while($row_game = mysqli_fetch_assoc($result)):
                $id_ini = (int)$row_game['id'];
                // PREPARED: hindari SQL injection lewat id_game
                try {
                    $data_ulasan = tz_db()->fetchOne(
                        'SELECT AVG(rating) AS hasil_rata FROM reviews WHERE id_game = ?',
                        [$id_ini]
                    ) ?: ['hasil_rata' => 0];
                } catch (\Throwable $e) { $data_ulasan = ['hasil_rata' => 0]; }
                $angka_bintang = ($data_ulasan['hasil_rata'] > 0) ? round((float)$data_ulasan['hasil_rata'], 1) : 0;
            ?>

                <a href="game_detail.php?game=<?= rawurlencode((string)$row_game['slug']) ?>" class="tp-card">
                    <div class="tp-img" style="background-image:url('<?= tz_attr($row_game['gambar']) ?>')"></div>
                    <div class="tp-info">
                        <h4><?= tz_e($row_game['nama_game']) ?></h4>
                        <div class="tp-meta">
                            ⭐ <?= tz_e(number_format($angka_bintang, 1)) ?> | <?= (int)($row_game['terjual'] ?? 0) ?> terjual
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
        </div>
        
        <div id="notFound" style="display:none; width:100%; padding: 50px 0;">
            <div style="display: flex; justify-content: center; align-items: center; width: 100%;">
                <div style="background:#fff3f3; border:2px dashed #ff0000; padding:20px 40px; border-radius:10px; text-align:center;">
                    <h3 style="color:#ff0000; margin:0;">GAME LAU GADA MPRUYY!</h3>
                    <p style="color:#666;">Coba kata kunci lain...</p>
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
            <?= tz_csrf_field() ?>
            <div style="text-align:center; margin-bottom:20px; color:white;">
                <div style="position: relative; display: inline-block;">
                    <img src="uploads/<?= tz_attr(!empty($_SESSION['foto']) ? basename((string)$_SESSION['foto']) : 'Default.jpg') ?>?t=<?= time() ?>"
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
                <h2 style="margin:0; color:#ffffff; font-size:22px;">@<?= tz_e($_SESSION['username'] ?? '') ?></h2>
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
                    <input type="text" name="nama_user" value="<?= tz_attr($_SESSION['nama_user'] ?? '') ?>" maxlength="64" style="width:100%; color:#fff; padding:10px;  background: rgba(255, 255, 255, 0.08);
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
                    <input type="text" name="username" value="<?= tz_attr($_SESSION['username'] ?? '') ?>" maxlength="32" pattern="[A-Za-z0-9_.\-]{3,32}" style="width:100%; color:#fff; padding:10px;  background: rgba(255, 255, 255, 0.08);
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
                    <input type="email" name="email" value="<?= tz_attr($_SESSION['email'] ?? '') ?>" maxlength="128" style="width:100%; padding:10px; color:#fff; background: rgba(255, 255, 255, 0.08);
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
                        <div id="det_<?= tz_attr($st['id']) ?>" style="display:none; padding-top: 10px; margin-top: 8px; border-top: 1px dashed <?= tz_attr($st['border']) ?>;">
                            <?php if($st['count'] > 0 && $st['q']):
                                @mysqli_data_seek($st['q'], 0);
                                while($d = mysqli_fetch_assoc($st['q'])): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; background: white; padding: 8px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <img src="<?= tz_attr(!empty($d['gambar_game_asli']) ? $d['gambar_game_asli'] : 'Default.jpg') ?>"
                                        onerror="this.src='./Default.jpg'"
                                        style="width: 35px; height: 35px; border-radius: 6px; object-fit: cover;">

                                    <div style="flex: 1;">
                                        <div style="font-size: 11px; font-weight: bold; color: #333;">
                                            <?= tz_e($d['nama_game_asli']) ?>
                                        </div>

                                        <div style="font-size: 9px; color: #424242;">
                                            Paket: <?= tz_e($d['paket']) ?>
                                        </div>
                                    </div>
                                    <?php if($st['id'] === 'dikirim'): ?>
                                        <button onclick="event.stopPropagation(); window.location.href='Konfirmasi.php?id=<?= (int)$d['id_order'] ?>'"
                                                style="background: #9c27b0; color: white; border: none; padding: 5px 8px; border-radius: 5px; font-size: 9px; cursor: pointer;">
                                            TERIMA
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; else: ?>
                                <div style="font-size: 10px; color: #bebebe; text-align: center;">Belum ada pesanan nih bre.</div>
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

/* --- VALENTINE GOD-TIER OVERLAY --- */
/* --- VALENTINE GOD-TIER ATMOSPHERE --- */
.valentine-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; z-index: 9998;
    background: radial-gradient(circle, rgba(255, 116, 141, 0) 25%, rgba(255, 20, 147, 0.5) 100%);
    box-shadow: inset 0 0 350px rgba(255, 20, 147, 0.8);
    opacity: 0; transition: opacity 1.5s ease-in-out;
}

/* Container Jantung & Orbit */
.v-side-heart {
    position: fixed; top: 50%; transform: translateY(-50%);
    width: 250px; height: 250px;
    z-index: 10001; pointer-events: none;
    opacity: 0; transition: 1.5s ease;
    display: flex; align-items: center; justify-content: center;
}
.heart-left { left: 20px; }
.heart-right { right: 20px; }

/* Jantung Organik Berdegup */
.heart-organ {
    font-size: 150px;
    filter: drop-shadow(0 0 30px #ff1493);
    animation: organicBeat 0.8s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
    z-index: 2;
}

/* Partikel Love yang Berotasi (Banyak) */
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

@keyframes organicBeat {
    0% { transform: scale(0.95); }
    5% { transform: scale(1.15); }
    39% { transform: scale(0.85); }
    45% { transform: scale(1.05); }
    60% { transform: scale(0.95); }
    100% { transform: scale(0.9); }
}

/* Sakura & Others */
.sakura-branch {
    position: fixed; top: -30px; width: 550px; height: 450px;
    background: url('https://www.transparentpng.com/download/cherry-blossom/cherry-blossom-transparent-background-13.png');
    background-size: contain; background-repeat: no-repeat;
    z-index: 10002; opacity: 0; pointer-events: none;
    transition: opacity 2s ease, transform 0.1s ease-out; 
}
.sakura-left { left: -100px; transform: rotate(-15deg) translate(var(--x), var(--y)); }
.sakura-right { right: -100px; transform: scaleX(-1) rotate(-15deg) translate(var(--x), var(--y)); }

.v-active { opacity: 1 !important; transform: translate(0, 0) rotate(0deg) !important; }

/* Kucing di Atas Box */
.cat-on-box {
    position: absolute; top: -130px; right: 50px; width: 160px; z-index: 10005;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.4));
    opacity: 0; transition: 1s ease-out, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    pointer-events: auto !important;
}
.cat-on-box:hover { transform: scale(1.2) rotate(-5deg); cursor: pointer; }



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
let croppie_instance;
let selectedFile = null;
let stream = null;

// --- FUNGSI GENERAL & SIDEBAR ---
function toggleDetail(id) {
    var x = document.getElementById(id);
    x.style.display = (x.style.display === "none") ? "block" : "none";
}

function confirmSelesai(idOrder) {
    // 1. Pake Swal.fire buat nanya (Biar sinkron sama tema web lo)
    Swal.fire({
        title: 'YAKIN BRAY?',
        text: "Pastikan pesanan emang udah masuk ke akun lu!",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Udah Masuk!',
        cancelButtonText: 'Bentar, Cek Lagi',
        background: 'rgba(20, 20, 20, 0.8)', // Sesuaikan tema dark glass lo
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            // 2. Jalankan Fetch kalau User klik "Ya"
            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_order=' + idOrder
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "success") {
                    // 3. Pake TOAST Global lo buat suksesnya
                    Toast.fire({
                        icon: 'success',
                        html: `
                            <span class="tz-toast-title">MANTAP!</span>
                            <p class="tz-toast-content">Status pesanan berhasil diupdate.</p>
                        `
                    }).then(() => {
                        location.reload(); 
                    });
                } else {
                    // Notif Gagal
                    Toast.fire({
                        icon: 'error',
                        html: `
                            <span class="tz-toast-title">GAGAL!</span>
                            <p class="tz-toast-content">${data}</p>
                        `
                    });
                }
            })
            .catch(err => {
                console.error(err);
                Toast.fire({
                    icon: 'error',
                    title: 'Waduh!',
                    text: 'Koneksi ke server ruyam.'
                });
            });
        }
    });
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

// --- FUNGSI CHAT LIVE MPRRUY ---
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

function muatChatLive() {
    if($('#modalChatMprruy').is(':visible')) {
        $.ajax({
            url: 'Chat/load_chat.php',
            type: 'GET',
            success: function(data) {
                $('#chatBodyContainer').html(data);
            }
        });
    }
}
function zoomImage(src) {
    // 1. Masukkan gambar ke tag img di dalam modal
    $('#imgZoom').attr('src', src);
    
    // 2. Munculkan modal dengan display flex biar ketengah
    $('#imageModal').css('display', 'flex').hide().fadeIn(200);
}
function kirimPesanAjax() {
    var pesan = $('#inputPesanAjax').val();
    
    console.log("Tombol kirim dipicu. Pesan:", pesan); // Debug log

    if(pesan.trim() == "" && !selectedFile) {
        console.log("Pesan kosong, batal kirim.");
        return; 
    }

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
            console.log("Respon server:", response); // LIHAT DI SINI PAS KIRIM
            $('#inputPesanAjax').val('');
            cancelPreview(); 
            muatChatLive();
            setTimeout(scrollKeBawah, 200);
        },
        error: function(xhr, status, error) {
            console.error("AJAX ERROR:", status, error);
            alert("Gagal kirim: " + error);
        }
    });
}
function toggleMenuPlus() {
    $('#menuOptionsPlus').fadeToggle(150).css('display', 'flex');
    $('#plusIcon').toggleClass('fa-rotate-45');
}
// --- IMAGE & KAMERA HANDLER ---
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
    $('#previewPanel').hide(); // Pakai hide() biar langsung ilang
    $('#fileInput').val(''); // Reset input file
}

function openWebcamModal() {
    $('#cameraModal').css('display', 'flex');
    navigator.mediaDevices.getUserMedia({ video: true })
    .then(s => { 
        stream = s; 
        document.getElementById('webcam').srcObject = stream; 
    })
    .catch(err => { 
        alert("Kamera tidak diizinkan atau error: " + err); 
        closeCamera(); 
    });
}

function takeSnapshot() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
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

// --- EVENT LISTENERS ---
$(document).ready(function() {
    // 1. Jalankan auto-update tiap 2 detik
    setInterval(muatChatLive, 2000);

    // 2. Perbaiki Input Enter - Pake JQuery biar sinkron sama fungsi AJAX
    $(document).on('keypress', '#inputPesanAjax', function(e) {
        if (e.which === 13) { 
            e.preventDefault(); 
            console.log("Enter dideteksi, mengirim..."); // Cek di F12
            kirimPesanAjax();
        }
    });

    // 3. Pastikan fungsi cek admin jalan
    setInterval(cekStatusAdminLive, 3000);
    cekStatusAdminLive();

    // Input File Croppie
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

    // Button Crop
    document.getElementById('btn_crop')?.addEventListener('click', function() {
        croppie_instance.result({ type: 'base64', size: 'viewport', circle: true }).then(function(hasil) {
            document.getElementById('prev_foto').src = hasil;
            document.getElementById('nav_avatar').src = hasil;
            document.getElementById('foto_base64').value = hasil;
            document.getElementById('crop_wrapper').style.display = 'none';
            document.getElementById('prev_foto').style.display = 'inline-block';
        });
    });

    // SweetAlert Promo
$(document).ready(function() {
    let isPromoOpen = false;

    // Fungsi buat bikin orbit love banyak
    const createOrbit = (target) => {
        for (let i = 0; i < 8; i++) {
            const angle = i * 45; // Sebarkan love secara merata
            const duration = 3 + Math.random() * 2; // Kecepatan putar acak
            $(target).append(`<span class="orbit-love" style="--r:${angle}deg; --d:${duration}s;">💕</span>`);
        }
    };

    // Inject Elemen
    $('body').append(`
        <div id="v-overlay" class="valentine-overlay"></div>
        <div id="v-s-left" class="sakura-branch sakura-left"></div>
        <div id="v-s-right" class="sakura-branch sakura-right"></div>
        <div class="v-side-heart heart-left" id="l-heart-cont"><span class="heart-organ">🫀</span></div>
        <div class="v-side-heart heart-right" id="r-heart-cont"><span class="heart-organ">🫀</span></div>
    `);

    createOrbit('#l-heart-cont');
    createOrbit('#r-heart-cont');

    // Mouse Move Parallax
    $(document).on('mousemove', function(e) {
        if (!isPromoOpen) return;
        let moveX = (e.pageX - window.innerWidth / 2) / 50;
        let moveY = (e.pageY - window.innerHeight / 2) / 50;
        $('.sakura-branch').css({'--x': moveX + 'px', '--y': moveY + 'px'});
    });

    const btnP = document.getElementById('btnPromo');
    if (btnP) {
        btnP.onclick = function(e) {
            e.preventDefault(); e.stopPropagation();
            isPromoOpen = true;

            $('#v-overlay, .sakura-branch, .v-side-heart').addClass('v-active');

            Swal.fire({
                title: '<span style="font-family: serif; letter-spacing: 2px;">🌸 WHEN DID YOU KNOW? 🌸</span>',
                html: `
                    <div style="position:relative;">
                        <img src="https://www.pngarts.com/files/1/White-Cat-PNG-Transparent-Image.png" class="cat-on-box v-active">
                        <div style="position:relative; overflow:hidden; border-radius:35px; border: 3px solid rgba(255,255,255,0.5);">
                            <iframe src="../Home/806/index.html" style="width:100%; height:460px; border:none; border-radius:30px;"></iframe>
                        </div>
                    </div>
                `,
                showConfirmButton: false, 
                showCloseButton: true,
                width: '800px',
                background: 'rgba(255, 116, 141, 0.85)',
                backdropFilter: 'blur(35px) saturate(200%)',
                color: '#fff',
                padding: '30px',
                allowOutsideClick: true,
                willClose: () => {
                    isPromoOpen = false;
                    $('#v-overlay, .sakura-branch, .v-side-heart').removeClass('v-active');
                }
            });
        };
    }
});
});

// Global Click Handler (Tutup menu/sidebar)
document.addEventListener('click', function(event) {
    let menu = $('#menuOptionsPlus');
    let btnPlus = $('#btnPlusMenu');
    if (menu.is(':visible')) {
        // Jika klik di luar tombol plus dan di luar menu, maka tutup
        if (!btnPlus.is(event.target) && btnPlus.has(event.target).length === 0 && !menu.is(event.target) && menu.has(event.target).length === 0) {
            menu.fadeOut(150);
            $('#plusIcon').removeClass('fa-rotate-45');
        }
    }
});

document.addEventListener('keydown', (e) => { if(e.key === "Escape") closeAllSidebars(); });

function cekStatusAdminLive() {
    // Sesuaikan path ini dengan letak file update_status.php lo
    fetch('Chat/Admin_Chat/update_status.php') 
        .then(res => res.text())
        .then(status => {
            const currentStatus = status.trim().toLowerCase();
            const indicator = document.getElementById('onlineIndicator');
            const statusText = document.getElementById('onlineText');

            if (currentStatus === 'online') {
                // Tambahkan class animasi & warna hijau
                indicator.classList.add('online-glow');
                statusText.innerText = 'Online';
                statusText.style.color = '#00ff88'; // Hijau nyala
                statusText.style.textShadow = '0 0 5px rgba(0, 255, 136, 0.3)';
            } else {
                // Balikin ke merah/abu-abu tanpa animasi
                indicator.classList.remove('online-glow');
                indicator.style.background = '#ff4444'; // Merah pas offline
                indicator.style.boxShadow = '0 0 5px rgba(255, 68, 68, 0.5)';
                statusText.innerText = 'Offline (Slow Respon)';
                statusText.style.color = '#888';
                statusText.style.textShadow = 'none';
            }
        })
        .catch(err => {
            console.log("Admin kaga aktif / File kaga ketemu bray");
        });
}

// Cek tiap 3 detik biar kerasa live
setInterval(cekStatusAdminLive, 3000);
cekStatusAdminLive(); // Panggil pas pertama buka

function getTickHtml(statusAdmin, isRead) {
    // isRead = 1 (Dibaca), isRead = 0 (Belum dibaca)
    if (isRead == 1) {
        // Centang 2 Biru (Sudah Dibaca)
        return '<i class="fa-solid fa-check-double tick-anim" style="color: #00d2ff; font-size: 10px; margin-left: 5px;"></i>';
    } else if (statusAdmin === 'online') {
        // Centang 2 Abu-abu (Admin Online/Pesan Masuk)
        return '<i class="fa-solid fa-check-double tick-anim" style="color: #888; font-size: 10px; margin-left: 5px;"></i>';
    } else {
        // Centang 1 Abu-abu (Admin Offline)
        return '<i class="fa-solid fa-check tick-anim" style="color: #888; font-size: 10px; margin-left: 5px;"></i>';
    }
}
</script>
</body>
</html>