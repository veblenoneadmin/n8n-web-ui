<?php
require "auth.php";
require "config.php";
require "layout.php";

// Fetch split system installation products (no category filter)
$stmt = $pdo->query("
    SELECT id, item_name, unit_price
    FROM split_installation
    ORDER BY id DESC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = "<h2 class='text-xl font-semibold mb-4'></h2>";

$content .= "
<div class='bg-white p-4 rounded-xl shadow flex flex-col'>
    <div class='flex items-center justify-between mb-3'>
        <span class='font-medium text-gray-700'>Search Split System Installations</span>
        <input type='text' id='splitSearch' placeholder='Search by name...' 
        class='border px-3 py-2 rounded-lg shadow-sm w-64' onkeyup='filterSplit()'>
    </div>
    <div class='overflow-x-auto max-h-[500px] border rounded-lg'>
        <table id='splitTable' class='w-full border-collapse text-sm'>
            <thead class='bg-gray-100 sticky top-0'>
                <tr class='border-b'>
                    <th class='p-2 border'>ID</th>
                    <th class='p-2 border'>Name</th>
                    <th class='p-2 border'>Price</th>
                </tr>
            </thead>
            <tbody>
";

if (empty($rows)) {
    $content .= "<tr><td colspan='3' class='text-center p-4 text-gray-500'>No products found.</td></tr>";
} else {
    foreach ($rows as $row) {
        $content .= "
        <tr class='border-b hover:bg-gray-50'>
            <td class='p-2 text-center'>{$row['id']}</td>
            <td class='p-2 font-medium'>{$row['item_name']}</td>
            <td class='p-2 text-right'>$" . number_format($row['unit_price'], 2) . "</td>
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
function filterSplit() {
    var input = document.getElementById('splitSearch').value.toLowerCase();
    var rows = document.querySelectorAll('#splitTable tbody tr');

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
";

renderLayout("Split System Installations", $content);
?>
