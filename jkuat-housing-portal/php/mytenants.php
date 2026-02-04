<?php
session_start();
include '../includes/db.php';

// Force a test tenant ID for now
if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 'T001';
}
$tenant_id = $_SESSION['tenant_id'];

// Handle service request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $type_of_service = $_POST['service-type'];
    $date = $_POST['request-date'];
    $details = $_POST['request-details'];
    
    // Generate service ID
    $result = $conn->query("SELECT service_id FROM service_requests ORDER BY service_id DESC LIMIT 1");
    $service_id = $result->num_rows > 0 ? 
        'S'.str_pad((int)substr($result->fetch_assoc()['service_id'], 1) + 1, 3, '0', STR_PAD_LEFT) : 
        'S001';

    $stmt = $conn->prepare("INSERT INTO service_requests (service_id, tenant_id, type_of_service, bill_amount, date, status, details) VALUES (?, ?, ?, 0, ?, 'pending', ?)");
    $stmt->bind_param("sssss", $service_id, $tenant_id, $type_of_service, $date, $details);
    
    if ($stmt->execute()) {
        $_SESSION['service_success'] = "Service request submitted successfully!";
    } else {
        $_SESSION['service_error'] = "Error submitting request.";
    }
    $stmt->close();
    header("Location: mytenants.php?section=serviceRequest");
    exit();
}

// Handle notice submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_notice'])) {
    $details = mysqli_real_escape_string($conn, $_POST['details']);
    $notice_end_date = mysqli_real_escape_string($conn, $_POST['notice_end_date']);
    $date_sent = date('Y-m-d');

    // Generate notice ID
    $result = $conn->query("SELECT notice_id FROM notices ORDER BY notice_id DESC LIMIT 1");
    $notice_id = 'N001';
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['notice_id'];
        $num = (int)substr($last_id, 1) + 1;
        $notice_id = 'N' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    $stmt = $conn->prepare("INSERT INTO notices (notice_id, tenant_id, details, date_sent, notice_end_date, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssss", $notice_id, $tenant_id, $details, $date_sent, $notice_end_date);

    if ($stmt->execute()) {
        $_SESSION['notice_success'] = "Notice submitted successfully!";
    } else {
        $_SESSION['notice_error'] = "Error submitting notice.";
    }
    $stmt->close();
    header("Location: mytenants.php?section=serveNotice");
    exit();
}

// Handle notice deletion
if (isset($_GET['delete_notice'])) {
    $notice_id = $_GET['delete_notice'];
    $conn->query("DELETE FROM notices WHERE notice_id = '$notice_id'");
    $_SESSION['notice_success'] = "Notice deleted successfully!";
    header("Location: mytenants.php?section=serveNotice");
    exit();
}

// Handle notice revocation/restoration
if (isset($_GET['toggle_notice_status'])) {
    $notice_id = $_GET['toggle_notice_status'];
    // Get current status
    $result = $conn->query("SELECT status FROM notices WHERE notice_id = '$notice_id'");
    if ($result && $result->num_rows > 0) {
        $current_status = $result->fetch_assoc()['status'];
        $new_status = ($current_status == 'active') ? 'revoked' : 'active';
        $conn->query("UPDATE notices SET status = '$new_status' WHERE notice_id = '$notice_id'");
        $_SESSION['notice_success'] = "Notice status updated successfully!";
    }
    header("Location: mytenants.php?section=serveNotice");
    exit();
}

// Handle bill dispute
if (isset($_GET['dispute_bill'])) {
    $bill_id = $_GET['dispute_bill'];
    $conn->query("UPDATE bills SET statuses = 'disputed' WHERE bill_id = '$bill_id'");
    $_SESSION['bill_success'] = "Bill has been disputed successfully!";
    header("Location: mytenants.php?section=bills");
    exit();
}

// Fetch service requests with bill_amount
$service_stmt = $conn->prepare("SELECT service_id, type_of_service, details, date, status, bill_amount FROM service_requests WHERE tenant_id = ? ORDER BY date DESC");
$service_stmt->bind_param("s", $tenant_id);
$service_stmt->execute();
$service_requests = $service_stmt->get_result();
$service_stmt->close();

// Fetch notices with status
$notices_result = $conn->query("SELECT * FROM notices WHERE tenant_id = '$tenant_id' ORDER BY date_sent DESC");

// Fetch all bills (since bills table doesn't have tenant_id)
$bills_result = $conn->query("SELECT * FROM bills ORDER BY date_billed DESC");

// Get notification count
$notification_count = 0;
try {
    $count_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE recipient_type='tenant' AND recipient_id='$tenant_id'");
    if ($count_result) {
        $notification_count = $count_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    $notification_count = 0;
}

$tenant_name = isset($_SESSION['tenant_name']) ? $_SESSION['tenant_name'] : 'Tenant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Dashboard | JKUAT Staff Housing Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #fff; }
        
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            background: #fff; padding: 10px 20px; border-bottom: 1px solid #ccc;
            position: fixed; top: 0; left: 0; width: 100%; height: 70px; z-index: 10;
        }
        .topbar h2 { color: rgb(65, 172, 65); flex-grow: 1; text-align: center; }
        .topbar .icons { display: flex; gap: 20px; align-items: center; }
        .profile-icon { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }
        
        .sidebar {
            position: fixed; top: 70px; left: 0; width: 250px; height: calc(100vh - 70px);
            background: rgb(65, 172, 65); padding-top: 20px;
        }
        .sidebar h3 { padding: 15px 25px; color: white; font-weight: bold; }
        .sidebar a {
            display: flex; align-items: center; padding: 15px 25px; color: white; text-decoration: none;
            font-weight: bold; transition: background 0.3s;
        }
        .sidebar a:hover { background: red; }
        .sidebar a span { margin-right: 10px; font-size: 18px; }
        
        .main { margin-left: 250px; padding: 90px 40px 40px; }
        .content-section { display: none; }
        .content-section.active { display: block; }
        .content-section h3 { color: rgb(65, 172, 65); border-bottom: 2px solid rgb(65, 172, 65); padding-bottom: 10px; margin-bottom: 20px; }
        
        .btn {
            padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer;
            font-weight: bold; background: rgb(65, 172, 65); color: white;
        }
        .btn:hover { background: rgb(45, 152, 45); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: rgb(65, 172, 65); color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        
        .modal {
            display: none; position: fixed; z-index: 1001; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white; margin: 10% auto; padding: 30px; width: 80%;
            max-width: 500px; border-radius: 10px;
        }
        .close-btn { float: right; font-size: 28px; cursor: pointer; color: #aaa; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .flash-message {
            padding: 15px; margin-bottom: 20px; border-radius: 4px;
            animation: fadeOut 5s forwards; animation-delay: 2s;
        }
        .success-message { background: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .error-message { background: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        
        @keyframes fadeOut { to { opacity: 0; height: 0; padding: 0; margin: 0; } }
        
        .serve-notice-form {
            background: #f9f9f9; padding: 20px; border-radius: 8px;
            margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .serve-notice-form h4 { margin-top: 0; color: rgb(65, 172, 65); }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .action-buttons a, .action-buttons button {
            padding: 5px 10px; border-radius: 3px; text-decoration: none;
            font-size: 14px; margin-right: 5px; border: none; cursor: pointer;
        }
        .edit-btn { background: #4CAF50; color: white; }
        .delete-btn { background: #f44336; color: white; }
        .revoke-btn { background: #FF9800; color: white; }
        .restore-btn { background: #2196F3; color: white; }
        .dispute-btn { background: #9C27B0; color: white; }
        
        .status-active { color: green; font-weight: bold; }
        .status-revoked { color: orange; font-weight: bold; }
        .status-paid { color: green; font-weight: bold; }
        .status-not-paid { color: red; font-weight: bold; }
        .status-disputed { color: purple; font-weight: bold; }
        
        .dropdown {
            display: none; position: absolute; top: 30px; right: 0;
            background: white; border: 1px solid #ccc; width: 200px; z-index: 100;
        }
        .dropdown a { display: block; padding: 10px; color: black; text-decoration: none; }
        .dropdown a:hover { background: #f1f1f1; }
        
        .icon-button { cursor: pointer; position: relative; padding: 10px; }
        .notification-badge {
            position: absolute; top: -5px; right: -5px; background: red;
            color: white; border-radius: 50%; width: 18px; height: 18px;
            font-size: 12px; display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="logo">
            <img src="../images/2logo.png" alt="JKUAT Logo" style="height: 70px;">
        </div>
        <h2>JKUAT Staff Housing Portal</h2>
        <div class="icons">
            <a href="notifications.php" style="position: relative; text-decoration: none; color: inherit; padding: 10px;">
                🔔
                <?php if ($notification_count > 0): ?>
                    <span class="notification-badge"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a>
            <div class="icon-button" onclick="toggleDropdown('profileDropdown')">
                <img src="../images/p-icon.png" alt="Profile" class="profile-icon">
                <div class="dropdown" id="profileDropdown">
                    <a href="#"><?php echo htmlspecialchars($tenant_name); ?></a>
                    <a href="#">Profile</a>
                    <a href="logouts.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar">
        <h3><b>TENANT</b></h3>
        <a href="mytenants.php?section=serviceRequest"><span style="color: white; font-size: 18px;">🔧</span> Service Request</a>
        <a href="mytenants.php?section=serveNotice"><span style="color: white; font-size: 18px;">📝</span> Serve Notice</a>
        <a href="mytenants.php?section=bills"><span style="color: white; font-size: 18px;">💰</span> Bills</a>
        
    </div>

    <div class="main">
        <!-- Service Request Section -->
        <div id="serviceRequest" class="content-section <?php echo (!isset($_GET['section']) || $_GET['section'] === 'serviceRequest') ? 'active' : ''; ?>">
            <h3>Service Requests</h3>
            
            <?php if (isset($_SESSION['service_success'])): ?>
                <div class="flash-message success-message">
                    <?php echo $_SESSION['service_success']; unset($_SESSION['service_success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['service_error'])): ?>
                <div class="flash-message error-message">
                    <?php echo $_SESSION['service_error']; unset($_SESSION['service_error']); ?>
                </div>
            <?php endif; ?>

            <button class="btn" onclick="openModal('addRequestModal')">+ Add New Request</button>
            
            <table>
                <thead>
                    <tr>
                        <th>Service Requested</th>
                        <th>Details</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($service_requests->num_rows > 0): ?>
                        <?php while($row = $service_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['type_of_service']); ?></td>
                                <td><?php echo htmlspecialchars($row['details']); ?></td>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo 'KES ' . number_format($row['bill_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No service requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Serve Notice Section -->
        <div id="serveNotice" class="content-section <?php echo (isset($_GET['section']) && $_GET['section'] === 'serveNotice') ? 'active' : ''; ?>">
            <h3>Serve Notice</h3>
            
            <?php if (isset($_SESSION['notice_success'])): ?>
                <div class="flash-message success-message">
                    <?php echo $_SESSION['notice_success']; unset($_SESSION['notice_success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['notice_error'])): ?>
                <div class="flash-message error-message">
                    <?php echo $_SESSION['notice_error']; unset($_SESSION['notice_error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="serve-notice-form">
                <h4>+ ADD NOTICE</h4>
                <form method="POST">
                    <div class="form-group">
                        <label for="notice_end_date">Vacation Date</label>
                        <input type="date" id="notice_end_date" name="notice_end_date" required>
                    </div>
                    <div class="form-group">
                        <label for="details">Notice Details</label>
                        <textarea id="details" name="details" placeholder="Enter your notice details here..." required></textarea>
                    </div>
                    <button type="submit" name="submit_notice" class="btn">SUBMIT NOTICE</button>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>NOTICE ID</th>
                        <th>DETAILS</th>
                        <th>DATE SENT</th>
                        <th>VACATION DATE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($notices_result->num_rows > 0): ?>
                        <?php while($notice = $notices_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($notice['notice_id']); ?></td>
                                <td><?php echo htmlspecialchars($notice['details']); ?></td>
                                <td><?php echo htmlspecialchars($notice['date_sent']); ?></td>
                                <td><?php echo htmlspecialchars($notice['notice_end_date']); ?></td>
                                <td class="<?php echo $notice['status'] === 'active' ? 'status-active' : 'status-revoked'; ?>">
                                    <?php echo strtoupper(htmlspecialchars($notice['status'])); ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($notice['status'] === 'active'): ?>
                                        <a href="?section=serveNotice&toggle_notice_status=<?php echo $notice['notice_id']; ?>" class="revoke-btn" onclick="return confirm('Are you sure you want to revoke this notice?')">REVOKE</a>
                                    <?php else: ?>
                                        <a href="?section=serveNotice&toggle_notice_status=<?php echo $notice['notice_id']; ?>" class="restore-btn" onclick="return confirm('Are you sure you want to restore this notice?')">RESTORE</a>
                                    <?php endif; ?>
                                    <a href="?section=serveNotice&delete_notice=<?php echo $notice['notice_id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this notice?')">DELETE</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No notices found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Bills Section -->
        <div id="bills" class="content-section <?php echo (isset($_GET['section']) && $_GET['section'] === 'bills') ? 'active' : ''; ?>">
            <h3>My Bills</h3>
            
            <?php if (isset($_SESSION['bill_success'])): ?>
                <div class="flash-message success-message">
                    <?php echo $_SESSION['bill_success']; unset($_SESSION['bill_success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['bill_error'])): ?>
                <div class="flash-message error-message">
                    <?php echo $_SESSION['bill_error']; unset($_SESSION['bill_error']); ?>
                </div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Service ID</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date Billed</th>
                        <th>Date Settled</th>
                        <th>Payment Status</th>
                        <th>Bill Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bills_result && $bills_result->num_rows > 0): ?>
                        <?php while($bill = $bills_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                <td><?php echo htmlspecialchars($bill['service_id']); ?></td>
                                <td><?php echo htmlspecialchars($bill['type_of_bill']); ?></td>
                                <td><?php echo 'KES ' . number_format($bill['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($bill['date_billed']); ?></td>
                                <td><?php echo $bill['date_settled'] ? htmlspecialchars($bill['date_settled']) : 'Not paid'; ?></td>
                                <td class="<?php echo $bill['status'] === 'paid' ? 'status-paid' : 'status-not-paid'; ?>">
                                    <?php echo strtoupper(htmlspecialchars($bill['status'])); ?>
                                </td>
                                <td class="<?php echo $bill['statuses'] === 'active' ? 'status-active' : 'status-disputed'; ?>">
                                    <?php echo strtoupper(htmlspecialchars($bill['statuses'])); ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($bill['statuses'] === 'active' && $bill['status'] !== 'paid'): ?>
                                        <a href="?section=bills&dispute_bill=<?php echo $bill['bill_id']; ?>" class="dispute-btn" onclick="return confirm('Are you sure you want to dispute this bill?')">DISPUTE</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No bills found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Service Request Modal -->
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('addRequestModal')">&times;</span>
            <h3>Add Service Request</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="service-type">Type of Service</label>
                    <input type="text" id="service-type" name="service-type" placeholder="e.g., Plumbing, Electrical" required>
                </div>
                <div class="form-group">
                    <label for="request-date">Date</label>
                    <input type="date" id="request-date" name="request-date" required>
                </div>
                <div class="form-group">
                    <label for="request-details">Details</label>
                    <textarea id="request-details" name="request-details" rows="4" placeholder="Describe the issue..." required></textarea>
                </div>
                <button type="submit" name="submit_request" class="btn">Submit Request</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            history.pushState(null, null, `?section=${sectionId}`);
        }

        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            const allDropdowns = document.querySelectorAll('.dropdown');
            allDropdowns.forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        window.onclick = function(event) {
            if (!event.target.matches('.icon-button') && !event.target.closest('.icon-button') && !event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(d => d.style.display = 'none');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Set default dates
            const today = new Date();
            const futureDate = new Date();
            futureDate.setDate(today.getDate() + 30);
            
            const noticeDate = document.getElementById('notice_end_date');
            if (noticeDate) {
                noticeDate.value = futureDate.toISOString().split('T')[0];
                noticeDate.min = today.toISOString().split('T')[0];
            }
            
            const requestDate = document.getElementById('request-date');
            if (requestDate) {
                requestDate.value = today.toISOString().split('T')[0];
            }
            
            // Check URL for section parameter
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            if (section) {
                showSection(section);
            }
        });
    </script>
</body>
</html>