<?php
/**
 * _security.php — Helper keamanan terpusat untuk semua file PHP TopZone.
 * ─────────────────────────────────────────────────────────────────────
 *
 * Tujuan: tidak ada lagi `mysqli_query` dengan string concat di seluruh
 * codebase. Semua akses DB lewat helper di sini (PDO + prepared).
 *
 * Cara pakai:
 *
 *   require_once __DIR__ . '/_security.php';
 *   tz_security_init();           // wajib di top setiap script
 *   tz_require_login();           // user biasa
 *   tz_require_admin();           // admin only
 *
 *   $row  = tz_db()->fetchOne('SELECT * FROM users WHERE id=?', [$id]);
 *   $rows = tz_db()->fetchAll('SELECT * FROM games WHERE kategori=?', [$kat]);
 *   tz_db()->exec('UPDATE orders SET status=? WHERE id_order=?', [$st, $id]);
 *
 *   echo tz_e($user_input);       // escape HTML
 *   tz_csrf_field();               // <input hidden> token
 *   tz_csrf_verify();              // di handler POST
 *
 *   tz_validate_upload($_FILES['gambar'], ['png','jpg','jpeg','webp']);
 *
 * Backward compat:
 *   $koneksi dan $conn (mysqli) tetap tersedia agar query lama tidak putus
 *   selagi dimigrasikan satu per satu. PRIORITAS: pindahkan semua ke tz_db().
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────
//  ENV LOADER (idempotent)
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_load_env')) {
    function tz_load_env(?string $path = null): array {
        static $loaded = false; static $values = [];
        if ($loaded && $path === null) return $values;
        $path = $path ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) { $loaded = true; return $values; }
        $size = @filesize($path);
        if ($size !== false && $size > 1024 * 1024) {
            $loaded = true; return $values;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $k = trim(substr($line, 0, $eq));
            // Validasi key — anti proto-pollution-style + invalid PHP env
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', $k)) continue;
            $v = trim(substr($line, $eq + 1));
            if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
             || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            if (strlen($v) > 8192) $v = substr($v, 0, 8192);
            $values[$k] = $v;
            if (getenv($k) === false) {
                @putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
        $loaded = true; return $values;
    }
}

// ─────────────────────────────────────────────────────────────────────
//  SECURE SESSION INIT + SECURITY HEADERS
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_security_init')) {
    function tz_security_init(): void {
        // 1. Headers
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: interest-cohort=(), camera=(), microphone=()');
            header_remove('X-Powered-By'); // jangan beri tahu PHP version

            // CSP — longgar untuk halaman yang pakai inline-style+script existing
            header(
                "Content-Security-Policy: default-src 'self'; " .
                "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
                "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
                "img-src 'self' data: blob: https:; " .
                "connect-src 'self'; " .
                "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
            );
        }

        // 2. Session settings sebelum start
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode',  '1');
            ini_set('session.cookie_httponly',  '1');
            ini_set('session.cookie_samesite',  'Lax');
            // Secure flag kalau HTTPS
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                  || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            ini_set('session.cookie_secure', $https ? '1' : '0');
            session_start();
        }

        // 3. Session hijack guard — bind ke UA fingerprint
        $ua_hash = isset($_SERVER['HTTP_USER_AGENT'])
                 ? hash('sha256', $_SERVER['HTTP_USER_AGENT']) : '';
        if (isset($_SESSION['_ua'])) {
            if (!hash_equals($_SESSION['_ua'], $ua_hash)) {
                // UA berubah drastis → bisa stolen cookie
                session_unset();
                session_destroy();
                session_start();
            }
        } else {
            $_SESSION['_ua'] = $ua_hash;
        }

        // 4. Session age — regenerate setiap 30 menit
        $now = time();
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
        } elseif ($now - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = $now;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
//  DATABASE — PDO singleton dengan prepared statements
// ─────────────────────────────────────────────────────────────────────
if (!class_exists('TZ_Database')) {
    final class TZ_Database {
        private \PDO $pdo;

        public function __construct(string $host, string $user, string $pass, string $name) {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $this->pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false, // real prepared
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES'",
            ]);
        }

        public function pdo(): \PDO { return $this->pdo; }

        public function fetchOne(string $sql, array $params = []): ?array {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        }

        public function fetchAll(string $sql, array $params = []): array {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }

        public function fetchColumn(string $sql, array $params = []) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        }

        public function exec(string $sql, array $params = []): int {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }

        public function lastInsertId(): string { return $this->pdo->lastInsertId(); }

        public function transaction(callable $fn) {
            $this->pdo->beginTransaction();
            try {
                $r = $fn($this);
                $this->pdo->commit();
                return $r;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }
}

if (!function_exists('tz_db')) {
    function tz_db(): TZ_Database {
        static $instance = null;
        if ($instance !== null) return $instance;

        // Konfigurasi default (dari koneksi.php) — bisa override via env
        $env  = tz_load_env();
        $host = $env['DB_HOST'] ?? 'localhost';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';
        $name = $env['DB_NAME'] ?? 'topzone';
        try {
            $instance = new TZ_Database($host, $user, $pass, $name);
        } catch (\PDOException $e) {
            // Tidak bocor detail
            error_log('[topzone-db] ' . $e->getMessage());
            http_response_code(500);
            die('Database tidak tersedia. Coba beberapa saat lagi.');
        }
        return $instance;
    }
}

// ─────────────────────────────────────────────────────────────────────
//  AUTH GUARDS
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_is_logged_in')) {
    function tz_is_logged_in(): bool {
        return isset($_SESSION['id_user']) && (int)$_SESSION['id_user'] > 0;
    }
}

if (!function_exists('tz_is_admin')) {
    function tz_is_admin(): bool {
        if (!tz_is_logged_in()) return false;
        $env = tz_load_env();

        // Daftar admin via .env: ADMIN_USERNAMES=alice,bob  ADMIN_IDS=1,2
        $names = array_filter(array_map('trim',
            explode(',', $env['ADMIN_USERNAMES'] ?? '')));
        $ids   = array_filter(array_map('intval',
            explode(',', $env['ADMIN_IDS'] ?? '')));

        $uname = $_SESSION['username'] ?? '';
        $uid   = (int)($_SESSION['id_user'] ?? 0);

        if (!empty($names) && in_array($uname, $names, true)) return true;
        if (!empty($ids)   && in_array($uid,   $ids,   true)) return true;
        return false;
    }
}

if (!function_exists('tz_require_login')) {
    function tz_require_login(string $redirect = '/Login/tampilanlogin.php'): void {
        if (!tz_is_logged_in()) {
            if (PHP_SAPI === 'cli') return;
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('tz_require_admin')) {
    function tz_require_admin(): void {
        if (!tz_is_admin()) {
            $env = tz_load_env();
            $configured = !empty($env['ADMIN_USERNAMES'] ?? '')
                       || !empty($env['ADMIN_IDS'] ?? '');
            http_response_code(403);
            // Pesan ramah — kasih tau cara konfigurasi pertama kali
            ?>
            <!DOCTYPE html>
            <html lang="id"><head><meta charset="UTF-8"><title>Akses Ditolak</title>
            <style>body{font-family:system-ui,sans-serif;background:#0f1117;color:#e2e8f0;
                margin:0;display:flex;align-items:center;justify-content:center;height:100vh}
                .b{max-width:560px;background:#181b24;padding:32px;border-radius:14px;
                border:1px solid #2a2f3d;box-shadow:0 10px 30px rgba(0,0,0,.4)}
                h1{margin:0 0 8px;font-size:1.5rem;color:#f87171}
                p{color:#a4abc0;line-height:1.6}
                code{background:#0f1117;padding:2px 8px;border-radius:4px;color:#22c55e}
                a{color:#818cf8}</style></head><body><div class="b">
                <h1>🚫 Halaman ini hanya untuk admin</h1>
                <?php if (!$configured): ?>
                <p>Belum ada admin yang dikonfigurasi. Untuk menetapkan admin pertama,
                tambahkan baris berikut ke file <code>.env</code> di root TopZone:</p>
                <pre style="background:#0f1117;padding:12px;border-radius:6px;color:#22c55e">ADMIN_USERNAMES=username_admin_kamu
# atau pakai ID:
ADMIN_IDS=1</pre>
                <p>Lalu restart TopZone (atau refresh halaman ini).</p>
                <?php else: ?>
                <p>Akun yang sedang login tidak punya hak admin.
                <a href="../Home/index.php">Kembali ke beranda</a>.</p>
                <?php endif; ?>
            </div></body></html>
            <?php
            exit;
        }
    }
}

if (!function_exists('tz_current_user')) {
    function tz_current_user(): ?array {
        if (!tz_is_logged_in()) return null;
        return tz_db()->fetchOne('SELECT id, username, nama_user, email, foto FROM users WHERE id = ?',
            [(int)$_SESSION['id_user']]);
    }
}

// ─────────────────────────────────────────────────────────────────────
//  CSRF
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_csrf_token')) {
    function tz_csrf_token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('tz_csrf_field')) {
    function tz_csrf_field(): string {
        $t = tz_csrf_token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('tz_csrf_verify')) {
    /**
     * Lempar 403 kalau token tidak valid.
     * Untuk endpoint AJAX, accept dari header X-CSRF-Token juga.
     */
    function tz_csrf_verify(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
         && $_SERVER['REQUEST_METHOD'] !== 'PUT'
         && $_SERVER['REQUEST_METHOD'] !== 'DELETE') return;
        $sent = $_POST['_csrf']
              ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($sent) || $sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
            http_response_code(403);
            die('CSRF token tidak valid. Refresh halaman dan coba lagi.');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
//  HTML / OUTPUT ESCAPE
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_e')) {
    function tz_e($v): string {
        if ($v === null || $v === false) return '';
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('tz_attr')) {
    /** Escape untuk dipakai di nilai atribut HTML. */
    function tz_attr($v): string {
        if ($v === null || $v === false) return '';
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
if (!function_exists('tz_url')) {
    /** Escape untuk dipakai di URL (?param=). */
    function tz_url($v): string { return rawurlencode((string)$v); }
}
if (!function_exists('tz_js')) {
    /** Escape untuk dipakai di literal JS string. */
    function tz_js($v): string {
        return json_encode((string)$v,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}

// ─────────────────────────────────────────────────────────────────────
//  RATE LIMIT (file-based, sederhana — untuk login)
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_rate_limit')) {
    /**
     * @return bool true kalau OK; false kalau over-limit.
     * @param  string $key  pembeda bucket (mis: "login:" . $ip)
     * @param  int    $max  jumlah request maks
     * @param  int    $win  window dalam detik
     */
    function tz_rate_limit(string $key, int $max = 8, int $win = 60): bool {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'topzone_rl';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $file = $dir . DIRECTORY_SEPARATOR . sha1($key) . '.json';
        $now  = time();
        $data = ['list' => []];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw) { $j = @json_decode($raw, true); if (is_array($j)) $data = $j; }
        }
        $list = is_array($data['list'] ?? null) ? $data['list'] : [];
        // Buang yang lebih lama dari window
        $cutoff = $now - $win;
        $list = array_values(array_filter($list, fn($t) => is_int($t) && $t >= $cutoff));
        $list[] = $now;
        @file_put_contents($file, json_encode(['list' => $list]), LOCK_EX);
        return count($list) <= $max;
    }
}

if (!function_exists('tz_client_ip')) {
    function tz_client_ip(): string {
        // Hanya percaya socket address — XFF spoofable kecuali ada proxy terverifikasi
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// ─────────────────────────────────────────────────────────────────────
//  FILE UPLOAD VALIDATOR
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_validate_upload')) {
    /**
     * Validasi upload file gambar.
     * @param array $file       $_FILES['xxx']
     * @param array $allowed    extension whitelist (lowercase, tanpa titik)
     * @param int   $maxBytes   ukuran maksimum
     * @return string|null      nama file aman (tanpa path) atau null + error
     */
    function tz_validate_upload(array $file, array $allowed = ['png','jpg','jpeg','webp','gif'], int $maxBytes = 5 * 1024 * 1024): ?string {
        if (empty($file) || !isset($file['error'])) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if (!is_uploaded_file($file['tmp_name'])) return null;
        if ($file['size'] <= 0 || $file['size'] > $maxBytes) return null;

        $orig = (string)($file['name'] ?? '');
        // Tolak null byte / path traversal di nama
        if (strpos($orig, "\0") !== false) return null;
        if (preg_match('/[\\/\\\\]/', $orig)) return null;

        // Ambil ekstensi terakhir (case-insensitive)
        $ext = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) return null;

        // Tolak DOUBLE EXTENSION yang berbahaya: foo.php.png
        $bareName = pathinfo($orig, PATHINFO_FILENAME);
        if (preg_match('/\.(?:php\d?|phtml|phar|html?|js|sh|cgi|pl|asp|aspx|jsp|exe|bat|cmd)$/i', $bareName)) {
            return null;
        }

        // Verifikasi MIME via getimagesize (untuk gambar) — pasti gambar valid
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) return null;
        $mime = $info['mime'] ?? '';
        $okMimes = [
            'image/png'  => ['png'],
            'image/jpeg' => ['jpg','jpeg'],
            'image/webp' => ['webp'],
            'image/gif'  => ['gif'],
        ];
        if (!isset($okMimes[$mime])) return null;
        if (!in_array($ext, $okMimes[$mime], true)) return null;

        // Generate nama acak
        $safe = bin2hex(random_bytes(8)) . '.' . $ext;
        return $safe;
    }
}

if (!function_exists('tz_uploads_dir')) {
    function tz_uploads_dir(): string {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        // Pastikan ada .htaccess yang blok eksekusi PHP
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht,
                "# Auto-generated by _security.php — JANGAN HAPUS\n" .
                "<FilesMatch \"\\.(?i:ph(?:p\\d?|tml|ar)|html?|js|sh|cgi|pl|asp|aspx|jsp|exe|bat|cmd)$\">\n" .
                "  Require all denied\n" .
                "</FilesMatch>\n" .
                "Options -ExecCGI -Indexes\n" .
                "AddType application/octet-stream .ph .ph3 .ph4 .ph5\n" .
                "php_flag engine off\n"
            );
        }
        return $dir;
    }
}

// ─────────────────────────────────────────────────────────────────────
//  SAFE REDIRECT
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_safe_redirect')) {
    function tz_safe_redirect(string $path): void {
        // Hanya boleh path relatif (tidak ke domain lain)
        $path = trim($path);
        if ($path === '' || preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            $path = '/Home/index.php';
        }
        header('Location: ' . $path);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────
//  WEBHOOK SIGNATURE / TOKEN VERIFICATION (untuk callback)
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_verify_webhook')) {
    /**
     * Verifikasi webhook Xendit/payment gateway via callback token.
     * Xendit kirim header X-CALLBACK-TOKEN — bandingkan dengan env XENDIT_CALLBACK_TOKEN.
     */
    function tz_verify_webhook(string $envKey = 'XENDIT_CALLBACK_TOKEN', string $headerName = 'HTTP_X_CALLBACK_TOKEN'): bool {
        $env = tz_load_env();
        $expected = $env[$envKey] ?? '';
        if ($expected === '') {
            // Belum dikonfigurasi → tolak semua webhook
            error_log("[topzone-webhook] $envKey belum diisi di .env, webhook ditolak.");
            return false;
        }
        $sent = $_SERVER[$headerName] ?? '';
        if (!is_string($sent) || $sent === '') return false;
        return hash_equals($expected, $sent);
    }
}

// ─────────────────────────────────────────────────────────────────────
//  BACKWARD COMPAT — sediakan $koneksi & $conn (mysqli) yang masih dipakai
//  beberapa file lama. Ini agar migrasi bisa bertahap, bukan big-bang.
// ─────────────────────────────────────────────────────────────────────
if (!function_exists('tz_legacy_mysqli')) {
    function tz_legacy_mysqli(): \mysqli {
        static $m = null;
        if ($m !== null) return $m;
        $env  = tz_load_env();
        $host = $env['DB_HOST'] ?? 'localhost';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';
        $name = $env['DB_NAME'] ?? 'topzone';
        $m = @new \mysqli($host, $user, $pass, $name);
        if ($m->connect_errno) {
            error_log('[topzone-mysqli] ' . $m->connect_error);
            http_response_code(500);
            die('Database tidak tersedia.');
        }
        $m->set_charset('utf8mb4');
        return $m;
    }
}
