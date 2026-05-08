<?php
/**
 * kirim_chat.php — HARDENED v3.1
 *   • require_login
 *   • Prepared SQL
 *   • Length cap (anti spam)
 *   • Rate-limit (anti flood)
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) { http_response_code(401); exit; }

$pesan = trim((string)($_POST['pesan'] ?? ''));
if ($pesan === '' || strlen($pesan) > 1000) {
    http_response_code(400);
    exit;
}

if (!tz_rate_limit('chat:user:' . $id_user, 30, 60)) {
    http_response_code(429);
    exit;
}

try {
    tz_db()->exec(
        "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES (?, ?, 'user', 0)",
        [$id_user, $pesan]
    );
} catch (\Throwable $e) {
    error_log('[topzone-chat-kirim] ' . $e->getMessage());
    http_response_code(500);
}
