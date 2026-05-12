<?php
/**
 * admin_orders.php — HARDENED v3.1 (sync NAFI: search + grouping)
 *   • require_admin
 *   • Prepared statements (untuk update + search + statistik)
 *   • CSRF check di update status
 *   • Whitelist status
 *   • XSS-safe output
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

// --- 1. LOGIKA UPDATE STATUS (prepared + whitelist + CSRF) ---
if (isset($_POST['update_status'])) {
    tz_csrf_verify();
    $id_order    = (int)($_POST['id_order'] ?? 0);
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

// --- 2. LOGIKA SEARCH (prepared) ---
$search_query = '';
$rows = [];

try {
    $q_search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    if ($q_search !== '' && strlen($q_search) <= 64) {
        $search_query = $q_search;
        // Escape LIKE special chars
        $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q_search) . '%';
        $maybeId = ctype_digit($q_search) ? (int)$q_search : 0;
        $rows = tz_db()->fetchAll(
            'SELECT o.*, u.username FROM orders o
             LEFT JOIN users u ON o.id_user = u.id
             WHERE u.username LIKE ? OR o.id_user = ?
             ORDER BY o.id_user DESC, o.created_at DESC
             LIMIT 500',
            [$like, $maybeId]
        );
    } else {
        $rows = tz_db()->fetchAll(
            'SELECT o.*, u.username FROM orders o
             LEFT JOIN users u ON o.id_user = u.id
             ORDER BY o.id_user DESC, o.created_at DESC
             LIMIT 500'
        );
    }
} catch (\Throwable $e) {
    error_log('[topzone-admin-orders] ' . $e->getMessage());
    $rows = [];
}

// --- 3. STATISTIK (prepared) ---
$total_cuan  = (int)(tz_db()->fetchColumn(
    "SELECT COALESCE(SUM(total_price),0) FROM orders WHERE UPPER(status) = 'SELESAI'"
) ?: 0);
$total_order = (int)tz_db()->fetchColumn('SELECT COUNT(*) FROM orders');
$total_game  = (int)tz_db()->fetchColumn('SELECT COUNT(*) FROM games');

$jumlah_data = count($rows);

// Sediakan mysqli result-like iteration untuk template
// Konversi $rows array ke generator function untuk template di bawah
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Dashboard</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        
        .sidebar { width: 220px; height: 100vh; background: #000; padding: 15px; position: fixed; border-right: 1px solid #333; z-index: 100; }
        .sidebar h1 { color: var(--primary); font-size: 20px; margin-bottom: 25px; letter-spacing: 1px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px; transition: 0.3s; margin-bottom: 5px; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }

        .content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        
        /* Search Bar Style */
        .search-container { margin-bottom: 25px; display: flex; gap: 10px; }
        .search-input { background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 12px 20px; border-radius: 10px; width: 300px; outline: none; transition: 0.3s; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 10px rgba(0, 255, 136, 0.2); }
        .btn-search { background: var(--primary); color: #000; border: none; padding: 0 20px; border-radius: 10px; font-weight: bold; cursor: pointer; }
        .btn-reset { background: #333; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-size: 14px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 15px; border-left: 4px solid var(--primary); }
        .stat-card small { color: #888; font-size: 12px; }
        .stat-card h3 { margin: 5px 0 0; color: #fff; }

        /* Empty State */
        .empty-state { text-align: center; padding: 50px; background: var(--card); border-radius: 15px; border: 2px dashed #444; margin-top: 20px; }
        .empty-state h2 { color: #555; margin-bottom: 10px; }
        .empty-state p { color: var(--primary); font-family: 'Courier New', monospace; font-weight: bold; font-size: 20px; }

        /* Grouping Styles */
        .user-group-wrapper { margin-bottom: 50px; }
        .user-header { text-align: center; background: #000; padding: 15px; border-radius: 15px 15px 0 0; border: 1px solid #333; }
        .user-header h3 { margin: 0; color: var(--primary); text-transform: uppercase; letter-spacing: 2px; font-size: 18px; }
        .user-header span { color: #666; font-family: monospace; font-size: 11px; }

        .table-container { background: var(--card); border-radius: 0 0 15px 15px; padding: 20px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: center; padding: 12px; border-bottom: 2px solid #333; color: var(--primary); text-transform: uppercase; font-size: 10px; }
        td { padding: 15px; border-bottom: 1px solid #2a2a2a; text-align: center; font-size: 13px; }
        
        .qty-badge { background: #00ff8822; color: var(--primary); padding: 3px 8px; border-radius: 4px; font-weight: bold; border: 1px solid var(--primary); }
        .acc-box { background: #111; padding: 10px; border-radius: 8px; border: 1px solid #444; font-size: 11px; font-family: 'Consolas', monospace; color: #ccc; text-align: left; }
        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: bold; display: inline-block; text-transform: uppercase; }
        .pending { background: #f39c12; color: #fff; }
        .proses { background: #3498db; color: #fff; }
        .selesai { background: #2ecc71; color: #fff; }
        .dikirim { background: #9b59b6; color: #fff; }

        .btn-add { background: var(--primary); color: #000; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; float: right; }
        .btn-set { background: var(--primary); color: #000; border: none; padding: 6px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        select { background: #252525; color: #fff; border: 1px solid #444; padding: 6px; border-radius: 6px; font-size: 12px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link active">📦 Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link">🎮 Kelola Game</a>
        <a href="admin_paket.php" class="nav-link">💎 Kelola Paket</a>
        <a href="../Home/Chat/Admin_Chat/admin_chat.php" class="nav-link">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <a href="admin_tambah_game.php" class="btn-add">+ Tambah Game Baru</a>
        <h2 style="margin-top: 0;">Dashboard Admin</h2>

        <!-- SEARCH FORM -->
        <form method="GET" class="search-container">
            <input type="text" name="search" class="search-input" maxlength="64" placeholder="Cari Nama User atau ID..." value="<?= tz_attr($search_query) ?>">
            <button type="submit" class="btn-search">CARI</button>
            <?php if(!empty($search_query)): ?>
                <a href="admin_orders.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>

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

        <?php if ($jumlah_data > 0): ?>
            <?php
            $current_user = null;
            foreach ($rows as $row):
                if ($current_user !== $row['id_user']):
                    if ($current_user !== null) echo '</tbody></table></div></div>';
                    $current_user = $row['id_user'];
            ?>
                <div class="user-group-wrapper">
                    <div class="user-header">
                        <h3><?= tz_e($row['username'] ?? 'GUEST') ?></h3>
                        <span>USER ID: #<?= tz_e($row['id_user'] ?? 'N/A') ?></span>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 20%;">GAME & PAKET</th>
                                    <th style="width: 10%;">JUMLAH</th>
                                    <th style="width: 35%;">DETAIL AKUN</th>
                                    <th style="width: 15%;">TOTAL HARGA</th>
                                    <th style="width: 20%;">STATUS & AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                <?php endif; ?>

                <tr>
                    <td>
                        <strong style="color: #fff;"><?= tz_e($row['game_name']) ?></strong><br>
                        <small style="color: var(--primary); font-size: 10px;">#ORD-<?= (int)$row['id_order'] ?> | <?= tz_e($row['paket']) ?></small>
                    </td>
                    <td><span class="qty-badge"><?= (int)$row['item_count'] ?>x</span></td>
                    <td>
                        <div class="acc-box">
                            <?php
                            $acc = json_decode((string)$row['catatan'], true);
                            if (is_array($acc)) {
                                foreach ($acc as $k => $v) echo '<b>' . tz_e($k) . ':</b> ' . tz_e($v) . '<br>';
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
                            <input type="hidden" name="id_order" value="<?= (int)$row['id_order'] ?>">
                            <span class="status-badge <?= tz_attr(strtolower((string)$row['status'])) ?>"><?= tz_e($row['status']) ?></span>
                            <div style="margin-top: 10px; display: flex; gap: 5px; justify-content: center;">
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
            <?php echo '</tbody></table></div></div>'; ?>
        
        <?php else: ?>
            <!-- TAMPILAN KALO USER GA KETEMU -->
            <div class="empty-state">
                <h2>Opps! Sepertinya ada yang salah...</h2>
                <p>User/ID gada mpruyy</p>
            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>