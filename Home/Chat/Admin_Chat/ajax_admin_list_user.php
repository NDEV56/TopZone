<?php
/**
 * ajax_admin_list_user.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared SQL
 *   • Tidak inject username ke onclick (XSS-safe via data-attr)
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();

try {
    $rows = tz_db()->fetchAll(
        "SELECT u.id, u.username, c.pesan, c.waktu
         FROM users u
         JOIN chat c ON u.id = c.id_user
         WHERE c.id_chat IN (SELECT MAX(id_chat) FROM chat GROUP BY id_user)
         ORDER BY c.waktu DESC
         LIMIT 200"
    );
} catch (\Throwable $e) {
    error_log('[topzone-list-user] ' . $e->getMessage());
    exit;
}

foreach ($rows as $row) {
    $idu      = (int)$row['id'];
    $username = (string)($row['username'] ?? '');
    $pesan    = (string)($row['pesan']    ?? '');
    $singkat  = (mb_strlen($pesan) > 30) ? mb_substr($pesan, 0, 30) . '...' : $pesan;
    $waktu    = date('H:i', strtotime((string)$row['waktu']));
    ?>
    <div class="user-item" id="user-<?= $idu ?>"
         data-id="<?= $idu ?>"
         data-name="<?= tz_attr($username) ?>"
         onclick="openChat(this.dataset.id, this.dataset.name)"
         style="padding: 15px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.3s; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold; color: #00ff88; font-size: 14px;"><?= tz_e($username) ?></span>
            <span style="font-size: 11px; color: #666;"><?= tz_e($waktu) ?></span>
        </div>
        <div style="font-size: 12px; color: #aaa; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= tz_e($singkat) ?>
        </div>
    </div>
    <?php
}
