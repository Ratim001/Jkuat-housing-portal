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
$allowed_statuses = ['pending', 'approved', 'rejected', 'cancelled', 'won'];

// Normalize status to lowercase
$new_status = strtolower($new_status);

// Validate application_id format (should be like AP001, AP002, etc.)
if (!preg_match('/^AP\d{3}$/', $application_id)) {
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

// Update application status
$update_stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
if (!$update_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare update statement: ' . $conn->error);
    exit;
}

$update_stmt->bind_param("ss", $new_status, $application_id);
if ($update_stmt->execute()) {
    logs_write('info', "Application status updated: $application_id -> $new_status by admin " . ($_SESSION['username'] ?? 'unknown'));
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'application_id' => $application_id,
        'status' => $new_status
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
    logs_write('error', "Failed to update application status: $application_id -> $new_status. Error: " . $conn->error);
}

exit;
