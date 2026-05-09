<?php
// chat.php - Sisi User
include '../koneksi.php'; 
$id_user_skrg = $_SESSION['id_user'] ?? null;
if (!$id_user_skrg) { echo "Login dulu bray!"; exit; }
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div id="displayChat" style="height:350px; overflow-y:auto; padding:15px; background:#121212; display:flex; flex-direction:column; gap:10px;">
    </div>

<div id="previewPanel" style="display:none; padding:10px; background:#1e1e1e; border-top:1px solid #333; position:relative;">
    <span onclick="cancelPreview()" style="position:absolute; top:5px; right:15px; color:#ff4444; cursor:pointer; font-size:24px; font-weight:bold;">&times;</span>
    <img id="imgPreview" style="max-height:120px; border-radius:8px; border:2px solid #00ff88; display:block; margin:auto;">
</div>

<div style="padding:10px; border-top:1px solid #333; background:#1e1e1e; display:flex; gap:10px; align-items:center;">
    
    <div style="position:relative;">
        <button type="button" onclick="toggleMenu()" style="background:#333; color:#00ff88; ...">
            <i class="fa-solid fa-plus" id="plusIcon"></i> + <!-- Tambahin tanda plus biasa buat ngetes -->
        </button>
        
        <div id="menuOptions" style="display:none; position:absolute; bottom:55px; left:0; background:#252525; padding:10px; border-radius:15px; flex-direction:column; gap:15px; border:1px solid #444; z-index:100; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
            <button onclick="document.getElementById('fileInput').click(); toggleMenu()" title="Galeri" style="background:none; border:none; color:#007bff; cursor:pointer; font-size:20px;">
                <i class="fa-solid fa-image"></i>
            </button>
            <button onclick="openWebcam(); toggleMenu()" title="Kamera" style="background:none; border:none; color:#28a745; cursor:pointer; font-size:20px;">
                <i class="fa-solid fa-camera"></i>
            </button>
        </div>
    </div>

    <input type="file" id="fileInput" accept="image/*" style="display:none;" onchange="handleImageSelect(this)">

    <input type="text" id="msgInput" placeholder="Tulis pesan..." autocomplete="off" style="flex:1; padding:10px 18px; border-radius:25px; border:1px solid #444; background:#000; color:#fff; outline:none; font-size:14px;">
    
    <button onclick="sendLive()" style="background:#007bff; color:white; border:none; padding:10px 22px; border-radius:25px; cursor:pointer; font-weight:bold; font-size:12px;">
        KIRIM
    </button>
</div>

<div id="cameraModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; flex-direction:column;">
    <div style="background:#1e1e1e; padding:20px; border-radius:15px; text-align:center; border:1px solid #00ff88;">
        <video id="webcam" autoplay playsinline style="width:100%; max-width:400px; border-radius:10px; background:#000;"></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div style="margin-top:20px; display:flex; gap:10px; justify-content:center;">
            <button onclick="takeSnapshot()" style="background:#00ff88; color:#000; border:none; padding:10px 25px; border-radius:20px; font-weight:bold; cursor:pointer;">AMBIL FOTO</button>
            <button onclick="closeCamera()" style="background:#ff4444; color:#fff; border:none; padding:10px 25px; border-radius:20px; font-weight:bold; cursor:pointer;">BATAL</button>
        </div>
    </div>
</div>

<script>
let selectedFile = null;
let stream = null;

// Togle Menu + (Persis logic Admin)
function toggleMenu() { 
    $('#menuOptions').fadeToggle(150).css('display', 'flex');
    $('#plusIcon').toggleClass('fa-rotate-45'); // Variasi: icon muter dikit pas diklik
}

// Handler Pilih Gambar (Admin logic)
function handleImageSelect(input) {
    const file = input.files[0];
    if (file && file.type.match('image.*')) {
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#imgPreview').attr('src', e.target.result);
            $('#previewPanel').slideDown();
        }
        reader.readAsDataURL(file);
    }
}

function cancelPreview() {
    selectedFile = null;
    $('#previewPanel').slideUp();
    $('#fileInput').val('');
}

// Fungsi Kamera (Persis dari Admin Chat)
function openWebcam() {
    $('#cameraModal').css('display', 'flex');
    navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 720 } })
    .then(s => { stream = s; document.getElementById('webcam').srcObject = stream; })
    .catch(err => { alert("Kamera Error: " + err); closeCamera(); });
}

function takeSnapshot() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        selectedFile = new File([blob], "user_snap_" + Date.now() + ".jpg", {type:"image/jpeg"});
        $('#imgPreview').attr('src', canvas.toDataURL('image/jpeg'));
        $('#previewPanel').slideDown();
        closeCamera();
    }, 'image/jpeg');
}

function closeCamera() {
    if(stream) stream.getTracks().forEach(t => t.stop());
    $('#cameraModal').hide();
}

// Auto Load Chat (Per 2 detik)
function loadChat() {
    $.ajax({
        url: 'Chat/load_chat.php',
        type: 'GET',
        success: function(data) {
            $('#displayChat').html(data);
            var chatBox = document.getElementById("displayChat");
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
}
setInterval(loadChat, 2000);

// Kirim Pesan & Gambar (Pake FormData biar file keangkat)
function sendLive() {
    var pesan = $('#msgInput').val();
    if(pesan.trim() == "" && !selectedFile) return;

    let formData = new FormData();
    formData.append('pesan', pesan);
    if(selectedFile) formData.append('gambar', selectedFile);

    $.ajax({
        url: 'Chat/kirim_chat.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function() {
            $('#msgInput').val('');
            cancelPreview();
            loadChat();
        }
    });
}

// Klik luar menu buat nutup menu melayang
$(document).on('click', function(e) {
    if (!$(e.target).closest('button[onclick="toggleMenu()"]').length && !$(e.target).closest('#menuOptions').length) {
        $('#menuOptions').fadeOut(150);
    }
});

// Fitur Enter buat kirim
$('#msgInput').on('keypress', function (e) {
    if(e.which === 13) sendLive();
});
</script>