<?php
/**
 * admin_orders.php — Dashboard pesanan (HARDENED v3.1)
 *   • require_admin
 *   • CSRF + whitelist status
 *   • Prepared SQL untuk update + select
 *   • XSS-safe output
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

// --- LOGIKA UPDATE STATUS ---
if (isset($_POST['update_status'])) {
    tz_csrf_verify();
    $id_order = (int)($_POST['id_order'] ?? 0);
    $status_baru = strtoupper(trim((string)($_POST['status_baru'] ?? '')));
    $allowed = ['PENDING','PROSES','DIKIRIM','SELESAI'];
    if ($id_order > 0 && in_array($status_baru, $allowed, true)) {
        try {
            tz_db()->exec(
                'UPDATE orders SET status = ? WHERE id_order = ?',
                [$status_baru, $id_order]
            );
        } catch (\Throwable $e) {
            error_log('[topzone-admin-orders] ' . $e->getMessage());
        }
    }
    tz_safe_redirect('/Home/admin_orders.php');
}

// --- STATISTIK ---
$total_cuan  = (int)(tz_db()->fetchColumn(
    "SELECT COALESCE(SUM(total_price),0) FROM orders WHERE UPPER(status) = 'SELESAI'"
) ?: 0);
$total_order = (int)tz_db()->fetchColumn('SELECT COUNT(*) FROM orders');
$total_game  = (int)tz_db()->fetchColumn('SELECT COUNT(*) FROM games');

// --- DATA PESANAN ---
$rows = tz_db()->fetchAll('SELECT * FROM orders ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Dashboard</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; transition: 0.3s; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }
        .content { margin-left: 250px; padding: 30px; width: 100%; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 15px; border-left: 4px solid var(--primary); }
        .stat-card small { color: #888; font-size: 12px; }
        .stat-card h3 { margin: 5px 0 0; color: #fff; }
        .table-container { background: var(--card); border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #333; color: var(--primary); text-transform: uppercase; font-size: 11px; }
        td { padding: 15px; border-bottom: 1px solid #2a2a2a; }
        .acc-box { background: #111; padding: 10px; border-radius: 8px; border: 1px solid #444; font-size: 12px; font-family: 'Consolas', monospace; }
        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: bold; display: inline-block; }
        .pending { background: #f39c12; color: #fff; }
        .proses { background: #3498db; color: #fff; }
        .dikirim { background: #9b59b6; color: #fff; }
        .selesai { background: #2ecc71; color: #fff; }
        .btn-add { background: var(--primary); color: #000; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; float: right; margin-bottom: 20px; font-size: 14px; transition: 0.3s; }
        .btn-add:hover { background: #00cc6d; }
        .btn-set { background: var(--primary); color: #000; border: none; padding: 6px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        select { background: #252525; color: #fff; border: 1px solid #444; padding: 6px; border-radius: 6px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link active">📦 Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link">🎮 Kelola Game</a>
        <a href="admin_paket.php" class="nav-link">💎 Kelola Paket</a>
        <a href="Chat/Admin_Chat/admin_chat.php" class="nav-link ">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <a href="admin_tambah_game.php" class="btn-add">+ Tambah Game Baru</a>
        <h2 style="margin-top: 0;">🚀 Dashboard Admin</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <small>TOTAL ORDER</small>
                <h3><?= tz_e($total_order) ?> Pesanan</h3>
            </div>
            <div class="stat-card">
                <small>TOTAL CUAN (SELESAI)</small>
                <h3>Rp <?= tz_e(number_format($total_cuan)) ?></h3>
            </div>
            <div class="stat-card">
                <small>GAME AKTIF</small>
                <h3><?= tz_e($total_game) ?> Game</h3>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Game & Paket</th>
                        <th>Detail Akun</th>
                        <th>Total Harga</th>
                        <th>Status & Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong style="color: #fff;"><?= tz_e($row['game_name']) ?></strong><br>
                            <small style="color: var(--primary);"><?= tz_e($row['paket']) ?></small>
                        </td>
                        <td>
                            <div class="acc-box">
                                <?php
                                $acc = json_decode((string)$row['catatan'], true);
                                if (is_array($acc)) {
                                    foreach ($acc as $k => $v) {
                                        echo '<b>' . tz_e($k) . ':</b> ' . tz_e($v) . '<br>';
                                    }
                                } else {
                                    echo tz_e($row['catatan']);
                                }
                                ?>
                            </div>
                        </td>
                        <td><b style="color: #fff;">Rp <?= tz_e(number_format((int)$row['total_price'])) ?></b></td>
                        <td>
                            <form method="POST">
                                <?= tz_csrf_field() ?>
                                <input type="hidden" name="id_order" value="<?= tz_attr($row['id_order']) ?>">
                                <span class="status-badge <?= tz_attr(strtolower((string)$row['status'])) ?>">
                                    <?= tz_e($row['status']) ?>
                                </span>
                                <div style="margin-top: 10px; display: flex; gap: 8px;">
                                    <select name="status_baru">
                                        <?php foreach (['PENDING','PROSES','DIKIRIM','SELESAI'] as $opt): ?>
                                            <option value="<?= tz_attr($opt) ?>" <?= strtoupper((string)$row['status']) === $opt ? 'selected' : '' ?>>
                                                <?= tz_e(ucfirst(strtolower($opt))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-set">SET</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
