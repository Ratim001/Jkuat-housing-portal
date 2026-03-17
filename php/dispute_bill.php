<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$bill_id = $_POST['bill_id'] ?? '';
if (!$bill_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing bill_id']);
    exit;
}

// Ensure user is logged in as tenant/applicant and has tenant_id
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
    require_once __DIR__ . '/../includes/helpers.php';
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t) { $_SESSION['tenant_id'] = $t['tenant_id']; $tenant_id = $t['tenant_id']; }
}
if (!$tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only tenants can dispute bills']);
    exit;
}

// Verify this bill belongs to this tenant via service->service_requests
$stmt = $conn->prepare("SELECT b.bill_id, b.service_id FROM bills b WHERE b.bill_id = ? LIMIT 1");
$stmt->bind_param('s', $bill_id);
$stmt->execute();
$brow = $stmt->get_result()->fetch_assoc();
if (!$brow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Bill not found']);
    exit;
}

$service_id = $brow['service_id'];
$sstmt = $conn->prepare("SELECT tenant_id FROM service_requests WHERE service_id = ? LIMIT 1");
$sstmt->bind_param('s', $service_id);
$sstmt->execute();
$srow = $sstmt->get_result()->fetch_assoc();
if (!$srow || $srow['tenant_id'] !== $tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not authorized to dispute this bill']);
    exit;
}

// Mark bill as disputed (set statuses='disputed') and notify admin
// Update statuses to disputed
$upd = $conn->prepare("UPDATE bills SET statuses = 'disputed' WHERE bill_id = ?");
if ($upd === false) {
    logs_write('error', 'dispute_bill: prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
$upd->bind_param('s', $bill_id);
if (!$upd->execute()) {
    logs_write('error', 'dispute_bill: execute failed: ' . $upd->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark bill disputed']);
    exit;
}

// Insert a notification for admin
require_once __DIR__ . '/../includes/helpers.php';
$notificationId = uniqid('NT');
$adminId = $_SESSION['user_id'] ?? 'system';
$msg = "Tenant {$tenant_id} disputed bill {$bill_id}";
$dateSent = date('Y-m-d H:i:s');
notify_insert_safe($conn, $notificationId, $adminId, 'admin', 'admin', $msg, $dateSent, 'unread', 'Bill Dispute');

echo json_encode(['success' => true]);

?>
