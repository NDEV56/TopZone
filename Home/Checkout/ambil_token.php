<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../koneksi.php';

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $_ENV[trim($parts[0])] = trim($parts[1]);
    }
}
loadEnv(__DIR__ . '/../../.env');

if (!isset($koneksi) && isset($conn)) { $koneksi = $conn; }

$secret_key = $_ENV['XENDIT_SECRET_KEY'] ?? '';
$base_url   = $_ENV['BASE_URL'] ?? 'http://localhost/topzone';

$id_user   = mysqli_real_escape_string($koneksi, $_POST['id_user'] ?? 0);
$id_game   = mysqli_real_escape_string($koneksi, $_POST['id_game'] ?? 0);
$produk    = mysqli_real_escape_string($koneksi, $_POST['produk'] ?? 'Produk');
$harga     = (int)($_POST['harga'] ?? 0);
$qty       = (int)($_POST['qty'] ?? 1);
$user_val  = mysqli_real_escape_string($koneksi, $_POST['user'] ?? '-');
$external_id = 'TZ-' . time();

// ── LOG: Checkout dimulai ──
tz_log('critical', 'CHECKOUT_START', "User memulai checkout produk '{$produk}'", [
    'id_user'     => $id_user,
    'id_game'     => $id_game,
    'produk'      => $produk,
    'harga'       => $harga,
    'qty'         => $qty,
    'external_id' => $external_id,
]);

$q_game = mysqli_query($koneksi, "SELECT nama_game FROM games WHERE id = '$id_game'");
$d_game = mysqli_fetch_assoc($q_game);
$game_name_fix = $d_game['nama_game'] ?? 'Game';

$sql = "INSERT INTO orders (external_id, id_user, game_name, paket, total_price, catatan, status, item_count, created_at)
        VALUES ('$external_id', '$id_user', '$game_name_fix', '$produk', '$harga', '$user_val', 'pending', '$qty', NOW())";

if (!mysqli_query($koneksi, $sql)) {
    // ── LOG: Gagal simpan order ──
    tz_log('error', 'ORDER_DB_ERROR', "Gagal menyimpan order ke database", [
        'external_id' => $external_id,
        'id_user'     => $id_user,
        'db_error'    => mysqli_error($koneksi),
    ]);
    echo json_encode(['message' => 'Gagal simpan ke database: ' . mysqli_error($koneksi)]);
    exit;
}

// ── LOG: Order berhasil dibuat ──
tz_log('critical', 'ORDER_CREATED', "Order '{$external_id}' berhasil dibuat — {$game_name_fix} ({$produk}) Rp{$harga}", [
    'external_id' => $external_id,
    'game'        => $game_name_fix,
    'produk'      => $produk,
    'harga'       => $harga,
    'status'      => 'pending',
]);

// Kirim ke Xendit
$data_invoice = [
    'external_id'          => $external_id,
    'amount'               => $harga,
    'description'          => 'Top Up ' . $game_name_fix . ' - ' . $produk,
    'invoice_duration'     => 86400,
    'currency'             => 'IDR',
    'customer'             => ['given_names' => 'Customer TopZone'],
    'success_redirect_url' => $base_url . '/Home/index.php?status=success',
    'failure_redirect_url' => $base_url . '/Home/index.php?status=failed',
];

// ── LOG: Kirim request ke Xendit ──
tz_log('common', 'XENDIT_REQUEST', "Mengirim invoice request ke Xendit untuk order '{$external_id}'", [
    'external_id' => $external_id,
    'amount'      => $harga,
]);

$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_invoice));
curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$result   = json_decode($response, true);
curl_close($ch);

if ($curlErr) {
    tz_log('error', 'XENDIT_CURL_ERROR', "cURL error saat koneksi ke Xendit", [
        'external_id' => $external_id,
        'curl_error'  => $curlErr,
    ]);
}

if (isset($result['invoice_url'])) {
    tz_log('common', 'XENDIT_INVOICE_CREATED', "Invoice Xendit berhasil dibuat", [
        'external_id'  => $external_id,
        'invoice_url'  => $result['invoice_url'],
    ]);
    echo json_encode(['invoice_url' => $result['invoice_url']]);
} else {
    tz_log('error', 'XENDIT_INVOICE_FAILED', "Xendit gagal membuat invoice", [
        'external_id' => $external_id,
        'response'    => $result,
    ]);
    echo json_encode(['message' => 'Gagal dari Xendit', 'details' => $result]);
}
?>
