<?php
/**
 * ════════════════════════════════════════════════════════════════════
 *  TOPZONE — Admin Access Guard
 * ════════════════════════════════════════════════════════════════════
 *  Include file ini di awal SEMUA halaman admin.* untuk mencegah
 *  user biasa / guest mengakses panel admin.
 *
 *  Cara pakai (di admin.php / admin_orders.php / dll):
 *
 *    require_once __DIR__ . '/../includes/admin_guard.php';
 * ════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/security.php';

// Belum login → redirect ke login
if (!is_logged_in()) {
    header('Location: ../Login/tampilanlogin.php?redirect=admin');
    exit;
}

// Login tapi bukan admin → 403
if (!is_admin()) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>403 — Akses Ditolak</title>
        <style>
            body{font-family:sans-serif;background:#f4f7f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
            .box{background:#fff;padding:40px;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.1);text-align:center;max-width:420px}
            h1{color:#dc3545;margin:0 0 10px;font-size:48px}
            p{color:#666;line-height:1.6}
            a{display:inline-block;margin-top:20px;background:#007bff;color:#fff;padding:10px 25px;border-radius:8px;text-decoration:none}
        </style>
    </head>
    <body>
        <div class="box">
            <h1>403</h1>
            <h2>Akses Ditolak</h2>
            <p>Halaman ini khusus untuk admin. Akun kamu tidak punya akses.</p>
            <a href="../Home/index.php">⬅ Kembali ke Beranda</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
