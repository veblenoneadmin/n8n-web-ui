<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';

// Redirect to login if session not set
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$isAdmin = $role === 'admin';

try {
    $totalOrdersStmt = $pdo->query("SELECT COUNT(*) AS total FROM orders");
    $totalOrders = $totalOrdersStmt->fetch()['total'] ?? 0;

    $clientsStmt = $pdo->query("SELECT COUNT(*) AS total FROM customers");
    $totalClients = $clientsStmt->fetch()['total'] ?? 0;

    $ductedStmt = $pdo->query("SELECT COUNT(*) AS total FROM ductedinstallations");
    $totalDuctedInstallations = $ductedStmt->fetch()['total'] ?? 0;

    $splitStmt = $pdo->query("SELECT COUNT(*) AS total FROM split_installation");
    $totalSplitInstallations = $splitStmt->fetch()['total'] ?? 0;

    $totalInstallations = $totalDuctedInstallations + $totalSplitInstallations;

    $pendingOrdersStmt = $pdo->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'");
    $pendingOrders = $pendingOrdersStmt->fetch()['total'] ?? 0;

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
    $activities = $activityStmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

ob_start();
?>
<h1>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></h1>
<p>Total Orders: <?= $totalOrders ?></p>
<p>Total Installations: <?= $totalInstallations ?></p>
<p>Total Clients: <?= $totalClients ?></p>
<p>Pending Orders: <?= $pendingOrders ?></p>
<?php
$content = ob_get_clean();
renderLayout("Dashboard", $content, "home");
?>
