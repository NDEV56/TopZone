<?php include 'koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Kelola Game</title>
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
            min-height: 100vh;
            display: flex; 
            position: relative;
            overflow-x: hidden;
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
        SIDEBAR UTAMA
        ========================================================================= */
        .sidebar { 
            width: 220px; 
            height: 100vh; 
            background: rgba(3, 8, 24, 0.75);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 20px 15px; 
            position: fixed; 
            border-right: 1px solid var(--glass-border); 
            z-index: 100; 
        }

        .sidebar h1 { 
            color: var(--text-main); 
            font-size: 20px; 
            margin-bottom: 25px; 
            letter-spacing: 1px; 
            font-weight: 700;
        }

        .nav-link { 
            display: block; 
            color: var(--text-muted); 
            text-decoration: none; 
            padding: 12px 15px; 
            border-radius: 12px; 
            transition: all 0.25s ease; 
            margin-bottom: 8px; 
            font-size: 14px; 
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active { 
            background: rgba(0, 92, 255, 0.15); 
            color: var(--primary); 
            border-left: 3px solid var(--primary);
            padding-left: 18px;
            box-shadow: 0 4px 15px rgba(0, 92, 255, 0.15);
        }

        /* ==========================================================================
        MAIN CONTENT VIEW
        ========================================================================= */
        .content { 
            margin-left: 220px; 
            padding: 30px; 
            width: calc(100% - 220px); 
            box-sizing: border-box; 
        }

        .form-card { 
            background: rgba(11, 23, 58, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 30px; 
            border-radius: 15px; 
            margin-bottom: 40px; 
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
        }

        .form-group { 
            margin-bottom: 20px; 
        }

        label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--primary); 
            font-size: 14px; 
            font-weight: bold; 
            text-shadow: 0 0 8px rgba(0, 210, 255, 0.2);
        }

        input, select, textarea { 
            width: 100%; 
            padding: 12px; 
            background: rgba(255, 255, 255, 0.04); 
            border: 1px solid var(--glass-border); 
            color: #fff; 
            border-radius: 8px; 
            box-sizing: border-box; 
            outline: none;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(0, 210, 255, 0.4);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.15);
        }

        select option {
            background: var(--navy-mid);
            color: #fff;
        }

        /* ==========================================================================
        BAGIAN UPLOAD GAMBAR: STYLING CLEAN, ANIMASI & PREVIEW
        ========================================================================== */
        .upload-container {
            width: 100%;
            margin-top: 5px;
        }

        /* Visual Box Dropzone Kustom */
        .image-upload-box {
            position: relative;
            width: 100%;
            min-height: 150px;
            border: 2px dashed rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 20px;
            box-sizing: border-box;
        }

        /* Efek Hover Glowing Area Upload */
        .image-upload-box:hover {
            border-color: var(--primary);
            background: rgba(0, 210, 255, 0.03);
            box-shadow: 0 0 25px rgba(0, 210, 255, 0.15);
        }

        /* Sembunyikan input asli bawaan browser agar clean */
        #hidden-file-input {
            display: none !important;
        }

        /* Placeholder awal (Text & Ikon) */
        .upload-placeholder {
            text-align: center;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .upload-icon {
            font-size: 36px;
            margin-bottom: 8px;
            text-shadow: 0 0 10px var(--primary-glow);
            display: inline-block;
            animation: bounceIcon 3s infinite ease-in-out;
        }

        @keyframes bounceIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .upload-text {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
        }

        /* Container Live Preview */
        .preview-image-container {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            display: none;
            opacity: 0;
            background: var(--navy-mid);
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            padding: 10px;
            box-sizing: border-box;
        }

        /* Image Object Preview */
        #img-live-preview {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 5px 25px rgba(0,0,0,0.5);
        }

        /* Floating Badge Trigger Ganti Gambar */
        .btn-change-image {
            position: absolute;
            bottom: 10px;
            background: rgba(0, 0, 0, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            transition: all 0.2s ease;
            letter-spacing: 0.5px;
        }
        .image-upload-box:hover .btn-change-image {
            background: var(--topzone-blue);
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(0, 92, 255, 0.5);
        }

        /* Animasi Transisi saat File Dimasukkan */
        @keyframes previewScaleIn {
            from { opacity: 0; transform: scale(0.93); }
            to { opacity: 1; transform: scale(1); }
        }

        /* State ketika file telah dipilih admin */
        .image-upload-box.has-file .upload-placeholder {
            opacity: 0;
            transform: scale(0.8);
        }
        .image-upload-box.has-file .preview-image-container {
            display: flex;
            opacity: 1;
            animation: previewScaleIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        /* ==========================================================================
        DESAIN SAKLAR / TOGGLE SWITCH INTERAKTIF & GLOWING
        ========================================================================== */
        .switch-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
            background: rgba(255,255,255,0.02);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
            width: fit-content;
        }

        .switch-label-text {
            font-size: 13px;
            font-weight: bold;
            transition: all 0.3s ease;
            min-width: 130px;
        }

        .glow-active-left {
            color: #00ffaa !important;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.6), 0 0 20px rgba(0, 255, 170, 0.2);
        }

        .glow-active-right {
            color: #ffaa00 !important;
            text-shadow: 0 0 10px rgba(255, 170, 0, 0.6), 0 0 20px rgba(255, 170, 0, 0.2);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .3s ease;
            border-radius: 34px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s ease;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        input:checked + .slider {
            background-color: #ffaa00;
            box-shadow: 0 0 10px rgba(255, 170, 0, 0.4);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        /* ==========================================================================
        PAKET & SUB-CUSTOM FIELDS DYNAMIC STYLE
        ========================================================================= */
        .paket-row { 
            display: flex; 
            gap: 12px; 
            background: rgba(255, 255, 255, 0.02); 
            padding: 15px; 
            border-radius: 10px; 
            margin-bottom: 15px; 
            border: 1px dashed rgba(0, 210, 255, 0.2); 
            align-items: flex-start; 
            animation: rowFadeIn 0.3s ease both;
        }

        .custom-fields-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 5px;
            width: 100%;
        }

        .custom-input-item {
            display: flex;
            gap: 5px;
            align-items: center;
            width: 100%;
            animation: rowFadeIn 0.2s ease;
        }

        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .global-custom-box {
            background: rgba(0, 210, 255, 0.03);
            border: 1px solid rgba(0, 210, 255, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            animation: rowFadeIn 0.3s ease;
        }

        /* ==========================================================================
        BUTTONS ACCENTS
        ========================================================================== */
        .btn-action { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            border: none; 
            padding: 14px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            width: 100%; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
        }

        .btn-action:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45); 
        }

        .btn-add-paket { 
            background: rgba(0, 210, 255, 0.05); 
            color: var(--primary); 
            border: 1px solid var(--primary); 
            margin-top: 10px; 
            margin-bottom: 20px; 
        }

        .btn-add-paket:hover {
            background: var(--primary);
            color: var(--navy-deep);
            box-shadow: 0 0 15px var(--primary);
        }

        .btn-remove-row { 
            background: rgba(255, 68, 68, 0.1); 
            color: #ff4444; 
            border: 1px solid rgba(255, 68, 68, 0.3); 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold;
            height: 44px;
            width: 44px;
            flex-shrink: 0;
        }

        .btn-remove-row:hover:not([disabled]) {
            background: #ff4444;
            color: #fff;
        }

        .btn-custom-action {
            padding: 5px 10px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            height: 33px;
            box-shadow: none;
            min-width: 33px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-custom-add {
            background: rgba(0, 210, 255, 0.15);
            color: var(--primary);
            border: 1px solid rgba(0, 210, 255, 0.3);
        }
        .btn-custom-add:hover { background: var(--primary); color: var(--navy-deep); }

        .btn-custom-remove {
            background: rgba(255, 68, 68, 0.15);
            color: #ff4444;
            border: 1px solid rgba(255, 68, 68, 0.3);
        }
        .btn-custom-remove:hover { background: #ff4444; color: #fff; }

        /* ==========================================================================
        TABLE STYLE
        ========================================================================== */
        .table-container { 
            background: rgba(11, 23, 58, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 15px; 
            padding: 20px; 
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid rgba(255, 255, 255, 0.06); color: var(--primary); font-size: 12px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); font-size: 14px; color: var(--text-main); vertical-align: middle; }
        .game-img { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; }
        .btn-small { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-edit { border: 1px solid var(--primary); color: var(--primary); background: rgba(0, 210, 255, 0.05); margin-right: 5px; }
        .btn-edit:hover { background: var(--primary); color: var(--navy-deep); }
        .btn-delete { border: 1px solid #ff4444; color: #ff4444; background: rgba(255, 68, 68, 0.05); }
        .btn-delete:hover { background: #ff4444; color: #fff; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link"> Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link active"> Kelola Game</a>
        <a href="admin_paket.php" class="nav-link"> Kelola Paket</a>
        <a href="../Home/Chat/Admin_Chat/admin_chat.php" class="nav-link "> Chat Pelanggan</a>
        <a href="index.php" class="nav-link"> Lihat Website</a>
    </div>

    <div class="content">
        <h2 style="margin-top: 0; margin-bottom: 20px;">Kelola & Tambah Game Baru</h2>
        
        <div class="form-card">
            <form action="proses_tambah_game.php" method="POST" enctype="multipart/form-data" onsubmit="cleanRupiahBeforeSubmit()">
                <div class="form-group">
                    <label>Nama Game</label>
                    <input type="text" name="nama_game" id="nama_game" placeholder="Contoh: Mobile Legends" required oninput="buatSlug(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Slug URL (Otomatis/Custom)</label>
                    <input type="text" name="slug" id="slug" placeholder="Contoh: mobile-legends" required>
                </div>

                <div class="form-group">
                    <label>Deskripsi Game (Opsional)</label>
                    <textarea name="deskripsi" rows="4" placeholder="Tulis deskripsi / petunjuk top up game di sini bray..."></textarea>
                </div>

                <div class="form-group">
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="Game">General Game</option>
                        <option value="MOBA">MOBA</option>
                        <option value="FPS">FPS</option>
                        <option value="Open World">Open World</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mata Uang / Merk Voucher</label>
                    <input type="text" name="tipe_voucher" placeholder="Contoh: Diamonds, Robux, UC, CP" required>
                </div>

                <div class="form-group">
                    <label>Upload Logo Game (Gambar)</label>
                    <div class="upload-container">
                        <div class="image-upload-box" id="upload-box" onclick="document.getElementById('hidden-file-input').click()">
                            
                            <div class="upload-placeholder">
                                <div class="upload-icon">📸</div>
                                <div class="upload-text">Klik atau seret logo game kesini bray...</div>
                            </div>

                            <div class="preview-image-container">
                                <img id="img-live-preview" src="#" alt="Preview">
                                <div class="btn-change-image">Ganti Gambar</div>
                            </div>
                        </div>

                        <input type="file" name="gambar" id="hidden-file-input" accept="image/*" required onchange="handleLivePreview(this)">
                    </div>
                </div>

                <div class="form-group" id="wrapper_cakupan_global" style="display: none;">
                    <label style="color: #ffaa00;">⚙️ Cakupan Format Form Input User (Kustom)</label>
                    <div class="global-custom-box">
                        <label style="font-size: 12px; margin-bottom: 5px;">Setel Format Input Global Game Ini:</label>
                        <div class="custom-fields-container">
                            <div class="custom-input-item">
                                <input type="text" name="g_label_custom[]" id="g_input_pertama" placeholder="Contoh: ID User" style="padding: 10px; font-size: 13px;">
                                <button type="button" class="btn-custom-action btn-custom-add" onclick="tambahInputGlobalField(this)">+</button>
                            </div>
                        </div>

                        <div class="switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" id="switch_segame" name="cakupan_switch" value="aktif" onchange="handleSwitchCakupan()">
                                <span class="slider"></span>
                            </label>
                            <span id="text_status_switch" class="switch-label-text glow-active-left">Data Satu Game</span>
                        </div>
                    </div>
                </div>

                <hr style="border: 0.5px solid #333; margin: 30px 0;">
                <label> Tambah Paket Langsung (Opsional)</label>
                
                <div id="paket-list">
                    <div class="paket-row" style="flex-wrap: wrap;">
                        <input type="text" name="p_nama[]" class="p-input" placeholder="Jumlah (ex: 50)" style="flex: 2; min-width: 150px;" oninput="cekValidasiPaket(this)">
                        <input type="text" name="p_harga[]" class="p-input input-harga-paket" placeholder="Harga" style="flex: 1; min-width: 100px;" oninput="formatRupiah(this); cekValidasiPaket(this)">
                        
                        <div class="wrapper-select-tipe" style="flex: 1.5; min-width: 220px;">
                            <select name="p_tipe[]" class="select-tipe-paket" onchange="cekTipePaket()">
                                <option value="umum">Umum (ID Only)</option>
                                <option value="roblox_login">Roblox Login</option>
                                <option value="robux_5_hari">Robux 5 Hari</option>
                                <option value="custom">Kustom (Punya Form Sendiri)</option>
                            </select>
                        </div>
                        
                        <button type="button" class="btn-remove-row" style="opacity: 0.2; cursor: not-allowed;" disabled onclick="hapusBaris(this)">✕</button> 
                    </div>
                </div>

                <button type="button" class="btn-action btn-add-paket" onclick="tambahPaket()">+ Tambah Baris Paket</button>
                <button type="submit" name="simpan" class="btn-action">SIMPAN GAME & PAKET</button>
            </form>
        </div>

        <h3 style="color: var(--primary); margin-bottom: 15px;"> Game Saat Ini</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Nama Game / Slug</th>
                        <th>Kategori</th>
                        <th>Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = mysqli_query($koneksi, "SELECT * FROM games ORDER BY id DESC");
                    while($row = mysqli_fetch_assoc($res)):
                    ?>
                    <tr>
                        <td><img src="<?= $row['gambar'] ?>" class="game-img"></td>
                        <td>
                            <strong style="color: #fff;"><?= $row['nama_game'] ?></strong><br>
                            <small style="color: var(--primary); font-size: 10px;"><?= $row['slug'] ?></small>
                        </td>
                        <td><small style="color: #888;"><?= $row['kategori'] ?></small></td>
                        <td>
                            <a href="admin_paket.php?game=<?= $row['slug'] ?>" class="btn-small btn-edit">➕ Kelola Paket</a>
                            <a href="hapus_game.php?id=<?= $row['id'] ?>" class="btn-small btn-delete" onclick="return confirm('⚠️ Hapus game ini?')">🗑️ Hapus Game</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function buatSlug(val) {
            let slug = val.toLowerCase().replace(/[^\w ]+/g, '').replace(/ +/g, '-');
            document.getElementById('slug').value = slug;
        }

        /* FUNGSI UNTUK MENANGANI LIVE PREVIEW GAMBAR */
        function handleLivePreview(input) {
            const uploadBox = document.getElementById('upload-box');
            const previewImg = document.getElementById('img-live-preview');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    uploadBox.classList.add('has-file'); // Memicu animasi & transisi CSS
                }

                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '#';
                uploadBox.classList.remove('has-file');
            }
        }

        // Interaksi dinamis paket bawaan
        function cekValidasiPaket(el) {
            const row = el.closest('.paket-row');
            const inputs = row.querySelectorAll('.p-input');
            
            let adaIsinya = false;
            inputs.forEach(input => {
                if(input.value.trim() !== '') adaIsinya = true;
            });

            inputs.forEach(input => {
                if(adaIsinya) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            });
        }

        function cekTipePaket() {
            const wrapperGlobal = document.getElementById('wrapper_cakupan_global');
            const gInputPertama = document.getElementById('g_input_pertama');
            const semuaSelectPaket = document.querySelectorAll('.select-tipe-paket');
            
            let adaKustom = false;
            semuaSelectPaket.forEach(select => {
                if (select.value === 'custom') {
                    adaKustom = true;
                }
            });

            if (adaKustom) {
                wrapperGlobal.style.display = 'block';
                gInputPertama.setAttribute('required', 'required');
                handleSwitchCakupan();
            } else {
                wrapperGlobal.style.display = 'none';
                gInputPertama.removeAttribute('required');
                gInputPertama.value = '';
                resetFieldGlobalTambahan();
                
                const semuaSelectWrapper = document.querySelectorAll('.wrapper-select-tipe');
                allSelectWrapper.forEach(el => el.style.display = 'block');
            }
        }

        function handleSwitchCakupan() {
            const switchEl = document.getElementById('switch_segame');
            const statusText = document.getElementById('text_status_switch');
            const semuaSelectWrapper = document.querySelectorAll('.wrapper-select-tipe');

            if (!switchEl.checked) {
                statusText.innerText = "Data Satu Game";
                statusText.className = "switch-label-text glow-active-left";
                semuaSelectWrapper.forEach(el => {
                    el.style.display = 'none';
                });
            } else {
                statusText.innerText = "Data Non-Segame";
                statusText.className = "switch-label-text glow-active-right";
                semuaSelectWrapper.forEach(el => {
                    el.style.display = 'block';
                });
            }
        }

        function tambahInputGlobalField(btn) {
            const container = btn.closest('.custom-fields-container');
            const newItem = document.createElement('div');
            newItem.className = 'custom-input-item';
            newItem.innerHTML = `
                <input type="text" name="g_label_custom[]" placeholder="Format Tambahan (ex: Server)" required style="padding: 10px; font-size: 13px;">
                <button type="button" class="btn-custom-action btn-custom-remove" onclick="hapusInputKustomField(this)">-</button>
            `;
            container.appendChild(newItem);
        }

        function hapusInputKustomField(btn) {
            btn.closest('.custom-input-item').remove();
        }

        function resetFieldGlobalTambahan() {
            const container = document.querySelector('.global-custom-box .custom-fields-container');
            const items = container.querySelectorAll('.custom-input-item');
            for(let i = 1; i < items.length; i++) {
                items[i].remove();
            }
        }

        function tambahPaket() {
            const container = document.getElementById('paket-list');
            const switchEl = document.getElementById('switch_segame');
            const wrapperGlobal = document.getElementById('wrapper_cakupan_global');

            let harusSembunyi = (!switchEl.checked && wrapperGlobal.style.display === 'block');

            const row = document.createElement('div');
            row.className = 'paket-row';
            row.style.flexWrap = 'wrap';
            row.innerHTML = `
                <input type="text" name="p_nama[]" class="p-input" placeholder="Jumlah (ex: 50)" style="flex: 2; min-width: 150px;" oninput="cekValidasiPaket(this)">
                <input type="text" name="p_harga[]" class="p-input input-harga-paket" placeholder="Harga" style="flex: 1; min-width: 100px;" oninput="formatRupiah(this); cekValidasiPaket(this)">
                
                <div class="wrapper-select-tipe" style="flex: 1.5; min-width: 220px; display: ${harusSembunyi ? 'none' : 'block'};">
                    <select name="p_tipe[]" class="select-tipe-paket" onchange="cekTipePaket()">
                        <option value="umum">Umum (ID Only)</option>
                        <option value="roblox_login">Roblox Login</option>
                        <option value="robux_5_hari">Robux 5 Hari</option>
                        <option value="custom">Kustom (Punya Form Sendiri)</option>
                    </select>
                </div>
                
                <button type="button" class="btn-remove-row" onclick="hapusBaris(this)">✕</button>
            `;
            
            container.appendChild(row);
            cekStatusTombolHapus();
        }

        function hapusBaris(btn) {
            const container = document.getElementById('paket-list');
            if (container.children.length > 1) {
                btn.parentElement.remove();
            }
            cekStatusTombolHapus();
            cekTipePaket();
        }

        function cekStatusTombolHapus() {
            const container = document.getElementById('paket-list');
            const semuaTombol = container.querySelectorAll('.btn-remove-row');
            if (container.children.length === 1) {
                let allBtn = semuaTombol[0];
                allBtn.setAttribute('disabled', 'disabled');
                allBtn.style.opacity = '0.2';
                allBtn.style.cursor = 'not-allowed';
            } else {
                semuaTombol.forEach(btn => { 
                    btn.removeAttribute('disabled'); 
                    btn.style.opacity = '1'; 
                    btn.style.cursor = 'pointer';
                });
            }
        }

        function formatRupiah(input) {
            let value = input.value.replace(/[^0-9]/g, ''); 
            input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, "."); 
        }

        function cleanRupiahBeforeSubmit() {
            const hargaInputs = document.querySelectorAll('.input-harga-paket');
            hargaInputs.forEach(input => {
                input.value = input.value.replace(/[^0-9]/g, '');
            });

            const wrapperGlobal = document.getElementById('wrapper_cakupan_global');
            const switchEl = document.getElementById('switch_segame');
            
            if (wrapperGlobal.style.display === 'block' && !switchEl.checked) {
                const semuaSelectPaket = document.querySelectorAll('.select-tipe-paket');
                semuaSelectPaket.forEach(select => {
                    select.value = 'custom';
                });
            }
        }
    </script>
</body>
</html>