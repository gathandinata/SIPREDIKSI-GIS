<?php
session_start();

if (isset($_SESSION["id_admin"])) {
    header("Location: dashboard/index.php");
    exit;
}

header("Location: login/index.php");
exit;
?>