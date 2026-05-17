<?php
include '../../koneksi.php';
$id_user = $_GET['id_user'];

// --- LOGIKA AUTO-READ (Centang Biru) ---
// Update semua pesan dari user ini menjadi 'dibaca' (1)
mysqli_query($koneksi, "UPDATE chat SET is_read = 1 WHERE id_user = '$id_user' AND pengirim = 'user' AND is_read = 0");

$chats = mysqli_query($koneksi, "SELECT * FROM chat WHERE id_user = '$id_user' ORDER BY waktu ASC");

while($row = mysqli_fetch_assoc($chats)) {
    $isAdmin = ($row['pengirim'] == 'admin');
    $class = $isAdmin ? 'admin-msg' : 'user-msg';
    $pesan = $row['pesan'];
    
    // Deteksi Gambar
    $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $pesan);

    echo '<div class="chat-bubble '.$class.'">';
    if($is_image) {
        // Gunakan path yang benar ke folder uploads
        echo '<img src="../../uploads/'.$pesan.'" style="max-width:200px; border-radius:10px; cursor:pointer;" onclick="zoomImage(this.src)">';
    } else {
        echo htmlspecialchars($pesan);
    }
    
    // Tambahin status centang di sisi admin juga biar sinkron kalau lo mau
    echo '<span class="msg-time">'.date('H:i', strtotime($row['waktu'])).'</span>';
    
    // Jika admin yang kirim, tampilkan centang (opsional di sisi admin)
    if($isAdmin) {
        $tickColor = ($row['is_read'] == 1) ? '#4fc3f7' : '#888';
        $ticks = ($row['is_read'] == 1) ? '✓✓' : '✓';
        echo '<span style="color:'.$tickColor.'; font-size:10px; margin-left:5px;">'.$ticks.'</span>';
    }
    
    echo '</div>';
}
?>