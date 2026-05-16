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

        /* ==========================================================================
        BODY & ANIMASI LATAR BELAKANG (Liquid Blobs)
        ========================================================================== */
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
        ========================================================================== */
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
        ========================================================================== */
        .content { 
            margin-left: 220px; 
            padding: 30px; 
            width: calc(100% - 220px); 
            box-sizing: border-box; 
        }

        /* ==========================================================================
        CONTAINER FORM (Glassmorphism Card)
        ========================================================================== */
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

        input, select { 
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
        }

        input:focus, select:focus {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(0, 210, 255, 0.4);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.15);
        }

        /* Mengubah warna teks opsi dropdown agar terbaca di background gelap */
        select option {
            background: var(--navy-mid);
            color: #fff;
        }

        /* ==========================================================================
        PAKET ROW DYNAMIC (Fluid Row Style)
        ========================================================================== */
        .paket-row { 
            display: flex; 
            gap: 12px; 
            background: rgba(255, 255, 255, 0.02); 
            padding: 15px; 
            border-radius: 10px; 
            margin-bottom: 12px; 
            border: 1px dashed rgba(0, 210, 255, 0.2); 
            align-items: center; 
            animation: rowFadeIn 0.3s ease both;
        }

        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ==========================================================================
        BUTTONS (Glow & Glass Style)
        ========================================================================== */
        .btn-action { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            border: none; 
            padding: 12px 20px; 
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
            padding: 12px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold;
            transition: all 0.2s ease;
        }

        .btn-remove-row:hover {
            background: #ff4444;
            color: #fff;
            box-shadow: 0 0 12px rgba(255, 68, 68, 0.4);
        }

        /* ==========================================================================
        TABLE STYLE (Glassmorphism Table)
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

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }

        th { 
            text-align: left; 
            padding: 15px; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.06); 
            color: var(--primary); 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }

        td { 
            padding: 15px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
            font-size: 14px; 
            color: var(--text-main);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }

        .game-img { 
            width: 45px; 
            height: 45px; 
            border-radius: 8px; 
            object-fit: cover; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }

        /* Action Buttons Table */
        .btn-small { 
            padding: 6px 14px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 11px; 
            display: inline-block; 
            transition: all 0.2s ease;
        }

        .btn-edit { 
            border: 1px solid var(--primary); 
            color: var(--primary); 
            background: rgba(0, 210, 255, 0.05);
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: var(--primary);
            color: var(--navy-deep);
            box-shadow: 0 0 10px var(--primary-glow);
        }

        .btn-delete { 
            border: 1px solid #ff4444; 
            color: #ff4444; 
            background: rgba(255, 68, 68, 0.05);
        }

        .btn-delete:hover {
            background: #ff4444;
            color: #fff;
            box-shadow: 0 0 10px rgba(255, 68, 68, 0.3);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0, 92, 255, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0, 170, 255, 0.4); }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link">📦 Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link active">🎮 Kelola Game</a>
        <a href="admin_paket.php" class="nav-link">💎 Kelola Paket</a>
        <a href="../Home/Chat/Admin_Chat/admin_chat.php" class="nav-link ">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <h2 style="margin-top: 0;">Kelola & Tambah Game Baru</h2>
        
        <div class="form-card">
            <form action="proses_tambah_game.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Game</label>
                    <input type="text" name="nama_game" id="nama_game" placeholder="Contoh: Mobile Legends" required oninput="buatSlug(this.value)">
                </div>
                
                <!-- INPUT SLUG BARU -->
                <div class="form-group">
                    <label>Slug URL (Otomatis/Custom)</label>
                    <input type="text" name="slug" id="slug" placeholder="Contoh: mobile-legends" required>
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
                    <input type="file" name="gambar" required>
                </div>

                <hr style="border: 0.5px solid #333; margin: 30px 0;">
                <label>💎 Tambah Paket Langsung</label>
                <div id="paket-list">
                    <div class="paket-row">
                        <input type="text" name="p_nama[]" placeholder="Jumlah (ex: 50)" required style="flex: 2;">
                        <input type="text" name="p_harga[]" placeholder="Harga" required 
                            style="flex: 1;" 
                            oninput="formatRupiah(this)">
                        <select name="p_tipe[]" style="flex: 1;">
                            <option value="umum">Umum (ID Only)</option>
                            <option value="login">Roblox Login</option>
                            <option value="5hari">Robux 5 Hari</option>
                        </select>
                        <div style="width: 35px;"></div> 
                    </div>
                </div>
                <button type="button" class="btn-action btn-add-paket" onclick="tambahPaket()">+ Tambah Baris Paket</button>
                <button type="submit" name="simpan" class="btn-action">SIMPAN GAME & PAKET 🔥</button>
            </form>
        </div>

        <h3 style="color: var(--primary); margin-bottom: 15px;">📝 Game Saat Ini</h3>
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
                            <a href="admin_paket.php?id_game=<?= $row['id'] ?>" class="btn-small btn-edit">+ Kelola Paket</a>
                            <a href="hapus_game.php?id=<?= $row['id'] ?>" 
                               class="btn-small btn-delete" 
                               onclick="return confirm('⚠️ Hapus game ini?\nSemua paket di dalamnya juga akan terhapus secara permanen!')">
                               🗑️ Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Fungsi buat bikin slug otomatis pas ngetik Nama Game
        function buatSlug(val) {
            let slug = val.toLowerCase()
                         .replace(/[^\w ]+/g, '')
                         .replace(/ +/g, '-');
            document.getElementById('slug').value = slug;
        }

        function tambahPaket() {
            const container = document.getElementById('paket-list');
            const row = document.createElement('div');
            row.className = 'paket-row';
            row.innerHTML = `
                <input type="text" name="p_nama[]" placeholder="Jumlah" required style="flex: 2;">
                <input type="text" name="p_harga[]" placeholder="Harga" required style="flex: 1;" oninput="formatRupiah(this)">
                <select name="p_tipe[]" style="flex: 1;">
                    <option value="umum">Umum</option>
                    <option value="login">Roblox Login</option>
                    <option value="5hari">Robux 5 Hari</option>
                </select>
                <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">✕</button>
            `;
            container.appendChild(row);
        }

        function formatRupiah(input) {
            let value = input.value.replace(/[^0-9]/g, ''); 
            input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, "."); 
        }
    </script>
</body>
</html>