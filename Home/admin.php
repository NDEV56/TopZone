<?php include 'koneksi.php'; $res = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC"); ?>
<!DOCTYPE html>
<html>
<head><link rel="stylesheet" href="style.css"></head>
<body>
    <div class="container">
        <h2 style="color:white">Panel Admin</h2>
        <table border="1" style="background:white; width:100%">
            <tr><th>ID</th><th>Game</th><th>ID Akun</th><th>Status</th><th>Aksi</th></tr>
            <?php while($row = mysqli_fetch_assoc($res)): ?>
            <tr>
                <td>#<?php echo $row['id_order']; ?></td>
                <td><?php echo $row['game_name']; ?></td>
                <td><?php echo $row['catatan']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td>
                    <form action="update_status.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $row['id_order']; ?>">
                        <select name="st">
                            <option value="Proses">Proses</option>
                            <option value="Sudah Dikirim">Kirim</option>
                            <option value="Selesai">Selesai</option>
                        </select>
                        <button>Update</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>