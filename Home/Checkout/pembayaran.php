<?php
session_start();
include '../koneksi.php'; 

$items_checkout = [];
$total_tagihan = 0;
$mode_checkout = ''; // Pengandaian mode: 'multi' (keranjang) atau 'single' (beli langsung)

// =========================================================================
// LOGIKA 1: JIKA CHECKOUT DARI KERANJANG (MULTI ITEM VIA PARAMETER 'ids')
// =========================================================================
if (!empty($_GET['ids'])) {
    $mode_checkout = 'multi';
    
    // Pecah string ID (Misal: "1,2,3") menjadi array, lalu bersihkan dari SQL Injection
    $ids_raw = explode(',', $_GET['ids']);
    $ids_clean = array_map('intval', $ids_raw);
    $ids_implode = implode(',', $ids_clean);

    // Ambil data game dan catatan akun langsung dari database keranjang & games
    $query = "SELECT k.id_keranjang, k.qty, k.harga, k.nama_produk, k.catatan, g.nama_game, g.gambar, g.id AS id_game 
              FROM keranjang k 
              LEFT JOIN games g ON k.id_game = g.id 
              WHERE k.id_keranjang IN ($ids_implode)";
    
    $result = mysqli_query($koneksi, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $items_checkout[] = [
            'id_game'     => $row['id_game'],
            'nama_game'   => $row['nama_game'] ?? 'Game',
            'gambar'      => $row['gambar'] ? "../" . $row['gambar'] : "../assets/img/default.jpg",
            'nama_produk' => $row['nama_produk'],
            'harga'       => (int)$row['harga'], // Harga satuan asli
            'qty'         => (int)$row['qty'],
            'user_data'   => $row['catatan'] // Menyimpan data akun bawaan dari keranjang
        ];
        // Total tagihan akumulasi: (harga satuan * qty) per item
        $total_tagihan += ((int)$row['harga'] * (int)$row['qty']);
    }
} 
// =========================================================================
// LOGIKA 2: JIKA BELI LANGSUNG DARI HALAMAN PRODUK (SINGLE ITEM)
// =========================================================================
elseif (!empty($_GET['id_game'])) {
    $mode_checkout = 'single';
    $id_game = (int)$_GET['id_game'];
    $user_data_mentah = $_GET['user'] ?? '';
    $nama_produk = $_GET['paket'] ?? ($_GET['produk'] ?? 'Produk'); 
    $harga_satuan = (int)($_GET['harga'] ?? 0);
    $qty = (int)($_GET['qty'] ?? 1);

    // Ambil data cover & nama game dari DB
    $nama_game = 'Game';
    $path_foto = "../assets/img/default.jpg";
    
    $stmt_game = $koneksi->prepare("SELECT nama_game, gambar FROM games WHERE id = ?");
    $stmt_game->bind_param("i", $id_game);
    $stmt_game->execute();
    $result_game = $stmt_game->get_result();
    
    if ($result_game && $result_game->num_rows > 0) {
        $data_game = $result_game->fetch_assoc();
        $nama_game = $data_game['nama_game'] ?? 'Game';
        $path_foto = "../" . ($data_game['gambar'] ?? 'default.jpg'); 
    }
    $stmt_game->close();

    // Masukkan ke array satu-satunya item
    $items_checkout[] = [
        'id_game'     => $id_game,
        'nama_game'   => $nama_game,
        'gambar'      => $path_foto,
        'nama_produk' => $nama_produk,
        'harga'       => $harga_satuan,
        'qty'         => $qty,
        'user_data'   => $user_data_mentah
    ];
    $total_tagihan = $harga_satuan * $qty;
} else {
    // Pengaman jika diakses tanpa parameter apa pun mprruy
    die("<h2 style='color:#fff; text-align:center; font-family:sans-serif; padding-top:100px;'>Akses Ilegal Bray! Data produk tidak ditemukan.</h2>");
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
            align-items: flex-start;
        }

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

        h2 i { color: var(--neon-accent); }

        /* Blok baris per item */
        .item-checkout-block {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            flex-wrap: wrap;
            background: rgba(0,0,0,0.15);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.05);
        }

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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
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

        #box-konfirmasi { 
            background: rgba(255, 106, 0, 0.08); 
            padding: 15px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            border: 1px dashed var(--neon-accent); 
        }

        hr { border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0; }

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
            <h2><i class="fa-solid fa-gamepad"></i> Informasi Pesanan</h2>
            
            <?php foreach ($items_checkout as $index => $item): ?>
            <div class="item-checkout-block" 
                 data-idgame="<?= htmlspecialchars($item['id_game']) ?>" 
                 data-produk="<?= htmlspecialchars($item['nama_produk']) ?>" 
                 data-harga="<?= $item['harga'] ?>" 
                 data-qty="<?= $item['qty'] ?>">
                
                <img src="<?= htmlspecialchars($item['gambar']) ?>" onerror="this.src='../assets/img/default.jpg'" style="width: 90px; height: 90px; border-radius: 16px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 8px 20px rgba(0,0,0,0.3);" alt="Game Cover">
                
                <div style="flex: 1; min-width: 200px;">
                    <h3 style="margin:0; font-size: 1.3em; color: #fff; font-weight: 700;"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                    <p style="margin:6px 0 0 0; color: rgba(255,255,255,0.6); font-size: 0.95em;">
                        <i class="fa-solid fa-layer-group" style="margin-right: 5px; color: var(--neon-glow);"></i> Game: <strong><?= htmlspecialchars($item['nama_game']) ?></strong> 
                        &bull; <b style="color:var(--neon-glow);">Rp <?= number_format($item['harga'], 0, ',', '.') ?> (x<?= $item['qty'] ?>)</b>
                    </p>

                    <div style="background: rgba(0,0,0,0.15); padding: 15px; border-radius: 14px; margin-top: 15px; border: 1px solid rgba(255,255,255,0.05); border-left: 3px solid var(--neon-accent);">
                        <label style="font-size: 0.75em; color: var(--neon-glow); font-weight: 800; text-transform: uppercase; display: block; margin-bottom: 12px; letter-spacing: 1px;">
                            <i class="fa-solid fa-user-gear"></i> Konfirmasi / Edit Data Akun:
                        </label>
                        
                        <div class="container-input-data">
                            <?php 
                            $acc_json = json_decode($item['user_data'], true); 
                            
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
                                $data_list = explode('|', $item['user_data']); 
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
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-credit-card"></i> Metode Pembayaran</h2>
            <div id="xendit-box" class="method-box" onclick="pilihMetode(this, 'Xendit Payment (QRIS, VA, Dana, dll)')">
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
            
            <?php if ($mode_checkout === 'single'): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: rgba(255,255,255,0.7);">
                    <span>Harga Satuan</span>
                    <span style="color: #fff; font-weight: 600;">Rp <?= number_format($items_checkout[0]['harga'], 0, ',', '.') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: rgba(255,255,255,0.7);">
                    <span>Jumlah</span>
                    <span style="color: #fff; font-weight: 600;">x<?= $items_checkout[0]['qty'] ?></span>
                </div>
                <hr>
            <?php else: ?>
                <div style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-bottom: 12px;">
                    <i class="fa-solid fa-boxes-stacked"></i> Gabungan (<?= count($items_checkout) ?>) Item Keranjang
                </div>
                <hr>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.25em; margin-bottom: 20px; align-items: center;">
                <span>Total Tagihan</span>
                <span style="color: #fff; text-shadow: 0 0 10px rgba(255,106,0,0.4); font-weight: 800;">Rp <?= number_format($total_tagihan, 0, ',', '.') ?></span>
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
    let finalPayload = [];
    let isDataKosong = false;

    // Kumpulkan data terupdate dari setiap blok game secara independen (mendukung multi/single otomatis)
    document.querySelectorAll('.item-checkout-block').forEach(block => {
        let tempAkun = [];
        block.querySelectorAll('.input-edit-data').forEach(input => {
            const label = input.getAttribute('data-label');
            const val = input.value.trim();
            if(val === "") {
                isDataKosong = true;
            }
            tempAkun.push(label + ": " + val);
        });

        finalPayload.push({
            id_game: block.getAttribute('data-idgame'),
            produk: block.getAttribute('data-produk'),
            harga: block.getAttribute('data-harga'), // harga satuan
            qty: block.getAttribute('data-qty'),
            user_data: tempAkun.join(' | ') // data gabungan khusus game ini
        });
    });

    if (isDataKosong) {
        alert("Data pesanan atau akun ada yang kosong mprruy! Harap lengkapi semua.");
        return;
    }

    // Ubah status tombol jadi Loading State
    btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Processing...";
    btn.disabled = true;

    // Bangun data kiriman
    let bodyData = {
        'id_user': <?= json_encode($_SESSION['id_user'] ?? 0) ?>,
        'total_bayar': '<?= $total_tagihan ?>',
        'pesanan_multi': JSON.stringify(finalPayload) // Kirim full payload terstruktur array JSON
    };

    // Jaga komparasi kompatibilitas mundur jika 'ambil_token.php' lu masih butuh parameter lama untuk single item
    if(finalPayload.length === 1) {
        bodyData['id_game'] = finalPayload[0].id_game;
        bodyData['produk']  = finalPayload[0].produk;
        bodyData['harga']   = '<?= $total_tagihan ?>'; // Total harga akumulasi
        bodyData['qty']     = finalPayload[0].qty;
        bodyData['user']    = finalPayload[0].user_data;
    }

    fetch('ambil_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(bodyData)
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

function pilihMetode(element, nama) {
    document.querySelectorAll('.method-box').forEach(box => {
        box.classList.remove('active');
    });
    element.classList.add('active');
    
    const confirmBox = document.getElementById('box-konfirmasi');
    confirmBox.style.display = 'block';
    document.getElementById('teks-metode').innerText = nama;
    document.getElementById('pay-button').disabled = false;
}
</script>

</body>
</html>