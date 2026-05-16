<?php
/**
 * Chat/Admin_Chat/update_status.php — HARDENED v3.1 (file BARU dari NAFI)
 *   • require_admin
 *   • CSRF check (POST only)
 *   • Whitelist status: online|offline
 *   • Prepared UPDATE
 *   • GET: cek status (public — dipakai user untuk lihat apakah admin sedang online)
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin toggle status — wajib admin
    tz_require_admin();
    tz_csrf_verify();

    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['online', 'offline'], true)) {
        http_response_code(400);
        exit('bad-status');
    }

    try {
        // NAFI: UPDATE tanpa WHERE — update global admin status.
        // Kalau column status_admin tidak ada di tabel users, ignore error.
        tz_db()->exec('UPDATE users SET status_admin = ?', [$status]);
        echo 'Success';
    } catch (\Throwable $e) {
        // Mungkin column tidak ada — log diam-diam, response success agar UI tidak rusak
        error_log('[topzone-admin-status] ' . $e->getMessage());
        echo 'noop';
    }
    exit;
}

// GET: cek status admin (untuk user di chat)
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
try {
    $row = tz_db()->fetchOne('SELECT status_admin FROM users LIMIT 1');
    echo (string)($row['status_admin'] ?? 'offline');
} catch (\Throwable $e) {
    echo 'offline';
}
