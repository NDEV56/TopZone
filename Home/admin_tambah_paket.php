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
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .form-container { background: var(--card); padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; border: 1px solid #333; box-shadow: 0 15px 35px rgba(0,0,0,0.5); }
        .header-game { text-align: center; margin-bottom: 25px; }
        .header-game img { width: 70px; height: 70px; border-radius: 15px; object-fit: cover; border: 2px solid var(--primary); margin-bottom: 10px; }
        .header-game h2 { margin: 0; font-size: 20px; color: #fff; }
        .header-game p { margin: 5px 0 0; font-size: 12px; color: var(--primary); font-family: monospace; }

        label { display: block; margin-bottom: 8px; font-size: 13px; color: #888; margin-top: 15px; }
        input, select { width: 100%; padding: 12px 15px; background: #121212; border: 1px solid #444; border-radius: 10px; color: #fff; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.3s; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 10px rgba(0, 255, 136, 0.2); }

        .btn-group { margin-top: 30px; display: flex; flex-direction: column; gap: 10px; }
        .btn-save { background: var(--primary); color: #000; border: none; padding: 14px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 15px; transition: 0.3s; }
        .btn-save:hover { background: #fff; transform: translateY(-2px); }
        .btn-back { text-align: center; color: #666; text-decoration: none; font-size: 13px; }
        .btn-back:hover { color: #fff; }
        
        /* Gaya khusus untuk opsi Roblox */
        option { background: var(--card); color: #fff; }
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