<?php
require 'config.php';
require 'layout.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    if ($name && $price > 0) {
        $stmt = $pdo->prepare('INSERT INTO products (name, price) VALUES (?, ?)');
        $stmt->execute([$name, $price]);
        $message = '✅ Product added successfully!';
    } else {
        $message = '⚠️ Please enter valid name and price.';
    }
}

ob_start();
?>
<div class=\"max-w-lg mx-auto bg-white p-8 rounded-xl shadow\">
  <h2 class=\"text-2xl font-semibold text-gray-800 mb-4\">Add New Product</h2>
  <?php if ($message): ?>
  <div class=\"mb-4 text-green-600 font-medium\"> <?= htmlspecialchars($message) ?> </div>
  <?php endif; ?>
  <form method=\"POST\">
    <label class=\"block font-medium mb-2\">Product Name</label>
    <input type=\"text\" name=\"name\" class=\"border rounded w-full p-2 mb-4\" required>

    <label class=\"block font-medium mb-2\">Price (₱)</label>
    <input type=\"number\" name=\"price\" step=\"0.01\" class=\"border rounded w-full p-2 mb-4\" required>

    <button type=\"submit\" class=\"bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700\">Add Product</button>
  </form>
</div>
<?php
$content = ob_get_clean();
renderLayout('Add Product', $content);
?>
