<?php
function renderLayout(string $title, string $content, string $activePage = ""): void {
    $titleEsc = htmlspecialchars($title);
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

            .sidebar-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                border-radius: 8px;
                font-weight: 500;
                color: #64748B;
                transition: 0.25s;
            }
            .sidebar-link .material-icons {
                font-size: 20px;
                color: #94A3B8;
            }
            .sidebar-link:hover {
                background: #EEF2F7;
                color: #475569;
            }
            .sidebar-link:hover .material-icons {
                color: #475569;
            }

            /* ACTIVE LINK */
            .sidebar-active {
                background: #4F46E5;
                color: #fff !important;
            }
            .sidebar-active .material-icons {
                color: white !important;
            }
        </style>
    </head>

    <body class="flex">

        <!-- SIDEBAR -->
        <aside class="w-64 bg-white shadow-md p-5 h-screen flex flex-col gap-3 sticky top-0">

            <h2 class="text-gray-700 text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="material-icons text-gray-500">grid_view</span>
                LCMB
            </h2>

            <a href="home.php" class="sidebar-link <?= $activePage=="home"?"sidebar-active":"" ?>">
                <span class="material-icons">dashboard</span> Home
            </a>

            <a href="create_order.php" class="sidebar-link <?= $activePage=="create_order"?"sidebar-active":"" ?>">
                <span class="material-icons">add_shopping_cart</span> Create Order
            </a>

            <a href="orders.php" class="sidebar-link <?= $activePage=="orders"?"sidebar-active":"" ?>">
                <span class="material-icons">receipt_long</span> Orders
            </a>

            <a href="products.php" class="sidebar-link <?= $activePage=="products"?"sidebar-active":"" ?>">
                <span class="material-icons">inventory_2</span> Products
            </a>

            <a href="personnel.php" class="sidebar-link <?= $activePage=="personnel"?"sidebar-active":"" ?>">
                <span class="material-icons">people_alt</span> Personnel
            </a>

        </aside>

        <!-- MAIN -->
        <main class="flex-1">

            <header class="flex items-center justify-between px-8 py-5 bg-[#fafafa] border-b">

                <div>
                    <div class="text-sm text-gray-500 flex items-center gap-1">
                        <span class="material-icons text-gray-400 text-base">dashboard</span>
                        Dashboard /
                        <span class="text-gray-800 font-medium"><?= $titleEsc ?></span>
                    </div>
                    <h1 class="text-2xl font-semibold text-gray-800"><?= $titleEsc ?></h1>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative">
                        <input type="text" placeholder="Search here"
                            class="border rounded-lg px-4 py-2 pl-10 w-64 bg-white shadow-sm focus:outline-none focus:border-indigo-500">
                        <span class="material-icons absolute left-3 top-2 text-gray-400 text-base">search</span>
                    </div>

                    <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">account_circle</span>
                    <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">settings</span>
                    <span class="material-icons text-gray-600 hover:text-gray-900 cursor-pointer">notifications</span>
                </div>
            </header>

            <div class="p-8">
                <?= $content ?>
            </div>

        </main>

    </body>
    </html>
    <?php
}
