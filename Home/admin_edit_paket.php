<?php
include 'koneksi.php';

// 1. Ambil ID dari URL dengan aman
$id_get = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : '';

// 2. Ambil data paket berdasarkan ID
// PENTING: Ganti 'id_produk' kalau nama kolom di database lu beda!
$query = mysqli_query($koneksi, "SELECT * FROM produk_game WHERE id_produk = '$id_get'");
$data = mysqli_fetch_assoc($query);

// Proteksi kalau ID ngasal atau data gak ada
if (!$data) {
    header("Location: admin_paket.php");
    exit();
}

// 3. Logika Update data
if (isset($_POST['update'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga = mysqli_real_escape_string($koneksi, $_POST['harga']);

    // Gunakan nama kolom yang sama ('id_produk') untuk WHERE clause
    $sql_update = "UPDATE produk_game SET 
                   nama_produk = '$nama', 
                   harga = '$harga' 
                   WHERE id_produk = '$id_get'";
    
    $update = mysqli_query($koneksi, $sql_update);

    if ($update) {
        echo "<script>alert('Berhasil diupdate!'); window.location='admin_paket.php';</script>";
    } else {
        echo "Gagal update: " . mysqli_error($koneksi);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Paket - TopZone</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .form-card { background: #1e1e1e; padding: 30px; border-radius: 12px; border: 1px solid #333; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { color: #00ff88; margin-top: 0; }
        label { display: block; margin-top: 15px; font-size: 13px; color: #888; }
        input { width: 100%; padding: 12px; margin-top: 5px; background: #252525; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; }
        .btn-update { background: #00ff88; color: #000; border: none; padding: 12px; width: 100%; margin-top: 25px; font-weight: bold; cursor: pointer; border-radius: 6px; transition: 0.3s; }
        .btn-update:hover { background: #fff; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="form-card">
    <h2>Edit Paket</h2>
    <form method="POST">
        <label>Nama Item/Produk</label>
        <input type="text" name="nama_produk" value="<?php echo htmlspecialchars($data['nama_produk']); ?>" required>

        <label>Harga (Rp)</label>
        <input type="number" name="harga" value="<?php echo htmlspecialchars($data['harga']); ?>" required>

        <button type="submit" name="update" class="btn-update">SIMPAN PERUBAHAN</button>
        <a href="admin_paket.php" class="btn-back"> Kembali</a>
    </form>
</div>

</body>
</html>