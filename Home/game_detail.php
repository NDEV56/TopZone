<?php
/**
 * TOPZONE - Game Topup Detail Page (Fixed Layout Layout Symmetry)
 * Author: Gemini AI
 * Date: 2026-05-16
 */

require_once __DIR__ . '/_security.php';
tz_security_init();

// ==========================================
// 1. AMBIL DATA GAME BERDASARKAN SLUG (PREPARED)
// ==========================================
$slug_raw = (string)($_GET['game'] ?? '');
$slug = preg_replace('/[^a-z0-9\-]/i', '', substr($slug_raw, 0, 64));
if ($slug === '') {
    http_response_code(404);
    die("Game tidak ditemukan. <a href='index.php'>Home</a>");
}

$g = tz_db()->fetchOne('SELECT * FROM games WHERE slug = ? LIMIT 1', [$slug]);
if (!$g) {
    http_response_code(404);
    die("Game tidak ditemukan. <a href='index.php'>Home</a>");
}

$id_g = (int)$g['id'];

// ==========================================
// 2. STATISTIK RATING & ULASAN (PREPARED)
// ==========================================
$q_avg = mysqli_query($koneksi, "SELECT AVG(rating) as rata_rata, COUNT(id) as total_review FROM reviews WHERE id_game = '$id_g'");
$res_avg = mysqli_fetch_assoc($q_avg);

$rating_rata  = ($res_avg['total_review'] > 0) ? round((float)$res_avg['rata_rata'], 1) : 0;
$total_review = (int)$res_avg['total_review'];
$terjual      = (int)($g['terjual'] ?? 0);

// ==========================================
// 3. DAFTAR ULASAN (PREPARED)
// ==========================================
$reviews = tz_db()->fetchAll(
    'SELECT * FROM reviews WHERE id_game = ? ORDER BY id DESC LIMIT 50',
    [$id_g]
);

// Backward compat: kode di bawah masih pakai $q_rev → buat iterator dari array
class TZ_ReviewIter implements \Iterator {
    private array $items;
    private int   $idx = 0;
    public function __construct(array $items) { $this->items = $items; }
    public function current(): mixed { return $this->items[$this->idx] ?? false; }
    public function key(): mixed     { return $this->idx; }
    public function next(): void     { $this->idx++; }
    public function rewind(): void   { $this->idx = 0; }
    public function valid(): bool    { return $this->idx < count($this->items); }
}
// Tetap pakai mysqli_fetch_assoc-compatible loop di template:
function tz_review_fetch(&$state) {
    if ($state === null) return false;
    if (!isset($state['i'])) $state['i'] = 0;
    if ($state['i'] >= count($state['rows'])) return false;
    return $state['rows'][$state['i']++];
}
$q_rev = ['rows' => $reviews];

// ==========================================
// 4. IDENTITAS USER (SESSION/GUEST)
// ==========================================
$nama_tampil = (string)($_SESSION['nama_user'] ?? "User" . random_int(100, 999));

$selected_produk = $_GET['select_produk'] ?? '';
$qty_cart = $_GET['qty'] ?? 1;
$from_cart = $_GET['from_cart'] ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Up <?= tz_e($g['nama_game']) ?> - TOPZONE OFFICIAL</title>
    
    <style>
        :root {
            --primary: #ff4d4d;
            --primary-hover: #e04343;
            --dark: #333;
            --bg: #f4f7f9;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        body { 
            background: linear-gradient(135deg, #050e2e, #1205a5, #050e2e);
            display: flex; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            color: #white; 
            line-height: 1.6;
        }

        /* Container Layout */
        .container { 
            max-width: 1200px; 
            margin: 40px auto; 
            display: grid;
            grid-template-columns: 7fr 4fr; /* Mengunci rasio perbandingan lebar kiri dan kanan */
            gap: 30px; 
            padding: 0 20px; 
            width: 100%;
            box-sizing: border-box;
        }

        /* Glassmorphism Shared Style */
        .glass-panel {
            color: #fff;
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(255,255,255,0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.3); 
            padding: 30px; 
            border-radius: 20px; 
            box-sizing: border-box;
        }

        .main-info { 
            width: 100%;
        }

        .tp-img { 
            width: 120px; 
            height: 120px; 
            background-size: cover; 
            background-position: center; 
            border-radius: 15px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Grid Produk - Disamakan ukurannya agar tidak mengecil saat item sedikit */
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
            min-height: 90px; /* Menjaga tinggi card produk tetap seragam */
        }
        .item-card:hover { 
            border-color: var(--primary); 
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,77,77,0.1);
        }
        .item-card.selected { 
            border: 2.5px solid var(--primary); 
            background: #fff0f0; 
            position: relative;
        }
        .item-card.selected::after {
            content: "✓";
            position: absolute;
            top: 5px;
            right: 10px;
            color: var(--primary);
            font-weight: bold;
        }

        .price { 
            color: var(--primary); 
            font-weight: 800; 
            font-size: 16px; 
            margin-top: 8px; 
        }

        /* Sidebar Kanan */
        .side-buy { 
            position: sticky; 
            top: 40px;
            width: 100%;
        }

        /* Form Input Unified */
        .form-input { 
            width: 100%; 
            padding: 13px; 
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(255,255,255,0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.3);
            border-radius: 12px; 
            font-size: 14px; 
            box-sizing: border-box; 
            margin-bottom: 15px; 
            color: #fff;
            transition: 0.3s;
        }
        .form-input:focus { border-color: gold; outline: none; }
        .form-input::placeholder { color: #ccc; }

        /* Tabs System */
        .tab-container { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { 
            flex: 1; 
            padding: 12px; 
            border-radius: 10px; 
            cursor: pointer; 
            text-align: center; 
            font-weight: bold; 
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(251, 255, 0, 0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 8px rgba(0, 0, 0, 0.4), inset 0 0 0 1px rgba(255, 217, 0, 0.3); 
            font-size: 13px; 
            color: #fff;
            transition: 0.3s;
        }
        .tab-btn.active { 
            background: #c9a227; 
            color: #000; 
            border-color: gold; 
        }

        /* Dynamic wrapper untuk mencegah layout 'anjing' / collapse jumping saat tab diganti */
        .dynamic-field-wrapper {
            min-height: 140px; /* Mengunci ruang kosong agar tinggi sidebar kanan stabil */
        }

        /* Qty Control */
        .qty-control { 
            display: flex; 
            align-items: center; 
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(255,255,255,0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.3);
            border-radius: 12px; 
            width: fit-content; 
            overflow: hidden; 
            margin: 15px 0;
        }
        .qty-btn { color: #fff; width: 40px; height: 40px; border: none; background: transparent; cursor: pointer; font-size: 18px; transition: 0.2s;}
        .qty-btn:hover { background: rgba(255,255,255,0.2); }
        .qty-input { width: 60px; color: #fff; text-align: center; border: none; font-weight: bold; font-size: 16px; background: transparent;}
        .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        /* Star Rating Form */
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 35px; color: #ddd; cursor: pointer; }
        .rating-stars input:checked ~ label, .rating-stars label:hover, .rating-stars label:hover ~ label { color: #ffca08; }

        /* Review Item */
        .rev-item { 
            background: rgba(255,255,255,0.05); 
            padding: 20px; 
            border-radius: 15px; 
            margin-bottom: 15px; 
            border-left: 5px solid #ffca08; 
            color: #fff;
        }

        /* Action Buttons */
        .btn-cart {
            width: 60px; height: 60px; 
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%); 
            border: 1px solid rgba(255,255,255,0.25);
            border-top-color: rgba(255,255,255,0.5);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.3);
            border-radius: 15px; 
            color: #007bff; 
            font-size: 24px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            transition: 0.3s;
        }
        .btn-cart:hover { background: #007bff; color: #fff; transform: scale(1.02);}

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
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .btn-buy-now:hover { background: #0056b3; transform: scale(1.02); }

        /* Responsive */
        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; }
            .side-buy { position: static; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="main-info glass-panel">
        <a href="index.php" class="btn-back-home"
                style="display: inline-flex;align-items: center;gap: 6px;padding: 8px 16px;margin-bottom: 16px;background:#333;color: #ffffff;border: 1.5px solid #ddd;border-radius: 8px;font-size: 13px;font-weight: 500;text-decoration: none;cursor: pointer;transition: all 0.2s ease;">
                 Kembali ke Home
        </a>
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="tp-img" style="background-image:url('<?= tz_attr($g['gambar']) ?>')"></div>
            <div>
                <h1 style="margin: 0; font-size: 28px;"><?= tz_e($g['nama_game']) ?></h1>
                <p style="color: #666; margin: 5px 0;">Kategori: <strong><?= tz_e($g['kategori']) ?></strong></p>
                <div style="color: #ffca08; font-size: 20px;">
                    <?php for($i=1; $i<=5; $i++) echo ($i <= $rating_rata) ? "★" : "☆"; ?>
                    <span style="color: #888; font-size: 15px;"> (<?php echo $rating_rata; ?>/5.0) | <?php echo $terjual; ?> Terjual</span>
                </div>
            </div>
        </div>

        <hr style="margin: 35px 0; border: 0; border-top: 1.5px solid rgba(255,255,255,0.15);">

        <h3>1. Pilih Nominal Top Up</h3>
        
        <?php 
        $gn_check = strtolower($g['nama_game']);
        if (strpos($gn_check, 'roblox') !== false): 
        ?>
            <div class="tab-container">
                <div class="tab-btn active" id="btn-tab-login" onclick="toggleRobloxTab('login')">Robux Via Login</div>
                <div class="tab-btn" id="btn-tab-5hari" onclick="toggleRobloxTab('5hari')">Robux 5 Hari (Tanpa Login)</div>
            </div>
        <?php endif; ?>

        <div class="item-grid" id="product-list">
            <?php 
            $q_produk = mysqli_query($koneksi, "SELECT * FROM produk_game WHERE id_game = '$id_g' ORDER BY harga ASC");
            if(mysqli_num_rows($q_produk) > 0): 
                while($p = mysqli_fetch_assoc($q_produk)): 
                    $tipe_p = $p['tipe'] ?? 'default'; 
            ?>
                <div class="item-card produk-item" 
                    data-tipe="<?= $tipe_p ?>" 
                    onclick="selectProduct(this, <?= $p['harga']; ?>, '<?= addslashes($p['nama_produk']); ?>')"
                    style="display: <?= ($gn_check == 'roblox' && $tipe_p != 'roblox_login') ? 'none' : 'flex' ?>;">
                    <div style="font-size: 14px; font-weight: 600; color: #ffffff;"><?= $p['nama_produk']; ?></div>
                    <div class="price">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></div>
                </div>
            <?php endforeach; ?>

            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #aaa;">
                    <p>Produk belum tersedia untuk game ini mprruy. 🙏</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 60px; border-top: 2px solid gold; padding-top: 30px;">
            <h3>2. Testimoni Pembeli</h3>
            
            <div style="background: rgba(255, 255, 255, 0.05); padding: 25px; border-radius: 15px; border: 1px solid rgba(251, 255, 0, 0.25); margin-bottom: 30px;">
                <form action="simpan_ulasan.php" method="POST">
                    <?= tz_csrf_field() ?>
                    <input type="hidden" name="id_game" value="<?= (int)$id_g ?>">
                    <input type="hidden" name="slug"    value="<?= tz_attr($g['slug']) ?>">

                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Beri Rating:</label>
                    <div class="rating-stars">
                        <input type="radio" name="rating" value="5" id="star5" required><label for="star5">★</label>
                        <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                        <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                        <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                        <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                    </div>

                    <textarea name="komentar" placeholder="Gimana layanannya mprruy? Tulis di sini..." required 
                              style="width: 100%; height: 100px; border-radius: 12px; padding: 15px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color:#fff; font-family: inherit; resize: none; box-sizing: border-box; margin-top: 10px;"></textarea>
                    <button type="submit" style="background:#c9a227; color:black; border:none; padding:12px 25px; border-radius:10px; cursor:pointer; margin-top:15px; font-weight:bold;">Kirim Testimoni</button>
                </form>
                <?php else: ?>
                <p style="color:#666;">Silakan <a href="../Login/tampilanlogin.php">login</a> dulu untuk memberi ulasan.</p>
                <?php endif; ?>
            </div>

            <div style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                <?php if (count($q_rev['rows']) > 0): ?>
                    <?php foreach ($q_rev['rows'] as $rev): ?>
                        <div class="rev-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong>👤 <?php echo htmlspecialchars($rev['user_name']); ?></strong>
                                <span style="font-size: 11px; color: #ccc;"><?php echo date('d M Y', strtotime($rev['created_at'] ?? 'now')); ?></span>
                            </div>
                            <div style="color: #ffca08; font-size: 14px; margin: 5px 0;">
                                <?php $rt = (int)$rev['rating']; for ($k=1; $k<=5; $k++) echo ($k <= $rt) ? "★" : "☆"; ?>
                            </div>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #eee;">
                                "<?php echo nl2br(htmlspecialchars($rev['komentar'])); ?>"
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #bbb; padding: 20px;">Belum ada ulasan. Jadilah yang pertama! 🔥</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="side-buy">
        <div class="sticky-card glass-panel">
            <h3 style="margin-top:0; border-bottom: 2px solid rgba(255,255,255,0.15); padding-bottom: 10px;">Detail Pesanan</h3>
            
            <label style="font-size: 13px; font-weight: bold; margin-top: 20px; margin-bottom: 8px; display: block;">Lengkapi Data Akun:</label>
            <div id="dynamic-inputs" class="dynamic-field-wrapper">
                
                <?php 
                $game_name_lower = strtolower($g['nama_game']);
                
                // KONDISI 1: ROBLOX
                if (strpos($game_name_lower, 'roblox') !== false): ?>
                    <div id="roblox-fields-login">
                        <input type="text" id="rblx_user" placeholder="Username Roblox" class="form-input">
                        <input type="password" id="rblx_pass" placeholder="Password Roblox" class="form-input">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px;">
                            <input type="text" id="bc1" placeholder="Code 1" class="form-input">
                            <input type="text" id="bc2" placeholder="Code 2" class="form-input">
                            <input type="text" id="bc3" placeholder="Code 3" class="form-input">
                        </div>
                        <small style="color: gold; font-size: 12px; display: block; margin-top: -5px;">*Wajib sertakan backup codes mprruy!</small>
                    </div>
                    <div id="roblox-fields-5hari" style="display:none;">
                        <input type="text" id="rblx_id_only" placeholder="Username / Profile Link" class="form-input">
                    </div>

                <?php 
                // KONDISI 2: MLBB
                elseif (strpos($game_name_lower, 'legend') !== false || strpos($game_name_lower, 'ml') !== false): ?>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="user_id" placeholder="User ID" style="flex: 2;" class="form-input">
                        <input type="text" id="zone_id" placeholder="(Zone)" style="flex: 1;" class="form-input">
                    </div>

                <?php 
                // KONDISI 3: GENSHIN
                elseif (strpos($game_name_lower, 'genshin') !== false): ?>
                    <input type="number" id="uid_genshin" placeholder="Masukkan UID" class="form-input">
                    <select id="server_genshin" class="form-input" style="color: #000;">
                        <option value="Asia">Server Asia</option>
                        <option value="America">Server America</option>
                        <option value="Europe">Server Europe</option>
                        <option value="TW_HK_MO">Server TW/HK/MO</option>
                    </select>

                <?php 
                // KONDISI 4: DEFAULT (FF, PUBG, DLL)
                else: ?>
                    <input type="text" id="general_user_id" placeholder="Masukkan ID Akun" class="form-input">
                <?php endif; ?>
            </div>

            <label style="font-size: 13px; font-weight: bold; margin-top: 10px; display: block;">Jumlah Pembelian:</label>
            <div class="qty-control">
                <button class="qty-btn" onclick="adjustQty(-1)">-</button>
                <input type="number" id="qty_val" class="qty-input" value="1" readonly>
                <button class="qty-btn" onclick="adjustQty(1)">+</button>
            </div>

            <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #ccc;">
                    <span>Produk:</span>
                    <span id="selected-product-name">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <span style="font-weight: bold;">Total:</span>
                    <span id="total-price-display" style="font-size: 22px; color: var(--primary); font-weight: 800;">Rp 0</span>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-cart" onclick="addToCart()">🛒</button>
                <button type="button" class="btn-buy-now" onclick="submitOrder()">⚡ Beli Sekarang</button>
            </div>
            
            <p style="font-size: 12px; color: gold; text-align: center; margin-top: 15px; margin-bottom: 0;">
                Layanan aktif 24 Jam. Proses otomatis & aman 100%.
            </p>
        </div>
    </div>
</div>

<script>
    let currentSelectedProduct = null;
    let basePrice = 0;
    let currentQuantity = 1;
    let robloxTabMode = 'login'; // 'login' atau '5hari'
    

    function selectProduct(element, price, name) {
        // Reset pilihan sebelumnya
        const cards = document.querySelectorAll('.item-card');
        cards.forEach(c => c.classList.remove('selected'));

        // Aktifkan pilihan baru
        element.classList.add('selected');
        
        // Simpan ke variabel global agar bisa dibaca fungsi submitOrder
        currentSelectedProduct = name;
        basePrice = price;

        // Update UI
        document.getElementById('selected-product-name').innerText = name;
        
        // Pastikan fungsi ini ada di script kamu mprruy
        if (typeof updatePriceDisplay === "function") {
            updatePriceDisplay();
        } else {
            // Fallback jika updatePriceDisplay belum dibuat
            const display = document.getElementById('total-price-display');
            if(display) display.innerText = "Rp " + price.toLocaleString('id-ID');
        }
    }

    function adjustQty(amount) {
        currentQuantity += amount;
        if (currentQuantity < 1) currentQuantity = 1;
        document.getElementById('qty_val').value = currentQuantity;
        updatePriceDisplay();
    }

    /**
     * Hitung & tampilkan total harga live
     */
    function updatePriceDisplay() {
        const total = basePrice * currentQuantity;
        document.getElementById('total-price-display').innerText = "Rp " + total.toLocaleString('id-ID');
    }

    function toggleRobloxTab(mode) {
        robloxTabMode = mode;
        const filterKey = (mode === 'login') ? 'roblox_login' : 'roblox_5hari';
        
        // 1. UI Button & Fields (Udah ada di kode lu)
        document.getElementById('btn-tab-login').classList.toggle('active', mode === 'login');
        document.getElementById('btn-tab-5hari').classList.toggle('active', mode === '5hari');
        document.getElementById('roblox-fields-login').style.display = (mode === 'login' ? 'block' : 'none');
        document.getElementById('roblox-fields-5hari').style.display = (mode === '5hari' ? 'block' : 'none');

        document.querySelectorAll('.produk-item').forEach(el => {
            el.style.display = (el.getAttribute('data-tipe') === filterKey) ? 'flex' : 'none';
        });

        // Reset pilihan produk kalau pindah tab biar gak salah harga
        currentSelectedProduct = null;
        basePrice = 0;
        document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
        document.getElementById('selected-product-name').innerText = '-';
        updatePriceDisplay();
    }

    function addToCart() {
        const isLogged = <?php echo isset($_SESSION['id_user']) ? 'true' : 'false'; ?>;
        
        if (!isLogged) {
            Swal.fire({
                icon: 'warning',
                title: 'LOGIN DULU!',
                text: 'Akun lu belum nyangkut mprruy.',
                background: '#1205a5',
                color: '#fff'
            }).then(() => { window.location.href = "../Login/tampilanlogin.php"; });
            return;
        }

        if (!currentSelectedProduct) {
            Swal.fire({ icon: 'info', title: 'INFO', text: 'Pilih produk dulu bray!', background: '#1205a5', color: '#fff' });
            return;
        }

        const idGameAsli = "<?php echo $id_g; ?>";
        // Persiapan data
        let formData = new FormData();
        // Nama field harus 'id_game' sesuai tabel di phpMyAdmin lu
        formData.append('id_game', idGameAsli); 
        formData.append('nama_produk', currentSelectedProduct);
        formData.append('harga', basePrice);
        formData.append('qty', currentQuantity);
        fetch('proses_keranjang.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(data => {
            Swal.fire({
                icon: 'success',
                title: 'BERHASIL',
                text: `${currentSelectedProduct} masuk keranjang!`,
                background: '#1205a5',
                color: '#fff'
            }).then(() => { window.location.reload(); });
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'ERROR', text: 'Koneksi ruyam!', background: '#1205a5', color: '#fff' });
        });
    }

    function submitOrder() {
        // 1. CEK LOGIN
        const isLoggedIn = <?php echo isset($_SESSION['id_user']) ? 'true' : 'false'; ?>;
        if (!isLoggedIn) {
            Swal.fire({
                icon: 'info',
                title: 'LOGIN DULU BRAY',
                text: 'Biar transaksinya aman & masuk histori.',
                background: '#1205a5',
                color: '#fff'
            }).then(() => { window.location.href = "../Login/tampilanlogin.php"; });
            return;
        }

        if (!currentSelectedProduct) {
            Swal.fire({ icon: 'warning', title: 'PILIH PRODUK!', text: 'Pilih nominalnya dulu mprruy.', background: '#1205a5', color: '#fff' });
            return;
        }

        // 3. NOTIF SURUH ISI DATA (VALIDASI INPUT)
        let userDataRaw = "";
        const gameName = <?= tz_js(strtolower((string)$g['nama_game'])) ?>;

        try {
            if (gameName.includes('roblox')) {
                if (robloxTabMode === 'login') {
                    const u = document.getElementById('rblx_user').value.trim();
                    const p = document.getElementById('rblx_pass').value.trim();
                    if(!u || !p) throw "Isi Username & Password Roblox lu mprruy!";
                    userDataRaw = `Mode: Login | User: ${u} | Pass: ${p}`;
                } else {
                    const idOnly = document.getElementById('rblx_id_only').value.trim();
                    if(!idOnly) throw "Isi Username Roblox-nya mprruy!";
                    userDataRaw = `Mode: 5 Hari | Target: ${idOnly}`;
                }
            } else if (gameName.includes('ml') || gameName.includes('legend')) {
                const id = document.getElementById('user_id').value.trim();
                const zone = document.getElementById('zone_id').value.trim();
                if(!id || !zone) throw "User ID & Zone ID MLBB wajib diisi!";
                userDataRaw = `ID: ${id} (${zone})`;
            } else {
                // Cek input general (untuk FF, PUBG, dll)
                const inputGeneral = document.getElementById('general_user_id');
                if(!inputGeneral || !inputGeneral.value.trim()) throw "Data akun/ID game jangan kosong mprruy!";
                userDataRaw = inputGeneral.value.trim();
            }
        } catch (pesanError) {
            Swal.fire({ icon: 'error', title: 'DATA KOSONG!', text: pesanError, background: '#1205a5', color: '#fff' });
            return;
        }

        // 4. GAS KE PEMBAYARAN
        const gameId = "<?php echo $id_g; ?>";
        // Pastikan variabel basePrice & currentQuantity ada nilainya
        const finalPrice = (typeof basePrice !== 'undefined') ? basePrice : 0;
        const finalQty = (typeof currentQuantity !== 'undefined') ? currentQuantity : 1;
        
        const targetUrl = `Checkout/pembayaran.php?id_game=${gameId}&user=${encodeURIComponent(userDataRaw)}&produk=${encodeURIComponent(currentSelectedProduct)}&harga=${finalPrice}&qty=${finalQty}`;
        
        window.location.href = targetUrl;
    }
    function cekLoginSebelumBeli() {
    // Asumsikan kamu menyimpan status login di variabel JS atau mengecek session PHP
    var isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    if (!isLoggedIn) {
        alert("Waduh! Login dulu yuk sebelum belanja.");
        window.location.href = "login.php"; // Arahkan ke halaman login
        return false;
    }
    
    // Jika sudah login, lanjut ke proses keranjang/beli
    document.getElementById("form-pembelian").submit();
    }
</script>

</body>
</html>