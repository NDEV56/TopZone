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

$data = tz_db()->fetchOne('SELECT * FROM produk_game WHERE id_produk = ?', [$id]);
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
            'UPDATE produk_game SET nama_produk = ?, harga = ? WHERE id_produk = ?',
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
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .form-card { background: #1e1e1e; padding: 30px; border-radius: 12px; border: 1px solid #333; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { color: #00ff88; margin-top: 0; }
        label { display: block; margin-top: 15px; font-size: 13px; color: #888; }
        input { width: 100%; padding: 12px; margin-top: 5px; background: #252525; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; }
        .btn-update { background: #00ff88; color: #000; border: none; padding: 12px; width: 100%; margin-top: 25px; font-weight: bold; cursor: pointer; border-radius: 6px; transition: 0.3s; }
        .btn-update:hover { background: #fff; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 13px; }
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
