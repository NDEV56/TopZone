<?php
/**
 * admin.php — Panel Admin sederhana (HARDENED v3.1)
 *   • require_admin (auth+admin guard)
 *   • Prepared statements
 *   • XSS-safe output via tz_e()
 *   • CSRF token di form update status
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

$rows = tz_db()->fetchAll('SELECT * FROM orders ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - TopZone</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2 style="color:white">Panel Admin</h2>
        <table border="1" style="background:white; width:100%">
            <tr><th>ID</th><th>Game</th><th>ID Akun</th><th>Status</th><th>Aksi</th></tr>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td>#<?= tz_e($row['id_order']) ?></td>
                <td><?= tz_e($row['game_name']) ?></td>
                <td><?= tz_e($row['catatan']) ?></td>
                <td><?= tz_e($row['status']) ?></td>
                <td>
                    <form action="update_status.php" method="POST">
                        <?= tz_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= tz_attr($row['id_order']) ?>">
                        <select name="st">
                            <option value="Proses">Proses</option>
                            <option value="Sudah Dikirim">Kirim</option>
                            <option value="Selesai">Selesai</option>
                        </select>
                        <button>Update</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
