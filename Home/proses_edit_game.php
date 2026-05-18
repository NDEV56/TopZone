<?php
// Koneksi ke database bray (sesuaikan nama variabel koneksi lu, misal $koneksi atau $conn)
include "koneksi.php"; 

if (isset($_POST['update'])) {
    $id_game    = mysqli_real_escape_string($koneksi, $_POST['id_game']);
    $nama_game  = mysqli_real_escape_string($koneksi, $_POST['nama_game']);
    $slug       = mysqli_real_escape_string($koneksi, $_POST['slug']);
    $deskripsi  = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $kategori   = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $tipe_voucher = mysqli_real_escape_string($koneksi, $_POST['tipe_voucher']);

    // 1. Ambil data gambar lama dari database buat cadangan kalau gak diganti
    $query_lama = mysqli_query($koneksi, "SELECT gambar FROM games WHERE id = '$id_game'");
    $data_lama  = mysqli_fetch_assoc($query_lama);
    $gambar_fix = $data_lama['gambar']; // Default pake gambar lama

    // 2. Cek apakah admin upload file gambar baru
    if (isset($_FILES['gambar']['name']) && $_FILES['gambar']['name'] != "") {
        $filename = $_FILES['gambar']['name'];
        $ext      = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Bikin nama unik biar gak bentrok di folder
        $nama_gambar_baru = "assets/img/" . time() . "_" . $slug . "." . $ext;
        $target_upload    = "../" . $nama_gambar_baru; // Arahkan ke folder assets lu bray

        // Proses pindahin gambar baru ke folder assets
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_upload)) {
            
            // Hapus file gambar lama di folder biar gak menuh-menuhin hosting/lokal (jika ada)
            if (file_exists("../" . $data_lama['gambar']) && !empty($data_lama['gambar'])) {
                unlink("../" . $data_lama['gambar']);
            }
            
            // Set gambar fix ke jalur yang baru
            $gambar_fix = $nama_gambar_baru;
        }
    }

    // 3. Eksekusi Query UPDATE ke Database bray!
    $query_update = "UPDATE games SET 
                        nama_game    = '$nama_game', 
                        slug         = '$slug', 
                        deskripsi    = '$deskripsi', 
                        kategori     = '$kategori', 
                        tipe_voucher = '$tipe_voucher', 
                        gambar       = '$gambar_fix' 
                    WHERE id = '$id_game'";

    $eksekusi = mysqli_query($koneksi, $query_update);

    if ($eksekusi) {
        echo "<script>
                alert('🚀 Mantap bray! Data Game Berhasil Diupdate!');
                window.location.href = 'admin_tambah_game.php';
              </script>";
    } else {
        echo "<script>
                alert('❌ Waduh Gagal Update ke Database bray! Cek query lu.');
                window.history.back();
              </script>";
    }
} else {
    // Kalau langsung nembak URL tanpa submit form, tendang balik
    header("Location: admin_tambah_game.php");
    exit();
}
?>