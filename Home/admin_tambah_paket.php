<?php
include 'koneksi.php';

// 1. Ambil slug dari URL (?game=slug-game)
$slug = isset($_GET['game']) ? mysqli_real_escape_string($koneksi, $_GET['game']) : '';

// 2. Cari info game berdasarkan slug untuk header form
$query_game = mysqli_query($koneksi, "SELECT * FROM games WHERE slug = '$slug'");
$data_game = mysqli_fetch_assoc($query_game);

// Proteksi jika game tidak ditemukan
if (!$data_game) {
    echo "<script>alert('Data game tidak ditemukan!'); window.location='admin_tambah_game.php';</script>";
    exit;
}

// Mengambil status setelan game dari database. Nilainya dari kolom 'tipe_input'
$status_settingan_game = isset($data_game['tipe_input']) ? $data_game['tipe_input'] : 'umum';

// Mengambil data string format input dari kolom 'target_input_kustom' (Contoh isi: "ID User, Server")
$fitur_kustom_game = isset($data_game['target_input_kustom']) ? $data_game['target_input_kustom'] : '';

// Dipecah menggunakan KOMA (,) sesuai dengan implode(', ', $filter_custom) di proses_tambah_game
$array_fitur_kustom = [];
if (!empty($fitur_kustom_game)) {
    $array_fitur_kustom = array_map('trim', explode(',', $fitur_kustom_game));
}

// 3. Logika Simpan Data ke Database
if (isset($_POST['simpan'])) {
    $id_game      = $data_game['id'];
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga_asli   = mysqli_real_escape_string($koneksi, $_POST['harga_db']);
    
    if ($status_settingan_game === 'kustom_global') {
        // Jika kustom_global (Satu Game), otomatis tipenya mengunci format global game tersebut
        $tipe = 'custom:' . implode(' + ', $array_fitur_kustom);
    } elseif ($status_settingan_game === 'kustom_per_paket') {
        // Jika kustom_per_paket (Non-Segame), ambil nilai dinamis dari input form yang diedit admin saat ini
        $tipe = 'custom';
        if (isset($_POST['p_label_custom'])) {
            $filter_custom = array_filter($_POST['p_label_custom']); // Buang array kosong
            if (!empty($filter_custom)) {
                $tipe = 'custom:' . implode(' + ', $filter_custom);
            }
        }
    } else {
        // Jika umum / standar biasa, ambil dari dropdown select_tipe
        $tipe = mysqli_real_escape_string($koneksi, $_POST['tipe']);
        if (($tipe === 'custom' || $tipe === 'roblox_login' || $tipe === 'roblox_5hari') && isset($_POST['p_label_custom'])) {
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
            --danger: #ff4444;
            --success: #00ffaa;
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

        .status-banner {
            padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: bold; margin-top: 15px; text-align: center;
        }
        .status-segame { background: rgba(0, 255, 170, 0.1); color: #00ffaa; border: 1px solid rgba(0, 255, 170, 0.2); }
        .status-nonsegame { background: rgba(255, 170, 0, 0.1); color: #ffaa00; border: 1px solid rgba(255, 170, 0, 0.2); }
        .status-global { background: rgba(0, 210, 255, 0.1); color: var(--primary); border: 1px solid rgba(0, 210, 255, 0.2); }

        label { display: block; margin-bottom: 8px; font-size: 13px; color: var(--text-muted); margin-top: 18px; font-weight: 500; }
        input, select { width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); border-radius: 10px; color: #fff; font-size: 14px; outline: none; transition: all 0.25s ease; }
        input:focus, select:focus { background: rgba(255, 255, 255, 0.06); border-color: rgba(0, 170, 255, 0.4); box-shadow: 0 0 15px rgba(0, 170, 255, 0.15); }
        input:disabled { background: rgba(255, 255, 255, 0.02); color: #738ca8; cursor: not-allowed; border-style: dashed; }
        option { background: var(--navy-mid); color: #fff; }

        .custom-fields-wrapper {
            display: flex; flex-direction: column; gap: 6px; margin-top: 8px;
            background: rgba(0, 210, 255, 0.02); padding: 12px; border-radius: 10px;
            border: 1px dashed rgba(0, 210, 255, 0.2);
        }

        .custom-input-group { display: flex; gap: 8px; align-items: center; margin-bottom: 5px; }

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

        /* ==========================================================================
        MODAL SYSTEM (Topzone High Alert Glassmorphism)
        ========================================================================== */
        .modal-alert-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(3, 7, 20, 0.8); backdrop-filter: blur(10px);
            display: none; justify-content: center; align-items: center; z-index: 99999;
        }
        .modal-alert-box {
            background: #0b173a; border: 1px solid rgba(255, 68, 68, 0.3);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), 0 0 30px rgba(255, 68, 68, 0.15);
            width: 90%; max-width: 420px; padding: 30px; border-radius: 16px; text-align: center;
        }
        .modal-alert-box h3 { color: #ff4444; font-size: 20px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-alert-box p { color: #cfd9e6; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }
        .modal-alert-buttons { display: flex; gap: 12px; justify-content: center; }
        .modal-btn { padding: 12px 24px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s; min-width: 100px; }
        .modal-btn-yes { background: #ff4444; color: #fff; box-shadow: 0 4px 15px rgba(255, 68, 68, 0.3); }
        .modal-btn-yes:hover { background: #cc3333; transform: scale(1.03); }
        .modal-btn-no { background: rgba(255,255,255,0.06); color: #a2b4cd; border: 1px solid var(--glass-border); }
        .modal-btn-no:hover { background: rgba(255,255,255,0.1); color: #fff; }
    </style>
</head>
<body>

<div class="form-container">
    <div class="header-game">
        <img src="<?= $data_game['gambar'] ?>" alt="Logo">
        <h2>Tambah Paket Baru</h2>
        <p><?= htmlspecialchars($data_game['nama_game']) ?> (<?= htmlspecialchars($data_game['slug']) ?>)</p>
        
        <?php if($status_settingan_game === 'kustom_global'): ?>
            <div class="status-banner status-segame">🔒 Mode: Data Satu Game (Form Diatur Global)</div>
        <?php elseif($status_settingan_game === 'kustom_per_paket'): ?>
            <div class="status-banner status-nonsegame">🔓 Mode: Data Non-Segame (Form Diatur Per Paket)</div>
        <?php else: ?>
            <div class="status-banner status-global">🌐 Mode: Format Game Standar / Umum</div>
        <?php endif; ?>
    </div>

    <form method="POST" id="form_tambah_paket" onsubmit="return handleValidasiForm(event)">
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
                <div id="container_input_dinamis">
                    </div>
            </div>

        </div>

        <div class="btn-group">
            <button type="submit" name="simpan" class="btn-save">SIMPAN KE DATABASE</button>
            <a href="admin_paket.php?game=<?= $slug ?>" class="btn-back">Kembali</a>
        </div>
    </form>
</div>

<div class="modal-alert-overlay" id="custom_alert_modal">
    <div class="modal-alert-box">
        <h3>⚠️ KONSEKUENSI KRUSIAL!</h3>
        <p>Anda mendeteksi adanya <b>perubahan susunan / nama form kustom</b> bawaan induk game asli.<br><br>Hal ini <b>SANGAT KRUSIAL</b> karena menyebabkan paket produk ini memiliki kolom input checkout yang berbeda dengan paket lainnya dalam satu game yang sama. Lanjutkan simpan?</p>
        <div class="modal-alert-buttons">
            <button class="modal-btn modal-btn-yes" onclick="konfirmasiSimpanSistem(true)">YA, SIMPAN</button>
            <button class="modal-btn modal-btn-no" onclick="konfirmasiSimpanSistem(false)">BATAL</button>
        </div>
    </div>
</div>

<script>
    const displayHarga = document.getElementById('display_harga');
    const hargaDb = document.getElementById('harga_db');
    
    const panelPengaturanData = document.getElementById('panel_pengaturan_data');
    const wrapperSelectTipe = document.getElementById('wrapper_select_tipe');
    const wrapperKustom = document.getElementById('custom_fields_wrapper');
    const containerDinamis = document.getElementById('container_input_dinamis');
    const selectTipe = document.getElementById('select_tipe');
    const labelKustomTitle = document.getElementById('label_kustom_title');
    const modalAlert = document.getElementById('custom_alert_modal');

    const modeGameDariDB = "<?= $status_settingan_game ?>";
    const dataKustomDariDB = <?= json_encode($array_fitur_kustom) ?>;
    
    // Penampung flag status perubahan bray
    let formTerdeteksiBerubah = false;
    let aksesIzinSimpan = false;

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

    // UTAMA: Sinkronisasi pemuatan form kustom serta penambahan fitur edit dinamis (+ / - / ubah nama)
    function sinkronisasiTampilanDenganDB() {
        containerDinamis.innerHTML = ''; 

        if (modeGameDariDB === 'kustom_global') {
            // 1. KUSTOM GLOBAL -> Form dikunci mati
            wrapperSelectTipe.style.display = 'none';
            wrapperKustom.style.display = 'flex';
            labelKustomTitle.innerText = "🔒 Mode Global: Mengunci Struktur Form Utama Game";

            if (dataKustomDariDB.length > 0) {
                dataKustomDariDB.forEach(nilai => {
                    const div = document.createElement('div');
                    div.className = 'custom-input-group';
                    div.innerHTML = `<input type="text" value="${nilai}" disabled style="padding: 10px;">`;
                    containerDinamis.appendChild(div);
                });
            } else {
                containerDinamis.innerHTML = `<p style="font-size:12px; color:#ff4444;">Format kustom game kosong.</p>`;
            }
            
        } else if (modeGameDariDB === 'kustom_per_paket') {
            // 2. KUSTOM PER PAKET -> Full Modifikasi: Ubah nama, Tambah (+), Kurang (-)
            wrapperSelectTipe.style.display = 'none';
            wrapperKustom.style.display = 'flex';
            labelKustomTitle.innerText = "🔓 Mode Per Paket: Form Bawaan Game (Bisa Dimodifikasi)";

            if (dataKustomDariDB.length > 0) {
                dataKustomDariDB.forEach((nilai, index) => {
                    const div = document.createElement('div');
                    div.className = 'custom-input-group';
                    
                    // Semua inputan diberi fungsi oninput untuk mendeteksi perubahan ketikan teks (Ubah Nama)
                    if (index === 0) {
                        div.innerHTML = `
                            <input type="text" name="p_label_custom[]" value="${nilai}" placeholder="Contoh: ID User" required oninput="setFlagPerubahan()" style="padding: 10px;">
                            <button type="button" class="btn-custom-action btn-custom-add" onclick="tambahFieldKustom()">+</button>
                        `;
                    } else {
                        div.innerHTML = `
                            <input type="text" name="p_label_custom[]" value="${nilai}" placeholder="Format Tambahan" required oninput="setFlagPerubahan()" style="padding: 10px;">
                            <button type="button" class="btn-custom-action btn-custom-remove" onclick="hapusFieldKustom(this)">-</button>
                        `;
                    }
                    containerDinamis.appendChild(div);
                });
            } else {
                buatSatuFieldKosong();
            }
            
        } else {
            // 3. JIKA UMUM / LAINNYA
            wrapperSelectTipe.style.display = 'block';
            wrapperKustom.style.display = 'none';
            selectTipe.value = 'umum';
            buatSatuFieldKosong();
        }
    }

    function buatSatuFieldKosong() {
        containerDinamis.innerHTML = `
            <div class="custom-input-group">
                <input type="text" name="p_label_custom[]" id="input_kustom_pertama" placeholder="Contoh: ID User" style="padding: 10px;">
                <button type="button" class="btn-custom-action btn-custom-add" onclick="tambahFieldKustom()">+</button>
            </div>
        `;
    }

    function toggleKustomField(select) {
        const inputPertama = document.getElementById('input_kustom_pertama');
        
        if (select.value === 'custom') {
            wrapperKustom.style.display = 'flex';
            labelKustomTitle.innerText = "Format Input Form User Khusus (Dinamis)";
            if(inputPertama) inputPertama.setAttribute('required', 'required');
        } else if (select.value === 'roblox_login' || select.value === 'roblox_5hari') {
            wrapperKustom.style.display = 'flex';
            labelKustomTitle.innerText = select.value === 'roblox_login' ? "Format Form: ROBLOX Via Login" : "Format Form: ROBLOX Gamepass 5 Hari";
            containerDinamis.innerHTML = select.value === 'roblox_login' ? 
                `<div class="custom-input-group"><input type="text" name="p_label_custom[]" value="Username/Email" required style="padding: 10px;"></div>
                 <div class="custom-input-group"><input type="text" name="p_label_custom[]" value="Password" required style="padding: 10px;"></div>
                 <div class="custom-input-group"><input type="text" name="p_label_custom[]" value="Kode Backup (Minimal 3)" required style="padding: 10px;"></div>` :
                `<div class="custom-input-group"><input type="text" name="p_label_custom[]" value="Link Gamepass / Tempat Game" required style="padding: 10px;"></div>
                 <div class="custom-input-group"><input type="text" name="p_label_custom[]" value="Nama Nickname Roblox" required style="padding: 10px;"></div>`;
        } else {
            wrapperKustom.style.display = 'none';
            if(inputPertama) inputPertama.removeAttribute('required');
            containerDinamis.innerHTML = '';
            buatSatuFieldKosong();
        }
    }

    // Aksi Tambah Field Baru
    function tambahFieldKustom() {
        setFlagPerubahan();
        const div = document.createElement('div');
        div.className = 'custom-input-group';
        div.innerHTML = `
            <input type="text" name="p_label_custom[]" placeholder="Format Tambahan (ex: Server)" required oninput="setFlagPerubahan()" style="padding: 10px;">
            <button type="button" class="btn-custom-action btn-custom-remove" onclick="hapusFieldKustom(this)">-</button>
        `;
        containerDinamis.appendChild(div);
    }

    // Aksi Hapus Field
    function hapusFieldKustom(btn) {
        setFlagPerubahan();
        btn.closest('.custom-input-group').remove();
    }

    // Aktifkan sinyal deteksi modifikasi data kustom
    function setFlagPerubahan() {
        if (modeGameDariDB === 'kustom_per_paket') {
            formTerdeteksiBerubah = true;
        }
    }

    // Handler Intersepsi Tombol Submit Simpan Utama
    function handleValidasiForm(event) {
        // Jika mode per paket dan terdeteksi perubahan, serta belum diberi izin akses submit
        if (modeGameDariDB === 'kustom_per_paket' && formTerdeteksiBerubah && !aksesIzenSimpan) {
            event.preventDefault(); // Blokir submit form bawaan browser
            modalAlert.style.display = "flex"; // Munculkan Modal Pop-up TopZone
            return false;
        }
        return true;
    }

    // Pengambil Keputusan dari tombol Modal Pop-up (Yes / No)
    function konfirmasiSimpanSistem(statusPilihan) {
        if (statusPilihan === true) {
            aksesIzenSimpan = true; // Berikan izin bypass validasi
            modalAlert.style.display = "none";
            // Trigger submit manual programmatis setelah diizinkan bray
            document.getElementById('form_tambah_paket').submit();
        } else {
            modalAlert.style.display = "none"; // Sembunyikan kembali modal, batalkan proses
        }
    }

    window.onload = function() {
        sinkronisasiTampilanDenganDB();
    };
</script>

</body>
</html>