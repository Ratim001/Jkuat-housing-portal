<?php
/**
 * php/forfeit_application.php
 * Purpose: REST endpoint for applicants to request forfeiture of their application
 * Authentication: Logged-in applicant, must own the application
 * Method: POST
 * Params: application_id
 * Action: Creates a forfeit request in post_forfeit_requests table with status='pending'
 *         Admin must review and approve/reject the request before application is cancelled
 */

require_once '../includes/init.php';
require_once '../includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication - applicant must be logged in
if (!isset($_SESSION['applicant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$raw_app_id = $_POST['application_id'] ?? null;
// Log raw input for debugging (helps catch encoding/whitespace issues)
if (function_exists('logs_write')) logs_write('debug', 'forfeit_application: received raw application_id: ' . var_export($raw_app_id, true));

$application_id = is_string($raw_app_id) ? trim($raw_app_id) : '';
$application_id = strtoupper($application_id);
$applicant_id = $_SESSION['applicant_id'];

// Validate application_id format (allow AP followed by one or more digits)
if (!preg_match('/^AP\\d+$/', $application_id)) {
    if (function_exists('logs_write')) logs_write('warning', 'forfeit_application: Invalid application_id format posted: ' . var_export($raw_app_id, true));
    // Attempt graceful fallback: find the most recent 'Applied' or 'Pending' application for this applicant
    $fallbackStmt = $conn->prepare("SELECT application_id FROM applications WHERE applicant_id = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending') ORDER BY date DESC LIMIT 1");
    if ($fallbackStmt) {
        $fallbackStmt->bind_param('s', $applicant_id);
        $fallbackStmt->execute();
        $fr = $fallbackStmt->get_result()->fetch_assoc();
        $fallbackApp = $fr['application_id'] ?? null;
        $fallbackStmt->close();
        if ($fallbackApp) {
            if (function_exists('logs_write')) logs_write('info', "forfeit_application: falling back to most recent application_id={$fallbackApp} for applicant {$applicant_id}");
            $application_id = $fallbackApp;
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid application_id format']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid application_id format']);
        exit;
    }
}

// Verify ownership: fetch the application and check it belongs to this applicant
$check_stmt = $conn->prepare("SELECT applicant_id, status FROM applications WHERE application_id = ?");
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

// Application not found
if (!$app_record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Application not found']);
    logs_write('warning', "Forfeit attempt on non-existent application: $application_id by $applicant_id");
    exit;
}

// Ownership check: application must belong to the logged-in applicant
if ($app_record['applicant_id'] !== $applicant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    logs_write('warning', "Unauthorized forfeit attempt: $applicant_id tried to cancel application $application_id owned by " . $app_record['applicant_id']);
    exit;
}

// Validate that applicant participated in ballot
$ballot_check = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ?");
if (!$ballot_check) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare ballot check statement: ' . $conn->error);
    exit;
}
$ballot_check->bind_param('s', $applicant_id);
$ballot_check->execute();
$ballot_result = $ballot_check->get_result()->fetch_assoc();
if (!$ballot_result || (int)$ballot_result['cnt'] === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You must have participated in the ballot to submit a forfeit request']);
    logs_write('info', "Forfeit attempt by applicant with no ballot participation: $applicant_id");
    exit;
}

// Check if ballot is closed
$ballot_is_closed = true; // Default to closed
try {
    $bc_check = $conn->prepare("SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
    if ($bc_check) {
        $bc_check->execute();
        $bc_result = $bc_check->get_result();
        if ($bc_result && $bc_result->num_rows > 0) {
            $bc_row = $bc_result->fetch_assoc();
            $is_open = (bool)$bc_row['is_open'];
            $end_date = $bc_row['end_date'];
            // Ballot is closed if is_open = 0 OR end_date has passed
            $ballot_is_closed = !$is_open || (strtotime($end_date) < time());
        }
    }
} catch (Exception $e) {
    $ballot_is_closed = true; // Default to closed if error
}

if (!$ballot_is_closed) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The ballot is currently open. You can forfeit after the ballot closes']);
    logs_write('info', "Forfeit attempt while ballot is open: $applicant_id");
    exit;
}

// Can only forfeit applied or pending applications (empty/NULL status also defaults to 'Applied')
$status_normalized = strtolower(trim((string)($app_record['status'] ?? '')));
if (!in_array($status_normalized, ['', 'applied', 'pending'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Can only forfeit Applied or Pending applications']);
    logs_write('info', "Forfeit attempt on non-Applied application: $application_id status=" . $app_record['status']);
    exit;
}

// Create a forfeit request instead of immediately cancelling
// Generate unique request ID with format PFR-YYYYMMDD-###
$today = date('Ymd');
$sequence = 1;
$maxAttempts = 999;
$requestId = null;

while ($sequence <= $maxAttempts) {
    $requestId = 'PFR-' . $today . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    // Check if this ID already exists
    $checkId = $conn->prepare("SELECT 1 FROM post_forfeit_requests WHERE request_id = ? LIMIT 1");
    if ($checkId) {
        $checkId->bind_param('s', $requestId);
        $checkId->execute();
        if ($checkId->get_result()->num_rows === 0) {
            break; // Found unique ID
        }
    }
    $sequence++;
}

if (empty($requestId) || $sequence > $maxAttempts) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate request ID']);
    logs_write('error', 'Failed to generate unique forfeit request ID');
    exit;
}

// Insert forfeit request into post_forfeit_requests table
$insert_stmt = $conn->prepare("INSERT INTO post_forfeit_requests (request_id, applicant_id, application_id, reason, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
if (!$insert_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare insert statement: ' . $conn->error);
    exit;
}

// Prepare reason and attachment from POST if provided, otherwise use defaults
$reason = trim($_POST['reason'] ?? '');
if (empty($reason)) {
    $reason = 'Forfeit request submitted via application forfeiture';
}

// Handle file upload for attachment if provided
$attachment = null;
if (!empty($_FILES['attachment']['name'])) {
    $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    $file_type = $_FILES['attachment']['type'];
    $file_size = $_FILES['attachment']['size'];
    $max_size = 3 * 1024 * 1024; // 3 MB
    
    if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK && in_array($file_type, $allowed) && $file_size <= $max_size) {
        $uploadsDir = __DIR__ . '/../uploads/post_forfeits';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $fname = 'pf_' . $applicant_id . '_' . time() . '.' . $ext;
        $target = $uploadsDir . '/' . $fname;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
            $attachment = 'uploads/post_forfeits/' . $fname;
        } else {
            logs_write('error', "forfeit_application: Failed to upload attachment for applicant $applicant_id");
        }
    } else {
        logs_write('warning', "forfeit_application: Invalid attachment file for applicant $applicant_id - type: $file_type, size: $file_size");
    }
}

$insert_stmt->bind_param("sssss", $requestId, $applicant_id, $application_id, $reason, $attachment);
if ($insert_stmt->execute()) {
    logs_write('info', "Forfeit request created: $requestId for application $application_id by applicant $applicant_id");
    
    // Insert notification for admin and applicant
    $dateSent = date('Y-m-d H:i:s');
    $adminId = 'system';
    
    // Notify admin of new forfeit request
    if (function_exists('notify_insert_safe')) {
        $msgAdmin = "Applicant {$applicant_id} submitted a forfeit request (ID: {$requestId}) for application {$application_id}. Please review and approve/reject.";
        notify_insert_safe($conn, uniqid('NT'), $adminId, 'admin', 'admin', $msgAdmin, $dateSent, 'unread', 'Forfeit Request');
        
        // Notify applicant that request is pending admin review
        $msgApplicant = "Your forfeit request ({$requestId}) has been submitted and is pending admin review. You will be notified when the admin makes a decision.";
        notify_insert_safe($conn, uniqid('NT'), $adminId, 'applicant', $applicant_id, $msgApplicant, $dateSent, 'unread', 'Forfeit Request Submitted');
    }
    
    // Try to email applicant if email available
    $e = $conn->prepare("SELECT email, name FROM applicants WHERE applicant_id = ? LIMIT 1");
    if ($e) {
        $e->bind_param('s', $applicant_id);
        $e->execute();
        $er = $e->get_result()->fetch_assoc();
        $email = $er['email'] ?? null;
        $name = $er['name'] ?? 'Applicant';
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $subject = 'Forfeit Request Submitted — JKUAT Housing';
            $bodyHtml = '<p>Dear ' . htmlspecialchars($name) . ',</p><p>Your forfeit request (' . htmlspecialchars($requestId) . ') for application ' . htmlspecialchars($application_id) . ' has been submitted and is pending admin review.</p><p>You will be notified by email and in-app notification once the admin makes a decision.</p>';
            if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicant_id, $email, $subject, $bodyHtml, 'Forfeit Request Submitted');
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'request_id' => $requestId,
        'application_id' => $application_id,
        'message' => 'Forfeit request submitted successfully',
        'status' => 'pending'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit forfeit request']);
    logs_write('error', "Failed to create forfeit request: $requestId. Error: " . $conn->error);
}

exit;
