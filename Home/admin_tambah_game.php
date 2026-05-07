<?php include 'koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Kelola Game</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        
        /* Sidebar */
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }

        /* Main Content */
        .content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); box-sizing: border-box; }
        
        /* Container Form */
        .form-card { background: var(--card); padding: 30px; border-radius: 15px; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--primary); font-size: 14px; font-weight: bold; }
        input, select { width: 100%; padding: 12px; background: #252525; border: 1px solid #444; color: #fff; border-radius: 8px; box-sizing: border-box; }
        
        /* Paket Row Dynamic */
        .paket-row { display: flex; gap: 10px; background: #1a1a1a; padding: 15px; border-radius: 10px; margin-bottom: 10px; border: 1px dashed #444; align-items: center; }
        
        /* Buttons */
        .btn-action { background: var(--primary); color: #000; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; }
        .btn-action:hover { opacity: 0.8; box-shadow: 0 0 15px var(--primary); }
        .btn-add-paket { background: transparent; color: var(--primary); border: 1px solid var(--primary); margin-top: 10px; margin-bottom: 20px; }

        /* Table Style */
        .table-container { background: var(--card); border-radius: 15px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #333; color: var(--primary); font-size: 12px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #2a2a2a; font-size: 14px; }
        .game-img { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; border: 1px solid #444; }
        .btn-small { padding: 6px 12px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 11px; display: inline-block; }
        .btn-edit { border: 1px solid var(--primary); color: var(--primary); }
        .btn-delete { border: 1px solid #ff4444; color: #ff4444; }
        .btn-remove-row { background: #ff4444; color: #fff; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
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
        <h2 style="margin-top: 0;">🚀 Kelola & Tambah Game Baru</h2>
        
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