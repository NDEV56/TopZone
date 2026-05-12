<?php
/**
 * ajax_admin_load_chat.php — HARDENED v3.1 (sync NAFI image-render update)
 *   • require_admin
 *   • Prepared SQL
 *   • Auto-mark-read pesan user
 *   • Image detection by extension whitelist + basename sanitization
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();

$id_user = (int)($_GET['id_user'] ?? 0);
if ($id_user <= 0) exit;

try {
    // Auto-read: tandai pesan user dari user ini sudah dibaca
    tz_db()->exec(
        "UPDATE chat SET is_read = 1 WHERE id_user = ? AND pengirim = 'user' AND is_read = 0",
        [$id_user]
    );

    $chats = tz_db()->fetchAll(
        'SELECT pesan, pengirim, waktu, is_read FROM chat WHERE id_user = ? ORDER BY waktu ASC LIMIT 500',
        [$id_user]
    );
} catch (\Throwable $e) {
    error_log('[topzone-admin-load-chat] ' . $e->getMessage());
    exit;
}

foreach ($chats as $row) {
    $isAdmin = ((string)$row['pengirim'] === 'admin');
    $class   = $isAdmin ? 'admin-msg' : 'user-msg';
    $pesan   = (string)$row['pesan'];
    $is_image = (bool)preg_match('/^[\w\-]+\.(jpg|jpeg|png|gif|webp)$/i', $pesan);

    echo '<div class="chat-bubble ' . tz_attr($class) . '">';
    if ($is_image) {
        echo '<img src="../../uploads/' . tz_attr(basename($pesan)) .
             '" style="max-width:200px; border-radius:10px; cursor:pointer;" onclick="zoomImage(this.src)">';
    } else {
        echo tz_e($pesan);
    }

    echo '<span class="msg-time">' . tz_e(date('H:i', strtotime((string)$row['waktu']))) . '</span>';

    if ($isAdmin) {
        $isRead = ((int)($row['is_read'] ?? 0) === 1);
        $tickColor = $isRead ? '#4fc3f7' : '#888';
        $ticks     = $isRead ? '✓✓' : '✓';
        echo '<span style="color:' . tz_attr($tickColor) . '; font-size:10px; margin-left:5px;">' . $ticks . '</span>';
    }

    echo '</div>';
}
