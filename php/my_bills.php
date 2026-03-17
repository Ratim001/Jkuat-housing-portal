<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
// session already started in includes/init.php

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

// Prefer tenant_id in session; fall back to DB lookup so rights apply immediately
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
    require_once __DIR__ . '/../includes/helpers.php';
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t) { $_SESSION['tenant_id'] = $t['tenant_id']; $tenant_id = $t['tenant_id']; }
}

// Fetch bills for this tenant via service->service_requests
$bills = [];
if ($tenant_id) {
    $q = $conn->prepare("SELECT b.bill_id, b.service_id, b.type_of_bill, b.amount, b.date_billed, b.date_settled, b.status, COALESCE(b.statuses,'active') as statuses FROM bills b JOIN service_requests s ON b.service_id = s.service_id WHERE s.tenant_id = ? ORDER BY b.date_billed DESC");
    $q->bind_param('s', $tenant_id);
    $q->execute();
    $bills = $q->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Bills | JKUAT Housing</title>
    <link rel="stylesheet" href="../css/ictdashboard.css">
    <style> body{font-family:Arial, sans-serif;} .container{margin-left:220px;padding:30px;} table{width:100%;border-collapse:collapse;} th,td{padding:10px;border:1px solid #ccc;} th{background:#006400;color:#fff;} .btn{padding:6px 10px;border-radius:5px;border:none;background:#006400;color:#fff;cursor:pointer;} .dispute{background:#ffc107;color:#000;} .status-badge{padding:4px 8px;border-radius:12px;font-weight:bold;} </style>
    </style>
</head>
<body>
<div class="container">
    <div class="top-header">
        <h1>My Bills</h1>
        <div class="muted-note">View your bills and dispute any you believe are incorrect.</div>
    </div>

    <?php if (!$tenant_id): ?>
        <div style="background:#fff3cd;padding:12px;border:1px solid #ffeeba;border-radius:6px;">You do not have a tenant record. Bills are linked to tenant accounts.</div>
    <?php else: ?>
        <div class="filter-bar">
            <input id="searchInput" placeholder="Search by bill id, service id or type...">
            <select id="statusFilter"><option value="">All statuses</option><option value="not paid">Not paid</option><option value="paid">Paid</option></select>
        </div>

        <table id="billsTable">
            <thead>
            <tr><th>Bill ID</th><th>Service ID</th><th>Type</th><th>Amount</th><th>Date Billed</th><th>Date Settled</th><th>Payment Status</th><th>Admin Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if ($bills && $bills->num_rows > 0): while ($r = $bills->fetch_assoc()):
                $adminStatus = strtolower($r['statuses'] ?? 'active');
                $badgeClass = $adminStatus === 'disputed' ? 'status-disputed' : 'status-active';
            ?>
                <tr data-bill-id="<?= htmlspecialchars($r['bill_id']) ?>">
                    <td><?= htmlspecialchars($r['bill_id']) ?></td>
                    <td><?= htmlspecialchars($r['service_id']) ?></td>
                    <td><?= htmlspecialchars($r['type_of_bill']) ?></td>
                    <td><?= number_format((float)$r['amount'],2) ?></td>
                    <td><?= htmlspecialchars($r['date_billed']) ?></td>
                    <td><?= htmlspecialchars($r['date_settled']) ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td><span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($adminStatus)) ?></span></td>
                    <td>
                        <?php if ($adminStatus !== 'disputed'): ?>
                            <button class="btn dispute" onclick="disputeBill('<?= htmlspecialchars($r['bill_id']) ?>')">Dispute</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="9">No bills found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Simple client-side filtering
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const rows = () => Array.from(document.querySelectorAll('#billsTable tbody tr'));
function applyFilters(){
    const q = (searchInput?.value||'').toLowerCase();
    const status = (statusFilter?.value||'').toLowerCase();
    rows().forEach(r=>{
        const text = r.textContent.toLowerCase();
        const payStatus = r.children[6]?.textContent.toLowerCase()||'';
        const match = (q === '' || text.includes(q)) && (status === '' || payStatus === status);
        r.style.display = match ? '' : 'none';
    });
}
if (searchInput) searchInput.addEventListener('input', applyFilters);
if (statusFilter) statusFilter.addEventListener('change', applyFilters);

function disputeBill(billId){
    if (!confirm('Are you sure you want to dispute this bill? The admin will be notified.')) return;
    fetch('dispute_bill.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'bill_id='+encodeURIComponent(billId)})
        .then(r=>r.json()).then(j=>{ if (j.success) location.reload(); else alert('Failed: '+(j.error||'unknown')); }).catch(e=>{console.error(e);alert('Error sending dispute')});
}
</script>
</body>
</html>
