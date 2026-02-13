<?php
/**
 * php/reset_password.php
 * Purpose: Allow an applicant to set a new password using a valid reset token.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (!$token) {
    $error = 'Invalid or missing token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT applicant_id, password_reset_expires FROM applicants WHERE password_reset_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $expiresTs = strtotime($row['password_reset_expires'] ?? '');
            if (!$expiresTs || $expiresTs < time()) {
                $error = 'This reset link has expired. Please request a new one.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u = $conn->prepare('UPDATE applicants SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE applicant_id = ?');
                $u->bind_param('ss', $hash, $row['applicant_id']);

                if ($u->execute()) {
                    logs_write('info', 'Password reset for applicant ' . $row['applicant_id']);
                    $success = 'Password updated successfully. You may now log in.';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            }
        } else {
            $error = 'Invalid token.';
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JKUAT Staff Housing Portal - Reset Password</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <h1>TECHNOLOGY FOR DEVELOPMENT</h1>
    </div>

    <div class="right-panel">
        <img src="../images/logo.png" alt="JKUAT Logo" class="login-logo">
        <h2>Reset your password</h2>
        <p>Please choose a strong password (minimum 8 characters).</p>

        <?php if ($error): ?>
            <p style="color: red; text-align:center; max-width:350px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color: green; text-align:center; max-width:350px;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post" action="reset_password.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required>

            <label for="password2">Confirm Password</label>
            <input type="password" id="password2" name="password2" required>

            <button type="submit">Set New Password</button>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
