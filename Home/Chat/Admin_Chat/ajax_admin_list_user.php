<?php
/**
 * ajax_admin_list_user.php — HARDENED v3.1 (sync NAFI: status bar + foto user)
 *   • require_admin
 *   • Prepared SQL
 *   • XSS-safe (data-attr untuk onclick, escape semua output)
 *   • Status admin toggle
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();

// 1. Ambil status admin
$current_status = 'offline';
try {
    $row_status = tz_db()->fetchOne('SELECT status_admin FROM users LIMIT 1');
    $current_status = (string)($row_status['status_admin'] ?? 'offline');
} catch (\Throwable $e) {
    error_log('[topzone-list-user] ' . $e->getMessage());
}
$is_online = ($current_status === 'online');
?>

<div class="admin-status-bar">
    <div class="status-info">
        <span class="status-label">ADMIN STATUS</span>
        <span id="statusLabel" class="status-value <?= $is_online ? 'online-color' : 'offline-color' ?>">
            <?= tz_e(strtoupper($current_status)) ?>
        </span>
    </div>
    <label class="compact-switch">
        <input type="checkbox" id="statusToggle" <?= $is_online ? 'checked' : '' ?> onchange="updateAdminStatus(this)">
        <span class="compact-slider"></span>
    </label>
</div>

<style>
    .admin-status-bar { padding: 12px 15px; background: #111; border-bottom: 1px solid #222; display: flex; justify-content: space-between; align-items: center; }
    .status-info { display: flex; flex-direction: column; gap: 2px; }
    .status-label { font-size: 9px; color: #666; letter-spacing: 1px; font-weight: 800; }
    .status-value { font-size: 11px; font-weight: bold; transition: 0.3s ease; }
    .online-color { color: #00ff88; text-shadow: 0 0 8px rgba(0, 255, 136, 0.4); }
    .offline-color { color: #ff4444; text-shadow: 0 0 8px rgba(255, 68, 68, 0.4); }
    .compact-switch { position: relative; display: inline-block; width: 34px; height: 18px; }
    .compact-switch input { opacity: 0; width: 0; height: 0; }
    .compact-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 20px; }
    .compact-slider:before { position: absolute; content: ""; height: 12px; width: 12px; left: 3px; bottom: 3px; background-color: white; transition: .4s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 50%; }
    input:checked + .compact-slider { background-color: #00ff88; }
    input:checked + .compact-slider:before { transform: translateX(16px); }
</style>

<?php
// 2. List user dengan pesan terakhir
try {
    $rows = tz_db()->fetchAll(
        "SELECT u.id, u.username, u.foto, c.pesan, c.created_at
         FROM users u
         JOIN chat c ON u.id = c.id_user
         WHERE c.id IN (SELECT MAX(id) FROM chat GROUP BY id_user)
         ORDER BY c.created_at DESC
         LIMIT 200"
    );
} catch (\Throwable $e) {
    error_log('[topzone-list-user] ' . $e->getMessage());
    exit;
}

foreach ($rows as $row) {
    $id        = (int)$row['id'];
    $username  = (string)($row['username'] ?? '');
    $pesan     = (string)($row['pesan'] ?? '');
    $foto_user = !empty($row['foto']) ? basename((string)$row['foto']) : 'Default.jpg';
    $singkat   = (mb_strlen($pesan) > 30) ? mb_substr($pesan, 0, 30) . '...' : $pesan;
    $waktu     = date('H:i', strtotime((string)$row['created_at']));
?>
    <div class="user-item" id="user-<?= $id ?>"
         data-id="<?= $id ?>"
         data-name="<?= tz_attr($username) ?>"
         data-foto="<?= tz_attr($foto_user) ?>"
         onclick="openChat(this.dataset.id, this.dataset.name, this.dataset.foto)"
         style="padding: 15px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.3s; position: relative;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="../../uploads/<?= tz_attr($foto_user) ?>" onerror="this.src='../../Default.jpg'" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #333;">
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: bold; color: #00ff88; font-size: 14px;"><?= tz_e($username) ?></span>
                    <span style="font-size: 11px; color: #666;"><?= tz_e($waktu) ?></span>
                </div>
                <div style="font-size: 12px; color: #aaa; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= tz_e($singkat) ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
