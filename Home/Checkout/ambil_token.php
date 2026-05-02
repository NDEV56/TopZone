<?php
header('Content-Type: application/json');

// 1. KONEKSI DATABASE
// Sesuaikan path ini dengan letak file koneksi.php lo
require_once __DIR__ . '/../koneksi.php'; 

// FUNGSI LOAD ENV (Wajib ada buat baca Secret Key Xendit)
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $_ENV[trim($parts[0])] = trim($parts[1]);
        }
    }
}

// Panggil file .env (asumsi letaknya 2 folder di atas file ini)
loadEnv(__DIR__ . '/../../.env');

// Sinkronisasi variabel koneksi (Trik Alias mprruy)
if (!isset($koneksi) && isset($conn)) { $koneksi = $conn; }

// Konfigurasi Xendit & App
$secret_key = $_ENV['XENDIT_SECRET_KEY'] ?? '';
$base_url   = $_ENV['BASE_URL'] ?? 'http://localhost/topzone';

// 2. TANGKAP DATA POST DARI JAVASCRIPT
$id_user = $_POST['id_user'] ?? 0;
$produk  = $_POST['produk'] ?? 'Produk';
$harga   = (int)($_POST['harga'] ?? 0);
$qty     = (int)($_POST['qty'] ?? 1);
$total   = $harga * $qty;
$user_val = $_POST['user'] ?? '-'; // Data ID Game / Akun
$external_id = 'TZ-' . time(); 

// 3. SIMPAN PESANAN KE DATABASE (Status: Pending)
$sql = "INSERT INTO orders (external_id, id_user, game_name, paket, total_price, catatan, status, item_count) 
        VALUES ('$external_id', '$id_user', '$produk', '$produk x$qty', '$total', '$user_val', 'pending', '$qty')";

if (!mysqli_query($koneksi, $sql)) {
    echo json_encode(['message' => 'Gagal simpan ke database: ' . mysqli_error($koneksi)]);
    exit;
}

// 4. KIRIM DATA KE API XENDIT (BUAT INVOICE)
$data = [
    'external_id' => $external_id,
    'amount' => $total,
    'description' => 'Top Up ' . $produk . ' (' . $user_val . ')',
    'invoice_duration' => 86400, // Aktif 24 jam
    'customer' => [
        'given_names' => 'Customer TopZone',
    ],
    'success_redirect_url' => $base_url . '/Home/index.php?status=success',
    'failure_redirect_url' => $base_url . '/Home/index.php?status=failed',
    'currency' => 'IDR',
    'items' => [
        [
            'name' => $produk,
            'quantity' => $qty,
            'price' => $harga
        ]
    ]
];

$payload = json_encode($data);

$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':'); // Key diakhiri titik dua
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// Cek jika ada masalah koneksi internet/server
if ($curl_error) {
    echo json_encode(['message' => 'CURL Error: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

// 5. KIRIM BALIK KE JAVASCRIPT
if (isset($result['invoice_url'])) {
    echo json_encode(['invoice_url' => $result['invoice_url']]);
} else {
    // Jika Xendit menolak (misal Secret Key salah)
    echo json_encode([
        'message' => 'Gagal dari Xendit: ' . ($result['message'] ?? 'Unknown Error'),
        'details' => $result
    ]);
}
?>