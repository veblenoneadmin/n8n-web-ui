<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<h2>Database connection error</h2><p>config.php must create a PDO instance named $pdo.</p>');
}

// ---------------------------
// AJAX: return booked personnel IDs for a date
// URL: create_order.php?check_booked=1&date=YYYY-MM-DD
// ---------------------------
if (isset($_GET['check_booked']) && $_GET['check_booked']) {
    $date = $_GET['date'] ?? null;
    header('Content-Type: application/json; charset=utf-8');
    if (!$date) {
        echo json_encode([]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
        $stmt->execute([$date]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(array_map('strval', $ids));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// ---------------------------
// Helper: detect whether a column exists in a table
// ---------------------------
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $q->execute([$column]);
        return (bool)$q->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Helper: find an existing split table name from a list of candidates
function find_split_table(PDO $pdo, array $candidates) {
    foreach ($candidates as $t) {
        $r = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        if ($r) return $t;
    }
    return null;
}

// ---------------------------
// Load lists
// ---------------------------
$message = '';
$products = [];
$personnel = [];
$ducted_installations = [];
$split_installations = [];

try {
    $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "❌ Failed to load products: " . $e->getMessage();
}

try {
    $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "❌ Failed to load personnel: " . $e->getMessage();
}

try {
    $ducted_installations = $pdo->query("SELECT id, equipment_name, model_name_indoor, model_name_outdoor, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ducted_installations = [];
    $message = "❌ Failed to load ducted installations: " . $e->getMessage();
}

// Load equipment list (add this near the other "Load lists" queries)
$equipment = [];
try {
    // Table: equipment (id, item, rate)
    $equipment = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // if table missing or query fails, $equipment stays an empty array
    $equipment = [];
    // optionally set $message so you see the error on the page (comment out in production)
    // $message = "❌ Failed to load equipment: " . $e->getMessage();
}


// Try to find a split installation table under several possible names
$split_table_candidates = ['split_system_installation', 'split_installations', 'split_systems', 'split_installation'];
$found = find_split_table($pdo, $split_table_candidates);
if ($found) {
    try {
        $split_installations = $pdo->query("
    SELECT id, item_name, unit_price 
    FROM `$found` 
    ORDER BY item_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $split_installations = [];
        $message = "❌ Failed to load split installations: " . $e->getMessage();
    }
} else {
    // no split table found — leave empty
    $split_installations = [];
}

// Determine selected appointment date for initial render (prefill from POST if available)
$selected_date = $_POST['appointment_date'] ?? $_GET['date'] ?? null;
$booked_personnel_ids = [];
if ($selected_date) {
    try {
        $q = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
        $q->execute([$selected_date]);
        $booked_personnel_ids = $q->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $booked_personnel_ids = [];
    }
}

// ---------------------------
// Handle POST - create order
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $generateOrderNumber = function() {
        return 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
    };

    $customer_name     = trim($_POST['customer_name'] ?? '');
    $customer_email    = trim($_POST['customer_email'] ?? '');
    $contact_number    = trim($_POST['contact_number'] ?? '');
    $appointment_date  = trim($_POST['appointment_date'] ?? '');
    $quantities        = $_POST['quantity'] ?? [];            // products
    $split_quantities  = $_POST['split'] ?? [];               // split (if any) -> expected format split[id][qty]
    $ducted_inputs     = $_POST['ducted'] ?? [];
    $personnel_inputs  = $_POST['personnel_selected'] ?? [];

    if ($customer_name === '') {
        $message = '⚠️ Please enter a customer name.';
    } else {
        try {
            $pdo->beginTransaction();

            // Build INSERT INTO orders adaptively depending on which columns exist
            $hasCustomerEmail   = column_exists($pdo, 'orders', 'customer_email');
            $hasContactNumber   = column_exists($pdo, 'orders', 'contact_number');
            $hasAppointmentDate = column_exists($pdo, 'orders', 'appointment_date');

            $cols = ['order_number', 'customer_name'];
            $placeholders = ['?', '?'];
            $values = [];

            $order_number = $generateOrderNumber();
            $values[] = $order_number;
            $values[] = $customer_name;

            if ($hasCustomerEmail) {
                $cols[] = 'customer_email';
                $placeholders[] = '?';
                $values[] = $customer_email !== '' ? $customer_email : null;
            }

            if ($hasContactNumber) {
                $cols[] = 'contact_number';
                $placeholders[] = '?';
                $values[] = $contact_number !== '' ? $contact_number : null;
            }

            if ($hasAppointmentDate) {
                $cols[] = 'appointment_date';
                $placeholders[] = '?';
                $values[] = $appointment_date !== '' ? $appointment_date : null;
            }

            // total_amount always present
            $cols[] = 'total_amount';
            $placeholders[] = '?';
            $values[] = 0;

            $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $order_id = $pdo->lastInsertId();

            // IMPORTANT: your order_items table has column installation_type before qty
            $insertStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $total = 0.0;

            // Products
            foreach ($quantities as $pid => $qtyRaw) {
                $pidInt = intval($pid);
                $qty = max(0, intval($qtyRaw));
                if ($qty > 0) {
                    $prod = null;
                    foreach ($products as $p) if ($p['id'] == $pidInt) { $prod = $p; break; }
                    if ($prod) {
                        $price = floatval($prod['price']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'product', $pidInt, null, $qty, $price, $subtotal]);
                        $total += $subtotal;
                    }
                }
            }

            // Split installations (if any) - expects $_POST['split'][id]['qty']
            foreach ($split_quantities as $sid => $sdata) {
                $sidInt = intval($sid);
                $qty = max(0, intval(($sdata['qty'] ?? 0)));
                if ($qty > 0) {
                    $row = null;
                    foreach ($split_installations as $s) if ($s['id'] == $sidInt) { $row = $s; break; }
                    if ($row) {
                        $price = floatval($row['price']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'installation', $sidInt, null, $qty, $price, $subtotal]);
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
                    $insertStmt->execute([$order_id, 'personnel', $pidInt, null, 1, $price, $subtotal]);
                    $total += $subtotal;

                    // Book personnel for appointment_date (if provided and table exists)
                    if ($appointment_date) {
                        try {
                            $tbl = $pdo->query("SHOW TABLES LIKE 'personnel_bookings'")->fetchColumn();
                            if ($tbl) {
                                $chk = $pdo->prepare("SELECT COUNT(*) FROM personnel_bookings WHERE personnel_id = ? AND booked_date = ?");
                                $chk->execute([$pidInt, $appointment_date]);
                                $count = (int)$chk->fetchColumn();
                                if ($count === 0) {
                                    $tryInsert = $pdo->prepare("INSERT INTO personnel_bookings (personnel_id, booked_date) VALUES (?, ?)");
                                    $tryInsert->execute([$pidInt, $appointment_date]);
                                }
                            }
                        } catch (Exception $ex) {
                            // ignore
                        }
                    }
                }
            }

            // Ducted Installations (with installation_type)
            foreach ($ducted_inputs as $did => $d) {
                $didInt = intval($did);
                $qty = max(0, intval($d['qty'] ?? 0));
                $type = $d['installation_type'] ?? '';
                if ($qty > 0 && $type !== '') {
                    $row = null;
                    foreach ($ducted_installations as $r) if ($r['id'] == $didInt) { $row = $r; break; }
                    if ($row) {
                        $price = floatval($row['total_cost']);
                        $subtotal = round($price * $qty, 2);
                        $insertStmt->execute([$order_id, 'installation', $didInt, $type, $qty, $price, $subtotal]);
                        $total += $subtotal;
                    }
                }
            }

            // Update orders total_amount
            $upd = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $upd->execute([round($total, 2), $order_id]);

            $pdo->commit();

            // Redirect to orders.php to view newly created order
            header("Location: orders?order_id=" . urlencode($order_id));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Failed to create order: " . $e->getMessage();
        }
    }
}

// Render page
ob_start();
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
(function() {

  // ===================== UPDATE TOTAL =====================
  function updateTotal() {
    let subtotal = 0;
    let summaryHTML = "";

    // Products
    document.querySelectorAll('#productsTable tbody tr').forEach(row => {
      const input = row.querySelector('.qty-input');
      if(!input) return;
      const qty = parseInt(input.value) || 0;
      const price = parseFloat(input.dataset.price) || 0;
      const sub = qty * price;

      row.querySelector('.subtotal').textContent = sub.toFixed(2);

      if(qty > 0) {
        const name = row.querySelector('.product-name').textContent || 'Item';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // Split Installations
    document.querySelectorAll('#splitTable tbody tr').forEach(row => {
      const input = row.querySelector('.split-qty');
      if(!input) return;
      const qty = parseInt(input.value) || 0;
      const price = parseFloat(input.dataset.price) || 0;
      const sub = qty * price;

      row.querySelector('.subtotal').textContent = sub.toFixed(2);

      if(qty > 0) {
        const name = row.querySelector('.item-name').textContent || 'Split Item';
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // Ducted Installations
    document.querySelectorAll('#ductedInstallationsTable tbody tr').forEach(row => {
      const input = row.querySelector('.installation-qty');
      if(!input) return;
      const qty = parseInt(input.value) || 0;
      const price = parseFloat(row.dataset.price) || 0;
      const sub = qty * price;

      row.querySelector('.installation-subtotal').textContent = sub.toFixed(2);

      if(qty > 0) {
        const type = row.querySelector('.install-type')?.value || '';
        const model = type === 'indoor' ? row.dataset.modelIndoor : row.dataset.modelOutdoor;
        summaryHTML += `<div class="flex justify-between mb-1"><span>${model} (${type}) x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // Personnel
    document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
      const input = row.querySelector('.hour-input');
      if(!input) return;
      const hours = parseFloat(input.value) || 0;
      const rate = parseFloat(row.dataset.rate) || 0;
      const sub = hours * rate;

      row.querySelector('.pers-subtotal').textContent = sub.toFixed(2);

      if(hours > 0) {
        const name = row.querySelector('.pers-name').textContent;
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} (${hours} hr)</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // Equipment
    document.querySelectorAll('#equipmentTable tbody tr').forEach(row => {
      const input = row.querySelector('.equip-input');
      if(!input) return;
      const qty = parseInt(input.value) || 0;
      const rate = parseFloat(row.dataset.rate) || 0;
      const sub = qty * rate;

      row.querySelector('.equip-subtotal').textContent = sub.toFixed(2);

      if(qty > 0) {
        const name = row.querySelector('.equip-name').textContent;
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${sub.toFixed(2)}</span></div>`;
      }

      subtotal += sub;
    });

    // Other Expenses
    document.querySelectorAll('.other-expense-row').forEach(row => {
      const name = row.querySelector('.expense-name').value.trim();
      const amt = parseFloat(row.querySelector('.expense-amount').value) || 0;
      if(name !== '' && amt > 0) {
        summaryHTML += `<div class="flex justify-between mb-1"><span>${name}</span><span>$${amt.toFixed(2)}</span></div>`;
      }
      subtotal += amt;
    });

    // Display summary
    document.getElementById('order-summary').innerHTML = summaryHTML || '<span style="color:#777;">No items selected.</span>';

    // Tax and Grand Total
    const gstRate = 0.10;
    const gst = subtotal * gstRate;
    const grandTotal = subtotal + gst;

    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = gst.toFixed(2);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);

    // Profit
    const profit = subtotal * 0.30;
    const netProfitPercent = subtotal > 0 ? ((profit - gst) / subtotal) * 100 : 0;
    const profitMargin = subtotal > 0 ? (profit / subtotal) * 100 : 0;

    document.getElementById('profitDisplay').textContent = profit.toFixed(2);
    document.getElementById('netProfitDisplay').textContent = netProfitPercent.toFixed(2);
    document.getElementById('profitMarginDisplay').textContent = profitMargin.toFixed(2);
    document.getElementById('totalProfitDisplay').textContent = profit.toFixed(2);
  }

  // ===================== UNIVERSAL PLUS / MINUS =====================
  document.addEventListener('click', function(e){
    const btn = e.target.closest('button');
    if(!btn) return;

    const plusClasses = ['plus-btn','hour-plus','equip-plus'];
    const minusClasses = ['minus-btn','hour-minus','equip-minus'];

    const row = btn.closest('tr');
    if(!row) return;

    let input = null;

    // Identify the corresponding input
    if(plusClasses.some(c=>btn.classList.contains(c)) || minusClasses.some(c=>btn.classList.contains(c))) {
      input = row.querySelector('input[type="number"]');
      if(!input) return;
    }

    let val = parseFloat(input.value) || 0;
    if(plusClasses.some(c=>btn.classList.contains(c))) val++;
    if(minusClasses.some(c=>btn.classList.contains(c))) val = Math.max(0, val-1);

    input.value = val;
    updateTotal();
  });

  // ===================== INPUT CHANGE =====================
  document.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('input', updateTotal);
  });

  // ===================== OTHER EXPENSES =====================
  const container = document.getElementById("otherExpensesContainer");
  const addBtn = document.getElementById("addOtherExpenseBtn");

  if(container && addBtn) {
    function addExpenseRow() {
      const row = document.createElement("div");
      row.className = "other-expense-row flex gap-2 items-center mb-2";
      row.innerHTML = `
        <input type="text" class="expense-name border p-2 rounded flex-1" placeholder="Expense Name">
        <input type="number" min="0" step="0.01" class="expense-amount border p-2 rounded w-24" placeholder="Amount">
        <button type="button" class="remove-expense-btn text-red-500">
          <span class="material-icons">close</span>
        </button>
      `;
      container.appendChild(row);

      row.querySelector(".remove-expense-btn").addEventListener("click", () => {
        row.remove();
        updateTotal();
      });
      row.querySelector(".expense-name").addEventListener("input", updateTotal);
      row.querySelector(".expense-amount").addEventListener("input", updateTotal);
    }

    addBtn.addEventListener('click', e => {
      e.preventDefault();
      addExpenseRow();
    });

    addExpenseRow(); // start with 1 row
  }

  // ===================== INITIAL LOAD =====================
  updateTotal();

})();
</script>



<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
