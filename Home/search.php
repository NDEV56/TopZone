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

$search   = substr(trim((string)($_GET['search']   ?? '')), 0, 64);
$kategori = substr(trim((string)($_GET['kategori'] ?? '')), 0, 32);

$allowedKategori = ['Game','MOBA','FPS','Open World',''];
if (!in_array($kategori, $allowedKategori, true)) $kategori = '';

// Escape karakter spesial LIKE supaya '%' user tidak meledakan query
$likeSearch   = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search)   . '%';
$likeKategori = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $kategori) . '%';

try {
    $rows = tz_db()->fetchAll(
        'SELECT g.*,
                COALESCE(AVG(r.rating), 0) AS rating_rata,
                COUNT(r.id) AS total_ulasan
         FROM games g
         LEFT JOIN reviews r ON g.id = r.id_game
         WHERE g.nama_game LIKE ? AND g.kategori LIKE ?
         GROUP BY g.id
         LIMIT 100',
        [$likeSearch, $likeKategori]
    );
} catch (\Throwable $e) {
    error_log('[topzone-search] ' . $e->getMessage());
    echo '';
    exit;
}

if (count($rows) === 0) {
    echo '';
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
