<?php
include '../../koneksi.php';
$id_user = $_GET['id_user'];

$chats = mysqli_query($koneksi, "SELECT * FROM chat WHERE id_user = '$id_user' ORDER BY waktu ASC");

while($row = mysqli_fetch_assoc($chats)) {
    $isAdmin = ($row['pengirim'] == 'admin');
    $class = $isAdmin ? 'admin-msg' : 'user-msg';
    
    echo '<div class="chat-bubble '.$class.'">';
    echo htmlspecialchars($row['pesan']);
    // Tambahin class msg-time di sini bray
    echo '<span class="msg-time">'.date('H:i', strtotime($row['waktu'])).'</span>';
    echo '</div>';
}
?>