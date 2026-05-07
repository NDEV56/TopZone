<?php
session_start();
session_unset();
session_destroy();

session_start();
$guestName = "Guest_" . rand(100, 999);
$_SESSION['nama_user'] = $guestName;
$_SESSION['foto'] = "Default.jpeg";
unset($_SESSION['user_id']);

// ── LOG: Guest login ──
tz_log('common', 'GUEST_LOGIN', "Sesi guest dimulai sebagai '{$guestName}'", [
    'guest_name' => $guestName,
]);

header("Location: ../Home/index.php");
exit();
?>
