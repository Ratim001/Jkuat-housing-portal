<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

$filter = $_GET['filter'] ?? '';
$sql = "SELECT * FROM applicants";
if ($filter === 'missing') {
    $sql .= " WHERE (name IS NULL OR name = '' OR email IS NULL OR email = '' OR contact IS NULL OR contact = '')";
}
$applicants = $conn->query($sql);
$applications = mysqli_query($conn, "SELECT * FROM applications ORDER BY date DESC");
$ballots = mysqli_query($conn, "SELECT * FROM balloting ORDER BY date_of_ballot DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['user_id'] ?? 'user002';

    // START BALLOT: enable ballots and notify applicants
    if (isset($_POST['start_ballot'])) {
        $conn->query("UPDATE ballot_control SET is_open = 1, start_date = NOW(), end_date = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE id = 1");

        $res = mysqli_query($conn, "SELECT applicant_id, name FROM applicants");
        $closing = date('Y-m-d', strtotime('+14 days'));
        $dateSent = date('Y-m-d H:i:s');
        while ($row = mysqli_fetch_assoc($res)) {
            $notificationId = uniqid('NT');
            $recipientId = $row['applicant_id'];
            $name = $row['name'] ?: 'Applicant';
            $message = "Hey \"{$name}\" this is to notify you ballots/bidding has begun. Closing date will be {$closing}";
            $title = 'Admin';
            $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('$notificationId', '$adminId', 'applicant', '$recipientId', '$title', '" . mysqli_real_escape_string($conn, $message) . "', '$dateSent', '$dateSent', 'unread')");
        }

        header("Location: manage_applicants.php");
        exit;

    // END BALLOT: disable ballots and notify applicants
    } elseif (isset($_POST['end_ballot'])) {
        $conn->query("UPDATE ballot_control SET is_open = 0, end_date = NOW() WHERE id = 1");

        $res = mysqli_query($conn, "SELECT applicant_id, name FROM applicants");
        $dateSent = date('Y-m-d H:i:s');
        while ($row = mysqli_fetch_assoc($res)) {
            $notificationId = uniqid('NT');
            $recipientId = $row['applicant_id'];
            $name = $row['name'] ?: 'Applicant';
            $message = "Hey \"{$name}\" this is to notify you ballots/bidding has closed.";
            $title = 'Admin';
            $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('$notificationId', '$adminId', 'applicant', '$recipientId', '$title', '" . mysqli_real_escape_string($conn, $message) . "', '$dateSent', '$dateSent', 'unread')");
        }

        header("Location: manage_applicants.php");
        exit;

    // CHOOSE WINNERS: randomly pick winners per house, create tenants, and notify winners/losers
    } elseif (isset($_POST['choose_winner'])) {
        // ensure ballots are closed when choosing winners
        $conn->query("UPDATE ballot_control SET is_open = 0, end_date = NOW() WHERE id = 1");

        $groupedApps = mysqli_query($conn, "SELECT house_no FROM applications WHERE status = 'Pending' GROUP BY house_no");

        while ($group = mysqli_fetch_assoc($groupedApps)) {
            $house = $group['house_no'];
            $candidates = mysqli_query($conn, "SELECT * FROM applications WHERE house_no = '$house' AND status = 'Pending'");

            $apps = [];
            while ($c = mysqli_fetch_assoc($candidates)) {
                $apps[] = $c;
            }

            if (count($apps) > 0) {
                $winner = $apps[array_rand($apps)];

                $update = $conn->prepare("UPDATE applications SET status = 'Won' WHERE application_id = ?");
                $update->bind_param("s", $winner['application_id']);
                $update->execute();

                $lastTenant = mysqli_query($conn, "SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
                $nextId = 'T001';
                if ($t = mysqli_fetch_assoc($lastTenant)) {
                    $num = (int)substr($t['tenant_id'], 1) + 1;
                    $nextId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
                }

                $today = date('Y-m-d');
                $insert = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date) VALUES (?, ?, ?, ?)");
                $insert->bind_param("ssss", $nextId, $winner['applicant_id'], $winner['house_no'], $today);
                $insert->execute();

                $conn->query("UPDATE applicants SET status = 'Tenant' WHERE applicant_id = '{$winner['applicant_id']}'");

                // Notify winner
                $adminId = $_SESSION['user_id'] ?? 'user002';
                $dateSent = date('Y-m-d H:i:s');
                $resWinner = mysqli_query($conn, "SELECT name FROM applicants WHERE applicant_id = '{$winner['applicant_id']}'");
                $winnerName = ($rw = mysqli_fetch_assoc($resWinner)) ? $rw['name'] : 'Applicant';
                $notificationId = uniqid('NT');
                $title = 'Admin';
                $message = "Hey \"{$winnerName}\" congratulations, you have won the ballot for house {$winner['house_no']}.";
                $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('$notificationId', '$adminId', 'applicant', '{$winner['applicant_id']}', '$title', '" . mysqli_real_escape_string($conn, $message) . "', '$dateSent', '$dateSent', 'unread')");

                // Notify losers for this house
                foreach ($apps as $appItem) {
                    if ($appItem['application_id'] === $winner['application_id']) continue;
                    $loserId = $appItem['applicant_id'];
                    $resL = mysqli_query($conn, "SELECT name FROM applicants WHERE applicant_id = '$loserId'");
                    $lname = ($rl = mysqli_fetch_assoc($resL)) ? $rl['name'] : 'Applicant';
                    $nId = uniqid('NT');
                    $lmsg = "Hey \"{$lname}\" the ballot has closed; you were not selected for house {$house}.";
                    $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('$nId', '$adminId', 'applicant', '$loserId', '$title', '" . mysqli_real_escape_string($conn, $lmsg) . "', '$dateSent', '$dateSent', 'unread')");
                }
            }
        }

        header("Location: manage_applicants.php");
        exit;
    } elseif (isset($_POST['send_notification'])) {
        $adminId = $_SESSION['user_id'] ?? 'user002';
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        $dateSent = date('Y-m-d H:i:s');
        $recipientType = 'applicant';
        $status = 'unread';
        $dateReceived = $dateSent;

        if ($_POST['applicant_id'] === 'all') {
            $res = mysqli_query($conn, "SELECT applicant_id FROM applicants");
            while ($row = mysqli_fetch_assoc($res)) {
                $notificationId = uniqid('NT');
                $recipientId = $row['applicant_id'];
                $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, date_sent, date_received, status)
                              VALUES ('$notificationId', '$adminId', '$recipientType', '$recipientId', '$message', '$dateSent', '$dateReceived', '$status')");
            }
        } else {
            $applicantId = $_POST['applicant_id'];
            $notificationId = uniqid('NT');
            $conn->query("INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, date_sent, date_received, status)
                          VALUES ('$notificationId', '$adminId', '$recipientType', '$applicantId', '$message', '$dateSent', '$dateReceived', '$status')");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Manage Applicants | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #fff; }
        .content { margin-left: 250px; padding: 20px 30px; min-height: 100vh; background-color: #fff; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; border-bottom: 1px solid #ddd; }
        .header-bar h1 { font-size: 28px; color: green; margin: 0; font-weight: bold; }
        .profile-icon { width: 38px; height: 38px; border-radius: 50%; background-image: url('../images/profile-icon.png'); background-size: cover; }
        .tab-buttons { display: flex; gap: 10px; margin: 20px 0; }
        .tab-buttons button { padding: 10px 20px; background-color: green; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .tab-buttons button.active { background-color: darkgreen; }
        .section { display: none; padding: 10px 0; }
        .section.active { display: block; }
        table { width: 100%; border-collapse: collapse; background-color: white; margin-top: 10px; }
        thead { background-color: green; }
        th, td { padding: 10px; text-align: left; }
        th { color: white; }
        .btn { padding: 10px 20px; background: green; color: white; border: none; margin-top: 20px; cursor: pointer; border-radius: 4px; }
        .notification-form { display: none; background:rgb(240, 236, 236); padding: 10px; margin-top: 10px; }
        .toast { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; z-index: 9999; min-width: 300px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out; }
        .toast.success { background-color: #4CAF50; }
        .toast.error { background-color: #f44336; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
    </style>
</head>
<body>
<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>MANAGE APPLICANTS</p>
    <nav>
        <ul>
            <li><a href="csdashboard.php">Dashboard</a></li>
            <li><a href="houses.php">Houses</a></li>
            <li><a href="tenants.php">Tenants</a></li>
            <li><a href="service_requests.php">Service Requests</a></li>
            <li><a href="manage_applicants.php" class="active">Manage Applicants</a></li>
            <li><a href="notices.php">Notices</a></li>
            <li><a href="bills.php">Bills</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
    </nav>
</div>
<div class="content">
    <div class="header-bar">
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <div class="profile-icon" title="Profile"></div>
    </div>

    <div class="tab-buttons">
        <button onclick="showSection('applicantsSection')" id="applicantsBtn" class="active">Applicants</button>
        <button onclick="showSection('applicationsSection')" id="applicationsBtn">Applications</button>
        <button onclick="showSection('ballotsSection')" id="ballotsBtn">Ballots</button>
    </div>

    <div class="section active" id="applicantsSection">
        <?php if (!getenv('SMTP_HOST')): ?>
            <div style="background:#fff3cd;border:1px solid #ffeeba;padding:10px;margin-bottom:12px;border-radius:4px;color:#856404;">
                <strong>Notice:</strong> Email delivery is not configured. Outgoing messages are written to <code>logs/emails.log</code>.
            </div>
        <?php endif; ?>
        <h2 class="section-title">Applicants <a href="?filter=missing" style="font-size:14px; color:#666; margin-left:10px;">(Missing Profile)</a></h2>

        <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; border-radius: 6px;">
            <form method="post">
                <input type="hidden" name="applicant_id" value="all">
                <label style="font-weight: bold; display:block; margin-bottom:5px;">Send Notification to All Applicants:</label>
                <textarea name="message" placeholder="Write your message here..." rows="3" style="width: 90%; padding: 10px;" required></textarea>
                <button type="submit" name="send_notification" class="btn">Send to All</button>
            </form>
        </div>

        <table>
            <thead><tr><th>ID</th><th>PF No</th><th>Name</th><th>Email</th><th>Contact</th><th>Next of Kin</th><th>NOK Contact</th><th>Username</th><th>Status</th><th>Notify</th></tr></thead>
            <tbody>
            <?php mysqli_data_seek($applicants, 0); while ($row = mysqli_fetch_assoc($applicants)) {
                // Count applications for this applicant
                $countStmt = $conn->prepare("SELECT COUNT(*) as app_count FROM applications WHERE applicant_id = ?");
                $countStmt->bind_param("s", $row['applicant_id']);
                $countStmt->execute();
                $countResult = $countStmt->get_result()->fetch_assoc();
                $hasApplications = $countResult['app_count'] > 0;
                $statusLabel = $hasApplications ? 'Applied' : 'Not Applied';
                $statusColor = $hasApplications ? 'green' : 'gray';
            ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($row, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'pf_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'email')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'contact')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_contact')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'username')) ?></td>
                    <td><span style="color: <?= $statusColor ?>; font-weight: bold;"><?= $statusLabel ?></span></td>
                    <td>
                        <button onclick="toggleForm('form<?= $row['applicant_id'] ?>')">Notify</button>
                        <form method="post" class="notification-form" id="form<?= $row['applicant_id'] ?>">
                            <input type="hidden" name="applicant_id" value="<?= $row['applicant_id'] ?>">
                            <textarea name="message" required placeholder="Enter message" rows="3" style="width: 100%;"></textarea><br>
                            <button type="submit" name="send_notification" class="btn">Send</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="section" id="applicationsSection">
        <h2 class="section-title">Applications</h2>
        <table>
            <thead><tr><th>ID</th><th>Applicant ID</th><th>Category</th><th>House No</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($app = mysqli_fetch_assoc($applications)) { ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($app, 'application_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($app, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($app, 'category')) ?></td>
                    <td><?= safe_echo(safe_array_get($app, 'house_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($app, 'date')) ?></td>
                    <td>
                        <select class="status-dropdown" data-app-id="<?= htmlspecialchars($app['application_id']) ?>" style="padding:6px 10px; border-radius:4px; border:1px solid #ccc;">
                            <option value="pending" <?= (strtolower($app['status'] ?? '') === 'pending' ? 'selected' : '') ?>>Pending</option>
                            <option value="approved" <?= (strtolower($app['status'] ?? '') === 'approved' ? 'selected' : '') ?>>Approved</option>
                            <option value="rejected" <?= (strtolower($app['status'] ?? '') === 'rejected' ? 'selected' : '') ?>>Rejected</option>
                            <option value="cancelled" <?= (strtolower($app['status'] ?? '') === 'cancelled' ? 'selected' : '') ?>>Cancelled</option>
                        </select>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="section" id="ballotsSection">
        <h2 class="section-title">Ballots</h2>
        <table>
            <thead><tr><th>ID</th><th>Applicant ID</th><th>House ID</th><th>Ballot No</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($b = mysqli_fetch_assoc($ballots)) { ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($b, 'ballot_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'house_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'ballot_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'date_of_ballot')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'status')) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <form method="POST">
            <button class="btn" name="start_ballot">Start Balloting</button>
            <button class="btn" name="end_ballot">End Balloting</button>
            <button class="btn" name="choose_winner" onclick="return confirm('Confirm choosing random winners?')">Choose Winner(s)</button>
        </form>
    </div>
</div>
<script>
function showSection(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.getElementById(id.replace('Section', 'Btn')).classList.add('active');
}
function toggleForm(id) {
    const form = document.getElementById(id);
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
}

// Toast notification helper
function showToast(message, type = 'success', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Handle status dropdown changes with AJAX
document.querySelectorAll('.status-dropdown').forEach(dropdown => {
    let originalValue = dropdown.value;
    
    dropdown.addEventListener('change', function() {
        const applicationId = this.getAttribute('data-app-id');
        const newStatus = this.value;
        
        // Show loading state
        this.disabled = true;
        const originalText = this.style.opacity;
        this.style.opacity = '0.6';
        
        // Send AJAX request
        fetch('update_application_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `application_id=${encodeURIComponent(applicationId)}&status=${encodeURIComponent(newStatus)}`
        })
        .then(response => {
            if (response.status === 403) {
                throw new Error('Unauthorized: Only admins can update application status');
            }
            if (response.status === 400) {
                throw new Error('Invalid application ID or status');
            }
            if (response.status === 404) {
                throw new Error('Application not found');
            }
            if (response.status === 500) {
                throw new Error('Server error while updating status');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const capitalizedStatus = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                showToast(`Status updated to ${capitalizedStatus}`, 'success');
                originalValue = newStatus;
            } else {
                throw new Error(data.error || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast(error.message || 'Failed to update application status', 'error');
            // Revert dropdown to original value
            this.value = originalValue;
        })
        .finally(() => {
            this.disabled = false;
            this.style.opacity = originalText;
        });
    });
});
</script>
</body>
</html>