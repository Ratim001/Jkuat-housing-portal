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
    $is_disabled = (trim($_POST['is_disabled'] ?? 'no') === 'yes') ? 1 : 0;
    $disability_details = trim($_POST['disability_details'] ?? '');

    // Basic validation
    if (strlen($name) < 2) $error = 'Name too short';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email';
    elseif (!preg_match('/^[0-9+()\\s\\-]{6,25}$/', $contact)) $error = 'Invalid contact';
    else {
        // Check if disability columns exist
        require_once __DIR__ . '/../includes/helpers.php';
        $hasDisabilityFields = column_exists_db($conn, 'applicants', 'is_disabled') && column_exists_db($conn, 'applicants', 'disability_details');
        
        if ($hasDisabilityFields) {
            $u = $conn->prepare('UPDATE applicants SET name = ?, email = ?, contact = ?, next_of_kin_name = ?, next_of_kin_contact = ?, username = ?, status = ?, is_disabled = ?, disability_details = ? WHERE applicant_id = ?');
            $u->bind_param('ssssssssss', $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $username, $status, $is_disabled, $disability_details, $id);
        } else {
            $u = $conn->prepare('UPDATE applicants SET name = ?, email = ?, contact = ?, next_of_kin_name = ?, next_of_kin_contact = ?, username = ?, status = ? WHERE applicant_id = ?');
            $u->bind_param('ssssssss', $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $username, $status, $id);
        }
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
    <label>Next of Kin Name</label>
    <input type="text" name="next_of_kin_name" value="<?= htmlspecialchars($app['next_of_kin_name'] ?? '') ?>">
    <label>Next of Kin Contact</label>
    <input type="text" name="next_of_kin_contact" value="<?= htmlspecialchars($app['next_of_kin_contact'] ?? '') ?>">
    <label>Status</label>
    <input type="text" name="status" value="<?= htmlspecialchars($app['status'] ?? '') ?>">
    <label style="margin-top: 16px; font-weight: 600;">Has Disability?</label>
    <div style="margin-top: 8px; margin-bottom: 16px;">
        <label style="font-weight: 600; margin-right: 20px;">
            <input type="radio" name="is_disabled" value="no" <?= (empty($app['is_disabled']) || intval($app['is_disabled']) !== 1) ? 'checked' : '' ?>> 
            No
        </label>
        <label style="font-weight: 600;">
            <input type="radio" name="is_disabled" value="yes" <?= (isset($app['is_disabled']) && intval($app['is_disabled']) === 1) ? 'checked' : '' ?>> 
            Yes
        </label>
    </div>
    <div id="disability_details_wrap" style="margin-bottom: 16px; <?= (isset($app['is_disabled']) && intval($app['is_disabled']) === 1) ? '' : 'display:none;' ?>">
        <label for="disability_details" style="font-weight: 600;">Disability Details</label>
        <textarea id="disability_details" name="disability_details" rows="4" placeholder="Describe any disability and support needs" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; margin-top: 8px;"><?= htmlspecialchars($app['disability_details'] ?? '') ?></textarea>
    </div>
    <button type="submit">Save</button>
    <a href="manage_applicants.php" class="btn" style="background:#006400;color:#fff;border:1px solid #006400;padding:6px 8px;border-radius:4px;">Back</a>
</form>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var disabilityRadios = document.querySelectorAll('input[name="is_disabled"]');
    var detailsWrap = document.getElementById('disability_details_wrap');
    
    disabilityRadios.forEach(function(radio){
        radio.addEventListener('change', function(){
            if(this.value === 'yes'){
                detailsWrap.style.display = 'block';
            } else {
                detailsWrap.style.display = 'none';
            }
        });
    });
});
</script>
</body></html>
