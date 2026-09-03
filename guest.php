<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["id_admin"])) {
    header("Location: ../dashboard/index.php");
    exit;
}
?>