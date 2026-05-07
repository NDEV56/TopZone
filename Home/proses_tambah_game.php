<?php
include 'koneksi.php';

if (isset($_POST['simpan'])) {
    $nama_game   = mysqli_real_escape_string($koneksi, $_POST['nama_game']);
    $slug        = str_replace(' ', '-', strtolower(mysqli_real_escape_string($koneksi, $_POST['slug'])));
    $kategori    = $_POST['kategori'];
    $tipe_voucher= mysqli_real_escape_string($koneksi, $_POST['tipe_voucher']);

    $filename     = $_FILES['gambar']['name'];
    $tmp_name     = $_FILES['gambar']['tmp_name'];
    $ekstensi     = pathinfo($filename, PATHINFO_EXTENSION);
    $new_filename = $slug . "-" . rand(100, 999) . "." . $ekstensi;
    $path         = $new_filename;

    if (move_uploaded_file($tmp_name, $path)) {
        $query_game = "INSERT INTO games (nama_game, slug, kategori, tipe_voucher, gambar)
                       VALUES ('$nama_game', '$slug', '$kategori', '$tipe_voucher', '$new_filename')";

        if (mysqli_query($koneksi, $query_game)) {
            $id_game_baru = mysqli_insert_id($koneksi);

            // Insert paket
            $p_nama  = $_POST['p_nama'];
            $p_harga = $_POST['p_harga'];
            $p_tipe  = $_POST['p_tipe'];
            $paket_count = count($p_nama);

            for ($i = 0; $i < $paket_count; $i++) {
                $pn = mysqli_real_escape_string($koneksi, $p_nama[$i]);
                $ph = (int)$p_harga[$i];
                $pt = mysqli_real_escape_string($koneksi, $p_tipe[$i]);
                mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_paket, harga, tipe)
                                        VALUES ('$id_game_baru', '$pn', '$ph', '$pt')");
            }

            tz_log('critical', 'ADMIN_GAME_ADDED', "Admin menambahkan game baru '{$nama_game}'", [
                'id_game'    => $id_game_baru,
                'nama_game'  => $nama_game,
                'slug'       => $slug,
                'kategori'   => $kategori,
                'tipe'       => $tipe_voucher,
                'juml_paket' => $paket_count,
                'gambar'     => $new_filename,
            ]);
            echo "<script>alert('Game berhasil ditambahkan!'); window.location='admin_tambah_game.php';</script>";
        } else {
            tz_log('error', 'ADMIN_GAME_DB_ERROR', "Gagal insert game '{$nama_game}' ke database", [
                'nama_game' => $nama_game,
                'db_error'  => mysqli_error($koneksi),
            ]);
        }
    } else {
        tz_log('error', 'ADMIN_GAME_UPLOAD_ERROR', "Gagal upload gambar untuk game '{$nama_game}'", [
            'nama_game' => $nama_game,
            'file'      => $filename,
        ]);
    }
}
?>
