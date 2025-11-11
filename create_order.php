<?php
// create_order.php — safe, defensive version
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

// Ensure $pdo is available
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<h2>Database connection error</h2><p>config.php must create a PDO instance named $pdo.</p>');
}

// Initialize variables
$message = '';
$products = [];
$personnel = [];

// Fetch products
try {
    $stmt = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC");
    $f = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($f)) $products = $f;
} catch (Exception $e) {
    $message = "❌ Failed to load products: " . $e->getMessage();
}

// Fetch personnel
try {
    $stmt = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC");
    $f = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($f)) $personnel = $f;
} catch (Exception $e) {
    $message = "❌ Failed to load personnel: " . $e->getMessage();
}

// Handle POST (create order)
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    function generateOrderNumber() {
        return 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
    }

    $customer_name = trim($_POST['customer_name'] ?? '');
    $quantities = $_POST['quantity'] ?? [];

    if ($customer_name === '') {
        $message = '⚠️ Please enter a customer name.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert order with zero total first
            $order_number = generateOrderNumber();
            $stmt = $pdo->prepare("INSERT INTO orders (order_number, customer_name, total_amount) VALUES (?, ?, 0)");
            $stmt->execute([$order_number, $customer_name]);
            $order_id = $pdo->lastInsertId();

            // Prepare order_items insert
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price, line_total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

            // Map products by ID for validation
            $products_by_id = [];
            foreach ($products as $p) {
                $products_by_id[$p['id']] = $p;
            }

            $total = 0.0;
            foreach ($quantities as $pid => $qtyRaw) {
                $pidInt = intval($pid);
                $qty = max(0, intval($qtyRaw));
                if ($qty > 0 && isset($products_by_id[$pidInt])) {
                    $price = floatval($products_by_id[$pidInt]['price']);
                    $subtotal = round($price * $qty, 2);
                    $total += $subtotal;
                    $itemStmt->execute([$order_id, $pidInt, $qty, $price, $subtotal]);
                }
            }

            // Update order total
            $upd = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $upd->execute([round($total, 2), $order_id]);

            $pdo->commit();

            // Redirect to avoid resubmission (PRG pattern)
            header("Location: create_order.php?success=1&total=" . urlencode(number_format($total, 2)));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Failed to create order: " . $e->getMessage();
        }
    }
}

// Handle success popup via GET
$showSuccessPopup = false;
$successAmount = '';
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['total'])) {
    $showSuccessPopup = true;
    $successAmount = $_GET['total'];
}

// Render page
ob_start();
?>

<form method="post" id="orderForm" class="space-y-6">

  <div class="flex gap-6">

    <!-- LEFT SIDE -->
    <div class="flex-1 flex flex-col gap-6">

     <div class="bg-white p-3 rounded-xl shadow">
  <div class="grid grid-cols-2 gap-4">
    
    <!-- Customer Name -->
    <div>
      <input 
        type="text" 
        name="customer_name" 
        placeholder="Enter customer name"
        class="border rounded w-full p-2" 
        required
      >
    </div>

    <!-- Customer Email -->
    <div>
      <input 
        type="email" 
        name="customer_email" 
        placeholder="email@example.com"
        class="border rounded w-full p-2" 
        required
      >
    </div>

  </div>
</div>



      <!-- Products -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Material</span>
          <input type="text" id="productSearch" placeholder="Search Product"
            class="border px-3 py-2 rounded-lg shadow-sm w-64 focus:outline-none focus:border-blue-500">
        </div>

        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="productsTable" class="w-full border-collapse">
            <thead class="bg-gray-100 text-left sticky top-0">
              <tr>
                <th class="p-2">Name</th>
                <th class="p-2">Price</th>
                <th class="p-2">Qty</th>
                <th class="p-2">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr class="border-b">
                  <td class="p-2 product-name"><?= htmlspecialchars($p['name']) ?></td>
                  <td class="p-2"><?= number_format($p['price'], 2) ?></td>
                  <td class="p-2">
                    <div class="flex items-center space-x-2">
                      <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn" data-id="<?= (int)$p['id'] ?>">-</button>
                      <input type="number" min="0" name="quantity[<?= (int)$p['id'] ?>]" value="0"
                             class="qty-input border rounded w-16 text-center" data-price="<?= htmlspecialchars($p['price']) ?>">
                      <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn" data-id="<?= (int)$p['id'] ?>">+</button>
                    </div>
                  </td>
                  <td class="p-2 subtotal">0.00</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Personnel -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Personnel</span>
          <input type="text" id="personnelSearch" placeholder="Search..."
            class="border px-3 py-2 rounded-lg shadow-sm w-64 focus:outline-none focus:border-blue-500">
        </div>

        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="personnelTable" class="w-full border-collapse">
            <thead class="bg-gray-100 text-left sticky top-0">
              <tr>
                <th class="p-2">Name</th>
                <th class="p-2">Rate</th>
                <th class="p-2">Select</th>
                <th class="p-2">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($personnel as $pers): ?>
                <tr class="border-b personnel-row">
                  <td class="p-2 pers-name"><?= htmlspecialchars($pers['name']) ?></td>
                  <td class="p-2"><?= number_format($pers['rate'], 2) ?></td>
                  <td class="p-2 text-center">
                    <input type="checkbox" class="pers-check" name="personnel_selected[]" 
                           value="<?= $pers['id'] ?>" data-price="<?= htmlspecialchars($pers['rate']) ?>">
                  </td>
                  <td class="p-2 pers-subtotal">0.00</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div> <!-- END LEFT SIDE -->

    <!-- RIGHT SIDE -->
    <div class="w-80 bg-white p-6 rounded-2xl shadow-lg border border-gray-100 flex flex-col">
      <h3 class="text-xl font-semibold mb-4">Qoutation Summary</h3>
      <div id="order-summary" class="flex-1 mb-4 overflow-auto" style="min-height: 200px;">
        <span style="color:#777;">No items selected.</span>
      </div>

      <hr class="mb-4">

      <p class="text-lg font-medium">
        Total: <span class="text-blue-700 font-bold text-2xl">₱<span id="total">0.00</span></span>
      </p>

      <button type="submit"
        class="mt-4 w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-lg">
        Save Order
      </button>
    </div>

  </div> <!-- END FLEX MAIN -->
</form>

<!-- JS -->
<script>
(function() {

  function updateTotal() {
    var total = 0;
    var summaryHTML = '';

    document.querySelectorAll('.qty-input').forEach(function(input){
      var price = parseFloat(input.dataset.price) || 0;
      var qty = parseInt(input.value) || 0;
      var subtotal = price * qty;
      var row = input.closest('tr');
      row.querySelector('.subtotal').textContent = subtotal.toFixed(2);
      var name = row.querySelector('.product-name')?.textContent || 'Item';
      if (qty > 0) summaryHTML += `<div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>${name} x ${qty}</span><span>₱${subtotal.toFixed(2)}</span></div>`;
      total += subtotal;
    });

    document.querySelectorAll('.pers-check').forEach(function(chk){
      var price = parseFloat(chk.dataset.price) || 0;
      var subtotal = chk.checked ? price : 0;
      var row = chk.closest('tr');
      row.querySelector('.pers-subtotal').textContent = subtotal.toFixed(2);
      if (chk.checked) {
        var name = row.querySelector('.pers-name')?.textContent || 'Personnel';
        summaryHTML += `<div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>${name}</span><span>₱${subtotal.toFixed(2)}</span></div>`;
      }
      total += subtotal;
    });

    document.getElementById('order-summary').innerHTML = summaryHTML || '<span style="color:#777;">No items selected.</span>';
    document.getElementById('total').textContent = total.toFixed(2);
  }

  document.addEventListener('click', function(e){
    if (e.target.classList.contains('plus-btn') || e.target.classList.contains('minus-btn')) {
      var input = e.target.parentElement.querySelector('.qty-input');
      var qty = parseInt(input.value) || 0;
      if (e.target.classList.contains('plus-btn')) qty++;
      if (e.target.classList.contains('minus-btn') && qty > 0) qty--;
      input.value = qty;
      updateTotal();
    }
  });

  document.addEventListener('change', function(e){
    if (e.target.classList.contains('pers-check')) updateTotal();
  });

  document.getElementById("productSearch").addEventListener("keyup", function(){
    var filter = this.value.toLowerCase();
    document.querySelectorAll("#productsTable tbody tr").forEach(function(row){
      row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
    });
  });

  document.getElementById('personnelSearch').addEventListener('input', function(){
    const search = this.value.toLowerCase();
    document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
      const name = row.querySelector('td:first-child').textContent.toLowerCase();
      row.style.display = name.includes(search) ? '' : 'none';
    });
  });

})();
</script>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
  <div class="bg-white p-6 rounded-xl shadow-lg text-center w-96">
    <h2 class="text-2xl font-semibold text-green-600 mb-3">Order Saved!</h2>
    <p class="text-lg text-gray-700 mb-4">Total: ₱<span id="successAmount"></span></p>
    <button id="closeModal" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
      Done
    </button>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  <?php if ($showSuccessPopup): ?>
    document.getElementById("successAmount").textContent = "<?= $successAmount ?>";
    document.getElementById("successModal").classList.remove("hidden");
    document.getElementById("successModal").classList.add("flex");
  <?php endif; ?>

  document.getElementById("closeModal").addEventListener("click", function(){
    document.getElementById("successModal").classList.add("hidden");
    document.getElementById("successModal").classList.remove("flex");
    document.getElementById("orderForm").reset();
    document.getElementById("total").textContent = "0.00";
    document.getElementById('order-summary').innerHTML = '<span style="color:#777;">No items selected.</span>';
  });
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
