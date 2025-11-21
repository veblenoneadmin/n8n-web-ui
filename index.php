<?php
session_start();

// Force a default user for testing
$_SESSION['user_id'] = 1;
$_SESSION['name'] = 'Admin';
$_SESSION['role'] = 'admin';

require_once "config.php";
require_once "layout.php";

// You can now skip the login check
// if (empty($_SESSION['user_id'])) { ... }

try {
    $totalOrdersStmt = $pdo->query("SELECT COUNT(*) AS total FROM orders");
    $totalOrders = $totalOrdersStmt->fetch()['total'] ?? 0;

    $totalClientsStmt = $pdo->query("SELECT COUNT(*) AS total FROM customers");
    $totalClients = $totalClientsStmt->fetch()['total'] ?? 0;

    $ductedStmt = $pdo->query("SELECT COUNT(*) AS total FROM ductedinstallations");
    $totalDucted = $ductedStmt->fetch()['total'] ?? 0;

    $splitStmt = $pdo->query("SELECT COUNT(*) AS total FROM split_installation");
    $totalSplit = $splitStmt->fetch()['total'] ?? 0;

    $totalInstallations = $totalDucted + $totalSplit;

    $pendingOrdersStmt = $pdo->query("SELECT COUNT(*) AS total FROM orders WHERE status='pending'");
    $pendingOrders = $pendingOrdersStmt->fetch()['total'] ?? 0;
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<style>
body { font-family:sans-serif; margin:0; padding:0; background:#f8f9fa; }
.container { max-width:900px; margin:40px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
h1 { margin-bottom:20px; }
.card { padding:15px; border-radius:8px; background:#eef2f7; margin-bottom:15px; }
</style>
</head>
<body>
<div class="container">
<h1>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></h1>
<div class="card">Total Orders: <?= $totalOrders ?></div>
<div class="card">Total Installations: <?= $totalInstallations ?></div>
<div class="card">Total Clients: <?= $totalClients ?></div>
<div class="card">Pending Orders: <?= $pendingOrders ?></div>
<a href="logout.php">Logout</a>
</div>
</body>
</html>
