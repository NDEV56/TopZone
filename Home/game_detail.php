<?php
// 1. KONEKSI HARUS PALING ATAS
include 'koneksi.php'; 

// 2. AMBIL DATA GAME BERDASARKAN SLUG
$slug = $_GET['game'] ?? '';
$query = mysqli_query($conn, "SELECT * FROM games WHERE slug = '$slug'");
$g = mysqli_fetch_assoc($query);

// Jika game gak ada, stop di sini
if (!$g) { die("Game tidak ditemukan mprruy!"); }

$id_g = $g['id'];

// 3. HITUNG STATISTIK (Rating Rata-rata & Total Terjual)
$q_avg = mysqli_query($conn, "SELECT AVG(rating) as rata_rata, COUNT(id) as total_review FROM reviews WHERE id_game = '$id_g'");
$res_avg = mysqli_fetch_assoc($q_avg);
$rating_rata = ($res_avg['total_review'] > 0) ? round($res_avg['rata_rata'], 1) : 0;
$total_review = $res_avg['total_review']; 

$terjual = $g['terjual'] ?? 0; // Ambil dari kolom terjual di tabel games

// 4. AMBIL LIST ULASAN UNTUK DITAMPILKAN DI BAWAH
$q_rev = mysqli_query($conn, "SELECT * FROM reviews WHERE id_game = '$id_g' ORDER BY created_at DESC");

// 5. URUSAN NAMA USER (Tetap sama di satu perangkat)
session_start();

if (isset($_SESSION['nama_user'])) {
    // Kalau dia login, pake nama dari akunnya
    $nama_tampil = $_SESSION['nama_user'];
} elseif (isset($_COOKIE['guest_name'])) {
    // Kalau dia guest tapi udah pernah dapet nama, pake nama dari Cookie
    $nama_tampil = $_COOKIE['guest_name'];
} else {
    // Kalau bener-bener baru pertama kali mampir, kasih nama random
    $nama_tampil = "User" . rand(100, 999);
    // Simpan di Cookie selama 1 tahun (3600 detik * 24 jam * 365 hari)
    setcookie('guest_name', $nama_tampil, time() + (3600 * 24 * 365), "/"); 
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Top Up <?php echo $g['nama_game']; ?></title>
    <style>
        /* CSS buat nampilin gambar mprruy */
        .tp-img {
            width: 150px;
            height: 150px;
            background-size: cover;
            background-position: center;
            border-radius: 20px; /* Biar ujungnya tumpul cakep */
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 3px solid white;
        } 

        body { background: #f4f7f9; font-family: sans-serif; margin: 0; }
        .container { max-width: 1100px; margin: 40px auto; display: flex; gap: 30px; padding: 0 20px; }
        .main-info { flex: 2; background: white; padding: 30px; border-radius: 15px; }
        .side-buy { flex: 1; }
        .sticky-card { position: sticky; top: 20px; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .comment-card { background: #fafafa; padding: 15px; border-radius: 10px; margin-bottom: 10px; border-left: 5px solid #ffca08; }
        .rating-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .rating-input input { display: none; }
        .rating-input label { font-size: 30px; color: #ddd; cursor: pointer; }
        .rating-input input:checked ~ label, .rating-input label:hover, .rating-input label:hover ~ label { color: #ffca08; }
    </style>
</head>
<body>

<div class="container">
    <div class="main-info">
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="tp-img" style="background-image:url('<?php echo $g['gambar']; ?>')"></div>
            
            <div>
                <h1 style="margin: 0;"><?php echo $g['nama_game']; ?></h1>
                <p style="color: #888; margin: 5px 0;">Kategori: <?php echo $g['kategori']; ?></p>
                <div style="color: #ffca08; font-size: 18px;">
                    <?php for($i=1; $i<=5; $i++) echo ($i <= $rating_rata) ? "★" : "☆"; ?>
                    <span style="color: #888; font-size: 14px;"> (<?php echo $rating_rata; ?>) | <?php echo $terjual; ?> Terjual</span>
                </div>
            </div>
        </div>
        

        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

        <div class="review-section">
            <h3>Kirim Ulasan Lu 🔥</h3>
            <form action="simpan_ulasan.php" method="POST">
                <input type="hidden" name="id_game" value="<?php echo $id_g; ?>">
                <input type="hidden" name="slug" value="<?php echo $g['slug']; ?>">
                <input type="hidden" name="nama_user" value="<?php echo $nama_tampil; ?>">

                <div class="rating-input">
                    <input type="radio" name="rating" id="s5" value="5" required><label for="s5">★</label>
                    <input type="radio" name="rating" id="s4" value="4"><label for="s4">★</label>
                    <input type="radio" name="rating" id="s3" value="3"><label for="s3">★</label>
                    <input type="radio" name="rating" id="s2" value="2"><label for="s2">★</label>
                    <input type="radio" name="rating" id="s1" value="1"><label for="s1">★</label>
                </div>

                <textarea name="komentar" placeholder="Tulis catatan lu mprruy..." required style="width:100%; height:80px; margin-top:10px; padding:10px;"></textarea>
                <button type="submit" style="background:#ff4d4d; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; margin-top:10px;">Kirim Testi</button>
            </form>

            <div style="margin-top: 30px;">
                <?php if(mysqli_num_rows($q_rev) > 0): ?>
                    <?php while($rev = mysqli_fetch_assoc($q_rev)): ?>
                        <div class="comment-card">
                            <strong>👤 <?php echo $rev['user_name']; ?></strong>
                            <div style="color: #ffca08; font-size: 12px;">
                                <?php for($k=1; $k<=$rev['rating']; $k++) echo "★"; ?>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($rev['komentar'])); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#888;">Belum ada ulasan.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="side-buy">
        <div class="sticky-card">
            <h3>Top Up Instan</h3>
            <div style="font-size: 24px; color: #ff4d4d; font-weight: bold; margin-bottom: 15px;">
                Rp <?php echo number_format($g['harga']); ?>
            </div>
            <input type="text" placeholder="ID Akun Lu..." style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:5px; box-sizing:border-box;">
            <button style="width:100%; background:#ff4d4d; color:white; border:none; padding:15px; border-radius:8px; font-weight:bold;">BELI SEKARANG</button>
        </div>
    </div>
</div>

</body>
</html>