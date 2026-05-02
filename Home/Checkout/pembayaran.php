<?php
session_start();
include '../koneksi.php'; 

// 1. AMBIL DATA DARI URL
$id_game = $_GET['id_game'] ?? '';
$user_data = $_GET['user'] ?? '';
$nama_produk = $_GET['produk'] ?? 'Produk';
$harga_satuan = (int)($_GET['harga'] ?? 0);
$qty = (int)($_GET['qty'] ?? 1);

// 2. AMBIL DATA DARI DATABASE (Tabel 'games')
$query_game = mysqli_query($conn, "SELECT * FROM games WHERE id = '$id_game'");
$data_game = mysqli_fetch_assoc($query_game);

// 3. SET DATA DINAMIS
$nama_game = $data_game['nama_game'] ?? 'Game';
$nama_foto = $data_game['gambar'] ?? 'default.jpg';
$path_foto = "../" . $nama_foto; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout - TopZone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { display: flex; gap: 20px; padding: 20px; font-family: sans-serif; max-width: 1000px; margin: auto; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #fff; }
        .btn-pay { width: 100%; padding: 15px; background: #ff6a00; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-pay:hover { background: #e65c00; }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }
        .method-box { padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .method-box.active { border-color: #ff6a00; background: #fff9f5; }
    </style>
</head>
<body>

<div class="container">
    <div style="flex: 2;">
        <div class="card">
            <h2>Informasi Pesanan</h2>
            <div style="display: flex; gap: 20px; align-items: flex-start;">
                <!-- Foto Produk -->
                <img src="<?= $path_foto ?>" style="width: 90px; height: 90px; border-radius: 12px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                
                <div style="flex: 1;">
                    <h3 style="margin:0; font-size: 1.2em;"><?= htmlspecialchars($nama_produk) ?></h3>
                    <p style="margin:5px 0; color:#666; font-size: 0.9em;">Game: <?= htmlspecialchars($nama_game) ?></p>

                    <!-- Box Data Akun -->
                    <div style="background: #fdfdfd; padding: 15px; border-radius: 10px; margin-top: 15px; border: 1px solid #eee;">
                        <label style="font-size: 0.75em; color: #aaa; font-weight: bold; text-transform: uppercase; display: block; margin-bottom: 10px; letter-spacing: 1px;">Data Akun Terisi:</label>
                        
                        <div style="display: grid; gap: 8px;">
                            <?php 
                            $user_raw = $_GET['user'] ?? '';
                            // Kita pecah datanya berdasarkan tanda '|' yang kita buat di detail tadi
                            $data_list = explode('|', $user_raw); 

                            foreach ($data_list as $baris): 
                                if (empty(trim($baris))) continue; // Skip kalau datanya kosong
                                
                                // Cek apakah ada label (misal "User: admin")
                                if (strpos($baris, ':') !== false) {
                                    $pecah_label = explode(':', $baris, 2);
                                    $label = trim($pecah_label[0]);
                                    $value = trim($pecah_label[1]);
                                } else {
                                    $label = "Data";
                                    $value = trim($baris);
                                }
                            ?>
                                <div style="display: flex; justify-content: space-between; font-size: 14px; padding-bottom: 5px; border-bottom: 1px dashed #f0f0f0;">
                                    <span style="color: #888;"><?= htmlspecialchars($label) ?></span>
                                    <strong style="color: #333; font-family: 'Courier New', Courier, monospace;"><?= htmlspecialchars($value) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Input Hidden buat dikirim ke backend/Xendit -->
                    <input type="hidden" id="edit-user" value="<?= htmlspecialchars($user_raw) ?>">
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Metode Pembayaran</h2>
            <div id="xendit-box" class="method-box" onclick="pilihMetode('Xendit Payment (QRIS, VA, Dana, dll)')">
                <strong>Xendit Payment Gateway</strong><br>
                <small>Proses instan via QRIS, e-Wallet, & Virtual Account</small>
            </div>
        </div>
    </div>

    <div style="flex: 1;">
        <div class="card">
            <h2>Detail Pembayaran</h2>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Harga Satuan</span>
                <span>Rp <?= number_format($harga_satuan, 0, ',', '.') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Jumlah</span>
                <span>x<?= $qty ?></span>
            </div>
            <hr>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2em; margin-bottom: 15px;">
                <span>Total</span>
                <span style="color: #ff6a00;">Rp <?= number_format($harga_satuan * $qty, 0, ',', '.') ?></span>
            </div>

            <!-- Konfirmasi Pilihan -->
            <div id="box-konfirmasi" style="display: none; background: #fff9f5; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px dashed #ff6a00;">
                <span style="font-size: 0.85em; color: #666;">Metode yang dipilih:</span><br>
                <strong id="teks-metode" style="color: #ff6a00;">-</strong>
            </div>

            <button id="pay-button" class="btn-pay" disabled>Checkout & Bayar</button>
        </div>
    </div>
</div>

<script>
// Gabungkan semua fungsi onclick jadi satu mprruy biar gak bentrok
document.getElementById('pay-button').onclick = function() {
    const btn = this;
    
    // 1. Ambil data user lengkap dari input (ID Game / Akun)
    const dataUserLengkap = document.getElementById('edit-user').value;

    if (dataUserLengkap === "") {
        alert("Data pesanan tidak valid!");
        return;
    }

    // 2. Visual loading
    btn.innerText = "Processing...";
    btn.disabled = true;

    // 3. Kirim data ke PHP
    // Tips: id_user diambil langsung dari session PHP lewat script ini
    fetch('ambil_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'id_user': '<?= $_SESSION['id_user'] ?? 0 ?>', // Ini kunci biar id_user gak 0!
            'produk': '<?= $nama_produk ?? $_GET['produk'] ?>',
            'harga': '<?= $harga_satuan ?? $_GET['harga'] ?>',
            'qty': '<?= $qty ?? $_GET['qty'] ?>',
            'user': dataUserLengkap
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.invoice_url) {
            // Berhasil: Lempar ke halaman pembayaran Xendit
            window.location.href = data.invoice_url;
        } else {
            // Gagal: Kasih tau alasannya (misal: Secret Key salah)
            alert("Gagal: " + (data.message || "Unknown error"));
            btn.innerText = "Checkout & Bayar";
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert("Gagal konek ke server!");
        btn.innerText = "Checkout & Bayar";
        btn.disabled = false;
    });
};

// Fungsi untuk memilih metode (UI saja)
function pilihMetode(nama) {
    const boxKonfirmasi = document.getElementById('box-konfirmasi');
    const teksMetode = document.getElementById('teks-metode');
    const xenditBox = document.getElementById('xendit-box');
    const btn = document.getElementById('pay-button');

    if(boxKonfirmasi) boxKonfirmasi.style.display = 'block';
    if(teksMetode) teksMetode.innerText = nama;
    if(xenditBox) xenditBox.classList.add('active');

    btn.disabled = false;
}
</script>

</body>
</html>