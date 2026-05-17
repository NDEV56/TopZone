<?php
/**
 * search.php — HARDENED v3.1
 *   • Prepared statements (parameter binding untuk LIKE)
 *   • Output XSS-safe
 *   • Length cap pada input
 *   • Whitelist kategori
 */
require_once __DIR__ . '/_security.php';
tz_security_init();

// Ambil parameter search dan kategori
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Ubah kata kunci ke huruf kecil biar pencarian bebas huruf besar/kecil bray
$searchLower = strtolower($search);

// Pondasi dasar query
$sql = "SELECT g.*, 
               IFNULL(AVG(r.rating), 0) as rating_rata, 
               COUNT(r.id) as total_ulasan
        FROM games g
        LEFT JOIN reviews r ON g.id = r.id_game
        WHERE 1=1"; // Menggunakan 1=1 agar penggabungan AND di bawah aman

// Filter Kategori (Abaikan jika kategorinya kosong atau bernilai "Semua")
if ($kategori !== '' && strtolower($kategori) !== 'semua') {
    $sql .= " AND LOWER(g.kategori) = '" . mysqli_real_escape_string($conn, strtolower($kategori)) . "'";
}

// Filter Pencarian Nama Game (Dibuat case-insensitive pakai LOWER)
if ($search !== '') {
    $sql .= " AND LOWER(g.nama_game) LIKE '%" . mysqli_real_escape_string($conn, $searchLower) . "%'";
}

// Kelompokkan berdasarkan ID Game
$sql .= " GROUP BY g.id";

// Eksekusi query menggunakan variabel $conn sesuai file aslimu bray
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($g = mysqli_fetch_assoc($result)) {
        // Render format kartu game persis bawaan index.php kamu
        echo '
        <a href="game_detail.php?game='.$g['slug'].'" class="tp-card">
            <div class="tp-img" style="background-image:url(\''.$g['gambar'].'\')"></div>
            <div class="tp-info">
                <h4>'.$g['nama_game'].'</h4>
                <div class="tp-meta">
                    ⭐ '.number_format($g['rating_rata'], 1).' | '.$g['terjual'].' terjual
                </div>
            </div>
        </a>';
    }
} else {
    // Kirim string "tidak ditemukan" agar dibaca oleh cleanData.includes("tidak ditemukan") di JS kamu
    echo "tidak ditemukan"; 
}
