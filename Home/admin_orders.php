<?php
include 'koneksi.php';

// --- 1. LOGIKA UPDATE STATUS ---
if (isset($_POST['update_status'])) {
    $id_order = mysqli_real_escape_string($koneksi, $_POST['id_order']);
    $status_baru = mysqli_real_escape_string($koneksi, $_POST['status_baru']);
    mysqli_query($koneksi, "UPDATE orders SET status = '$status_baru' WHERE id_order = '$id_order'");
    header("Location: admin_orders.php");
    exit();
}

// --- 2. LOGIKA SEARCH ---
$search_query = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($koneksi, $_GET['search']);
    $search_query = $search;
    // Cari berdasarkan Username atau ID User
    $where_clause = " WHERE u.username LIKE '%$search%' OR o.id_user = '$search' ";
}

// --- 3. AMBIL DATA STATISTIK ---
$q_cuan = mysqli_query($koneksi, "SELECT SUM(total_price) as total FROM orders WHERE status = 'SELESAI'");
$total_cuan = mysqli_fetch_assoc($q_cuan)['total'] ?? 0;

$q_order = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM orders");
$total_order = mysqli_fetch_assoc($q_order)['total'] ?? 0;

$q_game = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM games");
$total_game = mysqli_fetch_assoc($q_game)['total'] ?? 0;

// --- 4. AMBIL DATA PESANAN DENGAN FILTER SEARCH ---
$query_str = "SELECT o.*, u.username FROM orders o 
              LEFT JOIN users u ON o.id_user = u.id 
              $where_clause
              ORDER BY o.id_user DESC, o.created_at DESC";
$res = mysqli_query($koneksi, $query_str);
$jumlah_data = mysqli_num_rows($res);
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
            <input type="text" name="search" class="search-input" placeholder="Cari Nama User atau ID..." value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="btn-search">CARI</button>
            <?php if(!empty($search_query)): ?>
                <a href="admin_orders.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>

        <div class="stats-grid">
            <div class="stat-card">
                <small>TOTAL ORDER</small>
                <h3><?= $total_order ?> Pesanan</h3>
            </div>
            <div class="stat-card">
                <small>TOTAL CUAN (SELESAI)</small>
                <h3>Rp <?= number_format($total_cuan) ?></h3>
            </div>
            <div class="stat-card">
                <small>GAME AKTIF</small>
                <h3><?= $total_game ?> Game</h3>
            </div>
        </div>

        <?php if ($jumlah_data > 0): ?>
            <?php 
            $current_user = null;
            while($row = mysqli_fetch_assoc($res)): 
                if ($current_user !== $row['id_user']): 
                    if ($current_user !== null) echo '</tbody></table></div></div>'; 
                    $current_user = $row['id_user'];
            ?>
                <div class="user-group-wrapper">
                    <div class="user-header">
                        <h3><?= htmlspecialchars($row['username'] ?? 'GUEST') ?></h3>
                        <span>USER ID: #<?= $row['id_user'] ?? 'N/A' ?></span>
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
                        <strong style="color: #fff;"><?= $row['game_name'] ?></strong><br>
                        <small style="color: var(--primary); font-size: 10px;">#ORD-<?= $row['id_order'] ?> | <?= $row['paket'] ?></small>
                    </td>
                    <td><span class="qty-badge"><?= $row['item_count'] ?>x</span></td>
                    <td>
                        <div class="acc-box">
                            <?php 
                            $acc = json_decode($row['catatan'], true);
                            if(is_array($acc)) {
                                foreach($acc as $k => $v) echo "<b>$k:</b> " . htmlspecialchars($v) . "<br>";
                            } else { echo htmlspecialchars($row['catatan']); }
                            ?>
                        </div>
                    </td>
                    <td><b style="color: #fff;">Rp <?= number_format($row['total_price']) ?></b></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="id_order" value="<?= $row['id_order'] ?>">
                            <span class="status-badge <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
                            <div style="margin-top: 10px; display: flex; gap: 5px; justify-content: center;">
                                <select name="status_baru">
                                    <option value="PENDING" <?= $row['status'] == 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                    <option value="PROSES" <?= $row['status'] == 'PROSES' ? 'selected' : '' ?>>Proses</option>
                                    <option value="DIKIRIM" <?= $row['status'] == 'DIKIRIM' ? 'selected' : '' ?>>Dikirim</option>
                                    <option value="SELESAI" <?= $row['status'] == 'SELESAI' ? 'selected' : '' ?>>Selesai</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-set">SET</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
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