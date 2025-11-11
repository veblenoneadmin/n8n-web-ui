<?php
require 'config.php';
$pdo = getPDO();

$invoice_id = $_GET['invoice_id'] ?? null;
$invoice_number = $_GET['invoice_number'] ?? null;

if (!$invoice_id && !$invoice_number) die('invoice_id or invoice_number required');

if ($invoice_id) {
    $stmt = $pdo->prepare('SELECT i.*,o.*,c.name as customer_name,c.email as customer_email,c.phone,c.address
        FROM invoices i JOIN orders o ON i.order_id=o.id JOIN customers c ON o.customer_id=c.id WHERE i.id=?');
    $stmt->execute([$invoice_id]);
} else {
    $stmt = $pdo->prepare('SELECT i.*,o.*,c.name as customer_name,c.email as customer_email,c.phone,c.address
        FROM invoices i JOIN orders o ON i.order_id=o.id JOIN customers c ON o.customer_id=c.id WHERE i.invoice_number=?');
    $stmt->execute([$invoice_number]);
}
$inv = $stmt->fetch();
if (!$inv) die('Invoice not found');

$items = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?');
$items->execute([$inv['order_id']]);
$rows = $items->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice <?=$inv['invoice_number']?></title>
<style>
body{font-family:Arial;max-width:800px;margin:auto;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{border:1px solid #ddd;padding:8px;text-align:left;}
tfoot td{font-weight:bold;}
.right{text-align:right;}
</style>
</head>
<body>
<h2>Invoice <?=$inv['invoice_number']?></h2>
<p><strong>Customer:</strong> <?=htmlspecialchars($inv['customer_name'])?><br>
<?=htmlspecialchars($inv['customer_email'])?><br>
<?=htmlspecialchars($inv['address'])?></p>

<table>
<thead><tr><th>#</th><th>Item</th><th>SKU</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr></thead>
<tbody>
<?php $i=1; foreach($rows as $r): ?>
<tr>
<td><?=$i++?></td>
<td><?=htmlspecialchars($r['name'])?></td>
<td><?=htmlspecialchars($r['sku'])?></td>
<td class="right"><?=$r['qty']?></td>
<td class="right"><?=number_format($r['price'],2)?></td>
<td class="right"><?=number_format($r['line_total'],2)?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr><td colspan="5" class="right">Tax</td><td class="right"><?=number_format($inv['tax'],2)?></td></tr>
<tr><td colspan="5" class="right">Discount</td><td class="right">-<?=number_format($inv['discount'],2)?></td></tr>
<tr><td colspan="5" class="right">Total</td><td class="right"><?=number_format($inv['total'],2)?></td></tr>
</tfoot>
</table>
<p>Thank you for your business!</p>
</body>
</html>
