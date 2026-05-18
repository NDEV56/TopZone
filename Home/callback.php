<?php
require_once __DIR__ . '/../koneksi.php'; 

if (!isset($koneksi) && isset($conn)) { $koneksi = $conn; }

$rawRequest = file_get_contents("php://input");
$data = json_decode($rawRequest, true);

if (!$data) {
    http_response_code(400);
    die("Tidak ada data");
}

$external_id    = mysqli_real_escape_string($koneksi, $data['external_id']); 
$status         = $data['status']; 
$payment_method = mysqli_real_escape_string($koneksi, $data['payment_method'] ?? 'Xendit');

if ($status === 'PAID' || $status === 'SETTLED') {
    
    // Ambil id_user pemilik orderan ini dulu bray biar ga salah sasaran
    $q_order = mysqli_query($koneksi, "SELECT id_user FROM orders WHERE external_id = '$external_id' LIMIT 1");
    $d_order = mysqli_fetch_assoc($q_order);
    $id_user_order = $d_order['id_user'] ?? 0;

    // Hapus keranjang pake cara simpel berpatokan pada ID Game di invoice
    $sql_hapus_keranjang = "DELETE FROM keranjang 
                            WHERE id_user = '$id_user_order' 
                            AND id_game IN (
                                SELECT g.id FROM games g
                                INNER JOIN orders o ON (o.game_name COLLATE utf8mb4_general_ci) = (g.nama_game COLLATE utf8mb4_general_ci)
                                WHERE o.external_id = '$external_id'
                            )";
                            
    mysqli_query($koneksi, $sql_hapus_keranjang);

    // Update status order utama bray
    $sql = "UPDATE orders 
            SET orders.status = 'proses', 
                orders.payment_method = '$payment_method'
            WHERE orders.external_id = '$external_id'";
    
    if (mysqli_query($koneksi, $sql)) {
        http_response_code(200);
        echo "Lunas mprruy! Keranjang dibersihkan sukses.";
    } else {
        file_put_contents('callback_error.log', "[" . date('Y-m-d H:i:s') . "] " . mysqli_error($koneksi) . "\n", FILE_APPEND);
        http_response_code(500);
    }
}else {
    http_response_code(200);
    echo "Status bukan PAID, abaikan.";
}
?>