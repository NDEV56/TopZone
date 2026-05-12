<?php
/**
 * kirim_chat.php — HARDENED v3.1 (sync NAFI upload-image update)
 *   • require_login
 *   • CSRF check
 *   • Validasi file upload (whitelist ext+MIME)
 *   • Prepared SQL
 *   • Rate-limit anti chat-flood
 */
require_once __DIR__ . '/../_security.php';
tz_security_init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id_user = (int)($_SESSION['id_user'] ?? 0);
if ($id_user <= 0) {
    http_response_code(401);
    exit('login-needed');
}

tz_csrf_verify();

if (!tz_rate_limit('chat:user:' . $id_user, 30, 60)) {
    http_response_code(429);
    exit('rate-limit');
}

$pesan = trim((string)($_POST['pesan'] ?? ''));
if (strlen($pesan) > 1000) {
    http_response_code(400);
    exit('msg-too-long');
}

$uploadsDir = tz_uploads_dir();
$gambarNama = null;

// 1. Jika ada gambar
if (isset($_FILES['gambar']) && is_array($_FILES['gambar'])
    && ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

    $safe = tz_validate_upload(
        $_FILES['gambar'],
        ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        5 * 1024 * 1024
    );
    if ($safe === null) {
        http_response_code(400);
        exit('file-invalid');
    }
    $ext = pathinfo($safe, PATHINFO_EXTENSION);
    $gambarNama = 'IMG_USER_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dest = $uploadsDir . DIRECTORY_SEPARATOR . $gambarNama;

    if (!@move_uploaded_file($_FILES['gambar']['tmp_name'], $dest)) {
        error_log('[topzone-kirim-chat] move_uploaded_file gagal');
        http_response_code(500);
        exit('upload-fail');
    }
    @chmod($dest, 0644);
}

try {
    if ($gambarNama !== null) {
        // Simpan nama file gambar sebagai pesan
        tz_db()->exec(
            "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES (?, ?, 'user', 0)",
            [$id_user, $gambarNama]
        );
    }
    // Insert pesan teks (kalau ada)
    if ($pesan !== '') {
        tz_db()->exec(
            "INSERT INTO chat (id_user, pesan, pengirim, is_read) VALUES (?, ?, 'user', 0)",
            [$id_user, $pesan]
        );
    }
    echo 'ok';
} catch (\Throwable $e) {
    error_log('[topzone-kirim-chat] ' . $e->getMessage());
    http_response_code(500);
    echo 'db-fail';
}
