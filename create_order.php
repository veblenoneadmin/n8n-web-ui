<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

// Fetch data from DB for demonstration
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
$splitSystems = $pdo->query("SELECT * FROM split_systems")->fetchAll(PDO::FETCH_ASSOC);
$ductedInstallations = $pdo->query("SELECT * FROM ducted_installations")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT * FROM equipment")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6 bg-gray-100 min-h-screen">

  <!-- Customer Details -->
  <div class="bg-white p-4 rounded-xl shadow mb-6">
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block font-medium mb-1">Customer Name</label>
        <input type="text" name="customer_name" placeholder="John Doe" class="border rounded w-full p-2">
      </div>
      <div>
        <label class="block font-medium mb-1">Customer Email</label>
        <input type="email" name="customer_email" placeholder="email@example.com" class="border rounded w-full p-2">
      </div>
    </div>
  </div>

  <!-- PRODUCTS TABLE -->
  <div class="bg-white p-4 rounded-xl shadow mb-6 overflow-x-auto">
    <h2 class="font-bold text-lg mb-3">Products</h2>
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-200">
          <th class="p-2 text-left">Product</th>
          <th class="p-2">Price</th>
          <th class="p-2">Quantity</th>
          <th class="p-2">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($products as $p): ?>
        <tr>
          <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
          <td class="p-2">$<?= number_format($p['price'], 2) ?></td>
          <td class="p-2 text-center">
            <div class="inline-flex items-center space-x-2">
              <button type="button" class="minus bg-gray-200 px-2 py-1 rounded">-</button>
              <input type="number" min="0" value="0" class="qty-input border rounded w-16 text-center" data-price="<?= $p['price'] ?>" data-type="product">
              <button type="button" class="plus bg-gray-200 px-2 py-1 rounded">+</button>
            </div>
          </td>
          <td class="subtotal p-2 text-right">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- SPLIT SYSTEM TABLE -->
  <div class="bg-white p-4 rounded-xl shadow mb-6 overflow-x-auto">
    <h2 class="font-bold text-lg mb-3">Split Systems</h2>
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-200">
          <th class="p-2 text-left">Name</th>
          <th class="p-2">Price</th>
          <th class="p-2">Quantity</th>
          <th class="p-2">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($splitSystems as $s): ?>
        <tr>
          <td class="item-name p-2"><?= htmlspecialchars($s['name']) ?></td>
          <td class="p-2">$<?= number_format($s['price'],2) ?></td>
          <td class="p-2 text-center">
            <div class="inline-flex items-center space-x-2">
              <button type="button" class="minus bg-gray-200 px-2 py-1 rounded">-</button>
              <input type="number" min="0" value="0" class="qty-input border rounded w-16 text-center" data-price="<?= $s['price'] ?>" data-type="split">
              <button type="button" class="plus bg-gray-200 px-2 py-1 rounded">+</button>
            </div>
          </td>
          <td class="subtotal p-2 text-right">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- DUCTED INSTALLATIONS TABLE -->
  <div class="bg-white p-4 rounded-xl shadow mb-6 overflow-x-auto">
    <h2 class="font-bold text-lg mb-3">Ducted Installations</h2>
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-200">
          <th class="p-2 text-left">Name</th>
          <th class="p-2">Installation Type</th>
          <th class="p-2">Price</th>
          <th class="p-2">Quantity</th>
          <th class="p-2">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($ductedInstallations as $d): ?>
        <tr data-price="<?= $d['price'] ?>" data-model-indoor="<?= $d['indoor_model'] ?>" data-model-outdoor="<?= $d['outdoor_model'] ?>">
          <td class="p-2"><?= htmlspecialchars($d['name']) ?></td>
          <td class="p-2">
            <select class="install-type border rounded p-1 w-full">
              <option value="indoor">Indoor</option>
              <option value="outdoor">Outdoor</option>
            </select>
          </td>
          <td class="p-2">$<?= number_format($d['price'],2) ?></td>
          <td class="p-2 text-center">
            <div class="inline-flex items-center space-x-2">
              <button type="button" class="minus bg-gray-200 px-2 py-1 rounded">-</button>
              <input type="number" min="0" value="0" class="qty-input border rounded w-16 text-center" data-type="ducted">
              <button type="button" class="plus bg-gray-200 px-2 py-1 rounded">+</button>
            </div>
          </td>
          <td class="installation-subtotal p-2 text-right">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- EQUIPMENT TABLE -->
  <div class="bg-white p-4 rounded-xl shadow mb-6 overflow-x-auto">
    <h2 class="font-bold text-lg mb-3">Equipment</h2>
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-200">
          <th class="p-2 text-left">Name</th>
          <th class="p-2">Rate</th>
          <th class="p-2">Quantity</th>
          <th class="p-2">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($equipment as $e): ?>
        <tr data-rate="<?= $e['rate'] ?>">
          <td class="equip-name p-2"><?= htmlspecialchars($e['name']) ?></td>
          <td class="p-2">$<?= number_format($e['rate'],2) ?></td>
          <td class="p-2 text-center">
            <div class="inline-flex items-center space-x-2">
              <button type="button" class="minus bg-gray-200 px-2 py-1 rounded">-</button>
              <input type="number" min="0" value="0" class="qty-input border rounded w-16 text-center" data-type="equipment">
              <button type="button" class="plus bg-gray-200 px-2 py-1 rounded">+</button>
            </div>
          </td>
          <td class="equip-subtotal p-2 text-right">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PERSONNEL TABLE -->
  <div class="bg-white p-4 rounded-xl shadow mb-6 overflow-x-auto">
    <h2 class="font-bold text-lg mb-3">Personnel</h2>
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-200">
          <th class="p-2 text-left">Name</th>
          <th class="p-2">Rate/hr</th>
          <th class="p-2">Hours</th>
          <th class="p-2">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($personnel as $p): ?>
        <tr data-rate="<?= $p['rate'] ?>">
          <td class="pers-name p-2"><?= htmlspecialchars($p['name']) ?></td>
          <td class="p-2">$<?= number_format($p['rate'],2) ?></td>
          <td class="p-2 text-center">
            <div class="inline-flex items-center space-x-2">
              <button type="button" class="minus bg-gray-200 px-2 py-1 rounded">-</button>
              <input type="number" min="0" value="0" class="qty-input border rounded w-16 text-center" data-type="personnel">
              <button type="button" class="plus bg-gray-200 px-2 py-1 rounded">+</button>
            </div>
          </td>
          <td class="pers-subtotal p-2 text-right">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- OTHER EXPENSES -->
  <div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold text-lg mb-3">Other Expenses</h2>
    <div id="otherExpensesContainer" class="space-y-2"></div>
    <button type="button" id="addOtherExpense" class="bg-indigo-500 text-white px-3 py-1 rounded mt-2">+ Add More</button>
  </div>

  <!-- SUMMARY -->
  <div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold text-lg mb-3">Order Summary</h2>
    <div id="order-summary" class="mb-3 text-gray-700"></div>
    <div class="flex justify-between mb-1"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
    <div class="flex justify-between mb-1"><span>GST 10%:</span><span>$<span id="taxAmount">0.00</span></span></div>
    <div class="flex justify-between mb-1 font-bold"><span>Grand Total:</span><span>$<span id="grandTotal">0.00</span></span></div>
    <div class="flex justify-between mb-1"><span>Profit:</span><span>$<span id="profitDisplay">0.00</span></span></div>
    <div class="flex justify-between mb-1"><span>Net Profit %:</span><span><span id="netProfitDisplay">0</span>%</span></div>
    <div class="flex justify-between mb-1"><span>Profit Margin %:</span><span><span id="profitMarginDisplay">0</span>%</span></div>
    <div class="flex justify-between font-bold"><span>Total Profit:</span><span>$<span id="totalProfitDisplay">0.00</span></span></div>
  </div>

</div>

<!-- JS -->
<script>
// ===================== UNIFIED JS FOR PLUS/MINUS & CALC =====================
(function() {
  const otherContainer = document.getElementById('otherExpensesContainer');
  document.getElementById('addOtherExpense').addEventListener('click', function() {
    const row = document.createElement('div');
    row.className = 'other-expense-row flex space-x-2';
    row.innerHTML = `
      <input type="text" placeholder="Expense Name" class="expense-name border rounded p-1 flex-1">
      <input type="number" min="0" value="0" class="expense-amount border rounded p-1 w-32">
      <button type="button" class="remove bg-red-500 text-white px-2 rounded">x</button>
    `;
    otherContainer.appendChild(row);

    row.querySelector('.remove').addEventListener('click', () => { row.remove(); updateTotal(); });
    row.querySelector('.expense-amount').addEventListener('input', updateTotal);
  });

  function updateTotal() {
    let subtotal = 0;
    let summaryHTML = "";

    // Generic calculation function
    function calc(type, subClassName, nameSelector) {
      document.querySelectorAll(`input[data-type="${type}"]`).forEach(input => {
        const qty = parseFloat(input.value)||0;
        const row = input.closest('tr');
        let price = parseFloat(input.dataset.price || row.dataset.price || row.dataset.rate || 0);
        if (type === 'ducted') {
          const installType = row.querySelector('.install-type').value;
          const model = installType==='indoor'?row.dataset.modelIndoor:row.dataset.modelOutdoor;
          summaryHTML += qty>0?`<div class="flex justify-between mb-1"><span>${model} (${installType}) x ${qty}</span><span>$${(qty*price).toFixed(2)}</span></div>`:'';
        } else {
          const name = row.querySelector(nameSelector).textContent;
          summaryHTML += qty>0?`<div class="flex justify-between mb-1"><span>${name} x ${qty}</span><span>$${(qty*price).toFixed(2)}</span></div>`:'';
        }
        row.querySelector(`.${subClassName}`).textContent = (qty*price).toFixed(2);
        subtotal += qty*price;
      });
    }

    calc('product','subtotal','product-name');
    calc('split','subtotal','item-name');
    calc('ducted','installation-subtotal','');
    calc('equipment','equip-subtotal','equip-name');
    calc('personnel','pers-subtotal','pers-name');

    document.querySelectorAll(".other-expense-row").forEach(row=>{
      let name = row.querySelector(".expense-name").value.trim();
      let amt = parseFloat(row.querySelector(".expense-amount").value)||0;
      if(amt>0 && name) summaryHTML += `<div class="flex justify-between mb-1"><span>${name}</span><span>$${amt.toFixed(2)}</span></div>`;
      subtotal += amt;
    });

    document.getElementById('order-summary').innerHTML = summaryHTML||'<span style="color:#777;">No items selected.</span>';

    const gst = subtotal*0.10;
    const grand = subtotal+gst;
    const profit = subtotal*0.30;
    const netProfitPercent = subtotal?(profit-gst)/subtotal*100:0;
    const profitMargin = subtotal?profit/subtotal*100:0;

    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = gst.toFixed(2);
    document.getElementById('grandTotal').textContent = grand.toFixed(2);
    document.getElementById('profitDisplay').textContent = profit.toFixed(2);
    document.getElementById('netProfitDisplay').textContent = netProfitPercent.toFixed(2);
    document.getElementById('profitMarginDisplay').textContent = profitMargin.toFixed(2);
    document.getElementById('totalProfitDisplay').textContent = profit.toFixed(2);
  }

  // PLUS/MINUS BUTTONS
  document.addEventListener('click', function(e){
    const btn = e.target.closest('button');
    if(!btn) return;
    const isPlus = btn.classList.contains('plus');
    const isMinus = btn.classList.contains('minus');
    if(!isPlus && !isMinus) return;

    const container = btn.parentElement;
    const input = container.querySelector('input[type="number"]');
    if(!input) return;
    let val = parseFloat(input.value)||0;
    if(isPlus) val++;
    if(isMinus) val = Math.max(0,val-1);
    input.value = val;
    updateTotal();
  });

  document.querySelectorAll('input[type="number"]').forEach(input=>{
    input.addEventListener('input', updateTotal);
  });
  document.querySelectorAll('.install-type').forEach(sel=>{
    sel.addEventListener('change', updateTotal);
  });

  updateTotal();
})();
</script>
