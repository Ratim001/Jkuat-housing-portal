<?php
/**
 * php/request_password_reset.php
 * Purpose: Allow applicants to request a password reset link.
 * Behaviour: Generates a token and expiry, sends email via send_email(),
 *            and shows a generic success message regardless of email existence.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';

$error = '';
$success = '';

// Simple rate-limit: max 5 requests per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attemptsFile = __DIR__ . '/../logs/password_reset_attempts.log';
$attempts = [];
if (file_exists($attemptsFile)) {
    $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
}
$now = time();
$ipAttempts = array_filter($attempts[$ip] ?? [], function ($t) use ($now) {
    return $t > $now - 3600;
});
if (count($ipAttempts) >= 5) {
    $error = 'Too many password reset requests from your IP. Try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        // Look up applicant by email
        $stmt = $conn->prepare('SELECT applicant_id, name FROM applicants WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $u = $conn->prepare('UPDATE applicants SET password_reset_token = ?, password_reset_expires = ? WHERE applicant_id = ?');
            $u->bind_param('sss', $token, $expires, $row['applicant_id']);

            if ($u->execute()) {
                $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                $link = $appUrl . '/php/reset_password.php?token=' . urlencode($token);

                // Build email content using helper
                $emailContent = build_password_reset_email($row['name'], $link);
                // Prefer HTML version; send_email will fall back to logging if SMTP/mail() not configured
                send_email($email, $emailContent['subject'], $emailContent['html'], true);

                // Record successful attempt
                $ipAttempts[] = $now;
                $attempts[$ip] = $ipAttempts;
                file_put_contents($attemptsFile, json_encode($attempts));
            } else {
                logs_write('error', 'Failed to set password reset token for ' . $row['applicant_id'] . ': ' . $conn->error);
            }
        } else {
            // Still behave as if email exists to avoid user enumeration
            $ipAttempts[] = $now;
            $attempts[$ip] = $ipAttempts;
            file_put_contents($attemptsFile, json_encode($attempts));
        }

        $success = 'If an account with that email exists, a password reset link has been sent.';
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JKUAT Staff Housing Portal - Password Reset</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <h1>TECHNOLOGY FOR DEVELOPMENT</h1>
    </div>

    <div class="right-panel">
        <img src="../images/logo.png" alt="JKUAT Logo" class="login-logo">
        <h2>Forgot your password?</h2>
        <p>Enter your registered email to receive a reset link.</p>

        <?php if ($error): ?>
            <p style="color: red; text-align:center; max-width:350px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color: green; text-align:center; max-width:350px;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="post" action="request_password_reset.php">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required>

            <button type="submit">Send Reset Link</button>
        </form>
    </div>
</div>

</body>
</html>
