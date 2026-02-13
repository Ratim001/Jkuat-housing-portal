<?php
/**
 * php/edit_applicant.php
 * Purpose: Admin-only page to edit applicant records (name, email, contact, next_of_kin, status, username)
 * Author: repo automation / commit: admin: add edit applicant
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$error = '';
$success = '';
$id = $_GET['id'] ?? ($_POST['applicant_id'] ?? '');
if (!$id) {
    header('Location: manage_applicants.php');
    exit;
}

// Fetch applicant
$stmt = $conn->prepare('SELECT * FROM applicants WHERE applicant_id = ? LIMIT 1');
$stmt->bind_param('s', $id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
if (!$app) {
    header('Location: manage_applicants.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
    $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Basic validation
    if (strlen($name) < 2) $error = 'Name too short';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email';
    elseif (!preg_match('/^[0-9+()\\s\\-]{6,25}$/', $contact)) $error = 'Invalid contact';
    else {
        $u = $conn->prepare('UPDATE applicants SET name = ?, email = ?, contact = ?, next_of_kin_name = ?, next_of_kin_contact = ?, username = ?, status = ? WHERE applicant_id = ?');
        $u->bind_param('ssssssss', $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $username, $status, $id);
        if ($u->execute()) {
            $success = 'Applicant updated.';
            // refresh
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Update failed: ' . $conn->error;
        }
    }
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Edit Applicant</title></head><body>
<h2>Edit Applicant <?= htmlspecialchars($app['applicant_id']) ?></h2>
<?php if ($error): ?><div style="color:red"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="applicant_id" value="<?= htmlspecialchars($app['applicant_id']) ?>">
    <label>Username</label>
    <input type="text" name="username" value="<?= htmlspecialchars($app['username'] ?? '') ?>">
    <label>Full name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($app['name'] ?? '') ?>">
    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($app['email'] ?? '') ?>">
    <label>Contact</label>
    <input type="text" name="contact" value="<?= htmlspecialchars($app['contact'] ?? '') ?>">
    <label>Next of kin</label>
    <input type="text" name="next_of_kin_name" value="<?= htmlspecialchars($app['next_of_kin_name'] ?? '') ?>">
    <label>NOK Contact</label>
    <input type="text" name="next_of_kin_contact" value="<?= htmlspecialchars($app['next_of_kin_contact'] ?? '') ?>">
    <label>Status</label>
    <input type="text" name="status" value="<?= htmlspecialchars($app['status'] ?? '') ?>">
    <button type="submit">Save</button>
    <a href="manage_applicants.php">Back</a>
</form>
</body></html>
