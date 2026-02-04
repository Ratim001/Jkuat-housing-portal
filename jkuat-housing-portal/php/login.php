<?php
session_start();
include '../includes/db.php';  // ✅ Make sure this path is correct

// Show PHP errors (for debugging—remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'ICT Admin') {
                    header('Location: ictdashboard.php');
                    exit;
                } elseif ($user['role'] == 'CS Admin') {
                    header('Location: csdashboard.php');  // 🔑 You'll need to create this page
                    exit;
                } else {
                    $error = 'Access denied. Unknown role.';
                }
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'User not found.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JKUAT Staff Housing Portal - Login</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <h1>TECHNOLOGY FOR DEVELOPMENT</h1>
    </div>

    <div class="right-panel">
        <img src="../images/logo.png" alt="JKUAT Logo" class="login-logo">
        <h2>Hi, welcome back</h2>
        <p>Please fill in your details to log in</p>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>

            <div class="options">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit">Sign In</button>
        </form>

        
        <footer>&copy; 2025 - ABNO Softwares International</footer>
    </div>
</div>

</body>
</html>
