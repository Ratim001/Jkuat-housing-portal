<?php
/**
 * php/update_application_status.php
 * Purpose: REST endpoint to update application status (Pending, Approved, Rejected, Cancelled, Won)
 * Authentication: Admin only
 * Method: POST
 * Params: application_id, status
 */

require_once '../includes/init.php';
require_once '../includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication - admin only (CS Admin or ICT Admin)
if (!isset($_SESSION['role']) || strpos($_SESSION['role'], 'Admin') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only admins can update application status']);
    logs_write('warning', 'Unauthorized attempt to update application status');
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$application_id = $_POST['application_id'] ?? '';
$new_status = $_POST['status'] ?? '';
// Allow these statuses (use underscore for multiword values sent from UI)
// Support both 'not_successful' and legacy 'unsuccessful'; store as 'Not Successful'
$allowed_statuses = ['applied','pending', 'approved', 'rejected', 'cancelled', 'allocated', 'won', 'not_successful', 'unsuccessful'];

// Normalize status to lowercase for validation
$new_status = strtolower($new_status);

// Validate application_id format (should be like AP001 or AP1000 — allow variable digits)
if (!preg_match('/^AP\d+$/', $application_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid application_id format']);
    logs_write('warning', "Invalid application_id format: $application_id");
    exit;
}

// Validate status
if (!in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid status. Allowed: " . implode(', ', $allowed_statuses)]);
    logs_write('warning', "Invalid status attempt: $new_status for application $application_id");
    exit;
}

// Verify application exists
$check_stmt = $conn->prepare("SELECT applicant_id FROM applications WHERE application_id = ?");
if (!$check_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare check statement: ' . $conn->error);
    exit;
}

$check_stmt->bind_param("s", $application_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$app_record = $result->fetch_assoc();

if (!$app_record) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Application not found']);
    logs_write('warning', "Attempt to update non-existent application: $application_id");
    exit;
}

// Before updating, if admin attempts to mark as approved ensure applicant placed a ballot
if ($new_status === 'approved') {
    // ensure balloting table exists
    $tbl = $conn->real_escape_string('balloting');
    $tbres = $conn->query("SHOW TABLES LIKE '" . $tbl . "'");
    if (!($tbres && $tbres->num_rows > 0)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Balloting table is missing. Cannot approve until ballots are recorded. Run migrations."]);
        logs_write('error', "Attempt to approve application $application_id but balloting table missing");
        exit;
    }

    $applicantId = $app_record['applicant_id'];
    $houseNo = $app_record['house_no'];
    // map house_no to house_id if possible
    $hidStmt = $conn->prepare("SELECT house_id FROM houses WHERE house_no = ? LIMIT 1");
    $hidStmt->bind_param('s', $houseNo);
    $hidStmt->execute();
    $hidR = $hidStmt->get_result()->fetch_assoc();
    $houseId = $hidR['house_id'] ?? null;

    if ($houseId) {
        $ballotCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ? AND house_id = ?");
        $ballotCheck->bind_param('ss', $applicantId, $houseId);
    } else {
        $ballotCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ?");
        $ballotCheck->bind_param('s', $applicantId);
    }
    $ballotCheck->execute();
    $bc = $ballotCheck->get_result()->fetch_assoc();
    if (!($bc && $bc['cnt'] > 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot approve: applicant has not placed a ballot for this house.']);
        logs_write('warning', "Attempt to approve application $application_id but no ballot found for applicant {$applicantId}");
        exit;
    }
}

// Update application status
$update_stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
if (!$update_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare update statement: ' . $conn->error);
    exit;
}

// Store canonical values compatible with both ENUM and VARCHAR schemas.
// (Later migrations normalize to VARCHAR, but some environments may still be ENUM.)
if (in_array($new_status, ['unsuccessful', 'not_successful'], true)) {
    $storeStatus = 'unsuccessful';
} else {
    $storeStatus = strtolower(str_replace('_', ' ', $new_status));
}
$update_stmt->bind_param("ss", $storeStatus, $application_id);
if ($update_stmt->execute()) {
    logs_write('info', "Application status updated: $application_id -> $new_status by admin " . ($_SESSION['username'] ?? 'unknown'));
    // If application is allocated via this endpoint, mark the applicant role as tenant
    if ($new_status === 'allocated') {
        $applicantId = $app_record['applicant_id'];
        $u = $conn->prepare("UPDATE applicants SET role = 'tenant' WHERE applicant_id = ?");
        if ($u) {
            $u->bind_param('s', $applicantId);
            $u->execute();
        }
    }
    // Send notification to the applicant about the status change
    $applicantId = $app_record['applicant_id'];
    $resName = $conn->prepare("SELECT name FROM applicants WHERE applicant_id = ?");
    $resName->bind_param('s', $applicantId);
    $resName->execute();
    $rN = $resName->get_result()->fetch_assoc();
    $appName = $rN['name'] ?: 'Applicant';
    $notificationId = uniqid('NT');
    $adminId = $_SESSION['user_id'] ?? 'user002';
    $dateSent = date('Y-m-d H:i:s');
    $title = 'Admin';
    // Friendly label for the user notification/JSON
    $displayStatus = ($storeStatus === 'unsuccessful') ? 'Not successful' : ucwords($storeStatus);
    $msg = "Dear \"{$appName}\": Your application {$application_id} status has been changed to {$displayStatus}.";
    require_once '../includes/helpers.php';
    notify_insert_safe($conn, $notificationId, $adminId, 'applicant', $applicantId, $msg, $dateSent, 'unread', $title);
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'application_id' => $application_id,
        'status' => $displayStatus
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
    logs_write('error', "Failed to update application status: $application_id -> $new_status. Error: " . $conn->error);
}

exit;
