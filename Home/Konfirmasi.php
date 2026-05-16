<?php
/**
 * Konfirmasi.php — HARDENED v3.1
 *   • require_login
 *   • Prepared SQL
 *   • Hanya boleh konfirmasi order MILIK user
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_login();

$id_order = (int)($_GET['id'] ?? 0);
$id_user  = (int)$_SESSION['id_user'];

if ($id_order <= 0) tz_safe_redirect('/Home/index.php');

try {
    $rows = tz_db()->exec(
        "UPDATE orders SET status = 'selesai'
         WHERE id_order = ? AND id_user = ? AND status IN ('proses','dikirim')",
        [$id_order, $id_user]
    );
    if ($rows > 0) {
        // Update statistik terjual — pakai prepared, JANGAN concat
        $order = tz_db()->fetchOne('SELECT game_name FROM orders WHERE id_order = ?', [$id_order]);
        if ($order && !empty($order['game_name'])) {
            tz_db()->exec(
                "UPDATE games
                 SET terjual = terjual + 1
                 WHERE INSTR(?, nama_game) > 0",
                [(string)$order['game_name']]
            );
        }
    }
} catch (\Throwable $e) {
    error_log('[topzone-konfirmasi] ' . $e->getMessage());
}

tz_safe_redirect('/Home/index.php');
