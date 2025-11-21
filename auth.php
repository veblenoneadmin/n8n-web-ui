<?php
session_start();

$current = basename($_SERVER['PHP_SELF']);

// Pages that must NOT require login
$public_pages = ['login.php', 'check_session.php'];

if (!in_array($current, $public_pages)) {
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit();
    }
}
