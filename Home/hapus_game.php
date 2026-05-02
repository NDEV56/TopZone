<?php
include 'koneksi.php';

if (isset($_GET['id'])) {
    // Pakai intval biar aman, mastiin ID itu angka
    $id = intval($_GET['id']);

    // 1. Ambil nama file gambar buat dihapus dari folder
    $cek_gambar = mysqli_query($koneksi, "SELECT gambar FROM games WHERE id = $id");
    $data = mysqli_fetch_assoc($cek_gambar);
    
    if ($data) {
        $nama_file = $data['gambar'];

        // 2. Hapus dulu paket yang berhubungan (Child Table)
        // Ini penting kalau lu pake relasi Foreign Key di DB
        mysqli_query($koneksi, "DELETE FROM produk_game WHERE id_game = $id");

        // 3. Hapus data gamenya (Parent Table)
        $hapus = mysqli_query($koneksi, "DELETE FROM games WHERE id = $id");

        if ($hapus) {
            // 4. Hapus file fisiknya
            if (!empty($nama_file) && file_exists("../gambar/" . $nama_file)) {
                unlink("../gambar/" . $nama_file);
            }
            echo "<script>alert('Berhasil dihapus dari Database!'); window.location='admin_tambah_game.php';</script>";
        } else {
            // Kalau gagal hapus di DB, munculin errornya apa
            echo "Error Database: " . mysqli_error($koneksi);
        }
    } else {
        echo "<script>alert('Data tidak ditemukan di database!'); window.location='admin_tambah_game.php';</script>";
    }
}
?>