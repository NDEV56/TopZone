<?php
include 'koneksi.php';

if (isset($_POST['simpan'])) {
    $nama_game = mysqli_real_escape_string($koneksi, $_POST['nama_game']);
    // Tangkap slug dan buat jadi lowercase + ganti spasi jadi min (buat jaga-jaga kalau user salah input)
    $slug = str_replace(' ', '-', strtolower(mysqli_real_escape_string($koneksi, $_POST['slug'])));
    
    $kategori = $_POST['kategori'];
    $tipe_voucher = mysqli_real_escape_string($koneksi, $_POST['tipe_voucher']);

    // Urusan Gambar (Tetap di folder Home)
    $filename = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $ekstensi = pathinfo($filename, PATHINFO_EXTENSION);
    $new_filename = $slug . "-" . rand(100, 999) . "." . $ekstensi;
    $path = $new_filename; 

    if (move_uploaded_file($tmp_name, $path)) {
        // TAMBAHKAN kolom 'slug' di query INSERT
        $query_game = "INSERT INTO games (nama_game, slug, kategori, tipe_voucher, gambar) 
                       VALUES ('$nama_game', '$slug', '$kategori', '$tipe_voucher', '$new_filename')";
        
        if(mysqli_query($koneksi, $query_game)) {
            $id_game_baru = mysqli_insert_id($koneksi);

            // Masukin data paket produk (Array)
            $p_nama = $_POST['p_nama'];
            $p_harga = $_POST['p_harga'];
            $p_tipe = $_POST['p_tipe'];

            foreach ($p_nama as $key => $val) {
                $nama_p = mysqli_real_escape_string($koneksi, $val);
                $harga_p = str_replace('.', '', $p_harga[$key]); 
                $tipe_p = $p_tipe[$key];

                if (!empty($nama_p)) {
                    mysqli_query($koneksi, "INSERT INTO produk_game (id_game, nama_produk, harga, tipe) 
                                            VALUES ('$id_game_baru', '$nama_p', '$harga_p', '$tipe_p')");
                }
            }
            echo "<script>alert('Game & Slug Berhasil Ditambah!'); window.location='admin_tambah_game.php';</script>";
        }
    } else {
        echo "Gagal upload gambar bos!";
    }
}
?>