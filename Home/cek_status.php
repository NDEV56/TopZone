<?php
/**
 * cek_status.php — HARDENED v3.1
 *   • Prepared statement
 *   • Hanya kembalikan status order milik user yang login
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id_user = (int)($_SESSION['id_user'] ?? 0);
$id      = (int)($_GET['id'] ?? 0);

if ($id_user <= 0 || $id <= 0) { http_response_code(400); echo 'null'; exit; }

try {
    $row = tz_db()->fetchOne(
        'SELECT status FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1',
        [$id, $id_user]
    );
    echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[topzone-cek-status] ' . $e->getMessage());
    echo 'null';
}
