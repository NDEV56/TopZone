<?php
session_start();
include '../koneksi.php'; 

// 1. AMBIL DATA DARI URL
$id_game = mysqli_real_escape_string($koneksi, $_GET['id_game'] ?? '');
$user_data_mentah = $_GET['user'] ?? '';
$nama_produk = $_GET['produk'] ?? 'Produk';
$harga_satuan = (int)($_GET['harga'] ?? 0);
$qty = (int)($_GET['qty'] ?? 1);

// 2. AMBIL DATA DARI DATABASE (Cek Game & Tipe-nya)
// Pastikan variabel koneksi lo bener ($koneksi atau $conn)
$query_game = mysqli_query($koneksi, "SELECT * FROM games WHERE id = '$id_game'");
$data_game = mysqli_fetch_assoc($query_game);

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
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-pay { width: 100%; padding: 15px; background: #ff6a00; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-pay:hover { background: #e65c00; }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }
        .method-box { padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .method-box.active { border-color: #ff6a00; background: #fff9f5; }
        .input-edit-data { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-top: 5px; }
        .group-input { margin-bottom: 12px; }
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

                    <!-- Box Edit Data Akun (Fungsi dari kode lama lo mprruy) -->
                    <div style="background: #fdfdfd; padding: 15px; border-radius: 10px; margin-top: 15px; border: 1px solid #eee;">
                        <label style="font-size: 0.75em; color: #aaa; font-weight: bold; text-transform: uppercase; display: block; margin-bottom: 10px; letter-spacing: 1px;">
                            Lengkapi / Edit Data Akun:
                        </label>
                        
                        <div id="container-input-data">
                            <?php 
                            // Pecah data berdasarkan '|' dan ':'
                            $data_list = explode('|', $user_data_mentah); 
                            foreach ($data_list as $baris): 
                                if (empty(trim($baris))) continue;
                                
                                if (strpos($baris, ':') !== false) {
                                    $pecah = explode(':', $baris, 2);
                                    $label = trim($pecah[0]);
                                    $value = trim($pecah[1]);
                                } else {
                                    $label = "Data";
                                    $value = trim($baris);
                                }
                            ?>
                                <div class="group-input">
                                    <span style="color: #888; font-size: 12px; font-weight: bold;"><?= $label ?></span>
                                    <input type="text" class="input-edit-data" data-label="<?= $label ?>" value="<?= htmlspecialchars($value) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
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
// Fungsi Ambil Data dari Input Dinamis (Ini yang lo mau mprruy)
document.getElementById('pay-button').onclick = function() {
    const btn = this;
    
    // 1. PROSES DATA DARI INPUT (Biar kalau user ganti username/pass, datanya ikut ke update)
    const allInputs = document.querySelectorAll('.input-edit-data');
    let dataArray = [];
    
    allInputs.forEach(input => {
        const label = input.getAttribute('data-label');
        const val = input.value.trim();
        if(val !== "") {
            dataArray.push(label + ": " + val);
        }
    });

    const dataUserTerupdate = dataArray.join(' | ');

    if (dataArray.length === 0) {
        alert("Data pesanan tidak boleh kosong mprruy!");
        return;
    }

    // 2. Visual loading
    btn.innerText = "Processing...";
    btn.disabled = true;

    // 3. Kirim ke PHP (ambil_token.php)
    fetch('ambil_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'id_user': '<?= $_SESSION['id_user'] ?? 0 ?>',
            'produk': '<?= $nama_produk ?>',
            'harga': '<?= $harga_satuan * $qty ?>', // Kirim total harga
            'qty': '<?= $qty ?>',
            'user': dataUserTerupdate // Kirim data yang udah diedit user
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.invoice_url) {
            window.location.href = data.invoice_url;
        } else {
            alert("Gagal: " + (data.message || "Error server"));
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

function pilihMetode(nama) {
    document.getElementById('box-konfirmasi').style.display = 'block';
    document.getElementById('teks-metode').innerText = nama;
    document.getElementById('xendit-box').classList.add('active');
    document.getElementById('pay-button').disabled = false;
}
</script>

</body>
</html>