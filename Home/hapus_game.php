<?php
include 'koneksi.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $cek_gambar = mysqli_query($koneksi, "SELECT gambar, nama_game FROM games WHERE id = $id");
    $data       = mysqli_fetch_assoc($cek_gambar);

    if ($data) {
        $nama_game = $data['nama_game'];
        $nama_file = $data['gambar'];

        mysqli_query($koneksi, "DELETE FROM produk_game WHERE id_game = $id");
        $hapus = mysqli_query($koneksi, "DELETE FROM games WHERE id = $id");

        if ($hapus) {
            if (!empty($nama_file) && file_exists("../gambar/" . $nama_file)) {
                unlink("../gambar/" . $nama_file);
            }
            tz_log('critical', 'ADMIN_GAME_DELETED', "Admin menghapus game '{$nama_game}'", [
                'id_game'   => $id,
                'nama_game' => $nama_game,
                'file'      => $nama_file,
            ]);
            echo "<script>alert('Berhasil dihapus dari Database!'); window.location='admin_tambah_game.php';</script>";
        } else {
            tz_log('error', 'ADMIN_GAME_DELETE_ERROR', "Gagal hapus game '{$nama_game}' dari database", [
                'id_game'  => $id,
                'db_error' => mysqli_error($koneksi),
            ]);
            echo "Error Database: " . mysqli_error($koneksi);
        }
    } else {
        tz_log('uncommon', 'ADMIN_GAME_NOT_FOUND', "Hapus game gagal — ID {$id} tidak ditemukan", [
            'id_game' => $id,
        ]);
        echo "<script>alert('Data tidak ditemukan di database!'); window.location='admin_tambah_game.php';</script>";
    }
}
?>
