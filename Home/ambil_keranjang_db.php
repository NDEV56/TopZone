<?php
/**
 * ambil_keranjang_db.php — HARDENED v3.1
 *   • Prepared SQL
 *   • Auth check (kalau guest, return [])
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) { echo '[]'; exit; }

try {
    $rows = tz_db()->fetchAll(
        'SELECT k.id_keranjang, k.nama_produk, k.harga, k.qty,
                g.nama_game, g.gambar
         FROM keranjang k
         LEFT JOIN games g ON k.id_game = g.id
         WHERE k.id_user = ?
         ORDER BY k.id_keranjang DESC
         LIMIT 200',
        [$id_user]
    );
} catch (\Throwable $e) {
    error_log('[topzone-keranjang] ' . $e->getMessage());
    echo '[]';
    exit;
}

foreach ($rows as &$row) {
    if (empty($row['gambar'])) $row['gambar'] = 'Default.jpg';
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
