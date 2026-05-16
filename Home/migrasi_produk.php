<?php
/**
 * migrasi_produk.php — HANYA BOLEH JALAN DARI CLI
 *
 * Sebelumnya: siapa saja yang tahu URL ini bisa men-trigger migrasi
 * massal di DB. Sekarang dibatasi PHP_SAPI === 'cli'.
 *
 * Cara pakai:
 *   php Home/migrasi_produk.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Skrip migrasi hanya bisa dijalankan dari command line.');
}

require_once __DIR__ . '/_security.php';

$db = tz_db();
$games = $db->fetchAll('SELECT id, nama_game, harga FROM games');

$inserted = 0;
foreach ($games as $g) {
    $id_g    = (int)$g['id'];
    $nama_p  = 'Paket Dasar ' . (string)$g['nama_game'];
    $harga_p = (int)$g['harga'];

    $cek = $db->fetchOne('SELECT id FROM produk_game WHERE id_game = ? LIMIT 1', [$id_g]);
    if ($cek) continue;

    $db->exec(
        "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) VALUES (?, ?, ?, 'umum')",
        [$id_g, $nama_p, $harga_p]
    );
    $inserted++;
}

echo "Migrasi selesai. {$inserted} paket baru dimasukkan.\n";
