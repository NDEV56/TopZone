<?php
include 'koneksi.php';

if (isset($_POST['simpan'])) {
    $nama_game = mysqli_real_escape_string($koneksi, $_POST['nama_game']);
    $slug = str_replace(' ', '-', strtolower(mysqli_real_escape_string($koneksi, $_POST['slug'])));
    $kategori = $_POST['kategori'];
    $tipe_voucher = mysqli_real_escape_string($koneksi, $_POST['tipe_voucher']);
    $deskripsi = isset($_POST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_POST['deskripsi']) : '';

    // ==========================================================================
    // LOGIC PENYESUAIAN SETINGAN INPUT USER (Sesuai Saklar & Input Dinamis)
    // ==========================================================================
    $tipe_input = 'umum';
    $target_input_kustom = '';

    // Cek apakah user mengisi format kustom global di atas
    if (isset($_POST['g_label_custom']) && !empty($_POST['g_label_custom'])) {
        // Filter array agar tidak ada baris input kosong yang ikut tersimpan
        $filter_custom = array_filter($_POST['g_label_custom'], function($val) {
            return trim($val) !== '';
        });

        if (!empty($filter_custom)) {
            // Gabungkan array [ "ID User", "Server" ] menjadi string "ID User, Server"
            $target_input_kustom = mysqli_real_escape_string($koneksi, implode(', ', $filter_custom));
            
            // Cek status Saklar (cakupan_switch)
            // Jika tidak dicentang (di kiri), berarti mode "Data Satu Game" (Global)
            if (!isset($_POST['cakupan_switch'])) {
                $tipe_input = 'kustom_global'; 
            } else {
                // Jika dicentang (di kanan), berarti mode "Data Non-Segame" (Per Paket)
                $tipe_input = 'kustom_per_paket';
            }
        }
    }
    // ==========================================================================

    // Urusan Gambar
    $filename = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $ekstensi = pathinfo($filename, PATHINFO_EXTENSION);
    $new_filename = $slug . "-" . rand(100, 999) . "." . $ekstensi;
    $path = $new_filename; 

    if (move_uploaded_file($tmp_name, $path)) {
        // Masukkan semua data setingan yang sudah pas ke table games
        $query_game = "INSERT INTO games (nama_game, slug, kategori, tipe_voucher, gambar, deskripsi, tipe_input, target_input_kustom) 
                       VALUES ('$nama_game', '$slug', '$kategori', '$tipe_voucher', '$new_filename', '$deskripsi', '$tipe_input', '$target_input_kustom')";
        
        if(mysqli_query($koneksi, $query_game)) {
            $id_game_baru = mysqli_insert_id($koneksi);

            // Masukin data paket produk (Array)
            $p_nama = $_POST['p_nama'];
            $p_harga = $_POST['p_harga'];
            $p_tipe = $_POST['p_tipe'];

            foreach ($p_nama as $key => $val) {
                $nama_p = mysqli_real_escape_string($koneksi, $val);
                // Karena di form JS sudah diclean format rupiahnya saat submit, tinggal amankan string
                $harga_p = mysqli_real_escape_string($koneksi, $p_harga[$key]); 
                $tipe_p = $p_tipe[$key];

                if (!empty($nama_p)) {
                    mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) 
                                            VALUES ('$id_game_baru', '$nama_p', '$harga_p', '$tipe_p')");
                }
            }
            echo "<script>alert('Game, Setingan Form, & Paket Berhasil Ditambah!'); window.location='admin_tambah_game.php';</script>";
        }
    } else {
        echo "Gagal upload gambar bos!";
    }
}
?>