<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
header('Content-Type: text/html; charset=utf-8');
// session is started in includes/init.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$tenant_id = $_POST['tenant_id'] ?? ($_SESSION['tenant_id'] ?? null);
$details = trim($_POST['details'] ?? '');
$move_out_date = $_POST['move_out_date'] ?? '';

if (!$tenant_id || !$details || !$move_out_date) {
    header('Location: my_notices.php?msg=' . urlencode('Missing required fields'));
    exit;
}

// Enforce two-weeks notice: move_out_date must be at least 14 days from today
try {
    $moveDt = new DateTime($move_out_date);
} catch (Exception $e) {
    header('Location: my_notices.php?msg=' . urlencode('Invalid move out date'));
    exit;
}

$today = new DateTime();
$min = (clone $today)->modify('+14 days');
if ($moveDt < $min) {
    header('Location: my_notices.php?msg=' . urlencode('Move out date must be at least 14 days from today'));
    exit;
}

// date_sent should be two weeks before move_out_date
$date_sent_dt = (clone $moveDt)->modify('-14 days');
$date_sent = $date_sent_dt->format('Y-m-d H:i:s');
$notice_id = uniqid('N');
$status = 'active';

$stmt = $conn->prepare("INSERT INTO notices (notice_id, tenant_id, details, date_sent, notice_end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssss', $notice_id, $tenant_id, $details, $date_sent, $move_out_date, $status);
if (!$stmt->execute()) {
    header('Location: my_notices.php?msg=' . urlencode('Failed to submit notice'));
    exit;
}

// Update tenant record move_out_date so admin and tenant views reflect the intended move out
$u = $conn->prepare("UPDATE tenants SET move_out_date = ? WHERE tenant_id = ?");
$u->bind_param('ss', $move_out_date, $tenant_id);
$u->execute();

// Notify admin that a notice has been scheduled (will be effective at date_sent)
require_once __DIR__ . '/../includes/helpers.php';
$notificationId = uniqid('NT');
$adminId = $_SESSION['user_id'] ?? 'system';
$notifMsg = "Tenant {$tenant_id} scheduled a move-out notice ({$notice_id}) for {$move_out_date}.";
notify_insert_safe($conn, $notificationId, $adminId, 'admin', 'admin', $notifMsg, date('Y-m-d H:i:s'), 'unread', 'Move-out Notice');

// Send confirmation email to tenant if we can find their applicant email
$infoQ = $conn->prepare("SELECT t.tenant_id, t.applicant_id, a.email, a.name FROM tenants t LEFT JOIN applicants a ON t.applicant_id = a.applicant_id WHERE t.tenant_id = ? LIMIT 1");
if ($infoQ) {
    $infoQ->bind_param('s', $tenant_id);
    $infoQ->execute();
    $info = $infoQ->get_result()->fetch_assoc();
    $email = $info['email'] ?? null;
    $name = $info['name'] ?? 'Tenant';
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $subject = 'Move-out notice scheduled — JKUAT Housing';
        $bodyHtml = '<p>Dear ' . htmlspecialchars($name) . ',</p><p>Your move-out notice ' . htmlspecialchars($notice_id) . ' for ' . htmlspecialchars($move_out_date) . ' has been scheduled.</p>';
        if (function_exists('notify_and_email')) notify_and_email($conn, 'tenant', $tenant_id, $email, $subject, $bodyHtml, 'Move-out Notice');
    }
}

header('Location: my_notices.php?msg=' . urlencode('Notice scheduled successfully'));
exit;
