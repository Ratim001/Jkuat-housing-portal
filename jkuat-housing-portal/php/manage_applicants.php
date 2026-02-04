<?php
include '../includes/db.php';
session_start();

function safe_echo($value) {
    return htmlspecialchars((string)($value ?? ''));
}

function safe_array_get($array, $key, $default = '') {
    return is_array($array) && isset($array[$key]) ? $array[$key] : $default;
}

$applicants = mysqli_query($conn, "SELECT * FROM applicants");
$applications = mysqli_query($conn, "SELECT * FROM applications ORDER BY date DESC");
$ballots = mysqli_query($conn, "SELECT * FROM balloting ORDER BY date_of_ballot DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_ballot'])) {
        // Optional logging
    } elseif (isset($_POST['end_ballot'])) {
        // Optional logging
    } elseif (isset($_POST['choose_winner'])) {
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
        <h2 class="section-title">Applicants</h2>

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
            <?php mysqli_data_seek($applicants, 0); while ($row = mysqli_fetch_assoc($applicants)) { ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($row, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'pf_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'email')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'contact')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_contact')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'username')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'status')) ?></td>
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
                    <td><?= safe_echo(safe_array_get($app, 'status')) ?></td>
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
</script>
</body>
</html>