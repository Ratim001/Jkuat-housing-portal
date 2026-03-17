<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

// Only allow logged-in applicants
// `includes/init.php` already starts the session; avoid calling `session_start()` again.
if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$applicant_id = $_SESSION['applicant_id'];
// Resolve tenant id from session or DB so tenant features are immediately available
$tenant_id = $_SESSION['tenant_id'] ?? null; // may be set if applicant has tenant record
if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
    require_once __DIR__ . '/../includes/helpers.php';
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t) { $_SESSION['tenant_id'] = $t['tenant_id']; $tenant_id = $t['tenant_id']; }
}

$error = '';
$success = '';

// If user is not a tenant, they cannot submit service requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_service'])) {
    if (!$tenant_id) {
        $error = 'Only tenants can submit service requests. Contact admin if you are a tenant but cannot see this.';
    } else {
        $type = trim($_POST['type_of_service'] ?? '');
        $details = trim($_POST['details'] ?? '');
        if ($type === '' || $details === '') {
            $error = 'Please provide both service type and details.';
        } else {
            // Generate service id
            $q = $conn->query("SELECT service_id FROM service_requests ORDER BY service_id DESC LIMIT 1");
            $newNum = 1;
            if ($q && $row = $q->fetch_assoc()) {
                $last = preg_replace('/[^0-9]/', '', $row['service_id']);
                $newNum = ((int)$last) + 1;
            }
            $service_id = 'S' . str_pad($newNum, 3, '0', STR_PAD_LEFT);
            $date = date('Y-m-d H:i:s');
            $status = 'pending';
            $bill_amount = 0.00;

            $ins = $conn->prepare("INSERT INTO service_requests (service_id, tenant_id, type_of_service, details, bill_amount, date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($ins === false) {
                logs_write('error', 'my_service_requests: prepare failed: ' . $conn->error);
                $error = 'Server error. Please try again later.';
            } else {
                // types: service_id(s), tenant_id(s), type(s), details(s), bill_amount(d), date(s), status(s)
                $ins->bind_param('ssssdss', $service_id, $tenant_id, $type, $details, $bill_amount, $date, $status);
                if ($ins->execute()) {
                    $success = 'Service request created. Service ID: ' . htmlspecialchars($service_id);
                } else {
                    logs_write('error', 'my_service_requests: execute failed: ' . $ins->error);
                    $error = 'Failed to create service request: ' . $conn->error;
                }
            }
        }
    }
}

// Fetch the applicant's service requests by tenant_id if present
$requests = [];
if ($tenant_id) {
    $stmt = $conn->prepare("SELECT service_id, tenant_id, type_of_service, details, bill_amount, date, status FROM service_requests WHERE tenant_id = ? ORDER BY date DESC");
    $stmt->bind_param('s', $tenant_id);
    $stmt->execute();
    $requests = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Service Requests | JKUAT Housing</title>
    <link rel="stylesheet" href="../css/ictdashboard.css">
    <style>
        :root{--green:#0b6b2b;--muted:#f4f6f5;--danger:#dc3545}
        body{font-family:Inter,Segoe UI,Arial, sans-serif;background:var(--muted);}
        .container{margin-left:220px;padding:30px;max-width:1100px;margin-right:30px}
        .card{background:#fff;border-radius:10px;padding:22px;box-shadow:0 6px 18px rgba(0,0,0,0.06);}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden}
        th,td{padding:12px 14px;border-bottom:1px solid #eee;text-align:left}
        th{background:var(--green);color:#fff;font-weight:600}
        .btn{padding:10px 14px;border-radius:6px;border:none;background:var(--green);color:#fff;cursor:pointer}
        .btn.secondary{background:#fff;color:var(--green);border:1px solid var(--green)}
        .error{background:#fff3f3;padding:12px;border-left:4px solid #f5c2c7;color:#721c24;margin-bottom:12px}
        .success{background:#f3fff5;padding:12px;border-left:4px solid #b6e6c8;color:#155724;margin-bottom:12px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px;margin-bottom:16px}
        label{display:block;margin-bottom:6px;font-weight:600;color:#254b2b}
        input[type="text"], textarea{width:100%;padding:10px;border-radius:6px;border:1px solid #d6d6d6;font-size:14px;background:#fff}
        textarea{min-height:44px;resize:none;overflow:hidden}
        .muted-note{color:#6b6b6b;font-size:13px;margin-top:8px}
        @media (max-width:800px){.container{margin-left:0;padding:20px}.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="container">
    <h1 style="margin-bottom:6px">My Service Requests</h1>
    <p class="muted-note">Create and track maintenance requests for your unit.</p>
    <div style="height:12px"></div>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$tenant_id): ?>
        <p>You currently do not have a tenant record. Service requests can only be submitted by tenants. Contact administration to link your account.</p>
    <?php else: ?>
        <div class="card">
        <form method="POST">
            <input type="hidden" name="create_service" value="1">
            <div class="form-grid">
                <div>
                    <label for="type_of_service">Type of Service</label>
                    <textarea id="type_of_service" name="type_of_service" placeholder="e.g. Plumbing, Electrical, Door repair" required rows="1" oninput="autoResize(this)"></textarea>
                </div>
                <div>
                    <label for="details">Details</label>
                    <textarea id="details" name="details" placeholder="Describe the issue with as much detail as possible" required rows="2" oninput="autoResize(this)"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <button class="btn" type="submit">Create Service Request</button>
                <button type="button" class="btn secondary" onclick="clearForm()">Clear</button>
                <div class="muted-note">You can expand the fields by typing — they auto-resize.</div>
            </div>
        </form>
        </div>

        <h2 style="margin-top:30px">Your Requests</h2>
        <div style="margin-top:12px"></div>
        <table>
            <thead>
                <tr><th>Service ID</th><th>Type</th><th>Details</th><th>Bill (KES)</th><th>Date</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if ($requests && $requests->num_rows > 0): while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['service_id']) ?></td>
                    <td><?= htmlspecialchars($r['type_of_service']) ?></td>
                    <td style="max-width:380px;white-space:pre-wrap;"><?= htmlspecialchars($r['details']) ?></td>
                    <td><?= number_format((float)$r['bill_amount'],2) ?></td>
                    <td><?= htmlspecialchars($r['date']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($r['status'])) ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">No service requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
<script>
// Auto-resize textareas based on content
function autoResize(el){
    if(!el) return;
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight) + 'px';
}
// Initialize any existing textareas on load
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('textarea').forEach(t => autoResize(t));
});
function clearForm(){
    document.getElementById('type_of_service').value='';
    document.getElementById('details').value='';
    autoResize(document.getElementById('type_of_service'));
    autoResize(document.getElementById('details'));
}
</script>
