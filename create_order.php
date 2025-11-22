<?php
ob_start(); // Start output buffering to allow header() redirect
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require "auth.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $generateOrderNumber = function() {
        return 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
    };

    $customer_name     = trim($_POST['customer_name'] ?? '');
    $customer_email    = trim($_POST['customer_email'] ?? '');
    $contact_number    = trim($_POST['contact_number'] ?? '');
    $appointment_date  = trim($_POST['appointment_date'] ?? '');
    $quantities        = $_POST['quantity'] ?? [];
    $split_quantities  = $_POST['split'] ?? [];
    $ducted_inputs     = $_POST['ducted'] ?? [];
    $personnel_inputs  = $_POST['personnel_selected'] ?? [];

    if ($customer_name === '') {
        $message = '⚠️ Please enter a customer name.';
    } else {
        try {
            $pdo->beginTransaction();

            // --- Build orders INSERT ---
            $cols = ['order_number', 'customer_name'];
            $placeholders = ['?', '?'];
            $values = [$generateOrderNumber(), $customer_name];

            if (column_exists($pdo, 'orders', 'customer_email')) {
                $cols[] = 'customer_email';
                $placeholders[] = '?';
                $values[] = $customer_email ?: null;
            }

            if (column_exists($pdo, 'orders', 'contact_number')) {
                $cols[] = 'contact_number';
                $placeholders[] = '?';
                $values[] = $contact_number ?: null;
            }

            if (column_exists($pdo, 'orders', 'appointment_date')) {
                $cols[] = 'appointment_date';
                $placeholders[] = '?';
                $values[] = $appointment_date ?: null;
            }

            // total_amount always present
            $cols[] = 'total_amount';
            $placeholders[] = '?';
            $values[] = 0;

            $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $order_id = $pdo->lastInsertId();

            // --- Prepare order_items INSERT ---
            $insertStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $total = 0.0;

            // Products
            foreach ($quantities as $pid => $qtyRaw) {
                $qty = max(0, intval($qtyRaw));
                if ($qty > 0) {
                    $prod = null;
                    foreach ($products as $p) if ($p['id'] == intval($pid)) { $prod = $p; break; }
                    if ($prod) {
                        $price = floatval($prod['price']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'product', intval($pid), null, $qty, $price]);
                        $total += $subtotal;
                    }
                }
            }

            // Split installations
            foreach ($split_quantities as $sid => $sdata) {
                $qty = max(0, intval($sdata['qty'] ?? 0));
                if ($qty > 0) {
                    $row = null;
                    foreach ($split_installations as $s) if ($s['id'] == intval($sid)) { $row = $s; break; }
                    if ($row) {
                        $price = floatval($row['unit_price']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'installation', intval($sid), null, $qty, $price]);
                        $total += $subtotal;
                    }
                }
            }

            // Personnel
            foreach ($personnel_inputs as $pid) {
                $pidInt = intval($pid);
                $pers = null;
                foreach ($personnel as $p) if ($p['id'] == $pidInt) { $pers = $p; break; }
                if ($pers) {
                    $price = floatval($pers['rate']);
                    $subtotal = round($price, 2);
                    $insertStmt->execute([$order_id, 'personnel', $pidInt, null, 1, $price]);
                    $total += $subtotal;

                    // Book personnel
                    if ($appointment_date && table_exists($pdo, 'personnel_bookings')) {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM personnel_bookings WHERE personnel_id=? AND booked_date=?");
                        $chk->execute([$pidInt, $appointment_date]);
                        if ((int)$chk->fetchColumn() === 0) {
                            $ins = $pdo->prepare("INSERT INTO personnel_bookings (personnel_id, booked_date) VALUES (?, ?)");
                            $ins->execute([$pidInt, $appointment_date]);
                        }
                    }
                }
            }

            // Ducted Installations
            foreach ($ducted_inputs as $did => $d) {
                $qty = max(0, intval($d['qty'] ?? 0));
                $type = $d['installation_type'] ?? '';
                if ($qty > 0 && $type !== '') {
                    $row = null;
                    foreach ($ducted_installations as $r) if ($r['id'] == intval($did)) { $row = $r; break; }
                    if ($row) {
                        $price = floatval($row['total_cost']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'installation', intval($did), $type, $qty, $price]);
                        $total += $subtotal;
                    }
                }
            }

            // Update orders total_amount
            $upd = $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?");
            $upd->execute([round($total,2), $order_id]);

            $pdo->commit();

           // Redirect to order details
$order_id = $pdo->lastInsertId(); // make sure $order_id is set
if (!headers_sent()) {
    header("Location: orders.php?order_id=" . urlencode($order_id));
    exit;
} else {
    echo "<script>window.location.href='orders.php?order_id=" . urlencode($order_id) . "';</script>";
    exit;
}

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Failed to create order: " . $e->getMessage();
        }
    }
}
?>


<?php if ($message): ?>
  <div class="mb-4 text-red-600"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" id="orderForm" class="space-y-6">
  <div class="flex gap-6">

    <!-- LEFT SIDE -->
    <div class="flex-1 flex flex-col gap-6">

      <!-- Customer Info (Name, Email, Contact, Date) -->
      <div class="bg-white p-3 rounded-xl shadow shadow border border-gray-200">
        <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
        <div class="grid grid-cols-2 gap-4">
          <input type="text" name="customer_name" placeholder="Name" class="border rounded w-full text-sm p-2" required>
          <input type="email" name="customer_email" placeholder="Email" class="border rounded w-full text-sm p-2">
          <input type="text" name="contact_number" placeholder="Phone Number" class="border rounded w-full text-sm p-2">
          <input type="date" name="appointment_date" id="appointment_date" value="<?= htmlspecialchars($selected_date ?? '') ?>" class="border rounded w-full p-2">
        </div>
      </div>

      <!-- Products -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Material</span>
          <input type="text" id="productSearch" placeholder="Search Product" class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="productsTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach($products as $p): ?>
              <tr class="border-b">
                <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
                <td class="p-2 text-center"><?= number_format($p['price'], 2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="quantity[<?= (int)$p['id'] ?>]" value="0" class="qty-input border rounded w-16 text-center" data-price="<?= htmlspecialchars($p['price']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Split System Installation -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Split System Installation</span>
          <input type="text" id="splitSearch" placeholder="Search Split" class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="splitTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Unit Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach($split_installations as $s): ?>
              <tr class="border-b">
                <td class="item-name p-2"><?= htmlspecialchars($s['item_name']) ?></td>
                <td class="p-2 text-center"><?= number_format($s['unit_price'], 2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="split[<?= (int)$s['id'] ?>][qty]" value="0" class="split-qty border rounded w-16 text-center" data-price="<?= htmlspecialchars($s['unit_price']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($split_installations)): ?>
              <tr><td colspan="4" class="p-4 text-center text-gray-500">No split items available.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Ducted Installations -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Ducted Installation</span>
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="ductedInstallationsTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr>
                <th class="p-2 text-left">Equipment</th>
                <th class="p-2 text-center">Type</th>
                <th class="p-2 text-center">Price</th>
                <th class="p-2 text-center">Qty</th>
                <th class="p-2 text-center">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($ducted_installations as $inst): ?>
              <tr class="border-b"
                  data-model-indoor="<?= htmlspecialchars($inst['model_name_indoor']) ?>"
                  data-model-outdoor="<?= htmlspecialchars($inst['model_name_outdoor']) ?>"
                  data-price="<?= htmlspecialchars($inst['total_cost']) ?>">
                <td class="p-2"><?= htmlspecialchars($inst['equipment_name']) ?></td>
                <td class="p-2 text-center">
                  <select name="ducted[<?= (int)$inst['id'] ?>][installation_type]" class="install-type border rounded p-1">
                    <option value="indoor">Indoor</option>
                    <option value="outdoor">Outdoor</option>
                  </select>
                </td>
                <td class="p-2 text-center"><?= number_format($inst['total_cost'],2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="ducted[<?= (int)$inst['id'] ?>][qty]" value="0" class="installation-qty border rounded w-16 text-center" data-price="<?= htmlspecialchars($inst['total_cost']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="installation-subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Personnel -->
<div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Personnel</span>
    <input type="text" id="personnelSearch" placeholder="Search..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
  </div>

  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table id="personnelTable" class="w-full border-collapse text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Hours</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach($personnel as $pers):
          $isBooked = in_array($pers['id'], $booked_personnel_ids);
        ?>
        <tr class="border-b <?= $isBooked ? 'bg-red-50 opacity-80' : '' ?>"
            data-personnel-id="<?= (int)$pers['id'] ?>"
            data-rate="<?= htmlspecialchars($pers['rate']) ?>">

          <td class="pers-name p-2"><?= htmlspecialchars($pers['name']) ?></td>
          <td class="p-2 text-center"><?= number_format($pers['rate'], 2) ?></td>

          <td class="p-2 text-center">
            <?php if(!$isBooked): ?>
            <div class="flex items-center justify-center gap-2">
              <button type="button" class="hour-minus bg-gray-200 px-2 rounded">–</button>
              <input type="number" name="personnel_hours[<?= (int)$pers['id'] ?>]"
                     class="hour-input w-12 text-center border rounded"
                     value="0" min="0">
              <button type="button" class="hour-plus bg-gray-200 px-2 rounded">+</button>
            </div>
            <?php else: ?>
              <span class="text-red-400 text-xs">Booked</span>
            <?php endif; ?>
          </td>

          <td class="pers-subtotal p-2 text-center">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Equipment -->
<div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Equipment</span>
    <input type="text" id="equipmentSearch" placeholder="Search..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
  </div>

  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table id="equipmentTable" class="w-full border-collapse text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Item</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Qty</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach($equipment as $equip): ?>
        <tr class="border-b"
            data-equip-id="<?= (int)$equip['id'] ?>"
            data-rate="<?= htmlspecialchars($equip['rate']) ?>">

          <td class="equip-name p-2"><?= htmlspecialchars($equip['item']) ?></td>
          <td class="p-2 text-center"><?= number_format($equip['rate'], 2) ?></td>

          <td class="p-2 text-center">
            <div class="flex items-center justify-center gap-2">
              <button type="button" class="equip-minus bg-gray-200 px-2 rounded">–</button>
              <input type="number"
                     name="equipment_qty[<?= (int)$equip['id'] ?>]"
                     class="equip-input w-12 text-center border rounded"
                     value="0" min="0">
              <button type="button" class="equip-plus bg-gray-200 px-2 rounded">+</button>
            </div>
          </td>

          <td class="equip-subtotal p-2 text-center">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- OTHER EXPENSES CARD -->
<div class="bg-white p-4 rounded-xl shadow flex flex-col mb-4">

    <span class="font-medium text-gray-700 mb-2">Other Expenses</span>

    <!-- Wrapper for dynamic inputs -->
    <div id="otherExpensesWrapper">
        <div id="otherExpensesContainer" class="space-y-2"></div>
    </div>

    <!-- Add More Button BELOW the inputs -->
    <div class="flex justify-end mt-3">
        <button type="button" id="addOtherExpenseBtn"
                class="flex items-center gap-2 px-3 py-2 rounded-full bg-blue-500 text-white shadow hover:bg-blue-600">
            <span class="material-icons text-sm">add</span>
            <span class="text-sm">Add more</span>
        </button>
    </div>

</div>

    </div> <!-- END LEFT SIDE -->

   <!-- RIGHT PANEL WRAPPER -->
<div class="w-80 flex flex-col gap-4">

    <!-- PROFIT CARD -->
    <div id="profitCard" class="bg-white p-4 rounded-xl shadow border border-gray-200">
        <h3 class="text-base font-semibold text-gray-700 mb-2">Profit Summary</h3>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Profit:</span>
            <span>$<span id="profitDisplay">0.00</span></span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Percent Margin:</span>
            <span><span id="profitMarginDisplay">0.00</span>%</span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Net Profit:</span>
            <span><span id="netProfitDisplay">0.00</span>%</span>
        </div>

        <div class="flex justify-between font-semibold text-gray-700">
            <span>Total Profit:</span>
            <span>$<span id="totalProfitDisplay">0.00</span></span>
        </div>
    </div>

    <!-- SUMMARY CARD -->
    <div id="rightPanel" class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
        
        <!-- ITEM LIST -->
        <div id="order-summary" class="flex-1 overflow-y-auto mb-4">
            <span style="color:#777;">No items selected.</span>
        </div>

        <!-- TOTALS -->
        <hr class="mb-3">

        <p class="text-base font-medium text-gray-600 flex justify-between mb-1">
            <span>Subtotal:</span>
            <span>$<span id="subtotalDisplay">0.00</span></span>
        </p>

        <p class="text-base font-medium text-gray-600 flex justify-between mb-1">
            <span>Tax:</span>
            <span>$<span id="taxAmount">0.00</span></span>
        </p>

        <p class="text-xl font-semibold flex justify-between text-blue-700 mb-4">
            <span>Grand Total:</span>
            <span>$<span id="grandTotal">0.00</span></span>
        </p>

        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-lg">
            Save Order
        </button>
    </div>

</div>



  </div>
</form>

<!-- JS -->
<script>
(function () {

  // -------------------------
  // UPDATE TOTALS FUNCTION
  // -------------------------
  function updateTotal() {
    let subtotal = 0;
    let summaryHTML = "";

    // ----- PRODUCTS -----
    document.querySelectorAll('.qty-input').forEach(input => {
      const qty = parseInt(input.value) || 0;
      const price = parseFloat(input.dataset.price) || 0;
      const row = input.closest('tr');
      const sub = price * qty;

      if (row.querySelector('.subtotal')) row.querySelector('.subtotal').textContent = sub.toFixed(2);

      if (qty > 0) {
        const name = row.querySelector('.product-name')?.textContent || 'Item';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // ----- SPLIT ITEMS -----
    document.querySelectorAll('.split-qty').forEach(input => {
      const qty = parseInt(input.value) || 0;
      const price = parseFloat(input.dataset.price) || 0;
      const row = input.closest('tr');
      const sub = price * qty;

      if (row.querySelector('.subtotal')) row.querySelector('.subtotal').textContent = sub.toFixed(2);

      if (qty > 0) {
        const name = row.querySelector('.item-name')?.textContent || 'Split Item';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // ----- PERSONNEL -----
    document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
      const input = row.querySelector('.hour-input');
      if (!input) return;

      const rate = parseFloat(row.dataset.rate) || 0;
      const hours = parseFloat(input.value) || 0;
      const persSubtotal = rate * hours;

      const subtotalCell = row.querySelector('.pers-subtotal');
      if (subtotalCell) subtotalCell.textContent = persSubtotal.toFixed(2);

      if (hours > 0) {
        const name = row.querySelector('.pers-name')?.textContent || 'Personnel';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} (${hours} hr)</span><span>$${persSubtotal.toFixed(2)}</span></div>`;
      }

      subtotal += persSubtotal;
    });

    // ----- DUCTED INSTALLATIONS -----
    document.querySelectorAll('.installation-qty').forEach(input => {
      const qty = parseInt(input.value) || 0;
      const row = input.closest('tr');
      const price = parseFloat(row.dataset.price) || 0;
      const sub = price * qty;

      if (row.querySelector('.installation-subtotal')) row.querySelector('.installation-subtotal').textContent = sub.toFixed(2);

      if (qty > 0) {
        const type = row.querySelector('.install-type')?.value || '';
        const model = type === 'indoor' ? row.dataset.modelIndoor : row.dataset.modelOutdoor;
        summaryHTML += `<div class="flex justify-between mb-1"><span>${model} (${type}) x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // ----- EQUIPMENT -----
    document.querySelectorAll('#equipmentTable tbody tr').forEach(row => {
      const input = row.querySelector('.equip-input');
      const qty = parseInt(input.value) || 0;
      const rate = parseFloat(row.dataset.rate) || 0;
      const sub = qty * rate;

      if (row.querySelector('.equip-subtotal')) row.querySelector('.equip-subtotal').textContent = sub.toFixed(2);

      if (qty > 0) {
        const name = row.querySelector('.equip-name')?.textContent || 'Equipment';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // ----- OTHER EXPENSES -----
    document.querySelectorAll('.other-expense-row').forEach(row => {
      const name = row.querySelector('.expense-name')?.value.trim() || '';
      const amt = parseFloat(row.querySelector('.expense-amount')?.value) || 0;
      if (name && amt > 0) {
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name}</span><span>$${amt.toFixed(2)}</span></div>`;
      }
      subtotal += amt;
    });

    // ----- SHOW SUMMARY -----
    document.getElementById('order-summary').innerHTML = summaryHTML || '<span style="color:#777;">No items selected.</span>';

    // ----- GST + TOTAL -----
    const gstRate = 0.10;
    const gstAmount = subtotal * gstRate;
    const grandTotal = subtotal + gstAmount;

    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = gstAmount.toFixed(2);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);

    // ----- PROFIT SUMMARY -----
    const profit = subtotal * 0.30;
    const netProfitPercent = subtotal > 0 ? ((profit - gstAmount) / subtotal) * 100 : 0;
    const profitMargin = subtotal > 0 ? (profit / subtotal) * 100 : 0;
    const totalProfit = profit;

    document.getElementById('profitDisplay').textContent = profit.toFixed(2);
    document.getElementById('netProfitDisplay').textContent = netProfitPercent.toFixed(2);
    document.getElementById('profitMarginDisplay').textContent = profitMargin.toFixed(2);
    document.getElementById('totalProfitDisplay').textContent = totalProfit.toFixed(2);
  }

  // -------------------------
  // EVENT DELEGATION FOR ALL BUTTONS
  // -------------------------
  document.addEventListener('click', function(e) {
    const target = e.target;

    // Products plus/minus
    if (target.matches('.plus-btn') || target.matches('.minus-btn')) {
      const row = target.closest('tr');
      const input = row.querySelector('.qty-input');
      if (!input) return;
      input.value = parseInt(input.value || 0) + (target.matches('.plus-btn') ? 1 : -1);
      input.value = Math.max(0, input.value);
      updateTotal();
    }

    // Personnel plus/minus
    if (target.matches('.hour-plus') || target.matches('.hour-minus')) {
      const row = target.closest('tr');
      const input = row.querySelector('.hour-input');
      if (!input) return;
      input.value = parseInt(input.value || 0) + (target.matches('.hour-plus') ? 1 : -1);
      input.value = Math.max(0, input.value);
      updateTotal();
    }

    // Equipment plus/minus
    if (target.matches('.equip-plus') || target.matches('.equip-minus')) {
      const row = target.closest('tr');
      const input = row.querySelector('.equip-input');
      if (!input) return;
      input.value = parseInt(input.value || 0) + (target.matches('.equip-plus') ? 1 : -1);
      input.value = Math.max(0, input.value);
      updateTotal();
    }

    // Remove Other Expense row
    if (target.closest('.remove-expense-btn')) {
      const row = target.closest('.other-expense-row');
      if (row) row.remove();
      updateTotal();
    }
  });

  // -------------------------
  // INPUT CHANGE HANDLERS
  // -------------------------
  document.addEventListener('input', function(e) {
    if (e.target.matches('.qty-input, .split-qty, .installation-qty, .hour-input, .equip-input, .expense-name, .expense-amount, .install-type')) {
      updateTotal();
    }
  });

  // -------------------------
  // ADD OTHER EXPENSE ROW
  // -------------------------
  const otherContainer = document.getElementById('otherExpensesContainer');
  const addOtherBtn = document.getElementById('addOtherExpenseBtn');
  if (otherContainer && addOtherBtn) {
    function addExpenseRow() {
      const row = document.createElement('div');
      row.classList.add('other-expense-row', 'flex', 'gap-2', 'items-center', 'mb-2');
      row.innerHTML = `
        <input type="text" class="expense-name border p-2 rounded flex-1" placeholder="Expense Name">
        <input type="number" min="0" step="0.01" class="expense-amount border p-2 rounded w-24" placeholder="Amount">
        <button type="button" class="remove-expense-btn text-red-500"><span class="material-icons">close</span></button>
      `;
      otherContainer.appendChild(row);
    }

    addOtherBtn.addEventListener('click', function(e) {
      e.preventDefault();
      addExpenseRow();
    });

    // Start with one row
    addExpenseRow();
  }

  // -------------------------
  // INITIAL LOAD
  // -------------------------
  updateTotal();

})();
</script>



<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
