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
            font-family: 'Segoe UI', Roboto, sans-serif; 
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

        /* ==========================================================================
        MAIN CONTENT VIEW & GRID
        ========================================================================== */
        .content { 
            margin-left: 220px; 
            padding: 40px; 
            width: calc(100% - 220px); 
            box-sizing: border-box; 
            min-height: 100vh; 
        }

        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            align-items: start;
        }

        /* ==========================================================================
        GAME CARD COMPONENT (Glassmorphism Effect)
        ========================================================================== */
        .game-card {
            background: rgba(11, 23, 58, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .game-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 210, 255, 0.4);
            box-shadow: 0 15px 35px rgba(0, 92, 255, 0.15);
        }

        .game-header {
            background: rgba(8, 18, 48, 0.6);
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--glass-border);
        }

        .game-info { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }

        .game-info img { 
            width: 45px; 
            height: 45px; 
            border-radius: 10px; 
            object-fit: cover; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .game-info h3 { 
            margin: 0; 
            font-size: 16px; 
            color: #fff; 
            font-weight: 600; 
        }

        .slug-tag { 
            display: block; 
            font-size: 10px; 
            color: var(--primary); 
            opacity: 0.85; 
            font-family: 'Consolas', monospace; 
            margin-top: 2px; 
            text-shadow: 0 0 6px var(--primary-glow);
        }

        /* ==========================================================================
        PAKET TABLE INSIDE CARD
        ========================================================================== */
        .paket-table { 
            width: 100%; 
            border-collapse: collapse; 
        }

        .paket-table th { 
            background: rgba(4, 11, 31, 0.4); 
            padding: 12px 15px; 
            color: var(--primary); 
            text-align: left; 
            font-size: 11px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            border-bottom: 1px solid var(--glass-border);
            font-weight: 700;
        }

        .paket-table td { 
            padding: 14px 15px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
            color: #d1dbed; 
            font-size: 13.5px; 
        }

        .paket-table tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }

        .paket-table tr:last-child td { 
            border-bottom: none; 
        }

        /* ==========================================================================
        ACTION BUTTONS & INTERACTIONS
        ========================================================================== */
        .btn-add-item { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            text-decoration: none; 
            padding: 7px 14px; 
            border-radius: 6px; 
            font-size: 11px; 
            font-weight: 700; 
            transition: all 0.25s ease; 
            box-shadow: 0 4px 12px rgba(0, 92, 255, 0.25);
        }

        .btn-add-item:hover { 
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 170, 255, 0.4);
        }

        .action-btns { 
            display: flex; 
            gap: 15px; 
        }

        .btn-edit-item { 
            color: var(--primary); 
            text-decoration: none; 
            font-size: 12px; 
            font-weight: bold; 
            transition: all 0.2s ease; 
            text-shadow: 0 0 5px rgba(0, 210, 255, 0.1);
        }

        .btn-edit-item:hover { 
            color: #fff; 
            text-shadow: 0 0 8px var(--primary-glow);
        }

        .btn-del-item { 
            color: #ff4d4d; 
            text-decoration: none; 
            font-size: 12px; 
            font-weight: bold; 
            transition: all 0.2s ease; 
        }

        .btn-del-item:hover { 
            color: #fff; 
            text-shadow: 0 0 8px rgba(255, 77, 77, 0.6); 
        }

        /* Empty State View */
        .empty-state { 
            padding: 40px; 
            text-align: center; 
            color: var(--text-muted); 
            font-style: italic; 
            font-size: 13.5px; 
            background: rgba(11, 23, 58, 0.2);
            border-radius: 12px;
            border: 1px dashed var(--glass-border);
        }

        /* ==========================================================================
        SCROLLBAR MANAGEMENT
        ========================================================================== */
        ::-webkit-scrollbar { 
            width: 6px; 
            height: 6px;
        }
        ::-webkit-scrollbar-track { 
            background: transparent; 
        }
        ::-webkit-scrollbar-thumb { 
            background: rgba(0, 92, 255, 0.2); 
            border-radius: 10px; 
        }
        ::-webkit-scrollbar-thumb:hover { 
            background: rgba(0, 170, 255, 0.4); 
        }
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
