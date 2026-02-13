<?php
/**
 * php/forfeit_application.php
 * Purpose: REST endpoint for applicants to cancel/forfeit their application
 * Authentication: Logged-in applicant, must own the application
 * Method: POST
 * Params: application_id
 * Action: Sets application.status = 'Cancelled'
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
$application_id = $_POST['application_id'] ?? '';
$applicant_id = $_SESSION['applicant_id'];

// Validate application_id format
if (!preg_match('/^AP\d{3}$/', $application_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid application_id format']);
    exit;
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

// Can only forfeit pending applications (database stores lowercase)
if (strtolower($app_record['status']) !== 'pending') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Can only forfeit Pending applications']);
    logs_write('info', "Forfeit attempt on non-Pending application: $application_id status=" . $app_record['status']);
    exit;
}

// Update application status to 'cancelled' (normalized to lowercase)
$update_stmt = $conn->prepare("UPDATE applications SET status = 'cancelled' WHERE application_id = ? AND applicant_id = ?");
if (!$update_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    logs_write('error', 'Failed to prepare update statement: ' . $conn->error);
    exit;
}

$update_stmt->bind_param("ss", $application_id, $applicant_id);
if ($update_stmt->execute()) {
    logs_write('info', "Application forfeited: $application_id by applicant $applicant_id");
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'application_id' => $application_id,
        'status' => 'Cancelled'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to cancel application']);
    logs_write('error', "Failed to cancel application: $application_id. Error: " . $conn->error);
}

exit;
