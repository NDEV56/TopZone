<?php
/**
 * search.php — HARDENED v3.1.1 (preserve fitur case-insensitive Nafi)
 *   • Prepared statements (anti SQL injection)
 *   • Output XSS-safe via tz_e / tz_attr
 *   • Length cap pada input
 *   • Case-insensitive search (LOWER)
 *   • Tetap response 'tidak ditemukan' untuk kompat dengan javascript.js
 */
require_once __DIR__ . '/_security.php';
tz_security_init();

// Ambil + sanitasi input
$search   = substr(trim((string)($_GET['search']   ?? '')), 0, 64);
$kategori = substr(trim((string)($_GET['kategori'] ?? '')), 0, 32);

// Whitelist kategori
$allowedKategori = ['Game','MOBA','FPS','Open World','semua',''];
$katNorm = strtolower($kategori);
if (!in_array($kategori, $allowedKategori, true) && !in_array($katNorm, ['semua',''], true)) {
    $kategori = ''; // ignore invalid
}

// Bangun query dengan prepared statements
$params = [];
$sql = "SELECT g.*,
               IFNULL(AVG(r.rating), 0) AS rating_rata,
               COUNT(r.id) AS total_ulasan
        FROM games g
        LEFT JOIN reviews r ON g.id = r.id_game
        WHERE 1=1";

// Filter kategori (skip kalau kosong atau 'semua')
if ($kategori !== '' && $katNorm !== 'semua') {
    $sql .= " AND LOWER(g.kategori) = LOWER(?)";
    $params[] = $kategori;
}

// Filter pencarian (case-insensitive)
if ($search !== '') {
    // Escape karakter LIKE: %, _, \
    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], strtolower($search)) . '%';
    $sql .= " AND LOWER(g.nama_game) LIKE ?";
    $params[] = $like;
}

$sql .= " GROUP BY g.id LIMIT 100";

try {
    $rows = tz_db()->fetchAll($sql, $params);
} catch (\Throwable $e) {
    error_log('[topzone-search] ' . $e->getMessage());
    echo 'tidak ditemukan';
    exit;
}

if (count($rows) === 0) {
    // String yang dikenali javascript.js sebagai "kosong" → tampilkan notFound
    echo 'tidak ditemukan';
    exit;
}

foreach ($rows as $g) {
    $slug    = tz_attr($g['slug'] ?? '');
    $gambar  = tz_attr($g['gambar'] ?? 'Default.jpg');
    $nama    = tz_e($g['nama_game'] ?? '');
    $rating  = number_format((float)($g['rating_rata'] ?? 0), 1);
    $terjual = (int)($g['terjual'] ?? 0);
    echo '
    <a href="game_detail.php?game=' . $slug . '" class="tp-card">
        <div class="tp-img" style="background-image:url(\'' . $gambar . '\')"></div>
        <div class="tp-info">
            <h4>' . $nama . '</h4>
            <div class="tp-meta">
                ⭐ ' . $rating . ' | ' . $terjual . ' terjual
            </div>
        </div>
    </a>';
}
