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
            header("Location: orders.php?order_id=" . urlencode($order_id));
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
      <div class="bg-white p-3 rounded-xl shadow">
        <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
        <div class="grid grid-cols-2 gap-4">
          <input type="text" name="customer_name" placeholder="Name" class="border rounded w-full text-sm p-2" required>
          <input type="email" name="customer_email" placeholder="Email" class="border rounded w-full text-sm p-2">
          <input type="text" name="contact_number" placeholder="Phone Number" class="border rounded w-full text-sm p-2">
          <input type="date" name="appointment_date" id="appointment_date" value="<?= htmlspecialchars($selected_date ?? '') ?>" class="border rounded w-full p-2">
        </div>
      </div>

      <!-- Products -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
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
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
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
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
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
      <div class="bg-white p-4 rounded-xl shadow flex flex-col">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Personnel</span>
          <input type="text" id="personnelSearch" placeholder="Search..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="personnelTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Rate</th><th class="p-2 text-center">Select</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach($personnel as $pers):
                $isBooked = in_array($pers['id'], $booked_personnel_ids);
              ?>
              <tr class="border-b <?= $isBooked ? 'bg-red-50 opacity-80' : '' ?>" data-personnel-id="<?= (int)$pers['id'] ?>">
                <td class="pers-name p-2"><?= htmlspecialchars($pers['name']) ?></td>
                <td class="p-2 text-center"><?= number_format($pers['rate'],2) ?></td>
                <td class="p-2 text-center">
                  <input type="checkbox" name="personnel_selected[]" class="pers-check" value="<?= (int)$pers['id'] ?>" data-price="<?= htmlspecialchars($pers['rate']) ?>" <?= $isBooked ? 'disabled title="Booked on selected date"' : '' ?>>
                </td>
                <td class="pers-subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div> <!-- END LEFT SIDE -->

    <!-- RIGHT PANEL -->
    <div id="rightPanel" class="w-80 flex flex-col bg-white p-6 rounded-2xl shadow-sm border border-gray-100 h-auto max-h-[80vh]">

      <!-- ITEM LIST -->
      <div id="order-summary" class="flex-1 overflow-y-auto mb-4">
        <span style="color:#777;">No items selected.</span>
      </div>

      <!-- TOTAL + BUTTON -->
      <div id="summaryFooter">
        <hr class="mb-4">
        <p class="text-lg font-medium mb-3">Total:
          <span class="text-blue-700 font-bold text-2xl">$<span id="total">0.00</span></span>
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
(function(){

  function updateTotal(){
    var total = 0;
    var summaryHTML = '';

    // Products (.qty-input)
    document.querySelectorAll('.qty-input').forEach(function(input){
      var qty = parseInt(input.value) || 0;
      var price = parseFloat(input.dataset.price) || 0;
      var subtotal = qty * price;
      var row = input.closest('tr');
      var subcell = row.querySelector('.subtotal');
      if (subcell) subcell.textContent = subtotal.toFixed(2);
      if (qty > 0) {
        var name = row.querySelector('.product-name')?.textContent || row.querySelector('.item-name')?.textContent || 'Item';
        summaryHTML += `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>${name} x ${qty}</span><span>$${subtotal.toFixed(2)}</span></div>`;
      }
      total += subtotal;
    });

    // Split (.split-qty)
    document.querySelectorAll('.split-qty').forEach(function(input){
      var qty = parseInt(input.value) || 0;
      var price = parseFloat(input.dataset.price) || 0;
      var subtotal = qty * price;
      var row = input.closest('tr');
      var subcell = row.querySelector('.subtotal');
      if (subcell) subcell.textContent = subtotal.toFixed(2);
      if (qty > 0) {
        var name = row.querySelector('.item-name')?.textContent || 'Split';
        summaryHTML += `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>${name} x ${qty}</span><span>₱${subtotal.toFixed(2)}</span></div>`;
      }
      total += subtotal;
    });

    // Personnel (checkboxes)
    document.querySelectorAll('.pers-check').forEach(function(chk){
      var price = parseFloat(chk.dataset.price) || 0;
      var subtotal = chk.checked ? price : 0;
      var row = chk.closest('tr');
      var subcell = row.querySelector('.pers-subtotal');
      if (subcell) subcell.textContent = subtotal.toFixed(2);
      if (chk.checked) {
        var name = row.querySelector('.pers-name')?.textContent || 'Personnel';
        summaryHTML += `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>${name}</span><span>₱${subtotal.toFixed(2)}</span></div>`;
      }
      total += subtotal;
    });

    // Ducted Installations (.installation-qty)
    document.querySelectorAll('.installation-qty').forEach(function(input){
      var qty = parseInt(input.value) || 0;
      var row = input.closest('tr');
      var price = parseFloat(row.dataset.price) || 0;
      var subtotal = price * qty;
      var subcell = row.querySelector('.installation-subtotal');
      if (subcell) subcell.textContent = subtotal.toFixed(2);

      if (qty > 0) {
        var select = row.querySelector('.install-type');
        var type = select ? select.value : '';
        var model = type === 'indoor' ? row.dataset.modelIndoor : row.dataset.modelOutdoor;
        summaryHTML += `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>${model} (${type}) x ${qty}</span><span>₱${subtotal.toFixed(2)}</span></div>`;
      }

      total += subtotal;
    });

    document.getElementById('order-summary').innerHTML = summaryHTML || '<span style="color:#777;">No items selected.</span>';
    document.getElementById('total').textContent = total.toFixed(2);
  }

  // plus/minus general handler
  document.addEventListener('click', function(e){
    if (e.target.classList.contains('plus-btn') || e.target.classList.contains('minus-btn')) {
      var input = e.target.closest('td,div')?.querySelector('input[type=number]');
      if (!input) return;
      var v = parseInt(input.value) || 0;
      if (e.target.classList.contains('plus-btn')) v++;
      if (e.target.classList.contains('minus-btn') && v > 0) v--;
      input.value = v;
      updateTotal();
    }
  });

  // checkbox / install-type change
  document.addEventListener('change', function(e){
    if (e.target.classList.contains('pers-check') || e.target.classList.contains('install-type') || e.target.classList.contains('split-qty') || e.target.classList.contains('installation-qty') || e.target.classList.contains('qty-input')) updateTotal();
  });

  // input direct changes
  document.querySelectorAll('input[type=number]').forEach(function(inp){ inp.addEventListener('input', updateTotal); });

  // search filters
  document.getElementById('productSearch')?.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('#productsTable tbody tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
  });
  document.getElementById('personnelSearch')?.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('#personnelTable tbody tr').forEach(r => r.style.display = r.querySelector('td:first-child').textContent.toLowerCase().includes(q) ? '' : 'none');
  });
  document.getElementById('splitSearch')?.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('#splitTable tbody tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
  });

  // AJAX: update personnel availability when appointment_date changes
  document.getElementById('appointment_date')?.addEventListener('change', function(){
    var date = this.value;
    if (!date) return;
    fetch('<?= basename(__FILE__) ?>?check_booked=1&date=' + encodeURIComponent(date))
      .then(r => r.json())
      .then(bookedIds => {
        var set = new Set(bookedIds.map(String));
        document.querySelectorAll('#personnelTable tbody tr').forEach(function(row){
          var pid = row.dataset.personnelId || row.getAttribute('data-personnel-id') || row.querySelector('input.pers-check')?.value;
          if (!pid) return;
          pid = String(pid);
          var checkbox = row.querySelector('input.pers-check');
          if (set.has(pid)) {
            row.classList.add('bg-red-50', 'opacity-80');
            if (checkbox) { checkbox.checked = false; checkbox.disabled = true; checkbox.title = 'Booked on this date'; }
          } else {
            row.classList.remove('bg-red-50', 'opacity-80');
            if (checkbox) { checkbox.disabled = false; checkbox.title = ''; }
          }
        });
        updateTotal();
      })
      .catch(err => {
        console.error('availability check failed', err);
      });
  });

  // Right panel sizing helper (safe: only if elements exist)
  function adjustRightPanelHeight() {
    const panel = document.getElementById('rightPanel');
    const summary = document.getElementById('order-summary');
    const footer = document.getElementById('summaryFooter');
    if (!panel || !summary || !footer) return;
    const panelHeight = panel.clientHeight;
    const footerHeight = footer.offsetHeight;
    summary.style.height = (panelHeight - footerHeight - 16) + 'px';
  }
  window.addEventListener('load', adjustRightPanelHeight);
  window.addEventListener('resize', adjustRightPanelHeight);

  // initial total update
  updateTotal();

})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
