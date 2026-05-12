<?php
/**
 * admin_paket.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared SQL semua
 *   • Hapus sekarang via POST + CSRF (sebelumnya GET ?hapus=ID — CSRFable)
 *   • XSS-safe
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    tz_csrf_verify();
    $id_paket = (int)$_POST['hapus_id'];
    if ($id_paket > 0) {
        try {
            tz_db()->exec('DELETE FROM produk_game WHERE id = ?', [$id_paket]);
        } catch (\Throwable $e) {
            error_log('[topzone-admin-paket] ' . $e->getMessage());
        }
    }
    tz_safe_redirect('/Home/admin_paket.php');
}

$games = tz_db()->fetchAll('SELECT * FROM games ORDER BY nama_game ASC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Paket - TopZone</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; --accent: #252525; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; z-index: 100; box-sizing: border-box; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; letter-spacing: 1px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }
        .content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); box-sizing: border-box; min-height: 100vh; }
        .game-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; align-items: start; }
        .game-card { background: var(--card); border-radius: 12px; overflow: hidden; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .game-header { background: var(--accent); padding: 18px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
        .game-info { display: flex; align-items: center; gap: 12px; }
        .game-info img { width: 45px; height: 45px; border-radius: 10px; object-fit: cover; border: 1px solid #444; }
        .game-info h3 { margin: 0; font-size: 16px; color: #fff; font-weight: 600; }
        .slug-tag { display: block; font-size: 10px; color: var(--primary); opacity: 0.7; font-family: 'Consolas', monospace; margin-top: 2px; }
        .paket-table { width: 100%; border-collapse: collapse; }
        .paket-table th { background: #181818; padding: 12px 15px; color: var(--primary); text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .paket-table td { padding: 14px 15px; border-bottom: 1px solid #2a2a2a; color: #ccc; font-size: 13.5px; }
        .paket-table tr:last-child td { border-bottom: none; }
        .btn-add-item { background: var(--primary); color: #000; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 800; }
        .btn-add-item:hover { background: #fff; }
        .action-btns { display: flex; gap: 15px; align-items: center; justify-content: flex-end; }
        .btn-edit-item { color: var(--primary); text-decoration: none; font-size: 12px; font-weight: bold; }
        .btn-del-item { background: none; border: none; color: #ff4d4d; font-size: 12px; font-weight: bold; cursor: pointer; padding: 0; font-family: inherit; }
        .btn-edit-item:hover { color: #fff; }
        .btn-del-item:hover { color: #fff; text-shadow: 0 0 5px #ff4d4d; }
        .empty-state { padding: 40px; text-align: center; color: #666; font-style: italic; font-size: 13px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link">📦 Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link">🎮 Kelola Game</a>
        <a href="admin_paket.php" class="nav-link active">💎 Kelola Paket</a>
        <a href="Chat/Admin_Chat/admin_chat.php" class="nav-link ">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <h2 style="margin-bottom: 30px; font-weight: 700;">💎 Management Paket Produk</h2>

        <div class="game-grid">
            <?php foreach ($games as $g):
                $id_game    = (int)$g['id'];
                $paket_list = tz_db()->fetchAll(
                    'SELECT * FROM produk_game WHERE id_game = ? ORDER BY tipe ASC, harga ASC',
                    [$id_game]
                );
            ?>
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-info">
                            <img src="<?= tz_attr($g['gambar']) ?>" alt="">
                            <div>
                                <h3><?= tz_e($g['nama_game']) ?></h3>
                                <span class="slug-tag">/<?= tz_e($g['slug']) ?></span>
                            </div>
                        </div>
                        <a href="admin_tambah_paket.php?game=<?= rawurlencode((string)$g['slug']) ?>" class="btn-add-item">+ ITEM</a>
                    </div>

                    <table class="paket-table">
                        <thead>
                            <tr>
                                <th width="50%">ITEM</th>
                                <th width="30%">HARGA</th>
                                <th style="text-align:right; padding-right:15px;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($paket_list) > 0):
                                $current_tipe = '';
                                foreach ($paket_list as $p):
                                    $tipe_p = (string)$p['tipe'];
                                    if ($tipe_p !== $current_tipe) {
                                        $current_tipe = $tipe_p;
                                        $hl = ''; $hc = '';
                                        if ($tipe_p === 'roblox_login')      { $hl = '--- KATEGORI: VIA LOGIN (ROBLOX) ---'; $hc = '#ff4d4d'; }
                                        elseif ($tipe_p === 'roblox_5hari')  { $hl = '--- KATEGORI: 5 HARI / GIFT (ROBLOX) ---'; $hc = '#2ecc71'; }
                                        if ($hl !== '') {
                                            echo "<tr><td colspan='3' style='background: rgba(255,255,255,0.05); color: " . tz_attr($hc) . "; font-weight: bold; text-align: center; font-size: 12px; letter-spacing: 1px; padding: 10px 0;'>" . tz_e($hl) . "</td></tr>";
                                        }
                                    }
                            ?>
                                    <tr>
                                        <td><strong style="color: #fff;"><?= tz_e($p['nama_produk']) ?></strong></td>
                                        <td style="color: var(--primary); font-family: monospace; font-weight: bold;">
                                            Rp <?= tz_e(number_format((int)$p['harga'], 0, ',', '.')) ?>
                                        </td>
                                        <td style="text-align:right; padding-right:15px;">
                                            <div class="action-btns">
                                                <a href="admin_edit_paket.php?id=<?= (int)$p['id'] ?>" class="btn-edit-item">Edit</a>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus paket ini?')">
                                                    <?= tz_csrf_field() ?>
                                                    <input type="hidden" name="hapus_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn-del-item">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="3" class="empty-state">Belum ada paket tersedia.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>
