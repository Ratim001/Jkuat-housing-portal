<?php
/**
 * php/request_password_reset.php
 * Purpose: Allow users to request a password reset. Generates secure token and expiry, emails link or logs it.
 * Author: repo automation / commit: auth: implement password reset
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        // Simple session-based rate limit per email
        if (!isset($_SESSION['pw_reset_attempts'])) $_SESSION['pw_reset_attempts'] = [];
        $now = time();
        $_SESSION['pw_reset_attempts'][$email] = array_filter($_SESSION['pw_reset_attempts'][$email] ?? [], function($ts) use ($now) { return $ts > $now - 3600; });
        if (count($_SESSION['pw_reset_attempts'][$email]) >= 5) {
            $error = 'Too many requests. Try again later.';
        } else {
            $_SESSION['pw_reset_attempts'][$email][] = $now;

            $stmt = $conn->prepare('SELECT applicant_id, name FROM applicants WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                $token = bin2hex(random_bytes(24));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $u = $conn->prepare('UPDATE applicants SET password_reset_token = ?, password_reset_expires = ? WHERE applicant_id = ?');
                $u->bind_param('sss', $token, $expires, $user['applicant_id']);
                $u->execute();

                $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                $resetLink = $appUrl . '/php/reset_password.php?token=' . urlencode($token);
                $subject = 'Password reset request';
                $body = "Hello {$user['name']}\n\nTo reset your password, click the link below (valid 1 hour):\n$resetLink\n\nIf you did not request this, ignore this message.";

                if (getenv('SMTP_HOST')) {
                    // Minimal mail() fallback; recommend PHPMailer in production
                    $headers = 'From: no-reply@' . parse_url($appUrl, PHP_URL_HOST) . "\r\n";
                    @mail($email, $subject, $body, $headers);
                } else {
                    $emailLogDir = __DIR__ . '/../logs';
                    if (!is_dir($emailLogDir)) @mkdir($emailLogDir, 0755, true);
                    $logFile = $emailLogDir . '/emails.log';
                    $logLine = date('c') . " RESET_EMAIL to $email -- Link: $resetLink\n";
                    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
                }
            }

            // Always show generic success message
            $success = 'If an account with that email exists, a password reset link has been sent.';
        }
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Request Password Reset</title></head>
<body>
<h2>Request Password Reset</h2>
<?php if ($error): ?><div style="color:red"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="post">
    <label>Email</label>
    <input type="email" name="email" required>
    <button type="submit">Send Reset Link</button>
</form>
</body>
</html>
<?php
/**
 * php/request_password_reset.php
 * Purpose: Request a password reset link for an applicant.
 * Author: repo automation / commit: auth: implement password reset request
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

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
$ipAttempts = array_filter($attempts[$ip] ?? [], function($t) use ($now) { return $t > $now - 3600; });
if (count($ipAttempts) >= 5) {
    $error = 'Too many password reset requests from your IP. Try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        // find applicant
        $s = $conn->prepare('SELECT applicant_id, name FROM applicants WHERE email = ? LIMIT 1');
        $s->bind_param('s', $email);
        $s->execute();
        $r = $s->get_result();
        if ($row = $r->fetch_assoc()) {
            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $u = $conn->prepare('UPDATE applicants SET password_reset_token = ?, password_reset_expires = ? WHERE applicant_id = ?');
            $u->bind_param('sss', $token, $expires, $row['applicant_id']);
            if ($u->execute()) {
                $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                $link = $appUrl . '/php/reset_password.php?token=' . urlencode($token);
                $subject = 'Password reset request';
                $tpl = __DIR__ . '/../templates/emails/reset_password.html';
                if (file_exists($tpl)) {
                    $html = file_get_contents($tpl);
                    $bodyHtml = str_replace(['{{name}}','{{link}}'], [htmlspecialchars($row['name']), $link], $html);
                    send_email($email, $subject, $bodyHtml, true);
                } else {
                    $body = "Hello {$row['name']},\n\nClick to reset your password: $link\n\nThis link expires in 1 hour.";
                    send_email($email, $subject, $body, false);
                }
                $success = 'If the email exists, a password reset link has been sent.';

                // record attempt
                $ipAttempts[] = $now;
                $attempts[$ip] = $ipAttempts;
                file_put_contents($attemptsFile, json_encode($attempts));
            } else {
                $error = 'Could not set reset token. Try again.';
            }
        } else {
            // still respond success to avoid enumeration
            $success = 'If the email exists, a password reset link has been sent.';
            $ipAttempts[] = $now;
            $attempts[$ip] = $ipAttempts;
            file_put_contents($attemptsFile, json_encode($attempts));
        }
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Password reset</title></head><body>
<h2>Password reset</h2>
<?php if ($error): ?><div style="color:red"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="post">
    <label>Email</label>
    <input type="email" name="email" required>
    <button type="submit">Send reset link</button>
</form>
</body></html>
