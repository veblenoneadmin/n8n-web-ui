<?php
require "admin_only.php";
require "config.php";


$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $role = trim($_POST['role']);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $pass, $role]);

    header("Location: users.php");
    exit;
}

require "layout.php";

$content = '<h2>Add User</h2>
<form method="post">
<input type="text" name="name" placeholder="Name" required>
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>

<select name="role">
<option value="admin">Admin</option>
<option value="user">User</option>
</select>

<button type="submit">Save</button>
</form>';

renderLayout("Add User", $content);
