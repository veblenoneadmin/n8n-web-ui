<?php
require "admin_only.php";
require "config.php";

// Check if ID is passed
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Request");
}

$id = $_GET['id'];

// Fetch user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role  = trim($_POST['role']);
    $password = trim($_POST['password']);

    if ($password === "") {
        // Update without changing password
        $update = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $update->execute([$name, $email, $role, $id]);
    } else {
        // Update password too (plain text for now)
        $update = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
        $update->execute([$name, $email, $password, $role, $id]);
    }

    header("Location: users.php");
    exit;
}

require "layout.php";

// Build page
$content = <<<HTML
<div class="bg-white p-8 rounded-xl shadow-sm">
    <h2 class="text-lg font-semibold mb-4">Edit User</h2>

    <form method="post" class="space-y-3">
        <input type="text" name="name" value="{$user['name']}" placeholder="Name" required class="border p-2 rounded w-full">

        <input type="email" name="email" value="{$user['email']}" placeholder="Email" required class="border p-2 rounded w-full">

        <select name="role" class="border p-2 rounded w-full">
            <option value="admin"  {$user['role'] === 'admin' ? 'selected' : ''}>Admin</option>
            <option value="user"   {$user['role'] === 'user' ? 'selected' : ''}>User</option>
        </select>

        <input type="password" name="password" placeholder="Leave blank to keep existing password" class="border p-2 rounded w-full">

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
    </form>
</div>
HTML;

renderLayout("Edit User", $content);
