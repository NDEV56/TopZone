<?php
header('Content-Type: application/json');

// 1. KONEKSI DATABASE
require_once __DIR__ . '/../koneksi.php'; 

// FUNGSI LOAD ENV
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

loadEnv(__DIR__ . '/../../.env');

// Sinkronisasi variabel koneksi
if (!isset($koneksi) && isset($conn)) { $koneksi = $conn; }

$secret_key = $_ENV['XENDIT_SECRET_KEY'] ?? '';
$base_url   = $_ENV['BASE_URL'] ?? 'http://localhost/topzone';

// 2. TANGKAP DATA POST DARI PEMBAYARAN.PHP
$id_user   = mysqli_real_escape_string($koneksi, $_POST['id_user'] ?? 0);
$id_game   = mysqli_real_escape_string($koneksi, $_POST['id_game'] ?? 0);
$produk    = mysqli_real_escape_string($koneksi, $_POST['produk'] ?? 'Produk');
$harga     = (int)($_POST['harga'] ?? 0);
$qty       = (int)($_POST['qty'] ?? 1);
$user_val  = mysqli_real_escape_string($koneksi, $_POST['user'] ?? '-'); 
$external_id = 'TZ-' . time(); 

// 3. CARI NAMA GAME ASLI DI TABEL GAMES
$q_game = mysqli_query($koneksi, "SELECT nama_game FROM games WHERE id = '$id_game'");
$d_game = mysqli_fetch_assoc($q_game);
$game_name_fix = $d_game['nama_game'] ?? 'Game'; // Akan dapet "Roblox", "Free Fire", dll.

// 4. SIMPAN KE DATABASE ORDERS
// Sekarang game_name isinya nama asli, bukan angka atau kata "Game" doang
$sql = "INSERT INTO orders (external_id, id_user, game_name, paket, total_price, catatan, status, item_count, created_at) 
        VALUES ('$external_id', '$id_user', '$game_name_fix', '$produk', '$harga', '$user_val', 'pending', '$qty', NOW())";

if (!mysqli_query($koneksi, $sql)) {
    echo json_encode(['message' => 'Gagal simpan ke database: ' . mysqli_error($koneksi)]);
    exit;
}

// 5. KIRIM DATA KE XENDIT
$data_invoice = [
    'external_id' => $external_id,
    'amount' => $harga,
    'description' => 'Top Up ' . $game_name_fix . ' - ' . $produk,
    'invoice_duration' => 86400,
    'currency' => 'IDR',
    'customer' => [
        'given_names' => 'Customer TopZone',
    ],
    'success_redirect_url' => $base_url . '/Home/index.php?status=success',
    'failure_redirect_url' => $base_url . '/Home/index.php?status=failed'
];

$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_invoice));
curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

// 6. KIRIM BALIK KE JAVASCRIPT
if (isset($result['invoice_url'])) {
    echo json_encode(['invoice_url' => $result['invoice_url']]);
} else {
    echo json_encode([
        'message' => 'Gagal dari Xendit',
        'details' => $result
    ]);
}
?>