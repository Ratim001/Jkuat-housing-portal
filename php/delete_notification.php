<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

// This endpoint is consumed via fetch() and MUST return valid JSON.
// In development, any PHP warning/notice would corrupt JSON and break deletion.
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

// init.php may start an output buffer to inject scripts into HTML pages.
// Ensure we never append anything to this JSON response.
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$nid = $_POST['notification_id'] ?? '';
if (!$nid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
    exit;
}

// Determine session user type and id
$user_type = '';
$user_id = '';
if (isset($_SESSION['applicant_id'])) { $user_type = 'applicant'; $user_id = $_SESSION['applicant_id']; }
elseif (isset($_SESSION['tenant_id'])) { $user_type = 'tenant'; $user_id = $_SESSION['tenant_id']; }
else { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }

// Verify notification belongs to this recipient
$stmt = $conn->prepare("SELECT notification_id FROM notifications WHERE notification_id = ? AND recipient_type = ? AND recipient_id = ? LIMIT 1");
if ($stmt === false) {
    logs_write('error', 'delete_notification: prepare failed (verify SQL/location): ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}

$stmt->bind_param('sss', $nid, $user_type, $user_id);
if (!$stmt->execute()) {
    logs_write('error', 'delete_notification: execute failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}

$res = $stmt->get_result();
if (!$res || !$res->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized to delete this notification']);
    exit;
}

$del = $conn->prepare("DELETE FROM notifications WHERE notification_id = ?");
if ($del === false) {
    logs_write('error', 'delete_notification: delete prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}

$del->bind_param('s', $nid);
if ($del->execute()) {
    echo json_encode(['success' => true]);
    exit;
} else {
    logs_write('error', 'delete_notification: delete execute failed: ' . $del->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete']);
    exit;
}
