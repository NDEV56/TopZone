<?php
include '../../koneksi.php';

// 1. Ambil status admin saat ini
$check_status = mysqli_query($koneksi, "SELECT status_admin FROM users LIMIT 1");
$row_status = mysqli_fetch_assoc($check_status);
$current_status = $row_status['status_admin'] ?? 'offline';
$is_online = ($current_status == 'online');

// --- BAGIAN TOMBOL TOGGLE COMPACT (PALING ATAS) ---
echo '
<div class="admin-status-bar">
    <div class="status-info">
        <span class="status-label">ADMIN STATUS</span>
        <span id="statusLabel" class="status-value '.($is_online ? 'online-color' : 'offline-color').'">
            '.strtoupper($current_status).'
        </span>
    </div>
    <label class="compact-switch">
        <input type="checkbox" id="statusToggle" '.($is_online ? 'checked' : '').' onchange="updateAdminStatus(this)">
        <span class="compact-slider"></span>
    </label>
</div>

<style>
    .admin-status-bar {
        padding: 12px 15px;
        background: rgba(255, 255, 255, 0.03); 
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .status-info { display: flex; flex-direction: column; gap: 2px; }
    .status-label { font-size: 9px; color: #647b9b; letter-spacing: 1px; font-weight: 800; }
    .status-value { font-size: 11px; font-weight: bold; transition: 0.3s ease; }
    
    /* Indikator Warna Neon */
    .online-color { color: #00ff88; text-shadow: 0 0 10px rgba(0, 255, 136, 0.5); }
    .offline-color { color: #ff4444; text-shadow: 0 0 10px rgba(255, 68, 68, 0.5); }

    /* Switch Compact Design */
    .compact-switch { position: relative; display: inline-block; width: 34px; height: 18px; }
    .compact-switch input { opacity: 0; width: 0; height: 0; }
    .compact-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(255, 255, 255, 0.1); transition: .4s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .compact-slider:before {
        position: absolute; content: ""; height: 12px; width: 12px; left: 2px; bottom: 2px;
        background-color: white; transition: .4s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    input:checked + .compact-slider { background-color: #00ff88; box-shadow: 0 0 10px rgba(0, 255, 136, 0.3); }
    input:checked + .compact-slider:before { transform: translateX(16px); }
</style>
';

// --- BAGIAN LIST USER ---
$query = mysqli_query($koneksi, "SELECT u.id, u.username, u.foto, c.pesan, c.waktu 
    FROM users u 
    JOIN chat c ON u.id = c.id_user 
    WHERE c.id_chat IN (SELECT MAX(id_chat) FROM chat GROUP BY id_user)
    ORDER BY c.waktu DESC");

if (!$query) { die("Query Error: " . mysqli_error($koneksi)); }

while($row = mysqli_fetch_assoc($query)) {
    $pesan_singkat = strlen($row['pesan']) > 30 ? substr($row['pesan'], 0, 30) . "..." : $row['pesan'];
    $waktu = date('H:i', strtotime($row['waktu']));
    $foto_user = (!empty($row['foto'])) ? $row['foto'] : 'default-profile.png';

    echo '
    <div class="user-item" id="user-'.$row['id'].'" onclick="openChat('.$row['id'].', \''.htmlspecialchars($row['username']).'\', \''.$foto_user.'\')" 
         style="padding: 16px 18px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); cursor: pointer; transition: all 0.2s ease; position: relative;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="../../uploads/'.$foto_user.'" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="user-name" style="font-weight: 600; font-size: 13.5px;">'.htmlspecialchars($row['username']).'</span>
                    <span style="font-size: 11px; color: #647b9b;">'.$waktu.'</span>
                </div>
                <div style="font-size: 12px; color: #a3b8cc; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    '.htmlspecialchars($pesan_singkat).'
                </div>
            </div>
        </div>
    </div>';
}
?>