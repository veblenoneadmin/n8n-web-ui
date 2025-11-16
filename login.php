<?php
session_start();
require_once "config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['password']) { // plain text temp
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        header("Location: home.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<style>
body {
    margin: 0;
    font-family: "Inter", sans-serif;
    background: linear-gradient(135deg, #4b79a1, #283e51);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #333;
}

.login-container {
    background: #fff;
    width: 400px;
    padding: 40px 30px;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    text-align: center;
    animation: fadeIn 0.4s ease-in-out;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(15px);}
    to   {opacity: 1; transform: translateY(0);}
}

.login-container h2 {
    font-weight: 600;
    margin-bottom: 25px;
    color: #2d3748;
}

.input-group {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
    align-items: center; /* center the inputs inside the group */
}

.input-group label {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 6px;
    color: #555;
    align-self: flex-start; /* label stays left */
}

.input-group input {
    width: 80%; /* input smaller than full width */
    max-width: 300px;
    padding: 12px;
    border: 1px solid #ccd0d5;
    border-radius: 8px;
    font-size: 14px;
    transition: 0.25s;
}

.input-group input:focus {
    border-color: #3182ce;
    outline: none;
    box-shadow: 0 0 0 2px rgba(49,130,206,0.2);
}

button {
    width: 80%;
    max-width: 300px;
    padding: 12px;
    background: #0066ff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    color: #fff;
    transition: 0.20s;
    margin-top: 10px;
}

button:hover {
    background: #0052cc;
}

.error {
    background: #ffe4e4;
    color: #c53030;
    padding: 8px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid #ffbdbd;
}
</style>
</head>
<body>

<div class="login-container">
    <h2>🔐 Sign In</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="input-group">
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>

        <div class="input-group">
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
