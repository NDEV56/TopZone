<?php
/**
 * ajax_admin_load_chat.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared SQL
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();

$id_user = (int)($_GET['id_user'] ?? 0);
if ($id_user <= 0) exit;

try {
    $rows = tz_db()->fetchAll(
        'SELECT pesan, pengirim, waktu FROM chat WHERE id_user = ? ORDER BY waktu ASC LIMIT 500',
        [$id_user]
    );
} catch (\Throwable $e) {
    error_log('[topzone-admin-load-chat] ' . $e->getMessage());
    exit;
}

foreach ($rows as $row) {
    $isAdmin = ((string)$row['pengirim'] === 'admin');
    $class = $isAdmin ? 'admin-msg' : 'user-msg';
    echo '<div class="chat-bubble ' . tz_attr($class) . '">';
    echo tz_e($row['pesan']);
    echo '<span class="msg-time">' . tz_e(date('H:i', strtotime((string)$row['waktu']))) . '</span>';
    echo '</div>';
}
