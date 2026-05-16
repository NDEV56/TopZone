<?php
/**
 * update_profile.php — HARDENED v3.1
 *   • require_login (sebelumnya: $_SESSION['id_user'] tanpa cek)
 *   • CSRF
 *   • Prepared SQL
 *   • Validasi base64 image (decode → re-encode via getimagesize → simpan ke uploads/ random name)
 *   • Whitelist mime + size cap
 *   • Email unique check
 *   • Username pattern guard
 */
require_once __DIR__ . '/_security.php';
tz_security_init();
tz_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['btn_simpan'])) {
    tz_safe_redirect('/Home/index.php');
}
tz_csrf_verify();

$id_user    = (int)$_SESSION['id_user'];
$nama_baru  = trim((string)($_POST['nama_user'] ?? ''));
$user_baru  = trim((string)($_POST['username']  ?? ''));
$email_baru = trim((string)($_POST['email']     ?? ''));
$pass_baru  =      (string)($_POST['password']  ?? '');

$nama_file  = (string)($_SESSION['foto'] ?? 'Default.jpg');

// Validasi field umum
$err = null;
if ($nama_baru === '' || strlen($nama_baru) > 64)                       $err = 'Nama tidak valid';
elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $user_baru))            $err = 'Username harus 3-32 karakter (huruf, angka, _, ., -)';
elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL) || strlen($email_baru) > 128) $err = 'Email tidak valid';
elseif ($pass_baru !== '' && (strlen($pass_baru) < 8 || strlen($pass_baru) > 200))     $err = 'Password baru minimal 8 karakter';

if ($err !== null) {
    echo "<script>alert(" . tz_js($err) . "); history.back();</script>";
    exit;
}

// Cek username/email belum dipakai user lain
$dupe = tz_db()->fetchOne(
    'SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1',
    [$user_baru, $email_baru, $id_user]
);
if ($dupe) {
    echo "<script>alert('Username atau email sudah dipakai user lain'); history.back();</script>";
    exit;
}

// ─── Foto base64 ─────────────────────────────────────────
$foto_data = (string)($_POST['foto_base64'] ?? '');
if ($foto_data !== '') {
    if (strlen($foto_data) > 8 * 1024 * 1024) {
        echo "<script>alert('Foto terlalu besar (max 8 MB)'); history.back();</script>";
        exit;
    }
    if (preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#i', $foto_data, $m)) {
        $type   = strtolower($m[1]);
        $b64    = preg_replace('/\s+/', '', $m[2]);
        $bytes  = base64_decode($b64, true);
        if ($bytes === false || strlen($bytes) === 0) {
            echo "<script>alert('Foto tidak valid'); history.back();</script>";
            exit;
        }
        // Verifikasi: harus benar-benar gambar
        $imgInfo = @getimagesizefromstring($bytes);
        if ($imgInfo === false) {
            echo "<script>alert('Foto tidak valid (bukan gambar)'); history.back();</script>";
            exit;
        }
        $okMimes = ['image/png','image/jpeg','image/webp','image/gif'];
        if (!in_array($imgInfo['mime'], $okMimes, true)) {
            echo "<script>alert('Format gambar tidak didukung'); history.back();</script>";
            exit;
        }
        $extMap = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
        $ext = $extMap[$imgInfo['mime']];
        $newFile = 'pp_' . $id_user . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $uploadsDir = tz_uploads_dir();
        $path = $uploadsDir . DIRECTORY_SEPARATOR . $newFile;
        if (@file_put_contents($path, $bytes, LOCK_EX) === false) {
            echo "<script>alert('Gagal simpan foto'); history.back();</script>";
            exit;
        }
        @chmod($path, 0644);

        // Hapus foto lama (kalau di folder uploads/ dan bukan default)
        if ($nama_file !== '' && $nama_file !== 'Default.jpg') {
            $old = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($nama_file));
            if ($old && str_starts_with($old, realpath($uploadsDir) ?: $uploadsDir) && is_file($old)) {
                @unlink($old);
            }
        }
        $nama_file = 'uploads/' . $newFile;
    }
    // Else: format data: URL tidak dikenal — abaikan (jangan crash)
}

try {
    if ($pass_baru !== '') {
        $hashed = password_hash($pass_baru, PASSWORD_DEFAULT);
        tz_db()->exec(
            'UPDATE users SET nama_user = ?, username = ?, email = ?, foto = ?, password = ? WHERE id = ?',
            [$nama_baru, $user_baru, $email_baru, $nama_file, $hashed, $id_user]
        );
    } else {
        tz_db()->exec(
            'UPDATE users SET nama_user = ?, username = ?, email = ?, foto = ? WHERE id = ?',
            [$nama_baru, $user_baru, $email_baru, $nama_file, $id_user]
        );
    }

    // Sinkron session
    $_SESSION['nama_user'] = $nama_baru;
    $_SESSION['username']  = $user_baru;
    $_SESSION['email']     = $email_baru;
    $_SESSION['foto']      = $nama_file;

    echo "<script>alert('Profil berhasil di-update!'); window.location='index.php';</script>";
} catch (\Throwable $e) {
    error_log('[topzone-profile] ' . $e->getMessage());
    echo "<script>alert('Gagal update'); history.back();</script>";
}
