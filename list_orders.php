<?php
require 'config.php';
$pdo = getPDO();
$stmt = $pdo->query('SELECT o.*,i.invoice_number FROM orders o LEFT JOIN invoices i ON i.order_id=o.id ORDER BY o.created_at DESC LIMIT 50');
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
