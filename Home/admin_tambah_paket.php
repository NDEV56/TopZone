<?php
include 'koneksi.php';

// 1. Ambil slug dari URL (?game=slug-game)
$slug = isset($_GET['game']) ? mysqli_real_escape_string($koneksi, $_GET['game']) : '';

// 2. Cari info game berdasarkan slug untuk header form
// *Pastikan di tabel 'games' sudah ada kolom penanda tipenya, di sini kita asumsikan namanya 'tipe_kustom_game'*
$query_game = mysqli_query($koneksi, "SELECT * FROM games WHERE slug = '$slug'");
$data_game = mysqli_fetch_assoc($query_game);

// Proteksi jika game tidak ditemukan
if (!$data_game) {
    echo "<script>alert('Data game tidak ditemukan!'); window.location='admin_tambah_game.php';</script>";
    exit;
}

// Mengambil status setelan game dari database. Nilainya bisa: 'segame', 'non_segame', atau 'global'
// Jika belum ada/kosong, kita set default ke 'global' biar tidak error bray
$status_settingan_game = isset($data_game['tipe_kustom_game']) ? $data_game['tipe_kustom_game'] : 'global';

// 3. Logika Simpan Data ke Database
if (isset($_POST['simpan'])) {
    $id_game      = $data_game['id'];
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga_asli   = mysqli_real_escape_string($koneksi, $_POST['harga_db']);
    
    // Logika penentuan nilai TIPE yang akan masuk ke tabel produk_game
    if ($status_settingan_game === 'segame') {
        // Jika kustom segame, form diatur global awal, maka tipenya otomatis 'segame' atau 'umum'
        $tipe = 'segame';
    } elseif ($status_settingan_game === 'non_segame') {
        // Jika kustom non-segame, ambil langsung nilai dari input kustomnya
        $tipe = 'custom';
        if (isset($_POST['p_label_custom'])) {
            $filter_custom = array_filter($_POST['p_label_custom']); // Buang array kosong
            if (!empty($filter_custom)) {
                $tipe = 'custom:' . implode(' + ', $filter_custom);
            }
        }
    } else {
        // Jika global / standar biasa, ambil dari dropdown select_tipe
        $tipe = mysqli_real_escape_string($koneksi, $_POST['tipe']);
        if ($tipe === 'custom' && isset($_POST['p_label_custom'])) {
            $filter_custom = array_filter($_POST['p_label_custom']);
            if (!empty($filter_custom)) {
                $tipe = 'custom:' . implode(' + ', $filter_custom);
            }
        }
    }

    // Query eksekusi penyimpanan ke tabel produk_game
    $insert = mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) 
                                      VALUES ('$id_game', '$nama_produk', '$harga_asli', '$tipe')");
    
    if ($insert) {
        echo "<script>alert('Sukses! Paket $nama_produk berhasil ditambahkan.'); window.location='admin_paket.php?game=$slug';</script>";
    } else {
        echo "<script>alert('Gagal simpan data!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Paket - <?= htmlspecialchars($data_game['nama_game']) ?></title>
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
            overflow-x: hidden;
        }

        body::before {
            content: ""; position: absolute; width: 500px; height: 500px; border-radius: 50%;
            background: linear-gradient(45deg, #002266, var(--topzone-blue), #00aaff);
            filter: blur(100px); z-index: -1; opacity: 0.5;
        }

        .form-container { 
            background: rgba(11, 23, 58, 0.45); 
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 35px 30px; 
            border-radius: 20px; 
            width: 100%; 
            max-width: 460px; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3); 
        }

        .header-game { 
            text-align: center; 
            margin-bottom: 25px; 
        }

        .header-game img { 
            width: 75px; height: 75px; border-radius: 16px; 
            object-fit: cover; border: 2px solid var(--topzone-blue); 
            margin-bottom: 12px; box-shadow: 0 0 20px rgba(0, 92, 255, 0.3);
        }

        .header-game h2 { margin: 0; font-size: 20px; color: var(--text-main); font-weight: 700; }
        .header-game p { margin: 6px 0 0; font-size: 12px; color: var(--primary); font-family: monospace; letter-spacing: 0.5px; }

        /* BANNER INDIKATOR SISTEM FORM */
        .status-banner {
            padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: bold; margin-top: 15px; text-align: center;
        }
        .status-segame { background: rgba(0, 255, 170, 0.1); color: #00ffaa; border: 1px solid rgba(0, 255, 170, 0.2); }
        .status-nonsegame { background: rgba(255, 170, 0, 0.1); color: #ffaa00; border: 1px solid rgba(255, 170, 0, 0.2); }
        .status-global { background: rgba(0, 210, 255, 0.1); color: var(--primary); border: 1px solid rgba(0, 210, 255, 0.2); }

        label { display: block; margin-bottom: 8px; font-size: 13px; color: var(--text-muted); margin-top: 18px; font-weight: 500; }
        input, select { width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); border-radius: 10px; color: #fff; font-size: 14px; outline: none; transition: all 0.25s ease; }
        input:focus, select:focus { background: rgba(255, 255, 255, 0.06); border-color: rgba(0, 170, 255, 0.4); box-shadow: 0 0 15px rgba(0, 170, 255, 0.15); }
        option { background: var(--navy-mid); color: #fff; }

        .custom-fields-wrapper {
            display: flex; flex-direction: column; gap: 6px; margin-top: 8px;
            background: rgba(0, 210, 255, 0.02); padding: 12px; border-radius: 10px;
            border: 1px dashed rgba(0, 210, 255, 0.2);
        }

        .custom-input-group { display: flex; gap: 8px; align-items: center; }

        .btn-custom-action {
            height: 42px; width: 42px; border-radius: 8px; border: none;
            cursor: pointer; font-weight: bold; font-size: 16px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }

        .btn-custom-add { background: rgba(0, 210, 255, 0.15); color: var(--primary); border: 1px solid rgba(0, 210, 255, 0.3); }
        .btn-custom-add:hover { background: var(--primary); color: var(--navy-deep); }
        .btn-custom-remove { background: rgba(255, 68, 68, 0.15); color: #ff4444; border: 1px solid rgba(255, 68, 68, 0.3); }
        .btn-custom-remove:hover { background: #ff4444; color: #fff; }

        .btn-group { margin-top: 30px; display: flex; flex-direction: column; gap: 12px; }
        .btn-save { background: linear-gradient(135deg, var(--topzone-blue), #00aaff); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 15px; box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3); }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45); }
        .btn-back { text-align: center; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px; }
        .btn-back:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="form-container">
    <div class="header-game">
        <img src="<?= $data_game['gambar'] ?>" alt="Logo">
        <h2>Tambah Paket Baru</h2>
        <p><?= htmlspecialchars($data_game['nama_game']) ?> (<?= htmlspecialchars($data_game['slug']) ?>)</p>
        
        <?php if($status_settingan_game === 'segame'): ?>
            <div class="status-banner status-segame">🔒 Mode: Kustom Satu Game (Form Diatur Global)</div>
        <?php elseif($status_settingan_game === 'non_segame'): ?>
            <div class="status-banner status-nonsegame">🔓 Mode: Kustom Non-Segame (Form Diatur Per Paket)</div>
        <?php else: ?>
            <div class="status-banner status-global">🌐 Mode: Format Game Standar / Global</div>
        <?php endif; ?>
    </div>

    <form method="POST">
        <label>Nama Produk / Jumlah Voucher</label>
        <input type="text" name="nama_produk" placeholder="Contoh: 1000 Robux" required autocomplete="off">

        <label>Harga (Otomatis Format Titik)</label>
        <input type="text" id="display_harga" placeholder="Contoh: 120.000" required autocomplete="off">
        <input type="hidden" name="harga_db" id="harga_db">

        <div id="panel_pengaturan_data" style="display: block;">
            
            <div id="wrapper_select_tipe">
                <label>Tipe / Kategori Sistem Khusus</label>
                <select name="tipe" id="select_tipe" onchange="toggleKustomField(this)">
                    <optgroup label="Umum">
                        <option value="umum">UMUM (Normal)</option>
                        <option value="promo">PROMO (Murah)</option>
                    </optgroup>
                    
                    <?php if($data_game['slug'] == 'roblox'): ?>
                    <optgroup label="Khusus Roblox">
                        <option value="roblox_login">ROBLOX (Via Login)</option>
                        <option value="roblox_5hari">ROBLOX (Via Gamepass 5 Hari)</option>
                    </optgroup>
                    <?php endif; ?>

                    <optgroup label="Dinamis">
                        <option value="custom">KUSTOM (Tentukan Sendiri)</option>
                    </optgroup>
                </select>
            </div>

            <div class="custom-fields-wrapper" id="custom_fields_wrapper" style="display: none; margin-top: 15px;">
                <label style="margin-top: 0; color: var(--primary);" id="label_kustom_title">Format Input Form User Khusus</label>
                <div class="custom-input-group">
                    <input type="text" name="p_label_custom[]" id="input_kustom_pertama" placeholder="Contoh: ID User" style="padding: 10px;">
                    <button type="button" class="btn-custom-action btn-custom-add" onclick="tambahFieldKustom()">+</button>
                </div>
            </div>

        </div>

        <div class="btn-group">
            <button type="submit" name="simpan" class="btn-save">SIMPAN KE DATABASE</button>
            <a href="admin_paket.php" class="btn-back">Kembali</a>
        </div>
    </form>
</div>

<script>
    const displayHarga = document.getElementById('display_harga');
    const hargaDb = document.getElementById('harga_db');
    
    const panelPengaturanData = document.getElementById('panel_pengaturan_data');
    const wrapperSelectTipe = document.getElementById('wrapper_select_tipe');
    const wrapperKustom = document.getElementById('custom_fields_wrapper');
    const selectTipe = document.getElementById('select_tipe');
    const inputKustomPertama = document.getElementById('input_kustom_pertama');
    const labelKustomTitle = document.getElementById('label_kustom_title');

    // Menerima data setelan langsung dari variabel PHP yang bersumber dari database
    const modeGameDariDB = "<?= $status_settingan_game ?>";

    // Format Rupiah Realtime Input Harga
    displayHarga.addEventListener('input', function(e) {
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

    // UTAMA: Logika Penyesuaian Tampilan Otomatis Mengikuti Isi Database Game
    function sinkronisasiTampilanDenganDB() {
        if (modeGameDariDB === 'segame') {
            // 1. JIKA SEGAME -> PANEL DATA HILANG TOTAL (GADA APA-APA BRAY)
            panelPengaturanData.style.display = 'none';
            inputKustomPertama.removeAttribute('required');
            
        } else if (modeGameDariDB === 'non_segame') {
            // 2. JIKA NON-SEGAME -> PANEL DATA KUSTOM LANGSUNG MUNCUL
            panelPengaturanData.style.display = 'block';
            wrapperSelectTipe.style.display = 'none'; // Sembunyikan dropdown pilihan tipe global biasa
            wrapperKustom.style.display = 'flex';     // Langsung paksa tampilkan kolom penambah data kustom
            labelKustomTitle.innerText = "Format Input Form Khusus Paket Ini (Non-Segame)";
            inputKustomPertama.setAttribute('required', 'required');
            
        } else {
            // 3. JIKA GLOBAL / LAINNYA -> TETAP MUNCUL SEPERTI BIASA
            panelPengaturanData.style.display = 'block';
            wrapperSelectTipe.style.display = 'block';
            wrapperKustom.style.display = 'none';
            selectTipe.value = 'umum';
            inputKustomPertama.removeAttribute('required');
        }
    }

    // Handler pendukung jika di mode global/standar admin mengubah tipe dropdown ke kustom
    function toggleKustomField(select) {
        if (select.value === 'custom') {
            wrapperKustom.style.display = 'flex';
            inputKustomPertama.setAttribute('required', 'required');
        } else {
            wrapperKustom.style.display = 'none';
            inputKustomPertama.removeAttribute('required');
            inputKustomPertama.value = '';
            resetFieldKustomTambahan();
        }
    }

    function tambahFieldKustom() {
        const div = document.createElement('div');
        div.className = 'custom-input-group';
        div.innerHTML = `
            <input type="text" name="p_label_custom[]" placeholder="Format Tambahan (ex: Server)" required style="padding: 10px;">
            <button type="button" class="btn-custom-action btn-custom-remove" onclick="hapusFieldKustom(this)">-</button>
        `;
        wrapperKustom.appendChild(div);
    }

    function hapusFieldKustom(btn) {
        btn.closest('.custom-input-group').remove();
    }

    function resetFieldKustomTambahan() {
        const grupTambahan = wrapperKustom.querySelectorAll('.custom-input-group');
        for (let i = 1; i < grupTambahan.length; i++) {
            grupTambahan[i].remove();
        }
    }

    // Jalankan auto-matching sesaat setelah halaman web termuat sempurna
    window.onload = function() {
        sinkronisasiTampilanDenganDB();
    };
</script>

</body>
</html>