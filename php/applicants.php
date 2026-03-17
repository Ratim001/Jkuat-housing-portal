<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$applicant_id = $_SESSION['applicant_id'];

$stmt = $conn->prepare("SELECT name, email, contact, role, photo FROM applicants WHERE applicant_id = ?");
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

// Read and clear flash messages from redirects (e.g., from submit_post_forfeit.php)
$flash_error = $_SESSION['flash_error'] ?? null;
$flash_success = $_SESSION['flash_success'] ?? null;
if (isset($_SESSION['flash_error'])) unset($_SESSION['flash_error']);
if (isset($_SESSION['flash_success'])) unset($_SESSION['flash_success']);

// Handle Application Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_house'])) {
    $apply_error = '';
    $category = trim($_POST['category']);
    // Applicants no longer choose a specific house number — always create a Pending category-only application
    $house_no = '';
    $date = trim($_POST['apply_date']);
    // New flow: when a staff applies they get status 'Applied' until they submit a ballot
    $status = 'Applied';

    // Enforce one application per category per applicant (ignore cancelled applications)
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM applications WHERE applicant_id = ? AND category = ? AND LOWER(status) != 'cancelled'");
    $chk->bind_param('ss', $applicant_id, $category);
    $chk->execute();
    $cnt = (int)$chk->get_result()->fetch_assoc()['cnt'];
    if ($cnt > 0) {
        $apply_error = 'You already have an active application in this category. You may forfeit it before applying again.';
    } else {
        $query = mysqli_query($conn, "SELECT application_id FROM applications ORDER BY application_id DESC LIMIT 1");
        $newNum = ($row = mysqli_fetch_assoc($query)) ? (int)substr($row['application_id'], 2) + 1 : 1;
        $application_id = 'AP' . str_pad($newNum, 3, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO applications (application_id, applicant_id, category, house_no, date, status) VALUES (?, ?, ?, ?, ?, ?)");
        // Allow empty house_no for Pending category applications
        $stmt->bind_param("ssssss", $application_id, $applicant_id, $category, $house_no, $date, $status);
        if ($stmt->execute()) {
            // Double-check that the status was persisted as 'Applied'. Some
            // environments or schema differences may result in an empty value
            // immediately after insert; enforce it to keep the workflow:
            $checkStmt = $conn->prepare("SELECT status FROM applications WHERE application_id = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('s', $application_id);
                $checkStmt->execute();
                $r = $checkStmt->get_result()->fetch_assoc();
                if (!isset($r['status']) || strtolower(trim($r['status'])) !== 'applied') {
                    $enforce = $conn->prepare("UPDATE applications SET status = 'Applied' WHERE application_id = ?");
                    if ($enforce) { $enforce->bind_param('s', $application_id); $enforce->execute(); }
                }
            }

            // Create internal notification and send confirmation email to applicant
            $dateSent = date('Y-m-d H:i:s');
            $notificationId = uniqid('NT');
            $msg = "Dear applicant: Your application ({$application_id}) for category {$category} was received.";
            if (function_exists('notify_insert_safe')) {
                notify_insert_safe($conn, $notificationId, $_SESSION['applicant_id'] ?? 'system', 'applicant', $applicant_id, $msg, $dateSent, 'unread', 'Application Submitted');
            }
            // Try to email the user if email is available
            $email = $profile_check['email'] ?? null;
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = 'Application received — JKUAT Housing';
                $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p><p>Visit your applications page to view status.</p>';
                if (function_exists('notify_and_email')) {
                    notify_and_email($conn, 'applicant', $applicant_id, $email, $subject, $bodyHtml, 'Application Submitted');
                } else {
                    send_email($email, $subject, $bodyHtml, true);
                }
            }

            header("Location: applicants.php");
            exit;
        } else {
            $apply_error = 'Failed to submit application. Please try again.';
        }
    }
}

// Fetch applications for display with pagination
$myapps_page = max(1, (int)($_GET['myapps_page'] ?? 1));
$myapps_per_page = (int)($_GET['myapps_per_page'] ?? ($_SESSION['myapps_per_page'] ?? 10));
if (!in_array($myapps_per_page, [10,25,50,100])) $myapps_per_page = 10;
$_SESSION['myapps_per_page'] = $myapps_per_page;

$cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE applicant_id = ?");
$cntStmt->bind_param('s', $applicant_id);
$cntStmt->execute();
$total_row = $cntStmt->get_result()->fetch_assoc();
$total_myapps = (int)($total_row['cnt'] ?? 0);
$myapps_offset = ($myapps_page - 1) * $myapps_per_page;

$applications = $conn->prepare("SELECT ap.application_id, ap.applicant_id, ap.category, COALESCE((SELECT h.house_no FROM balloting b JOIN houses h ON h.house_id = b.house_id WHERE b.applicant_id = ap.applicant_id AND b.category = ap.category ORDER BY b.date_of_ballot DESC LIMIT 1), ap.house_no) AS house_no, ap.date, COALESCE(NULLIF(ap.status, ''), 'Applied') AS status FROM applications ap WHERE ap.applicant_id = ? ORDER BY ap.date DESC LIMIT ?, ?");
$applications->bind_param("sii", $applicant_id, $myapps_offset, $myapps_per_page);
$applications->execute();
$results = $applications->get_result();

// Lightweight JSON mode for live UI updates (does not affect normal HTML flow)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $items = [];
    $all = $conn->prepare(
        "SELECT ap.application_id, ap.category, "
        . "COALESCE((SELECT h.house_no FROM balloting b JOIN houses h ON h.house_id = b.house_id WHERE b.applicant_id = ap.applicant_id AND b.category = ap.category ORDER BY b.date_of_ballot DESC LIMIT 1), ap.house_no) AS house_no, "
        . "ap.date, "
        . "COALESCE(NULLIF(ap.status, ''), 'Applied') AS status "
        . "FROM applications ap WHERE ap.applicant_id = ? ORDER BY ap.date DESC"
    );
    if ($all) {
        $all->bind_param('s', $applicant_id);
        $all->execute();
        $r = $all->get_result();
        while ($row = $r->fetch_assoc()) {
            $rawStatus = isset($row['status']) ? trim((string)$row['status']) : '';
            $norm = strtolower($rawStatus);
            if ($norm === 'not successful' || $norm === 'not_successful') {
                $norm = 'unsuccessful';
            }
            $items[] = [
                'application_id' => $row['application_id'],
                'category' => $row['category'],
                'house_no' => $row['house_no'],
                'date' => $row['date'],
                'status' => $norm,
            ];
        }
    }
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// Ballot control state for applicants dashboard
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
    // Table likely missing — leave ballots closed and continue
    $ballot_open = false;
    $ballot_closing = null;
}

// Active page detection
$current = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Make an Application | JKUAT Housing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif;
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
            background: #f7f7f7;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
        /* Smaller variant for standalone submit buttons (post-forfeit form) - minimize button */
        .forfeit-btn.small {
            padding: 4px 8px;
            font-size: 12px;
            display: inline-block;
            width: 32px;
            height: 28px;
            min-width: 32px;
            text-indent: -9999px;
            position: relative;
            overflow: hidden;
        }
        .forfeit-btn.small::before {
            content: '▬';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            text-indent: 0;
            font-size: 16px;
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
    background-color: #f7f7f7;
    min-width: 120px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
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
            background-color: #004225;
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
                left: -220px;
                z-index: 100;
                transition: left 0.3s ease;
            }
            .hamburger-menu {
                display: flex;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .portal-title {
                font-size: 18px;
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
                width: 200px;
            }
            .top-bar {
                flex-direction: column;
                gap: 10px;
            }
            .portal-title {
                font-size: 16px;
            }
        }

    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="../images/2logo.png" alt="JKUAT Logo" style="width: 60px; height: auto;">
    </div>
    <h2><strong>Applicant Portal</strong></h2>
    <p style="color: #ccc; font-size: 12px; margin: 10px 0 20px 0;">Navigation</p>
    <a href="applicants.php" class="<?= $current === 'applicants.php' ? 'active' : '' ?>">Apply</a>
    <a href="ballot.php" class="<?= $current === 'ballot.php' ? 'active' : '' ?>">Balloting</a>
    <a href="notifications.php" class="<?= $current === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
    <a href="my_notices.php" class="<?= $current === 'my_notices.php' ? 'active' : '' ?>">My Notices</a>
    <a href="my_bills.php" class="<?= $current === 'my_bills.php' ? 'active' : '' ?>">My Bills</a>
    <a href="my_service_requests.php" class="<?= $current === 'my_service_requests.php' ? 'active' : '' ?>">Service Requests</a>
    <a href="applicant_profile.php" class="<?= $current === 'applicant_profile.php' ? 'active' : '' ?>">My Profile</a>
    <a href="my_tenant.php" class="<?= $current === 'my_tenant.php' ? 'active' : '' ?>">My House</a>
</div>

<div class="main-content">

    <div class="top-bar">
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="portal-title">JKUAT STAFF HOUSING PORTAL</div>
        <div class="profile-dropdown">
                <?php
                    $profileSrc = '../images/p-icon.png';
                    if (!empty($profile_check['photo'])) {
                        $profileSrc = '../' . $profile_check['photo'];
                    }
                ?>
                <img src="<?= htmlspecialchars($profileSrc) ?>" class="profile-icon" alt="Profile">
                <div class="dropdown-content" id="profileMenu">
                    <div style="padding:10px 15px; border-bottom:1px solid #eee; font-weight:700; color:#004225;">Role: <?= htmlspecialchars(ucfirst($profile_check['role'] ?? 'applicant')) ?></div>
                    <a href="applicant_profile.php">View Profile</a>
                    <a href="my_tenant.php">My House</a>
                    <a href="?logout=1">Logout</a>
                </div>
            </div>
    </div>

    <div style="margin-bottom:12px;"><button onclick="history.back();" style="background:#006400;color:#fff;border:1px solid #006400;padding:6px 10px;border-radius:4px;margin-right:8px;">Back</button></div>
    <div class="header">APPLY</div>

    <?php if ($ballot_open): ?>
        <div style="background:#e6f4ea;padding:15px;border:1px solid #c3e6cb;border-radius:6px;margin-bottom:16px;">
            <strong>Ballot is OPEN.</strong> Closing date: <?= htmlspecialchars(date('F j, Y', strtotime($ballot_closing))) ?>.
            <div style="margin-top:8px;"><a href="ballot.php" style="background:#006400;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;">Go to Balloting</a></div>
        </div>
    <?php endif; ?>

    <script>
    // Poll ballot state and update the notice automatically
    (function(){
        let lastState = <?= $ballot_open ? '1' : '0' ?>;
        function updateNotice(isOpen, closing){
            const existing = document.getElementById('ballotNotice');
            if (isOpen) {
                if (!existing) {
                    const div = document.createElement('div');
                    div.id = 'ballotNotice';
                    div.style.background = '#e6f4ea';
                    div.style.padding = '15px';
                    div.style.border = '1px solid #c3e6cb';
                    div.style.borderRadius = '6px';
                    div.style.marginBottom = '16px';
                    div.innerHTML = '<strong>Ballot is OPEN.</strong> Closing date: ' + (closing ? closing : '') + '. <div style="margin-top:8px;"><a href="ballot.php" style="background:#006400;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;">Go to Balloting</a></div>';
                    const main = document.querySelector('.main-content');
                    if (main && main.firstChild) main.insertBefore(div, main.firstChild.nextSibling);
                } else {
                    existing.innerHTML = '<strong>Ballot is OPEN.</strong> Closing date: ' + (closing ? closing : '') + '. <div style="margin-top:8px;"><a href="ballot.php" style="background:#006400;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;">Go to Balloting</a></div>';
                }
            } else {
                if (existing) existing.remove();
            }
        }

        async function poll(){
            try {
                const r = await fetch('ballot_state.php');
                const j = await r.json();
                if (j && j.success) {
                    const isOpen = j.is_open ? 1 : 0;
                    if (isOpen !== lastState) {
                        updateNotice(j.is_open, j.end_date ? (new Date(j.end_date)).toLocaleDateString() : '');
                        lastState = isOpen;
                    }
                }
            } catch (e) {}
        }
        setInterval(poll, 3000);
        // also listen for localStorage event to update immediately
        window.addEventListener('storage', function(e){ if (e.key === 'ballot_state_updated') poll(); });
    })();
    </script>
    <?php
        // Show post-close forfeit request area (only if applicant participated in ballot AND ballot is closed)
        $has_ballot_participation = false;
        $ballot_is_closed = false;
        
        // Check if applicant participated in ballot
        $bq = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ?");
        if ($bq) { $bq->bind_param('s', $applicant_id); $bq->execute(); $br = $bq->get_result()->fetch_assoc(); if ($br && (int)$br['cnt'] > 0) $has_ballot_participation = true; }
        
        // Check if ballot is closed
        $ballot_is_closed = true; // Default to closed if table doesn't exist
        try {
            $bc_query = $conn->prepare("SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
            if ($bc_query) {
                $bc_query->execute();
                $bc_result = $bc_query->get_result();
                if ($bc_result && $bc_result->num_rows > 0) {
                    $bc_row = $bc_result->fetch_assoc();
                    // Ballot is closed if is_open = 0 OR end_date has passed
                    $is_open = (bool)$bc_row['is_open'];
                    $end_date = $bc_row['end_date'];
                    $ballot_is_closed = !$is_open || (strtotime($end_date) < time());
                }
            }
        } catch (Exception $e) {
            $ballot_is_closed = true; // Default to closed if error
        }
        
        // Show forfeit form only if applicant participated in ballot AND ballot is closed
        $show_forfeit_form = $has_ballot_participation && $ballot_is_closed;
        
        // Clear any apply/forfeit errors if applicant has participated in ballot
        if ($has_ballot_participation && !empty($apply_error) && strpos($apply_error, 'forfeit') !== false) {
            $apply_error = '';
        }
    ?>
    <div style="margin-top:18px;padding:12px;border:1px solid #e6e6e6;border-radius:8px;background:#fbfbfb;">
        <h3 style="margin:0 0 8px 0;color:#006400;">Request Forfeit After Close</h3>
        <?php if ($show_forfeit_form): ?>
            <p style="color:#444;margin:0 0 8px 0;">Since you participated in the ballot, you may request to forfeit. Admin will review your request.</p>
            <form method="POST" action="submit_post_forfeit.php" enctype="multipart/form-data" style="margin-top:8px;">
                <div style="margin-bottom:8px;"><label style="font-weight:700;">Reason</label><br><textarea name="reason" rows="3" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;" required></textarea></div>
                <div style="margin-bottom:8px;"><label style="font-weight:700;">Attachment (optional, pdf/jpg/png)</label><br><input type="file" name="attachment" accept="application/pdf,image/jpeg,image/png"></div>
                <button class="forfeit-btn small" type="submit" title="Submit Forfeit Request">submit</button>
            </form>
        <?php else: ?>
            <?php if (!$has_ballot_participation): ?>
                <p style="color:#666;margin:0;">You have not participated in the ballot. The forfeit option will appear after you participate in the ballot.</p>
            <?php elseif (!$ballot_is_closed): ?>
                <p style="color:#666;margin:0;">The ballot is currently open. You can request to forfeit after the ballot closes.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($apply_error)): ?>
        <div style="background:#f8d7da;padding:12px;border:1px solid #f5c6cb;border-radius:6px;margin-bottom:12px;color:#721c24;font-weight:700;">
            <?= htmlspecialchars($apply_error) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_error)): ?>
        <div style="background:#f8d7da;padding:12px;border:1px solid #f5c6cb;border-radius:6px;margin-bottom:12px;color:#721c24;font-weight:700;">
            <?= htmlspecialchars($flash_error) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($flash_success)): ?>
        <div style="background:#d4edda;padding:12px;border:1px solid #c3e6cb;border-radius:6px;margin-bottom:12px;color:#155724;font-weight:700;">
            <?= htmlspecialchars($flash_success) ?>
        </div>
    <?php endif; ?>
    <form method="POST" id="applyForm">
            <div>
                <label>Category</label>
                <select name="category" id="categorySelect" required onchange="filterHousesForApply()">
                    <option value="">Select</option>
                    <option value="1 Bedroom">1 Bedroom</option>
                    <option value="2 Bedroom">2 Bedroom</option>
                    <option value="3 Bedroom">3 Bedroom</option>
                    <option value="4 Bedroom">4 Bedroom</option>
                </select>
            </div>
            <!-- Removed House No selection; applicants apply by category only -->
            <?php
            // No house numbers are shown to applicants — they apply for category only.
            ?>
            <div>
                <label>Expected move-in date</label>
                <input type="date" name="apply_date" id="applyDate" required min="<?= date('Y-m-d') ?>">
            </div>
            <div style="align-self: end;">
                <button type="submit" name="apply_house" id="applyButton">APPLY</button>
            </div>
        </form>

    <div class="header">Your Applications</div>
    <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
        <form method="get" style="margin:0;">
            <label>Per page:
                <select name="myapps_per_page" onchange="this.form.submit()">
                    <option value="10" <?= $myapps_per_page==10? 'selected': '' ?>>10</option>
                    <option value="25" <?= $myapps_per_page==25? 'selected': '' ?>>25</option>
                    <option value="50" <?= $myapps_per_page==50? 'selected': '' ?>>50</option>
                    <option value="100" <?= $myapps_per_page==100? 'selected': '' ?>>100</option>
                </select>
            </label>
            <input type="hidden" name="myapps_page" value="<?= intval($myapps_page) ?>">
        </form>
        <div style="color:#666;font-size:14px;">Total: <?= intval($total_myapps) ?></div>
    </div>
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
            // If status is empty treat as 'No Application' instead of defaulting to 'Pending'.
            $rawStatus = isset($row['status']) ? trim((string)$row['status']) : '';
            $normStatus = strtolower($rawStatus);
            if ($normStatus === '') {
                $displayStatus = 'No Application';
            } elseif ($normStatus === 'unsuccessful' || $normStatus === 'not_successful' || $normStatus === 'not successful') {
                $displayStatus = 'Not successful';
            } else {
                $displayStatus = ucfirst($normStatus);
            }
        ?>
            <tr id="row-<?= htmlspecialchars($row['application_id']) ?>" data-app-id="<?= htmlspecialchars($row['application_id']) ?>">
                <td><?= htmlspecialchars($row['application_id']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td data-field="house_no"><?= htmlspecialchars($row['house_no']) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['date']))) ?></td>
                <td>
                    <?php
                        $ds = strtolower($displayStatus);
                        $color = 'gray';
                        if ($ds === 'applied') $color = 'red';
                        elseif ($ds === 'pending') $color = 'orange';
                        elseif ($ds === 'won') $color = 'green';
                        // Support both older and canonical forms; prefer 'unsuccessful'
                        elseif ($ds === 'not successful' || $ds === 'not_successful' || $ds === 'unsuccessful') $color = 'red';
                        elseif ($ds === 'allocated') $color = 'green';
                    ?>
                    <span data-field="status" style="color: <?= $color ?>; font-weight:700;"><?= htmlspecialchars($displayStatus) ?></span>
                </td>
                <td>
                    <?php if (strtolower(trim($row['status'])) === 'applied'): ?>
                        <button class="forfeit-btn" data-app-id="<?= htmlspecialchars($row['application_id']) ?>">Forfeit</button>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php
        $myapps_total_pages = max(1, ceil($total_myapps / $myapps_per_page));
        echo '<div style="margin-top:8px;">';
        if ($myapps_page > 1) echo '<a href="?myapps_page='.($myapps_page-1).'&myapps_per_page='.$myapps_per_page.'">&laquo; Prev</a> ';
        echo ' Page '.intval($myapps_page).' of '.intval($myapps_total_pages).' ';
        if ($myapps_page < $myapps_total_pages) echo ' <a href="?myapps_page='.($myapps_page+1).'&myapps_per_page='.$myapps_per_page.'">Next &raquo;</a>';
        echo '</div>';
    ?>
</div>

<script>
// Client-side validation and confirmation for apply form
function validateApplyForm() {
    const cat = document.getElementById('categorySelect').value;
    const date = document.getElementById('applyDate').value;
    const btn = document.getElementById('applyButton');
    if (!btn) return;
    if (!cat || !date) {
        btn.disabled = true;
        return;
    }
    const selected = new Date(date);
    const today = new Date();
    today.setHours(0,0,0,0);
    if (selected < today) {
        btn.disabled = true;
        return;
    }
    btn.disabled = false;
}

document.addEventListener('DOMContentLoaded', function(){
    const categorySelect = document.getElementById('categorySelect');
    const applyDate = document.getElementById('applyDate');
    if (categorySelect) categorySelect.addEventListener('change', validateApplyForm);
    if (applyDate) applyDate.addEventListener('change', validateApplyForm);
    validateApplyForm();

    // Confirmation before final submit
    const form = document.getElementById('applyForm');
    form.addEventListener('submit', function(e){
        const cat = document.getElementById('categorySelect').value;
        const date = document.getElementById('applyDate').value;
        if (!confirm('Confirm submission of application for House category: ' + cat + ' with expected move-in date: ' + date + '?')) {
            e.preventDefault();
            return false;
        }
        // Allow submit to proceed; server will assign application ID and status = Applied
    });
});

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
    // Clear any lingering error toasts if applicant has already balloted (status != Applied)
    // This prevents confusion when user sees error message after their status has changed
    const appRows = document.querySelectorAll('tr[data-app-id]');
    let hasNonAppliedStatus = false;
    
    appRows.forEach(row => {
        const statusEl = row.querySelector('[data-field="status"]');
        if (statusEl) {
            const statusText = (statusEl.textContent || '').toLowerCase().trim();
            // If any application is NOT 'applied', user has progressed past forfeit window
            if (statusText && statusText !== 'applied' && statusText !== 'no application') {
                hasNonAppliedStatus = true;
            }
        }
    });
    
    // Remove all error toasts containing "forfeit" if user has non-applied status
    // These are stale errors from previous attempts
    if (hasNonAppliedStatus) {
        // Remove by setting display none AND removing from DOM
        document.querySelectorAll('.toast.error').forEach(toast => {
            if (toast.textContent.includes('forfeit') || toast.textContent.includes('Cannot')) {
                toast.style.display = 'none';
                // Also remove from DOM completely to ensure it's gone
                setTimeout(() => { toast.remove(); }, 0);
            }
        });
    }
    const forfeitButtons = document.querySelectorAll('.forfeit-btn');
    const profileIcon = document.querySelector('.profile-icon');
    const dropdown = document.getElementById('profileMenu');

    forfeitButtons.forEach(button => {
        // Safety check: verify the row status before attaching click handler
        const row = button.closest('tr');
        if (row) {
            const statusEl = row.querySelector('[data-field="status"]');
            if (statusEl) {
                const statusText = (statusEl.textContent || '').toLowerCase().trim();
                // If status is NOT 'applied', disable the button as extra safety
                if (statusText !== 'applied') {
                    button.disabled = true;
                    button.style.opacity = '0.5';
                    button.title = 'Cannot forfeit: Application status is ' + statusText;
                    return; // Skip adding click listener
                }
            }
        }
        
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
                        throw new Error('Cannot forfeit this application (only Applied applications can be forfeited)');
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

// Live-update application statuses/house numbers so users don't need to refresh
(function(){
    const POLL_MS = 4000;
    let inFlight = false;

    function statusLabel(norm) {
        if (!norm) return 'No Application';
        if (norm === 'unsuccessful') return 'Not successful';
        return norm.charAt(0).toUpperCase() + norm.slice(1);
    }

    function statusColor(norm) {
        if (!norm) return 'gray';
        if (norm === 'applied') return 'red';
        if (norm === 'pending') return 'orange';
        if (norm === 'won' || norm === 'allocated') return 'green';
        if (norm === 'unsuccessful') return 'red';
        return 'gray';
    }

    async function poll() {
        if (inFlight) return;
        if (document.visibilityState && document.visibilityState !== 'visible') return;
        inFlight = true;
        try {
            const res = await fetch('applicants.php?ajax=1', { cache: 'no-store' });
            const j = await res.json();
            if (!j || !j.success || !Array.isArray(j.items)) return;

            j.items.forEach(function(it){
                const appId = it.application_id;
                const norm = (it.status || '').toString().trim().toLowerCase();
                const row = document.querySelector('tr[data-app-id="' + CSS.escape(appId) + '"]');
                if (!row) return;
                const houseCell = row.querySelector('[data-field="house_no"]');
                if (houseCell && typeof it.house_no !== 'undefined') {
                    const newHouse = (it.house_no || '').toString();
                    if (houseCell.textContent !== newHouse) houseCell.textContent = newHouse;
                }
                const st = row.querySelector('[data-field="status"]');
                if (st) {
                    const label = statusLabel(norm);
                    if (st.textContent !== label) st.textContent = label;
                    st.style.color = statusColor(norm);
                    st.style.fontWeight = '700';
                }
            });
        } catch (e) {
            // ignore
        } finally {
            inFlight = false;
        }
    }

    setInterval(poll, POLL_MS);
})();

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
