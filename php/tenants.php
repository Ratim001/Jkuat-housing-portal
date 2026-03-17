<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

// Handle notification form submission directly here
$notificationSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'], $_POST['message'])) {
    $tenant_id = $_POST['tenant_id'];
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $recipient_type = 'tenant';
    $user_id = 'user002'; // Replace with actual logged-in user ID if needed

    // Generate next notification ID
    $getLastId = mysqli_query($conn, "SELECT notification_id FROM notifications ORDER BY notification_id DESC LIMIT 1");
    if (mysqli_num_rows($getLastId) > 0) {
        $row = mysqli_fetch_assoc($getLastId);
        $lastId = intval(substr($row['notification_id'], 2)); // Remove 'NT' prefix
        $newId = 'NT' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newId = 'NT001';
    }

    // Use the safe notifier to create a notification with title 'Admin'
    $notificationId = uniqid('NT');
    $adminId = $user_id;
    $dateSent = date('Y-m-d H:i:s');
    // Fetch tenant/applicant name for greeting
    $resA = mysqli_query($conn, "SELECT name FROM applicants WHERE applicant_id = (SELECT applicant_id FROM tenants WHERE tenant_id = '{$tenant_id}' LIMIT 1)");
    $an = ($resA && $r = mysqli_fetch_assoc($resA)) ? $r['name'] : 'Tenant';
    $msgFormatted = "Dear \"{$an}\": " . $message;
    if (function_exists('notify_insert_safe')) {
        notify_insert_safe($conn, $notificationId, $adminId, 'tenant', $tenant_id, $msgFormatted, $dateSent, 'unread', 'Admin');
        $notificationSuccess = true;
    } else {
        // Fallback: insert directly with title column if present
        $insertQuery = "INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, title, date_sent, date_received) VALUES ('{$notificationId}', '{$adminId}', 'tenant', '{$tenant_id}', '" . mysqli_real_escape_string($conn, $msgFormatted) . "', 'Admin', NOW(), NOW())";
        $notificationSuccess = mysqli_query($conn, $insertQuery);
    }
}

// Load tenant list
// Only include tenants whose applicant record was created with role='tenant'
$query = "
    SELECT 
        t.tenant_id,
        t.house_no,
        t.move_in_date,
        t.move_out_date,
        t.status,
        a.pf_no,
        a.name,
        a.email,
        a.role
    FROM tenants t
    JOIN applicants a ON t.applicant_id = a.applicant_id
    WHERE a.role = 'tenant'
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Tenants | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <style>
    table {
        width: 100%;
        margin-top: 20px;
        border-collapse: collapse;
        background: #fff;
    }

    th, td {
        padding: 10px;
        border: 1px solid #ddd;
    }

    th {
        background: #006400;
        color: #fff;
    }

    tr:hover {
        background-color: rgb(239, 241, 240);
    }

    button {
        padding: 5px 10px;
        border: none;
        border-radius: 5px;
        color: #fff;
        margin-right: 5px;
        cursor: pointer;
    }

    .notify-btn { background-color: #007bff; }
    .terminate-btn { background-color: #dc3545; }
    
    .status-active {
        background-color: #09da3aff;
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
    }
    
    .status-terminated {
        background-color: #1a1818ff;
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border-radius: 10px;
        width: 400px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        position: relative;
    }

    .modal h3 {
        margin-top: 0;
        color: #006400;
    }

    .modal label {
        display: block;
        margin-top: 10px;
        font-weight: bold;
    }

    .modal input,
    .modal select,
    .modal textarea {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
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
        background: transparent;
        border: none;
        font-size: 22px;
        color: #999;
        cursor: pointer;
    }

    .success-message {
        background: #d4edda;
        color: #155724;
        padding: 10px;
        margin: 20px 0;
        border: 1px solid #c3e6cb;
        border-radius: 5px;
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
            table {
                font-size: 12px;
            }
            th, td {
                padding: 6px;
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
    <p>TENANTS</p>
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
    <header class="top-header">
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </header>

    <h2 style="color: #006400;">Tenants List</h2>

    <table>
        <thead>
            <tr>
                <th>PF Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>House No</th>
                <th>Move In</th>
                <th>Move Out</th>
                <th>Status</th>
                <th>Manage Tenant</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)) { 
                $statusClass = ($row['status'] == 'Active') ? 'status-active' : 'status-terminated';
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['pf_no']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['house_no']) ?></td>
                    <td><?= htmlspecialchars($row['move_in_date']) ?></td>
                    <td><?= htmlspecialchars($row['move_out_date']) ?></td>
                    <td><span class="<?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td>
                        <button class="notify-btn" onclick="openNotifyForm('<?= $row['tenant_id'] ?>')">Notify</button>
                        <button class="terminate-btn" onclick="terminateTenant('<?= $row['tenant_id'] ?>')" <?= ($row['status'] == 'Terminated') ? 'disabled' : '' ?>>Terminate</button>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Notify Modal -->
    <div class="modal" id="notifyModal">
        <div class="modal-content">
            <button class="btn-close" onclick="closeNotifyForm()">&times;</button>
            <h3>Send Notification</h3>
            <form method="POST">
                <input type="hidden" id="notify_tenant_id" name="tenant_id">
                <label>Message:</label>
                <textarea name="message" rows="4" required></textarea>
                <button type="submit" class="btn-submit">Send</button>
            </form>
        </div>
    </div>

    <!-- Terminate Form -->
    <form id="terminateForm" action="terminate_tenant.php" method="POST" style="display: none;">
        <input type="hidden" name="tenant_id" id="terminate_tenant_id">
    </form>
</div>

<script>
function toggleMenu() {
    alert("Menu clicked");
}

function openNotifyForm(tenantId) {
    document.getElementById('notify_tenant_id').value = tenantId;
    document.getElementById('notifyModal').style.display = 'block';
}

function closeNotifyForm() {
    document.getElementById('notifyModal').style.display = 'none';
}

function terminateTenant(tenantId) {
    if (confirm("Are you sure you want to terminate this tenant?")) {
        document.getElementById('terminate_tenant_id').value = tenantId;
        document.getElementById('terminateForm').submit();
    }
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