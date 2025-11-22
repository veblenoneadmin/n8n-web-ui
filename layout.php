<?php
function renderLayout(string $title, string $content, string $activePage = ""): void {
    $titleEsc = htmlspecialchars($title);
    $user = htmlspecialchars($_SESSION['name'] ?? 'Guest');
    $initial = strtoupper(substr($user, 0, 1));
    $role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $titleEsc ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background: #f8f9fa; font-family: "Roboto", sans-serif; }
        .sidebar-link { display:flex; align-items:center; padding-top:5px; gap:12px; border-radius:8px;
                        font-weight:500; color:#64748B; transition:0.25s; }
        .sidebar-link .material-icons { font-size:20px; color:#94A3B8; }
        .sidebar-link:hover { background:#EEF2F7; color:#475569; }
        .sidebar-link:hover .material-icons { color:#475569; }
        .sidebar-active { background:#4F46E5; color:#fff !important; }
        .sidebar-active .material-icons { color:white !important; }

        .dropdown-show { display:block !important; animation:fadeIn .15s ease-in-out; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-3px);} to{opacity:1;transform:translateY(0);} }

        table th { height:42px; line-height:42px; text-align:center; font-size:15px !important; }
        table td { font-size:14px; vertical-align:middle; text-align:center; }
        table th:first-child, table td:first-child { text-align:left; padding-left:.75rem; }
        #productsTable, #personnelTable, #ductedInstallationsTable { font-size:13px; }
        .qty-input, .installation-qty { font-size:13px; height:28px; }
        .plus-btn, .minus-btn { font-size:12px; padding:2px 6px; }
        .pers-check { transform:scale(0.9); }
        #appointment_date::placeholder { color:#999; opacity:1; }
    </style>
</head>

<body class="flex">

<!-- SIDEBAR -->
<aside class="w-52 bg-white shadow-md p-5 flex flex-col gap-3 rounded-2xl sticky top-4 h-[calc(100vh-2rem)] ml-4">
    <h2 class="text-gray-700 text-lg font-semibold mb-4 flex items-center gap-2">
        <span class="material-icons text-gray-500">grid_view</span> LCMB
    </h2>

    <!-- User Dropdown -->
    <div class="w-full mb-2">
        <button id="userToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 bg-indigo-600 text-white flex items-center justify-center rounded-full font-medium text-sm">
                    <?= $initial ?>
                </div>
                <p class="font-semibold text-gray-500"><?= $user ?></p>
            </div>
            <span class="material-icons text-gray-500">expand_more</span>
        </button>
        <div id="userMenu" class="hidden mt-1 rounded-lg">
            <a href="profile.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">person</span> Profile
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">settings</span> Settings
            </a>
            <a href="logout.php" class="flex items-center gap-3 px-1.5 py-2 text-red-600 rounded hover:bg-gray-100">
                <span class="material-icons text-red-500 text-base">logout</span> Logout
            </a>
        </div>
    </div>

    <div class="border-b border-gray-200 mt-1"></div>

    <!-- NAV ITEMS (FIXED LINKS) -->
    <a href="index.php" class="sidebar-link <?= $activePage=='index'?'sidebar-active':'' ?>">
        <span class="material-icons">dashboard</span> Home
    </a>

    <a href="create_order.php" class="sidebar-link <?= $activePage=='create_order'?'sidebar-active':'' ?>">
        <span class="material-icons">add_shopping_cart</span> Create Order
    </a>

    <a href="orders.php" class="sidebar-link <?= $activePage=='orders'?'sidebar-active':'' ?>">
        <span class="material-icons">receipt_long</span> Orders
    </a>

    <a href="personnel.php" class="sidebar-link <?= $activePage=='personnel'?'sidebar-active':'' ?>">
        <span class="material-icons">people_alt</span> Personnel
    </a>

    <!-- PRODUCTS DROPDOWN -->
    <div class="w-full">
        <button id="productsToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <span class="material-icons text-gray-500">inventory_2</span>
                <p class="font-medium text-gray-500">Products</p>
            </div>
            <span class="material-icons text-gray-500">expand_more</span>
        </button>

        <div id="productsMenu" class="hidden mt-1 rounded-lg">
            <a href="products.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">electrical_services</span> Electrical Items
            </a>
            <a href="ducted_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">view_in_ar</span> Ducted Installations
            </a>
            <a href="split_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">ac_unit</span> Split System Installation
            </a>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
    <!-- SETTINGS DROPDOWN -->
    <div class="w-full mb-2">
        <button id="settingsToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <span class="material-icons text-gray-500">settings</span>
                <p class="font-medium text-gray-500">Settings</p>
            </div>
            <span class="material-icons text-gray-500">expand_more</span>
        </button>

        <div id="settingsMenu" class="hidden mt-1 rounded-lg">
            <button id="openTaxModal" class="w-full text-left flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">percent</span> Tax
            </button>
            <a href="gst.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-icons text-gray-500 text-base">receipt_long</span> GST
            </a>
            <a href="users.php" class="sidebar-link <?= $activePage=='users'?'sidebar-active':'' ?>">
                <span class="material-icons">people_alt</span> Users
            </a>
        </div>
    </div>
    <?php endif; ?>
</aside>

<!-- Dropdown Script -->
<script>
document.getElementById("productsToggleBtn").onclick = () => {
    document.getElementById("productsMenu").classList.toggle("hidden");
}
document.getElementById("userToggleBtn").onclick = () => {
    document.getElementById("userMenu").classList.toggle("hidden");
}
document.getElementById("settingsToggleBtn")?.addEventListener("click", () => {
    document.getElementById("settingsMenu").classList.toggle("hidden");
});
</script>

<!-- MAIN CONTENT -->
<main class="flex-1 p-4 md:p-8 overflow-auto">

    <!-- HEADER -->
    <header class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm text-gray-500 flex items-center gap-1">
                <span class="material-icons text-gray-400 text-base">dashboard</span>
                Dashboard / <span class="text-gray-800 font-medium"><?= $titleEsc ?></span>
            </div>
            <h1 class="text-2xl font-semibold text-gray-800"><?= $titleEsc ?></h1>
        </div>

        <div class="flex items-center gap-4">
            <div class="relative">
                <input type="text" placeholder="Search here"
                    class="border rounded-lg px-4 py-2 pl-10 w-48 md:w-64 bg-transparent shadow-sm focus:outline-none focus:border-indigo-500">
                <span class="material-icons absolute left-3 top-2 text-gray-400 text-base">search</span>
            </div>

            <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">account_circle</span>
            <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">settings</span>
            <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">notifications</span>
        </div>
    </header>

    <div>
        <?= $content ?>
    </div>
</main>

<!-- TAX MODAL -->
<div id="taxModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
  <div class="bg-white p-5 rounded-lg w-80 shadow-lg">
    <h2 class="text-xl font-semibold mb-4">Set Tax Rate</h2>
    <label class="block mb-2 font-medium text-gray-600">Tax (%)</label>
    <input type="number" id="taxValue" class="border rounded w-full p-2 mb-4" placeholder="Enter tax rate">
    <div class="flex justify-end gap-2">
      <button id="closeTaxModal" class="px-3 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
      <button id="saveTaxRate" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Save</button>
    </div>
  </div>
</div>

<script>
document.getElementById("openTaxModal")?.addEventListener("click", () => {
    document.getElementById("taxModal").classList.remove("hidden");
});
document.getElementById("closeTaxModal")?.addEventListener("click", () => {
    document.getElementById("taxModal").classList.add("hidden");
});
</script>

</body>
</html>
<?php
}
?>
