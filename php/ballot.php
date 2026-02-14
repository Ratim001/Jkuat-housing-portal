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
    header('Location: applicant_profile.php?redirect=ballot.php');
    exit;
}

$current = basename($_SERVER['PHP_SELF']);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: applicantlogin.php');
    exit;
}

function generateBallotId($conn) {
    $result = mysqli_query($conn, "SELECT ballot_id FROM balloting ORDER BY ballot_id DESC LIMIT 1");
    if ($row = mysqli_fetch_assoc($result)) {
        $lastIdNum = (int)substr($row['ballot_id'], 6);
        return 'ballot' . str_pad($lastIdNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        return 'ballot001';
    }
}

// Ballot control state
$ballot_open = false;
$ballot_closing = null;
// Safely attempt to read ballot_control; if the table doesn't exist, default to closed
try {
    $bc_res = mysqli_query($conn, "SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
    if ($bc_res && $bc_row = mysqli_fetch_assoc($bc_res)) {
        $ballot_open = (bool)$bc_row['is_open'];
        $ballot_closing = $bc_row['end_date'];
    }
} catch (mysqli_sql_exception $e) {
    $ballot_open = false;
    $ballot_closing = null;
}

function generateBallotNo($conn) {
    // generate a 4-digit ballot number that's not yet used
    $tries = 0;
    do {
        $n = rand(1000, 9999);
        $res = mysqli_query($conn, "SELECT ballot_no FROM balloting WHERE ballot_no = '$n' LIMIT 1");
        $tries++;
        if ($tries > 50) {
            // fallback to unique prefix
            return uniqid('B');
        }
    } while ($res && mysqli_num_rows($res) > 0);
    return (string)$n;
}

$ballot_error = '';
$ballot_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ballot'])) {
    if (!$ballot_open) {
        $ballot_error = 'Ballots are currently closed. Please wait for the administrator to start the ballot.';
    } else {
        $ballot_id = generateBallotId($conn);
        $house_id = $_POST['house_id'] ?? null;
        $date_of_ballot = $_POST['date_of_ballot'] ?? date('Y-m-d');
        $status = "Open";

        if (empty($house_id)) {
            $ballot_error = 'Please select a house to ballot for.';
        } else {
            $ballot_no = generateBallotNo($conn);

            $stmt = $conn->prepare("INSERT INTO balloting (ballot_id, applicant_id, house_id, ballot_no, date_of_ballot, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $ballot_id, $applicant_id, $house_id, $ballot_no, $date_of_ballot, $status);
            
            if ($stmt->execute()) {
                $ballot_success = "Ballot submitted successfully! Your ballot number is {$ballot_no}.";
            } else {
                $ballot_error = "Error submitting ballot. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balloting | JKUAT Housing</title>
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
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sidebar h2 {
            margin-bottom: 30px;
            font-size: 22px;
            font-weight: bold;
            color: #fff;
        }
        .sidebar a {
            display: block;
            width: 100%;
            padding: 14px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            margin: 5px 0;
            border-radius: 4px;
            text-align: left;
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
        }
        .profile-icon {
            width: 50px;
            height: 50px;
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

        h2 {
            color: #004225;
            margin-bottom: 10px;
        }

        form {
            margin-bottom: 30px;
        }

        label {
            font-weight: bold;
            display: block;
            margin: 10px 0 5px;
        }

        input, select {
            padding: 10px;
            width: 100%;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        button {
            margin-top: 20px;
            background-color: #006400;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 4px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .house-card {
            border: 2px solid #ccc;
            padding: 10px;
            background-color: #e6f4ea;
            cursor: pointer;
            text-align: center;
            border-radius: 5px;
            font-weight: bold;
        }

        .house-card.selected {
            background-color: #cce5ff;
            border-color: #007bff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 20px;
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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

    <h2>Start Balloting</h2>

    <?php if ($ballot_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($ballot_error) ?></div>
    <?php endif; ?>

    <?php if ($ballot_success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($ballot_success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if ($ballot_open): ?>
            <div class="alert alert-success">Ballots are OPEN. Closing date: <?= htmlspecialchars(date('F j, Y', strtotime($ballot_closing))) ?></div>
        <?php else: ?>
            <div class="alert alert-error">Ballots are currently closed. You cannot submit a ballot right now.</div>
        <?php endif; ?>

        <label>Date of Ballot</label>
        <input type="date" name="date_of_ballot" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>

        <label>Select House Category</label>
        <select id="categorySelect" onchange="filterHouses()" required>
            <option value="">-- Select Category --</option>
            <option value="1 Bedroom">1 Bedroom</option>
            <option value="2 Bedroom">2 Bedroom</option>
            <option value="3 Bedroom">3 Bedroom</option>
            <option value="4 Bedroom">4 Bedroom</option>
        </select>

        <label>Select a Vacant House</label>
        <div class="grid" id="houseGrid">
            <?php
            $query = "SELECT * FROM houses WHERE status = 'Vacant'";
            $houses = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($houses)) {
                $houseNumber = $row['house_number'] ?? 'House #' . $row['house_id'];
                echo "<div class='house-card' data-id='{$row['house_id']}' data-cat='{$row['category']}' onclick='selectHouse(this)'>{$houseNumber}<br>({$row['category']})</div>";
            }
            ?>
        </div>
        <input type="hidden" name="house_id" id="selectedHouse" required>

        <button type="submit" name="submit_ballot" <?= $ballot_open ? '' : 'disabled' ?>>Submit Ballot</button>
    </form>

    <h2>My Ballots</h2>
    <table>
        <thead>
        <tr>
            <th>Ballot ID</th>
            <th>Ballot No</th>
            <th>House ID</th>
            <th>Date of Ballot</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $myBallots = mysqli_query($conn, "SELECT * FROM balloting WHERE applicant_id = '$applicant_id'");
        while ($ballot = mysqli_fetch_assoc($myBallots)) {
            echo "<tr>
                <td>{$ballot['ballot_id']}</td>
                <td>{$ballot['ballot_no']}</td>
                <td>{$ballot['house_id']}</td>
                <td>{$ballot['date_of_ballot']}</td>
                <td>{$ballot['status']}</td>
            </tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<script>
function filterHouses() {
    const selectedCategory = document.getElementById('categorySelect').value;
    const cards = document.querySelectorAll('#houseGrid .house-card');
    cards.forEach(card => {
        card.style.display = card.dataset.cat === selectedCategory ? 'block' : 'none';
    });
}

function selectHouse(card) {
    document.querySelectorAll('#houseGrid .house-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('selectedHouse').value = card.dataset.id;
}

// ballot numbers are auto-generated server-side now; no client-side selection needed

const profileIcon = document.querySelector('.profile-icon');
const profileMenu = document.getElementById('profileMenu');

profileIcon.addEventListener('click', () => {
    profileMenu.style.display = profileMenu.style.display === 'block' ? 'none' : 'block';
});

window.addEventListener('click', e => {
    if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.style.display = 'none';
    }
});
</script>
</body>
</html>
