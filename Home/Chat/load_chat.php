<?php
session_start();
include '../koneksi.php';

if(isset($_SESSION['id_user'])){
    $id_user_skrg = $_SESSION['id_user'];
    $chats = mysqli_query($koneksi, "SELECT * FROM chat WHERE id_user = '$id_user_skrg' ORDER BY waktu ASC");
    
    while($row = mysqli_fetch_assoc($chats)):
        $is_me = ($row['pengirim'] == 'user');
?>
    <div style="margin-bottom:15px; display:flex; flex-direction:column; <?= $is_me ? 'align-items:flex-end;' : 'align-items:flex-start;' ?>">
        <div style="padding:10px; border-radius:12px; max-width:80%; font-size:13px; <?= $is_me ? 'background:#007bff; color:white;' : 'background:#eee; color:#333;' ?>">
            <?= htmlspecialchars($row['pesan']) ?>
        </div>
        <div style="font-size:10px; color:#999; margin-top:4px;">
            <?= date('H:i', strtotime($row['waktu'])) ?>
            <?php if($is_me): ?>
                <span style="margin-left:3px; color:<?= $row['is_read'] == 1 ? '#4fc3f7' : '#ccc' ?>;">
                    <?= $row['is_read'] == 1 ? '✓✓' : '✓' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endwhile; } ?>