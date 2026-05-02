<?php
// Jalur ke koneksi.php
require_once __DIR__ . '/../koneksi.php'; 

// 1. Tangkap data JSON dari Xendit
$rawRequest = file_get_contents("php://input");
$data = json_decode($rawRequest, true);

if (!$data) {
    http_response_code(400);
    die("Tidak ada data");
}

// 2. Ambil info penting & Amankan data
$external_id    = mysqli_real_escape_string($koneksi, $data['external_id']); 
$status         = $data['status']; 
$payment_method = mysqli_real_escape_string($koneksi, $data['payment_method'] ?? 'Xendit');

// 3. Logika Update Database
if ($status === 'PAID' || $status === 'SETTLED') {
    // Ganti 'Success' jadi 'proses' biar otomatis pindah tab di index
    $sql = "UPDATE orders SET status = 'proses', payment_method = '$payment_method' WHERE external_id = '$external_id'";
    
    if (mysqli_query($koneksi, $sql)) {
        http_response_code(200);
        echo "Lunas mprruy! Status jadi proses.";
    } else {
        file_put_contents('callback_error.log', "[" . date('Y-m-d H:i:s') . "] " . mysqli_error($koneksi) . "\n", FILE_APPEND);
        http_response_code(500);
    }
} else {
    // Jika status EXPIRED atau lainnya, beri respon 200 tapi jangan update ke 'proses'
    http_response_code(200);
    echo "Status bukan PAID, abaikan.";
}
?>