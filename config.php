<?php
// -----------------------------
// Database Configuration
// -----------------------------
$host = 'shortline.proxy.rlwy.net';
$port = 31315;
$db   = 'railway';
$user = 'root';
$pass = 'rVkBsGReslMeafTlzATAlrIvbCPWSbaY';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// -----------------------------
// SESSION CONFIGURATION FOR RAILWAY
// -----------------------------

// Force session to write in a writable temp folder
session_save_path(sys_get_temp_dir());

session_name("n8n_session");
$hostDomain = $_SERVER['HTTP_HOST'];

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $hostDomain,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
