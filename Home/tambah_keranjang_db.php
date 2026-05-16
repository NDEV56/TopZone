<?php
/**
 * tambah_keranjang_db.php — HARDENED v3.1
 *   • require_login
 *   • Prepared statements
 *   • Verify produk_game record exists (anti id-spoof + price tamper)
 *   • Pakai harga DARI DB, bukan dari client (anti payment fraud)
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([]);
    exit;
}

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) { http_response_code(401); echo json_encode([]); exit; }

// id dari client (id DARI tabel produk_game)
$id_or_game = (int)($_POST['id'] ?? 0);
if ($id_or_game <= 0) { http_response_code(400); echo json_encode([]); exit; }

try {
    // Cari produk_game yang cocok — sumber kebenaran harga
    $p = tz_db()->fetchOne(
        'SELECT id, id_game, nama_produk, harga FROM produk_game WHERE id = ? LIMIT 1',
        [$id_or_game]
    );
    // Fallback: kalau yang dikirim id_game (legacy dari front-end lama)
    if (!$p) {
        $p = tz_db()->fetchOne(
            'SELECT id, id_game, nama_produk, harga FROM produk_game WHERE id_game = ? ORDER BY harga ASC LIMIT 1',
            [$id_or_game]
        );
    }
    if (!$p) { http_response_code(404); echo json_encode([]); exit; }

    tz_db()->exec(
        'INSERT INTO keranjang (id_user, id_game, nama_produk, harga, qty)
         VALUES (?, ?, ?, ?, 1)',
        [$id_user, (int)$p['id_game'], (string)$p['nama_produk'], (int)$p['harga']]
    );

    // Kembalikan keranjang user
    $rows = tz_db()->fetchAll(
        'SELECT id AS id_keranjang, nama_produk, harga, qty FROM keranjang WHERE id_user = ?',
        [$id_user]
    );
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[topzone-keranjang] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
