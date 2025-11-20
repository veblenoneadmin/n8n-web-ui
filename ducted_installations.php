<?php
require "auth.php"; // Must be logged in
require "config.php";
require "layout.php";

// Fetch ducted installation products
$stmt = $pdo->query("
    SELECT id, model_name_indoor, model_name_outdoor, equipment_name, quantity, total_cost
    FROM ductedinstallations
    ORDER BY id DESC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = "<h2 class='text-xl font-semibold mb-4'></h2>";

$content .= "
<div class='bg-white p-4 rounded-xl shadow flex flex-col'>
    <div class='flex items-center justify-between mb-3'>
        <span class='font-medium text-gray-700'>Search Ducted Installations</span>
        <input type='text' id='ductedSearch' placeholder='Search by model or equipment...' 
        class='border px-3 py-2 rounded-lg shadow-sm w-64' onkeyup='filterDucted()'>
    </div>
    <div class='overflow-x-auto max-h-[500px] border rounded-lg'>
        <table id='ductedTable' class='w-full border-collapse text-sm'>
            <thead class='bg-gray-100 sticky top-0'>
                <tr class='border-b'>
                    <th class='p-2 border'>ID</th>
                    <th class='p-2 border'>Indoor Model</th>
                    <th class='p-2 border'>Outdoor Model</th>
                    <th class='p-2 border'>Equipment</th>
                    <th class='p-2 border'>Quantity</th>
                    <th class='p-2 border'>Total Cost</th>
                </tr>
            </thead>
            <tbody>
";

if (empty($rows)) {
    $content .= "<tr><td colspan='6' class='text-center p-4 text-gray-500'>No products found.</td></tr>";
} else {
    foreach ($rows as $row) {
        $content .= "
        <tr class='border-b hover:bg-gray-50'>
            <td class='p-2 text-center'>{$row['id']}</td>
            <td class='p-2 font-medium'>{$row['model_name_indoor']}</td>
            <td class='p-2 font-medium'>{$row['model_name_outdoor']}</td>
            <td class='p-2'>{$row['equipment_name']}</td>
            <td class='p-2 text-center'>{$row['quantity']}</td>
            <td class='p-2 text-right'>S" . number_format($row['total_cost'], 2) . "</td>
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
function filterDucted() {
    var input = document.getElementById('ductedSearch').value.toLowerCase();
    var rows = document.querySelectorAll('#ductedTable tbody tr');

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
";

renderLayout("Ducted Installations", $content);
?>
