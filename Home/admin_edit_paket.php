<?php
/**
 * admin_edit_paket.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared statements
 *   • CSRF
 *   • XSS-safe
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) tz_safe_redirect('/Home/admin_paket.php');

$data = tz_db()->fetchOne('SELECT * FROM produk_game WHERE id = ?', [$id]);
if (!$data) tz_safe_redirect('/Home/admin_paket.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    tz_csrf_verify();
    $nama  = trim((string)($_POST['nama_produk'] ?? ''));
    $harga = (int)($_POST['harga'] ?? 0);

    if ($nama === '' || strlen($nama) > 64 || $harga < 0 || $harga > 100000000) {
        echo "<script>alert('Input tidak valid'); history.back();</script>";
        exit;
    }
    try {
        tz_db()->exec(
            'UPDATE produk_game SET nama_produk = ?, harga = ? WHERE id = ?',
            [$nama, $harga, $id]
        );
        echo "<script>alert('Berhasil diupdate!'); window.location='admin_paket.php';</script>";
        exit;
    } catch (\Throwable $e) {
        error_log('[topzone-edit-paket] ' . $e->getMessage());
        echo "<script>alert('Gagal update'); history.back();</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Paket - TopZone</title>
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
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            position: relative;
            overflow: hidden;
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
        FORM CARD (Glassmorphism Style)
        ========================================================================== */
        .form-card { 
            background: rgba(11, 23, 58, 0.45); 
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 30px; 
            border-radius: 12px; 
            border: 1px solid var(--glass-border); 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); 
            animation: cardFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 { 
            color: var(--text-main); 
            margin-top: 0; 
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* ==========================================================================
        INPUTS & LABELS
        ========================================================================== */
        label { 
            display: block; 
            margin-top: 18px; 
            font-size: 13px; 
            color: var(--text-muted); 
            font-weight: 500;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            margin-top: 6px; 
            background: rgba(255, 255, 255, 0.03); 
            border: 1px solid var(--glass-border); 
            color: #fff; 
            border-radius: 8px; 
            box-sizing: border-box; 
            outline: none;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        input:focus { 
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(0, 170, 255, 0.4); 
            box-shadow: 0 0 15px rgba(0, 170, 255, 0.15);
        }

        /* ==========================================================================
        BUTTONS (Glow Actions)
        ========================================================================== */
        .btn-update { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            border: none; 
            padding: 12px; 
            width: 100%; 
            margin-top: 25px; 
            font-weight: 600; 
            cursor: pointer; 
            border-radius: 8px; 
            transition: all 0.25s ease; 
            box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
        }

        .btn-update:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45);
        }

        .btn-back { 
            display: block; 
            text-align: center; 
            margin-top: 15px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .btn-back:hover { 
            color: var(--primary); 
            text-shadow: 0 0 8px var(--primary-glow);
        }
    </style>
</head>
<body>

<div class="form-card">
    <h2>Edit Paket</h2>
    <form method="POST">
        <?= tz_csrf_field() ?>
        <label>Nama Item/Produk</label>
        <input type="text" name="nama_produk" maxlength="64"
               value="<?= tz_attr($data['nama_produk']) ?>" required>

        <label>Harga (Rp)</label>
        <input type="number" name="harga" min="0" max="100000000"
               value="<?= tz_attr($data['harga']) ?>" required>

        <button type="submit" name="update" class="btn-update">SIMPAN PERUBAHAN</button>
        <a href="admin_paket.php" class="btn-back"> Kembali</a>
    </form>
</div>

</body>
</html>
