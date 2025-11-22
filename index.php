<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<h2>Database connection error</h2><p>config.php must create a PDO instance named $pdo.</p>');
}

// ---------------------------
// Helper functions
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
$products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id, equipment_name, model_name_indoor, model_name_outdoor, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Equipment
$equipment = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

// Split system table
$split_table_candidates = ['split_system_installation', 'split_installations', 'split_systems', 'split_installation'];
$found = find_split_table($pdo, $split_table_candidates);
$split_installations = [];
if ($found) {
    $split_installations = $pdo->query("SELECT id, item_name, unit_price FROM `$found` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Booked personnel for selected date
$selected_date = $_POST['appointment_date'] ?? $_GET['date'] ?? null;
$booked_personnel_ids = [];
if ($selected_date) {
    $q = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
    $q->execute([$selected_date]);
    $booked_personnel_ids = $q->fetchAll(PDO::FETCH_COLUMN);
}

// ---------------------------
// Handle POST - create order
// ---------------------------
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
    $equipment_inputs  = $_POST['equipment_qty'] ?? [];
    $other_expenses    = $_POST['other_expense'] ?? [];

    if ($customer_name === '') {
        $message = '⚠️ Please enter a customer name.';
    } else {
        try {
            $pdo->beginTransaction();

            // Build orders insert
            $cols = ['order_number', 'customer_name'];
            $placeholders = ['?', '?'];
            $values = [];

            $order_number = $generateOrderNumber();
            $values[] = $order_number;
            $values[] = $customer_name;

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

            $cols[] = 'total_amount';
            $placeholders[] = '?';
            $values[] = 0;

            $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $order_id = $pdo->lastInsertId();

            // ---------------------------
            // Insert order items (NO line_total!)
            $insertStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $total = 0;

            // Products
            foreach ($quantities as $pid => $qtyRaw) {
                $qty = max(0, intval($qtyRaw));
                if ($qty <= 0) continue;
                $prod = array_filter($products, fn($p)=>$p['id']==$pid);
                $prod = reset($prod);
                if ($prod) {
                    $price = floatval($prod['price']);
                    $insertStmt->execute([$order_id, 'product', $pid, null, $qty, $price]);
                    $total += $price * $qty;
                }
            }

            // Split
            foreach ($split_quantities as $sid => $sdata) {
                $qty = max(0, intval($sdata['qty'] ?? 0));
                if ($qty <= 0) continue;
                $row = array_filter($split_installations, fn($s)=>$s['id']==$sid);
                $row = reset($row);
                if ($row) {
                    $price = floatval($row['unit_price']);
                    $insertStmt->execute([$order_id, 'installation', $sid, null, $qty, $price]);
                    $total += $price * $qty;
                }
            }

            // Ducted installations
            foreach ($ducted_inputs as $did => $d) {
                $qty = max(0, intval($d['qty'] ?? 0));
                $type = $d['installation_type'] ?? '';
                if ($qty <= 0 || !$type) continue;
                $row = array_filter($ducted_installations, fn($r)=>$r['id']==$did);
                $row = reset($row);
                if ($row) {
                    $price = floatval($row['total_cost']);
                    $insertStmt->execute([$order_id, 'installation', $did, $type, $qty, $price]);
                    $total += $price * $qty;
                }
            }

            // Personnel
            foreach ($personnel_inputs as $pid => $hours) {
                $hours = floatval($hours);
                if ($hours <= 0) continue;
                $pers = array_filter($personnel, fn($p)=>$p['id']==$pid);
                $pers = reset($pers);
                if ($pers) {
                    $price = floatval($pers['rate']);
                    $insertStmt->execute([$order_id, 'personnel', $pid, null, $hours, $price]);
                    $total += $price * $hours;
                }
            }

            // Equipment
            foreach ($equipment_inputs as $eid => $qty) {
                $qty = max(0, intval($qty));
                if ($qty <= 0) continue;
                $row = array_filter($equipment, fn($e)=>$e['id']==$eid);
                $row = reset($row);
                if ($row) {
                    $price = floatval($row['rate']);
                    $insertStmt->execute([$order_id, 'equipment', $eid, null, $qty, $price]);
                    $total += $price * $qty;
                }
            }

            // Other expenses
            foreach ($other_expenses as $exp) {
                $amt = floatval($exp['amount'] ?? 0);
                if ($amt <= 0) continue;
                $insertStmt->execute([$order_id, 'other', 0, null, 1, $amt]);
                $total += $amt;
            }

            // Update total_amount
            $upd = $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?");
            $upd->execute([$total, $order_id]);

            $pdo->commit();

            header("Location: orders.php?order_id=" . urlencode($order_id));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "❌ Failed to create order: " . $e->getMessage();
        }
    }
}

// ---------------------------
// Render form
// ---------------------------
ob_start();
?>

<?php if ($message): ?>
  <div class="text-red-600 mb-4"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" id="orderForm" class="space-y-6">
  <!-- Customer Info -->
  <div class="bg-white p-4 rounded-xl shadow">
    <h3 class="font-semibold mb-2">Client Information</h3>
    <div class="grid grid-cols-2 gap-4">
      <input type="text" name="customer_name" placeholder="Name" required class="border p-2 rounded">
      <input type="email" name="customer_email" placeholder="Email" class="border p-2 rounded">
      <input type="text" name="contact_number" placeholder="Phone" class="border p-2 rounded">
      <input type="date" name="appointment_date" value="<?= htmlspecialchars($selected_date ?? '') ?>" class="border p-2 rounded">
    </div>
  </div>

  <!-- PRODUCTS, SPLIT, DUCTED, PERSONNEL, EQUIPMENT, OTHER EXPENSES -->
  <!-- For brevity, you can reuse your existing tables with correct JS bindings -->

  <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Order</button>
</form>

<script>
// ====== PLUS / MINUS BUTTONS ======
// You can reuse the JS you already have for qty-input, split-qty, installation-qty, equip-input, etc.
// Make sure the buttons have correct classes: plus-btn, minus-btn
// And they update the input value, then call updateTotal()
// ====== TOTAL CALCULATION ======
// Use your previous updateTotal() function
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
