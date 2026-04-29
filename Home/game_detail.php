<?php
include 'koneksi.php'; 

// 1. AMBIL DATA GAME
$slug = $_GET['game'] ?? '';
$query = mysqli_query($conn, "SELECT * FROM games WHERE slug = '$slug'");
$g = mysqli_fetch_assoc($query);

if (!$g) { die("Game tidak ditemukan mprruy!"); }

$id_g = $g['id'];

// 2. HITUNG STATISTIK RATING
$q_avg = mysqli_query($conn, "SELECT AVG(rating) as rata_rata, COUNT(id) as total_review FROM reviews WHERE id_game = '$id_g'");
$res_avg = mysqli_fetch_assoc($q_avg);
$rating_rata = ($res_avg['total_review'] > 0) ? round($res_avg['rata_rata'], 1) : 0;
$total_review = $res_avg['total_review'];
$terjual = $g['terjual'] ?? 0;

// 3. AMBIL DAFTAR ULASAN DARI DATABASE
$q_rev = mysqli_query($conn, "SELECT * FROM reviews WHERE id_game = '$id_g' ORDER BY id DESC");

// 4. SESSION USER
session_start();
$nama_tampil = $_SESSION['nama_user'] ?? ($_COOKIE['guest_name'] ?? "User" . rand(100, 999));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Top Up <?php echo $g['nama_game']; ?> - TOPZONE</title>
    <style>
        body { background: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; color: #333; }
        .container { max-width: 1100px; margin: 40px auto; display: flex; gap: 30px; padding: 0 20px; align-items: flex-start; }
        
        /* Kolom Kiri */
        .main-info { flex: 2; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .tp-img { width: 120px; height: 120px; background-size: cover; background-position: center; border-radius: 15px; }

        /* Rating System (Input Bintang) */
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; margin: 10px 0; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 35px; color: #ddd; cursor: pointer; transition: 0.2s; }
        .rating-stars input:checked ~ label, .rating-stars label:hover, .rating-stars label:hover ~ label { color: #ffca08; }

        /* Item Cards */
        .item-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-top: 15px; }
        .item-card { border: 1.5px solid #eee; padding: 15px; border-radius: 12px; cursor: pointer; text-align: center; transition: 0.2s; }
        .item-card:hover { border-color: #ff4d4d; }
        .item-card.selected { border: 2px solid #ff4d4d; background: #fff0f0; }

        /* Kolom Kanan (Sidebar) */
        .side-buy { flex: 1; position: sticky; top: 20px; }
        .sticky-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .label-sm { font-size: 12px; font-weight: 700; color: #666; display: block; margin-bottom: 5px; text-transform: uppercase; }
        .form-input { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 10px; font-size: 14px; box-sizing: border-box; margin-bottom: 12px; }
        .form-input:focus { border-color: #ff4d4d; outline: none; }
        
        .qty-control { display: flex; align-items: center; border: 1.5px solid #ddd; border-radius: 10px; width: fit-content; overflow: hidden; margin-bottom: 20px; }
        .qty-btn { width: 35px; height: 35px; border: none; background: #f8f9fa; cursor: pointer; font-size: 18px; font-weight: bold; }
        .qty-input { width: 50px; text-align: center; border: none; font-weight: bold; }

        .btn-buy { width: 100%; background: #ff4d4d; color: white; border: none; padding: 15px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 16px; transition: 0.3s; }
        .btn-buy:hover { background: #e04343; }

        /* Roblox Tabs */
        .tab-container { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; text-align: center; font-weight: bold; background: white; font-size: 13px; }
        .tab-btn.active { background: #333; color: white; border-color: #333; }
        
        .info-icon { display: inline-block; width: 14px; height: 14px; background: #ccc; color: white; border-radius: 50%; text-align: center; font-size: 10px; font-style: normal; line-height: 14px; margin-left: 4px; }
        
        /* Review List Item */
        .rev-item { background: #fafafa; padding: 15px; border-radius: 12px; margin-bottom: 12px; border-left: 4px solid #ffca08; }

        /* Notifikasi Sukses */
        .toast-success {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2ecc71;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none; /* Sembunyi dulu */
            z-index: 9999;
            animation: slideIn 0.5s forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

    </style>
</head>
<body>

<div class="container">
    <div class="main-info">
        <a href="index.php" class="btn-back-home"
                style="display: inline-flex;align-items: center;gap: 6px;padding: 8px 16px;margin-bottom: 16px;background:#333;color: #ffffff;border: 1.5px solid #ddd;border-radius: 8px;font-size: 13px;font-weight: 500;text-decoration: none;cursor: pointer;transition: all 0.2s ease;">
                 Kembali ke Home
            </a>
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="tp-img" style="background-image:url('<?php echo $g['gambar']; ?>')"></div>
            <div>
                <h1 style="margin: 0; font-size: 24px;"><?php echo $g['nama_game']; ?></h1>
                <p style="color: #888; margin: 5px 0;">Kategori: <?php echo $g['kategori']; ?></p>
                <div style="color: #ffca08; font-size: 18px;">
                    <?php for($i=1; $i<=5; $i++) echo ($i <= $rating_rata) ? "★" : "☆"; ?>
                    <span style="color: #888; font-size: 14px;"> (<?php echo $rating_rata; ?>) | <?php echo $terjual; ?> Terjual</span>
                </div>
            </div>
        </div>

        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

        <h3>Pilih Produk 🔥</h3>
        <?php if (strtolower($g['nama_game']) == 'roblox'): ?>
            <div class="tab-container">
                <div class="tab-btn active" onclick="switchTab(this, 'login')">Robux Via Login</div>
                <div class="tab-btn" onclick="switchTab(this, '5hari')">Robux 5 Hari</div>
            </div>
            <div id="tab-login" class="item-grid">
                <div class="item-card" onclick="selectProduct(this, 14087, '80 Robux (Login)')"><strong>80 Robux</strong><div style="color:#ff4d4d">Rp 14.087</div></div>
                <div class="item-card" onclick="selectProduct(this, 68881, '400 Robux (Login)')"><strong>400 Robux</strong><div style="color:#ff4d4d">Rp 68.881</div></div>
            </div>
            <div id="tab-5hari" class="item-grid" style="display:none;">
                <div class="item-card" onclick="selectProduct(this, 120, '1 Robux (5 Hari)')"><strong>1 Robux</strong><div style="color:#ff4d4d">Rp 120</div></div>
            </div>
        <?php else: ?>
            <div class="item-grid">
                <div class="item-card" onclick="selectProduct(this, <?php echo $g['harga']; ?>, 'Paket Utama')">
                    <strong>Diamond/Item</strong>
                    <div style="color:#ff4d4d">Rp <?php echo number_format($g['harga']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 50px; border-top: 2px solid #f4f7f9; padding-top: 20px;">
            <h3>Kirim Testimoni Lu 🔥</h3>
            <form action="simpan_ulasan.php" method="POST">
                <input type="hidden" name="id_game" value="<?php echo $id_g; ?>">
                <input type="hidden" name="slug" value="<?php echo $g['slug']; ?>">
                <input type="hidden" name="user_name" value="<?php echo $nama_tampil; ?>">
                
                <div class="rating-stars">
                    <input type="radio" name="rating" value="5" id="star5" required><label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                </div>

                <textarea name="komentar" placeholder="Gimana layanannya mprruy? Tulis di sini..." required 
                          style="width: 100%; height: 100px; border-radius: 12px; padding: 15px; border: 1.5px solid #eee; font-family: inherit; resize: none; box-sizing: border-box;"></textarea>
                <button type="submit" style="background:#333; color:white; border:none; padding:12px 25px; border-radius:10px; cursor:pointer; margin-top:10px; font-weight:bold;">Kirim Ulasan</button>
            </form>
        </div>

        <div style="margin-top: 40px;">
            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Ulasan Pembeli (<?php echo $total_review; ?>)</h4>
            <?php if(mysqli_num_rows($q_rev) > 0): ?>
                <?php while($rev = mysqli_fetch_assoc($q_rev)): ?>
                    <div class="rev-item">
                        <div style="display: flex; justify-content: space-between;">
                            <strong style="font-size: 14px;">👤 <?php echo htmlspecialchars($rev['user_name'] ?? 'User'); ?></strong>
                            <span style="font-size: 11px; color: #999;"><?php echo date('d M Y', strtotime($rev['created_at'] ?? 'now')); ?></span>
                        </div>
                        <div style="color: #ffca08; font-size: 12px; margin: 5px 0;">
                            <?php for($k=1; $k<=5; $k++) echo ($k <= $rev['rating']) ? "★" : "☆"; ?>
                        </div>
                        <p style="margin: 0; font-size: 13px; color: #555; line-height: 1.5;">
                            <?php echo nl2br(htmlspecialchars($rev['komentar'])); ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #bbb; padding: 20px;">Belum ada ulasan mprruy.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="side-buy">
        <div class="sticky-card">
            <h3 style="margin-top:0; font-size: 18px;">Informasi Pesanan</h3>
            
            <div id="dynamic-inputs">
                <?php 
                $gn = strtolower($g['nama_game']);
                if (strpos($gn, 'roblox') !== false): ?>
                    <div id="roblox-login-fields">
                        <label class="label-sm">Data Login <i class="info-icon">i</i></label>
                        <input type="text" id="roblox_user" placeholder="Username/Email" class="form-input">
                        <label class="label-sm">Password <i class="info-icon">i</i></label>
                        <input type="password" id="roblox_pass" placeholder="Password" class="form-input">
                        <label class="label-sm">Backup Codes (Min. 3)</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" id="code1" placeholder="C1" class="form-input">
                            <input type="text" id="code2" placeholder="C2" class="form-input">
                            <input type="text" id="code3" placeholder="C3" class="form-input">
                        </div>
                    </div>
                    <div id="roblox-5hari-fields" style="display:none;">
                        <label class="label-sm">Username <i class="info-icon">i</i></label>
                        <input type="text" id="user_id_rblx" placeholder="Contoh: rblxitemku" class="form-input">
                    </div>
                <?php elseif (strpos($gn, 'genshin') !== false): ?>
                    <div style="display: flex; gap: 8px;">
                        <div style="flex: 2;"><label class="label-sm">User ID</label><input type="text" id="user_id" placeholder="UID" class="form-input"></div>
                        <div style="flex: 1.5;"><label class="label-sm">Server</label>
                            <select id="server_choice" class="form-input"><option value="Asia">Asia</option><option value="America">America</option></select>
                        </div>
                    </div>
                <?php elseif (strpos($gn, 'legend') !== false || strpos($gn, 'ml') !== false): ?>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="user_id" placeholder="User ID" style="flex: 2;" class="form-input">
                        <input type="text" id="zone_id" placeholder="Zone" style="flex: 1;" class="form-input">
                    </div>
                <?php else: ?>
                    <label class="label-sm">Player ID <i class="info-icon">i</i></label>
                    <input type="text" id="user_id" placeholder="Masukkan ID Akun" class="form-input">
                <?php endif; ?>
            </div>

            <label class="label-sm">Jumlah Beli</label>
            <div class="qty-control">
                <button class="qty-btn" onclick="changeQty(-1)">-</button>
                <input type="number" id="qty" class="qty-input" value="1" readonly>
                <button class="qty-btn" onclick="changeQty(1)">+</button>
            </div>

            <div style="border-top:1px dashed #eee; padding-top:15px; margin-bottom: 20px;">
                <span style="color:#888; font-size:13px;">Total Bayar</span>
                <div id="total-display" style="font-size:24px; color:#ff4d4d; font-weight:800;">Rp 0</div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 20px; align-items: center;">
                <button type="button" onclick="tambahKeKeranjang()" 
                    style="width: 55px; height: 55px; background: #ffffff; border: 2px solid #007bff; border-radius: 12px; color: #007bff; font-size: 22px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    🛒
                </button>

                <button type="button" onclick="prosesBeli()" 
                    style="flex-grow: 1; height: 55px; background: #007bff; color: white; border: none; border-radius: 12px; font-weight: bold; font-size: 16px; cursor: pointer;">
                    ⚡ Beli Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/** * PROTOKOL JS ANTI-PAOK 
 * Pastikan semua variabel global dideklarasikan di paling atas
 */
let paketTerpilih = null;
let selectedPrice = 0;
let currentQty = 1;

// 1. Fungsi Pilih Produk (Paket)
function selectProduct(el, price, name) {
    // Hapus class selected dari semua kartu dulu
    const allCards = document.querySelectorAll('.item-card');
    allCards.forEach(card => card.classList.remove('selected'));
    
    // Tambah class selected ke yang diklik
    el.classList.add('selected');
    
    // Simpan data ke variabel global
    selectedPrice = price;
    paketTerpilih = { 
        nama: name, 
        harga: price 
    };
    
    console.log("Produk dipilih:", paketTerpilih); // Buat cek di F12
    updateTotal();
}

// 2. Fungsi Update Total Harga (Live)
function updateTotal() {
    const display = document.getElementById('total-display');
    if (display) {
        let total = selectedPrice * currentQty;
        display.innerText = "Rp " + total.toLocaleString('id-ID');
    }
}

// 3. Fungsi Kontrol Jumlah (Qty)
function changeQty(val) {
    currentQty += val;
    if (currentQty < 1) currentQty = 1;
    
    const qtyInput = document.getElementById('qty');
    if (qtyInput) {
        qtyInput.value = currentQty;
    }
    updateTotal();
}

// 4. Fungsi Cek Login (Koneksi ke PHP Session)
function checkLogin() {
    // Kita ambil status login dari PHP
    const loginStatus = <?php echo isset($_SESSION['id_user']) ? 'true' : 'false'; ?>;
    return loginStatus;
}

// 5. Fungsi Masukin Keranjang (Core Logic)
function tambahKeKeranjang() {
    // Cek Login Dulu
    if (!checkLogin()) {
        alert("Woy mprruy, login dulu lah baru bisa belanja! 😅");
        window.location.href = "../Login/tampilanlogin.php";
        return;
    }

    // Cek Apakah Paket Sudah Dipilih
    if (!paketTerpilih) {
        alert("Pilih paketnya dulu mprruy, jangan asal klik! 🔥");
        return;
    }

    // Siapkan Data untuk Dikirim ke PHP
    let fd = new FormData();
    fd.append('nama_produk', paketTerpilih.nama);
    fd.append('harga', paketTerpilih.harga);
    fd.append('qty', currentQty);

    // Kirim Data pakai Fetch API (Biar gak reload halaman)
    fetch('proses_keranjang.php', {
        method: 'POST',
        body: fd
    })
    .then(response => response.text())
    .then(hasil => {
        console.log("Respon Server:", hasil);
        alert("Mantap! " + paketTerpilih.nama + " sukses masuk keranjang. 🔥");
        // Redirect ke home biar angka keranjang di header ke-update
        window.location.href = "index.php"; 
    })
    .catch(err => {
        console.error("Error Fetch:", err);
        alert("Waduh, koneksi ke database gagal mprruy!");
    });
}

// 6. Fungsi Beli Sekarang (Direct Action)
function prosesBeli() {
    if (!paketTerpilih) {
        alert("Pilih dulu produknya mprruy!");
        return;
    }
    alert("Sabar mprruy, fitur Beli Langsung lagi disiapin. Masukin keranjang dulu aja!");
}

</script>

</body>
</html>