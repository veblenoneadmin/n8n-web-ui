<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user'; // default to 'user' if role not set
$isAdmin = $role === 'admin';

// ====================
// Fetch Analytics Data
// ====================
try {
    // Total Orders
    $totalOrdersStmt = $pdo->query("SELECT COUNT(*) AS total FROM orders");
    $totalOrders = $totalOrdersStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total number of clients
    $clientsStmt = $pdo->query("SELECT COUNT(*) AS total FROM customers");
    $totalClients = $clientsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total ducted installations
    $ductedStmt = $pdo->query("SELECT COUNT(*) AS total FROM ductedinstallations");
    $totalDuctedInstallations = $ductedStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total split installations
    $splitStmt = $pdo->query("SELECT COUNT(*) AS total FROM split_installation");
    $totalSplitInstallations = $splitStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Optional: total installations (sum of ducted + split)
    $totalInstallations = $totalDuctedInstallations + $totalSplitInstallations;

    // ====================
    // Fetch Activity Logs
    // ====================
    if ($isAdmin) {
        $activityStmt = $pdo->prepare("
            SELECT l.created_at, u.name AS user_name, l.action, l.reference_type, l.reference_id
            FROM activity_logs l
            JOIN users u ON u.id = l.user_id
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        $activityStmt->execute();
    } else {
        $activityStmt = $pdo->prepare("
            SELECT l.created_at, u.name AS user_name, l.action, l.reference_type, l.reference_id
            FROM activity_logs l
            JOIN users u ON u.id = l.user_id
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        $activityStmt->execute([$currentUserId]);
    }

    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ====================
// Build Page Content
// ====================
ob_start();
?>

<!-- Analytics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Product Orders</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalOrders ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Installations</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalInstallations ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Clients</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalClients ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Pending Orders</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $pendingOrders ?? 0 ?></p>
    </div>
</div>

<!-- Recent Activity Log -->
<div class="bg-white p-6 rounded-xl shadow">
    <h2 class="text-lg font-semibold mb-3 text-gray-700">Recent Activity</h2>
    <p class="text-sm text-gray-500 mb-4">Showing the last 10 system actions</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
            <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <th class="px-4 py-2 border-b">User</th>
                    <th class="px-4 py-2 border-b">Action</th>
                    <th class="px-4 py-2 border-b">Reference</th>
                    <th class="px-4 py-2 border-b">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($activities)): ?>
                    <?php foreach ($activities as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 border-b font-medium text-gray-700">
                                <?= htmlspecialchars($log['user_name']); ?>
                            </td>
                            <td class="px-4 py-2 border-b text-gray-600">
                                <?= htmlspecialchars($log['action']); ?>
                            </td>
                            <td class="px-4 py-2 border-b text-gray-600">
                                <?= $log['reference_type'] 
                                    ? ucfirst($log['reference_type']) . " #{$log['reference_id']}" 
                                    : '—'; ?>
                            </td>
                            <td class="px-4 py-2 border-b text-gray-500">
                                <?= date("M d, Y h:i A", strtotime($log['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">
                            No activity recorded yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
renderLayout("Home", $content, "home");
?>
