<?php
/**
 * proses_keranjang.php — HARDENED v3.1
 *   • require_login (sebelumnya: hanya akses $_SESSION['id_user'] tanpa cek)
 *   • Prepared statements
 *   • Lookup produk DI DB untuk dapat harga yang benar (anti payment fraud)
 *   • Validasi qty
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nama_produk'])) {
    http_response_code(400);
    echo "no_input";
    exit;
}

$id_user = (int)$_SESSION['id_user'];
$id_game = (int)($_POST['id_game']     ?? 0);
$nama    = trim((string)($_POST['nama_produk'] ?? ''));
$qty     = (int)($_POST['qty']         ?? 1);

if ($id_game <= 0 || $nama === '' || strlen($nama) > 64 || $qty < 1 || $qty > 999) {
    http_response_code(400);
    echo "Gagal: Input tidak valid.";
    exit;
}

try {
    // Harga DARI DB (jangan percaya $_POST['harga'])
    $p = tz_db()->fetchOne(
        'SELECT harga FROM produk_game WHERE id_game = ? AND nama_produk = ? LIMIT 1',
        [$id_game, $nama]
    );
    $harga = $p ? (int)$p['harga'] : 0;
    if ($harga <= 0) {
        http_response_code(404);
        echo "Gagal: Produk tidak ditemukan.";
        exit;
    }

    tz_db()->exec(
        'INSERT INTO keranjang (id_user, id_game, nama_produk, harga, qty)
         VALUES (?, ?, ?, ?, ?)',
        [$id_user, $id_game, $nama, $harga, $qty]
    );
    echo "Sukses";
} catch (\Throwable $e) {
    error_log('[topzone-proses-keranjang] ' . $e->getMessage());
    echo "Gagal";
}
