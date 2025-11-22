<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

// ---------------------------
// AJAX: return booked personnel IDs for a date
// ---------------------------
if (isset($_GET['check_booked']) && $_GET['check_booked']) {
    $date = $_GET['date'] ?? null;
    header('Content-Type: application/json; charset=utf-8');
    if (!$date) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
    $stmt->execute([$date]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(array_map('strval', $ids));
    exit;
}

// Helper functions
function column_exists(PDO $pdo, string $table, string $column): bool {
    try { $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $q->execute([$column]); return (bool)$q->fetch(); }
    catch (Exception $e) { return false; }
}

function find_split_table(PDO $pdo, array $candidates) {
    foreach ($candidates as $t) { if ($pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn()) return $t; }
    return null;
}

// Load lists
$products = $pdo->query("SELECT id,name,price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id,name,rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id,equipment_name,model_name_indoor,model_name_outdoor,total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT id,item,rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

$split_table = find_split_table($pdo,['split_system_installation','split_installations','split_systems','split_installation']);
$split_installations = $split_table ? $pdo->query("SELECT id,item_name,unit_price FROM `$split_table` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];

$selected_date = $_POST['appointment_date'] ?? $_GET['date'] ?? null;
$booked_personnel_ids = [];
if ($selected_date) {
    $stmt = $pdo->prepare("SELECT personnel_id FROM personnel_bookings WHERE booked_date = ?");
    $stmt->execute([$selected_date]);
    $booked_personnel_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle POST — create order safely
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    if ($customer_name === '') $message = '⚠️ Please enter customer name.';
    else {
        try {
            $pdo->beginTransaction();
            $cols = ['order_number','customer_name']; $placeholders=['?','?']; $values=[];
            $order_number = 'ORD-'.date('YmdHis').'-'.rand(100,999);
            $values[] = $order_number; $values[] = $customer_name;

            foreach (['customer_email','contact_number','appointment_date'] as $col) {
                if (column_exists($pdo,'orders',$col)) {
                    $cols[] = $col; $placeholders[] = '?';
                    $values[] = trim($_POST[$col]??'') ?: null;
                }
            }
            $cols[] = 'total_amount'; $placeholders[] = '?'; $values[]=0;

            $stmt = $pdo->prepare("INSERT INTO orders(".implode(',',$cols).") VALUES (".implode(',',$placeholders).")");
            $stmt->execute($values);
            $order_id = $pdo->lastInsertId();

            $insertItem = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,installation_type,qty,price) VALUES (?,?,?,?,?,?)");

            // --- Products ---
            foreach ($_POST['quantity'] ?? [] as $pid => $qty) {
                $qty = max(0,intval($qty));
                if ($qty>0) {
                    $prod = array_filter($products, fn($p)=>$p['id']==$pid);
                    if ($prod) {
                        $prod = array_values($prod)[0];
                        $insertItem->execute([$order_id,'product',$pid,null,$qty,$prod['price']]);
                    }
                }
            }

            // --- Split installations ---
            foreach ($_POST['split'] ?? [] as $sid=>$data) {
                $qty = max(0,intval($data['qty']??0));
                if ($qty>0) { $insertItem->execute([$order_id,'installation',$sid,null,$qty,floatval($data['unit_price']??0)]); }
            }

            // --- Ducted ---
            foreach ($_POST['ducted'] ?? [] as $did=>$data) {
                $qty = max(0,intval($data['qty']??0));
                $type = $data['installation_type']??null;
                if ($qty>0 && $type) { $insertItem->execute([$order_id,'installation',$did,$type,$qty,floatval($data['price']??0)]); }
            }

            // --- Personnel ---
            foreach ($_POST['personnel_selected'] ?? [] as $pid) {
                $pid = intval($pid);
                $hours = max(0,intval($_POST['personnel_hours'][$pid]??0));
                if ($hours>0) { $insertItem->execute([$order_id,'personnel',$pid,null,$hours,floatval($_POST['personnel_rate'][$pid]??0)]); }
            }

            // --- Equipment ---
            foreach ($_POST['equipment_qty'] ?? [] as $eid=>$qty) {
                $qty = max(0,intval($qty));
                if ($qty>0) { $insertItem->execute([$order_id,'equipment',$eid,null,$qty,floatval($_POST['equipment_rate'][$eid]??0)]); }
            }

            $pdo->commit();
            header("Location: orders.php?order_id=".$order_id);
            exit;
        } catch (Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); $message = "❌ Failed to create order: ".$e->getMessage(); }
    }
}

// ---------------------------
// Render HTML
// ---------------------------
ob_start();
?>

<?php if ($message): ?>
<div class="mb-4 text-red-600"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" id="orderForm" class="space-y-6">
  <div class="flex gap-6">
    <div class="flex-1 flex flex-col gap-6">
      <!-- Customer Info -->
      <div class="bg-white p-3 rounded-xl shadow border border-gray-200">
        <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
        <div class="grid grid-cols-2 gap-4">
          <input type="text" name="customer_name" placeholder="Name" class="border rounded w-full text-sm p-2" required>
          <input type="email" name="customer_email" placeholder="Email" class="border rounded w-full text-sm p-2">
          <input type="text" name="contact_number" placeholder="Phone Number" class="border rounded w-full text-sm p-2">
          <input type="date" name="appointment_date" id="appointment_date" class="border rounded w-full p-2">
        </div>
      </div>

      <!-- Products Table -->
      <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
        <h5 class="font-medium text-gray-700 mb-2">Material</h5>
        <table class="w-full border-collapse text-sm">
          <thead class="bg-gray-100"><tr><th>Name</th><th>Price</th><th>Qty</th></tr></thead>
          <tbody>
            <?php foreach($products as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td><?= number_format($p['price'],2) ?></td>
              <td>
                <button type="button" class="minus-btn">-</button>
                <input type="number" min="0" name="quantity[<?= $p['id'] ?>]" value="0" class="qty-input" data-price="<?= $p['price'] ?>">
                <button type="button" class="plus-btn">+</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Split, Ducted, Personnel, Equipment, Other Expenses -->
      <!-- You can replicate similar HTML as above for the other tables -->
    </div>

    <!-- Right Panel -->
    <div class="w-80 flex flex-col gap-4">
      <div class="bg-white p-4 rounded-xl shadow border border-gray-200" id="rightPanel">
        <h3 class="text-base font-semibold text-gray-700 mb-2">Order Summary</h3>
        <div id="order-summary" class="flex-1 overflow-y-auto mb-4"><span style="color:#777;">No items selected.</span></div>
        <hr class="mb-3">
        <p class="flex justify-between text-gray-600"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></p>
        <p class="flex justify-between text-gray-600"><span>Tax:</span><span>$<span id="taxAmount">0.00</span></span></p>
        <p class="flex justify-between text-blue-700 font-semibold text-lg"><span>Grand Total:</span><span>$<span id="grandTotal">0.00</span></span></p>
        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700">Save Order</button>
      </div>
    </div>
  </div>
</form>

<!-- JS for plus/minus and summary -->
<script>
document.querySelectorAll('.plus-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{ let input=btn.previousElementSibling; input.value=parseInt(input.value||0)+1; updateTotal(); });
});
document.querySelectorAll('.minus-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{ let input=btn.nextElementSibling; input.value=Math.max(0,parseInt(input.value||0)-1); updateTotal(); });
});
document.querySelectorAll('.qty-input').forEach(inp=>inp.addEventListener('input',updateTotal));

function updateTotal(){
  let subtotal=0; let summary="";
  document.querySelectorAll('.qty-input').forEach(inp=>{
    let q=parseInt(inp.value)||0;
    let p=parseFloat(inp.dataset.price)||0;
    let row=inp.closest('tr');
    subtotal+=q*p;
    if(q>0){ summary+=`<div class="flex justify-between"><span>${row.cells[0].innerText} x ${q}</span><span>$${(q*p).toFixed(2)}</span></div>`; }
  });
  const gst=subtotal*0.1;
  document.getElementById('subtotalDisplay').textContent=subtotal.toFixed(2);
  document.getElementById('taxAmount').textContent=gst.toFixed(2);
  document.getElementById('grandTotal').textContent=(subtotal+gst).toFixed(2);
  document.getElementById('order-summary').innerHTML=summary||'<span style="color:#777;">No items selected.</span>';
}
updateTotal();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content);
?>
