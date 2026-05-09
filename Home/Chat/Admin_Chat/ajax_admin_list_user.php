<?php
include '../../koneksi.php';

// Pastikan kolom u.foto benar-benar ada di tabel users
$query = mysqli_query($koneksi, "SELECT u.id, u.username, u.foto, c.pesan, c.waktu 
    FROM users u 
    JOIN chat c ON u.id = c.id_user 
    WHERE c.id_chat IN (SELECT MAX(id_chat) FROM chat GROUP BY id_user)
    ORDER BY c.waktu DESC");

if (!$query) {
    die("Query Error: " . mysqli_error($koneksi));
}

while($row = mysqli_fetch_assoc($query)) {
    $pesan_singkat = strlen($row['pesan']) > 30 ? substr($row['pesan'], 0, 30) . "..." : $row['pesan'];
    $waktu = date('H:i', strtotime($row['waktu']));
    
    // Gunakan kolom 'foto'. Kalau kosong atau null, pakai default
    $foto_user = (!empty($row['foto'])) ? $row['foto'] : 'default-profile.png';

    echo '
    <div class="user-item" id="user-'.$row['id'].'" onclick="openChat('.$row['id'].', \''.htmlspecialchars($row['username']).'\', \''.$foto_user.'\')" 
         style="padding: 15px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.3s; position: relative;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <!-- Thumbnail di sidebar -->
            <img src="../../uploads/'.$foto_user.'" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #333;">
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: bold; color: #00ff88; font-size: 14px;">'.htmlspecialchars($row['username']).'</span>
                    <span style="font-size: 11px; color: #666;">'.$waktu.'</span>
                </div>
                <div style="font-size: 12px; color: #aaa; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    '.htmlspecialchars($pesan_singkat).'
                </div>
            </div>
        </div>
    </div>';
}
?>