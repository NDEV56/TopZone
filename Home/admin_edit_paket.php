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
        /* ==========================================================================
        RESET & VARIABEL UTAMA (Topzone Blue Navy Theme)
        ========================================================================== */
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        } 

        :root { 
            --primary: #00d2ff; 
            --primary-glow: rgba(0, 210, 255, 0.35);
            --topzone-blue: #005cff;
            --navy-deep: #050d26;
            --navy-mid: #0b173a;
            --glass-bg: rgba(11, 23, 58, 0.45);
            --glass-border: rgba(255, 255, 255, 0.06);
            --text-main: #ffffff;
            --text-muted: #647b9b;
        }

        /* ==========================================================================
        BODY & ANIMASI LATAR BELAKANG (Liquid Blobs)
        ========================================================================== */
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: var(--navy-deep); 
            color: var(--text-main); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            position: relative;
            overflow: hidden;
        }

        body::before, body::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: linear-gradient(45deg, #002266, var(--topzone-blue), #00aaff);
            filter: blur(100px);
            z-index: -1;
            opacity: 0.5;
            animation: liquidMovement 15s infinite alternate ease-in-out;
        }

        body::after {
            right: -50px;
            bottom: -50px;
            background: linear-gradient(45deg, var(--topzone-blue), #001133, #00d2ff);
            animation-delay: -7.5s;
        }

        @keyframes liquidMovement {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
            50% { transform: translate(80px, 40px) scale(1.1) rotate(180deg); border-radius: 30% 70% 70% 30% / 50% 30% 70% 50%; }
            100% { transform: translate(-40px, 60px) scale(0.95) rotate(360deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
        }

        /* ==========================================================================
        FORM CARD (Glassmorphism Style)
        ========================================================================== */
        .form-card { 
            background: rgba(11, 23, 58, 0.45); 
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 30px; 
            border-radius: 12px; 
            border: 1px solid var(--glass-border); 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); 
            animation: cardFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 { 
            color: var(--text-main); 
            margin-top: 0; 
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* ==========================================================================
        INPUTS & LABELS
        ========================================================================== */
        label { 
            display: block; 
            margin-top: 18px; 
            font-size: 13px; 
            color: var(--text-muted); 
            font-weight: 500;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            margin-top: 6px; 
            background: rgba(255, 255, 255, 0.03); 
            border: 1px solid var(--glass-border); 
            color: #fff; 
            border-radius: 8px; 
            box-sizing: border-box; 
            outline: none;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        input:focus { 
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(0, 170, 255, 0.4); 
            box-shadow: 0 0 15px rgba(0, 170, 255, 0.15);
        }

        /* ==========================================================================
        BUTTONS (Glow Actions)
        ========================================================================== */
        .btn-update { 
            background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
            color: #fff; 
            border: none; 
            padding: 12px; 
            width: 100%; 
            margin-top: 25px; 
            font-weight: 600; 
            cursor: pointer; 
            border-radius: 8px; 
            transition: all 0.25s ease; 
            box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
        }

        .btn-update:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 170, 255, 0.45);
        }

        .btn-back { 
            display: block; 
            text-align: center; 
            margin-top: 15px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .btn-back:hover { 
            color: var(--primary); 
            text-shadow: 0 0 8px var(--primary-glow);
        }
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