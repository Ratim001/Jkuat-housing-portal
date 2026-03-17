<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

// Log the start of the submission
error_log('=== submit_post_forfeit.php === Request method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Session applicant_id: ' . ($_SESSION['applicant_id'] ?? 'NOT SET'));

// Generate human-friendly request id: PFR-YYYYMMDD-###
function generate_forfeit_request_id($conn) {
    $date = date('Ymd');
    $prefix = 'PFR-' . $date . '-';
    $like = $conn->real_escape_string($prefix) . '%';
    $q = $conn->prepare("SELECT request_id FROM post_forfeit_requests WHERE request_id LIKE ? ORDER BY created_at DESC LIMIT 1");
    if ($q) {
        $q->bind_param('s', $like);
        $q->execute();
        $res = $q->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $last = $row['request_id'];
            $parts = explode('-', $last);
            $n = intval(end($parts));
            $n++;
        } else {
            $n = 1;
        }
    } else {
        // fallback to uniqid if query fails
        error_log('generate_forfeit_request_id: prepare failed: ' . $conn->error);
        return 'PFR' . uniqid();
    }
    return $prefix . str_pad($n, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed'); exit;
}

$applicant_id = $_SESSION['applicant_id'] ?? null;
if (!$applicant_id) {
    $_SESSION['flash_error'] = 'You must be signed in to submit a forfeit request.';
    header('Location: applicant_profile.php'); exit;
}

$reason = trim($_POST['reason'] ?? '');
if ($reason === '') {
    $_SESSION['flash_error'] = 'Please provide a reason for the forfeit request.';
    header('Location: applicant_profile.php'); exit;
}

// Minimal eligibility check: ensure applicant has at least one ballot record (placed) or an active application
$hasBallot = false;
// Check balloting participation
if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ?")) {
    $stmt->bind_param('s', $applicant_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && (int)$r['cnt'] > 0) $hasBallot = true;
}

// If no ballot entries, accept applicants who at least have an application in active statuses
if (!$hasBallot) {
    if ($stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE applicant_id = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved','won','allocated','tenant')")) {
        $stmt2->bind_param('s', $applicant_id);
        $stmt2->execute();
        $r2 = $stmt2->get_result()->fetch_assoc();
        if ($r2 && (int)$r2['cnt'] > 0) $hasBallot = true;
    }
}

if (!$hasBallot) {
    $_SESSION['flash_error'] = 'No ballot participation or related application found — cannot request post-closed forfeit.';
    error_log('submit_post_forfeit: Ballot check failed for applicant: ' . $applicant_id);
    header('Location: applicant_profile.php'); exit;
}

$attachmentPath = null;
if (!empty($_FILES['attachment']['name'])) {
    // Basic upload validation: limit size and types
    $allowed = ['image/jpeg','image/png','application/pdf'];
    if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK && in_array($_FILES['attachment']['type'], $allowed) && $_FILES['attachment']['size'] <= 3 * 1024 * 1024) {
        $uploadsDir = __DIR__ . '/../uploads/post_forfeits';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $fname = 'pf_' . $applicant_id . '_' . time() . '.' . $ext;
        $target = $uploadsDir . '/' . $fname;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
            $attachmentPath = 'uploads/post_forfeits/' . $fname;
        } else {
            error_log('submit_post_forfeit: move_uploaded_file failed for applicant ' . $applicant_id . ' to ' . $target);
        }
    }
}

$requestId = generate_forfeit_request_id($conn);
$applicationId = trim($_POST['application_id'] ?? '');
error_log('submit_post_forfeit: Generated request ID ' . $requestId . ' for applicant ' . $applicant_id . ', reason length: ' . strlen($reason) . ', attachment: ' . ($attachmentPath ?? 'NULL'));
$stmt = $conn->prepare("INSERT INTO post_forfeit_requests (request_id, applicant_id, application_id, reason, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
if ($stmt) {
    // bind variables must be passed by reference and match the types string
    // bind attachment as empty string when not provided to avoid NULL binding issues
    $att = $attachmentPath === null ? '' : $attachmentPath;
    $appIdForDb = ($applicationId === '') ? '' : $applicationId;
    error_log('submit_post_forfeit: Binding params - requestId: ' . $requestId . ', applicant_id: ' . $applicant_id . ', application_id: ' . $appIdForDb . ', reason: ' . substr($reason, 0, 50) . '..., att: ' . $att);
    $stmt->bind_param('sssss', $requestId, $applicant_id, $appIdForDb, $reason, $att);
    if ($stmt->execute()) {
        error_log('submit_post_forfeit: Successfully inserted request ID: ' . $requestId . ' for applicant: ' . $applicant_id);
        $_SESSION['flash_success'] = 'Forfeit request submitted successfully';
        // notify admins: insert a notification for admin role if notifications table exists
        if (strpos(mysqli_get_server_info($conn), 'MariaDB') !== false || true) {
            $nid = uniqid('NT');
            $msg = "Applicant {$applicant_id} submitted a post-close forfeit request (ID: {$requestId}).";
            // best-effort: insert into notifications if table exists
            $resn = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($resn && $resn->num_rows > 0) {
                $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, date_sent, date_received, status) VALUES ('" . $conn->real_escape_string($nid) . "', 'system', 'admin', '', '" . $conn->real_escape_string($msg) . "', NOW(), NOW(), 'unread')");
            }
        }
        // Try to email the applicant a confirmation using template
        $e = $conn->prepare("SELECT email, name FROM applicants WHERE applicant_id = ? LIMIT 1");
        if ($e) {
            $e->bind_param('s', $applicant_id);
            $e->execute();
            $er = $e->get_result()->fetch_assoc();
            $appEmail = $er['email'] ?? null;
            $appName = $er['name'] ?? 'Applicant';
            if (!empty($appEmail) && filter_var($appEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    require_once __DIR__ . '/../includes/email.php';
                    $tpl = __DIR__ . '/../templates/emails/post_forfeit_request_submitted.html';
                    $body = '';
                    if (file_exists($tpl)) {
                        $body = file_get_contents($tpl);
                        $body = str_replace('{{applicant_name}}', htmlspecialchars($appName), $body);
                        $body = str_replace('{{request_id}}', htmlspecialchars($requestId), $body);
                        $body = str_replace('{{reason}}', nl2br(htmlspecialchars($reason)), $body);
                        $body = str_replace('{{created_at}}', date('Y-m-d H:i:s'), $body);
                    } else {
                        $body = '<p>Dear ' . htmlspecialchars($appName) . ',</p><p>Your forfeit request has been received.</p>';
                    }
                    $html = build_email_wrapper($body);
                    if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicant_id, $appEmail, 'Forfeit Request Submitted', $html, 'Forfeit Request');
                } catch (Exception $ex) { error_log('Forfeit submission email failed: ' . $ex->getMessage()); }
            }
        }

        // Redirect back to applicants list if request came from there, otherwise profile page
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($ref, 'applicants.php') !== false) {
            header('Location: applicants.php'); exit;
        }
        header('Location: applicant_profile.php'); exit;
    } else {
        error_log('submit_post_forfeit: execute() failed. Error: ' . $stmt->error);
    }
}
else {
    error_log('submit_post_forfeit: prepare failed: ' . $conn->error);
}

// If we reach here, log detailed stmt error if available and show generic message
if (isset($stmt) && $stmt && $stmt->error) {
    error_log('submit_post_forfeit: stmt error: ' . $stmt->error);
} else if (isset($stmt)) {
    error_log('submit_post_forfeit: execute failed - stmt->error is empty');
} else {
    error_log('submit_post_forfeit: stmt is null/false');
}

$_SESSION['flash_error'] = 'Failed to submit request. Please try again.';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($ref, 'applicants.php') !== false) {
    header('Location: applicants.php'); exit;
}
header('Location: applicant_profile.php'); exit;

?>
