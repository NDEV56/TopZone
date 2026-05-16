<?php
include 'koneksi.php';

// 1. Ambil slug dari URL (?game=slug-game)
$slug = isset($_GET['game']) ? mysqli_real_escape_string($koneksi, $_GET['game']) : '';

// 2. Cari info game berdasarkan slug untuk header form
$query_game = mysqli_query($koneksi, "SELECT * FROM games WHERE slug = '$slug'");
$data_game = mysqli_fetch_assoc($query_game);

// Proteksi jika game tidak ditemukan
if (!$data_game) {
    echo "<script>alert('Data game tidak ditemukan!'); window.location='admin_paket.php';</script>";
    exit;
}

// 3. Logika Simpan Data ke Database
if (isset($_POST['simpan'])) {
    $id_game      = $data_game['id'];
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $tipe         = mysqli_real_escape_string($koneksi, $_POST['tipe']); // Ini kategori sistemnya
    
    // Ambil harga asli (yang sudah bersih dari titik lewat input hidden)
    $harga_asli   = mysqli_real_escape_string($koneksi, $_POST['harga_db']);

    // Pastikan nama kolom 'tipe' di database sesuai, atau ganti jadi 'kategori_sistem' jika lu sudah ubah di DB
    $insert = mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) 
                                     VALUES ('$id_game', '$nama_produk', '$harga_asli', '$tipe')");
    
    if ($insert) {
        echo "<script>alert('Sukses! Paket $nama_produk berhasil ditambahkan.'); window.location='admin_paket.php';</script>";
    } else {
        echo "<script>alert('Gagal simpan data!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Paket - <?= $data_game['nama_game'] ?></title>
    <style>
        /* ==========================================================================
        RESET & VARIABEL UTAMA (Topzone Blue Navy Theme)
        ========================================================================== */
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        } 

        :root { 
            --primary: #00d2ff; 
            --primary-glow: rgba(0, 210, 255, 0.35);
            --topzone-blue: #005cff;
            --navy-deep: #050d26;
            --navy-mid: #0b173a;
            --glass-bg: rgba(11, 23, 58, 0.45);
            --glass-border: rgba(255, 255, 255, 0.06);
            --text-main: #ffffff;
            --text-muted: #647b9b;
        }

        /* ==========================================================================
        BODY & ANIMASI LATAR BELAKANG (Liquid Blobs)
        ========================================================================== */
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: var(--navy-deep); 
            color: var(--text-main); 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            position: relative;
            overflow: hidden;
        }

        body::before, body::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: linear-gradient(45deg, #002266, var(--topzone-blue), #00aaff);
            filter: blur(100px);
            z-index: -1;
            opacity: 0.5;
            animation: liquidMovement 15s infinite alternate ease-in-out;
        }

        body::after {
            right: -50px;
            bottom: -50px;
            background: linear-gradient(45deg, var(--topzone-blue), #001133, #00d2ff);
            animation-delay: -7.5s;
        }

        @keyframes liquidMovement {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
            50% { transform: translate(80px, 40px) scale(1.1) rotate(180deg); border-radius: 30% 70% 70% 30% / 50% 30% 70% 50%; }
            100% { transform: translate(-40px, 60px) scale(0.95) rotate(360deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
        }

        /* ==========================================================================
        CONTAINER FORM (Glassmorphism Box)
        ========================================================================== */
        .form-container { 
            background: rgba(11, 23, 58, 0.45); 
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 35px 30px; 
            border-radius: 20px; 
            width: 100%; 
            max-width: 450px; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3); 
            animation: formFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes formFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ==========================================================================
        HEADER GAME VIEW
        ========================================================================== */
        .header-game { 
            text-align: center; 
            margin-bottom: 25px; 
        }

        .header-game img { 
            width: 75px; 
            height: 75px; 
            border-radius: 16px; 
            object-fit: cover; 
            border: 2px solid var(--topzone-blue); 
            margin-bottom: 12px; 
            box-shadow: 0 0 20px rgba(0, 92, 255, 0.3);
        }

        .header-game h2 { 
            margin: 0; 
            font-size: 20px; 
            color: var(--text-main); 
            font-weight: 700;
        }

        .header-game p { 
            margin: 6px 0 0; 
            font-size: 12px; 
            color: var(--primary); 
            font-family: monospace; 
            letter-spacing: 0.5px;
            text-shadow: 0 0 8px var(--primary-glow);
        }

        /* ==========================================================================
        INPUT, SELECT, & LABELS
        ========================================================================== */
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-size: 13px; 
            color: var(--text-muted); 
            margin-top: 18px; 
            font-weight: 500;
        }

        input, select { 
            width: 100%; 
            padding: 12px 15px; 
            background: rgba(255, 255, 255, 0.03); 
            border: 1px solid var(--glass-border); 
            border-radius: 10px; 
            color: #fff; 
            font-size: 14px; 
            box-sizing: border-box; 
            outline: none; 
            transition: all 0.25s ease; 
        }

        input:focus, select:focus { 
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(0, 170, 255, 0.4); 
            box-shadow: 0 0 15px rgba(0, 170, 255, 0.15); 
        }

        option { 
            background: var(--navy-mid); 
            color: #fff; 
        }

        /* ==========================================================================
        BUTTON GROUP (Glow Action)
        ========================================================================== */
        .btn-group { 
            margin-top: 30px; 
            display: flex; 
            flex-direction: column; 
            gap: 12px; 
        }

        .btn-save { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            border: none; 
            padding: 14px; 
            border-radius: 10px; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 15px; 
            transition: all 0.25s ease; 
            box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
        }

        .btn-save:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45);
        }

        .btn-back { 
            text-align: center; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: color 0.2s ease;
            padding: 5px;
        }

        .btn-back:hover { 
            color: var(--primary); 
            text-shadow: 0 0 8px var(--primary-glow);
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="header-game">
        <img src="<?= $data_game['gambar'] ?>" alt="Logo">
        <h2>Tambah Paket Baru</h2>
        <p><?= $data_game['nama_game'] ?> (<?= $data_game['slug'] ?>)</p>
    </div>

    <form method="POST">
        <label>Nama Produk / Jumlah Voucher</label>
        <input type="text" name="nama_produk" placeholder="Contoh: 1000 Robux" required autocomplete="off">

        <label>Harga (Otomatis Format Titik)</label>
        <input type="text" id="display_harga" placeholder="Contoh: 120.000" required autocomplete="off">
        <input type="hidden" name="harga_db" id="harga_db">

        <label>Tipe / Kategori Sistem</label>
        <select name="tipe">
            <!-- Pilihan Umum -->
            <optgroup label="Umum">
                <option value="umum">UMUM (Normal)</option>
                <option value="promo">PROMO (Murah)</option>
            </optgroup>
            
            <!-- Pilihan Khusus Roblox (Hanya muncul/relevan jika slug itu roblox) -->
            <?php if($data_game['slug'] == 'roblox'): ?>
            <optgroup label="Khusus Roblox">
                <option value="roblox_login">ROBLOX (Via Login)</option>
                <option value="roblox_5hari">ROBLOX (Via Gamepass 5 Hari)</option>
            </optgroup>
            <?php endif; ?>
        </select>

        <div class="btn-group">
            <button type="submit" name="simpan" class="btn-save">SIMPAN KE DATABASE</button>
            <a href="admin_paket.php" class="btn-back">Kembali ke Kelola Paket</a>
        </div>
    </form>
</div>

<script>
    const displayHarga = document.getElementById('display_harga');
    const hargaDb = document.getElementById('harga_db');

    displayHarga.addEventListener('keyup', function(e) {
        let value = this.value.replace(/[^0-9]/g, '');
        hargaDb.value = value;
        if (value !== "") {
            this.value = formatRibuan(value);
        }
    });

    function formatRibuan(angka) {
        let number_string = angka.toString(),
            sisa = number_string.length % 3,
            rupiah = number_string.substr(0, sisa),
            ribuan = number_string.substr(sisa).match(/\d{3}/g);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        return rupiah;
    }
</script>

</body>
</html>