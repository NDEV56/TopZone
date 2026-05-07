<?php
require_once __DIR__ . '/../koneksi.php';

$rawRequest = file_get_contents("php://input");
$data = json_decode($rawRequest, true);

if (!$data) {
    tz_log('warning', 'CALLBACK_INVALID_PAYLOAD', "Callback diterima tapi payload tidak valid atau kosong", [
        'raw' => substr($rawRequest, 0, 200),
    ]);
    http_response_code(400);
    die("Tidak ada data");
}

$external_id    = mysqli_real_escape_string($koneksi, $data['external_id']);
$status         = $data['status'];
$payment_method = mysqli_real_escape_string($koneksi, $data['payment_method'] ?? 'Xendit');
$amount         = $data['amount'] ?? 0;

// ── LOG: Callback masuk dari Xendit ──
tz_log('critical', 'PAYMENT_CALLBACK', "Callback Xendit diterima — order '{$external_id}' status: {$status}", [
    'external_id'    => $external_id,
    'status'         => $status,
    'payment_method' => $payment_method,
    'amount'         => $amount,
]);

if ($status === 'PAID' || $status === 'SETTLED') {
    $sql = "UPDATE orders
            JOIN games ON orders.game_name = CAST(games.id AS CHAR)
            SET orders.status = 'proses',
                orders.payment_method = '$payment_method',
                orders.game_name = games.nama_game
            WHERE orders.external_id = '$external_id'";

    if (mysqli_query($koneksi, $sql)) {
        // ── LOG: Pembayaran berhasil ──
        tz_log('critical', 'PAYMENT_SUCCESS', "Pembayaran LUNAS — order '{$external_id}' diupdate ke 'proses'", [
            'external_id'    => $external_id,
            'payment_method' => $payment_method,
            'amount'         => $amount,
            'new_status'     => 'proses',
        ]);
        http_response_code(200);
        echo "Lunas mprruy!";
    } else {
        // ── LOG: DB error saat update order ──
        tz_log('error', 'PAYMENT_UPDATE_DB_ERROR', "Gagal update status order setelah pembayaran sukses", [
            'external_id' => $external_id,
            'db_error'    => mysqli_error($koneksi),
        ]);
        http_response_code(500);
    }
} else {
    // ── LOG: Callback status selain PAID ──
    tz_log('uncommon', 'PAYMENT_NON_PAID', "Callback Xendit dengan status non-PAID diabaikan", [
        'external_id' => $external_id,
        'status'      => $status,
    ]);
    http_response_code(200);
    echo "Status bukan PAID, abaikan.";
}
?>
