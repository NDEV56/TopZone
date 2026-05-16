<?php
/**
 * chat.php — User chat (HARDENED v3.1 sync NAFI update)
 *   • require_login (sebelumnya: cek manual, sekarang sentralisasi)
 *   • CSRF token disisipkan ke semua AJAX
 *   • Validasi file size + MIME di client (anti accidental upload besar)
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

if (!tz_is_logged_in()) {
    echo "Login dulu bray!";
    exit;
}
$id_user_skrg = (int)$_SESSION['id_user'];
$csrf = tz_csrf_token();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
    @keyframes tickPop {
        0% { transform: scale(0); opacity: 0; }
        50% { transform: scale(1.4); }
        100% { transform: scale(1); opacity: 1; }
    }
    .tick-anim { animation: tickPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: inline-block; }

    #displayChat::-webkit-scrollbar { width: 4px; }
    #displayChat::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
</style>

<input type="hidden" id="chat_csrf_token" value="<?= tz_attr($csrf) ?>">

<div id="displayChat" style="height:400px; overflow-y:auto; padding:15px; background:#121212; display:flex; flex-direction:column; gap:10px;">
</div>

<div id="previewPanel" style="display:none; padding:10px; background:#1e1e1e; border-top:1px solid #333; position:relative;">
    <span onclick="cancelPreview()" style="position:absolute; top:5px; right:15px; color:#ff4444; cursor:pointer; font-size:24px; font-weight:bold;">&times;</span>
    <img id="imgPreview" style="max-height:120px; border-radius:8px; border:2px solid #00ff88; display:block; margin:auto;">
</div>

<div style="padding:10px; border-top:1px solid #333; background:#1e1e1e; display:flex; gap:10px; align-items:center;">
    <div style="position:relative;">
        <button type="button" onclick="toggleMenu()" style="background:#333; color:#00ff88; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer;">
            <i class="fa-solid fa-plus" id="plusIcon" style="transition:0.3s;"></i>
        </button>

        <div id="menuOptions" style="display:none; position:absolute; bottom:55px; left:0; background:#252525; padding:10px; border-radius:15px; flex-direction:column; gap:15px; border:1px solid #444; z-index:100; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
            <button onclick="document.getElementById('fileInput').click(); toggleMenu()" style="background:none; border:none; color:#007bff; cursor:pointer; font-size:20px;"><i class="fa-solid fa-image"></i></button>
            <button onclick="openWebcam(); toggleMenu()" style="background:none; border:none; color:#28a745; cursor:pointer; font-size:20px;"><i class="fa-solid fa-camera"></i></button>
        </div>
    </div>

    <input type="file" id="fileInput" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="handleImageSelect(this)">
    <input type="text" id="msgInput" placeholder="Tulis pesan..." autocomplete="off" maxlength="1000" style="flex:1; padding:10px 18px; border-radius:25px; border:1px solid #444; background:#000; color:#fff; outline:none;">
    <button onclick="sendLive()" style="background:#007bff; color:white; border:none; padding:10px 22px; border-radius:25px; cursor:pointer; font-weight:bold;">KIRIM</button>
</div>

<div id="cameraModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; flex-direction:column;">
    <video id="webcam" autoplay playsinline style="width:90%; max-width:400px; border-radius:10px; border:2px solid #00ff88;"></video>
    <canvas id="canvas" style="display:none;"></canvas>
    <div style="margin-top:20px; display:flex; gap:10px;">
        <button onclick="takeSnapshot()" style="background:#00ff88; color:#000; border:none; padding:10px 25px; border-radius:20px; font-weight:bold; cursor:pointer;">FOTO</button>
        <button onclick="closeCamera()" style="background:#ff4444; color:#fff; border:none; padding:10px 25px; border-radius:20px; font-weight:bold; cursor:pointer;">BATAL</button>
    </div>
</div>

<script>
let selectedFile = null;
let stream = null;
let currentAdminStatus = 'offline';
const CHAT_CSRF = document.getElementById('chat_csrf_token').value;

function toggleMenu() {
    $('#menuOptions').fadeToggle(150).css('display', 'flex');
    $('#plusIcon').toggleClass('fa-rotate-45');
}

function handleImageSelect(input) {
    const file = input.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { alert("Gambar terlalu besar (max 5 MB)"); return; }
        if (!/^image\/(png|jpeg|webp|gif)$/.test(file.type)) {
            alert("Format gambar tidak didukung"); return;
        }
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = e => { $('#imgPreview').attr('src', e.target.result); $('#previewPanel').slideDown(); }
        reader.readAsDataURL(file);
    }
}

function cancelPreview() { selectedFile = null; $('#previewPanel').slideUp(); $('#fileInput').val(''); }

function openWebcam() {
    $('#cameraModal').css('display', 'flex');
    navigator.mediaDevices.getUserMedia({ video: true })
    .then(s => { stream = s; document.getElementById('webcam').srcObject = stream; })
    .catch(err => { alert("Kamera Error!"); closeCamera(); });
}

function closeCamera() { if(stream) stream.getTracks().forEach(t => t.stop()); $('#cameraModal').hide(); }

function takeSnapshot() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        selectedFile = new File([blob], "snap.jpg", {type:"image/jpeg"});
        $('#imgPreview').attr('src', canvas.toDataURL('image/jpeg'));
        $('#previewPanel').slideDown();
        closeCamera();
    }, 'image/jpeg');
}

function updateTicksRealTime() {
    document.querySelectorAll('.tick-container').forEach(container => {
        const isRead = container.getAttribute('data-read');
        if (isRead == '1') {
            container.style.color = '#4fc3f7';
            container.innerText = '✓✓';
        } else if (currentAdminStatus === 'online') {
            container.style.color = '#888';
            container.innerText = '✓✓';
        } else {
            container.style.color = '#888';
            container.innerText = '✓';
        }
    });
}

function cekStatusAdmin() {
    fetch('Chat/Admin_Chat/update_status.php', { credentials: 'same-origin' })
        .then(res => res.text())
        .then(status => { currentAdminStatus = (status || '').trim(); })
        .catch(() => {});
}

function loadChat() {
    $.ajax({
        url: 'Chat/load_chat.php',
        success: function(data) {
            $('#displayChat').html(data);
            if(typeof updateTicksRealTime === "function") updateTicksRealTime();
        }
    });
}

function sendLive() {
    var pesan = $('#msgInput').val();
    if (pesan.trim() == "" && !selectedFile) return;
    if (pesan.length > 1000) { alert("Pesan terlalu panjang"); return; }

    let formData = new FormData();
    formData.append('pesan', pesan);
    formData.append('_csrf', CHAT_CSRF);
    if (selectedFile) formData.append('gambar', selectedFile);

    $.ajax({
        url: 'Chat/kirim_chat.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CHAT_CSRF },
        data: formData,
        contentType: false,
        processData: false,
        success: function() {
            $('#msgInput').val('');
            cancelPreview();
            loadChat();
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                alert("Sesi habis, silakan login lagi.");
                window.location.href = '../../Login/tampilanlogin.php';
            } else if (xhr.status === 413) {
                alert("File terlalu besar.");
            } else if (xhr.status === 429) {
                alert("Terlalu banyak pesan, pelan-pelan ya.");
            }
        }
    });
}

setInterval(() => { loadChat(); cekStatusAdmin(); }, 2000);
$(document).on('click', e => { if (!$(e.target).closest('button').length) $('#menuOptions').fadeOut(150); });
$('#msgInput').on('keypress', e => { if(e.which === 13) sendLive(); });
</script>
