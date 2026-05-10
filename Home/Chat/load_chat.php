<?php
session_start();
include '../koneksi.php';

if(isset($_SESSION['id_user'])){
    $id_user_skrg = $_SESSION['id_user'];
    $chats = mysqli_query($koneksi, "SELECT * FROM chat WHERE id_user = '$id_user_skrg' ORDER BY waktu ASC");
    
    while($row = mysqli_fetch_assoc($chats)):
        $is_me = ($row['pengirim'] == 'user');
        $pesan = $row['pesan'];
        $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $pesan);
?>
    <div style="margin-bottom:10px; display:flex; flex-direction:column; <?= $is_me ? 'align-items:flex-end;' : 'align-items:flex-start;' ?>">
        <div style="padding:10px; border-radius:12px; max-width:75%; font-size:13px; <?= $is_me ? 'background:#007bff; color:white; border-bottom-right-radius:2px;' : 'background:#333; color:#eee; border-bottom-left-radius:2px;' ?>">
            <?php if($is_image): ?>
                <img src="uploads/<?= $pesan ?>" style="max-width:100%; border-radius:8px; cursor:pointer;" onclick="zoomImage(this.src)">
            <?php else: ?>
                <?= htmlspecialchars($pesan) ?>
            <?php endif; ?>
        </div>
        <div style="font-size:9px; color:#666; margin-top:4px; display:flex; align-items:center; gap:3px;">
            <?= date('H:i', strtotime($row['waktu'])) ?>
            <?php if($is_me): ?>
                <span class="tick-container" data-read="<?= $row['is_read'] ?>" style="color:<?= $row['is_read'] == 1 ? '#4fc3f7' : '#888' ?>;">
                    <?= $row['is_read'] == 1 ? '✓✓' : '✓' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endwhile; } ?>