<?php
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

// Session settings for Railway
session_name("n8n_session");
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.railway.app',  // ensures cookie works for Railway domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
