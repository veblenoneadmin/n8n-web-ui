<?php
$host = 'shortline.proxy.rlwy.net';           // Railway MySQL host
$port = 31315;                                // Railway MySQL port
$db   = 'railway';                            // Railway database name
$user = 'root';                               // Railway username
$pass = 'rVkBsGReslMeafTlzATAlrIvbCPWSbaY';  // Railway password
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
?>
