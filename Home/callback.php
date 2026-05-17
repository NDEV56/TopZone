<?php
/**
 * callback.php — Webhook Xendit (HARDENED v3.1)
 *
 * Patch utama: SEBELUMNYA siapa saja bisa POST dan menyatakan order PAID.
 * Sekarang:
 *   • WAJIB header X-CALLBACK-TOKEN cocok dengan XENDIT_CALLBACK_TOKEN di .env
 *   • Body size cap (16 KB)
 *   • Whitelist status
 *   • Prepared statements
 *   • Log audit ke logs/payment-callback.log
 *   • Tidak bocor mysqli_error
 */
require_once __DIR__ . '/_security.php';
tz_security_init();

// 1. Body cap (anti memory bomb)
$max = 16 * 1024;
$raw = file_get_contents('php://input', false, null, 0, $max + 1);
if ($raw === false || strlen($raw) > $max) {
    http_response_code(413);
    error_log('[topzone-callback] body terlalu besar');
    die('Body too large');
}

// 2. Verifikasi token (anti payment fraud)
if (!tz_verify_webhook('XENDIT_CALLBACK_TOKEN', 'HTTP_X_CALLBACK_TOKEN')) {
    http_response_code(401);
    error_log('[topzone-callback] callback token invalid ip=' . tz_client_ip());
    die('Unauthorized');
}

// 3. Parse JSON aman
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['external_id']) || empty($data['status'])) {
    http_response_code(400);
    die('Bad payload');
}

$external_id    = substr((string)$data['external_id'], 0, 64);
$status         = strtoupper((string)$data['status']);
$payment_method = substr((string)($data['payment_method'] ?? 'Xendit'), 0, 64);

$allowedStatus = ['PAID','SETTLED','PENDING','EXPIRED','FAILED'];
if (!in_array($status, $allowedStatus, true)) {
    http_response_code(400);
    die('Bad status');
}

$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
@mkdir($logDir, 0755, true);
@file_put_contents(
    $logDir . DIRECTORY_SEPARATOR . 'payment-callback.log',
    date('c') . " ip=" . tz_client_ip() . " ext=$external_id status=$status\n",
    FILE_APPEND | LOCK_EX
);

if ($status === 'PAID' || $status === 'SETTLED') {
    try {
        // Pertama: pastikan order ada & belum diproses (idempotent)
        $order = tz_db()->fetchOne(
            'SELECT id_order, status FROM orders WHERE external_id = ? LIMIT 1',
            [$external_id]
        );
        if (!$order) {
            http_response_code(200);
            echo 'unknown_order';
            exit;
        }

        // Idempotent — jangan double-process
        $oldStatus = strtolower((string)$order['status']);
        if (in_array($oldStatus, ['proses','dikirim','selesai'], true)) {
            echo 'already_processed';
            exit;
        }

        tz_db()->exec(
            'UPDATE orders SET status = ?, payment_method = ? WHERE external_id = ?',
            ['proses', $payment_method, $external_id]
        );

        // Coba normalisasi game_name kalau itu cuma ID angka
        // (gunakan parameter binding, bukan CONCAT-like dari user input)
        tz_db()->exec(
            "UPDATE orders o
             JOIN games g ON o.game_name = CAST(g.id AS CHAR)
             SET o.game_name = g.nama_game
             WHERE o.external_id = ?",
            [$external_id]
        );

        http_response_code(200);
        echo 'ok';
    } catch (\Throwable $e) {
        error_log('[topzone-callback] ' . $e->getMessage());
        http_response_code(500);
        echo 'internal';
    }
} else {
    // Status lain — ack
    http_response_code(200);
    echo 'noop';
}
