<?php
require "auth.php"; // Must be logged in
require "config.php";
require "layout.php";

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    die("<h3 style='color:red; text-align:center; margin-top:40px;'>Access Denied: Admins Only</h3>");
}

$stmt = $pdo->query("
    SELECT 
        o.id AS order_id,
        o.customer_name,
        o.customer_email AS email,
        o.contact_number AS phone,
        SUM(CASE WHEN oi.item_type = 'product' THEN oi.qty ELSE 0 END) AS product_quantity,
        SUM(CASE WHEN oi.item_type = 'personnel' THEN oi.qty ELSE 0 END) AS personnel_count,
        SUM(CASE WHEN oi.item_type = 'installation' THEN oi.qty ELSE 0 END) AS installation_count,
        o.total_amount AS total
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY o.id, o.customer_name, o.customer_email, o.contact_number, o.total_amount
    ORDER BY o.id DESC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = "<h2 class='text-xl font-semibold mb-4'></h2>";

$content .= "
<div class='bg-white p-4 rounded-xl shadow flex flex-col'>
    <div class='flex items-center justify-between mb-3'>
        <span class='font-medium text-gray-700'>Search Orders</span>
        <input type='text' id='orderSearch' placeholder='Search by customer, email, phone...' 
        class='border px-3 py-2 rounded-lg shadow-sm w-64' onkeyup='filterOrders()'>
    </div>
    <div class='overflow-x-auto max-h-[500px] border rounded-lg'>
        <table id='ordersTable' class='w-full border-collapse text-sm'>
            <thead class='bg-gray-100 sticky top-0'>
                <tr class='border-b'>
                    <th class='p-2 border'>Order ID</th>
                    <th class='p-2 border'>Customer Name</th>
                    <th class='p-2 border'>Email</th>
                    <th class='p-2 border'>Phone</th>
                    <th class='p-2 border'>Qty Ordered</th>
                    <th class='p-2 border'>Personnel</th>
                    <th class='p-2 border'>Installation</th>
                    <th class='p-2 border'>Total</th>
                </tr>
            </thead>
            <tbody>
";

if (empty($rows)) {
    $content .= "<tr><td colspan='8' class='text-center p-4 text-gray-500'>No orders recorded yet.</td></tr>";
} else {
    foreach ($rows as $row) {
        $content .= "
        <tr class='border-b hover:bg-gray-50'>
            <td class='p-2 text-center'>{$row['order_id']}</td>
            <td class='p-2'>{$row['customer_name']}</td>
            <td class='p-2'>{$row['email']}</td>
            <td class='p-2'>{$row['phone']}</td>
            <td class='p-2 text-center'>{$row['product_quantity']}</td>
            <td class='p-2 text-center'>{$row['personnel_count']}</td>
            <td class='p-2 text-center'>{$row['installation_count']}</td>
            <td class='p-2 text-right'>₱" . number_format($row['total'], 2) . "</td>
        </tr>
        ";
    }
}

$content .= "
            </tbody>
        </table>
    </div>
</div>

<script>
function filterOrders() {
    var input = document.getElementById('orderSearch').value.toLowerCase();
    var rows = document.querySelectorAll('#ordersTable tbody tr');

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
";

renderLayout("Order History", $content);
?>
