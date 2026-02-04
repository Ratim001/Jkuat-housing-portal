<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$applicant_id = $_SESSION['applicant_id'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: applicantlogin.php');
    exit;
}


// Handle AJAX Forfeit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forfeit_ajax'])) {
    $forfeit_id = $_POST['forfeit_id'] ?? '';
    $stmt = $conn->prepare("DELETE FROM applications WHERE applicant_id = ? AND application_id = ? AND status = 'Pending'");
    $stmt->bind_param("ss", $applicant_id, $forfeit_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'fail';
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
</div>

<div class="main-content">

    <div class="top-bar">
        <div class="portal-title">JKUAT STAFF HOUSING PORTAL</div>
        <div class="profile-dropdown">
            <img src="../images/p-icon.png" class="profile-icon" alt="Profile">
            <div class="dropdown-content" id="profileMenu">
                <a href="#">Profile</a>
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
        <?php while ($row = $results->fetch_assoc()): ?>
            <tr id="row-<?= htmlspecialchars($row['application_id']) ?>">
                <td><?= htmlspecialchars($row['application_id']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['house_no']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['status'] ?: 'Pending') ?></td>
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
document.addEventListener('DOMContentLoaded', function () {
    const forfeitButtons = document.querySelectorAll('.forfeit-btn');
    const profileIcon = document.querySelector('.profile-icon');
    const dropdown = document.getElementById('profileMenu');

    forfeitButtons.forEach(button => {
        button.addEventListener('click', function () {
            const appId = this.getAttribute('data-app-id');
            if (confirm("Are you sure you want to forfeit this application?")) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'forfeit_ajax=1&forfeit_id=' + encodeURIComponent(appId)
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        document.getElementById('row-' + appId).remove();
                    } else {
                        alert('Failed to forfeit application.');
                    }
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
