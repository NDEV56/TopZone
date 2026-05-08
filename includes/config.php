<?php
/**
 * ════════════════════════════════════════════════════════════════════
 *  TOPZONE — Configuration Loader
 * ════════════════════════════════════════════════════════════════════
 *  Memuat konfigurasi dari .env, set error-reporting, timezone, dll.
 *  Include file ini di awal setiap entry-point PHP.
 * ════════════════════════════════════════════════════════════════════
 */

if (!defined('TOPZONE_INIT')) {
    define('TOPZONE_INIT', true);

    // ─── Load .env ──────────────────────────────────────────────────
    $envPath = realpath(__DIR__ . '/../.env');
    if ($envPath && file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Hapus quote kalau ada
            $value = preg_replace('/^[\'"](.*)[\'"]$/', '$1', $value);

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    // ─── Helper: ambil env dengan default ───────────────────────────
    if (!function_exists('env')) {
        function env($key, $default = null) {
            $val = $_ENV[$key] ?? getenv($key);
            if ($val === false || $val === null || $val === '') return $default;

            // Konversi tipe umum
            switch (strtolower($val)) {
                case 'true':  return true;
                case 'false': return false;
                case 'null':  return null;
            }
            return $val;
        }
    }

    // ─── Set timezone & error reporting ─────────────────────────────
    date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));

    if (env('APP_DEBUG', false) === true || env('APP_ENV', 'production') === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    // ─── Konstanta app ──────────────────────────────────────────────
    define('APP_NAME',   'TopZone');
    define('APP_ENV',    env('APP_ENV', 'development'));
    define('APP_DEBUG',  env('APP_DEBUG', false) === true);
    define('APP_SECRET', env('APP_SECRET', 'change_me_in_production'));
    define('BASE_URL',   rtrim(env('BASE_URL', 'http://localhost'), '/'));
}
