<?php
$host = 'localhost';
$db   = 'multi_order_invoice';  // your DB name
$user = 'root';             // your MySQL username
$pass = '';                 // your MySQL password (leave blank for default XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
