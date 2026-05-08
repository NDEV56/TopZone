<?php
/**
 * admin_tambah_game.php — HARDENED v3.1
 *   • require_admin
 *   • CSRF token di form
 *   • Prepared SELECT untuk daftar game
 *   • XSS-safe output
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

$games = tz_db()->fetchAll('SELECT * FROM games ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Kelola Game</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }
        .content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); box-sizing: border-box; }
        .form-card { background: var(--card); padding: 30px; border-radius: 15px; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--primary); font-size: 14px; font-weight: bold; }
        input, select { width: 100%; padding: 12px; background: #252525; border: 1px solid #444; color: #fff; border-radius: 8px; box-sizing: border-box; }
        .paket-row { display: flex; gap: 10px; background: #1a1a1a; padding: 15px; border-radius: 10px; margin-bottom: 10px; border: 1px dashed #444; align-items: center; }
        .btn-action { background: var(--primary); color: #000; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; }
        .btn-action:hover { opacity: 0.8; box-shadow: 0 0 15px var(--primary); }
        .btn-add-paket { background: transparent; color: var(--primary); border: 1px solid var(--primary); margin-top: 10px; margin-bottom: 20px; }
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
        <a href="Chat/Admin_Chat/admin_chat.php" class="nav-link ">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <h2 style="margin-top: 0;">🚀 Kelola & Tambah Game Baru</h2>

        <div class="form-card">
            <form action="proses_tambah_game.php" method="POST" enctype="multipart/form-data">
                <?= tz_csrf_field() ?>
                <div class="form-group">
                    <label>Nama Game</label>
                    <input type="text" name="nama_game" id="nama_game" maxlength="64"
                           placeholder="Contoh: Mobile Legends" required oninput="buatSlug(this.value)">
                </div>

                <div class="form-group">
                    <label>Slug URL (Otomatis/Custom)</label>
                    <input type="text" name="slug" id="slug" maxlength="64" pattern="[a-z0-9\-]+"
                           placeholder="Contoh: mobile-legends" required>
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
                    <input type="text" name="tipe_voucher" maxlength="64"
                           placeholder="Contoh: Diamonds, Robux, UC, CP" required>
                </div>
                <div class="form-group">
                    <label>Upload Logo Game (PNG/JPG/JPEG/WEBP/GIF, max 5MB)</label>
                    <input type="file" name="gambar" required accept="image/png,image/jpeg,image/webp,image/gif">
                </div>

                <hr style="border: 0.5px solid #333; margin: 30px 0;">
                <label>💎 Tambah Paket Langsung</label>
                <div id="paket-list">
                    <div class="paket-row">
                        <input type="text" name="p_nama[]" placeholder="Jumlah (ex: 50)" required maxlength="64" style="flex: 2;">
                        <input type="text" name="p_harga[]" placeholder="Harga" required
                            style="flex: 1;" oninput="formatRupiah(this)">
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
                    <?php foreach ($games as $row): ?>
                    <tr>
                        <td><img src="<?= tz_attr($row['gambar']) ?>" class="game-img" alt=""></td>
                        <td>
                            <strong style="color: #fff;"><?= tz_e($row['nama_game']) ?></strong><br>
                            <small style="color: var(--primary); font-size: 10px;"><?= tz_e($row['slug']) ?></small>
                        </td>
                        <td><small style="color: #888;"><?= tz_e($row['kategori']) ?></small></td>
                        <td>
                            <a href="admin_paket.php?id_game=<?= (int)$row['id'] ?>" class="btn-small btn-edit">+ Kelola Paket</a>
                            <a href="hapus_game.php?id=<?= (int)$row['id'] ?>"
                               class="btn-small btn-delete"
                               onclick="return confirm('Hapus game ini?\nSemua paket di dalamnya juga akan terhapus permanen!')">
                               🗑️ Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function buatSlug(val) {
            let slug = val.toLowerCase()
                         .replace(/[^\w ]+/g, '')
                         .replace(/ +/g, '-');
            document.getElementById('slug').value = slug;
        }

        // CSRF token harus ikut form-row dinamis. Tidak perlu, karena hanya 1 form.
        function tambahPaket() {
            const container = document.getElementById('paket-list');
            const row = document.createElement('div');
            row.className = 'paket-row';
            row.innerHTML = `
                <input type="text" name="p_nama[]" placeholder="Jumlah" required maxlength="64" style="flex: 2;">
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
