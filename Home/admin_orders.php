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
    margin: 0; 
    display: flex; 
    min-height: 100vh;
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

/* Container Layout */
.content { 
    margin-left: 220px; 
    padding: 30px; 
    width: calc(100% - 220px); 
}

/* ==========================================================================
   SEARCH BAR CONTAINER
   ========================================================================== */
.search-container { 
    margin-bottom: 25px; 
    display: flex; 
    gap: 10px; 
}

.search-input { 
    background: rgba(255, 255, 255, 0.03); 
    border: 1px solid var(--glass-border); 
    color: #fff; 
    padding: 12px 20px; 
    border-radius: 10px; 
    width: 300px; 
    outline: none; 
    transition: all 0.25s ease; 
}

.search-input:focus { 
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(0, 170, 255, 0.4); 
    box-shadow: 0 0 15px rgba(0, 170, 255, 0.15); 
}

.btn-search { 
    background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
    color: #fff; 
    border: none; 
    padding: 0 20px; 
    border-radius: 10px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: all 0.25s ease;
    box-shadow: 0 4px 15px rgba(0, 92, 255, 0.25);
}

.btn-search:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 170, 255, 0.4);
}

.btn-reset { 
    background: rgba(255, 255, 255, 0.05); 
    color: #fff; 
    text-decoration: none; 
    padding: 12px 20px; 
    border-radius: 10px; 
    font-size: 14px; 
    border: 1px solid var(--glass-border);
    transition: background 0.2s ease;
}

.btn-reset:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* ==========================================================================
   STATS GRID & CARDS
   ========================================================================== */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 20px; 
    margin-bottom: 30px; 
}

.stat-card { 
    background: rgba(11, 23, 58, 0.45); 
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    padding: 20px; 
    border-radius: 15px; 
    border: 1px solid var(--glass-border);
    border-left: 4px solid var(--topzone-blue); 
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.stat-card small { 
    color: var(--text-muted); 
    font-size: 12px; 
    font-weight: 500;
}

.stat-card h3 { 
    margin: 5px 0 0; 
    color: #fff; 
    font-size: 22px;
    font-weight: 700;
}

/* ==========================================================================
   EMPTY STATE VIEW
   ========================================================================== */
.empty-state { 
    text-align: center; 
    padding: 50px; 
    background: rgba(11, 23, 58, 0.3); 
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 15px; 
    border: 2px dashed rgba(0, 210, 255, 0.2); 
    margin-top: 20px; 
}

.empty-state h2 { 
    color: var(--text-muted); 
    margin-bottom: 10px; 
    font-weight: 600;
}

.empty-state p { 
    color: var(--primary); 
    font-family: 'Segoe UI', system-ui, sans-serif; 
    font-weight: 700; 
    font-size: 20px; 
    text-shadow: 0 0 10px var(--primary-glow);
}

/* ==========================================================================
   GROUPING & TABLE LAYOUT (Glass Components)
   ========================================================================== */
.user-group-wrapper { 
    margin-bottom: 40px; 
}

.user-header { 
    text-align: center; 
    background: rgba(8, 18, 48, 0.85); 
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 16px; 
    border-radius: 15px 15px 0 0; 
    border: 1px solid var(--glass-border); 
    border-bottom: none;
}

.user-header h3 { 
    margin: 0; 
    color: #fff; 
    text-transform: uppercase; 
    letter-spacing: 2px; 
    font-size: 16px; 
    font-weight: 700;
}

.user-header span { 
    color: var(--text-muted); 
    font-family: monospace; 
    font-size: 11px; 
}

.table-container { 
    background: rgba(11, 23, 58, 0.45); 
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 0 0 15px 15px; 
    padding: 20px; 
    border: 1px solid var(--glass-border); 
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3); 
    overflow-x: auto; 
}

table { 
    width: 100%; 
    border-collapse: collapse; 
}

th { 
    text-align: center; 
    padding: 14px; 
    border-bottom: 2px solid rgba(255, 255, 255, 0.06); 
    color: var(--primary); 
    text-transform: uppercase; 
    font-size: 11px; 
    letter-spacing: 0.5px;
    font-weight: 700;
}

td { 
    padding: 15px; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
    text-align: center; 
    font-size: 13.5px; 
    color: var(--text-main);
}

tr:hover td {
    background: rgba(255, 255, 255, 0.01);
}

/* ==========================================================================
   BADGES, BOXES, & CONTROLS
   ========================================================================== */
.qty-badge { 
    background: rgba(0, 210, 255, 0.1); 
    color: var(--primary); 
    padding: 4px 10px; 
    border-radius: 6px; 
    font-weight: 700; 
    border: 1px solid rgba(0, 210, 255, 0.25); 
}

.acc-box { 
    background: rgba(3, 7, 20, 0.4); 
    padding: 12px; 
    border-radius: 8px; 
    border: 1px solid var(--glass-border); 
    font-size: 11.5px; 
    font-family: 'Consolas', 'Courier New', monospace; 
    color: #d1dbed; 
    text-align: left; 
    line-height: 1.4;
}

/* Status Badges Customization */
.status-badge { 
    padding: 6px 14px; 
    border-radius: 20px; 
    font-size: 10px; 
    font-weight: 700; 
    display: inline-block; 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.pending { background: #e67e22; color: #fff; }
.proses { background: var(--topzone-blue); color: #fff; }
.selesai { background: #2ecc71; color: #fff; }
.dikirim { background: #9b59b6; color: #fff; }

/* Action Buttons & Select Control */
.btn-add { 
    background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
    color: #fff; 
    text-decoration: none; 
    padding: 12px 22px; 
    border-radius: 10px; 
    font-weight: 600; 
    float: right; 
    transition: all 0.25s ease;
    box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45);
}

.btn-set { 
    background: rgba(0, 210, 255, 0.08); 
    color: var(--primary); 
    border: 1px solid var(--primary); 
    padding: 6px 15px; 
    border-radius: 6px; 
    cursor: pointer; 
    font-weight: 600; 
    font-size: 12px;
    transition: all 0.2s ease;
}

.btn-set:hover {
    background: var(--primary);
    color: var(--navy-deep);
    box-shadow: 0 0 10px var(--primary-glow);
}

select { 
    background: rgba(255, 255, 255, 0.04); 
    color: #fff; 
    border: 1px solid var(--glass-border); 
    padding: 6px 10px; 
    border-radius: 6px; 
    font-size: 12px; 
    outline: none;
    transition: border 0.2s ease;
}

select:focus {
    border-color: rgba(0, 170, 255, 0.4);
}

select option {
    background: var(--navy-mid);
    color: #fff;
}

/* Custom Scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0, 92, 255, 0.2); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: rgba(0, 170, 255, 0.4); }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link active"> Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link"> Kelola Game</a>
        <a href="admin_paket.php" class="nav-link"> Kelola Paket</a>
        <a href="../Home/Chat/Admin_Chat/admin_chat.php" class="nav-link"> Chat Pelanggan</a>
        <a href="index.php" class="nav-link"> Lihat Website</a>
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