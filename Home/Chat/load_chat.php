<?php
/**
 * load_chat.php — HARDENED v3.1 (sync NAFI image-render update)
 *   • require_login
 *   • Prepared SQL
 *   • Image detection by extension whitelist
 *   • src path: sanitize basename, hindari path traversal
 *   • XSS-safe (tz_e + tz_attr)
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) exit;

try {
    $chats = tz_db()->fetchAll(
        'SELECT pesan, pengirim, created_at, is_read FROM chat WHERE id_user = ? ORDER BY created_at ASC LIMIT 500',
        [$id_user]
    );
} catch (\Throwable $e) {
    error_log('[topzone-load-chat] ' . $e->getMessage());
    exit;
}

foreach ($chats as $row):
    $is_me   = ((string)$row['pengirim'] === 'user');
    $pesan   = (string)$row['pesan'];
    $is_img  = (bool)preg_match('/^[\w\-]+\.(jpg|jpeg|png|gif|webp)$/i', $pesan);
    $isRead  = ((int)($row['is_read'] ?? 0) === 1);
?>
    <div style="margin-bottom:10px; display:flex; flex-direction:column; <?= $is_me ? 'align-items:flex-end;' : 'align-items:flex-start;' ?>">
        <div style="padding:10px; border-radius:12px; max-width:75%; font-size:13px; <?= $is_me ? 'background:#007bff; color:white; border-bottom-right-radius:2px;' : 'background:#333; color:#eee; border-bottom-left-radius:2px;' ?>">
            <?php if ($is_img): ?>
                <img src="uploads/<?= tz_attr(basename($pesan)) ?>" style="max-width:100%; border-radius:8px; cursor:pointer;" onclick="zoomImage(this.src)">
            <?php else: ?>
                <?= tz_e($pesan) ?>
            <?php endif; ?>
        </div>
        <div style="font-size:9px; color:#666; margin-top:4px; display:flex; align-items:center; gap:3px;">
            <?= tz_e(date('H:i', strtotime((string)$row['created_at']))) ?>
            <?php if ($is_me): ?>
                <span class="tick-container" data-read="<?= $isRead ? '1' : '0' ?>" style="color:<?= $isRead ? '#4fc3f7' : '#888' ?>;">
                    <?= $isRead ? '✓✓' : '✓' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
