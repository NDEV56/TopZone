<?php 
error_reporting(0); 
ini_set('display_errors', 0);

session_start();
include 'koneksi.php'; 

header('Content-Type: application/json');

$id_user = $_SESSION['id_user'] ?? 0;
$data_keranjang = [];

try {
    $db = isset($conn) ? $conn : $koneksi; 

    if (!$db) {
        throw new Exception("Koneksi database gagal mprruy.");
    }

    // FIXED: Memanggil kolom k.catatan secara langsung agar pas dengan item.catatan di JS
    $query = "SELECT 
                k.id_keranjang, k.nama_produk, k.harga, k.qty, k.catatan, 
                g.nama_game, g.gambar 
              FROM keranjang k 
              LEFT JOIN games g ON k.id_game = g.id 
              WHERE k.id_user = '$id_user' 
              ORDER BY k.id_keranjang DESC";

    $result = mysqli_query($db, $query);

    if (!$result) {
        throw new Exception(mysqli_error($db));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($row['gambar'])) {
            $row['gambar'] = "Default.jpg";
        }
        $data_keranjang[] = $row;
    }

    echo json_encode($data_keranjang);

} catch (Exception $e) {
    http_response_code(500); // Set response code biar terdeteksi error di console log browser
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>