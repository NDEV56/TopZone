<?php
/**
 * TopZone Logger — Core PHP Logger
 * Auto-prepended ke setiap request PHP via .user.ini
 *
 * LEVEL:
 *   common   → request normal, login sukses, dll
 *   uncommon → 404, search kosong, akses guest ke area login
 *   warning  → login gagal, duplikat username, data missing
 *   critical → payment masuk, order baru, admin action
 *   error    → DB error, PHP fatal, API gagal
 */

define('TZ_LOG_DIR',  __DIR__ . '/../logs');
define('TZ_LOG_FILE', TZ_LOG_DIR . '/topzone.log');
define('TZ_LOG_START', microtime(true));

// Buat folder logs kalau belum ada
if (!is_dir(TZ_LOG_DIR)) {
    mkdir(TZ_LOG_DIR, 0775, true);
}

// ──────────────────────────────────────────────
//  FUNGSI UTAMA: tz_log()
// ──────────────────────────────────────────────
function tz_log(string $level, string $event, string $msg, array $data = []): void {
    $validLevels = ['common', 'uncommon', 'warning', 'critical', 'error'];
    if (!in_array($level, $validLevels)) $level = 'common';

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
    // Ambil IP pertama kalau ada multiple (ngrok forwarding)
    $ip = explode(',', $ip)[0];

    $entry = [
        'ts'      => date('Y-m-d\TH:i:s') . '.' . sprintf('%03d', (int)((microtime(true) - floor(microtime(true))) * 1000)),
        'level'   => $level,
        'event'   => $event,
        'msg'     => $msg,
        'ip'      => trim($ip),
        'user'    => $_SESSION['username'] ?? ($_SESSION['nama_user'] ?? 'guest'),
        'uri'     => $_SERVER['REQUEST_URI'] ?? '/',
        'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'data'    => $data,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents(TZ_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ──────────────────────────────────────────────
//  ERROR HANDLER — tangkap PHP error/warning
// ──────────────────────────────────────────────
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    $typeMap = [
        E_ERROR        => ['error',   'PHP_FATAL'],
        E_WARNING      => ['warning', 'PHP_WARNING'],
        E_NOTICE       => ['uncommon','PHP_NOTICE'],
        E_PARSE        => ['error',   'PHP_PARSE'],
        E_USER_ERROR   => ['error',   'PHP_USER_ERROR'],
        E_USER_WARNING => ['warning', 'PHP_USER_WARNING'],
        E_USER_NOTICE  => ['uncommon','PHP_USER_NOTICE'],
        E_DEPRECATED   => ['uncommon','PHP_DEPRECATED'],
    ];
    [$level, $event] = $typeMap[$errno] ?? ['uncommon', 'PHP_UNKNOWN'];
    tz_log($level, $event, $errstr, [
        'file' => str_replace(__DIR__, '', $errfile),
        'line' => $errline,
        'code' => $errno,
    ]);
    return false; // Tetap tampilkan error normal PHP
});

// Tangkap fatal error via shutdown
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        tz_log('error', 'PHP_FATAL_SHUTDOWN', $err['message'], [
            'file' => str_replace(__DIR__, '', $err['file']),
            'line' => $err['line'],
        ]);
    }
    // Log durasi request
    $duration = round((microtime(true) - TZ_LOG_START) * 1000);
    if ($duration > 2000) {
        tz_log('warning', 'SLOW_REQUEST', "Request lambat {$duration}ms", [
            'uri'      => $_SERVER['REQUEST_URI'] ?? '/',
            'duration' => $duration,
        ]);
    }
});

// ──────────────────────────────────────────────
//  AUTO LOG SETIAP REQUEST
// ──────────────────────────────────────────────
$_tz_uri    = $_SERVER['REQUEST_URI'] ?? '/';
$_tz_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_tz_file   = basename($_SERVER['SCRIPT_FILENAME'] ?? '');

// Skip log untuk asset (gambar, css, js, font)
$_tz_ext = strtolower(pathinfo($_tz_uri, PATHINFO_EXTENSION));
if (!in_array($_tz_ext, ['jpg','jpeg','png','gif','webp','ico','css','js','woff','woff2','ttf','svg','mp3','mp4'])) {
    tz_log('common', 'HTTP_REQUEST', "{$_tz_method} {$_tz_uri}", [
        'file'       => $_tz_file,
        'referer'    => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80),
    ]);
}
