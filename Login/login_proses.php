<?php
/**
 * login_proses.php — HARDENED v3.1
 * ─────────────────────────────────
 * Dipatch terhadap:
 *   • SQL injection (SELECT * dengan string concat) → prepared
 *   • Brute force                                   → rate-limit + delay
 *   • User enumeration (pesan beda)                 → pesan generik
 *   • Session fixation                              → session_regenerate_id
 *   • Missing CSRF                                  → cek _csrf
 *   • Mysqli error leak                             → log only
 */

require_once __DIR__ . '/../Home/_security.php';
tz_security_init();

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tz_safe_redirect('/Login/tampilanlogin.php');
}

// CSRF (tampilanlogin.php sekarang menyertakan token)
tz_csrf_verify();

if (!isset($_POST['btn_login'])) {
    tz_safe_redirect('/Login/tampilanlogin.php');
}

$ip = tz_client_ip();

// Rate limit per-IP (8 percobaan / 60 detik)
if (!tz_rate_limit('login:' . $ip, 8, 60)) {
    http_response_code(429);
    error_log("[topzone-login] rate-limit ip=$ip");
    echo "<script>alert('Terlalu banyak percobaan login. Coba lagi sebentar lagi.'); window.location='tampilanlogin.php';</script>";
    exit;
}

// Tambahan delay untuk hambat brute force
usleep(random_int(150000, 350000)); // 150-350 ms

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

// Validasi minimal — hindari query DB sia-sia
if ($username === '' || $password === '' || strlen($username) > 64 || strlen($password) > 1024) {
    echo "<script>alert('Username atau password salah.'); window.location='tampilanlogin.php';</script>";
    exit;
}

// Prepared statement (anti SQL injection)
$user = tz_db()->fetchOne(
    'SELECT id, username, nama_user, email, foto, password
     FROM users WHERE username = ? LIMIT 1',
    [$username]
);

// Pesan generik — jangan beri tahu attacker apakah username valid atau tidak
$generic_fail = function (): void {
    echo "<script>alert('Username atau password salah.'); window.location='tampilanlogin.php';</script>";
    exit;
};

if (!$user) {
    // Tetap lakukan password_verify dummy supaya timing serupa
    password_verify($password, '$2y$10$dummyhashtoavoidtimingxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
    error_log("[topzone-login] no-such-user ip=$ip user=" . substr($username, 0, 32));
    $generic_fail();
}

if (!password_verify($password, $user['password'])) {
    error_log("[topzone-login] wrong-pass ip=$ip user=" . substr($username, 0, 32));
    $generic_fail();
}

// SUKSES — regenerate session ID untuk cegah fixation
session_regenerate_id(true);

$_SESSION['id_user']   = (int)$user['id'];
$_SESSION['username']  = (string)$user['username'];
$_SESSION['nama_user'] = (string)$user['nama_user'];
$_SESSION['email']     = (string)$user['email'];
$_SESSION['foto']      = (string)$user['foto'];
$_SESSION['_created']  = time();

// Re-issue CSRF token setelah login
unset($_SESSION['_csrf']);

tz_safe_redirect('/Home/index.php');
