<?php
/**
 * admin_chat.php — HARDENED v3.1 (sync NAFI update)
 *   • require_admin
 *   • CSRF token dipasang di AJAX header
 *   • XSS-safe (semua data dari server di-set lewat .text()/innerText)
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset & Dasar */
        * { box-sizing: border-box; } 
        :root { --primary: #00ff88; --dark: #121212; --card: #1e1e1e; --text: #eee; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: var(--text); margin: 0; display: flex; overflow: hidden; }
        
        /* Sidebar Utama */
        .sidebar { width: 220px; height: 100vh; background: #000; padding: 15px; position: fixed; border-right: 1px solid #333; z-index: 100; }
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

        /* Area Chat Kanan */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #121212; position: relative; border-right: 1px solid #333; }
        #chatHeader { padding: 12px 20px; background: #1e1e1e; border-bottom: 1px solid #333; font-weight: bold; color: var(--primary); font-size: 14px; }
        #chatWindow { flex: 1; padding: 15px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; background-image: radial-gradient(#222 1px, transparent 1px); background-size: 20px 20px; }
        
        /* Bubble Chat */
        .chat-bubble { padding: 10px 15px; border-radius: 12px; max-width: 65%; font-size: 13px; line-height: 1.4; position: relative; color: #fff; display: flex; flex-direction: column; }
        .admin-msg { background: #00ff88; color: #000; align-self: flex-end; border-bottom-right-radius: 2px; }
        .user-msg { background: #333; align-self: flex-start; border-bottom-left-radius: 2px; }
        .msg-time { font-size: 9px; margin-top: 4px; opacity: 0.6; align-self: flex-end; }

        /* Preview Panel */
        #previewPanel { display: none; padding: 15px; background: #1e1e1e; border-top: 1px solid #333; position: relative; }
        #imgPreview { max-height: 150px; border-radius: 8px; border: 2px solid var(--primary); }
        .close-preview { position: absolute; top: 5px; right: 15px; color: #ff4444; cursor: pointer; font-size: 24px; }

        /* Input Area & Action Menu */
        .input-area { padding: 12px 20px; background: #1e1e1e; display: flex; gap: 12px; border-top: 1px solid #333; align-items: center; position: relative; }
        .action-menu { position: relative; display: flex; align-items: center; }
        .main-btn { background: #252525; color: var(--primary); border: 1px solid #444; width: 38px; height: 38px; border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
        .main-btn.active { transform: rotate(45deg); background: var(--primary); color: #000; }
        
        .menu-options { position: absolute; bottom: 55px; left: 0; display: flex; flex-direction: column; gap: 10px; opacity: 0; transform: translateY(15px) scale(0.8); pointer-events: none; transition: 0.3s; background: #1e1e1e; padding: 8px; border-radius: 20px; border: 1px solid #333; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }
        .menu-options.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }
        .menu-options button { background: #252525; color: var(--primary); border: 1px solid #444; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        input[type="text"] { flex: 1; background: #252525; border: 1px solid #444; color: #fff; padding: 10px 18px; border-radius: 20px; outline: none; font-size: 13px; }
        .send-btn { background: var(--primary); color: #000; border: none; padding: 10px 22px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 12px; }

        /* Modal Zoom */
        #imageModal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; }
        .user-info-panel { 
            width: 280px; 
            background: #181818; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 40px 20px;
            text-align: center;
        }

        .profile-img-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 20px;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.1);
        }

        .info-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .info-id {
            background: #252525;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 11px;
            color: #888;
            border: 1px solid #333;
            letter-spacing: 1px;
        }

        .status-badge {
            margin-top: 25px;
            font-size: 12px;
            color: #555;
            border-top: 1px solid #333;
            padding-top: 20px;
            width: 100%;
        }
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
    <!-- List User Sidebar Kiri -->
    <div class="user-list" id="userList"></div>

    <!-- Area Chat Tengah -->
    <div class="chat-area">
        <div id="chatHeader">Pilih pelanggan untuk memulai chat</div>
        <div id="chatWindow">
            <div style="color: #555; text-align: center; margin-top: 50px;">Belum ada percakapan terpilih</div>
        </div>

        <div id="previewPanel">
            <span class="close-preview" onclick="cancelPreview()">&times;</span>
            <img id="imgPreview" src="">
        </div>

        <div class="input-area">
            <input type="hidden" id="selected_user_id">
            <div class="action-menu">
                <button type="button" class="main-btn" id="toggleBtn" onclick="toggleMenu()">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <div class="menu-options" id="menuOptions">
                    <button type="button" title="Kirim Gambar" onclick="triggerUpload('fileInput')">
                        <i class="fa-solid fa-image"></i>
                    </button>
                    <button type="button" title="Buka Kamera" onclick="triggerUpload('cameraInput')">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </div>
            </div>

            <input type="file" id="fileInput" accept="image/png, image/jpeg, image/jpg, image/gif" style="display:none;" onchange="handleImageSelect(this)">
            <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display:none;" onchange="handleImageSelect(this)">

            <input type="hidden" id="csrf_token" value="<?= tz_attr(tz_csrf_token()) ?>">
            <input type="text" id="adminMsg" placeholder="Ketik balasan..." autocomplete="off" maxlength="1000">
            <button onclick="sendAdminChat()" style="background:#007bff; color:white; border:none; width:38px; height:38px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-paper-plane" style="font-size: 16px;"></i>
            </button>
        </div>
    </div>

    <!-- Panel Info Profil (Kanan Baru) -->
    <div class="user-info-panel" id="userInfoPanel">
        <!-- Placeholder gambar jika belum pilih user -->
        <img src="../../uploads/default-profile.png" class="profile-img-large" id="infoPic">
        <div class="info-name" id="infoName">User Profil</div>
        <div class="info-id" id="infoID">ID: #0000</div>
        
        <div class="status-badge">
            <i class="fa-solid fa-circle-check" style="color: #007bff;"></i> Pelanggan TopZone
        </div>
    </div>
</div>

<div id="imageModal" onclick="$(this).hide()">
    <span style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; cursor:pointer;">&times;</span>
    <img id="imgZoom" style="max-width:90%; max-height:90%; border-radius:10px;">
</div>
<div id="cameraModal" style="display:none; position:fixed; z-index:3000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; flex-direction:column;">
    <div style="background:#1e1e1e; padding:20px; border-radius:15px; text-align:center; border:1px solid var(--primary);">
        <h3 style="color:var(--primary); margin-top:0;">Ambil Foto Instan</h3>
        <video id="webcam" autoplay playsinline style="width: 100%; max-width: 400px; border-radius: 10px; background:#000;"></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
            <button onclick="takeSnapshot()" style="background:var(--primary); color:#000; border:none; padding:10px 20px; border-radius:20px; font-weight:bold; cursor:pointer;">
                <i class="fa-solid fa-camera"></i> CEKREK
            </button>
            <button onclick="closeCamera()" style="background:#ff4444; color:#fff; border:none; padding:10px 20px; border-radius:20px; font-weight:bold; cursor:pointer;">
                BATAL
            </button>
        </div>
    </div>
</div>

<script>
    let currentUserId = null;
    let selectedFile = null;
    let stream = null;

    function toggleMenu() {
        $('#menuOptions').toggleClass('show');
        $('#toggleBtn').toggleClass('active');
    }

    function triggerUpload(inputId) {
        if(inputId === 'cameraInput') {
            openWebcam(); // Panggil fungsi Webcam khusus PC
        } else {
            document.getElementById(inputId).click(); // Buka explorer kalau pilih "Gambar"
        }
        toggleMenu();
    }
    function closeCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop()); // Matikan lampu kamera
        }
        $('#cameraModal').hide();
    }
    function takeSnapshot() {
        const video = document.getElementById('webcam');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');

        // Sesuaikan ukuran hasil jepretan
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Konversi hasil gambar ke file Blob
        canvas.toBlob(function(blob) {
            // Kita bikin objek File biar bisa dikirim via AJAX bareng pesan teks
            selectedFile = new File([blob], "snapshot_" + Date.now() + ".jpg", { type: "image/jpeg" });
            
            // Munculin di preview chat
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imgPreview').attr('src', e.target.result);
                $('#previewPanel').slideDown();
            }
            reader.readAsDataURL(selectedFile);
            
            closeCamera();
        }, 'image/jpeg');
    }
    function openWebcam() {
        $('#cameraModal').css('display', 'flex');
        
        // Minta izin akses webcam
        navigator.mediaDevices.getUserMedia({ 
            video: { width: 1280, height: 720 } 
        })
        .then(function(s) {
            stream = s;
            const video = document.getElementById('webcam');
            video.srcObject = stream;
        })
        .catch(function(err) {
            alert("Waduh bray, kamera nggak bisa dibuka. Pastiin lo klik 'Allow' di browser atau kamera nggak lagi dipake aplikasi lain.");
            console.error(err);
            closeCamera();
        });
    }

    // Logic Tampil Preview Setelah Pilih Foto/Kamera
    function handleImageSelect(input) {
        const file = input.files[0];
        
        // Validasi: Cek apakah file ada dan apakah tipe filenya adalah image
        if (file) {
            if (!file.type.match('image.*')) {
                alert("Waduh bray, harus gambar ya! File tipe " + file.type + " nggak dibolehin.");
                input.value = ''; // Reset input
                return;
            }

            selectedFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imgPreview').attr('src', e.target.result);
                $('#previewPanel').slideDown();
                
                // Scroll otomatis ke bawah biar preview kelihatan
                scrollChatToBottom(); 
            }
            reader.readAsDataURL(file);
        }
    }

    function cancelPreview() {
        selectedFile = null;
        $('#previewPanel').slideUp();
        $('#fileInput, #cameraInput').val('');
    }

    function loadUserList() {
        $.ajax({
            url: 'ajax_admin_list_user.php', 
            success: function(data) { 
                $('#userList').html(data); 
                if(currentUserId) $('#user-' + currentUserId).addClass('active');
            }
        });
    }

    // Tambahkan parameter img di sini
    function openChat(id, name, foto) {
        currentUserId = parseInt(id, 10);
        if (!Number.isFinite(currentUserId) || currentUserId <= 0) return;
        $('#selected_user_id').val(currentUserId);

        // XSS-safe: pakai .text() bukan .html()
        $('#chatHeader').empty()
            .append('Chat dengan: ')
            .append($('<span style="color:#fff"></span>').text(name));

        // Update Panel Info Kanan — pakai .text()
        $('#infoName').text(name);
        $('#infoID').text('ID: #' + currentUserId);

        // Sanitasi foto path — basename saja, tolak ../
        let safeFoto = String(foto || 'Default.jpg').replace(/[\\/]/g, '');
        let pathFoto = "../../uploads/" + safeFoto;
        $('#infoPic').attr('src', pathFoto);

        $('.user-item').removeClass('active');
        $('#user-' + currentUserId).addClass('active');
        loadMessages();
    }
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

    function sendAdminChat() {
        let msg = $('#adminMsg').val();
        if (!currentUserId || (msg.trim() == "" && !selectedFile)) return;
        if (msg.length > 1000) { alert("Pesan terlalu panjang (max 1000 karakter)"); return; }

        const csrfTok = document.getElementById('csrf_token').value;

        let formData = new FormData();
        formData.append('id_user', currentUserId);
        formData.append('pesan', msg);
        formData.append('_csrf', csrfTok);
        if (selectedFile) {
            formData.append('gambar', selectedFile);
        }

        $.ajax({
            url: 'ajax_admin_send_image.php',
            type: 'POST',
            headers: { 'X-CSRF-Token': csrfTok },
            data: formData,
            contentType: false,
            processData: false,
            success: function() {
                $('#adminMsg').val('');
                cancelPreview();
                loadMessages();
                loadUserList();
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    alert("Sesi habis. Silakan login admin lagi.");
                    window.location.href = '../../../Login/tampilanlogin.php';
                } else if (xhr.status === 413) {
                    alert("File gambar terlalu besar (max 5 MB).");
                } else {
                    alert("Gagal mengirim pesan.");
                }
            }
        });
    }

    function scrollChatToBottom() {
        var objDiv = document.getElementById("chatWindow");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    function zoomImage(src) {
        $('#imgZoom').attr('src', src);
        $('#imageModal').css('display', 'flex');
    }

    // Klik luar menu buat nutup
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.action-menu').length) {
            $('#menuOptions').removeClass('show');
            $('#toggleBtn').removeClass('active');
        }
    });

    setInterval(() => { loadUserList(); loadMessages(); }, 3000);
    $(document).ready(function() { loadUserList(); });
    document.getElementById('adminMsg').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { sendAdminChat(); }
    });
    function updateAdminStatus(checkbox) {
        const status = checkbox.checked ? 'online' : 'offline';
        const label = document.getElementById('statusLabel');
        
        // Ganti class warna & text dengan animasi
        label.innerText = status.toUpperCase();
        if (checkbox.checked) {
            label.classList.remove('offline-color');
            label.classList.add('online-color');
        } else {
            label.classList.remove('online-color');
            label.classList.add('offline-color');
        }

        // Kirim ke database lewat AJAX dengan CSRF token
        const csrfTok = document.getElementById('csrf_token').value;
        fetch('update_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-CSRF-Token': csrfTok
            },
            body: 'status=' + encodeURIComponent(status) + '&_csrf=' + encodeURIComponent(csrfTok)
        });
    }
</script>
</body>
</html>