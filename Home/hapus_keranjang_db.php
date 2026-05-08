<?php
/**
 * hapus_keranjang_db.php — HARDENED v3.1
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id_user = (int)($_SESSION['id_user'] ?? 0);
$id_keranjang = (int)($_GET['id'] ?? 0);

if ($id_user <= 0 || $id_keranjang <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'gagal']);
    exit;
}

try {
    $rows = tz_db()->exec(
        'DELETE FROM keranjang WHERE id_keranjang = ? AND id_user = ?',
        [$id_keranjang, $id_user]
    );
    echo json_encode(['status' => $rows > 0 ? 'sukses' : 'tidak_ditemukan']);
} catch (\Throwable $e) {
    error_log('[topzone-hapus-keranjang] ' . $e->getMessage());
    echo json_encode(['status' => 'gagal']);
}
