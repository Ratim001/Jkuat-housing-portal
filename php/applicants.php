<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$applicant_id = $_SESSION['applicant_id'];

// Check if profile is complete
$stmt = $conn->prepare("SELECT name, email, contact FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("s", $applicant_id);
$stmt->execute();
$profile_check = $stmt->get_result()->fetch_assoc();

if (empty($profile_check['name']) || empty($profile_check['email']) || empty($profile_check['contact'])) {
    header('Location: applicant_profile.php?redirect=applicants.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: applicantlogin.php');
    exit;
}

// Handle Application Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_house'])) {
    $category = trim($_POST['category']);
    $house_no = trim($_POST['house_no']);
    $date = trim($_POST['apply_date']);
    $status = 'Pending';

    $query = mysqli_query($conn, "SELECT application_id FROM applications ORDER BY application_id DESC LIMIT 1");
    $newNum = ($row = mysqli_fetch_assoc($query)) ? (int)substr($row['application_id'], 2) + 1 : 1;
    $application_id = 'AP' . str_pad($newNum, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO applications (application_id, applicant_id, category, house_no, date, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $application_id, $applicant_id, $category, $house_no, $date, $status);
    $stmt->execute();

    header("Location: applicants.php");
    exit;
}

// Fetch applications for display
$applications = $conn->prepare("SELECT * FROM applications WHERE applicant_id = ? ORDER BY date DESC");
$applications->bind_param("s", $applicant_id);
$applications->execute();
$results = $applications->get_result();

// Active page detection
$current = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply for House | JKUAT Housing</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
        }
        .sidebar {
            width: 220px;
            background-color: #004225;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 22px;
            font-weight: bold;
        }
        .sidebar a {
            display: block;
            padding: 14px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            margin: 10px 0;
            border-radius: 4px;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background-color: #006400;
        }

        .main-content {
            margin-left: 220px;
            padding: 40px;
            width: calc(100% - 220px);
        }

        .header {
            font-size: 24px;
            color: #006400;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        select, input[type="date"], input[type="text"], button {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #28a745;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th {
            background-color: #006400;
            color: white;
        }

        .forfeit-btn {
            background-color: #dc3545;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        .forfeit-btn:hover {
            background-color: #c82333;
        }
        .forfeit-btn:disabled {
            background-color: #999;
            cursor: not-allowed;
        }
        .toast { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; z-index: 9999; min-width: 300px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out; }
        .toast.success { background-color: #4CAF50; }
        .toast.error { background-color: #f44336; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
        .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.portal-title {
    font-size: 26px;
    color: #004225;
    font-weight: bold;
}

.profile-dropdown {
    position: relative;
    display: inline-block;
}

.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
}

.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: #ffffff;
    min-width: 120px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    border-radius: 5px;
    z-index: 1;
}

.dropdown-content a {
    color: #004225;
    padding: 10px 15px;
    text-decoration: none;
    display: block;
    font-weight: bold;
}

.dropdown-content a:hover {
    background-color: #f1f1f1;
}

    </style>
</head>
<body>

<div class="sidebar">
    <h2><strong>Applicant Portal</strong></h2>
    <a href="applicants.php" class="<?= $current === 'applicants.php' ? 'active' : '' ?>">Apply</a>
    <a href="ballot.php" class="<?= $current === 'ballot.php' ? 'active' : '' ?>">Balloting</a>
    <a href="notifications.php" class="<?= $current === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
    <a href="applicant_profile.php" class="<?= $current === 'applicant_profile.php' ? 'active' : '' ?>">My Profile</a>
</div>

<div class="main-content">

    <div class="top-bar">
        <div class="portal-title">JKUAT STAFF HOUSING PORTAL</div>
        <div class="profile-dropdown">
            <img src="../images/p-icon.png" class="profile-icon" alt="Profile">
            <div class="dropdown-content" id="profileMenu">
                <a href="applicant_profile.php">View Profile</a>
                <a href="?logout=1">Logout</a>
            </div>
        </div>
    </div>

    <div class="header">Apply for a Vacant House</div>

    <form method="POST">
        <div>
            <label>Category</label>
            <select name="category" required>
                <option value="">Select</option>
                <option value="1 Bedroom">1 Bedroom</option>
                <option value="2 Bedroom">2 Bedroom</option>
                <option value="3 Bedroom">3 Bedroom</option>
                <option value="4 Bedroom">4 Bedroom</option>
            </select>
        </div>
        <div>
            <label>House No</label>
            <input type="text" name="house_no" required>
        </div>
        <div>
            <label>Date</label>
            <input type="date" name="apply_date" required>
        </div>
        <div style="align-self: end;">
            <button type="submit" name="apply_house">Apply</button>
        </div>
    </form>

    <div class="header">Your Applications</div>
    <table>
        <thead>
        <tr>
            <th>Application ID</th>
            <th>Category</th>
            <th>House No</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $results->fetch_assoc()): 
            $displayStatus = ucfirst(strtolower($row['status'] ?: 'pending'));
        ?>
            <tr id="row-<?= htmlspecialchars($row['application_id']) ?>">
                <td><?= htmlspecialchars($row['application_id']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['house_no']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($displayStatus) ?></td>
                <td>
                    <?php if (strtolower(trim($row['status'])) === 'pending'): ?>
                        <button class="forfeit-btn" data-app-id="<?= htmlspecialchars($row['application_id']) ?>">Forfeit</button>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
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

document.addEventListener('DOMContentLoaded', function () {
    const forfeitButtons = document.querySelectorAll('.forfeit-btn');
    const profileIcon = document.querySelector('.profile-icon');
    const dropdown = document.getElementById('profileMenu');

    forfeitButtons.forEach(button => {
        button.addEventListener('click', function () {
            const appId = this.getAttribute('data-app-id');
            if (confirm("Are you sure you want to forfeit this application?")) {
                // Disable button and show loading state
                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Processing...';
                
                fetch('forfeit_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'application_id=' + encodeURIComponent(appId)
                })
                .then(response => {
                    if (response.status === 403) {
                        throw new Error('Unauthorized: You do not own this application');
                    }
                    if (response.status === 400) {
                        throw new Error('Invalid application ID');
                    }
                    if (response.status === 404) {
                        throw new Error('Application not found');
                    }
                    if (response.status === 422) {
                        throw new Error('Cannot forfeit this application (only Pending applications can be forfeited)');
                    }
                    if (response.status === 500) {
                        throw new Error('Server error while forfeiting application');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Application forfeited successfully', 'success');
                        // Replace button with "-" to indicate action is no longer available
                        this.parentElement.innerHTML = '-';
                    } else {
                        throw new Error(data.error || 'Failed to forfeit application');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast(error.message || 'Failed to forfeit application', 'error');
                    this.disabled = false;
                    this.textContent = originalText;
                });
            }
        });
    });

    profileIcon.addEventListener('click', function () {
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    // Hide dropdown when clicking outside
    window.addEventListener('click', function (e) {
        if (!profileIcon.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
</script>



</body>
</html>
