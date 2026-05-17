<?php
/**
 * hapus_game.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared statements
 *   • Safe unlink: hanya file di Home/ atau Home/uploads/
 *   • Tidak bocor mysqli_error
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('ID tidak valid'); window.location='admin_tambah_game.php';</script>";
    exit;
}

try {
    tz_db()->transaction(function ($db) use ($id) {
        // 1. Ambil nama file gambar
        $row = $db->fetchOne('SELECT gambar FROM games WHERE id = ?', [$id]);
        if (!$row) return null;

        $namaFile = (string)$row['gambar'];

        // 2. Hapus paket terkait
        $db->exec('DELETE FROM produk_game WHERE id_game = ?', [$id]);

        // 3. Hapus game
        $db->exec('DELETE FROM games WHERE id = ?', [$id]);

        // 4. Hapus file fisiknya — HANYA di folder Home/ atau Home/uploads/
        if ($namaFile !== '' && !str_contains($namaFile, '..') && !str_contains($namaFile, '/') && !str_contains($namaFile, '\\')) {
            $candidates = [
                __DIR__ . DIRECTORY_SEPARATOR . $namaFile,
                __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($namaFile),
            ];
            foreach ($candidates as $f) {
                $real = realpath($f);
                if ($real && str_starts_with($real, realpath(__DIR__) ?: __DIR__) && is_file($real)) {
                    @unlink($real);
                    break;
                }
            }
        }
        return true;
    });

    echo "<script>alert('Berhasil dihapus dari Database!'); window.location='admin_tambah_game.php';</script>";
} catch (\Throwable $e) {
    error_log('[topzone-hapus-game] ' . $e->getMessage());
    echo "<script>alert('Gagal menghapus. Coba lagi.'); window.location='admin_tambah_game.php';</script>";
}
