<?php
/**
 * ════════════════════════════════════════════════════════════════════
 *  TOPZONE — Security Helper
 * ════════════════════════════════════════════════════════════════════
 *  Kumpulan fungsi keamanan reusable:
 *    - CSRF token  → csrf_token(), csrf_field(), csrf_check()
 *    - Sanitasi    → sanitize(), e()
 *    - Auth helper → require_login(), require_admin(), is_logged_in()
 *    - Rate limit  → rate_limit()
 * ════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie session yang lebih aman
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}


// ─── 🔐 CSRF PROTECTION ────────────────────────────────────────────

/**
 * Generate (atau ambil) CSRF token untuk session ini.
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Hidden input HTML siap pakai. Tinggal echo di dalam <form>.
 *
 *   <form method="POST">
 *     <?= csrf_field() ?>
 *     ...
 *   </form>
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Validasi CSRF token dari $_POST. Otomatis 403 kalau invalid.
 */
function csrf_check(bool $die_on_fail = true): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = !empty($_SESSION['_csrf_token']) &&
             hash_equals($_SESSION['_csrf_token'], $token);

    if (!$valid && $die_on_fail) {
        http_response_code(403);
        die('CSRF token tidak valid. Refresh halaman & coba lagi.');
    }
    return $valid;
}


// ─── 🧼 INPUT SANITIZATION ─────────────────────────────────────────

/**
 * Trim + strip tags + escape untuk display HTML.
 */
function sanitize($value): string {
    if (is_array($value)) return '';
    return trim(strip_tags((string) $value));
}

/**
 * Shortcut untuk htmlspecialchars (escape output HTML).
 *
 *   <?= e($user_input) ?>
 */
function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validasi & cast ke integer aman.
 */
function int_val($value, int $default = 0): int {
    return is_numeric($value) ? (int) $value : $default;
}


// ─── 👤 AUTH HELPERS ───────────────────────────────────────────────

function is_logged_in(): bool {
    return isset($_SESSION['id_user']) && (int) $_SESSION['id_user'] > 0;
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? 'user') === 'admin');
}

function current_user_id(): int {
    return (int) ($_SESSION['id_user'] ?? 0);
}

/**
 * Redirect ke login kalau belum login.
 */
function require_login(string $redirect = '/Login/tampilanlogin.php'): void {
    if (!is_logged_in()) {
        $base = rtrim(BASE_URL ?? '', '/');
        header('Location: ' . $base . $redirect);
        exit;
    }
}

/**
 * Cek user adalah admin. Kalau bukan, 403.
 */
function require_admin(): void {
    if (!is_admin()) {
        http_response_code(403);
        die('Akses ditolak. Halaman ini khusus admin.');
    }
}


// ─── ⏳ RATE LIMITING (sederhana, file-based) ──────────────────────

/**
 * Limit request per IP. Return false kalau sudah lewat batas.
 *
 * @param string $key   Identifier unik (mis: 'login', 'chat_send')
 * @param int    $max   Max request per window
 * @param int    $window Window dalam detik
 */
function rate_limit(string $key, int $max = 10, int $window = 60): bool {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/tz_rl_' . md5($key . '|' . $ip);

    $now  = time();
    $data = file_exists($file) ? json_decode(@file_get_contents($file), true) : null;

    if (!is_array($data) || ($now - ($data['start'] ?? 0)) > $window) {
        $data = ['start' => $now, 'count' => 0];
    }

    $data['count']++;

    @file_put_contents($file, json_encode($data), LOCK_EX);

    return $data['count'] <= $max;
}
