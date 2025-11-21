<?php
require_once "config.php";

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body { margin:0; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#f0f0f0; }
.login-container { background:#fff; padding:40px; border-radius:12px; width:350px; box-shadow:0 6px 18px rgba(0,0,0,0.1); text-align:center; }
input { width:80%; padding:10px; margin:10px 0; border-radius:6px; border:1px solid #ccc; }
button { width:80%; padding:10px; border:none; border-radius:6px; background:#007bff; color:#fff; cursor:pointer; }
button:hover { background:#0056b3; }
.error { color:red; font-size:14px; margin-bottom:10px; }
</style>
</head>
<body>
<div class="login-container">
<h2>Login</h2>
<?php if($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="post">
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>
</div>
</body>
</html>
