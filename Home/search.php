<?php
include 'koneksi.php';

$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';

// Query Sakti: Gabungin tabel games dan reviews
$sql = "SELECT g.*, 
               IFNULL(AVG(r.rating), 0) as rating_rata, 
               COUNT(r.id) as total_ulasan
        FROM games g
        LEFT JOIN reviews r ON g.id = r.id_game
        WHERE (g.nama_game LIKE '%$search%') 
        AND (g.kategori LIKE '%$kategori%')
        GROUP BY g.id";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while ($g = mysqli_fetch_assoc($result)) {
        // Render kartu game persis format index.php
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
    // Kirim kosong biar di JS muncul "NOT FOUND"
    echo ""; 
}
?>