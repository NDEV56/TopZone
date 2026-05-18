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

// 2. TANGKAP DATA POST
$id_user     = mysqli_real_escape_string($koneksi, $_POST['id_user'] ?? 0);
$total_bayar = (int)($_POST['total_bayar'] ?? 0);
$external_id = 'TZ-' . time(); 

// Decode payload JSON pesanan_multi dari frontend
$pesanan_multi_raw = $_POST['pesanan_multi'] ?? '';
$items = json_decode($pesanan_multi_raw, true);

// Jika format JSON gagal atau kosong, fallback ke single item param
if (!is_array($items) || empty($items)) {
    $id_game  = mysqli_real_escape_string($koneksi, $_POST['id_game'] ?? 0);
    $produk   = mysqli_real_escape_string($koneksi, $_POST['produk'] ?? 'Produk');
    $harga    = (int)($_POST['harga'] ?? 0);
    $qty      = (int)($_POST['qty'] ?? 1);
    $user_val = mysqli_real_escape_string($koneksi, $_POST['user'] ?? '-');

    $items = [[
        'id_game'   => $id_game,
        'produk'    => $produk,
        'harga'     => $harga,
        'qty'       => $qty,
        'user_data' => $user_val
    ]];
    $total_bayar = $harga;
}

// 3. PROSES INSERT SEMUA ITEM KE TABEL ORDERS
$deskripsi_invoice_arr = [];

foreach ($items as $item) {
    $i_game_id  = mysqli_real_escape_string($koneksi, $item['id_game']);
    $i_produk   = mysqli_real_escape_string($koneksi, $item['produk']);
    $i_qty      = (int)$item['qty'];
    $i_user_val = mysqli_real_escape_string($koneksi, $item['user_data']);
    
    $i_harga_satuan = (int)$item['harga'];
    $i_subtotal     = (isset($_POST['pesanan_multi'])) ? ($i_harga_satuan * $i_qty) : $i_harga_satuan;

    // Ambil nama game asli dari tabel games
    $q_game = mysqli_query($koneksi, "SELECT nama_game FROM games WHERE id = '$i_game_id'");
    $d_game = mysqli_fetch_assoc($q_game);
    $game_name_fix = $d_game['nama_game'] ?? 'Game';

    $deskripsi_invoice_arr[] = $game_name_fix . " (" . $i_produk . " x" . $i_qty . ")";

    // Simpan ke database orders per item, diikat oleh satu external_id yang sama
    $sql = "INSERT INTO orders (external_id, id_user, game_name, paket, total_price, catatan, status, item_count, created_at) 
            VALUES ('$external_id', '$id_user', '$game_name_fix', '$i_produk', '$i_subtotal', '$i_user_val', 'pending', '$i_qty', NOW())";

    if (!mysqli_query($koneksi, $sql)) {
        echo json_encode(['message' => 'Gagal simpan item ke database bray: ' . mysqli_error($koneksi)]);
        exit;
    }
}

// Gabung baris deskripsi item untuk dikirim ke Xendit
$deskripsi_final = "Top Up TopZone: " . implode(', ', $deskripsi_invoice_arr);
if (strlen($deskripsi_final) > 250) {
    $deskripsi_final = substr($deskripsi_final, 0, 245) . "...";
}

// 4. TEMBAK DATA KE INVOICE XENDIT
$data_invoice = [
    'external_id' => $external_id,
    'amount' => $total_bayar,
    'description' => $deskripsi_final,
    'invoice_duration' => 86400,
    'currency' => 'IDR',
    'customer' => [
        'given_names' => 'Customer TopZone',
    ],
    // Mengarah balik ke halaman home dengan membawa data id invoice yang bener-bener dibeli bray!
    'success_redirect_url' => $base_url . '/Home/index.php?status=success&ext_id=' . $external_id,
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

// 5. RESPONS BALIK KE JAVASCRIPT FRONTEND
if (isset($result['invoice_url'])) {
    echo json_encode(['invoice_url' => $result['invoice_url']]);
} else {
    echo json_encode([
        'message' => 'Gagal mendapatkan token Xendit mprruy',
        'details' => $result
    ]);
}
?>