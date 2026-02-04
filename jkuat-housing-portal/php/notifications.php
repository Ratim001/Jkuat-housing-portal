<?php
session_start();
include '../includes/db.php';

// Check if either tenant or applicant is logged in
if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

// Determine user type and ID
$user_type = '';
$user_id = '';

if (isset($_SESSION['tenant_id'])) {
    $user_type = 'tenant';
    $user_id = $_SESSION['tenant_id'];
} elseif (isset($_SESSION['applicant_id'])) {
    $user_type = 'applicant';
    $user_id = $_SESSION['applicant_id'];
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: applicantlogin.php');
    exit;
}

// Mark all as read
$conn->query("UPDATE notifications SET status='read' WHERE recipient_type='$user_type' AND recipient_id='$user_id'");

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY date_sent DESC");
$stmt->bind_param("ss", $user_type, $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Count unread
$unread_result = $conn->query("SELECT COUNT(*) AS unread FROM notifications WHERE recipient_type='$user_type' AND recipient_id = '$user_id' AND status = 'unread'");
$unread_count = $unread_result->fetch_assoc()['unread'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | JKUAT Housing</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            background: #fff; padding: 10px 20px; border-bottom: 1px solid #ccc;
            position: fixed; top: 0; left: 0; width: 100%; height: 70px; z-index: 10;
        }
        .topbar h2 { color: rgb(65, 172, 65); flex-grow: 1; text-align: center; }
        .icons { display: flex; gap: 20px; align-items: center; }
        .profile-icon { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }
        
        .main-content {
            margin-top: 70px;
            padding: 40px;
        }
        .portal-title {
            font-size: 26px;
            color: #004225;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }
        .icons-right {
            display: flex;
            align-items: center;
            gap: 35px;
        }
        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
        }
        .bell-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
        }
        .bell-container {
            position: relative;
        }
        .bell-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            padding: 2px 5px;
        }
        .dropdown {
            display: none; position: absolute; top: 30px; right: 0;
            background: white; border: 1px solid #ccc; width: 200px; z-index: 100;
        }
        .dropdown a { display: block; padding: 10px; color: black; text-decoration: none; }
        .dropdown a:hover { background: #f1f1f1; }
        .icon-button { cursor: pointer; position: relative; }
        .notification-card {
            background-color: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .notification-title {
            font-weight: bold;
            font-size: 18px;
            color: #004225;
        }
        .notification-body {
            margin-top: 8px;
            color: #333;
        }
        .notification-date {
            font-size: 12px;
            color: gray;
            margin-top: 5px;
            text-align: right;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: rgb(65, 172, 65);
            text-decoration: none;
            font-weight: bold;
        }
        .back-link:hover {
            text-decoration: underline;
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
        <div class="icon-button" onclick="toggleDropdown('profileDropdown')">
            <img src="../images/p-icon.png" alt="Profile" class="profile-icon">
            <div class="dropdown" id="profileDropdown">
                <a href="#">Profile</a>
                <a href="logouts.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <a href="<?= $user_type === 'tenant' ? 'mytenants.php' : 'applicants.php' ?>" class="back-link">← Back to Dashboard</a>
    
    <h2 style="color:#006400;">Notifications</h2>

    <?php if ($notifications->num_rows > 0): ?>
        <?php while ($note = $notifications->fetch_assoc()): ?>
            <div class="notification-card" id="<?= htmlspecialchars($note['notification_id']) ?>">
                <div class="notification-title">
                    <?= htmlspecialchars($note['title'] ?? 'No Title') ?>
                </div>
                <div class="notification-body">
                    <?= nl2br(htmlspecialchars($note['message'])) ?>
                </div>
                <div class="notification-date">
                    <?= date('F j, Y, g:i a', strtotime($note['date_sent'])) ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No notifications found.</p>
    <?php endif; ?>
</div>

<script>
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
</script>

</body>
</html>