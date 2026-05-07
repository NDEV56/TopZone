<?php
// Jangan pake session_start() lagi di sini karena sudah ada di index.php
include '../koneksi.php'; // Keluar folder Chat ke Home

$id_user_skrg = $_SESSION['id_user'] ?? null;
if (!$id_user_skrg) { echo "Login dulu!"; exit; }
?>

<div id="displayChat" style="height:350px; overflow-y:auto; padding:15px; background:#f9f9f9;">
    </div>

<div style="padding:10px; border-top:1px solid #eee; display:flex; gap:5px;">
    <input type="text" id="msgInput" placeholder="Tulis pesan..." style="flex:1; padding:8px; border-radius:5px; border:1px solid #ddd;">
    <button onclick="sendLive()" style="background:#007bff; color:white; border:none; padding:5px 15px; border-radius:5px; cursor:pointer;">Kirim</button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Fungsi ambil pesan otomatis tiap 2 detik (LIVE)
function loadChat() {
    $.ajax({
        url: 'Chat/load_chat.php',
        type: 'GET',
        success: function(data) {
            $('#displayChat').html(data);
            // Scroll otomatis ke bawah kalau ada pesan baru
            var chatBox = document.getElementById("displayChat");
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
}

// Jalankan auto-load
setInterval(loadChat, 2000); 

// Fungsi kirim tanpa refresh
function sendLive() {
    var pesan = $('#msgInput').val();
    if(pesan == "") return;

    $.ajax({
        url: 'Chat/kirim_chat.php',
        type: 'POST',
        data: { pesan: pesan },
        success: function() {
            $('#msgInput').val(''); // Kosongin input
            loadChat(); // Langsung update tampilan
        }
    });
}
</script>