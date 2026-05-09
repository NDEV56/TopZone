<?php
include '../../koneksi.php';
$id_user = $_GET['id_user'];

$chats = mysqli_query($koneksi, "SELECT * FROM chat WHERE id_user = '$id_user' ORDER BY waktu ASC");

while($row = mysqli_fetch_assoc($chats)) {
    $isAdmin = ($row['pengirim'] == 'admin');
    $class = $isAdmin ? 'admin-msg' : 'user-msg';
    $pesan = $row['pesan'];
    
    // Deteksi Gambar
    $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $pesan);

    echo '<div class="chat-bubble '.$class.'">';
    if($is_image) {
        echo '<img src="../../uploads/'.$pesan.'" style="max-width:200px; border-radius:10px; cursor:pointer;" onclick="zoomImage(this.src)">';
    } else {
        echo htmlspecialchars($pesan);
    }
    echo '<span class="msg-time">'.date('H:i', strtotime($row['waktu'])).'</span>';
    echo '</div>';
}
?>