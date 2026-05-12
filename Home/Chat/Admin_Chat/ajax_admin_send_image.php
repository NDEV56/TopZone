<?php
/**
 * ajax_admin_send_image.php — HARDENED v3.1 (file BARU dari NAFI)
 *   • require_admin
 *   • CSRF check
 *   • File upload validator (whitelist ext+MIME, double-ext block, size cap)
 *   • Random filename ke uploads/ (sudah ada .htaccess blok exec PHP)
 *   • Prepared INSERT
 */
require_once __DIR__ . '/../../_security.php';
tz_security_init();
tz_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('method-not-allowed');
}
tz_csrf_verify();

$id_user = (int)($_POST['id_user'] ?? 0);
$pesan   = trim((string)($_POST['pesan'] ?? ''));

if ($id_user <= 0) {
    http_response_code(400);
    exit('bad-user');
}
if (strlen($pesan) > 1000) {
    http_response_code(400);
    exit('msg-too-long');
}

$uploadsDir = tz_uploads_dir(); // memastikan .htaccess block-PHP ada

// 1. Cek upload gambar (kalau ada)
$gambarTersimpan = null;
if (isset($_FILES['gambar']) && is_array($_FILES['gambar'])
    && ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

    // Pakai validator helper — whitelist + MIME + double-ext block + size cap
    $safeName = tz_validate_upload(
        $_FILES['gambar'],
        ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        5 * 1024 * 1024 // 5 MB
    );
    if ($safeName === null) {
        http_response_code(400);
        exit('file-invalid');
    }
    // Pakai prefix IMG_ untuk konsistensi dengan UI lama
    $extOnly  = pathinfo($safeName, PATHINFO_EXTENSION);
    $finalNm  = 'IMG_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $extOnly;
    $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $finalNm;

    if (!@move_uploaded_file($_FILES['gambar']['tmp_name'], $destPath)) {
        error_log('[topzone-send-image] move_uploaded_file gagal');
        http_response_code(500);
        exit('upload-fail');
    }
    @chmod($destPath, 0644);
    $gambarTersimpan = $finalNm;
}

// 2. Insert gambar sebagai pesan (prepared)
try {
    if ($gambarTersimpan) {
        tz_db()->exec(
            "INSERT INTO chat (id_user, pesan, pengirim, waktu) VALUES (?, ?, 'admin', NOW())",
            [$id_user, $gambarTersimpan]
        );
    }
    // 3. Insert pesan teks (kalau ada)
    if ($pesan !== '') {
        tz_db()->exec(
            "INSERT INTO chat (id_user, pesan, pengirim, waktu) VALUES (?, ?, 'admin', NOW())",
            [$id_user, $pesan]
        );
    }
    echo 'success';
} catch (\Throwable $e) {
    error_log('[topzone-send-image] ' . $e->getMessage());
    http_response_code(500);
    echo 'db-fail';
}
