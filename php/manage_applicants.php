<?php
require_once '../includes/init.php';
require_once '../includes/db.php';

// Helper: check if a database table exists to avoid fatal errors when migrations haven't run
function table_exists($conn, $table) {
    $tbl = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '" . $tbl . "'");
    return ($res && $res->num_rows > 0);
}

// Ensure there's a canonical ballot_control row with id=1 so UPDATEs affect at least one row
function ensure_ballot_control_row($conn) {
    try {
        if (!table_exists($conn, 'ballot_control')) return false;
        $r = $conn->query("SELECT id FROM ballot_control WHERE id = 1 LIMIT 1");
        if (!$r || $r->num_rows === 0) {
            // create a default row (closed)
            $conn->query("INSERT INTO ballot_control (id, is_open, start_date, end_date) VALUES (1, 0, NOW(), NULL)");
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Check if a column exists in a table
function column_exists($conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $schema = $conn->real_escape_string($conn->query("SELECT DATABASE() as db")->fetch_assoc()['db']);
    $q = $conn->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->bind_param('sss', $schema, $t, $c);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return ($r && $r['cnt'] > 0);
}

// Insert notification safely whether or not the optional `title` column exists
function insert_notification_safe($conn, $notificationId, $adminId, $recipientType, $recipientId, $message, $dateSent, $status = 'unread', $title = null) {
    static $hasTitle = null;
    if ($hasTitle === null) {
        $hasTitle = column_exists($conn, 'notifications', 'title');
    }

    $msgEsc = mysqli_real_escape_string($conn, $message);
    $notificationIdEsc = $conn->real_escape_string($notificationId);
    $adminIdEsc = $conn->real_escape_string($adminId);
    $recipientTypeEsc = $conn->real_escape_string($recipientType);
    $recipientIdEsc = $conn->real_escape_string($recipientId);
    $dateSentEsc = $conn->real_escape_string($dateSent);
    $statusEsc = $conn->real_escape_string($status);

    if ($hasTitle && $title !== null) {
        $titleEsc = $conn->real_escape_string($title);
        $sql = "INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('{$notificationIdEsc}', '{$adminIdEsc}', '{$recipientTypeEsc}', '{$recipientIdEsc}', '{$titleEsc}', '{$msgEsc}', '{$dateSentEsc}', '{$dateSentEsc}', '{$statusEsc}')";
    } else {
        $sql = "INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, date_sent, date_received, status) VALUES ('{$notificationIdEsc}', '{$adminIdEsc}', '{$recipientTypeEsc}', '{$recipientIdEsc}', '{$msgEsc}', '{$dateSentEsc}', '{$dateSentEsc}', '{$statusEsc}')";
    }
    return $conn->query($sql);
}

$filter = $_GET['filter'] ?? '';
$sql = "SELECT * FROM applicants";
if ($filter === 'missing') {
    $sql .= " WHERE (name IS NULL OR name = '' OR email IS NULL OR email = '' OR contact IS NULL OR contact = '')";
} elseif ($filter === 'disabled') {
    $sql .= " WHERE is_disabled = 1";
}

// Applicants pagination
$app_page = max(1, (int)($_GET['app_page'] ?? 1));
$app_per_page = (int)($_GET['app_per_page'] ?? ($_SESSION['app_per_page'] ?? 10));
if (!in_array($app_per_page, [10,25,50,100])) $app_per_page = 10;
$_SESSION['app_per_page'] = $app_per_page;

// full applicants result (for selects etc.)
$all_applicants = $conn->query($sql);
$total_applicants = $all_applicants ? $all_applicants->num_rows : 0;
$app_offset = ($app_page - 1) * $app_per_page;
$applicants = $conn->query($sql . " ORDER BY name ASC LIMIT " . intval($app_offset) . ", " . intval($app_per_page));

// Applications pagination
$apps_page = max(1, (int)($_GET['apps_page'] ?? 1));
$apps_per_page = (int)($_GET['apps_per_page'] ?? ($_SESSION['apps_per_page'] ?? 10));
if (!in_array($apps_per_page, [10,25,50,100])) $apps_per_page = 10;
$_SESSION['apps_per_page'] = $apps_per_page;
$total_apps_row = $conn->query("SELECT COUNT(*) as cnt FROM applications")->fetch_assoc();
$total_apps = (int)($total_apps_row['cnt'] ?? 0);
$apps_offset = ($apps_page - 1) * $apps_per_page;
$applications = mysqli_query($conn, "SELECT ap.application_id, ap.applicant_id, ap.category, COALESCE((SELECT h.house_no FROM balloting b JOIN houses h ON h.house_id = b.house_id WHERE b.applicant_id = ap.applicant_id AND b.category = ap.category AND b.house_id IS NOT NULL AND b.house_id <> '' ORDER BY b.date_of_ballot DESC LIMIT 1), ap.house_no) AS house_no, ap.date, COALESCE(NULLIF(ap.status, ''), 'Applied') AS status FROM applications ap ORDER BY ap.date DESC LIMIT " . intval($apps_offset) . ", " . intval($apps_per_page));

// Ballots pagination
$ballots_page = max(1, (int)($_GET['ballots_page'] ?? 1));
$ballots_per_page = (int)($_GET['ballots_per_page'] ?? ($_SESSION['ballots_per_page'] ?? 10));
if (!in_array($ballots_per_page, [10,25,50,100])) $ballots_per_page = 10;
$_SESSION['ballots_per_page'] = $ballots_per_page;
$total_ballots_row = $conn->query("SELECT COUNT(*) as cnt FROM balloting")->fetch_assoc();
$total_ballots = (int)($total_ballots_row['cnt'] ?? 0);
$ballots_offset = ($ballots_page - 1) * $ballots_per_page;
$ballots = mysqli_query($conn, "SELECT * FROM balloting ORDER BY date_of_ballot DESC LIMIT " . intval($ballots_offset) . ", " . intval($ballots_per_page));

// Forfeit requests (post-closed) - admin view
$forfeit_requests = null;
if ($conn->query("SHOW TABLES LIKE 'post_forfeit_requests'")->num_rows > 0) {
    $forfeit_requests = $conn->query("SELECT pfr.*, a.name AS applicant_name, a.email AS applicant_email FROM post_forfeit_requests pfr LEFT JOIN applicants a ON pfr.applicant_id = a.applicant_id ORDER BY pfr.created_at DESC LIMIT 200");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['user_id'] ?? 'user002';

    // MANUAL ALLOCATION: admin assigns a house to an applicant (outside balloting)
    if (isset($_POST['manual_allocate'])) {
        $applicantId = $_POST['applicant_id'] ?? '';
        $houseNo = $_POST['house_no'] ?? '';
        $notes = $_POST['notes'] ?? null;

        if (empty($applicantId) || empty($houseNo)) {
            $_SESSION['flash_error'] = 'Applicant and house selection are required for manual allocation.';
            header('Location: manage_applicants.php'); exit;
        }

        $hres = $conn->prepare("SELECT house_id, status FROM houses WHERE house_no = ? LIMIT 1");
        $hres->bind_param('s', $houseNo);
        $hres->execute();
        $hrow = $hres->get_result()->fetch_assoc();
        if (!$hrow || strtolower($hrow['status']) !== 'vacant') {
            $_SESSION['flash_error'] = 'Selected house is not available.';
            header('Location: manage_applicants.php'); exit;
        }

        $conn->begin_transaction();
        try {
            $lastTenant = mysqli_query($conn, "SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
            $nextId = 'T001';
            if ($t = mysqli_fetch_assoc($lastTenant)) {
                $num = (int)substr($t['tenant_id'], 1) + 1;
                $nextId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }

            $today = date('Y-m-d');
            $insert = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $nextId, $applicantId, $houseNo, $today);
            $insert->execute();

            // If the applicant has a recent related application in Applied/Pending state, mark it as Won and attach house_no
            $appFind = $conn->prepare("SELECT application_id FROM applications WHERE applicant_id = ? AND LOWER(status) IN ('applied','pending') ORDER BY date DESC LIMIT 1");
            if ($appFind) {
                $appFind->bind_param('s', $applicantId);
                $appFind->execute();
                $appR = $appFind->get_result()->fetch_assoc();
                if ($appR && !empty($appR['application_id'])) {
                    $appIdToUpdate = $appR['application_id'];
                    $uApp = $conn->prepare("UPDATE applications SET status = 'Won', house_no = ? WHERE application_id = ?");
                    if ($uApp) {
                        $uApp->bind_param('ss', $houseNo, $appIdToUpdate);
                        $uApp->execute();
                    }
                }
            }

            // Mark applicant as tenant (set status and role) so they appear in Tenants list
            $conn->query("UPDATE applicants SET status = 'Tenant', role = 'tenant' WHERE applicant_id = '" . $conn->real_escape_string($applicantId) . "'");
            $conn->query("UPDATE houses SET status = 'Occupied' WHERE house_no = '" . $conn->real_escape_string($houseNo) . "'");

            $allocId = uniqid('MA');
            $mstmt = $conn->prepare("INSERT INTO manual_allocations (allocation_id, admin_id, applicant_id, house_no, date_allocated, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $now = date('Y-m-d H:i:s');
            $mstmt->bind_param('ssssss', $allocId, $adminId, $applicantId, $houseNo, $now, $notes);
            $mstmt->execute();

            $notificationId = uniqid('NT');
            $msg = "Dear applicant: You have been allocated house {$houseNo} by CS Admin.";
            insert_notification_safe($conn, $notificationId, $adminId, 'applicant', $applicantId, $msg, $now, 'unread', 'Manual Allocation');

            $conn->commit();
            $_SESSION['flash_success'] = 'Manual allocation completed successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = 'Manual allocation failed: ' . $e->getMessage();
            logs_write('error', 'Manual allocation failed: ' . $e->getMessage());
        }

        header('Location: manage_applicants.php');
        exit;

    } elseif (isset($_POST['start_ballot']) || isset($_POST['start_ballot_custom'])) {
            if (table_exists($conn, 'ballot_control')) {
                // ensure row exists so UPDATE will affect a row
                ensure_ballot_control_row($conn);
            // Determine end date: custom end date takes precedence, otherwise duration (days), otherwise default 14 days
            $customEnd = trim($_POST['ballot_end_date'] ?? '');
            $durationDays = intval($_POST['ballot_duration_days'] ?? 0);

            if ($customEnd !== '') {
                // Validate date
                $ts = strtotime($customEnd);
                if ($ts === false) {
                    $_SESSION['flash_error'] = 'Invalid end date provided for ballot.';
                    header('Location: manage_applicants.php'); exit;
                }
                $endSql = "'" . $conn->real_escape_string(date('Y-m-d 23:59:59', $ts)) . "'";
                $closing = date('Y-m-d', $ts);
                $conn->query("UPDATE ballot_control SET is_open = 1, start_date = NOW(), end_date = " . $endSql . " WHERE id = 1");
            } elseif ($durationDays > 0) {
                $durationDays = max(1, $durationDays);
                $conn->query("UPDATE ballot_control SET is_open = 1, start_date = NOW(), end_date = DATE_ADD(NOW(), INTERVAL " . intval($durationDays) . " DAY) WHERE id = 1");
                $closing = date('Y-m-d', strtotime('+' . intval($durationDays) . ' days'));
            } else {
                // default 14 days
                $conn->query("UPDATE ballot_control SET is_open = 1, start_date = NOW(), end_date = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE id = 1");
                $closing = date('Y-m-d', strtotime('+14 days'));
            }

            // Notify only applicants who have active applications (applied, pending, approved)
            $applicantQ = "SELECT DISTINCT a.applicant_id, a.name, a.email FROM applications ap JOIN applicants a ON ap.applicant_id = a.applicant_id WHERE LOWER(ap.status) IN ('applied','pending','approved')";
            $res = mysqli_query($conn, $applicantQ);
            $dateSent = date('Y-m-d H:i:s');
            while ($row = mysqli_fetch_assoc($res)) {
                $notificationId = uniqid('NT');
                $recipientId = $row['applicant_id'];
                $name = $row['name'] ?: 'Applicant';
                $email = $row['email'] ?? null;
                $plainMsg = "Dear {$name}: Ballot is now OPEN. Closing date is {$closing}. Please visit Balloting to place your ballot for your applied category.";
                insert_notification_safe($conn, $notificationId, $adminId, 'applicant', $recipientId, $plainMsg, $dateSent, 'unread', 'Ballot Open');
                if (!empty($email)) {
                    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                    $ballotLink = $appUrl . '/php/ballot.php';
                    $htmlMsg = '<p>Dear ' . htmlspecialchars($name) . ': Ballot is now <strong>OPEN</strong>. Closing date is ' . htmlspecialchars($closing) . '.</p><p><a href="' . htmlspecialchars($ballotLink) . '">Go to Balloting</a></p>';
                    try { if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $recipientId, $email, 'Ballot Open - JKUAT Housing', $htmlMsg, 'Ballot Open'); else send_email($email, 'Ballot Open - JKUAT Housing', $plainMsg, false); } catch (Exception $e) { error_log('send_email error: ' . $e->getMessage()); }
                }
            }
        } else {
            $_SESSION['flash_error'] = "Database table 'ballot_control' is missing. Run migrations (see migrations/2026-02-13_add_ballot_control.sql).";
        }

        // If this was an AJAX request, return JSON so clients can update without reload
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            // Return authoritative state from DB so clients can update immediately
            $resp = ['success' => true, 'is_open' => 0, 'end_date' => null];
            if (table_exists($conn, 'ballot_control')) {
                $r = $conn->query("SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
                if ($r && $row = $r->fetch_assoc()) {
                    $resp['is_open'] = (int)((bool)$row['is_open']);
                    $resp['end_date'] = $row['end_date'];
                }
            }
            echo json_encode($resp);
            exit;
        }

        header("Location: manage_applicants.php");
        exit;

    } elseif (isset($_POST['end_ballot'])) {
            if (table_exists($conn, 'ballot_control')) {
            // ensure row exists first
            ensure_ballot_control_row($conn);
            $conn->query("UPDATE ballot_control SET is_open = 0, end_date = NOW() WHERE id = 1");
            // Notify only applicants who had active applications
            $applicantQ = "SELECT DISTINCT a.applicant_id, a.name, a.email FROM applications ap JOIN applicants a ON ap.applicant_id = a.applicant_id WHERE LOWER(ap.status) IN ('applied','pending','approved')";
            $res = mysqli_query($conn, $applicantQ);
            $dateSent = date('Y-m-d H:i:s');
            while ($row = mysqli_fetch_assoc($res)) {
                $notificationId = uniqid('NT');
                $recipientId = $row['applicant_id'];
                $name = $row['name'] ?: 'Applicant';
                $email = $row['email'] ?? null;
                $plainMsg = "Dear {$name}: Ballots have now CLOSED. Thank you for participating.";
                insert_notification_safe($conn, $notificationId, $adminId, 'applicant', $recipientId, $plainMsg, $dateSent, 'unread', 'Ballots Closed');
                if (!empty($email)) { 
                    $htmlMsg = '<p>Dear ' . htmlspecialchars($name) . ': Ballots have now <strong>CLOSED</strong>. Thank you for participating.</p>';
                    try { if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $recipientId, $email, 'Ballots Closed - JKUAT Housing', $htmlMsg, 'Ballots Closed'); else send_email($email, 'Ballots Closed - JKUAT Housing', $plainMsg, false); } catch (Exception $e) { error_log('send_email error: ' . $e->getMessage()); } 
                }
            }
        } else {
            $_SESSION['flash_error'] = "Database table 'ballot_control' is missing. Run migrations (see migrations/2026-02-13_add_ballot_control.sql).";
        }

        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            $resp = ['success' => true, 'is_open' => 0, 'end_date' => null];
            if (table_exists($conn, 'ballot_control')) {
                $r = $conn->query("SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
                if ($r && $row = $r->fetch_assoc()) {
                    $resp['is_open'] = (int)((bool)$row['is_open']);
                    $resp['end_date'] = $row['end_date'];
                }
            }
            echo json_encode($resp);
            exit;
        }

        header("Location: manage_applicants.php");
        exit;

    } elseif (isset($_POST['choose_winner'])) {
        // ensure ballots are closed
        if (table_exists($conn, 'ballot_control')) {
            ensure_ballot_control_row($conn);
            $conn->query("UPDATE ballot_control SET is_open = 0, end_date = NOW() WHERE id = 1");
        }

        $vacantCats = mysqli_query($conn, "SELECT category, COUNT(*) as cnt FROM houses WHERE LOWER(status) = 'vacant' GROUP BY category");
        while ($vc = mysqli_fetch_assoc($vacantCats)) {
            $category = $vc['category'];
            $vacantCount = (int)$vc['cnt'];
            if ($vacantCount <= 0) continue;

            $appsRes = $conn->prepare("SELECT * FROM applications WHERE category = ? AND LOWER(status) IN ('applied','pending')");
            $appsList = [];
            if ($appsRes) {
                $appsRes->bind_param('s', $category);
                $appsRes->execute();
                $r = $appsRes->get_result();
                while ($rowApp = mysqli_fetch_assoc($r)) $appsList[] = $rowApp;
            }
            if (count($appsList) === 0) continue;

            $numWinners = min($vacantCount, count($appsList));
            shuffle($appsList);
            $winners = array_slice($appsList, 0, $numWinners);
            $losers = array_slice($appsList, $numWinners);

            $vacantHousesStmt = $conn->prepare("SELECT house_no FROM houses WHERE LOWER(status) = 'vacant' AND category = ? ORDER BY RAND() LIMIT ?");
            $houses = [];
            if ($vacantHousesStmt) {
                $vacantHousesStmt->bind_param('si', $category, $numWinners);
                $vacantHousesStmt->execute();
                $vhRes = $vacantHousesStmt->get_result();
                while ($hrow = mysqli_fetch_assoc($vhRes)) $houses[] = $hrow['house_no'];
            }

            $conn->begin_transaction();
            try {
                $adminId = $_SESSION['user_id'] ?? 'user002';
                $dateSent = date('Y-m-d H:i:s');

                for ($i = 0; $i < count($winners); $i++) {
                    $winner = $winners[$i];
                    $assignedHouse = $houses[$i] ?? null;

                    $upd = $conn->prepare("UPDATE applications SET status = 'won', house_no = ? WHERE application_id = ?");
                    $upd->bind_param('ss', $assignedHouse, $winner['application_id']);
                    $upd->execute();

                    $lastTenant = mysqli_query($conn, "SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
                    $nextId = 'T001';
                    if ($t = mysqli_fetch_assoc($lastTenant)) {
                        $num = (int)substr($t['tenant_id'], 1) + 1;
                        $nextId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
                    }
                    $today = date('Y-m-d');
                    if ($assignedHouse) {
                        $ins = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date) VALUES (?, ?, ?, ?)");
                        $ins->bind_param('ssss', $nextId, $winner['applicant_id'], $assignedHouse, $today);
                        $ins->execute();
                        $conn->query("UPDATE houses SET status = 'Occupied' WHERE house_no = '" . $conn->real_escape_string($assignedHouse) . "'");
                    }
                    // Mark winner as tenant (set status and role) so they appear in Tenants list
                    $conn->query("UPDATE applicants SET status = 'Tenant', role = 'tenant' WHERE applicant_id = '" . $conn->real_escape_string($winner['applicant_id']) . "'");
                    @mysqli_query($conn, "UPDATE balloting SET status = 'won' WHERE applicant_id = '" . $conn->real_escape_string($winner['applicant_id']) . "'");

                    $resName = $conn->prepare("SELECT name, email FROM applicants WHERE applicant_id = ?");
                    $winnerName = 'Applicant';
                    $winnerEmail = null;
                    if ($resName) {
                        $resName->bind_param('s', $winner['applicant_id']);
                        $resName->execute();
                        $rN = $resName->get_result()->fetch_assoc();
                        $winnerName = $rN['name'] ?? 'Applicant';
                        $winnerEmail = $rN['email'] ?? null;
                    }
                    $notificationId = uniqid('NT');
                    $title = 'Admin';
                    $message = "Dear \"{$winnerName}\": Congratulations, you have been allocated house " . ($assignedHouse ?: '') . ".";
                    insert_notification_safe($conn, $notificationId, $adminId, 'applicant', $winner['applicant_id'], $message, $dateSent, 'unread', $title);
                    if ($winnerEmail) {
                        try {
                            require_once __DIR__ . '/../includes/helpers.php';
                            require_once __DIR__ . '/../includes/email.php';
                            $htmlBody = build_email_wrapper('<p>' . htmlspecialchars($message) . '</p>');
                            if (function_exists('notify_and_email')) {
                                notify_and_email($conn, 'applicant', $winner['applicant_id'], $winnerEmail, 'Congratulations — Ballot Win', $htmlBody, 'Ballot Win');
                            } else {
                                send_email($winnerEmail, 'Congratulations — Ballot Win', $htmlBody, true);
                            }
                        } catch (Exception $e) {
                            error_log('Failed sending winner email to ' . $winnerEmail . ': ' . $e->getMessage());
                        }
                    }
                }

                foreach ($losers as $loser) {
                    // Canonicalize loser status to 'Not Successful'
                    // Use applicant_id + category (more reliable than application_id in mixed flows)
                    $rej = $conn->prepare("UPDATE applications SET status = 'unsuccessful' WHERE applicant_id = ? AND category = ?");
                    $rej->bind_param('ss', $loser['applicant_id'], $loser['category']);
                    $rej->execute();
                    @mysqli_query($conn, "UPDATE balloting SET status = 'unsuccessful' WHERE applicant_id = '" . $conn->real_escape_string($loser['applicant_id']) . "' AND category = '" . $conn->real_escape_string($loser['category']) . "'");
                    $resL = $conn->prepare("SELECT name, email FROM applicants WHERE applicant_id = ?");
                    $lname = 'Applicant';
                    $lEmail = null;
                    if ($resL) {
                        $resL->bind_param('s', $loser['applicant_id']);
                        $resL->execute();
                        $rl = $resL->get_result()->fetch_assoc();
                        $lname = $rl['name'] ?? 'Applicant';
                        $lEmail = $rl['email'] ?? null;
                    }
                    $nId = uniqid('NT');
                    $lmsg = "Dear \"{$lname}\": The ballot has closed; you were not selected for category {$category}.";
                    insert_notification_safe($conn, $nId, $adminId, 'applicant', $loser['applicant_id'], $lmsg, $dateSent, 'unread', $title);
                    if ($lEmail) {
                        try {
                            require_once __DIR__ . '/../includes/helpers.php';
                            require_once __DIR__ . '/../includes/email.php';
                            $htmlBody = build_email_wrapper('<p>' . htmlspecialchars($lmsg) . '</p>');
                            if (function_exists('notify_and_email')) {
                                notify_and_email($conn, 'applicant', $loser['applicant_id'], $lEmail, 'Ballot Result - Not Selected', $htmlBody, 'Ballot Result');
                            } else {
                                send_email($lEmail, 'Ballot Result - Not Selected', $htmlBody, true);
                            }
                        } catch (Exception $e) {
                            error_log('Failed sending loser email to ' . $lEmail . ': ' . $e->getMessage());
                        }
                    }
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                logs_write('error', 'Choose winner failed for category ' . $category . ': ' . $e->getMessage());
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

        if ($_POST['applicant_id'] === 'all') {
            $res = mysqli_query($conn, "SELECT applicant_id, name FROM applicants");
            while ($row = mysqli_fetch_assoc($res)) {
                $notificationId = uniqid('NT');
                $recipientId = $row['applicant_id'];
                $name = $row['name'] ?: 'Applicant';
                $msgFormatted = "Dear \"{$name}\": " . $message;
                insert_notification_safe($conn, $notificationId, $adminId, $recipientType, $recipientId, $msgFormatted, $dateSent, $status, 'Admin');
            }
        } else {
            $applicantId = $_POST['applicant_id'];
            $notificationId = uniqid('NT');
            $resN = mysqli_query($conn, "SELECT name FROM applicants WHERE applicant_id = '{$applicantId}' LIMIT 1");
            $nrow = $resN ? mysqli_fetch_assoc($resN) : null;
            $name = $nrow['name'] ?? 'Applicant';
            $msgFormatted = "Dear \"{$name}\": " . $message;
            insert_notification_safe($conn, $notificationId, $adminId, $recipientType, $applicantId, $msgFormatted, $dateSent, $status, 'Admin');
        }
    }
    elseif (isset($_POST['admin_forfeit_action'])) {
        // Admin approves or rejects a post-forfeit request
        $adminId = $_SESSION['user_id'] ?? 'admin';
        $action = $_POST['admin_forfeit_action']; // approve or reject
        $reqId = $_POST['request_id'] ?? '';
        $notes = trim($_POST['decision_notes'] ?? '');

        if ($reqId === '' || !in_array($action, ['approve','reject'])) {
            $_SESSION['flash_error'] = 'Invalid action.';
            header('Location: manage_applicants.php'); exit;
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $upd = $conn->prepare("UPDATE post_forfeit_requests SET status = ?, admin_id = ?, decision_notes = ?, decided_at = NOW() WHERE request_id = ? LIMIT 1");
        if ($upd) {
            $upd->bind_param('ssss', $newStatus, $adminId, $notes, $reqId);
            if ($upd->execute()) {
                // Notify applicant
                $rq = $conn->prepare("SELECT applicant_id, application_id FROM post_forfeit_requests WHERE request_id = ? LIMIT 1");
                if ($rq) { $rq->bind_param('s', $reqId); $rq->execute(); $rres = $rq->get_result()->fetch_assoc(); }
                $appId = $rres['applicant_id'] ?? null;
                $applicationId = $rres['application_id'] ?? null;
                
                // If approved, update the application status to 'forfeit'
                if ($newStatus === 'approved' && $appId) {
                    if ($applicationId) {
                        // Update specific application if tracked
                        $updApp = $conn->prepare("UPDATE applications SET status = 'forfeit' WHERE application_id = ? LIMIT 1");
                        if ($updApp) {
                            $updApp->bind_param('s', $applicationId);
                            $updApp->execute();
                        }
                    } else {
                        // Update all pending/applied applications for this applicant
                        $updAllApp = $conn->prepare("UPDATE applications SET status = 'forfeit' WHERE applicant_id = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved')");
                        if ($updAllApp) {
                            $updAllApp->bind_param('s', $appId);
                            $updAllApp->execute();
                        }
                    }
                }
                
                if ($appId) {
                    $nid = uniqid('NT');
                    $msg = ($newStatus === 'approved') ? "Your post-close forfeit request ({$reqId}) has been approved. Your application has been forfeited." : "Your post-close forfeit request ({$reqId}) has been rejected.";
                    insert_notification_safe($conn, $nid, $adminId, 'applicant', $appId, $msg, date('Y-m-d H:i:s'), 'unread', 'Forfeit Request');
                    // try sending email if applicant has email
                    $em = $conn->prepare("SELECT email, name FROM applicants WHERE applicant_id = ? LIMIT 1");
                    if ($em) { $em->bind_param('s', $appId); $em->execute(); $emr = $em->get_result()->fetch_assoc(); $appEmail = $emr['email'] ?? null; $appName = $emr['name'] ?? 'Applicant'; }
                    if (!empty($appEmail)) {
                        try {
                                require_once __DIR__ . '/../includes/email.php';
                                $tpl = __DIR__ . '/../templates/emails/post_forfeit_request_decision.html';
                                if (file_exists($tpl)) {
                                    $body = file_get_contents($tpl);
                                    $body = str_replace('{{applicant_name}}', htmlspecialchars($appName), $body);
                                    $body = str_replace('{{request_id}}', htmlspecialchars($reqId), $body);
                                    $body = str_replace('{{decision}}', htmlspecialchars(strtoupper($newStatus)), $body);
                                    $body = str_replace('{{notes}}', nl2br(htmlspecialchars($notes)), $body);
                                } else {
                                    $body = '<p>Dear ' . htmlspecialchars($appName) . ',</p><p>' . htmlspecialchars($msg) . '</p>';
                                }
                                $html = build_email_wrapper($body);
                                if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $appId, $appEmail, 'Forfeit Request Decision', $html, 'Forfeit Decision');
                            } catch (Exception $e) { error_log('Forfeit-email: ' . $e->getMessage()); }
                }
                $_SESSION['flash_success'] = 'Request updated.';
            } else {
                $_SESSION['flash_error'] = 'Failed to update request.';
            }
        } else {
            $_SESSION['flash_error'] = 'Request table missing or invalid.';
        }

        header('Location: manage_applicants.php'); exit;
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
        body { margin: 0; font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif; background-color: #fff; }
        .content { margin-left: 220px; padding: 20px 30px; min-height: 100vh; background-color: #fff; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; border-bottom: 1px solid #ddd; }
        .header-bar h1 { font-size: 28px; color: green; margin: 0; font-weight: bold; }
        .profile-icon { width: 38px; height: 38px; border-radius: 50%; background-image: url('../images/profile-icon.png'); background-size: cover; }
        .tab-buttons { display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; }
        .tab-buttons button {
            padding: 10px 16px;
            background-color: #f3f5f7;
            color: #064b1a;
            border: 1px solid #cfe4d2;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 700;
            transition: background-color .15s ease, border-color .15s ease, transform .05s ease;
        }
        .tab-buttons button:hover { background-color: #e9f3ea; border-color: #9fd2a6; }
        .tab-buttons button:active { transform: translateY(1px); }
        .tab-buttons button.active { background-color: #006400; color: #fff; border-color: #006400; }
        .tab-buttons button:focus-visible { outline: 2px solid #006400; outline-offset: 2px; }

        .btn {
            padding: 10px 16px;
            background: #006400;
            color: #fff;
            border: 1px solid #006400;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 700;
            transition: background-color .15s ease, border-color .15s ease, transform .05s ease;
        }
        .btn:hover { background: #004d00; border-color: #004d00; }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: .55; cursor: not-allowed; background: #6c757d; border-color: #6c757d; transform: none; }
        .btn:focus-visible { outline: 2px solid #006400; outline-offset: 2px; }
        .btn-sm { padding: 6px 10px; border-radius: 6px; font-weight: 700; }
        .btn-secondary { background: #6c757d; border-color: #6c757d; }
        .btn-secondary:hover { background: #5a6268; border-color: #5a6268; }
        .btn-danger { background: #f44336; border-color: #f44336; }
        .btn-danger:hover { background: #d9362b; border-color: #d9362b; }
        .btn-warning { background: orange; border-color: orange; color: #1b1b1b; }
        .btn-warning:hover { background: #e08600; border-color: #e08600; }

        .btn-link { background: #eee; color: #333; border: 1px solid #ccc; }
        .btn-link:hover { background: #e3e3e3; }

        .section { display: none; padding: 10px 0; }
        .section.active { display: block; }
        table { width: 100%; border-collapse: collapse; background-color: white; margin-top: 10px; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        thead { background-color: #006400; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: white; }
        tbody tr:nth-child(even) { background-color: #fafafa; }
        tbody tr:hover { background-color: #f1f8f1; }

        select, input[type="text"], textarea {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            outline: none;
        }
        select:focus, input[type="text"]:focus, textarea:focus { border-color: #006400; box-shadow: 0 0 0 2px rgba(0,100,0,0.15); }

        .status-dropdown { padding: 6px 10px; border-radius: 6px; border: 1px solid #ccc; }
        .save-btn { margin-left: 8px; }

        .notification-form { display: none; background:rgb(240, 236, 236); padding: 10px; margin-top: 10px; }
        .toast { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; z-index: 9999; min-width: 300px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out; }
        .toast.success { background-color: #4CAF50; }
        .toast.error { background-color: #f44336; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }

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
                left: -220px;
                z-index: 100;
                transition: left 0.3s ease;
            }
            .hamburger-menu {
                display: flex;
            }
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .header-bar {
                flex-wrap: wrap;
                gap: 10px;
            }
            .header-bar h1 {
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
                width: 200px;
            }
            .header-bar h1 {
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
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div style="display:flex;align-items:center;gap:12px;">
            <button type="button" class="btn btn-sm btn-link" onclick="history.back();" style="background:#006400;color:#fff;border:1px solid #006400;padding:6px 8px;border-radius:4px;">Back</button>
            <h1>JKUAT STAFF HOUSING PORTAL</h1>
        </div>
        <div class="profile-icon" title="Profile"></div>
    </div>

    <div class="tab-buttons">
        <button onclick="showSection('applicantsSection')" id="applicantsBtn" class="active">Applicants</button>
        <button onclick="showSection('applicationsSection')" id="applicationsBtn">Applications</button>
        <button onclick="showSection('ballotsSection')" id="ballotsBtn">Ballots</button>
        <button onclick="showSection('forfeitsSection')" id="forfeitsBtn">Forfeit Requests</button>
        <button onclick="showSection('manualSection')" id="manualBtn">Manual Allocation</button>
    </div>

    <div class="section active" id="applicantsSection">
        <?php if (!getenv('SMTP_HOST')): ?>
            <div style="background:#fff3cd;border:1px solid #ffeeba;padding:10px;margin-bottom:12px;border-radius:4px;color:#856404;">
                <strong>Notice:</strong> Email delivery is not configured. Outgoing messages are written to <code>logs/emails.log</code>.
            </div>
        <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div style="background:#f8d7da;border:1px solid #f5c2c7;padding:10px;margin-bottom:12px;border-radius:4px;color:#842029;">
                    <strong>Error:</strong> <?= htmlspecialchars($_SESSION['flash_error']) ?>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
        <h2 class="section-title">Applicants 
            <a href="?filter=missing" style="font-size:14px; color:#666; margin-left:10px;">(Missing Profile)</a>
            <a href="?filter=disabled" style="font-size:14px; color:#666; margin-left:10px;">(Disabled)</a>
            <?php if (!empty($filter)): ?> <a href="manage_applicants.php" style="font-size:14px; color:#666; margin-left:10px;">(Clear filter)</a><?php endif; ?>
        </h2>

        <?php
            // quick summary: count disabled applicants
            $disabled_count = 0;
            $dc = $conn->query("SELECT COUNT(*) as cnt FROM applicants WHERE is_disabled = 1");
            if ($dc) { $dcr = $dc->fetch_assoc(); $disabled_count = (int)($dcr['cnt'] ?? 0); }
        ?>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
            <form method="get" style="margin:0;">
                <label>Per page:
                    <select name="app_per_page" onchange="this.form.submit()">
                        <option value="10" <?= $app_per_page==10? 'selected': '' ?>>10</option>
                        <option value="25" <?= $app_per_page==25? 'selected': '' ?>>25</option>
                        <option value="50" <?= $app_per_page==50? 'selected': '' ?>>50</option>
                        <option value="100" <?= $app_per_page==100? 'selected': '' ?>>100</option>
                    </select>
                </label>
                <input type="hidden" name="app_page" value="<?= intval($app_page) ?>">
                <?php if (!empty($filter)): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
            </form>
            <div style="color:#666;font-size:14px;">Total: <?= intval($total_applicants) ?></div>
            <div style="color:#666;font-size:14px;margin-left:12px;">Disabled: <strong style="color:#006400;"><?= intval($disabled_count) ?></strong></div>
        </div>

        <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; border-radius: 6px;">
            <form method="post">
                <input type="hidden" name="applicant_id" value="all">
                <label style="font-weight: bold; display:block; margin-bottom:5px;">Send Notification to All Applicants:</label>
                <textarea name="message" placeholder="Write your message here..." rows="3" style="width: 90%; padding: 10px;" required></textarea>
                <button type="submit" name="send_notification" class="btn">Send to All</button>
            </form>
        </div>

        <table>
            <thead><tr><th>ID</th><th>PF Number</th><th>Name</th><th>Email</th><th>Contact</th><th>Next of Kin</th><th>Next of Kin Contact</th><th>Disabled</th><th>Username</th><th>Status</th><th>Notify</th></tr></thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($applicants)) {
                // Determine applicant-level display status:
                // - If applicant record already marked Tenant, show 'Tenant'
                // - Else if the applicant has any applications, show 'Applied'
                // - Otherwise show 'No Application'
                $displayStatus = 'No Application';
                if (!empty($row['status']) && strtolower($row['status']) === 'tenant') {
                    $displayStatus = 'Tenant';
                } else {
                    // Count only non-cancelled applications so forfeited/cancelled ones don't mark the
                    // applicant as having an active application.
                    $cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE applicant_id = ? AND LOWER(status) != 'cancelled'");
                    if ($cntStmt) {
                        $cntStmt->bind_param('s', $row['applicant_id']);
                        $cntStmt->execute();
                        $c = $cntStmt->get_result()->fetch_assoc();
                        if ($c && (int)$c['cnt'] > 0) $displayStatus = 'Applied';
                    }
                }

                // pick a color for display
                switch (strtolower($displayStatus)) {
                    case 'pending': $statusColor = 'orange'; break;
                    case 'approved': $statusColor = 'blue'; break;
                    case 'won': $statusColor = 'goldenrod'; break;
                    case 'rejected': $statusColor = 'red'; break;
                    case 'cancelled': $statusColor = 'gray'; break;
                    case 'tenant': $statusColor = 'green'; break;
                    case 'applied': $statusColor = 'red'; break;
                    default: $statusColor = 'gray'; break;
                }
            ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($row, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'pf_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'email')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'contact')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_name')) ?></td>
                    <td><?= safe_echo(safe_array_get($row, 'next_of_kin_contact')) ?></td>
                    <td>
                        <?php if (!empty($row['is_disabled']) && intval($row['is_disabled']) === 1) {
                            echo '<span style="color:#c0392b;font-weight:700;">Yes</span>';
                            echo ' <a href="view_disability_details.php?id=' . htmlspecialchars($row['applicant_id']) . '" style="margin-left:8px;font-size:0.85em;color:#006400;text-decoration:underline;cursor:pointer;">View</a>';
                        } else {
                            echo '<span style="color:#4CAF50;font-weight:700;">No</span>';
                        } ?>
                    </td>
                    <td><?= safe_echo(safe_array_get($row, 'username')) ?></td>
                    <td><span style="color: <?= $statusColor ?>; font-weight: bold;"><?= htmlspecialchars($displayStatus) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleForm('form<?= $row['applicant_id'] ?>')">Notify</button>
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
        <?php
            $app_total_pages = max(1, ceil($total_applicants / $app_per_page));
            echo '<div style="margin-top:8px;">';
            if ($app_page > 1) echo '<a href="?app_page='.($app_page-1).'&app_per_page='.$app_per_page.'">&laquo; Prev</a> ';
            echo ' Page '.intval($app_page).' of '.intval($app_total_pages).' ';
            if ($app_page < $app_total_pages) echo ' <a href="?app_page='.($app_page+1).'&app_per_page='.$app_per_page.'">Next &raquo;</a>';
            echo '</div>';
        ?>
    </div>

    <div class="section" id="applicationsSection">
        <h2 class="section-title">Applications</h2>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
            <form method="get" style="margin:0;">
                <label>Per page:
                    <select name="apps_per_page" onchange="this.form.submit()">
                        <option value="10" <?= $apps_per_page==10? 'selected': '' ?>>10</option>
                        <option value="25" <?= $apps_per_page==25? 'selected': '' ?>>25</option>
                        <option value="50" <?= $apps_per_page==50? 'selected': '' ?>>50</option>
                        <option value="100" <?= $apps_per_page==100? 'selected': '' ?>>100</option>
                    </select>
                </label>
                <input type="hidden" name="apps_page" value="<?= intval($apps_page) ?>">
            </form>
            <div style="color:#666;font-size:14px;">Total: <?= intval($total_apps) ?></div>
        </div>
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
                                <?php
                                    $cur = strtolower(trim($app['status'] ?? ''));
                                    // Admin may only change from Applied -> Allocated here. Other statuses are managed automatically.
                                    if ($cur === 'applied') {
                                        // show a dropdown with Applied (selected) and Allocated option
                                        ?>
                                        <select class="status-dropdown" data-app-id="<?= htmlspecialchars($app['application_id']) ?>">
                                            <option value="applied" selected>Applied</option>
                                            <option value="allocated">Allocated</option>
                                        </select>
                                        <button type="button" class="btn btn-sm save-btn" data-app-id="<?= htmlspecialchars($app['application_id']) ?>" disabled>Save</button>
                                        <?php
                                    } else {
                                        // display read-only status with color
                                        $raw = $app['status'] ?? '';
                                        $norm = strtolower(str_replace('_', ' ', $raw));
                                        // If status is empty, show 'No Application' to match applicant-side UX.
                                        $display = $norm !== '' ? ucwords($norm) : 'No Application';
                                        $color = 'gray';
                                        if ($norm === 'applied') $color = 'red';
                                        elseif ($norm === 'pending') $color = 'orange';
                                        elseif ($norm === 'won') $color = 'green';
                                        elseif ($norm === 'not successful' || $norm === 'unsuccessful') $color = 'red';
                                        elseif ($norm === 'allocated') $color = 'green';
                                        echo '<span style="color:'.$color.'; font-weight:700;">'.htmlspecialchars($display).'</span>';
                                    }
                                ?>
                            </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php
            $apps_total_pages = max(1, ceil($total_apps / $apps_per_page));
            echo '<div style="margin-top:8px;">';
            if ($apps_page > 1) echo '<a href="?apps_page='.($apps_page-1).'&apps_per_page='.$apps_per_page.'">&laquo; Prev</a> ';
            echo ' Page '.intval($apps_page).' of '.intval($apps_total_pages).' ';
            if ($apps_page < $apps_total_pages) echo ' <a href="?apps_page='.($apps_page+1).'&apps_per_page='.$apps_per_page.'">Next &raquo;</a>';
            echo '</div>';
        ?>
    </div>

    <div class="section" id="ballotsSection">
        <h2 class="section-title">Ballots</h2>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
            <form method="get" style="margin:0;">
                <label>Per page:
                    <select name="ballots_per_page" onchange="this.form.submit()">
                        <option value="10" <?= $ballots_per_page==10? 'selected': '' ?>>10</option>
                        <option value="25" <?= $ballots_per_page==25? 'selected': '' ?>>25</option>
                        <option value="50" <?= $ballots_per_page==50? 'selected': '' ?>>50</option>
                        <option value="100" <?= $ballots_per_page==100? 'selected': '' ?>>100</option>
                    </select>
                </label>
                <input type="hidden" name="ballots_page" value="<?= intval($ballots_page) ?>">
            </form>
            <div style="color:#666;font-size:14px;">Total: <?= intval($total_ballots) ?></div>
        </div>
        <table>
            <thead><tr><th>ID</th><th>Applicant ID</th><th>House ID</th><th>Ballot No</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($b = mysqli_fetch_assoc($ballots)) {
                // Determine display status: prefer application status for this applicant+house, else balloting.status, else default to Approved
                $displayStatus = null;
                $applicantId = $b['applicant_id'];
                $houseId = $b['house_id'];

                // Resolve house_no from houses table if possible
                $houseNo = null;
                $hStmt = $conn->prepare("SELECT house_no FROM houses WHERE house_id = ? LIMIT 1");
                if ($hStmt) {
                    $hStmt->bind_param('s', $houseId);
                    $hStmt->execute();
                    $hr = $hStmt->get_result()->fetch_assoc();
                    $houseNo = $hr['house_no'] ?? null;
                }

                if ($houseNo) {
                    $aStmt = $conn->prepare("SELECT status FROM applications WHERE applicant_id = ? AND house_no = ? ORDER BY date DESC LIMIT 1");
                    if ($aStmt) {
                        $aStmt->bind_param('ss', $applicantId, $houseNo);
                        $aStmt->execute();
                        $ar = $aStmt->get_result()->fetch_assoc();
                        if ($ar && !empty($ar['status'])) $displayStatus = $ar['status'];
                    }
                }

                if (!$displayStatus && !empty($b['status'])) {
                    $displayStatus = $b['status'];
                }

                if (!$displayStatus) {
                    // Before winners are chosen we want ballots to read 'Approved' by default
                    $displayStatus = 'Approved';
                }
            ?>
                <tr>
                    <td><?= safe_echo(safe_array_get($b, 'ballot_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'applicant_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'house_id')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'ballot_no')) ?></td>
                    <td><?= safe_echo(safe_array_get($b, 'date_of_ballot')) ?></td>
                    <td><?= htmlspecialchars($displayStatus) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php
            $ballots_total_pages = max(1, ceil($total_ballots / $ballots_per_page));
            echo '<div style="margin-top:8px;">';
            if ($ballots_page > 1) echo '<a href="?ballots_page='.($ballots_page-1).'&ballots_per_page='.$ballots_per_page.'">&laquo; Prev</a> ';
            echo ' Page '.intval($ballots_page).' of '.intval($ballots_total_pages).' ';
            if ($ballots_page < $ballots_total_pages) echo ' <a href="?ballots_page='.($ballots_page+1).'&ballots_per_page='.$ballots_per_page.'">Next &raquo;</a>';
            echo '</div>';
        ?>

        <form id="ballotControlsForm" method="POST" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="display:flex;gap:8px;align-items:center;">
                <label style="font-weight:700;">Ballot end date (optional)</label>
                <input type="date" name="ballot_end_date" style="padding:8px;border:1px solid #ccc;border-radius:6px;">
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <label style="font-weight:700;">Or duration (days)</label>
                <input type="number" name="ballot_duration_days" min="1" placeholder="14" style="width:90px;padding:8px;border:1px solid #ccc;border-radius:6px;">
            </div>
                <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn" name="start_ballot_custom" title="Start ballot with the specified end date or duration">Start Balloting</button>
                <button class="btn btn-danger" name="end_ballot">End Balloting</button>
                <button class="btn btn-warning" name="choose_winner" onclick="return confirm('Confirm choosing random winners?')">Choose Winner(s)</button>
            </div>
        </form>
    </div>

    <div class="section" id="forfeitsSection">
        <h2 class="section-title">Post-Closed Forfeit Requests</h2>
        <?php if ($forfeit_requests === null): ?>
            <div style="background:#fff3cd;border:1px solid #ffeeba;padding:10px;margin-bottom:12px;border-radius:4px;color:#856404;">The post-forfeit requests table is not present. Run migrations.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Request ID</th><th>Applicant</th><th>Reason</th><th>Attachment</th><th>Created</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($r = mysqli_fetch_assoc($forfeit_requests)) { ?>
                    <tr>
                        <td><?= htmlspecialchars($r['request_id']) ?></td>
                        <td><?= htmlspecialchars($r['applicant_name'] ?: $r['applicant_id']) ?><br><small style="color:#666;"><?= htmlspecialchars($r['applicant_id']) ?></small></td>
                        <td style="max-width:380px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= htmlspecialchars($r['reason']) ?></td>
                        <td><?php if (!empty($r['attachment'])) echo '<a href="../' . htmlspecialchars($r['attachment']) . '" target="_blank">View</a>'; else echo '-'; ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending') { ?>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($r['request_id']) ?>">
                                    <input type="hidden" name="decision_notes" value="">
                                    <button class="btn btn-sm" name="admin_forfeit_action" value="approve">Approve</button>
                                </form>
                                <form method="post" style="display:inline-block;margin-left:6px;">
                                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($r['request_id']) ?>">
                                    <input type="hidden" name="decision_notes" value="">
                                    <button class="btn btn-danger btn-sm" name="admin_forfeit_action" value="reject">Reject</button>
                                </form>
                            <?php } else { ?>
                                <span style="font-weight:700;"><?= htmlspecialchars($r['status']) ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section" id="manualSection">
        <h2 class="section-title">Manual Allocation (CS Admin)</h2>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background:#f8d7da;border:1px solid #f5c2c7;padding:10px;margin-bottom:12px;border-radius:4px;color:#842029;">
                <strong>Error:</strong> <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background:#d4edda;border:1px solid #c3e6cb;padding:10px;margin-bottom:12px;border-radius:4px;color:#155724;">
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form method="POST">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label style="display:block;font-weight:bold;">Select Applicant</label>
                    <select name="applicant_id" required>
                        <option value="">-- Select Applicant --</option>
                        <?php
                            // Prefer showing disabled staff first to make manual allocation for disabled staff easier
                            $includeDetails = column_exists($conn, 'applicants', 'disability_details');
                            if (column_exists($conn, 'applicants', 'is_disabled')) {
                                $sqlApp = "SELECT applicant_id, name, email, is_disabled" . ($includeDetails ? ", disability_details" : "") . " FROM applicants ORDER BY is_disabled DESC, name ASC";
                            } else {
                                // older schemas may not have is_disabled column; fall back to simple ordering
                                $sqlApp = "SELECT applicant_id, name, email" . ($includeDetails ? ", disability_details" : "") . " FROM applicants ORDER BY name ASC";
                            }
                            $app_q = $conn->prepare($sqlApp);
                            if ($app_q) {
                                $app_q->execute();
                                $app_res = $app_q->get_result();
                                while ($ap = mysqli_fetch_assoc($app_res)) {
                                    $label = ($ap['name'] ?: $ap['applicant_id']) . ' (' . ($ap['email'] ?? '-') . ')';
                                    $titleAttr = '';
                                    if (!empty($ap['is_disabled']) && intval($ap['is_disabled']) === 1) {
                                        $label .= ' - DISABLED';
                                        if (!empty($ap['disability_details'])) {
                                            $preview = strlen($ap['disability_details']) > 120 ? substr($ap['disability_details'],0,117) . '...' : $ap['disability_details'];
                                            $titleAttr = ' title="' . htmlspecialchars($preview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                                        }
                                    }
                                    echo '<option value="' . htmlspecialchars($ap['applicant_id']) . '"' . $titleAttr . '>' . htmlspecialchars($label) . '</option>';
                                }
                            }
                        ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:bold;">Select Vacant House</label>
                    <select name="house_no" required>
                        <option value="">-- Select House --</option>
                        <?php
                            $hres = mysqli_query($conn, "SELECT house_no, category FROM houses WHERE LOWER(status) = 'vacant' ORDER BY house_no ASC");
                            while ($h = mysqli_fetch_assoc($hres)) {
                                echo '<option value="' . htmlspecialchars($h['house_no']) . '">' . htmlspecialchars($h['house_no'] . ' - ' . $h['category']) . '</option>';
                            }
                        ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:bold;">Notes (optional)</label>
                    <input type="text" name="notes" placeholder="Reason/notes">
                </div>
                <div style="align-self:end;">
                    <button class="btn" name="manual_allocate">Allocate</button>
                </div>
            </div>
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

// Wire up per-row Save buttons for status commits
document.querySelectorAll('.status-dropdown').forEach(dropdown => {
    const appId = dropdown.getAttribute('data-app-id');
    const saveBtn = document.querySelector('.save-btn[data-app-id="' + appId + '"]');
    if (!saveBtn) return;
    let original = dropdown.value;
    dropdown.addEventListener('change', function() {
        // enable save only when change detected
        saveBtn.disabled = (this.value === original);
    });
    saveBtn.addEventListener('click', function() {
        const applicationId = this.getAttribute('data-app-id');
        const newStatus = dropdown.value;
        if (!confirm('Confirm saving status change for ' + applicationId + ' -> ' + newStatus + '?')) return;
        this.disabled = true;
        fetch('update_application_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `application_id=${encodeURIComponent(applicationId)}&status=${encodeURIComponent(newStatus)}`
        }).then(r => r.json()).then(data => {
            if (data && data.success) {
                showToast('Status saved', 'success');
                original = newStatus;
                saveBtn.disabled = true;
                // replace dropdown with read-only label to reflect that admin cannot further change unless new Applied
                dropdown.parentElement.innerHTML = '<span style="color: green; font-weight:700;">' + data.status + '</span>';
            } else {
                showToast((data && data.error) || 'Failed to save', 'error');
                saveBtn.disabled = false;
            }
        }).catch(err => {
            console.error(err);
            showToast('Server error saving status', 'error');
            saveBtn.disabled = false;
        });
    });
});

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

// Ballot controls: intercept start/end form to submit via AJAX and require confirmation
document.addEventListener('DOMContentLoaded', function(){
    try {
        const form = document.getElementById('ballotControlsForm');
        if (!form) return;

        form.addEventListener('submit', function(e){
            // find which button was clicked using submitter (modern browsers)
            const submitter = (e.submitter) ? e.submitter : null;
            if (!submitter) return; // allow normal submit for older browsers

            const name = submitter.getAttribute('name');
            if (!name) return;

            // Only intercept start_ballot_custom and end_ballot
            if (name !== 'start_ballot_custom' && name !== 'end_ballot') return;

            e.preventDefault();

            const confirmMsg = name === 'start_ballot_custom' ? 'Start the ballot now? This will notify applicants.' : 'End the ballot now? This will notify applicants.';
            if (!confirm(confirmMsg)) return;

            const data = new FormData(form);
            data.append('ajax', '1');

            // include which button was pressed
            data.append(name, '1');

            fetch('manage_applicants.php', { method: 'POST', body: data, credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json && json.success) {
                    showToast('Ballot state updated', 'success');
                    // write a timestamp to localStorage so other tabs can react
                    try { localStorage.setItem('ballot_state_updated', Date.now().toString()); } catch(e){}
                } else {
                    showToast('Failed to update ballot state', 'error');
                }
            }).catch(err => {
                console.error(err);
                showToast('Server error updating ballot', 'error');
            });
        });
    } catch (e) { console.error(e); }
});
</script>
</body>
</html>