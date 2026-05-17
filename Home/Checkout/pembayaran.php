<?php
session_start();
include '../koneksi.php'; 

// 1. AMBIL DATA DARI URL (Disamakan dengan parameter: id_game, paket, harga)
$id_game = mysqli_real_escape_string($koneksi, $_GET['id_game'] ?? '');
$user_data_mentah = $_GET['user'] ?? '';

// Menggunakan 'paket' sesuai dengan parameter yang dikirim dari halaman produk sebelumnya
$nama_produk = $_GET['paket'] ?? ($_GET['produk'] ?? 'Produk'); 
$harga_satuan = (int)($_GET['harga'] ?? 0);
$qty = (int)($_GET['qty'] ?? 1);

// 2. AMBIL DATA DARI DATABASE (Cek Game & Tipe-nya)
$nama_game = 'Game';
$path_foto = "../assets/img/default.jpg"; // Set default cadangan jika tidak ketemu

if (!empty($id_game)) {
    $query_game = mysqli_query($koneksi, "SELECT * FROM games WHERE id = '$id_game'");
    
    if ($query_game && mysqli_num_rows($query_game) > 0) {
        $data_game = mysqli_fetch_assoc($query_game);
        $nama_game = $data_game['nama_game'] ?? 'Game';
        
        // Menggunakan kolom 'gambar' sesuai dengan struktur tabel games kamu bray
        $nama_foto = $data_game['gambar'] ?? 'default.jpg'; 
        
        // Sesuaikan path folder gambar kamu
        $path_foto = "../" . $nama_foto; 
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Premium - TopZone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="style.css">
    
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.07);
            --glass-border: rgba(255, 255, 255, 0.15);
            --glass-hover-border: rgba(255, 215, 0, 0.4);
            --neon-accent: #ff6a00;
            --neon-glow: #ffc800;
            --text-light: #f4f7f9;
        }

        body { 
            background: linear-gradient(135deg, #050e2e, #1205a5, #050e2e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex; 
            align-items: center;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            color: var(--text-light); 
            line-height: 1.6;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container { 
            display: flex; 
            gap: 25px; 
            padding: 30px 20px; 
            max-width: 1140px; 
            margin: auto; 
            width: 100%;
            box-sizing: border-box;
        }

        /* Premium Liquid Glass Card */
        .card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border); 
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            padding: 25px; 
            border-radius: 20px; 
            margin-bottom: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3), inset 0 0 0 1px rgba(255,255,255,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        h2 {
            margin-top: 0;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 12px;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2 i {
            color: var(--neon-accent);
        }

        /* Glass Form Inputs */
        .input-edit-data { 
            width: 100%; 
            padding: 12px 15px; 
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--glass-border); 
            border-radius: 12px; 
            margin-top: 6px; 
            color: #fff;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        
        .input-edit-data:focus { 
            outline: none;
            border-color: var(--neon-glow);
            box-shadow: 0 0 15px rgba(255, 106, 0, 0.3);
            background: rgba(0, 0, 0, 0.4);
        }

        .group-input { margin-bottom: 16px; }

        /* Liquid Interactive Payment Box */
        .method-box { 
            padding: 20px; 
            background: rgba(255,255,255,0.03);
            border: 2px solid var(--glass-border); 
            border-radius: 15px; 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .method-box::before {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90% , transparent, rgba(255,255,255,0.08), transparent);
            transition: 0.5s;
        }

        .method-box:hover::before { left: 100%; }

        .method-box:hover { 
            border-color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.06);
        }

        .method-box.active { 
            border-color: var(--neon-accent); 
            background: rgba(255, 106, 0, 0.1);
            box-shadow: 0 0 20px rgba(255, 106, 0, 0.2);
        }

        /* Luxury Checkout Button */
        .btn-pay { 
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, var(--neon-accent), #e65c00); 
            color: #fff; 
            border: none; 
            border-radius: 14px; 
            font-weight: bold; 
            font-size: 1rem;
            cursor: pointer; 
            box-shadow: 0 5px 15px rgba(255, 106, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        
        .btn-pay:hover:not(:disabled) { 
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(255, 106, 0, 0.5);
            background: linear-gradient(135deg, #ff7b1a, #ff5500);
        }
        
        .btn-pay:active:not(:disabled) { transform: scale(0.98); }
        .btn-pay:disabled { opacity: 0.4; cursor: not-allowed; box-shadow: none; }

        /* Confirmation Alert Styling */
        #box-konfirmasi { 
            background: rgba(255, 106, 0, 0.08); 
            padding: 15px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            border: 1px dashed var(--neon-accent); 
        }

        hr { border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0; }

        /* Responsive Layout Grid */
        @media (max-width: 900px) {
            .container { flex-direction: column; padding: 15px; }
            body { align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container animate__animated animate__fadeIn">
    <div style="flex: 2; width: 100%;">
        <div class="card">
            <h2><i class="fa-solid : fa-gamepad"></i> Informasi Pesanan</h2>
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <img src="<?= $path_foto ?>" style="width: 100px; height: 100px; border-radius: 16px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 8px 20px rgba(0,0,0,0.3);">
                
                <div style="flex: 1; min-width: 200px;">
                    <h3 style="margin:0; font-size: 1.35em; color: #fff; font-weight: 700;"><?= htmlspecialchars($nama_produk) ?></h3>
                    <p style="margin:6px 0 0 0; color: rgba(255,255,255,0.6); font-size: 0.95em;">
                        <i class="fa-solid fa-layer-group" style="margin-right: 5px; color: var(--neon-glow);"></i> Game: <strong><?= htmlspecialchars($nama_game) ?></strong>
                    </p>

                    <div style="background: rgba(0,0,0,0.15); padding: 18px; border-radius: 14px; margin-top: 20px; border: 1px solid rgba(255,255,255,0.05);">
                        <label style="font-size: 0.75em; color: var(--neon-glow); font-weight: 800; text-transform: uppercase; display: block; margin-bottom: 12px; letter-spacing: 1.5px;">
                            <i class="fa-solid fa-user-gear"></i> Konfirmasi / Edit Data Akun:
                        </label>
                        
                        <div id="container-input-data">
                            <?php 
                            $acc_json = json_decode($user_data_mentah, true); 
                            
                            if (is_array($acc_json)) {
                                foreach ($acc_json as $label => $value): 
                            ?>
                                    <div class="group-input">
                                        <span style="color: rgba(255,255,255,0.5); font-size: 12px; font-weight: bold; text-transform: capitalize;"><?= htmlspecialchars($label) ?></span>
                                        <input type="text" class="input-edit-data" data-label="<?= htmlspecialchars($label) ?>" value="<?= htmlspecialchars($value) ?>">
                                    </div>
                            <?php 
                                endforeach;
                            } else {
                                $data_list = explode('|', $user_data_mentah); 
                                foreach ($data_list as $baris): 
                                    if (empty(trim($baris))) continue;
                                    
                                    if (strpos($baris, ':') !== false) {
                                        $pecah = explode(':', $baris, 2);
                                        $label = trim($pecah[0]);
                                        $value = trim($pecah[1]);
                                    } else {
                                        $label = "Data Akun";
                                        $value = trim($baris);
                                    }
                            ?>
                                    <div class="group-input">
                                        <span style="color: rgba(255,255,255,0.5); font-size: 12px; font-weight: bold; text-transform: capitalize;"><?= htmlspecialchars($label) ?></span>
                                        <input type="text" class="input-edit-data" data-label="<?= htmlspecialchars($label) ?>" value="<?= htmlspecialchars($value) ?>">
                                    </div>
                            <?php 
                                endforeach; 
                            } 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-credit-card"></i> Metode Pembayaran</h2>
            <div id="xendit-box" class="method-box" onclick="pilihMetode('Xendit Payment (QRIS, VA, Dana, dll)')">
                <div style="background: rgba(255,106,0,0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--neon-accent); font-size: 1.3rem;">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <strong style="color: #fff; font-size: 1rem;">Xendit Payment Gateway</strong><br>
                    <small style="color: rgba(255,255,255,0.5); font-size: 0.85rem;">Proses instan otomatis via QRIS, e-Wallet, & Virtual Account</small>
                </div>
            </div>
        </div>
    </div>

    <div style="flex: 1; width: 100%;">
        <div class="card" style="position: sticky; top: 20px;">
            <h2><i class="fa-solid fa-receipt"></i> Detail Pembayaran</h2>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: rgba(255,255,255,0.7);">
                <span>Harga Satuan</span>
                <span style="color: #fff; font-weight: 600;">Rp <?= number_format($harga_satuan, 0, ',', '.') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: rgba(255,255,255,0.7);">
                <span>Jumlah</span>
                <span style="color: #fff; font-weight: 600;">x<?= $qty ?></span>
            </div>
            <hr>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.25em; margin-bottom: 20px; align-items: center;">
                <span>Total Tagihan</span>
                <span style="color: #fff; text-shadow: 0 0 10px rgba(255,106,0,0.4); font-weight: 800;">Rp <?= number_format($harga_satuan * $qty, 0, ',', '.') ?></span>
            </div>

            <div id="box-konfirmasi" class="animate__animated animate__fadeIn" style="display: none;">
                <span style="font-size: 0.85em; color: rgba(255,255,255,0.6);"><i class="fa-solid fa-circle-check"></i> Opsi Pembayaran:</span><br>
                <strong id="teks-metode" style="color: var(--neon-glow); font-size: 0.95rem;">-</strong>
            </div>

            <button id="pay-button" class="btn-pay" disabled>
                <i class="fa-solid fa-wallet"></i> Bayar Sekarang
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('pay-button').onclick = function() {
    const btn = this;
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
        alert("Data pesanan gak boleh kosong mprruy!");
        return;
    }

    // Ubah status tombol jadi Loading State yang clean
    btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Processing...";
    btn.disabled = true;

    fetch('ambil_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
                'id_user': '<?= $_SESSION['id_user'] ?? 0 ?>',
                'id_game': '<?= $id_game ?>',
                'produk': '<?= $nama_produk ?>',
                'harga': '<?= $harga_satuan * $qty ?>', 
                'qty': '<?= $qty ?>',
                'user': dataUserTerupdate 
            })
    })
    .then(res => res.json())
    .then(data => {
        if (data.invoice_url) {
            window.location.href = data.invoice_url;
        } else {
            alert("Gagal: " + (data.message || "Error server mprruy"));
            btn.innerHTML = "<i class='fa-solid fa-wallet'></i> Bayar Sekarang";
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert("Koneksi gagal bray!");
        btn.innerHTML = "<i class='fa-solid fa-wallet'></i> Bayar Sekarang";
        btn.disabled = false;
    });
};

function pilihMetode(nama) {
    const confirmBox = document.getElementById('box-konfirmasi');
    confirmBox.style.display = 'block';
    document.getElementById('teks-metode').innerText = nama;
    document.getElementById('xendit-box').classList.add('active');
    document.getElementById('pay-button').disabled = false;
}
</script>

</body>
</html>