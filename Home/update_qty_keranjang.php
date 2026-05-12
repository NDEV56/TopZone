<?php
/**
 * update_qty_keranjang.php — HARDENED v3.1
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id_user = (int)($_SESSION['id_user'] ?? 0);
$id  = (int)($_POST['id']  ?? 0);
$qty = (int)($_POST['qty'] ?? 0);

if ($id_user <= 0 || $id <= 0 || $qty < 1 || $qty > 999) {
    http_response_code(400);
    echo json_encode(['status' => 'gagal']);
    exit;
}

try {
    tz_db()->exec(
        'UPDATE keranjang SET qty = ? WHERE id = ? AND id_user = ?',
        [$qty, $id, $id_user]
    );
    echo json_encode(['status' => 'sukses']);
} catch (\Throwable $e) {
    error_log('[topzone-qty] ' . $e->getMessage());
    echo json_encode(['status' => 'gagal']);
}
