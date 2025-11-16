<?php
require "config.php";

$name = "Admin User";
$email = "admin@example.com";
$password = "admin123"; // login password
$role = "admin";

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$name, $email, $hash, $role]);

echo "Admin created successfully!";
