<?php
/**
 * TOPZONE - Game Topup Detail Page (Dynamic Input & Grid Layout Fixed)
 * Author: Gemini AI (Optimized & Secured)
 * Date: 2026-05-17
 */

session_start();
include 'koneksi.php'; 

// ==========================================
// 1. AMBIL DATA GAME BERDASARKAN SLUG (PREPARED STATEMENTS)
// ==========================================
$slug = $_GET['game'] ?? '';

$stmt_game = $koneksi->prepare("SELECT * FROM games WHERE slug = ?");
$stmt_game->bind_param("s", $slug);
$stmt_game->execute();
$g = $stmt_game->get_result()->fetch_assoc();

if (!$g) { 
    die("Game tidak ditemukan mprruy! Balik lagi ke <a href='index.php'>Home</a>"); 
}

$id_g = $g['id'];
$gn_check = strtolower($g['nama_game']);

// ==========================================
// 2. HITUNG STATISTIK RATING & ULASAN (PREPARED STATEMENTS)
// ==========================================
$stmt_avg = $koneksi->prepare("SELECT AVG(rating) as rata_rata, COUNT(id) as total_review FROM reviews WHERE id_game = ?");
$stmt_avg->bind_param("i", $id_g);
$stmt_avg->execute();
$res_avg = $stmt_avg->get_result()->fetch_assoc();

$rating_rata = ($res_avg['total_review'] > 0) ? round($res_avg['rata_rata'], 1) : 0;
$total_review = $res_avg['total_review'];
$terjual = $g['terjual'] ?? 0;
$stmt_rev = $koneksi->prepare("
    SELECT 
        r.*,
        COALESCE(u.username, r.nama_user) AS user_name_fix,
        COALESCE(u.foto, 'Default.jpg') AS foto_user_fix
    FROM reviews r
    LEFT JOIN users u 
        ON r.nama_user = u.nama_user
    WHERE r.id_game = ?
    ORDER BY r.id DESC
");

$stmt_rev->bind_param("i", $id_g);
$stmt_rev->execute();
$q_rev = $stmt_rev->get_result();

$array_ulasan_js = [];

while($row = $q_rev->fetch_assoc()) {
    $array_ulasan_js[] = $row;
}

$total_komentar = count($array_ulasan_js);
// ==========================================
// 4. LOGIKA IDENTITAS USER (SESSION/GUEST)
// ==========================================
$nama_tampil = $_SESSION['nama_user'] ?? ($_COOKIE['guest_name'] ?? "User" . rand(100, 999));

// SINKRONISASI PARAMETER RE-ORDER VIA URL
$selected_produk = $_GET['paket'] ?? ($_GET['select_produk'] ?? '');
$qty_cart = (int)($_GET['qty'] ?? 1);
if ($qty_cart < 1) $qty_cart = 1;
$from_cart = $_GET['from_cart'] ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="javascript.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <title>Top Up <?php echo htmlspecialchars($g['nama_game']); ?> - TOPZONE OFFICIAL</title>
    
    <style>
        :root {
            --primary: #007bff;
            --primary-hover: #0056b3;
            --dark: #050e2e;
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.25);
            --gold-accent: #c9a227;
        }

        body { 
            background: linear-gradient(135deg, #050e2e, #1205a5, #050e2e);
            display: flex; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            color: #ffffff; 
            line-height: 1.6;
            min-height: 100vh;
        }

        .container { 
            max-width: 1200px; 
            margin: 40px auto; 
            display: grid;
            grid-template-columns: 7fr 4fr; 
            gap: 30px; 
            padding: 0 20px; 
            width: 100%;
            box-sizing: border-box;
        }

        .glass-panel {
            color: #fff;
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid var(--glass-border);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.3); 
            padding: 30px; 
            border-radius: 20px; 
            box-sizing: border-box;
        }

        .tp-img { 
            width: 120px; 
            height: 120px; 
            background-size: cover; 
            background-position: center; 
            border-radius: 15px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .desc-panel {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
            color: #e0e0e0;
        }

        .item-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); 
            gap: 15px; 
            margin-top: 20px; 
        }

        .item-card { 
            background: rgba(255, 255, 255, 0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(251, 255, 0, 0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 8px rgba(0, 0, 0, 0.4), inset 0 0 0 1px rgba(255, 217, 0, 0.3);  
            padding: 20px; 
            border-radius: 15px; 
            cursor: pointer; 
            text-align: center; 
            transition: all 0.2s ease-in-out; 
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 90px; 
        }
        .item-card:hover { 
            border-color: var(--gold-accent); 
            transform: translateY(-3px);
        }
        .item-card.selected { 
            border: 2.5px solid var(--gold-accent); 
            background: rgba(0, 0, 0, 0.5); 
            position: relative;
        }
        .item-card.selected::after {
            content: "✓";
            position: absolute;
            top: 5px;
            right: 10px;
            color: var(--gold-accent);
            font-weight: bold;
        }

        .price { 
            color: white; 
            font-weight: 800; 
            font-size: 16px; 
            margin-top: 8px; 
        }

        .side-buy { 
            position: sticky; 
            top: 40px;
            width: 100%;
            height: fit-content;
        }

        /* =======================================================
           STYLING BARU & FIX UNTUK INPUT GROUP (KUSTOM KE SAMPING 2)
           ======================================================= */
        .dynamic-field-wrapper {
            width: 100%;
            margin-bottom: 15px;
        }

        /* Container grid penampung input */
        .input-grid-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px; /* Jarak antar input */
            width: 100%;
            box-sizing: border-box;
        }

        /* Aturan Input Umum */
        /* =======================================================
        STYLING FORM INPUT (FIX BACKGROUND PUTIH SAAT INPUT)
        ======================================================= */
        .form-input { 
            width: 100%; 
            padding: 13px 15px; 
            background: rgba(255, 255, 255, 0.08) !important; /* Paksa tetap transparan */
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid var(--glass-border);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-radius: 12px; 
            font-size: 14px; 
            box-sizing: border-box; 
            color: #ffffff !important; /* Paksa teks warna putih mprruy */
            transition: all 0.3s ease;
        }

        /* Menghilangkan warna putih ketika input diklik/fokus */
        .form-input:focus { 
            background: rgba(255, 255, 255, 0.12) !important; /* Sedikit lebih terang saat diklik */
            color: #ffffff !important;
            border-color: var(--gold-accent) !important; 
            box-shadow: 0 0 10px rgba(201, 162, 39, 0.5) !important;
            outline: none; 
        }

        /* Mengatasi autofill bawaan browser yang suka bikin background jadi putih/kuning */
        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover, 
        .form-input:-webkit-autofill:focus,
        .form-input:-webkit-autofill:active {
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s; /* Trik menahan background asli */
            box-shadow: inset 0 0 0px 1000px rgba(255, 255, 255, 0.08) !important;
        }

        .form-input::placeholder { 
            color: rgba(255, 255, 255, 0.6) !important; 
        }

        /* Jika isinya banyak, otomatis bagi 2 kolom kesamping (Flex Basis 48%) */
        .input-grid-container.multi-field .custom-dynamic-field {
            flex: 1 1 calc(50% - 6px);
            min-width: 130px; /* Supaya ga kekecilan di layar hp */
        }

        /* Jika isinya tunggal, paksa full lebar */
        .input-grid-container.single-field .custom-dynamic-field {
            flex: 1 1 100%;
        }

        /* Dropdown custom */
        select.form-input {
            color: #ffffff;
            background-color: rgba(15, 23, 42, 0.9);
        }
        select.form-input option {
            background: #1205a5;
            color: #fff;
        }

        .tab-container { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { 
            padding: 12px; 
            border-radius: 10px; 
            cursor: pointer; 
            text-align: center; 
            font-weight: bold; 
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(251, 255, 0, 0.25);
            font-size: 13px; 
            color: #fff;
            transition: 0.3s;
            flex: 1;
        }
        .tab-btn.active { 
            background: var(--gold-accent); 
            color: #000; 
            border-color: gold; 
        }

        .qty-control { 
            display: flex; 
            align-items: center; 
            background: rgba(255,255,255,0.08); 
            border: 1px solid var(--glass-border);
            border-radius: 12px; 
            width: fit-content; 
            overflow: hidden; 
            margin: 15px 0;
        }
        .qty-btn { color: #fff; width: 40px; height: 40px; border: none; background: transparent; cursor: pointer; font-size: 18px;}
        .qty-btn:hover { background: rgba(255,255,255,0.2); }
        .qty-input { width: 60px; color: #fff; text-align: center; border: none; font-weight: bold; font-size: 16px; background: transparent;}

        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 35px; color: #ddd; cursor: pointer; }
        .rating-stars input:checked ~ label, .rating-stars label:hover, .rating-stars label:hover ~ label { color: #ffca08; }

        .rev-item { 
            background: rgba(255,255,255,0.05); 
            padding: 20px; 
            border-radius: 15px; 
            margin-bottom: 15px; 
            border-left: 5px solid #ffca08; 
        }

        .btn-cart {
            width: 60px; height: 60px; 
            background: rgba(255,255,255,0.08); 
            border: 1px solid var(--glass-border);
            border-radius: 15px; 
            color: #007bff; 
            font-size: 24px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            transition: 0.3s;
        }
        .btn-cart:hover { background: #007bff; color: #fff; transform: scale(1.02); }

        .btn-buy-now {
            flex-grow: 1; height: 60px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 15px; 
            font-weight: bold; 
            font-size: 18px; 
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-buy-now:hover { background: #0056b3; transform: scale(1.02); }

        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; }
            .side-buy { position: static; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="main-info glass-panel">
        <a href="index.php" class="btn-back-home" style="display: inline-flex;align-items: center;gap: 6px;padding: 8px 16px;margin-bottom: 16px;background: rgba(255,255,255,0.08); color: #ffffff;border: 1px solid var(--glass-border);border-radius: 8px;font-size: 13px;font-weight: 500;text-decoration: none;transition: all 0.2s ease;">
                 Kembali ke Home
        </a>
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="tp-img" style="background-image:url('<?php echo htmlspecialchars($g['gambar']); ?>')"></div>
            <div>
                <h1 style="margin: 0; font-size: 28px;"><?php echo htmlspecialchars($g['nama_game']); ?></h1>
                <p style="color: #ffffff; margin: 5px 0;">Kategori: <strong><?php echo htmlspecialchars($g['kategori']); ?></strong></p>
                <div style="color: #ffca08; font-size: 20px;">
                    <?php for($i=1; $i<=5; $i++) echo ($i <= $rating_rata) ? "★" : "☆"; ?>
                    <span style="color: #fff; font-size: 15px;"> (<?php echo $rating_rata; ?>/5.0) | <?php echo htmlspecialchars($terjual); ?> Terjual</span>
                </div>
            </div>
        </div>

        <div class="desc-panel">
            <strong>Deskripsi Game:</strong>
            <p style="margin: 5px 0 0 0; line-height: 1.5; color: #ccc;">
                <?php echo !empty($g['deskripsi']) ? nl2br(htmlspecialchars($g['deskripsi'])) : "top up game murah, cepat, dan admin anti php in!"; ?>
            </p>
        </div>

        <hr style="margin: 35px 0; border: 0; border-top: 1.5px solid rgba(255,255,255,0.15);">

        <h3>1. Pilih Nominal Top Up</h3>
        
        <?php if (strpos($gn_check, 'roblox') !== false): ?>
            <div class="tab-container">
                <div class="tab-btn active" id="btn-tab-login" onclick="toggleRobloxTab('login')">Robux Via Login</div>
                <div class="tab-btn" id="btn-tab-5hari" onclick="toggleRobloxTab('5hari')">Robux 5 Hari (Tanpa Login)</div>
            </div>
        <?php endif; ?>

        <div class="item-grid" id="product-list">
            <?php 
            $stmt_p = $koneksi->prepare("SELECT * FROM produk_game WHERE id_game = ? ORDER BY harga ASC");
            $stmt_p->bind_param("i", $id_g);
            $stmt_p->execute();
            $q_produk = $stmt_p->get_result();

            if($q_produk->num_rows > 0): 
                while($p = $q_produk->fetch_assoc()): 
                    $tipe_p = $p['tipe'] ?? 'default'; 
            ?>
                <div class="item-card produk-item" 
                    data-tipe="<?= htmlspecialchars($tipe_p) ?>" 
                    data-name="<?= htmlspecialchars($p['nama_produk']); ?>"
                    onclick="selectProduct(this, <?= (int)$p['harga']; ?>, <?= htmlspecialchars(json_encode($p['nama_produk'])); ?>)"
                    style="display: <?= ($gn_check == 'roblox' && $tipe_p != 'roblox_login') ? 'none' : 'flex' ?>;">
                    <div style="font-size: 14px; font-weight: 600; color: #ffffff;"><?= htmlspecialchars($p['nama_produk']); ?></div>
                    <div class="price">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></div>
                </div>
            <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #aaa;">
                    <p>Produk belum tersedia untuk game ini mprruy.</p>
                </div>
            <?php endif; ?>
        </div>
        <div style="margin-top: 60px; border-top: 2px solid rgba(255, 215, 0, 0.3); padding-top: 30px; font-family: 'Poppins', sans-serif;">
            <h3 style="color: #fff; text-shadow: 0 0 15px rgba(201, 162, 39, 0.3); margin-bottom: 25px;">2. Testimoni Pembeli</h3>
            
            <div style="background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); padding: 25px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 35px;">
                <form action="simpan_ulasan.php" method="POST">
                    <input type="hidden" name="id_game" value="<?php echo $id_g; ?>">
                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($g['slug']); ?>">
                    <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($nama_tampil); ?>">
                    
                    <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #fff;">Beri Rating:</label>
                    <div class="rating-stars" style="margin-bottom: 15px;">
                        <input type="radio" name="rating" value="5" id="star5" required><label for="star5">★</label>
                        <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                        <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                        <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                        <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                    </div>
                    <textarea name="komentar" placeholder="Gimana layanannya mprruy? Tulis di sini..." required style="width: 100%; height: 100px; border-radius: 12px; padding: 15px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color:#fff; resize: none;"></textarea>
                    <button type="submit" style="background: linear-gradient(135deg, #e5b82e, #c9a227); color:black; border:none; padding:12px 28px; border-radius:12px; cursor:pointer; margin-top:15px; font-weight:bold;">Kirim Testimoni</button>
                </form>
            </div>

            <div class="tz-review-filter-container" style="display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap;">
                <button class="tz-filter-btn active" onclick="filterReviewHalaman('semua', event)">Semua Rating</button>
                <?php for($i=5; $i>=1; $i--): ?>
                    <button class="tz-filter-btn" onclick="filterReviewHalaman(<?php echo $i; ?>, event)">★ <?php echo $i; ?></button>
                <?php endfor; ?>
            </div>

            <div id="mainReviewContainer" style="display:flex; flex-direction:column; gap:16px;">

            <?php
            $total_komentar = count($array_ulasan_js);

            if($total_komentar > 0):
                foreach($array_ulasan_js as $index => $rev):

                    // Hide review lebih dari 3
                    $is_hidden = ($index >= 3) ? 'display:none;' : '';

                    // Foto user
                    $foto_user = !empty($rev['foto_user_fix'])
                        ? 'uploads/' . $rev['foto_user_fix']
                        : 'uploads/Default.jpg';

                    // Nama user
                    $nama_user_review = $rev['user_name_fix']
                        ?? $rev['nama_user']
                        ?? 'Anonymous';

                    // Tanggal
                    $tanggal_review = !empty($rev['created_at'])
                        ? date('d M Y H:i', strtotime($rev['created_at']))
                        : 'Baru saja';

                    // Rating
                    $rating = (int)$rev['rating'];
            ?>

            <div class="rev-item real-review-card tz-anim-fade-in"
                data-rating="<?= $rating; ?>"
                style="<?= $is_hidden; ?>">

                <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    gap:15px;
                ">

                    <!-- LEFT -->
                    <div style="
                        display:flex;
                        align-items:center;
                        gap:14px;
                    ">

                        <!-- FOTO -->
                        <img
                            src="<?= htmlspecialchars($foto_user); ?>"
                            onerror="this.onerror=null;this.src='uploads/Default.jpg';"
                            style="
                                width:48px;
                                height:48px;
                                border-radius:50%;
                                object-fit:cover;

                                border:2px solid rgba(255,255,255,0.15);

                                box-shadow:
                                    0 4px 12px rgba(0,0,0,0.25);
                            "
                        >

                        <!-- INFO -->
                        <div>

                            <div style="
                                font-weight:700;
                                color:#fff;
                                font-size:15px;
                            ">
                                <?= htmlspecialchars($nama_user_review); ?>
                            </div>

                            <div style="
                                font-size:12px;
                                color:rgba(255,255,255,0.45);
                                margin-top:3px;
                            ">
                                <?= $tanggal_review; ?>
                            </div>

                        </div>
                    </div>

                    <!-- RATING -->
                    <div style="
                        color:#ffca08;
                        font-size:16px;
                        letter-spacing:2px;
                        white-space:nowrap;
                    ">
                        <?= str_repeat('★', $rating); ?>
                        <?= str_repeat('☆', 5 - $rating); ?>
                    </div>

                </div>

                <!-- KOMENTAR -->
                <div style="
                    margin-top:16px;
                    padding-left:62px;

                    color:rgba(255,255,255,0.88);

                    line-height:1.7;
                    font-size:14px;
                    word-break:break-word;
                ">
                    <?= nl2br(htmlspecialchars($rev['komentar'])); ?>
                </div>

            </div>

            <?php
                endforeach;
            else:
            ?>

            <div style="
                text-align:center;
                padding:35px 20px;

                background:rgba(255,255,255,0.04);

                border-radius:16px;

                border:1px solid rgba(255,255,255,0.06);

                color:rgba(255,255,255,0.45);
            ">
                Belum ada ulasan mprruy 🔥
            </div>

            <?php endif; ?>

            <p id="noReviewText"
            style="
                    display:none;
                    text-align:center;
                    color:rgba(255,255,255,0.45);
                    padding:25px;
            ">
                Kosong mprruy, belum ada rating segini!
            </p>

            </div>

            <!-- BUTTON LIHAT SEMUA -->
            <div id="tzBtnViewAllContainer"
                style="
                    text-align:center;
                    margin-top:25px;

                    display: <?= ($total_komentar > 3) ? 'block' : 'none'; ?>;
            ">

                <button
                    type="button"
                    class="tz-btn-view-all-reviews"
                    onclick="bukaModalSemuaReview()"
                    style="
                        background:rgba(255,255,255,0.08);

                        backdrop-filter:blur(16px);
                        -webkit-backdrop-filter:blur(16px);

                        border:1px solid rgba(255,255,255,0.12);

                        color:white;

                        padding:14px 24px;

                        border-radius:14px;

                        cursor:pointer;

                        font-weight:600;

                        transition:0.25s;
                    "

                    onmouseover="
                        this.style.transform='translateY(-2px)';
                        this.style.borderColor='rgba(255,215,0,0.3)';
                    "

                    onmouseout="
                        this.style.transform='translateY(0px)';
                        this.style.borderColor='rgba(255,255,255,0.12)';
                    "
                >
                    Lihat Semua Ulasan (<?= $total_komentar; ?>)
                </button>

            </div>
        </div>
    </div>

    <div class="side-buy">
        <div class="sticky-card glass-panel">
            <h3 style="margin-top:0; border-bottom: 2px solid rgba(255,255,255,0.15); padding-bottom: 10px;">Detail Pesanan</h3>
            
            <label style="font-size: 13px; font-weight: bold; margin-top: 20px; margin-bottom: 8px; display: block;">Lengkapi Data Akun:</label>
            
            <div id="dynamic-inputs" class="dynamic-field-wrapper">
                <?php 
                // =======================================================
                // LOGIKA DETEKSI INPUT JIKA KUSTOM GLOBAL AKTIF
                // =======================================================
                if (($g['tipe_input'] ?? '') === 'kustom_global' && !empty($g['target_input_kustom'])): 
                    $fields = explode(',', $g['target_input_kustom']);
                    $is_single = (count($fields) === 1);
                    
                    // Beri kelas 'single-field' jika 1 input, atau 'multi-field' jika banyak input (ke samping 2)
                    $grid_class = $is_single ? 'single-field' : 'multi-field';
                ?>
                    <div class="input-grid-container <?= $grid_class; ?>">
                        <?php foreach ($fields as $index => $field_name): 
                            $field_trimmed = trim($field_name); 
                        ?>
                            <input type="text" 
                                   id="custom_field_<?= $index; ?>" 
                                   placeholder="Masukkan <?= htmlspecialchars($field_trimmed); ?>" 
                                   class="form-input custom-dynamic-field" 
                                   data-label="<?= htmlspecialchars($field_trimmed); ?>">
                        <?php endforeach; ?>
                    </div>

                <?php 
                // =======================================================
                // RESET KE LAYOUT BAWAAN LAMA / DEFAULT DATA UMUM
                // =======================================================
                elseif (strpos($gn_check, 'roblox') !== false): ?>
                    <div id="roblox-fields-login">
                        <input type="text" id="rblx_user" placeholder="Username Roblox" class="form-input" style="margin-bottom: 10px;">
                        <input type="password" id="rblx_pass" placeholder="Password Roblox" class="form-input" style="margin-bottom: 10px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-bottom: 5px;">
                            <input type="text" id="bc1" placeholder="Code 1" class="form-input">
                            <input type="text" id="bc2" placeholder="Code 2" class="form-input">
                            <input type="text" id="bc3" placeholder="Code 3" class="form-input">
                        </div>
                        <small style="color: gold; font-size: 12px; display: block; margin-bottom: 10px;">*Wajib sertakan backup codes mprruy!</small>
                    </div>
                    <div id="roblox-fields-5hari" style="display:none;">
                        <input type="text" id="rblx_id_only" placeholder="Username / Profile Link" class="form-input">
                    </div>

                <?php elseif (strpos($gn_check, 'genshin') !== false): ?>
                    <input type="number" id="uid_genshin" placeholder="Masukkan UID" class="form-input" style="margin-bottom: 10px;">
                    <select id="server_genshin" class="form-input">
                        <option value="Asia">Server Asia</option>
                        <option value="America">Server America</option>
                        <option value="Europe">Server Europe</option>
                        <option value="TW_HK_MO">Server TW/HK/MO</option>
                    </select>

                <?php else: ?>
                    <input type="text" id="general_user_id" placeholder="Masukkan ID Akun" class="form-input">
                <?php endif; ?>
            </div>

            <label style="font-size: 13px; font-weight: bold; margin-top: 10px; display: block;">Jumlah Pembelian:</label>
            <div class="qty-control">
                <button class="qty-btn" onclick="adjustQty(-1)">-</button>
                <input type="number" id="qty_val" class="qty-input" value="<?php echo $qty_cart; ?>" readonly>
                <button class="qty-btn" onclick="adjustQty(1)">+</button>
            </div>

            <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #ccc;">
                    <span>Produk:</span>
                    <span id="selected-product-name">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <span style="font-weight: bold;">Total:</span>
                    <span id="total-price-display" style="font-size: 22px; color: white; font-weight: 800;">Rp 0</span>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-cart" onclick="addToCart()">🛒</button>
                <button type="button" class="btn-buy-now" onclick="submitOrder()">Beli Sekarang</button>
            </div>
            
            <p style="font-size: 12px; color: gold; text-align: center; margin-top: 15px; margin-bottom: 0;">
                Layanan aktif 24 Jam. Proses otomatis & aman 100%.
            </p>
        </div>
    </div>
</div>
<div id="tzMprruyPopupReview" class="tz-popup-overlay-fixed" onclick="tutupModalSemuaReview(event)">
    <div class="tz-popup-box-main" onclick="event.stopPropagation()">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin:0;">Semua Ulasan</h3>
            <button onclick="tutupModalSemuaReview()" style="background:rgba(255,255,255,0.1); border:none; color:white; width:35px; height:35px; border-radius:50%; cursor:pointer;">&times;</button>
        </div>

        <div class="tz-review-filter-container" style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
            <button class="tz-filter-btn active" onclick="filterReviewDiDalamModal('semua', event)">Semua</button>
            <button class="tz-filter-btn" onclick="filterReviewDiDalamModal(5, event)">★ 5</button>
            <button class="tz-filter-btn" onclick="filterReviewDiDalamModal(4, event)">★ 4</button>
            <button class="tz-filter-btn" onclick="filterReviewDiDalamModal(3, event)">★ 3</button>
            <button class="tz-filter-btn" onclick="filterReviewDiDalamModal(2, event)">★ 2</button>
            <button class="tz-filter-btn" onclick="filterReviewDiDalamModal(1, event)">★ 1</button>
        </div>

        <div id="tzPopupReviewInjectionArea" class="tz-popup-scroll-wrapper"></div>
    </div>
</div>
<script>
    let currentSelectedProduct = null;
    let basePrice = 0;
    let currentQuantity = <?php echo $qty_cart; ?>;
    let robloxTabMode = 'login'; 

    window.DATA_TESTIMONI_GAME = <?php echo json_encode($array_ulasan_js ?? []); ?>;
    // Fungsi Toast dengan Efek Liquid Glass Premium & Minimalis
    function tampilkanToast(ikon, pesan) {
        Swal.fire({
            icon: ikon,
            title: pesan,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500, // Durasi tayang 2.5 detik
            timerProgressBar: true,
            background: 'rgba(255, 255, 255, 0.08)', // Efek Transparan Kaca mprruy
            color: '#ffffff',
            html: `
                <style>
                    /* Styling khusus biar dapet efek blur kaca tembus pandang */
                    .swal2-toast.swal2-show {
                        backdrop-filter: blur(12px) !important;
                        -webkit-backdrop-filter: blur(12px) !important;
                        border: 1px solid rgba(255, 255, 255, 0.15) !important;
                        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3) !important;
                        border-radius: 12px !important;
                        padding: 12px 20px !important;
                    }
                    /* Merapikan posisi text mprruy */
                    .swal2-toast .swal2-title {
                        font-family: 'Poppins', sans-serif !important;
                        font-size: 14px !important;
                        font-weight: 500 !important;
                        margin-left: 8px !important;
                        color: #ffffff !important;
                    }
                    /* Mengubah garis durasi menjadi neon tipis estetik */
                    .swal2-toast .swal2-timer-progress-bar {
                        background: linear-gradient(90deg, #00f2fe 0%, #4facfe 100%) !important;
                        height: 3px !important;
                        border-radius: 0 0 12px 12px !important;
                    }
                </style>
            `,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const paramProduk = <?php echo json_encode($selected_produk); ?>;
        if (paramProduk && paramProduk.trim() !== '') {
            const cards = document.querySelectorAll('.produk-item, .item-card');
            let foundCard = null;
            
            cards.forEach(card => {
                const cardName = card.getAttribute('data-name');
                if (cardName && cardName.toLowerCase().includes(paramProduk.toLowerCase())) {
                    foundCard = card;
                }
            });
            
            if (foundCard) {
                const tipeProduk = foundCard.getAttribute('data-tipe');
                if (tipeProduk === 'roblox_5hari') {
                    toggleRobloxTab('5hari');
                    const updatedCards = document.querySelectorAll('.produk-item, .item-card');
                    updatedCards.forEach(card => {
                        const cardName = card.getAttribute('data-name');
                        if (cardName && cardName.toLowerCase().includes(paramProduk.toLowerCase())) {
                            card.click();
                        }
                    });
                } else {
                    if (tipeProduk === 'roblox_login') toggleRobloxTab('login');
                    foundCard.click();
                }
            }
        }
    });

    function selectProduct(element, price, name) {
        const cards = document.querySelectorAll('.item-card, .produk-item');
        cards.forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        
        currentSelectedProduct = name;
        basePrice = price;
        document.getElementById('selected-product-name').innerText = name;
        updatePriceDisplay();
    }

    function adjustQty(amount) {
        currentQuantity += amount;
        if (currentQuantity < 1) currentQuantity = 1;
        document.getElementById('qty_val').value = currentQuantity;
        updatePriceDisplay();
    }

    function updatePriceDisplay() {
        const total = basePrice * currentQuantity;
        const display = document.getElementById('total-price-display');
        if(display) display.innerText = "Rp " + total.toLocaleString('id-ID');
    }

    function toggleRobloxTab(mode) {
        robloxTabMode = mode;
        const filterKey = (mode === 'login') ? 'roblox_login' : 'roblox_5hari';
        
        const btnLogin = document.getElementById('btn-tab-login');
        const btn5Hari = document.getElementById('btn-tab-5hari');
        const fieldsLogin = document.getElementById('roblox-fields-login');
        const fields5Hari = document.getElementById('roblox-fields-5hari');

        if(btnLogin) btnLogin.classList.toggle('active', mode === 'login');
        if(btn5Hari) btn5Hari.classList.toggle('active', mode === '5hari');
        if(fieldsLogin) fieldsLogin.style.display = (mode === 'login' ? 'block' : 'none');
        if(fields5Hari) fields5Hari.style.display = (mode === '5hari' ? 'block' : 'none');

        document.querySelectorAll('.produk-item').forEach(el => {
            el.style.display = (el.getAttribute('data-tipe') === filterKey) ? 'flex' : 'none';
        });

        currentSelectedProduct = null;
        basePrice = 0;
        document.querySelectorAll('.item-card, .produk-item').forEach(c => c.classList.remove('selected'));
        document.getElementById('selected-product-name').innerText = '-';
        updatePriceDisplay();
    }

    /* ==========================================================================
       VALIDASI REUSABLE KONTROLLER DATA USER
       ========================================================================== */
    function ambilDanValidasiDataUser() {
        let userDataRaw = '';
        const tipeInputDatabase = "<?php echo $g['tipe_input'] ?? ''; ?>";
        const gameName = "<?php echo strtolower($g['nama_game'] ?? ''); ?>";

        if (tipeInputDatabase === 'kustom_global') {
            const dynamicFields = document.querySelectorAll('.custom-dynamic-field');
            let collectedData = [];
            
            dynamicFields.forEach(field => {
                const value = field.value.trim();
                const label = field.getAttribute('data-label');
                if (!value) throw `Kolom ${label} wajib diisi mprruy!`;
                collectedData.push(`${label}: ${value}`);
            });
            userDataRaw = collectedData.join(' | ');

        } else if (gameName.includes('roblox')) {
            if (robloxTabMode === 'login') {
                const u = document.getElementById('rblx_user').value.trim();
                const p = document.getElementById('rblx_pass').value.trim();
                const b1 = document.getElementById('bc1').value.trim();
                const b2 = document.getElementById('bc2').value.trim();
                const b3 = document.getElementById('bc3').value.trim();
                if(!u || !p) throw "Isi Username & Password Roblox lu!";
                userDataRaw = `Mode: Login | User: ${u} | Pass: ${p} | Codes: ${b1},${b2},${b3}`;
            } else {
                const idOnly = document.getElementById('rblx_id_only').value.trim();
                if(!idOnly) throw "Isi Username Roblox-nya!";
                userDataRaw = `Mode: 5 Hari | Target: ${idOnly}`;
            }
        } else if (gameName.includes('genshin')) {
            const uid = document.getElementById('uid_genshin').value.trim();
            const srv = document.getElementById('server_genshin').value;
            if(!uid) throw "UID Genshin Impact jangan kosong!";
            userDataRaw = `UID: ${uid} | Server: ${srv}`;
        } else {
            const inputGeneral = document.getElementById('general_user_id');
            if(!inputGeneral || !inputGeneral.value.trim()) throw "Data ID game jangan kosong!";
            userDataRaw = inputGeneral.value.trim();
        }

        return userDataRaw;
    }

    /* ==========================================================================
       FUNGSI MASUK KERANJANG
       ========================================================================== */
    function addToCart() {
        const isLogged = <?php echo isset($_SESSION['id_user']) ? 'true' : 'false'; ?>;
        if (!isLogged) {
            tampilkanToast('warning', 'Akun lu belum nyangkut bray, login dulu!');
            setTimeout(() => { window.location.href = "../Login/tampilanlogin.php"; }, 1500);
            return;
        }
        if (!currentSelectedProduct) {
            tampilkanToast('info', 'Pilih nominal top up dulu mprruy!');
            return;
        }

        let validatedUserData = '';
        try {
            validatedUserData = ambilDanValidasiDataUser();
        } catch (pesanError) {
            tampilkanToast('error', pesanError);
            return; 
        }

        const formData = new FormData();
        formData.append('id_game', "<?php echo $id_g; ?>"); 
        formData.append('nama_produk', currentSelectedProduct);
        formData.append('harga', basePrice);
        formData.append('qty', currentQuantity);
        formData.append('user_data', validatedUserData); 

        fetch('proses_keranjang.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            tampilkanToast('success', `${currentSelectedProduct} masuk keranjang!`);
            setTimeout(() => { window.location.reload(); }, 1500);
        })
        .catch(err => {
            tampilkanToast('error', 'Koneksi ruyam ruy!');
        });
    }

    /* ==========================================================================
       FUNGSI BELI LANGSUNG / CHECKOUT DIRECT
       ========================================================================== */
    function submitOrder() {
        const isLoggedIn = <?php echo isset($_SESSION['id_user']) ? 'true' : 'false'; ?>;
        if (!isLoggedIn) {
            tampilkanToast('info', 'Login dulu bray biar aman.');
            setTimeout(() => { window.location.href = "../Login/tampilanlogin.php"; }, 1500);
            return;
        }

        if (!currentSelectedProduct) {
            tampilkanToast('warning', 'Pilih nominalnya dulu mprruy.');
            return;
        }

        let userDataRaw = '';
        try {
            userDataRaw = ambilDanValidasiDataUser();
        } catch (pesanError) {
            tampilkanToast('error', pesanError);
            return;
        }

        const gameId = "<?php echo $id_g; ?>";
        const targetUrl = `Checkout/pembayaran.php?id_game=${gameId}&user=${encodeURIComponent(userDataRaw)}&produk=${encodeURIComponent(currentSelectedProduct)}&harga=${basePrice}&qty=${currentQuantity}`;
        
        tampilkanToast('success', 'Mengarahkan ke pembayaran...');
        setTimeout(() => { window.location.href = targetUrl; }, 1200);
    }
</script>

</body>
</html>