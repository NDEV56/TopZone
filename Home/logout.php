<?php
session_start();
session_unset();
session_destroy();
header("Location: ../login/tampilanlogin.php"); // Arahin balik ke login
exit();
?>