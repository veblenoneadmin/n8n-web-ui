<?php
$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0777, true);
session_save_path($sessionPath);
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
