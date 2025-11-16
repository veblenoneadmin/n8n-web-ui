<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require "auth.php";

// Get order_id from URL
$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    die('<h2>No order ID provided.</h2>');
}

// Fetch order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('<h2>Order not found.</h2>');
}

// Fetch order items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: get item name by type
function getItemName(PDO $pdo, $type, $id) {
    switch ($type) {
        case 'product':
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id=?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: 'Product';
        case 'personnel':
            $stmt = $pdo->prepare("SELECT name FROM personnel WHERE id=?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: 'Personnel';
        case 'installation':
            $stmt = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: 'Installation';
        default:
            return 'Unknown';
    }
}

ob_start();
?>

<div class="bg-white p-6 rounded-xl shadow">
  <h2 class="text-2xl font-semibold mb-4">Order Details</h2>
  <p><strong>Order No:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
  <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?> (<?= htmlspecialchars($order['customer_email']) ?>)</p>
  <p><strong>Total:</strong> ₱<?= number_format($order['total_amount'], 2) ?></p>

  <h3 class="mt-6 text-lg font-semibold mb-2">Items</h3>
  <table class="w-full border-collapse border">
    <thead class="bg-gray-100">
      <tr>
        <th class="border p-2">Type</th>
        <th class="border p-2">Name</th>
        <th class="border p-2">Qty</th>
        <th class="border p-2">Price</th>
        <th class="border p-2">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td class="border p-2"><?= ucfirst($it['item_type']) ?></td>
          <td class="border p-2"><?= htmlspecialchars(getItemName($pdo, $it['item_type'], $it['item_id'])) ?></td>
          <td class="border p-2"><?= htmlspecialchars($it['qty']) ?></td>
          <td class="border p-2">₱<?= number_format($it['price'], 2) ?></td>
          <td class="border p-2">₱<?= number_format($it['line_total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="mt-6">
    <a href="create_order.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create New Order</a>
  </div>
</div>

<?php
$content = ob_get_clean();
renderLayout('Order Details', $content);
?>
