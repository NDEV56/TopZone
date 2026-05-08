<?php
include '../../koneksi.php';

// Query ini bakal ambil pesan terakhir dari tiap user dan urutin yang terbaru di atas
$query = mysqli_query($koneksi, "SELECT u.id, u.username, c.pesan, c.waktu 
    FROM users u 
    JOIN chat c ON u.id = c.id_user 
    WHERE c.id_chat IN (SELECT MAX(id_chat) FROM chat GROUP BY id_user)
    ORDER BY c.waktu DESC"); // Ini kunci biar yang terbaru di atas

while($row = mysqli_fetch_assoc($query)) {
    // Potong pesan kalau kepanjangan biar gak berantakan
    $pesan_singkat = strlen($row['pesan']) > 30 ? substr($row['pesan'], 0, 30) . "..." : $row['pesan'];
    $waktu = date('H:i', strtotime($row['waktu']));

    echo '
    <div class="user-item" id="user-'.$row['id'].'" onclick="openChat('.$row['id'].', \''.$row['username'].'\')" 
         style="padding: 15px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.3s; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold; color: #00ff88; font-size: 14px;">'.htmlspecialchars($row['username']).'</span>
            <span style="font-size: 11px; color: #666;">'.$waktu.'</span>
        </div>
        <div style="font-size: 12px; color: #aaa; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            '.htmlspecialchars($pesan_singkat).'
        </div>
    </div>';
}
?>