<?php
include 'koneksi.php';

// --- 1. LOGIKA HAPUS PAKET ---
if (isset($_GET['hapus'])) {
    $id_paket = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    // GANTI 'id_produk' di bawah ini dengan nama kolom kunci di database lu!
    $query_hapus = mysqli_query($koneksi, "DELETE FROM produk_game WHERE id_produk = '$id_paket'");
    
    if($query_hapus) {
        echo "<script>window.location='admin_paket.php';</script>";
        exit();
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
}
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
        
        /* Sidebar */
        .sidebar { width: 250px; height: 100vh; background: #000; padding: 20px; position: fixed; border-right: 1px solid #333; z-index: 100; box-sizing: border-box; }
        .sidebar h1 { color: var(--primary); font-size: 24px; margin-bottom: 30px; letter-spacing: 1px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }

        /* Main Content */
        .content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); box-sizing: border-box; min-height: 100vh; }
        
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            align-items: start;
        }

        .game-card {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .game-header {
            background: var(--accent);
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
        }

        .game-info { display: flex; align-items: center; gap: 12px; }
        .game-info img { width: 45px; height: 45px; border-radius: 10px; object-fit: cover; border: 1px solid #444; }
        .game-info h3 { margin: 0; font-size: 16px; color: #fff; font-weight: 600; }
        .slug-tag { display: block; font-size: 10px; color: var(--primary); opacity: 0.7; font-family: 'Consolas', monospace; margin-top: 2px; }

        .paket-table { width: 100%; border-collapse: collapse; }
        .paket-table th { background: #181818; padding: 12px 15px; color: var(--primary); text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .paket-table td { padding: 14px 15px; border-bottom: 1px solid #2a2a2a; color: #ccc; font-size: 13.5px; }
        .paket-table tr:last-child td { border-bottom: none; }

        .btn-add-item { background: var(--primary); color: #000; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 800; transition: 0.3s; }
        .btn-add-item:hover { background: #fff; transform: scale(1.05); }
        
        .action-btns { display: flex; gap: 15px; }
        .btn-edit-item { color: var(--primary); text-decoration: none; font-size: 12px; font-weight: bold; transition: 0.2s; }
        .btn-del-item { color: #ff4d4d; text-decoration: none; font-size: 12px; font-weight: bold; transition: 0.2s; }
        .btn-edit-item:hover { color: #fff; }
        .btn-del-item:hover { color: #fff; text-shadow: 0 0 5px #ff4d4d; }

        .empty-state { padding: 40px; text-align: center; color: #666; font-style: italic; font-size: 13px; }

        /* Scrollbar biar makin cakep */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--dark); }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>TOPZONE.</h1>
        <a href="admin_orders.php" class="nav-link">📦 Pesanan Masuk</a>
        <a href="admin_tambah_game.php" class="nav-link">🎮 Kelola Game</a>
        <a href="admin_paket.php" class="nav-link active">💎 Kelola Paket</a>
        <hr style="border: 0; border-top: 1px solid #222; margin: 20px 0;">
        <a href="../index.php" class="nav-link">🏠 Lihat Website</a>
    </div>

    <div class="content">
        <h2 style="margin-bottom: 30px; font-weight: 700;">💎 Management Paket Produk</h2>

        <div class="game-grid">
            <?php
            $q_game = mysqli_query($koneksi, "SELECT * FROM games ORDER BY nama_game ASC");
            while($g = mysqli_fetch_assoc($q_game)):
                $id_game = $g['id'];
                $slug_game = $g['slug']; 
            ?>
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-info">
                            <img src="<?= $g['gambar'] ?>" alt="<?= $g['nama_game'] ?>">
                            <div>
                                <h3><?= $g['nama_game'] ?></h3>
                                <span class="slug-tag">/<?= $slug_game ?></span>
                            </div>
                        </div>
                        <a href="admin_tambah_paket.php?game=<?= $slug_game ?>" class="btn-add-item">+ ITEM</a>
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
                            <?php
                            $q_paket = mysqli_query($koneksi, "SELECT * FROM produk_game WHERE id_game = '$id_game' ORDER BY harga ASC");
                            
                            if(mysqli_num_rows($q_paket) > 0) {
                                while($p = mysqli_fetch_assoc($q_paket)) {
                                    // GANTI 'id_produk' di bawah ini sesuai nama kolom di database lu!
                                    $id_p = $p['id_produk']; 
                                    $nama_p = htmlspecialchars($p['nama_produk']);
                                    $harga_p = number_format($p['harga'], 0, ',', '.');
                            ?>
                                <tr>
                                    <td><strong style="color: #fff;"><?php echo $nama_p; ?></strong></td>
                                    <td style="color: var(--primary); font-family: monospace; font-weight: bold;">
                                        Rp <?php echo $harga_p; ?>
                                    </td>
                                    <td style="text-align:right; padding-right:15px;">
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <a href="admin_edit_paket.php?id=<?php echo $id_p; ?>" class="btn-edit-item">Edit</a>
                                            <a href="admin_paket.php?hapus=<?php echo $id_p; ?>" 
                                            class="btn-del-item" 
                                            onclick="return confirm('Hapus paket <?php echo $nama_p; ?>?')">
                                            Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="3" class="empty-state">Belum ada paket tersedia.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

</body>
</html>