<?php
// Keluar 2 tingkat untuk ketemu koneksi.php di folder Home
include '../../koneksi.php'; 
// Pastiin query-nya begini bray:
$query = mysqli_query($koneksi, "SELECT u.id, u.username, c.pesan, c.waktu 
    FROM users u 
    JOIN chat c ON u.id = c.id_user 
    WHERE c.id_chat IN (SELECT MAX(id_chat) FROM chat GROUP BY id_user)
    ORDER BY c.waktu DESC"); // Kuncinya ada di DESC (Descending)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Chat</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
        /* Reset & Dasar */
        * { box-sizing: border-box; } 
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; overflow: hidden; }
        
        /* Sidebar Utama (Dikecilkan biar nggak kebesaran) */
        .sidebar { width: 220px; height: 100vh; background: #000; padding: 15px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 20px; margin-bottom: 25px; letter-spacing: 1px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px; transition: 0.3s; margin-bottom: 5px; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }

        .content { margin-left: 220px; display: flex; width: calc(100% - 220px); height: 100vh; }
        
        /* List User Sidebar Kiri */
        .user-list { width: 280px; background: #181818; border-right: 1px solid #333; overflow-y: auto; }
        .user-item { padding: 12px 15px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.3s; border-left: 3px solid transparent; }
        .user-item:hover { background: #252525; }
        .user-item.active { background: #222; border-left: 3px solid var(--primary); }
        .user-name { font-weight: bold; color: var(--primary); font-size: 13px; display: flex; justify-content: space-between; }
        .user-time { font-size: 10px; color: #666; font-weight: normal; }
        .last-msg { font-size: 11px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; }

        /* Area Chat Kanan */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #121212; }
        #chatHeader { padding: 12px 20px; background: #1e1e1e; border-bottom: 1px solid #333; font-weight: bold; color: var(--primary); font-size: 14px; }
        
        #chatWindow { flex: 1; padding: 15px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; background-image: radial-gradient(#222 1px, transparent 1px); background-size: 20px 20px; }
        
        /* Bubble Chat Aesthetic & Waktu Kecil */
        .chat-bubble { 
            padding: 10px 15px; 
            border-radius: 12px; 
            max-width: 65%; 
            font-size: 13px; 
            line-height: 1.4; 
            position: relative; 
            color: #fff;
            display: flex;
            flex-direction: column;
        }
        .admin-msg { background: #00ff88; color: #000; align-self: flex-end; border-bottom-right-radius: 2px; box-shadow: 0 2px 8px rgba(0, 255, 136, 0.15); }
        .user-msg { background: #333; align-self: flex-start; border-bottom-left-radius: 2px; }
        
        /* Indikator Waktu Di Pojok */
        .msg-time { 
            font-size: 9px; 
            margin-top: 4px; 
            opacity: 0.6; 
            display: block;
            align-self: flex-end; 
        }

        /* Input Area Ramping */
        .input-area { padding: 12px 20px; background: #1e1e1e; display: flex; gap: 10px; border-top: 1px solid #333; align-items: center; }
        input[type="text"] { flex: 1; background: #252525; border: 1px solid #444; color: #fff; padding: 10px 18px; border-radius: 20px; outline: none; transition: 0.3s; font-size: 13px; }
        input[type="text"]:focus { border-color: var(--primary); }
        button { background: var(--primary); color: #000; border: none; padding: 10px 22px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 12px; }
        button:hover { transform: scale(1.03); filter: brightness(1.1); }
    </style>
</head>
<body>

<div class="sidebar">
    <h1>TOPZONE.</h1>
    <a href="../../admin_orders.php" class="nav-link">📦 Pesanan Masuk</a>
    <a href="../../admin_tambah_game.php" class="nav-link">🎮 Kelola Game</a>
    <a href="../../admin_paket.php" class="nav-link">💎 Kelola Paket</a>
    <a href="admin_chat.php" class="nav-link active">💬 Chat Pelanggan</a>
    <a href="../../index.php" class="nav-link">🏠 Lihat Website</a>
</div>

<div class="content">
    <div class="user-list" id="userList"></div>

    <div class="chat-area">
        <div id="chatHeader">Pilih pelanggan untuk memulai chat</div>
        <div id="chatWindow">
            <div style="color: #555; text-align: center; margin-top: 50px;">Belum ada percakapan terpilih</div>
        </div>
        <div class="input-area">
            <input type="hidden" id="selected_user_id">
            <input type="text" id="adminMsg" placeholder="Ketik balasan untuk pelanggan..." autocomplete="off">
            <button onclick="sendAdminChat()">KIRIM</button>
        </div>
    </div>
</div>

<script>
    let currentUserId = null;

    // Load daftar user (AJAX memanggil file di folder yang sama)
    function loadUserList() {
        $.ajax({
            url: 'ajax_admin_list_user.php', 
            success: function(data) { 
                $('#userList').html(data); 
                // Tetap tandai user yang sedang aktif
                if(currentUserId) {
                    $('#user-' + currentUserId).addClass('active');
                }
            }
        });
    }

    // Klik user untuk buka chat
    function openChat(id, name) {
        currentUserId = id;
        $('#selected_user_id').val(id);
        $('#chatHeader').html('Chat dengan: <span style="color:#fff">' + name + '</span>');
        $('.user-item').removeClass('active');
        $('#user-' + id).addClass('active');
        loadMessages();
    }

    // Load isi pesan
    function loadMessages() {
        if(!currentUserId) return;
        $.ajax({
            url: 'ajax_admin_load_chat.php',
            type: 'GET',
            data: { id_user: currentUserId },
            success: function(data) {
                $('#chatWindow').html(data);
                scrollChatToBottom();
            }
        });
    }

    // Kirim pesan admin
    function sendAdminChat() {
        let msg = $('#adminMsg').val();
        if(msg.trim() == "" || !currentUserId) return;
        
        $.ajax({
            url: 'ajax_admin_send.php',
            type: 'POST',
            data: { id_user: currentUserId, pesan: msg },
            success: function() {
                $('#adminMsg').val(''); // Kosongkan input
                loadMessages(); // Refresh chat
                loadUserList(); // Update pesan terakhir di sidebar
            }
        });
    }

    function scrollChatToBottom() {
        var objDiv = document.getElementById("chatWindow");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    // Real-time update tiap 2 detik
    setInterval(() => {
        loadUserList();
        loadMessages();
    }, 2000);

    // Initial Load
    $(document).ready(function() {
        loadUserList();
    });

    // Support tombol Enter
    document.getElementById('adminMsg').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { sendAdminChat(); }
    });
</script>
</body>
</html>