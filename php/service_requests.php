<?php
include '../includes/db.php';
session_start();

// Suggest endpoint for autocomplete (returns JSON array)
if (isset($_GET['suggest']) && isset($_GET['q'])) {
    $q = trim($_GET['q']);
    $out = [];
    if ($q !== '') {
        $like = $q . '%';
        // suggest tenant ids
        $s1 = $conn->prepare("SELECT DISTINCT tenant_id FROM service_requests WHERE tenant_id LIKE ? LIMIT 10");
        $s1->bind_param('s', $like);
        $s1->execute();
        $r1 = $s1->get_result();
        while ($row = $r1->fetch_assoc()) $out[] = $row['tenant_id'];

        // suggest service ids
        $s2 = $conn->prepare("SELECT DISTINCT service_id FROM service_requests WHERE service_id LIKE ? LIMIT 10");
        $s2->bind_param('s', $like);
        $s2->execute();
        $r2 = $s2->get_result();
        while ($row = $r2->fetch_assoc()) $out[] = $row['service_id'];
    }
    header('Content-Type: application/json');
    echo json_encode(array_values(array_unique($out)));
    exit;
}

// Function to sync bill amount to service request
function syncBillAmountToServiceRequest($conn, $service_id, $amount) {
    // Billing implies billable = 1
    $stmt = $conn->prepare("UPDATE service_requests SET bill_amount = ?, is_billable = 1, status = 'Done' WHERE service_id = ?");
    $stmt->bind_param("ds", $amount, $service_id);
    return $stmt->execute();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $service_id = $_POST['service_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE service_id = ?");
    $stmt->bind_param("ss", $status, $service_id);
    if ($stmt->execute()) {
        echo "Status updated successfully";
    } else {
        echo "Error updating status";
    }
    exit;
}

// Handle mark not billable action (AJAX)
if (isset($_POST['mark_not_billable']) && isset($_POST['service_id'])) {
    $sid = $_POST['service_id'];
    // fetch tenant and applicant email for notification
    $infoQ = $conn->prepare("SELECT s.tenant_id, t.applicant_id, a.email FROM service_requests s LEFT JOIN tenants t ON s.tenant_id = t.tenant_id LEFT JOIN applicants a ON t.applicant_id = a.applicant_id WHERE s.service_id = ? LIMIT 1");
    $infoQ->bind_param('s', $sid);
    $infoQ->execute();
    $info = $infoQ->get_result()->fetch_assoc();
    $tenantId = $info['tenant_id'] ?? null;
    $applicantId = $info['applicant_id'] ?? null;
    $email = $info['email'] ?? null;

    // set is_billable = 0, bill_amount = 0 and append note to details
    $u = $conn->prepare("UPDATE service_requests SET is_billable = 0, bill_amount = 0, details = CONCAT(IFNULL(details,''), ' [Marked not billable by admin]') WHERE service_id = ?");
    $u->bind_param('s', $sid);
    header('Content-Type: application/json; charset=utf-8');
    if ($u->execute()) {
        // Log action
        $adminId = $_SESSION['user_id'] ?? 'admin';
        logs_write('info', "Admin {$adminId} marked service {$sid} not billable");

        // Notify tenant if available
        if ($tenantId) {
            $notificationId = uniqid('NT');
            $dateSent = date('Y-m-d H:i:s');
            $message = "Your service request {$sid} was reviewed and marked not billable by administration.";
            if (function_exists('notify_insert_safe')) {
                notify_insert_safe($conn, $notificationId, $adminId, 'tenant', $tenantId, $message, $dateSent, 'unread', 'Admin');
            }
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    require_once __DIR__ . '/../includes/helpers.php';
                    require_once __DIR__ . '/../includes/email.php';
                    $subject = 'Service Request - Not Billable';
                    $htmlBody = build_email_wrapper('<p>' . htmlspecialchars($message) . '</p>');
                    $recipientId = $applicantId ?? $tenantId;
                    if (function_exists('notify_and_email')) {
                        notify_and_email($conn, 'tenant', $recipientId, $email, $subject, $htmlBody, 'Service Request');
                    } else {
                        send_email($email, $subject, $htmlBody, true);
                    }
                } catch (Exception $e) {
                    error_log('Failed sending not-billable email to ' . $email . ': ' . $e->getMessage());
                }
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// Handle bill creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'], $_POST['type_of_bill'], $_POST['amount'])) {
    $service_id = $_POST['service_id'];
    $type_of_bill = $_POST['type_of_bill'];
    $amount = $_POST['amount'];
    $status = "not paid";
    $date_billed = date('Y-m-d');

    // Check if this service already has a bill
    $check_stmt = $conn->prepare("SELECT bill_id FROM bills WHERE service_id = ?");
    $check_stmt->bind_param("s", $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    header('Content-Type: application/json; charset=utf-8');
    if ($check_result->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "This service request has already been billed"]);
        exit;
    }

    // Generate bill ID
    $prefix = "B" . substr($type_of_bill, 0, 1);
    $stmt = $conn->prepare("SELECT bill_id FROM bills WHERE type_of_bill = ? ORDER BY bill_id DESC LIMIT 1");
    $stmt->bind_param("s", $type_of_bill);
    $stmt->execute();
    $result = $stmt->get_result();

    $newNumber = 1;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNum = (int) substr($row['bill_id'], 2);
        $newNumber = $lastNum + 1;
    }

    $bill_id = $prefix . str_pad($newNumber, 3, "0", STR_PAD_LEFT);

    $conn->begin_transaction();
    try {
        // 1. Insert into bills table (without tenant_id)
        $insert = $conn->prepare("INSERT INTO bills (bill_id, service_id, type_of_bill, amount, status, date_billed) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sssdss", $bill_id, $service_id, $type_of_bill, $amount, $status, $date_billed);
        
        if (!$insert->execute()) {
            throw new Exception("Failed to insert bill: " . $conn->error);
        }

        // 2. Update service request with bill amount and mark as Done
        if (!syncBillAmountToServiceRequest($conn, $service_id, $amount)) {
            throw new Exception("Failed to update service request amount: " . $conn->error);
        }

        $conn->commit();
        // After successful billing, notify tenant/applicant if available
        $infoQ = $conn->prepare("SELECT s.tenant_id, t.applicant_id, a.email, a.name FROM service_requests s LEFT JOIN tenants t ON s.tenant_id = t.tenant_id LEFT JOIN applicants a ON t.applicant_id = a.applicant_id WHERE s.service_id = ? LIMIT 1");
        if ($infoQ) {
            $infoQ->bind_param('s', $service_id);
            $infoQ->execute();
            $info = $infoQ->get_result()->fetch_assoc();
            $tenantId = $info['tenant_id'] ?? null;
            $applicantId = $info['applicant_id'] ?? null;
            $email = $info['email'] ?? null;
            $name = $info['name'] ?? 'Tenant';
            $msg = "Your service request {$service_id} has been billed (Bill ID: {$bill_id}). Amount: {$amount}.";
            if ($tenantId && function_exists('notify_insert_safe')) {
                notify_insert_safe($conn, uniqid('NT'), $_SESSION['user_id'] ?? 'admin', 'tenant', $tenantId, $msg, date('Y-m-d H:i:s'), 'unread', 'Billed');
            }
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = 'Service request billed — JKUAT Housing';
                $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p><p>Visit your bills page to view details.</p>';
                if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicantId ?? $tenantId, $email, $subject, $bodyHtml, 'Billed');
            }
        }
        echo json_encode(["success" => true, "bill_id" => $bill_id]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// Allow admin filtering by service_id or tenant_id via GET parameters
$filters = [];
$params = [];
$serviceFilter = '';
$tenantFilter = '';
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    // support searching by prefix of service_id or tenant_id
    $serviceFilter = $q;
    $tenantFilter = $q;
}

$baseQuery = "
    SELECT 
        s.service_id, 
        s.tenant_id, 
        t.house_no, 
        s.type_of_service, 
        s.details, 
        s.bill_amount, 
        s.is_billable,
        s.date, 
        s.status
    FROM service_requests s
    LEFT JOIN tenants t ON s.tenant_id = t.tenant_id
";

if ($q !== '') {
    $filters[] = "(s.service_id LIKE ? OR s.tenant_id LIKE ? )";
    $params[] = $serviceFilter . '%';
    $params[] = $tenantFilter . '%';
}

if (count($filters) > 0) {
    $baseQuery .= ' WHERE ' . implode(' AND ', $filters);
}
$baseQuery .= ' ORDER BY s.date DESC';

// Pagination: get total count then fetch only current page
$countQuery = "SELECT COUNT(*) as cnt FROM service_requests s LEFT JOIN tenants t ON s.tenant_id = t.tenant_id";
if (count($filters) > 0) $countQuery .= ' WHERE ' . implode(' AND ', $filters);
$total = 0;
if (count($params) > 0) {
    $cstmt = $conn->prepare($countQuery);
    $types = str_repeat('s', count($params));
    $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $cres = $cstmt->get_result()->fetch_assoc();
    $total = (int)($cres['cnt'] ?? 0);
} else {
    $cres = mysqli_query($conn, $countQuery);
    $rowc = $cres ? mysqli_fetch_assoc($cres) : null;
    $total = (int)($rowc['cnt'] ?? 0);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? ($_SESSION['sr_per_page'] ?? 10));
if (!in_array($per_page, [10,25,50,100])) $per_page = 10;
$_SESSION['sr_per_page'] = $per_page;
$offset = ($page - 1) * $per_page;

$baseQuery .= " LIMIT " . intval($offset) . ", " . intval($per_page);

if (count($params) > 0) {
    $stmt = $conn->prepare($baseQuery);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = mysqli_query($conn, $baseQuery);
}
?>

<!-- Rest of your HTML remains the same -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Service Requests | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif;
            background-color: #fff;
        }
        .main-content {
            margin-left: 220px;
            padding: 30px;
            background-color: #fff;
            min-height: 100vh;
        }
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f1f1;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }
        .top-header h1 {
            margin: 0;
            font-size: 24px;
            color: #006400;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            cursor: pointer;
        }
        .main-content h2 {
            color: #006400;
            margin-bottom: 20px;
        }
        .table-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .table-controls input[type="text"] {
            padding: 8px;
            width: 300px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background-color: #006400;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ccc;
        }
        .status-pending {
            color: red;
            font-weight: bold;
        }
        .status-done {
            color: green;
            font-weight: bold;
        }
        .sidebar ul li a.active {
            background-color: #ffffff;
            color: #005826;
            font-weight: bold;
        }
        .bill-btn {
            background-color: #28a745;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .bill-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            width: 400px;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            position: relative;
        }
        .modal-content h3 {
            margin-top: 0;
            color: #006400;
        }
        .modal input, .modal select {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .btn-submit {
            margin-top: 15px;
            background-color: #006400;
            color: #fff;
            padding: 10px;
            width: 100%;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
        }
        #billMessage {
            margin-top: 10px;
            padding: 10px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            display: none;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Hamburger Menu Styles */
        .hamburger-menu {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            background: none;
            border: none;
            padding: 10px;
        }
        .hamburger-menu span {
            width: 25px;
            height: 3px;
            background-color: #006400;
            border-radius: 2px;
            transition: 0.3s;
        }
        .sidebar.active {
            left: 0;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }
        .sidebar-overlay.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                z-index: 100;
                transition: left 0.3s ease;
            }
            .hamburger-menu {
                display: flex;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .top-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            .top-header h1 {
                font-size: 18px;
                flex: 1;
            }
        }
        @media (max-width: 480px) {
            .sidebar {
                width: 220px;
            }
            .top-header h1 {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>SERVICE REQUESTS</p>
    <nav>
        <ul>
            <li><a href="csdashboard.php">Dashboard</a></li>
            <li><a href="houses.php">Houses</a></li>
            <li><a href="tenants.php">Tenants</a></li>
            <li><a href="service_requests.php">Service Requests</a></li>
            <li><a href="manage_applicants.php">Manage Applicants</a></li>
            <li><a href="notices.php">Notices</a></li>
            <li><a href="bills.php">Bills</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
    </nav>
</div>

<div class="main-content">
    <div class="top-header">
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </div>

    <h2>Service Requests</h2>

    <div class="table-controls">
                <div style="display:flex;gap:10px;align-items:center;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;position:relative;">
                <input autocomplete="off" type="text" id="qSearch" name="q" placeholder="Search Service ID or Tenant ID" value="<?= htmlspecialchars($q ?? '') ?>" style="width:320px;" onkeyup="suggest(this.value)">
                <div id="suggestions" style="position:absolute; top:36px; left:0; background:#fff; border:1px solid #ddd; display:none; z-index:1000; max-height:220px; overflow:auto; width:320px;"></div>
                <button type="submit" class="btn-submit">Filter</button>
                <a href="service_requests.php" style="margin-left:6px;color:#006400;text-decoration:none;">Clear</a>
            </form>
            <div style="color:#666;font-size:13px;margin-left:12px;">Not all service requests will be billed.</div>
        </div>
    </div>

    <table id="serviceTable">
        <thead>
            <tr>
                <th>Service ID</th>
                <th>Tenant ID</th>
                <th>House No</th>
                <th>Type</th>
                <th>Details</th>
                <th>Bill (KES)</th>
                <th>Billable</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['service_id']) ?></td>
                    <td><?= htmlspecialchars($row['tenant_id']) ?></td>
                    <td><?= htmlspecialchars($row['house_no'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['type_of_service']) ?></td>
                    <td><?= htmlspecialchars($row['details']) ?></td>
                    <td><?= number_format((float)$row['bill_amount'], 2) ?></td>
                    <td>
                        <?php
                            // Billable UX:
                            // - NULL => undecided (blank)
                            // - 0 => No
                            // - 1 => Yes
                            if ($row['is_billable'] === null) {
                                echo '';
                            } else {
                                echo ((int)$row['is_billable'] === 1)
                                    ? '<span style="color:green;font-weight:700;">Yes</span>'
                                    : '<span style="color:#856404;font-weight:700;">No</span>';
                            }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td>
                        <select onchange="updateStatus('<?= $row['service_id'] ?>', this)" 
                            class="<?= strtolower($row['status']) === 'done' ? 'status-done' : 'status-pending' ?>">
                            <option value="Pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Done" <?= $row['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </td>
                    <td>
                        <button class="bill-btn" 
                            onclick="openBillModal('<?= $row['tenant_id'] ?>', '<?= $row['service_id'] ?>', '<?= $row['type_of_service'] ?>', '<?= $row['bill_amount'] ?>')"
                            <?= $row['status'] === 'Done' && (float)$row['bill_amount'] === 0.0 ? 'disabled' : '' ?>>
                            Bill
                        </button>
                        <button style="margin-left:8px;background:#ffc107;color:#000;border:none;padding:6px 8px;border-radius:4px;cursor:pointer;" onclick="markNotBillable('<?= $row['service_id'] ?>', this)">Not Billable</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9">No service requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php
        // Pagination UI
        $total_pages = max(1, ceil($total / $per_page));
        echo '<div style="margin-top:12px; display:flex; align-items:center; gap:12px;">';
        echo '<form method="get" style="display:inline-block; margin:0;">';
        echo 'Per page: <select name="per_page" onchange="this.form.submit()">';
        foreach ([10,25,50,100] as $pp) {
            $sel = ($pp == $per_page) ? 'selected' : '';
            echo '<option value="'.$pp.'" '.$sel.'>'.$pp.'</option>';
        }
        echo '</select>';
        // preserve q param
        if (!empty($q)) echo '<input type="hidden" name="q" value="'.htmlspecialchars($q).'">';
        echo '</form>';
        if ($page > 1) echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $page-1, 'per_page' => $per_page])).'">&laquo; Prev</a>';
        echo ' Page '.intval($page).' of '.intval($total_pages).' ';
        if ($page < $total_pages) echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $page+1, 'per_page' => $per_page])).'">Next &raquo;</a>';
        echo '</div>';
    ?>
</div>

<div id="billModal" class="modal">
    <div class="modal-content">
        <button class="btn-close" onclick="closeBillModal()">&times;</button>
        <h3>Bill Tenant for Service</h3>
        <div id="billMessage"></div>
        <form id="billForm">
            <input type="hidden" name="tenant_id" id="bill_tenant_id">
            <input type="hidden" name="service_id" id="bill_service_id">

            <label for="bill_type_of_bill">Type of Bill</label>
            <input type="text" name="type_of_bill" id="bill_type_of_bill" required>

            <label for="bill_amount">Amount (KES)</label>
            <input type="number" name="amount" id="bill_amount" step="0.01" min="0" required>

            <label>Date Billed</label>
            <input type="date" name="date_billed" value="<?= date('Y-m-d') ?>" required>

            <input type="hidden" name="status" value="not paid">

            <button type="submit" class="btn-submit">Submit Bill</button>
        </form>
    </div>
</div>

<script>
function toggleMenu() {
    alert("Profile menu coming soon.");
}

function openBillModal(tenantId, serviceId, serviceType, amount) {
    if (!tenantId || tenantId === 'undefined' || tenantId === 'null') {
        alert('Cannot create bill: Tenant information is missing');
        return;
    }
    
    document.getElementById('bill_tenant_id').value = tenantId;
    document.getElementById('bill_service_id').value = serviceId;
    document.getElementById('bill_type_of_bill').value = serviceType + " Service";
    document.getElementById('bill_amount').value = amount || '';
    document.getElementById('billModal').style.display = 'block';
    document.getElementById('billMessage').style.display = 'none';
}

function closeBillModal() {
    document.getElementById('billModal').style.display = 'none';
}

// Live client-side filter if the unified search input exists
const liveSearch = document.getElementById('qSearch');
if (liveSearch) {
    liveSearch.addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#serviceTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// Suggestion/autocomplete for unified q search
function suggest(q) {
    const box = document.getElementById('suggestions');
    if (!q) { box.style.display='none'; box.innerHTML=''; return; }
    fetch('service_requests.php?suggest=1&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(list => {
            if (!Array.isArray(list) || list.length === 0) { box.style.display='none'; box.innerHTML=''; return; }
            box.innerHTML = list.map(x => '<div class="suggestion-item" style="padding:8px;border-bottom:1px solid #eee;cursor:pointer;">'+escapeHtml(x)+'</div>').join('');
            box.style.display = 'block';
            document.querySelectorAll('#suggestions .suggestion-item').forEach(el => {
                el.addEventListener('click', function(){ document.getElementById('qSearch').value = this.textContent; box.style.display='none'; });
            });
        });
}

function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Mark not billable handler
function markNotBillable(serviceId, btn) {
    if (!confirm('Mark this service request as not billable?')) return;
    btn.disabled = true;
    fetch('service_requests.php', { method: 'POST', body: new URLSearchParams({ mark_not_billable: 1, service_id: serviceId }) })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const row = btn.closest('tr');
                if (row) {
                    const billAmountCell = row.querySelector('td:nth-child(6)');
                    const billableCell = row.querySelector('td:nth-child(7)');
                    if (billAmountCell) billAmountCell.textContent = '0.00';
                    if (billableCell) billableCell.innerHTML = '<span style="color:#856404;font-weight:700;">No</span>';
                    const billBtn = row.querySelector('.bill-btn');
                    if (billBtn) billBtn.disabled = true;
                }
                alert('Marked not billable.');
            } else {
                alert('Failed: ' + (data && data.error ? data.error : 'unknown'));
                btn.disabled = false;
            }
        }).catch(err => { console.error(err); alert('Request failed'); btn.disabled=false; });
}

document.getElementById("billForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    // Validate tenant_id exists
    const tenantId = formData.get('tenant_id');
    if (!tenantId) {
        const billMessage = document.getElementById("billMessage");
        billMessage.innerText = "Error: Tenant information is missing";
        billMessage.className = "error-message";
        billMessage.style.display = 'block';
        return;
    }

    // Show loading state
    const submitBtn = form.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = "Processing...";
    
    fetch('service_requests.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.json();
    })
    .then(data => {
        const billMessage = document.getElementById("billMessage");
        if (data.success) {
            billMessage.innerText = "Bill created successfully! Bill ID: " + data.bill_id;
            billMessage.className = "";
            billMessage.style.display = 'block';

            // Update the row UI immediately to reflect billable = Yes
            const serviceId = formData.get('service_id');
            const amount = formData.get('amount');
            if (serviceId) {
                const rows = document.querySelectorAll('#serviceTable tbody tr');
                rows.forEach(r => {
                    const sidCell = r.querySelector('td:first-child');
                    if (!sidCell || sidCell.textContent.trim() !== String(serviceId).trim()) return;
                    const billAmountCell = r.querySelector('td:nth-child(6)');
                    const billableCell = r.querySelector('td:nth-child(7)');
                    if (billAmountCell && amount !== null && amount !== '') {
                        const amt = Number(amount);
                        billAmountCell.textContent = isFinite(amt) ? amt.toFixed(2) : String(amount);
                    }
                    if (billableCell) billableCell.innerHTML = '<span style="color:green;font-weight:700;">Yes</span>';
                });
            }

            // Close modal after a short moment
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = "Submit Bill";
                closeBillModal();
            }, 700);
        } else {
            billMessage.innerText = "Error: " + (data.error || "Failed to create bill");
            billMessage.className = "error-message";
            billMessage.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Bill";
        }
    })
    .catch(err => {
        console.error('Error:', err);
        const billMessage = document.getElementById("billMessage");
        billMessage.innerText = "Error submitting bill. Please try again.";
        billMessage.className = "error-message";
        billMessage.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit Bill";
    });
});

function updateStatus(serviceId, selectElement) {
    const status = selectElement.value;
    const formData = new FormData();
    formData.append('update_status', true);
    formData.append('service_id', serviceId);
    formData.append('status', status);
    
    // Show loading state
    selectElement.disabled = true;
    
    fetch('service_requests.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.text();
    })
    .then(data => {
        console.log(data);
        selectElement.className = (status.toLowerCase() === 'done') ? 'status-done' : 'status-pending';
        
        // Update bill button state
        const row = selectElement.closest('tr');
        const billBtn = row.querySelector('.bill-btn');
        if (billBtn) {
            if (status.toLowerCase() === 'done') {
                const billAmount = row.querySelector('td:nth-child(6)').textContent.trim();
                billBtn.disabled = billAmount === '0.00' || billAmount === '';
            } else {
                billBtn.disabled = false;
            }
        }
    })
    .catch(err => {
        console.error('Error updating status:', err);
        alert('Failed to update status. Please try again.');
        // Revert the selection
        selectElement.value = (status.toLowerCase() === 'done') ? 'Pending' : 'Done';
    })
    .finally(() => {
        selectElement.disabled = false;
    });
}

// Hamburger Menu Toggle Function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Close sidebar when overlay is clicked
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
    
    // Close sidebar when a link is clicked
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    });
});
</script>

</body>
</html>