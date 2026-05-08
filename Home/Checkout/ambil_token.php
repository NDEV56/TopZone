<?php
/**
 * ambil_token.php — Buat invoice Xendit (HARDENED v3.1)
 *   • Prepared SQL
 *   • Validasi numeric + length
 *   • Tolak kalau secret_key kosong
 *   • Tidak bocor detail error
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

header('Content-Type: application/json');

$env = tz_load_env();
$secret_key = $env['XENDIT_SECRET_KEY'] ?? '';
$base_url   = $env['BASE_URL']         ?? 'http://localhost/topzone';

if ($secret_key === '') {
    http_response_code(500);
    echo json_encode(['message' => 'Payment gateway belum dikonfigurasi (XENDIT_SECRET_KEY).']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// Tangkap input + validasi
$id_user  = (int)($_POST['id_user'] ?? 0);
$id_game  = (int)($_POST['id_game'] ?? 0);
$produk   = trim((string)($_POST['produk'] ?? 'Produk'));
$harga    = (int)($_POST['harga'] ?? 0);
$qty      = (int)($_POST['qty']   ?? 1);
$user_val = trim((string)($_POST['user'] ?? '-'));

// Sanitas (panjang max + clamp)
if (strlen($produk)   > 128) $produk   = substr($produk,   0, 128);
if (strlen($user_val) > 256) $user_val = substr($user_val, 0, 256);
if ($harga < 100 || $harga > 100000000) {
    http_response_code(400);
    echo json_encode(['message' => 'Harga tidak valid']);
    exit;
}
if ($qty < 1 || $qty > 999) {
    http_response_code(400);
    echo json_encode(['message' => 'Qty tidak valid']);
    exit;
}

// Cari nama game asli
$game_name_fix = 'Game';
if ($id_game > 0) {
    try {
        $g = tz_db()->fetchOne('SELECT nama_game FROM games WHERE id = ? LIMIT 1', [$id_game]);
        if ($g && !empty($g['nama_game'])) $game_name_fix = (string)$g['nama_game'];
    } catch (\Throwable $e) {
        error_log('[topzone-ambil-token] ' . $e->getMessage());
    }
}

$external_id = 'TZ-' . time() . '-' . bin2hex(random_bytes(3));

try {
    tz_db()->exec(
        "INSERT INTO orders (external_id, id_user, game_name, paket, total_price, catatan, status, item_count, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
        [$external_id, $id_user, $game_name_fix, $produk, $harga, $user_val, $qty]
    );
} catch (\Throwable $e) {
    error_log('[topzone-ambil-token] insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Gagal simpan order']);
    exit;
}

// Buat invoice di Xendit
$data_invoice = [
    'external_id'         => $external_id,
    'amount'              => $harga,
    'description'         => 'Top Up ' . $game_name_fix . ' - ' . $produk,
    'invoice_duration'    => 86400,
    'currency'            => 'IDR',
    'customer'            => ['given_names' => 'Customer TopZone'],
    'success_redirect_url'=> $base_url . '/Home/index.php?status=success',
    'failure_redirect_url'=> $base_url . '/Home/index.php?status=failed',
];

$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data_invoice),
    CURLOPT_USERPWD        => $secret_key . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_code >= 400) {
    error_log('[topzone-ambil-token] xendit http=' . $http_code);
    http_response_code(502);
    echo json_encode(['message' => 'Gateway error']);
    exit;
}

$result = json_decode((string)$response, true);
if (is_array($result) && !empty($result['invoice_url'])) {
    echo json_encode(['invoice_url' => $result['invoice_url']]);
} else {
    error_log('[topzone-ambil-token] xendit-bad-response');
    http_response_code(502);
    echo json_encode(['message' => 'Gateway response invalid']);
}
