<?php
include 'koneksi.php';

// --- 1. LOGIKA UPDATE STATUS ADMIN ---
if (isset($_POST['update_status'])) {
    $id_order = mysqli_real_escape_string($koneksi, $_POST['id_order']);
    $status_baru = mysqli_real_escape_string($koneksi, $_POST['status_baru']);
    
    // Update status pesanan
    mysqli_query($koneksi, "UPDATE orders SET status = '$status_baru' WHERE id_order = '$id_order'");
    
    // Refresh halaman biar status langsung berubah di UI
    header("Location: admin_orders.php");
    exit();
}

// --- 2. LOGIKA AMBIL STATISTIK (DIBENERIN) ---
// Total Cuan: Hanya menghitung pesanan yang statusnya 'SELESAI'
$q_cuan = mysqli_query($koneksi, "SELECT SUM(total_price) as total FROM orders WHERE status = 'SELESAI'");
$data_cuan = mysqli_fetch_assoc($q_cuan);
$total_cuan = $data_cuan['total'] ?? 0; // Kalau kosong kasih 0

// Total Order: Menghitung semua baris di tabel orders
$q_order = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM orders");
$data_order = mysqli_fetch_assoc($q_order);
$total_order = $data_order['total'] ?? 0;

// Game Aktif: Menghitung jumlah game unik di tabel games
$q_game = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM games");
$data_game = mysqli_fetch_assoc($q_game);
$total_game = $data_game['total'] ?? 0;

// --- 3. AMBIL DATA PESANAN UNTUK TABEL ---
$res = mysqli_query($koneksi, "SELECT * FROM orders ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Dashboard</title>
    <style>
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; }
        
        /* Sidebar */
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; transition: 0.3s; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }

        /* Main Content */
        .content { margin-left: 250px; padding: 30px; width: 100%; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 15px; border-left: 4px solid var(--primary); }
        .stat-card small { color: #888; font-size: 12px; }
        .stat-card h3 { margin: 5px 0 0; color: #fff; }

        /* Table Estetik */
        .table-container { background: var(--card); border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #333; color: var(--primary); text-transform: uppercase; font-size: 11px; }
        td { padding: 15px; border-bottom: 1px solid #2a2a2a; }
        
        .acc-box { background: #111; padding: 10px; border-radius: 8px; border: 1px solid #444; font-size: 12px; font-family: 'Consolas', monospace; }
        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: bold; display: inline-block; }
        .pending { background: #f39c12; color: #fff; }
        .proses { background: #3498db; color: #fff; }
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
        <a href="../Home/Chat/Admin_Chat/admin_chat.php" class="nav-link ">💬 Chat Pelanggan</a>
        <a href="index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <a href="admin_tambah_game.php" class="btn-add">+ Tambah Game Baru</a>
        <h2 style="margin-top: 0;">🚀 Dashboard Admin</h2>

        <!-- Cards Statistik -->
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

        <!-- Tabel Pesanan -->
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
                    <?php while($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td>
                            <strong style="color: #fff;"><?= $row['game_name'] ?></strong><br>
                            <small style="color: var(--primary);"><?= $row['paket'] ?></small>
                        </td>
                        <td>
                            <div class="acc-box">
                                <?php 
                                $acc = json_decode($row['catatan'], true);
                                if(is_array($acc)) {
                                    foreach($acc as $k => $v) echo "<b>$k:</b> $v<br>";
                                } else { echo $row['catatan']; }
                                ?>
                            </div>
                        </td>
                        <td><b style="color: #fff;">Rp <?= number_format($row['total_price']) ?></b></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="id_order" value="<?= $row['id_order'] ?>">
                                <span class="status-badge <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
                                <div style="margin-top: 10px; display: flex; gap: 8px;">
                                    <select name="status_baru">
                                        <option value="PENDING" <?= $row['status'] == 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                        <option value="PROSES" <?= $row['status'] == 'PROSES' ? 'selected' : '' ?>>Proses</option>
                                        <option value="DIKIRIM" <? $row['status'] == 'DIKIRIM' ? 'selected' : '' ?>>Dikirim</option>
                                        <option value="SELESAI" <?= $row['status'] == 'SELESAI' ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-set">SET</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>