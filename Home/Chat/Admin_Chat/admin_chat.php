<?php
/**
 * admin_chat.php — HARDENED v3.1
 *   • require_admin
 *   • Prepared SQL untuk daftar user (di file AJAX)
 *   • CSRF dipasang di header AJAX
 *   • XSS-safe — tidak inject HTML dari nama user via inline JS
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TopZone Admin - Chat</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
        * { box-sizing: border-box; }
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; overflow: hidden; }
        .sidebar { width: 220px; height: 100vh; background: #000; padding: 15px; position: fixed; border-right: 1px solid #333; }
        .sidebar h1 { color: var(--primary); font-size: 20px; margin-bottom: 25px; letter-spacing: 1px; }
        .nav-link { display: block; color: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px; transition: 0.3s; margin-bottom: 5px; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #222; color: var(--primary); }
        .content { margin-left: 220px; display: flex; width: calc(100% - 220px); height: 100vh; }
        .user-list { width: 280px; background: #181818; border-right: 1px solid #333; overflow-y: auto; }
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #121212; }
        #chatHeader { padding: 12px 20px; background: #1e1e1e; border-bottom: 1px solid #333; font-weight: bold; color: var(--primary); font-size: 14px; }
        #chatWindow { flex: 1; padding: 15px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; background-image: radial-gradient(#222 1px, transparent 1px); background-size: 20px 20px; }
        .input-area { padding: 12px 20px; background: #1e1e1e; display: flex; gap: 10px; border-top: 1px solid #333; align-items: center; }
        input[type="text"] { flex: 1; background: #252525; border: 1px solid #444; color: #fff; padding: 10px 18px; border-radius: 20px; outline: none; transition: 0.3s; font-size: 13px; }
        input[type="text"]:focus { border-color: var(--primary); }
        button { background: var(--primary); color: #000; border: none; padding: 10px 22px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 12px; }
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
            <input type="hidden" id="csrf_token" value="<?= tz_attr(tz_csrf_token()) ?>">
            <input type="text" id="adminMsg" placeholder="Ketik balasan..." autocomplete="off" maxlength="1000">
            <button onclick="sendAdminChat()">KIRIM</button>
        </div>
    </div>
</div>

<script>
    let currentUserId = null;
    const csrfToken = document.getElementById('csrf_token').value;

    function loadUserList() {
        $.ajax({
            url: 'ajax_admin_list_user.php',
            success: function(data) {
                $('#userList').html(data);
                if (currentUserId) $('#user-' + currentUserId).addClass('active');
            }
        });
    }

    function openChat(id, name) {
        currentUserId = parseInt(id, 10);
        if (!Number.isFinite(currentUserId) || currentUserId <= 0) return;
        $('#selected_user_id').val(currentUserId);
        $('#chatHeader').empty()
            .append('Chat dengan: ')
            .append($('<span style="color:#fff"></span>').text(name));
        $('.user-item').removeClass('active');
        $('#user-' + currentUserId).addClass('active');
        loadMessages();
    }

    function loadMessages() {
        if (!currentUserId) return;
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

    function sendAdminChat() {
        const msg = $('#adminMsg').val();
        if (msg.trim() == "" || !currentUserId) return;
        $.ajax({
            url: 'ajax_admin_send.php',
            type: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            data: { id_user: currentUserId, pesan: msg, _csrf: csrfToken },
            success: function() {
                $('#adminMsg').val('');
                loadMessages();
                loadUserList();
            }
        });
    }

    function scrollChatToBottom() {
        const objDiv = document.getElementById("chatWindow");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    setInterval(() => { loadUserList(); loadMessages(); }, 2000);

    $(document).ready(function() { loadUserList(); });
    document.getElementById('adminMsg').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') sendAdminChat();
    });
</script>
</body>
</html>
