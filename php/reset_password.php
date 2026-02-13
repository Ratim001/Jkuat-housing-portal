<?php
/**
 * php/reset_password.php
 * Purpose: Allow user to set a new password using a valid reset token.
 * Author: repo automation / commit: auth: implement password reset
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    $error = 'Invalid token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($password !== $password2) $error = 'Passwords do not match.';
    else {
        $stmt = $conn->prepare('SELECT applicant_id, password_reset_expires FROM applicants WHERE password_reset_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (strtotime($row['password_reset_expires']) < time()) {
                $error = 'Token expired.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u = $conn->prepare('UPDATE applicants SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE applicant_id = ?');
                $u->bind_param('ss', $hash, $row['applicant_id']);
                if ($u->execute()) {
                    logs_write('info', 'Password reset for ' . $row['applicant_id']);
                    header('Location: applicantlogin.php?msg=' . urlencode('Password reset successful. Please log in.'));
                    exit;
                } else {
                    $error = 'Failed to update password.';
                }
            }
        } else {
            $error = 'Invalid token.';
        }
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Reset Password</title></head>
<body>
<h2>Reset Password</h2>
<?php if ($error): ?><div style="color:red"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <label>New Password</label>
    <input type="password" name="password" required>
    <label>Confirm Password</label>
    <input type="password" name="password2" required>
    <button type="submit">Set New Password</button>
    </form>
</body>
</html>
<?php
/**
 * php/reset_password.php
 * Purpose: Reset password using token.
 * Author: repo automation / commit: auth: implement password reset
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$success = '';

if (!$token) {
    $error = 'Invalid token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $new = $_POST['password'] ?? '';
    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $stmt = $conn->prepare('SELECT applicant_id, password_reset_expires FROM applicants WHERE password_reset_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $expires = strtotime($row['password_reset_expires']);
            if ($expires < time()) {
                $error = 'Token expired.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $u = $conn->prepare('UPDATE applicants SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE applicant_id = ?');
                $u->bind_param('ss', $hash, $row['applicant_id']);
                if ($u->execute()) {
                    $success = 'Password updated. You may now log in.';
                } else {
                    $error = 'Could not update password.';
                }
            }
        } else {
            $error = 'Invalid token.';
        }
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Reset password</title></head><body>
<h2>Reset password</h2>
<?php if ($error): ?><div style="color:red"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!$success): ?>
<form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <label>New password</label>
    <input type="password" name="password" required>
    <button type="submit">Set new password</button>
</form>
<?php endif; ?>
</body></html>
