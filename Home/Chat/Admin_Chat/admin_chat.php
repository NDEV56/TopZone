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
/* ==========================================================================
   RESET & VARIABEL UTAMA (Topzone Blue Navy Theme)
   ========================================================================== */
* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
} 

:root { 
    --primary: #00d2ff; 
    --primary-glow: rgba(0, 210, 255, 0.35);
    --topzone-blue: #005cff;
    --navy-deep: #050d26;
    --navy-mid: #0b173a;
    --glass-bg: rgba(11, 23, 58, 0.45);
    --glass-border: rgba(255, 255, 255, 0.06);
    --text-main: #ffffff;
    --text-muted: #647b9b;
}

/* ==========================================================================
   BODY & ANIMASI LATAR BELAKANG (Liquid Blobs)
   ========================================================================== */
body { 
    font-family: 'Segoe UI', system-ui, sans-serif; 
    background: var(--navy-deep); 
    color: var(--text-main); 
    height: 100vh;
    display: flex; 
    overflow: hidden; 
    position: relative;
}

body::before, body::after {
    content: "";
    position: absolute;
    width: 500px;
    height: 500px;
    border-radius: 50%;
    background: linear-gradient(45deg, #002266, var(--topzone-blue), #00aaff);
    filter: blur(100px);
    z-index: -1;
    opacity: 0.5;
    animation: liquidMovement 15s infinite alternate ease-in-out;
}

body::after {
    right: -50px;
    bottom: -50px;
    background: linear-gradient(45deg, var(--topzone-blue), #001133, #00d2ff);
    animation-delay: -7.5s;
}

@keyframes liquidMovement {
    0% { transform: translate(0, 0) scale(1) rotate(0deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
    50% { transform: translate(80px, 40px) scale(1.1) rotate(180deg); border-radius: 30% 70% 70% 30% / 50% 30% 70% 50%; }
    100% { transform: translate(-40px, 60px) scale(0.95) rotate(360deg); border-radius: 50% 50% 30% 70% / 50% 60% 40% 60%; }
}

/* ==========================================================================
   SIDEBAR UTAMA
   ========================================================================== */
.sidebar { 
    width: 220px; 
    height: 100vh; 
    background: rgba(3, 8, 24, 0.75);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    padding: 20px 15px; 
    position: fixed; 
    border-right: 1px solid var(--glass-border); 
    z-index: 100; 
}

.sidebar h1 { 
    color: var(--text-main); 
    font-size: 20px; 
    margin-bottom: 25px; 
    letter-spacing: 1px; 
    font-weight: 700;
}

.nav-link { 
    display: block; 
    color: var(--text-muted); 
    text-decoration: none; 
    padding: 12px 15px; 
    border-radius: 12px; 
    transition: all 0.25s ease; 
    margin-bottom: 8px; 
    font-size: 14px; 
    font-weight: 500;
}

.nav-link:hover, .nav-link.active { 
    background: rgba(0, 92, 255, 0.15); 
    color: var(--primary); 
    border-left: 3px solid var(--primary);
    padding-left: 18px;
    box-shadow: 0 4px 15px rgba(0, 92, 255, 0.15);
}

/* Container Layout */
.content { 
    margin-left: 220px; 
    display: flex; 
    width: calc(100% - 220px); 
    height: 100vh; 
}

/* ==========================================================================
   LIST USER (SIDEBAR KIRI)
   ========================================================================== */
.user-list { 
    width: 280px; 
    background: rgba(4, 11, 31, 0.4); 
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-right: 1px solid var(--glass-border); 
    overflow-y: auto; 
}

.user-item { 
    padding: 16px 18px; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
    cursor: pointer; 
    transition: all 0.2s ease; 
    border-left: 3px solid transparent; 
}

.user-item:hover { 
    background: rgba(255, 255, 255, 0.02); 
}

.user-item.active { 
    background: rgba(0, 92, 255, 0.12); 
    border-left: 3px solid var(--primary); 
    backdrop-filter: blur(8px);
}

.user-name { 
    font-weight: 600; 
    color: var(--text-main); 
    font-size: 13.5px; 
    display: flex; 
    justify-content: space-between; 
}

.user-item.active .user-name {
    color: var(--primary);
}

/* ==========================================================================
   AREA CHAT & HEADER (MURNI TRANSPARAN / ANTI-HITAM)
   ========================================================================== */
.chat-area { 
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    background: rgba(5, 13, 38, 0.15); /* Diturunkan kepekatannya agar tidak hitam solid */
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    position: relative; 
    border-right: 1px solid var(--glass-border); 
}

#chatHeader { 
    padding: 18px 20px; 
    background: rgba(8, 18, 48, 0.4); /* Diubah jadi semi-transparan */
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border-bottom: 1px solid var(--glass-border); 
    font-weight: 600; 
    color: var(--text-main); 
    font-size: 15px; 
    box-shadow: 0 4px 25px rgba(3, 8, 24, 0.2);
}

#chatWindow { 
    flex: 1; 
    padding: 20px; 
    overflow-y: auto; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
    background-image: radial-gradient(rgba(0, 92, 255, 0.04) 1.5px, transparent 1.5px); 
    background-size: 24px 24px; 
}

/* Memperbaiki warna teks default bawaan chat agar tidak hitam kusam */
#chatWindow div[style*="color: #555"],
#chatWindow div[style*="color:#555"] {
    color: var(--text-muted) !important;
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* ==========================================================================
   BUBBLE CHAT (Pop-up Animation)
   ========================================================================== */
.chat-bubble { 
    padding: 12px 18px; 
    border-radius: 18px; 
    max-width: 65%; 
    font-size: 13.5px; 
    line-height: 1.5; 
    position: relative; 
    color: var(--text-main); 
    display: flex; 
    flex-direction: column; 
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid var(--glass-border);
    animation: bubblePop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.12) both;
}

@keyframes bubblePop {
    from { opacity: 0; transform: translateY(10px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.admin-msg { 
    background: linear-gradient(135deg, rgba(0, 92, 255, 0.25), rgba(0, 170, 255, 0.1)); 
    border: 1px solid rgba(0, 170, 255, 0.25);
    align-self: flex-end; 
    border-bottom-right-radius: 4px; 
    box-shadow: 0 4px 15px rgba(0, 92, 255, 0.1);
}

.user-msg { 
    background: rgba(255, 255, 255, 0.03); 
    align-self: flex-start; 
    border-bottom-left-radius: 4px; 
}

.msg-time { 
    font-size: 10px; 
    margin-top: 6px; 
    color: var(--text-muted);
    align-self: flex-end; 
}

/* ==========================================================================
   PANEL PREVIEW IMAGE (GLASS EFFECT)
   ========================================================================== */
#previewPanel { 
    display: none; 
    padding: 15px; 
    background: rgba(11, 23, 58, 0.4); /* Diubah dari navy solid menjadi transparan */
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-top: 1px solid var(--glass-border); 
    position: relative; 
}

#imgPreview { 
    max-height: 150px; 
    border-radius: 12px; 
    border: 2px solid var(--primary); 
    box-shadow: 0 0 15px var(--primary-glow);
}

.close-preview { 
    position: absolute; 
    top: 5px; 
    right: 15px; 
    color: #ff4a4a; 
    cursor: pointer; 
    font-size: 24px; 
    transition: transform 0.2s;
}
.close-preview:hover { transform: scale(1.1); }

/* ==========================================================================
   INPUT AREA & ACTION MENU
   ========================================================================== */
.input-area { 
    padding: 16px 20px; 
    background: rgba(6, 14, 38, 0.35); /* Transparansi ditingkatkan dari 0.8 */
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    display: flex; 
    gap: 12px; 
    border-top: 1px solid var(--glass-border); 
    align-items: center; 
    position: relative; 
}

.action-menu { position: relative; display: flex; align-items: center; }

.main-btn { 
    background: rgba(255, 255, 255, 0.06); /* Dibuat sedikit lebih terang agar kontras */
    color: var(--primary); 
    border: 1px solid var(--glass-border); 
    width: 40px; 
    height: 40px; 
    border-radius: 50%; 
    cursor: pointer; 
    transition: all 0.25s ease; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
}

.main-btn:hover {
    background: rgba(0, 92, 255, 0.15);
    box-shadow: 0 0 10px var(--primary-glow);
}

.main-btn.active { 
    transform: rotate(45deg); 
    background: var(--primary); 
    color: var(--navy-deep); 
    box-shadow: 0 0 15px var(--primary);
}

.menu-options { 
    position: absolute; 
    bottom: 60px; 
    left: 0; 
    display: flex; 
    flex-direction: column; 
    gap: 10px; 
    opacity: 0; 
    transform: translateY(15px) scale(0.8); 
    pointer-events: none; 
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
    background: rgba(8, 19, 48, 0.9); 
    backdrop-filter: blur(15px);
    padding: 10px; 
    border-radius: 25px; 
    border: 1px solid var(--glass-border); 
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4); 
}

.menu-options.show { 
    opacity: 1; 
    transform: translateY(0) scale(1); 
    pointer-events: all; 
}

.menu-options button { 
    background: rgba(255, 255, 255, 0.04); 
    color: var(--primary); 
    border: 1px solid var(--glass-border); 
    width: 38px; 
    height: 38px; 
    border-radius: 50%; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    transition: all 0.2s;
}

.menu-options button:hover {
    background: var(--primary);
    color: var(--navy-deep);
}

input[type="text"] { 
    flex: 1; 
    background: rgba(255, 255, 255, 0.05); /* Sedikit dinaikkan dari 0.03 agar terlihat bersih */
    border: 1px solid var(--glass-border); 
    color: #fff; 
    padding: 12px 20px; 
    border-radius: 25px; 
    outline: none; 
    font-size: 13.5px; 
    transition: all 0.25s ease;
}

input[type="text"]:focus {
    background: rgba(255, 255, 255, 0.08); 
    border-color: rgba(0, 170, 255, 0.4);
    box-shadow: 0 0 15px rgba(0, 170, 255, 0.15);
}

.send-btn { 
    background: linear-gradient(135deg, var(--topzone-blue), #00aaff); 
    color: #fff; 
    border: none; 
    padding: 12px 24px; 
    border-radius: 25px; 
    font-weight: 600; 
    cursor: pointer; 
    font-size: 13px; 
    transition: all 0.25s ease;
    box-shadow: 0 4px 15px rgba(0, 92, 255, 0.3);
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 170, 255, 0.4);
}

/* ==========================================================================
   USER INFO PANEL & STATUS BADGE
   ========================================================================== */
.user-info-panel { 
    width: 280px; 
    background: rgba(4, 11, 31, 0.4); 
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-left: 1px solid var(--glass-border);
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
    border: 3px solid var(--topzone-blue);
    margin-bottom: 20px;
    box-shadow: 0 0 25px rgba(0, 92, 255, 0.35);
    transition: transform 0.4s ease;
}

.profile-img-large:hover {
    transform: scale(1.04) rotate(3deg);
}

.info-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
}

.info-id {
    background: rgba(255, 255, 255, 0.04);
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 11px;
    color: var(--text-muted);
    border: 1px solid var(--glass-border);
    letter-spacing: 1px;
}

.status-badge {
    margin-top: 25px;
    font-size: 12px;
    color: var(--text-muted);
    border-top: 1px solid var(--glass-border);
    padding-top: 20px;
    width: 100%;
    background: transparent; 
}

/* Modal Zoom & Scrollbar */
#imageModal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(3, 7, 20, 0.94); align-items:center; justify-content:center; }

::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0, 92, 255, 0.2); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: rgba(0, 170, 255, 0.4); }
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