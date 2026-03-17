<?php
/**
 * php/request_password_reset.php
 * Purpose: Allow applicants to request a password reset link.
 * Behaviour: If logged in, uses registered email from profile. Otherwise, asks for email.
 *            Generates a token and expiry, sends email via send_email(),
 *            and shows a generic success message regardless of email existence.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = '';
$success = '';
$is_logged_in = isset($_SESSION['applicant_id']);
$logged_in_applicant = null;

// If logged in, fetch applicant's registered email
if ($is_logged_in) {
    $applicant_id = $_SESSION['applicant_id'];
    $stmt = $conn->prepare('SELECT applicant_id, name, email FROM applicants WHERE applicant_id = ? LIMIT 1');
    $stmt->bind_param('s', $applicant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $logged_in_applicant = $res->fetch_assoc();
}

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
    logs_write('info', 'Password reset request received. Is logged in: ' . ($is_logged_in ? 'yes' : 'no'));
    
    // Determine which email to use
    $email = null;
    $applicant_id = null;
    $name = null;
    
    if ($is_logged_in && $logged_in_applicant) {
        // Use logged-in applicant's registered email
        $email = $logged_in_applicant['email'];
        $applicant_id = $logged_in_applicant['applicant_id'];
        $name = $logged_in_applicant['name'];
        logs_write('info', "Using logged-in applicant email: $email");
    } else {
        // Use email from form input
        $email = trim($_POST['email'] ?? '');
        logs_write('info', "Processing form email: $email");
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email.';
            logs_write('warning', "Invalid email format: $email");
        } else {
            // Look up applicant by email
            $stmt = $conn->prepare('SELECT applicant_id, name FROM applicants WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                $applicant_id = $row['applicant_id'];
                $name = $row['name'];
                logs_write('info', "Found applicant: $applicant_id for email: $email");
            } else {
                // Still behave as if email exists to avoid user enumeration
                logs_write('info', "No applicant found for email: $email (for security, not showing error)");
                $ipAttempts[] = $now;
                $attempts[$ip] = $ipAttempts;
                file_put_contents($attemptsFile, json_encode($attempts));
                $success = 'If an account with that email exists, a password reset link has been sent.';
                $email = null;
            }
        }
    }

    if (!$error && $email && $applicant_id && $name) {
        logs_write('info', "Attempting to send reset email to: $email");
        
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $u = $conn->prepare('UPDATE applicants SET password_reset_token = ?, password_reset_expires = ? WHERE applicant_id = ?');
        $u->bind_param('sss', $token, $expires, $applicant_id);

        if ($u->execute()) {
            logs_write('info', "Token saved for applicant: $applicant_id");
            
            $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
            $link = $appUrl . '/php/reset_password.php?token=' . urlencode($token);
            logs_write('info', "Reset link: $link");

            // Build email content using helper
            $emailContent = build_password_reset_email($name, $link);
            // Prefer HTML version; insert internal notification and send email
            logs_write('info', "Calling notify_and_email for: $email");
            if (function_exists('notify_and_email')) {
                notify_and_email($conn, 'applicant', $applicant_id, $email, $emailContent['subject'], $emailContent['html'], 'Password Reset');
                $sent = true;
            } else {
                $sent = send_email($email, $emailContent['subject'], $emailContent['html'], true);
            }
            logs_write('info', "notify/send returned: " . ($sent ? 'true' : 'false'));
            
            if (!$sent) {
                logs_write('error', 'Failed to send password reset email to ' . $email);
            }

            // Record successful attempt
            $ipAttempts[] = $now;
            $attempts[$ip] = $ipAttempts;
            file_put_contents($attemptsFile, json_encode($attempts));
            
            if (!$success) {
                $success = 'If an account with that email exists, a password reset link has been sent.';
            }
        } else {
            logs_write('error', 'Failed to set password reset token for ' . $applicant_id . ': ' . $conn->error);
        }
    } elseif (!$error) {
        logs_write('warning', "Cannot send email - missing data. email=$email, applicant_id=$applicant_id, name=$name");
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
        
        <?php if ($is_logged_in && $logged_in_applicant): ?>
            <p>A password reset link will be sent to your registered email address.</p>
        <?php else: ?>
            <p>Enter your registered email to receive a reset link.</p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p style="color: red; text-align:center; max-width:350px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color: green; text-align:center; max-width:350px;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="post" action="request_password_reset.php">
            <?php if ($is_logged_in && $logged_in_applicant): ?>
                <div style="margin-bottom: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 4px;">
                    <label style="font-weight: bold;">Your registered email:</label>
                    <p style="margin: 5px 0 0 0;"><?= htmlspecialchars($logged_in_applicant['email']) ?></p>
                </div>
            <?php else: ?>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            <?php endif; ?>

            <button type="submit">Send Reset Link</button>
        </form>
        
        <?php if (!$is_logged_in): ?>
            <p style="text-align: center; margin-top: 20px; font-size: 14px;">
                Remember your password? <a href="applicantlogin.php">Log in</a>
            </p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
