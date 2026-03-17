<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
require_once '../includes/validation.php';

// Allow guest access for registration. If a user is logged in, load their applicant data.
$applicant_id = $_SESSION['applicant_id'] ?? null;
$error = '';
$success = '';
$just_registered = false;

// If redirected here after successful registration, show a friendly message
if (isset($_GET['registered'])) {
    $success = 'Registration successful. You can now return to the login page.';
    $just_registered = true;
}

// Fetch current profile information only for authenticated applicants
if ($applicant_id) {
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("s", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applicant = $result->fetch_assoc();

    // Check if profile is already complete
    $profile_incomplete = empty($applicant['name']) || empty($applicant['email']) || empty($applicant['contact']);
} else {
    $applicant = [];
    $profile_incomplete = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If this page is being used to register a new applicant (no session applicant id),
    // handle registration here so the registration form can post to this page.
    if (empty($applicant_id) && isset($_POST['action']) && $_POST['action'] === 'register') {
        // Collect and validate inputs
        $pf_number = trim($_POST['pf_number'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $passwordRaw = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
        $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');
        $role = $_POST['role'] ?? 'applicant';
        $house_no = trim($_POST['house_no'] ?? '');

        $validationErrors = [];
        if (!validate_name($name)) $validationErrors[] = 'Name must be at least 2 characters.';
        if (!validate_email($email)) $validationErrors[] = 'Invalid email address.';
        if (!validate_phone($contact)) $validationErrors[] = 'Invalid contact number.';
        if (!validate_phone($next_of_kin_contact)) $validationErrors[] = 'Invalid next-of-kin contact.';
        if (!validate_username($username)) $validationErrors[] = 'Username must be at least 3 characters and contain only letters, numbers, underscore, dot or hyphen.';
        if (!validate_password($passwordRaw)) $validationErrors[] = 'Password must be at least 8 characters.';
        if ($passwordRaw !== $passwordConfirm) $validationErrors[] = 'Password and confirmation do not match.';

        if (count($validationErrors) > 0) {
            $error = implode(' ', $validationErrors);
        } else {
            // uniqueness check
            $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE pf_no = ? OR username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("sss", $pf_number, $username, $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $error = "PF Number, Username, or Email already exists.";
            } else {
                // generate applicant id
                $query = $conn->query("SELECT applicant_id FROM applicants ORDER BY applicant_id DESC LIMIT 1");
                if ($row = $query->fetch_assoc()) {
                    $lastIdNum = (int)substr($row['applicant_id'], 1);
                    $newIdNum = $lastIdNum + 1;
                } else {
                    $newIdNum = 1;
                }
                $newApplicantId = 'A' . str_pad($newIdNum, 3, '0', STR_PAD_LEFT);

                // Handle optional photo upload
                $photoDbPath = null;
                if (!empty($_FILES['photo']['name'])) {
                    $allowed = ['image/jpeg','image/png','image/gif'];
                    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK && in_array($_FILES['photo']['type'], $allowed)) {
                        $uploadsDir = __DIR__ . '/../images/uploads';
                        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $filename = $newApplicantId . '_' . time() . '.' . $ext;
                        $target = $uploadsDir . '/' . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                            $photoDbPath = 'images/uploads/' . $filename;
                        }
                    }
                }

                $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
                $emailToken = bin2hex(random_bytes(24));
                $status = 'Pending';
                $is_disabled = (trim($_POST['is_disabled'] ?? 'no') === 'yes') ? 1 : 0;
                $disability_details = trim($_POST['disability_details'] ?? '');

                require_once __DIR__ . '/../includes/helpers.php';
                // Check which optional columns exist so we don't try to insert into missing columns
                $hasDisabilityDetails = column_exists_db($conn, 'applicants', 'disability_details');
                $hasIsDisabled = column_exists_db($conn, 'applicants', 'is_disabled');

                // Base columns that always exist in our expected schema
                $cols = ['applicant_id','pf_no','username','password','name','email','contact','next_of_kin_name','next_of_kin_contact','role','photo'];
                $placeholders = array_fill(0, count($cols), '?');
                $types = str_repeat('s', count($cols));
                $values = [$newApplicantId, $pf_number, $username, $passwordHash, $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $role, $photoDbPath];

                // is_email_verified stored as literal 0
                $cols[] = 'is_email_verified';
                $placeholders[] = '0';

                // token and status
                $cols[] = 'email_verification_token';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $emailToken;

                $cols[] = 'status';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $status;

                if ($hasIsDisabled) {
                    $cols[] = 'is_disabled';
                    $placeholders[] = '?';
                    $types .= 'i';
                    $values[] = $is_disabled;
                }

                if ($hasDisabilityDetails) {
                    $cols[] = 'disability_details';
                    $placeholders[] = '?';
                    $types .= 's';
                    $values[] = $disability_details;
                }

                $sql = "INSERT INTO applicants (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $insert = $conn->prepare($sql);
                if ($insert && count($values) > 0) {
                    $bind_names = array_merge([$types], $values);
                    $refs = [];
                    foreach ($bind_names as $i => $v) $refs[$i] = &$bind_names[$i];
                    call_user_func_array([$insert, 'bind_param'], $refs);
                }
                    if ($insert->execute()) {
                        // If registered as an existing tenant, create tenant record
                        if ($role === 'tenant') {
                            $tq = $conn->query("SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
                            $nextTenantId = 'T001';
                            if ($tq && $tl = $tq->fetch_assoc()) {
                                $num = (int)substr($tl['tenant_id'], 1) + 1;
                                $nextTenantId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
                            }
                            $today = date('Y-m-d');
                            $insT = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date, status) VALUES (?, ?, ?, ?, ?)");
                            if ($insT) {
                                $st = 'Active';
                                $insT->bind_param('sssss', $nextTenantId, $newApplicantId, $house_no, $today, $st);
                                $insT->execute();
                                if (!empty($house_no)) {
                                    $u = $conn->prepare("UPDATE houses SET status = 'Occupied' WHERE house_no = ? LIMIT 1");
                                    if ($u) { $u->bind_param('s', $house_no); $u->execute(); }
                                }
                                $conn->query("UPDATE applicants SET role = 'tenant' WHERE applicant_id = '" . $conn->real_escape_string($newApplicantId) . "'");
                            }
                        }

                        // Send verification email (if SMTP configured) before redirecting
                        try {
                            require_once __DIR__ . '/../includes/email.php';
                            require_once __DIR__ . '/../includes/helpers.php';
                            $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                            $verifyLink = $appUrl . '/php/verify_email.php?token=' . urlencode($emailToken);
                            $subject = 'Verify your email';
                            $tpl = __DIR__ . '/../templates/emails/verify_email.html';
                            if (file_exists($tpl)) {
                                $html = file_get_contents($tpl);
                                $bodyHtml = str_replace(['{{name}}','{{link}}'], [htmlspecialchars($name), $verifyLink], $html);
                                // Log the verification link for local/dev environments so developers can access it when email is not delivered
                                logs_write('info', 'Verification link for ' . $newApplicantId . ': ' . $verifyLink);
                                if (function_exists('notify_and_email')) {
                                    $sent = notify_and_email($conn, 'applicant', $newApplicantId, $email, $subject, $bodyHtml, 'Verify Email');
                                } else {
                                    $sent = send_email($email, $subject, $bodyHtml, true);
                                }
                                logs_write('info', 'Verification email sent status (applicant_profile template): ' . ($sent ? 'sent' : 'failed'));
                            } else {
                                $body = "Hello $name,\n\nPlease verify your email by clicking the link: $verifyLink\n\nIf you did not register, ignore this message.";
                                $bodyHtml = nl2br(htmlspecialchars($body));
                                // Log the verification link for local/dev environments so developers can access it when email is not delivered
                                logs_write('info', 'Verification link for ' . $newApplicantId . ': ' . $verifyLink);
                                if (function_exists('notify_and_email')) {
                                    $sent = notify_and_email($conn, 'applicant', $newApplicantId, $email, $subject, $bodyHtml, 'Verify Email');
                                } else {
                                    $sent = send_email($email, $subject, $body, false);
                                }
                                logs_write('info', 'Verification email sent status (applicant_profile fallback): ' . ($sent ? 'sent' : 'failed'));
                            }
                        } catch (Exception $e) {
                            logs_write('error', 'Failed to send verification email after registration: ' . $e->getMessage());
                        }

                        // Redirect user to this page with a success flag (do not auto-login)
                        header('Location: applicant_profile.php?registered=1');
                        exit;
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $is_disabled = (trim($_POST['is_disabled'] ?? 'no') === 'yes') ? 1 : 0;
    $disability_details = trim($_POST['disability_details'] ?? '');
    $role = trim($_POST['role'] ?? ($applicant['role'] ?? 'applicant'));
    $house_no = trim($_POST['house_no'] ?? ($applicant['house_no'] ?? ''));
    $next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
    $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');

    // Validation
    if (!validate_name($name) || !validate_email($email) || !validate_phone($contact) || !validate_phone($next_of_kin_contact)) {
        $error = 'Please enter valid values for all required fields.';
    } elseif (!empty($passwordRaw) && !validate_password($passwordRaw)) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!empty($passwordRaw) && $passwordRaw !== $passwordConfirm) {
        $error = 'Password and confirmation do not match.';
    } else {
        // Check if email already exists for another applicant
        $check_stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE email = ? AND applicant_id != ?");
        $check_stmt->bind_param("ss", $email, $applicant_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'Email address is already in use by another applicant.';
        } else {
            // Build update SQL dynamically based on which columns actually exist in the DB
            $baseCols = ['name','email','contact','next_of_kin_name','next_of_kin_contact'];
            $baseVals = [$name, $email, $contact, $next_of_kin_name, $next_of_kin_contact];
            // Include is_disabled only if the column exists in the DB
            if (column_exists_db($conn, 'applicants', 'is_disabled')) {
                $baseCols[] = 'is_disabled';
                $baseVals[] = $is_disabled;
            }

            // If the user provided a new password during profile completion, include it (hashed)
            if (!empty($passwordRaw)) {
                $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
                $baseCols[] = 'password';
                $baseVals[] = $passwordHash;
            }

            $colCheckQ = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME IN ('disability_details','role')";
            $hasCols = [];
            if ($colRes = mysqli_query($conn, $colCheckQ)) {
                while ($r = mysqli_fetch_assoc($colRes)) $hasCols[$r['COLUMN_NAME']] = true;
            }

            if (!empty($hasCols['disability_details'])) {
                $baseCols[] = 'disability_details';
                $baseVals[] = $disability_details;
            }
            if (!empty($hasCols['role'])) {
                $baseCols[] = 'role';
                $baseVals[] = $role;
            }

            $setParts = array_map(function($c){ return "$c = ?"; }, $baseCols);
            $sql = "UPDATE applicants SET " . implode(', ', $setParts) . " WHERE applicant_id = ?";
            $stmtupd = $conn->prepare($sql);
            if ($stmtupd) {
                // bind params dynamically
                $types = str_repeat('s', count($baseVals) + 1);
                $params = array_merge($baseVals, [$applicant_id]);
                $bind_names = array_merge([$types], $params);
                $refs = [];
                foreach ($bind_names as $i => $v) $refs[$i] = &$bind_names[$i];
                call_user_func_array([$stmtupd, 'bind_param'], $refs);
            }

            // Handle optional photo upload separately
            $uploadedPhotoPath = null;
            if (!empty($_FILES['photo']['name'])) {
                $allowed = ['image/jpeg','image/png','image/gif'];
                if ($_FILES['photo']['error'] === UPLOAD_ERR_OK && in_array($_FILES['photo']['type'], $allowed)) {
                    $uploadsDir = __DIR__ . '/../images/uploads';
                    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $filename = $applicant_id . '_' . time() . '.' . $ext;
                    $target = $uploadsDir . '/' . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                        $uploadedPhotoPath = 'images/uploads/' . $filename;
                    }
                }
            }

            if ($stmtupd && $stmtupd->execute()) {
                    // If user selected to be an existing tenant, ensure they appear in tenants table
                    if ($role === 'tenant') {
                        // Check if applicant is already a tenant
                        $tchk = $conn->prepare("SELECT tenant_id FROM tenants WHERE applicant_id = ? LIMIT 1");
                        if ($tchk) {
                            $tchk->bind_param('s', $applicant_id);
                            $tchk->execute();
                            $tres = $tchk->get_result()->fetch_assoc();
                        } else {
                            $tres = null;
                        }

                        if (!$tres) {
                            // generate next tenant id
                            $lastTenant = mysqli_query($conn, "SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
                            $nextId = 'T001';
                            if ($t = mysqli_fetch_assoc($lastTenant)) {
                                $num = (int)substr($t['tenant_id'], 1) + 1;
                                $nextId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
                            }
                            $today = date('Y-m-d');
                            // insert tenant record if house_no provided
                            $ins = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date) VALUES (?, ?, ?, ?)");
                            if ($ins) {
                                $ins->bind_param('ssss', $nextId, $applicant_id, $house_no, $today);
                                $ins->execute();
                            }

                            if (!empty($house_no)) {
                                $conn->query("UPDATE houses SET status = 'Occupied' WHERE house_no = '" . $conn->real_escape_string($house_no) . "'");
                            }

                            // mark applicant as tenant role/status
                            $conn->query("UPDATE applicants SET status = 'Tenant', role = 'tenant' WHERE applicant_id = '" . $conn->real_escape_string($applicant_id) . "'");
                        }
                    }
                if ($uploadedPhotoPath) {
                    $pstmt = $conn->prepare("UPDATE applicants SET photo = ? WHERE applicant_id = ?");
                    $pstmt->bind_param("ss", $uploadedPhotoPath, $applicant_id);
                    $pstmt->execute();
                }
                $success = 'Profile updated successfully!';
                // If this profile completion was performed by a newly-registered user,
                // clear the temporary session and redirect them back to the login page
                // so they can sign in normally.
                if (isset($_GET['registered']) || (!empty($_SESSION['profile_incomplete']) && $_SESSION['profile_incomplete'] === true)) {
                    // clear registration-related session data
                    unset($_SESSION['applicant_id'], $_SESSION['username'], $_SESSION['profile_incomplete']);
                    header('Location: applicantlogin.php?registered=1');
                    exit;
                }
                // Refresh applicant data
                $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
                $stmt->bind_param("s", $applicant_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $applicant = $result->fetch_assoc();
                $profile_incomplete = false;
            } else {
                $error = 'An error occurred while updating your profile. Please try again.';
            }
        }
    }
}

// Redirect to applicants page if coming back
$redirect_to = $_GET['redirect'] ?? 'applicants.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Profile | JKUAT Staff Housing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { 
            height: 100%; 
            font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif;
            background-color: #f4f4f4;
        }
        
        :root { --primary: #006400; --accent: #0b5ed7; --muted:#666; }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .left-panel {
            flex-basis: 50%;
            background: url('../images/jkuat-bg.jpg') no-repeat center center;
            background-size: cover;
            background-color: #004225;
        }

        .right-panel {
            flex-basis: 50%;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 16px;
            overflow: auto; /* allow scrolling when content is taller than viewport */
        }

        .card {
            width: 100%;
            max-width: 680px;
            background: white;
            display: flex;
            flex-direction: column;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            padding: 18px;
            /* allow card content to grow and scroll when needed rather than truncating */
            overflow: auto;
        }

        /* compact form layout - two columns on wider screens */
        .compact-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 18px;
            align-items: start;
        }

        .compact-form .form-group {
            margin-bottom: 8px;
        }

        .compact-full {
            grid-column: 1 / -1;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header img {
            width: 84px;
            margin-bottom: 12px;
        }

        .form-header h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif;
            font-size: 13px;
            transition: border-color 0.18s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 6px rgba(11,94,215,0.12);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .required-note {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-submit {
            background-color: var(--primary);
            color: white;
        }

        .btn-submit:hover {
            background-color: #0849a8;
        }

        .btn-cancel {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-cancel:hover {
            background-color: #d0d0d0;
        }

        .info-box {
            background-color: #fbfff9;
            border-left: 4px solid var(--primary);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #333;
        }

        .info-box strong {
            color: var(--primary);
        }

        .complete-message {
            background: linear-gradient(90deg, rgba(11,94,215,0.06), rgba(0,100,0,0.02));
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 14px;
            border: 1px solid rgba(11,94,215,0.08);
            color: #0b5ed7;
            font-weight: 600;
            text-align: center;
        }

        .profile-status {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
            color: #999;
        }

        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                padding: 10px;
            }

            .card {
                padding: 16px;
            }

            .form-row,
            .compact-form {
                grid-template-columns: 1fr;
            }
        }

        /* Small accessibility helper: if the viewport is especially short, switch form into stepper-like experience */
        @media (max-height: 640px) {
            .compact-form { grid-auto-rows: min-content; }
            /* Add spacing so users can scroll comfortably */
            .card { padding-bottom: 32px; }
        }
    </style>
    <style>
        /* Floating save button shown on wider screens */
        .floating-save{position:fixed;right:18px;top:80px;background:var(--primary);color:#fff;padding:12px 14px;border-radius:8px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,0.18);z-index:9999;display:none;border:none;cursor:pointer}
        @media(min-width:992px){
            .floating-save{display:block}
            /* hide form-level submit button when floating button is available */
            .compact-form .button-group .btn-submit{display:none}
        }
        @media(max-width:991px){
            .floating-save{display:none}
        }
    </style>
</head>
<body>

<div class="container">
    <button id="floatingSave" class="floating-save" type="button">Save Profile</button>
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="card">
            <div style="margin-bottom:12px;"><button onclick="history.back();" style="background:#006400;color:#fff;border:1px solid #006400;padding:6px 10px;border-radius:4px;margin-right:8px;">Back</button></div>
            <div class="form-header">
                <img src="../images/2logo.png" alt="JKUAT Logo">
                <h1>Complete Your Profile</h1>
                <p>Please provide your personal details to complete your registration</p>
            </div>

            <?php if ($applicant_id && $profile_incomplete): ?>
                <div class="complete-message">Staff: please complete your profile to access housing services and notifications.</div>
            <?php endif; ?>

            <?php if (!$applicant_id): ?>
                <div class="info-box">
                    <strong>Welcome:</strong> Please register using the form below.
                </div>

                <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <?php if ($just_registered): ?>
                    <div style="text-align:center;margin-top:6px;">
                        <p style="font-weight:700;color:#155724;">Registration Successful</p>
                        <p style="margin-top:12px;color:#666;">You can now log in with your credentials.</p>
                        <div style="margin-top:12px;">
                            <a href="applicantlogin.php" class="btn btn-submit" style="text-decoration:none;padding:10px 18px;display:inline-block;background:#006400;color:#fff;border-radius:6px;">Back to Login</a>
                        </div>
                    </div>
                <?php else: ?>
                <form method="POST" action="" enctype="multipart/form-data" class="compact-form">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label>PF Number</label>
                        <input type="text" name="pf_number" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirm" required>
                    </div>
                    <div class="form-group">
                        <label>Full name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Contact</label>
                        <input type="text" name="contact" required>
                    </div>
                    <div class="form-group">
                        <label>Next of Kin Name</label>
                        <input type="text" name="next_of_kin_name" required>
                    </div>
                    <div class="form-group">
                        <label>Next of Kin Contact</label>
                        <input type="text" name="next_of_kin_contact" required>
                    </div>
                    <div class="form-group compact-full">
                        <label>Do you have a disability?</label>
                        <div style="display:flex;gap:12px;align-items:center;margin-top:6px;">
                            <label style="font-weight:600;"><input type="radio" name="is_disabled" value="no" checked> No</label>
                            <label style="font-weight:600;"><input type="radio" name="is_disabled" value="yes"> Yes</label>
                        </div>
                        <div id="reg_disability_details_wrap" style="margin-top:10px;display:none;">
                            <label style="font-weight:600;">If yes, please provide details</label>
                            <textarea name="disability_details" rows="3" placeholder="Describe any disability and support needs" style="margin-top:6px; width:100%;"></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="applicant">Applicant</option>
                            <option value="tenant">Existing Tenant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>House No (if Existing Tenant)</label>
                        <input type="text" name="house_no" placeholder="e.g. 101">
                    </div>
                    <div class="form-group">
                        <label>Photo (optional)</label>
                        <input type="file" name="photo" accept="image/*">
                    </div>
                    <div class="button-group compact-full">
                        <button type="submit" class="btn btn-submit">Register</button>
                    </div>
                </form>
                <?php endif; ?>

            <?php else: ?>
                <?php if ($profile_incomplete): ?>
                    <div class="info-box">
                        <strong>⚠️ Important:</strong> Your profile is incomplete. Please fill in all required information to proceed.
                    </div>
                <?php else: ?>
                    <div class="profile-status">Profile: <span style="color: #006400; font-weight: 600;">✓ Complete</span></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="message error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="message success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="compact-form">
                    <div class="compact-full" style="text-align:center; margin-bottom: 12px;">
                        <?php if (!empty($applicant['photo'])): ?>
                            <img src="../<?= htmlspecialchars($applicant['photo']) ?>" alt="Profile" style="width:90px;height:90px;border-radius:50%;object-fit:cover;margin-bottom:8px;">
                        <?php else: ?>
                            <img src="../images/p-icon.png" alt="Profile" style="width:90px;height:90px;border-radius:50%;margin-bottom:8px;">
                        <?php endif; ?>
                        <div style="margin-top:6px;">
                            <label style="display:block;font-weight:700;color:#006400;margin-bottom:8px;">Role</label>
                            <select name="role" style="padding:8px;border-radius:6px;border:1px solid #e6e6e6;">
                                <option value="applicant" <?= (empty($applicant['role']) || $applicant['role'] !== 'tenant') ? 'selected' : '' ?>>Applicant</option>
                                <option value="tenant" <?= (!empty($applicant['role']) && $applicant['role'] === 'tenant') ? 'selected' : '' ?>>Existing Tenant</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($applicant['name'] ?? '') ?>" required>
                        <div class="required-note">Your full legal name as it appears in official documents</div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($applicant['email'] ?? '') ?>" required>
                        <div class="required-note">We'll use this to send important notifications</div>
                    </div>

                    <div class="form-group">
                        <label for="contact">Phone Number *</label>
                        <input type="tel" id="contact" name="contact" value="<?= htmlspecialchars($applicant['contact'] ?? '') ?>" placeholder="+254..." required>
                        <div class="required-note">Include country code (e.g., +254)</div>
                    </div>

                    <div class="form-group">
                        <label for="next_of_kin_name">Next of Kin Name *</label>
                        <input type="text" id="next_of_kin_name" name="next_of_kin_name" value="<?= htmlspecialchars($applicant['next_of_kin_name'] ?? '') ?>" required>
                        <div class="required-note">Full name of your emergency contact</div>
                    </div>

                    <div class="form-group">
                        <label for="next_of_kin_contact">Next of Kin Phone Number *</label>
                        <input type="tel" id="next_of_kin_contact" name="next_of_kin_contact" value="<?= htmlspecialchars($applicant['next_of_kin_contact'] ?? '') ?>" placeholder="+254..." required>
                        <div class="required-note">Phone number of your emergency contact</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Set Password <?= ($profile_incomplete ? '*' : '(optional)') ?></label>
                        <input type="password" id="password" name="password" <?= ($profile_incomplete ? 'required' : '') ?>>
                        <div class="required-note">Choose a secure password (8+ characters).</div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm" <?= ($profile_incomplete ? 'required' : '') ?>>
                    </div>

                    <div class="form-group">
                        <label>Do you have a disability?</label>
                        <div style="display:flex;gap:12px;align-items:center;margin-top:6px;">
                            <label style="font-weight:600;"><input type="radio" name="is_disabled" value="no" <?= (empty($applicant['is_disabled']) || intval($applicant['is_disabled']) !== 1) ? 'checked' : '' ?>> No</label>
                            <label style="font-weight:600;"><input type="radio" name="is_disabled" value="yes" <?= (isset($applicant['is_disabled']) && intval($applicant['is_disabled']) === 1) ? 'checked' : '' ?>> Yes</label>
                        </div>
                        <div id="profile_disability_details_wrap" style="margin-top:10px; <?= (isset($applicant['is_disabled']) && intval($applicant['is_disabled']) === 1) ? '' : 'display:none;' ?>">
                            <label style="font-weight:600;">If yes, please provide details</label>
                            <textarea name="disability_details" id="profile_disability_details" rows="3" placeholder="Describe any disability and support needs" style="margin-top:6px;"><?= htmlspecialchars($applicant['disability_details'] ?? '') ?></textarea>
                        </div>
                    </div>

                        <div class="form-group">
                            <label for="house_no">House No (if Existing Tenant)</label>
                            <input type="text" id="house_no" name="house_no" value="<?= htmlspecialchars($applicant['house_no'] ?? '') ?>" placeholder="e.g. 101">
                            <div class="required-note">If you are an existing tenant, enter your house number to be added to the Tenants list.</div>
                        </div>

                    <div class="form-group">
                        <label for="photo">Profile Photo (optional)</label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                        <div class="required-note">Upload a clear headshot to display on your profile</div>
                    </div>

                    <div class="button-group compact-full">
                        <button type="submit" class="btn btn-submit">Save Profile</button>
                        <?php if (!$profile_incomplete): ?>
                            <button type="button" class="btn btn-cancel" onclick="window.location.href='<?= htmlspecialchars($redirect_to) ?>';" style="background:#006400;color:#fff;border:1px solid #006400;padding:6px 8px;border-radius:4px;">Back</button>
                        <?php endif; ?>
                    </div>
                </form>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('input[name="is_disabled"]').forEach(function(radio){
        radio.addEventListener('change', function(e){
            var form = e.target.closest('form');
            if(!form) return;
            var regWrap = form.querySelector('#reg_disability_details_wrap');
            var profileWrap = form.querySelector('#profile_disability_details_wrap');
            if(regWrap){ regWrap.style.display = (e.target.value === 'yes') ? 'block' : 'none'; }
            if(profileWrap){ profileWrap.style.display = (e.target.value === 'yes') ? 'block' : 'none'; }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var floatBtn = document.getElementById('floatingSave');
    if(!floatBtn) return;

    function findVisibleForm(){
        var forms = Array.from(document.querySelectorAll('form'));
        for(var i=0;i<forms.length;i++){
            var f = forms[i];
            if(f.offsetParent !== null) return f;
        }
        return document.querySelector('form');
    }

    floatBtn.addEventListener('click', function(){
        var f = findVisibleForm();
        if(!f) return;
        var p = f.querySelector('input[name="password"], input#password');
        var cp = f.querySelector('input[name="password_confirm"], input#confirm_password');
        if(p && cp && p.value !== cp.value){
            alert('Passwords do not match.');
            if(cp) cp.focus();
            return;
        }
        f.submit();
    });
});
</script>
</body>
</html>
