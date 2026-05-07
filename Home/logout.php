<?php
session_start();

// ── LOG: Logout ──
$loggedUser = $_SESSION['username'] ?? ($_SESSION['nama_user'] ?? 'unknown');
$idUser     = $_SESSION['id_user']  ?? 0;
tz_log('common', 'LOGOUT', "User '{$loggedUser}' logout", [
    'id_user'  => $idUser,
    'username' => $loggedUser,
]);

session_unset();
session_destroy();
header("Location: ../login/tampilanlogin.php");
exit();
?>
