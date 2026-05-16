<?php
/**
 * logout.php — HARDENED v3.1
 * Hancurkan sesi & cookie sebelum redirect.
 */
require_once __DIR__ . '/_security.php';
tz_security_init();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
tz_safe_redirect('/Login/tampilanlogin.php');
