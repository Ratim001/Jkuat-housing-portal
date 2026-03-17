<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
// session is started in includes/init.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$notice_id = $_POST['notice_id'] ?? '';
$new_status = $_POST['status'] ?? '';
if (!$notice_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$allowed = ['active','revoked','fulfilled'];
if (!in_array($new_status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

// Fetch notice
$stmt = $conn->prepare("SELECT notice_id, tenant_id, date_sent, status FROM notices WHERE notice_id = ? LIMIT 1");
$stmt->bind_param('s', $notice_id);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();
if (!$notice) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Notice not found']);
    exit;
}

$is_admin = isset($_SESSION['user_id']);
$is_applicant = isset($_SESSION['applicant_id']);

// Applicant can only revoke before date_sent
if ($is_applicant && !$is_admin) {
    if ($new_status !== 'revoked') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Applicants can only revoke notices']);
        exit;
    }
    // ensure applicant owns the notice
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
        require_once __DIR__ . '/../includes/helpers.php';
        $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
        if ($t) { $_SESSION['tenant_id'] = $t['tenant_id']; $tenant_id = $t['tenant_id']; }
    }
    if (!$tenant_id || $tenant_id !== $notice['tenant_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    // can revoke only before date_sent
    $date_sent = new DateTime($notice['date_sent']);
    $now = new DateTime();
    if ($now >= $date_sent) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Too late to revoke']);
        exit;
    }
}

// Admin can change any status
if (!$is_admin && !$is_applicant) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$u = $conn->prepare("UPDATE notices SET status = ? WHERE notice_id = ?");
$u->bind_param('ss', $new_status, $notice_id);
if (!$u->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
    exit;
}

// Notify tenant when admin updates status, or notify admin when applicant revokes
$dateNow = date('Y-m-d H:i:s');
if ($is_admin) {
    $target = $notice['tenant_id'];
    $notif = "Your move-out notice {$notice_id} status changed to {$new_status} by admin.";
    notify_insert_safe($conn, uniqid('NT'), $_SESSION['user_id'] ?? 'admin', $target, 'tenant', $notif, $dateNow, 'unread', 'Notice Status');
} else {
    // applicant revoked: notify admin
    $notif = "Tenant {$notice['tenant_id']} revoked move-out notice {$notice_id}.";
    notify_insert_safe($conn, uniqid('NT'), $_SESSION['applicant_id'] ?? 'tenant', 'admin', 'admin', $notif, $dateNow, 'unread', 'Notice Revoked');
}

// Additional side-effects: if applicant revoked, clear tenants.move_out_date; if admin fulfilled, mark tenant terminated
if ($is_applicant && $new_status === 'revoked') {
    $clear = $conn->prepare("UPDATE tenants SET move_out_date = NULL WHERE tenant_id = ?");
    $clear->bind_param('s', $notice['tenant_id']);
    $clear->execute();
}

if ($is_admin && $new_status === 'fulfilled') {
    // mark tenant as Terminated and keep move_out_date as notice_end_date
    $term = $conn->prepare("UPDATE tenants t JOIN notices n ON t.tenant_id = n.tenant_id SET t.status = 'Terminated' WHERE n.notice_id = ?");
    $term->bind_param('s', $notice_id);
    $term->execute();
}

echo json_encode(['success' => true]);
exit;
