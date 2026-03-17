<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
// session is started in includes/init.php

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

// Resolve tenant id from session or DB to give immediate tenant access
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id && !empty($_SESSION['applicant_id'])) {
    require_once __DIR__ . '/../includes/helpers.php';
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t) { $_SESSION['tenant_id'] = $t['tenant_id']; $tenant_id = $t['tenant_id']; }
}

// Handle optional success message
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Notices | JKUAT Housing</title>
    <style>
        body{margin:0;font-family:Arial, sans-serif;background-color:#fff}
        .main-content{padding:30px;max-width:1200px;margin:0 auto;min-height:100vh}

        .top-header{display:flex;justify-content:space-between;align-items:center;background:#f1f1f1;padding:15px 20px;margin-bottom:20px;border-bottom:1px solid #ccc}
        .top-header h1{margin:0;font-size:24px;color:#006400}
        .user-icon{width:40px;height:40px;cursor:pointer}

        h2{color:#006400;margin:0 0 18px}
        h3{margin:0 0 12px;color:#006400}

        .grid{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start}
        @media (max-width: 900px){.grid{grid-template-columns:1fr}}

        .card{background:#fff;border:1px solid #e6e6e6;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);padding:16px}
        .field{margin-top:12px}
        label{display:block;margin:0 0 6px;font-weight:700;color:#333}
        input, textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-family:inherit;font-size:14px;box-sizing:border-box}
        textarea{resize:vertical;min-height:120px}

        .note{background:#d4edda;padding:10px;border:1px solid #c3e6cb;border-radius:6px;margin-bottom:12px}
        .warn{background:#fff3cd;padding:12px;border:1px solid #ffeeba;border-radius:6px}

        table{table-layout:fixed;width:100%;border-collapse:collapse;background-color:#fff;box-shadow:0 2px 6px rgba(0,0,0,0.1);border-radius:8px;overflow:hidden}
        thead{background-color:#006400;color:#fff}
        th, td{padding:12px;text-align:left;border:1px solid #ccc;vertical-align:top;word-wrap:break-word}

        .action-btn{padding:8px 12px;border:none;border-radius:4px;cursor:pointer;font-size:14px}
        .btn-primary{background:#006400;color:#fff}
        .btn-danger{background:#dc3545;color:#fff}
        .btn-primary:hover{background:#005826}
        .btn-danger:hover{background:#c82333}

        .status-active{background-color:#28a745;color:#fff;padding:3px 8px;border-radius:4px;display:inline-block}
        .status-revoked{background-color:#dc3545;color:#fff;padding:3px 8px;border-radius:4px;display:inline-block}
        .status-fulfilled{background-color:#17a2b8;color:#fff;padding:3px 8px;border-radius:4px;display:inline-block}
    </style>
</head>
<body>
<div class="main-content">
    <div class="top-header">
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="alert('Profile menu coming soon.');">
    </div>

    <h2>My Notices</h2>
    <?php if ($msg): ?>
        <div class="note"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>Place a Notice / Move-out Request</h3>

            <?php if (!$tenant_id): ?>
                <div class="warn">You do not have a tenant record. Only tenants can place notices.</div>
            <?php else: ?>
                <form method="post" action="submit_notice.php">
                    <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($tenant_id); ?>">

                    <div class="field">
                        <label for="details">Details</label>
                        <textarea id="details" name="details" required></textarea>
                    </div>

                    <div class="field">
                        <label for="move_out_date">Move Out Date</label>
                        <input type="date" id="move_out_date" name="move_out_date" required>
                    </div>

                    <div class="field">
                        <button class="action-btn btn-primary" type="submit">Submit Notice</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div>
            <h3>Your Notices</h3>
            <?php
            // Fetch notices for tenant
            $notices = [];
            if ($tenant_id) {
                $p = $conn->prepare("SELECT notice_id, details, date_sent, notice_end_date, status FROM notices WHERE tenant_id = ? ORDER BY date_sent DESC");
                $p->bind_param('s', $tenant_id);
                $p->execute();
                $notices = $p->get_result();
            }
            ?>

            <table>
                <thead>
                    <tr>
                        <th style="width: 14%;">Notice ID</th>
                        <th style="width: 34%;">Details</th>
                        <th style="width: 16%;">Date Sent</th>
                        <th style="width: 16%;">Move Out Date</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($notices && $notices->num_rows > 0): while ($nr = $notices->fetch_assoc()): ?>
                    <?php
                    $status = strtolower($nr['status'] ?? 'active');
                    $statusClass = ($status === 'active') ? 'status-active' : (($status === 'revoked') ? 'status-revoked' : 'status-fulfilled');
                    $canRevoke = false;
                    try {
                        $ds = new DateTime($nr['date_sent']);
                        $now = new DateTime();
                        if ($now < $ds && $status === 'active') $canRevoke = true;
                    } catch (Exception $e) {}
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($nr['notice_id']) ?></td>
                        <td><?= htmlspecialchars($nr['details']) ?></td>
                        <td><?= htmlspecialchars($nr['date_sent']) ?></td>
                        <td><?= htmlspecialchars($nr['notice_end_date']) ?></td>
                        <td><span class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                        <td>
                            <?php if ($canRevoke): ?>
                                <button type="button" class="action-btn btn-danger" onclick="revokeNotice('<?= htmlspecialchars($nr['notice_id']) ?>')">Revoke</button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6">No notices found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

        <script>
        function revokeNotice(id) {
            if (!confirm('Revoke this notice?')) return;
            fetch('update_notice_status.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'notice_id=' + encodeURIComponent(id) + '&status=revoked'
            }).then(r=>r.json()).then(j=>{
                if (j.success) location.reload(); else alert('Failed: ' + (j.error||'unknown'));
            }).catch(e=>{ console.error(e); alert('Error'); });
        }
        </script>
    </div>
</body>
</html>
