<?php
/**
 * update_status.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared SQL (cegah injeksi)
 *   • Whitelist status
 *   • CSRF check
 *   • Tidak bocor mysqli_error
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();
tz_csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_order']) && !isset($_POST['id'])) {
    http_response_code(400);
    echo "no_id_received";
    exit;
}

$id = (int)($_POST['id_order'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo "invalid_id";
    exit;
}

$st = strtolower((string)($_POST['st'] ?? $_POST['status_baru'] ?? 'selesai'));
$allowed = ['pending', 'proses', 'dikirim', 'sudah dikirim', 'selesai'];
if (!in_array($st, $allowed, true)) {
    http_response_code(400);
    echo "invalid_status";
    exit;
}

try {
    $rows = tz_db()->exec(
        'UPDATE orders SET status = ? WHERE id_order = ?',
        [$st, $id]
    );
    echo $rows > 0 ? "success" : "no_change";
} catch (\Throwable $e) {
    error_log('[topzone-update-status] ' . $e->getMessage());
    http_response_code(500);
    echo "error";
}
