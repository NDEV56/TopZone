<?php
/**
 * ajax_admin_send.php — HARDENED v3.1
 *   • require_admin
 *   • CSRF
 *   • Prepared SQL
 *   • Validasi panjang
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();
tz_csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id_user = (int)($_POST['id_user'] ?? 0);
$pesan   = trim((string)($_POST['pesan'] ?? ''));

if ($id_user <= 0 || $pesan === '' || strlen($pesan) > 2000) {
    http_response_code(400);
    exit;
}

try {
    tz_db()->exec(
        "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES (?, ?, 'admin', 1)",
        [$id_user, $pesan]
    );
} catch (\Throwable $e) {
    error_log('[topzone-admin-send] ' . $e->getMessage());
    http_response_code(500);
}
