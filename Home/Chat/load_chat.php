<?php
/**
 * load_chat.php — HARDENED v3.1
 *   • Prepared SQL
 *   • require_login
 *   • XSS-safe (htmlspecialchars sudah ada — ditegaskan)
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) { exit; }

try {
    $rows = tz_db()->fetchAll(
        'SELECT pesan, pengirim, waktu, is_read FROM chat WHERE id_user = ? ORDER BY waktu ASC LIMIT 500',
        [$id_user]
    );
} catch (\Throwable $e) {
    error_log('[topzone-load-chat] ' . $e->getMessage());
    exit;
}

foreach ($rows as $row):
    $is_me = ((string)$row['pengirim'] === 'user');
?>
    <div style="margin-bottom:15px; display:flex; flex-direction:column; <?= $is_me ? 'align-items:flex-end;' : 'align-items:flex-start;' ?>">
        <div style="padding:10px; border-radius:12px; max-width:80%; font-size:13px; <?= $is_me ? 'background:#007bff; color:white;' : 'background:#eee; color:#333;' ?>">
            <?= tz_e($row['pesan']) ?>
        </div>
        <div style="font-size:10px; color:#999; margin-top:4px;">
            <?= tz_e(date('H:i', strtotime((string)$row['waktu']))) ?>
            <?php if ($is_me): ?>
                <span style="margin-left:3px; color:<?= ((int)$row['is_read'] === 1) ? '#4fc3f7' : '#ccc' ?>;">
                    <?= ((int)$row['is_read'] === 1) ? '✓✓' : '✓' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
