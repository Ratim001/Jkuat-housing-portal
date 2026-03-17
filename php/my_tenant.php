<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
// `includes/init.php` already starts the session; avoid calling session_start() again to prevent notices.

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
// If tenant_id not present in session, try to resolve from DB by applicant_id so rights apply immediately
if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
    require_once __DIR__ . '/../includes/helpers.php';
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t) {
        $tenant_id = $t['tenant_id'];
        $_SESSION['tenant_id'] = $tenant_id;
    }
}
if (!$tenant_id) {
    header('Location: applicants.php');
    exit;
}

$q = $conn->prepare("SELECT t.tenant_id, t.house_no, t.move_in_date, t.move_out_date, t.status AS tenant_status, a.pf_no, a.name, a.email, a.photo FROM tenants t JOIN applicants a ON t.applicant_id = a.applicant_id WHERE t.tenant_id = ? LIMIT 1");
$q->bind_param('s', $tenant_id);
$q->execute();
$row = $q->get_result()->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Tenant Info | JKUAT Housing</title>
    <link rel="stylesheet" href="../css/tenants.css">
    <style>
        :root{--jkuat-green:#0b6b2b;--jkuat-dark:#063b1f;--muted:#f6f8f7}
        body{background:var(--muted);font-family:Inter,Segoe UI,Arial,sans-serif;margin:0}
        .container{margin-left:220px;padding:30px;max-width:1000px;margin-right:30px}
        .header{display:flex;align-items:center;gap:18px;margin-bottom:18px}
        .logo{height:66px}
        h1{margin:0;color:var(--jkuat-dark)}
        .card{background:#fff;padding:22px;border-radius:10px;box-shadow:0 8px 24px rgba(6,59,31,0.06);max-width:900px}
        .avatar{width:96px;height:96px;border-radius:8px;background:#eee;display:inline-block;vertical-align:middle;margin-right:16px;object-fit:cover}
        .kv{font-weight:700;color:var(--jkuat-green);margin-right:6px}
        .meta-row{display:flex;align-items:center;gap:16px}
        hr{border:none;border-top:1px solid #eee;margin:16px 0}
        .info-list{margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .info-item{padding:6px 0}
        .status-pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#eef7ee;color:var(--jkuat-green);font-weight:600}
        @media(max-width:800px){.container{margin-left:0;padding:20px}.info-list{grid-template-columns:1fr} .meta-row{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="../images/2logo.png" alt="JKUAT" class="logo">
        <div>
            <h1>My Housing / Tenant Details</h1>
            <div style="color:#4b6b54">Welcome to the JKUAT Staff Housing Portal</div>
        </div>
    </div>
    <?php if (!$row): ?>
        <div>No tenant record found.</div>
    <?php else: ?>
        <div class="card">
            <div class="meta-row">
                <?php if (!empty($row['photo'])): ?>
                    <img src="../images/uploads/<?php echo htmlspecialchars($row['photo']); ?>" class="avatar" alt="Profile photo">
                <?php else: ?>
                    <div class="avatar" aria-hidden="true"></div>
                <?php endif; ?>
                <div>
                    <div style="font-size:18px;font-weight:700;color:#123b22"><?= htmlspecialchars($row['name']) ?></div>
                    <div style="margin-top:6px"><span class="kv">PF No:</span> <?= htmlspecialchars($row['pf_no']) ?></div>
                    <div style="margin-top:4px"><span class="kv">Email:</span> <?= htmlspecialchars($row['email']) ?></div>
                </div>
            </div>

            <hr>
            <div class="info-list">
                <div class="info-item"><span class="kv">House No:</span> <?= htmlspecialchars($row['house_no']) ?></div>
                <div class="info-item"><span class="kv">Status:</span> <span class="status-pill"><?= htmlspecialchars($row['tenant_status']) ?></span></div>
                <div class="info-item"><span class="kv">Move In:</span> <?= htmlspecialchars($row['move_in_date']) ?></div>
                <div class="info-item"><span class="kv">Move Out:</span> <?= htmlspecialchars($row['move_out_date']) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
