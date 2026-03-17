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

// Count competing applicants in a category (RAFFLE AUTO-DETECTION)
function countCompetingApplicants($conn, $category) {
    try {
        // Count all active applications (not rejected, cancelled, unsuccessful, won, or allocated)
        // Won/Allocated applicants should not be considered for ballot
        // Active eligible statuses: applied, pending, approved
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT applicant_id) as cnt FROM applications WHERE category = ? AND COALESCE(LOWER(REPLACE(NULLIF(TRIM(status),''), '_', ' ')), 'pending') NOT IN ('rejected','cancelled','unsuccessful','not successful','won','allocated')");
        if ($stmt) {
            $stmt->bind_param('s', $category);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }
    } catch (Exception $e) {
        return 0;
    }
    return 0;
}

// Get competing applicants for a category
function getCompetingApplicants($conn, $category) {
    try {
        // Get all active applicants (not rejected, cancelled, unsuccessful, won, or allocated)
        // Won/Allocated applicants should not be considered for ballot
        $stmt = $conn->prepare("SELECT DISTINCT applicant_id FROM applications WHERE category = ? AND COALESCE(LOWER(REPLACE(NULLIF(TRIM(status),''), '_', ' ')), 'pending') NOT IN ('rejected','cancelled','unsuccessful','not successful','won','allocated') ORDER BY applicant_id");
        if ($stmt) {
            $stmt->bind_param('s', $category);
            $stmt->execute();
            $result = $stmt->get_result();
            $applicants = [];
            while ($row = $result->fetch_assoc()) {
                $applicants[] = $row['applicant_id'];
            }
            return $applicants;
        }
    } catch (Exception $e) {
        return [];
    }
    return [];
}

$ballot_error = '';
$ballot_success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ballot'])) {
    if (!$ballot_open) {
        $ballot_error = 'Ballot is currently closed. Please wait for the administrator to start the ballot.';
    } else {
        $ballot_id = generateBallotId($conn);
        $house_id = $_POST['house_id'] ?? null;
        $slot_number = $_POST['slot_number'] ?? null;
        $category = $_POST['category'] ?? null;
        $date_of_ballot = $_POST['date_of_ballot'] ?? date('Y-m-d');
        $status = "Pending";

        // Get applicant's applications to determine category if not provided
        if (empty($category)) {
            $catStmt = $conn->prepare("SELECT DISTINCT category FROM applications WHERE applicant_id = ? AND LOWER(status) IN ('applied','pending','approved') LIMIT 1");
            $catStmt->bind_param('s', $applicant_id);
            $catStmt->execute();
            $catRes = $catStmt->get_result()->fetch_assoc();
            if ($catRes) {
                $category = $catRes['category'];
            }
        }

        if (empty($category)) {
            $ballot_error = 'No application found. Please make an application first.';
        } else {
            // server-side enforcement: applicant may only ballot for a category they applied for
            try {
                $apCheck = $conn->prepare("SELECT 1 FROM applications WHERE applicant_id = ? AND category = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved') LIMIT 1");
                if ($apCheck) {
                    $apCheck->bind_param('ss', $applicant_id, $category);
                    $apCheck->execute();
                    $apRes = $apCheck->get_result();
                    if (!$apRes || $apRes->num_rows === 0) {
                        $ballot_error = 'You can only participate in the ballot for a category you applied for.';
                    }
                }
            } catch (Exception $e) {
                // if anything unexpected happens, fall back to original behavior (do not block)
            }
        }
        if (empty($ballot_error) && !empty($slot_number) && !empty($_POST['is_raffle'])) {
            // RAFFLE MODE: Auto-detect competition and create draw/slots on first submission when needed
            $is_raffle = intval($_POST['is_raffle']) === 1;
            $raffle_applicant_count = intval($_POST['raffle_applicant_count'] ?? 0);

            // Server-authoritative competitor count (do not trust client values)
            $currentCompetitors = countCompetingApplicants($conn, $category);
            // FIXME: Enforce strictly - applicants can ONLY ballot for a category they applied for
            $apCheckForBallot = $conn->prepare("SELECT 1 FROM applications WHERE applicant_id = ? AND category = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved') LIMIT 1");
            $apCheckForBallot->bind_param('ss', $applicant_id, $category);
            $apCheckForBallot->execute();
            if (!$apCheckForBallot->get_result()->fetch_assoc()) {
                $ballot_error = 'You can only participate in the ballot for a category you applied for. Your applications do not include this category.';
            } else {
                $raffle_applicant_count = max(2, $raffle_applicant_count, $currentCompetitors);
            }

            if ($slot_number < 1 || $slot_number > $raffle_applicant_count) {
                $ballot_error = 'Invalid slot number.';
            } else {
                $draw_id = null;
                $draw_house_id = null;
                $draw_total_slots = null;

                $drawCheckStmt = $conn->prepare("SELECT draw_id, house_id, total_slots FROM raffle_draws WHERE category = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1");
                $drawCheckStmt->bind_param('s', $category);
                $drawCheckStmt->execute();
                $existingDraw = $drawCheckStmt->get_result()->fetch_assoc();

                // If no open draw exists yet, auto-create one by selecting a vacant house in this category.
                if (!$existingDraw) {
                    $vacStmt = $conn->prepare("SELECT house_id FROM houses WHERE category = ? AND LOWER(status) = 'vacant' ORDER BY house_no ASC LIMIT 1");
                    if ($vacStmt) {
                        $vacStmt->bind_param('s', $category);
                        $vacStmt->execute();
                        $vacRow = $vacStmt->get_result()->fetch_assoc();
                        $vacHouseId = $vacRow['house_id'] ?? null;
                    } else {
                        $vacHouseId = null;
                    }

                    if (empty($vacHouseId)) {
                        $ballot_error = 'No vacant house available for this category right now. Please try again later.';
                    } else {
                        $draw_id_new = 'DRAW' . uniqid();
                        $created_by = $applicant_id; // track who triggered auto-creation
                        $createDraw = $conn->prepare("INSERT INTO raffle_draws (draw_id, house_id, category, total_slots, status, created_by) VALUES (?, ?, ?, ?, 'open', ?)");
                        if ($createDraw) {
                            $createDraw->bind_param('sssis', $draw_id_new, $vacHouseId, $category, $raffle_applicant_count, $created_by);
                            $createDraw->execute();
                        }

                        // Re-fetch the newest open draw for this category (handles concurrent requests)
                        $drawCheckStmt2 = $conn->prepare("SELECT draw_id, house_id, total_slots FROM raffle_draws WHERE category = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1");
                        $drawCheckStmt2->bind_param('s', $category);
                        $drawCheckStmt2->execute();
                        $existingDraw = $drawCheckStmt2->get_result()->fetch_assoc();
                    }
                }

                if (empty($ballot_error) && $existingDraw) {
                    $draw_id = $existingDraw['draw_id'];
                    $draw_house_id = $existingDraw['house_id'] ?? null;
                    $draw_total_slots = intval($existingDraw['total_slots'] ?? 0);

                    // Use authoritative total_slots from the draw if available
                    if ($draw_total_slots >= 2) {
                        $raffle_applicant_count = max($raffle_applicant_count, $draw_total_slots);
                    }

                    // Validate draw_house_id exists in houses (required for ballot audit)
                    $houseOk = false;
                    if (!empty($draw_house_id)) {
                        $hChk = $conn->prepare("SELECT 1 FROM houses WHERE house_id = ? LIMIT 1");
                        if ($hChk) {
                            $hChk->bind_param('s', $draw_house_id);
                            $hChk->execute();
                            $houseOk = (bool)$hChk->get_result()->fetch_assoc();
                        }
                    }

                    if (!$houseOk) {
                        $ballot_error = 'Raffle draw is missing a valid house assignment. Please contact the administrator.';
                    } elseif ($slot_number < 1 || $slot_number > $raffle_applicant_count) {
                        $ballot_error = 'Invalid slot number.';
                    } else {
                        // Persist expansion back to the draw for consistency on future page loads.
                        if ($raffle_applicant_count > $draw_total_slots) {
                            $updSlots = $conn->prepare("UPDATE raffle_draws SET total_slots = ? WHERE draw_id = ? LIMIT 1");
                            if ($updSlots) {
                                $updSlots->bind_param('is', $raffle_applicant_count, $draw_id);
                                $updSlots->execute();
                            }
                        }

                        // Ensure placeholder slots exist for this draw
                        $existingNums = [];
                        $slotsNumsStmt = $conn->prepare("SELECT slot_number FROM raffle_slots WHERE draw_id = ?");
                        if ($slotsNumsStmt) {
                            $slotsNumsStmt->bind_param('s', $draw_id);
                            $slotsNumsStmt->execute();
                            $numsRes = $slotsNumsStmt->get_result();
                            while ($r = $numsRes->fetch_assoc()) {
                                $existingNums[intval($r['slot_number'])] = true;
                            }
                        }

                        for ($slotNum = 1; $slotNum <= $raffle_applicant_count; $slotNum++) {
                            if (!isset($existingNums[$slotNum])) {
                                $slot_id = 'SLOT' . uniqid() . $slotNum;
                                $slotInsert = $conn->prepare("INSERT IGNORE INTO raffle_slots (slot_id, draw_id, applicant_id, slot_number, status) VALUES (?, ?, NULL, ?, 'available')");
                                if ($slotInsert) {
                                    $slotInsert->bind_param('ssi', $slot_id, $draw_id, $slotNum);
                                    $slotInsert->execute();
                                }
                            }
                        }
                    }
                }
                
                // Now mark the selected slot as picked by this applicant
                if (empty($ballot_error) && !empty($draw_id) && !empty($draw_house_id)) {
                    // Resolve draw house_id -> actual house_no for storing in applications.house_no
                    $draw_house_no = null;
                    $hnStmt = $conn->prepare("SELECT house_no FROM houses WHERE house_id = ? LIMIT 1");
                    if ($hnStmt) {
                        $hnStmt->bind_param('s', $draw_house_id);
                        $hnStmt->execute();
                        $hnRow = $hnStmt->get_result()->fetch_assoc();
                        if ($hnRow && isset($hnRow['house_no'])) {
                            $draw_house_no = (string)$hnRow['house_no'];
                        }
                    }

                    // If a legacy row exists where applicant_id is pre-filled but status is still 'available',
                    // clear it first so the applicant can pick any slot (prevents unique key conflicts).
                    $legacyStmt = $conn->prepare("SELECT slot_id, slot_number, status FROM raffle_slots WHERE draw_id = ? AND applicant_id = ? LIMIT 1");
                    $legacyStmt->bind_param('ss', $draw_id, $applicant_id);
                    $legacyStmt->execute();
                    $legacyRow = $legacyStmt->get_result()->fetch_assoc();

                    if ($legacyRow && in_array(strtolower((string)($legacyRow['status'] ?? '')), ['picked', 'winner', 'loser'], true)) {
                        // Applicant has already picked (or the draw was completed)
                        $pickedSlot = intval($legacyRow['slot_number'] ?? 0);

                        // Ensure there is a balloting audit record (idempotent)
                        $auditChk = $conn->prepare("SELECT 1 FROM balloting WHERE applicant_id = ? AND category = ? LIMIT 1");
                        if ($auditChk) {
                            $auditChk->bind_param('ss', $applicant_id, $category);
                            $auditChk->execute();
                            $hasAudit = (bool)$auditChk->get_result()->fetch_assoc();
                            if (!$hasAudit && $pickedSlot > 0) {
                                $ballot_id = generateBallotId($conn);
                                $ballot_no = 'Slot ' . (string)$pickedSlot;
                                $auditHouseId = $draw_house_id;
                                $ballotInsert = $conn->prepare("INSERT INTO balloting (ballot_id, applicant_id, house_id, ballot_no, date_of_ballot, status, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                if ($ballotInsert) {
                                    $status_val = "Pending";
                                    $ballotInsert->bind_param('sssssss', $ballot_id, $applicant_id, $auditHouseId, $ballot_no, $date_of_ballot, $status_val, $category);
                                    $ballotInsert->execute();
                                }
                            }
                        }

                                $ballot_success = "🎰 You already selected Slot {$pickedSlot}. Waiting for raffle draw results.";
                                // Notify applicant about existing slot selection
                                $dateSent = date('Y-m-d H:i:s');
                                $notificationId = uniqid('NT');
                                $msg = "You selected Slot {$pickedSlot} for category {$category}. Waiting for raffle results.";
                                if (function_exists('notify_insert_safe')) {
                                    notify_insert_safe($conn, $notificationId, $applicant_id, 'applicant', $applicant_id, $msg, $dateSent, 'unread', 'Raffle Slot Selected');
                                }
                                $email = $profile_check['email'] ?? null;
                                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $subject = 'Raffle slot selected — JKUAT Housing';
                                    $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p><p>We will email you the results once the draw completes.</p>';
                                    if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicant_id, $email, $subject, $bodyHtml, 'Raffle Slot Selected');
                                }
                    } else {
                        if ($legacyRow && strtolower((string)($legacyRow['status'] ?? '')) === 'available') {
                            $cleanupStmt = $conn->prepare("UPDATE raffle_slots SET applicant_id = NULL WHERE draw_id = ? AND applicant_id = ? AND status = 'available'");
                            if ($cleanupStmt) {
                                $cleanupStmt->bind_param('ss', $draw_id, $applicant_id);
                                $cleanupStmt->execute();
                            }
                        }

                        // Update slot as picked (works for both placeholder slots and legacy pre-created rows)
                        $updateSlot = $conn->prepare("UPDATE raffle_slots SET applicant_id = ?, status = 'picked', picked_at = NOW() WHERE draw_id = ? AND slot_number = ? AND status = 'available' LIMIT 1");
                        if ($updateSlot) {
                            $updateSlot->bind_param('ssi', $applicant_id, $draw_id, $slot_number);
                            if ($updateSlot->execute() && $updateSlot->affected_rows > 0) {
                                // Confirm the actually picked slot for this applicant (server-authoritative)
                                $pickedSlotNum = (int)$slot_number;
                                $pickedStmt = $conn->prepare("SELECT slot_number FROM raffle_slots WHERE draw_id = ? AND applicant_id = ? AND status = 'picked' LIMIT 1");
                                if ($pickedStmt) {
                                    $pickedStmt->bind_param('ss', $draw_id, $applicant_id);
                                    $pickedStmt->execute();
                                    $pickedRow = $pickedStmt->get_result()->fetch_assoc();
                                    if ($pickedRow && isset($pickedRow['slot_number'])) {
                                        $pickedSlotNum = (int)$pickedRow['slot_number'];
                                    }
                                }

                                // Update application status to Pending
                                $appStmt = $conn->prepare("SELECT application_id FROM applications WHERE applicant_id = ? AND category = ? LIMIT 1");
                                if ($appStmt) {
                                    $appStmt->bind_param('ss', $applicant_id, $category);
                                    $appStmt->execute();
                                    $appRes = $appStmt->get_result()->fetch_assoc();
                                    if ($appRes) {
                                        $updateApp = $conn->prepare("UPDATE applications SET status = 'Pending', house_no = ? WHERE application_id = ?");
                                        if ($updateApp) {
                                            // Store the actual house number being raffled (not the slot number).
                                            // Slot selection is already captured in balloting.ballot_no (e.g., "Slot 3").
                                            $houseNoToStore = $draw_house_no;
                                            if ($houseNoToStore === null || trim((string)$houseNoToStore) === '') {
                                                // Fallback: at least persist the draw house identifier (shouldn't happen in normal flows).
                                                $houseNoToStore = (string)$draw_house_id;
                                            }
                                            $updateApp->bind_param('ss', $houseNoToStore, $appRes['application_id']);
                                            $updateApp->execute();
                                        }
                                    }
                                }

                                // Create balloting record for audit trail
                                $ballot_id = generateBallotId($conn);
                                $ballot_no = 'Slot ' . (string)$pickedSlotNum;
                                $auditHouseId = $draw_house_id;
                                $ballotInsert = $conn->prepare("INSERT INTO balloting (ballot_id, applicant_id, house_id, ballot_no, date_of_ballot, status, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                if ($ballotInsert) {
                                    $status_val = "Pending";
                                    $ballotInsert->bind_param('sssssss', $ballot_id, $applicant_id, $auditHouseId, $ballot_no, $date_of_ballot, $status_val, $category);
                                    $ballotInsert->execute();
                                }

                                $ballot_success = "🎰 Slot $pickedSlotNum selected! Waiting for raffle draw results.";
                                // Notify applicant about picked slot
                                $dateSent = date('Y-m-d H:i:s');
                                $notificationId = uniqid('NT');
                                $msg = "You selected Slot {$pickedSlotNum} for category {$category}. Waiting for raffle results.";
                                if (function_exists('notify_insert_safe')) {
                                    notify_insert_safe($conn, $notificationId, $applicant_id, 'applicant', $applicant_id, $msg, $dateSent, 'unread', 'Raffle Slot Selected');
                                }
                                $email = $profile_check['email'] ?? null;
                                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $subject = 'Raffle slot selected — JKUAT Housing';
                                    $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p><p>We will email you the results once the draw completes.</p>';
                                    if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicant_id, $email, $subject, $bodyHtml, 'Raffle Slot Selected');
                                }
                            } else {
                                $ballot_error = 'This slot was already picked. Please select another.';
                            }
                        } else {
                            $ballot_error = 'Database error. Please try again.';
                        }
                    }
                }
            }
        } elseif (empty($house_id)) {
            $ballot_error = 'Please select a house to ballot for.';
        } else {
            // Check if house exists and is vacant
            $houseStmt = $conn->prepare("SELECT house_id, house_no, category, status FROM houses WHERE house_id = ? LIMIT 1");
            $houseStmt->bind_param('s', $house_id);
            $houseStmt->execute();
            $houseRes = $houseStmt->get_result()->fetch_assoc();

            if (!$houseRes) {
                $ballot_error = 'Selected house not found.';
            } elseif (strtolower($houseRes['status']) !== 'vacant') {
                $ballot_error = 'Selected house is no longer vacant.';
            } elseif (strtolower($houseRes['category']) !== strtolower($category)) {
                $ballot_error = 'House category does not match your application.';
            } else {
                // Check if applicant already balloted for this category
                $ballotChkStmt = $conn->prepare("SELECT 1 FROM balloting WHERE applicant_id = ? AND category = ? LIMIT 1");
                $ballotChkStmt->bind_param('ss', $applicant_id, $category);
                $ballotChkStmt->execute();
                if ($ballotChkStmt->get_result()->fetch_assoc()) {
                    $ballot_error = 'You have already submitted a ballot for this category.';
                } else {
                    // Check if house already taken
                    $houseTakenStmt = $conn->prepare("SELECT 1 FROM balloting WHERE house_id = ? LIMIT 1");
                    $houseTakenStmt->bind_param('s', $house_id);
                    $houseTakenStmt->execute();
                    if ($houseTakenStmt->get_result()->fetch_assoc()) {
                        $ballot_error = 'This house has already been chosen.';
                    } else {
                        // Submit ballot
                        $ballot_no = generateBallotNo($conn);
                        $stmt = $conn->prepare("INSERT INTO balloting (ballot_id, applicant_id, house_id, ballot_no, date_of_ballot, status, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("sssssss", $ballot_id, $applicant_id, $house_id, $ballot_no, $date_of_ballot, $status, $category);
                            if ($stmt->execute()) {
                                // Update application status - preserve APPROVED, change APPLIED to PENDING
                                $appStmt = $conn->prepare("SELECT application_id, status FROM applications WHERE applicant_id = ? AND category = ? LIMIT 1");
                                if ($appStmt) {
                                    $appStmt->bind_param('ss', $applicant_id, $category);
                                    $appStmt->execute();
                                    $appRes = $appStmt->get_result()->fetch_assoc();
                                    if ($appRes && strtolower(trim($appRes['status'])) !== 'approved') {
                                        $updateStmt = $conn->prepare("UPDATE applications SET status = 'Pending', house_no = ? WHERE application_id = ?");
                                        if ($updateStmt) {
                                            $updateStmt->bind_param('ss', $houseRes['house_no'], $appRes['application_id']);
                                            $updateStmt->execute();
                                        }
                                    }
                                }
                                $ballot_success = "Ballot submitted successfully for house {$houseRes['house_no']}!";
                                // Notify applicant about ballot submission
                                $dateSent = date('Y-m-d H:i:s');
                                $notificationId = uniqid('NT');
                                $msg = "Your ballot for house {$houseRes['house_no']} in category {$category} has been submitted.";
                                if (function_exists('notify_insert_safe')) {
                                    notify_insert_safe($conn, $notificationId, $applicant_id, 'applicant', $applicant_id, $msg, $dateSent, 'unread', 'Ballot Submitted');
                                }
                                $email = $profile_check['email'] ?? null;
                                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $subject = 'Ballot submitted — JKUAT Housing';
                                    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                                    $ballotLink = $appUrl . '/php/ballot.php';
                                    $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p><p><a href="' . htmlspecialchars($ballotLink) . '">View Ballot</a></p>';
                                    if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicant_id, $email, $subject, $bodyHtml, 'Ballot Submitted');
                                }
                            } else {
                                $ballot_error = "Error submitting ballot. Please try again.";
                            }
                        } else {
                            $ballot_error = "Database error. Please try again.";
                        }
                    }
                }
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
        }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <h2><strong>Applicant Portal</strong></h2>
    <a href="applicants.php" class="<?= $current === 'applicants.php' ? 'active' : '' ?>">Apply</a>
    <a href="ballot.php" class="<?= $current === 'ballot.php' ? 'active' : '' ?>">Balloting</a>
    <a href="notifications.php" class="<?= $current === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
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
            <div class="alert alert-success">Ballot is OPEN. Closing date: <?= htmlspecialchars(date('F j, Y', strtotime($ballot_closing))) ?></div>
        <?php else: ?>
            <div class="alert alert-error">Ballot is currently closed. You cannot submit a ballot right now.</div>
        <?php endif; ?>

        <label>Date of Ballot</label>
        <input type="date" name="date_of_ballot" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>

        <label>Select House Category</label>
        <select id="categorySelect" name="category" onchange="filterHousesOrRaffle()" required>
            <option value="">-- Select Category --</option>
            <?php
                // Only show categories the applicant has applied for (Applied, Pending, or Approved status)
                $appliedCats = $conn->prepare("SELECT DISTINCT category FROM applications WHERE applicant_id = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved') ORDER BY category");
                if ($appliedCats) {
                    $appliedCats->bind_param('s', $applicant_id);
                    $appliedCats->execute();
                    $catRes = $appliedCats->get_result();
                    while ($catRow = $catRes->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($catRow['category']) . '">' . htmlspecialchars($catRow['category']) . '</option>';
                    }
                }
            ?>
        </select>

        <!-- Container for raffle or direct ballot section -->
        <div id="ballotTypeContainer">
            <!-- This will be populated by JavaScript based on category selection -->
        </div>

        <button type="submit" name="submit_ballot" id="submitBtn" <?= $ballot_open ? '' : 'disabled' ?>>Submit Ballot</button>
    </form>

    <!-- Hidden data for raffle draws -->
    <span id="raffleDataJson" style="display:none;"><?php 
        // Auto-detect raffle eligibility per category based on competing applicants.
        // If 2+ applicants exist in a category, show RAFFLE MODE with N slots.
        $allRaffles = [];
        $categories = ['1 Bedroom', '2 Bedroom', '3 Bedroom', '4 Bedroom'];

        foreach ($categories as $cat) {
            try {
                $competingCnt = countCompetingApplicants($conn, $cat);

                // Try to attach open draw metadata when present (optional)
                $draw_id = null;
                $house_id = null;
                $drawSlots = 0;
                $dStmt = $conn->prepare("SELECT draw_id, house_id, total_slots FROM raffle_draws WHERE category = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1");
                if ($dStmt) {
                    $dStmt->bind_param('s', $cat);
                    $dStmt->execute();
                    $d = $dStmt->get_result()->fetch_assoc();
                    if ($d && !empty($d['draw_id'])) {
                        $draw_id = $d['draw_id'];
                        $house_id = $d['house_id'] ?? null;
                        $drawSlots = intval($d['total_slots'] ?? 0);
                    }
                }

                if ($competingCnt >= 2) {
                    $effectiveSlots = max(2, $competingCnt, $drawSlots);
                    $allRaffles[$cat] = [
                        'draw_id' => $draw_id,
                        'house_id' => $house_id,
                        'total_slots' => $effectiveSlots,
                        'mode' => $draw_id ? 'raffle_open' : 'raffle_autodetect'
                    ];
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        echo htmlspecialchars(json_encode($allRaffles));
    ?></span>

    <h2>My Ballots</h2>
    <table>
        <thead>
        <tr>
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
            $displayBallotNo = $ballot['ballot_no'];
            if (preg_match('/-S(\d+)$/', (string)$displayBallotNo, $m)) {
                // Backward compatibility for older stored format
                $displayBallotNo = 'Slot ' . $m[1];
            }
            $displayStatus = trim((string)($ballot['status'] ?? ''));
            if (strtolower($displayStatus) === 'open') {
                $displayStatus = 'Pending';
            }
            echo "<tr>
                <td>{$displayBallotNo}</td>
                <td>{$ballot['house_id']}</td>
                <td>{$ballot['date_of_ballot']}</td>
                <td>{$displayStatus}</td>
            </tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<script>
// Load raffle data from hidden field
const allRaffles = JSON.parse(document.getElementById('raffleDataJson').textContent || '{}');

// Fetch all vacant houses and display based on selection
function filterHousesOrRaffle() {
    const selectedCategory = document.getElementById('categorySelect').value;
    const container = document.getElementById('ballotTypeContainer');
    
    if (!selectedCategory) {
        container.innerHTML = '<p style="color: #999;">Please select a category first.</p>';
        document.getElementById('submitBtn').disabled = true;
        return;
    }
    
    // Check if raffle exists for this category
    if (allRaffles[selectedCategory]) {
        showRaffleInterface(selectedCategory, allRaffles[selectedCategory]);
    } else {
        showDirectBallotInterface(selectedCategory);
    }
}

function showRaffleInterface(category, raffleData) {
    const container = document.getElementById('ballotTypeContainer');
    const submitBtn = document.getElementById('submitBtn');
    let slotsHTML = `
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffc107;">
            <strong style="color: #856404;">🎰 RAFFLE MODE - Pick Your Slot</strong>
            <p style="margin: 5px 0; font-size: 14px;">${raffleData.total_slots} applicants are competing for this category. Pick a number (slot) from available options.</p>
        </div>
        <label>Available Slots (1 to ${raffleData.total_slots})</label>
        <div class="grid" id="slotGrid">
    `;
    
    for (let i = 1; i <= raffleData.total_slots; i++) {
        slotsHTML += `
            <div class="house-card" onclick="selectSlot(this)" data-slot="${i}" style="cursor: pointer;">
                <strong>Slot ${i}</strong>
            </div>
        `;
    }
    
    slotsHTML += `
        </div>
        <input type="hidden" name="is_raffle" id="isRaffle" value="1">
        <input type="hidden" name="raffle_applicant_count" id="raffleApplicantCount" value="${raffleData.total_slots}">
        <input type="hidden" name="slot_number" id="selectedSlot">
    `;
    
    container.innerHTML = slotsHTML;
    // Require explicit selection before submit
    submitBtn.disabled = true;
}

function showDirectBallotInterface(category) {
    const container = document.getElementById('ballotTypeContainer');
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    
    // Fetch vacant houses for this category
    fetch('get_vacant_houses.php?category=' + encodeURIComponent(category))
        .then(response => response.json())
        .then(houses => {
            if (!houses || houses.length === 0) {
                container.innerHTML = '<p style="color: #666;">No vacant houses available in this category.</p>';
                document.getElementById('submitBtn').disabled = true;
                return;
            }
            
            let housesHTML = `
                <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #bee5eb;">
                    <strong style="color: #0c5460;">DIRECT BALLOT - Select a House</strong>
                    <p style="margin: 5px 0; font-size: 14px;">You are the only applicant or you have priority for this category. Select a house directly.</p>
                </div>
                <label>Select a Vacant House</label>
                <div class="grid" id="houseGrid">
            `;
            
            houses.forEach(house => {
                housesHTML += `
                    <div class="house-card" onclick="selectHouse(this)" data-id="${house.house_id}" data-cat="${house.category}" style="cursor: pointer;">
                        <strong>${house.house_no}</strong><br><small>(${house.category})</small>
                    </div>
                `;
            });
            
            housesHTML += `
                </div>
                <input type="hidden" name="house_id" id="selectedHouse">
            `;
            
            container.innerHTML = housesHTML;
            // User must pick a house first
            submitBtn.disabled = true;
        })
        .catch(error => {
            console.error('Error fetching houses:', error);
            container.innerHTML = '<p style="color: #c33;">Error loading houses. Please try again.</p>';
        });
}

function selectSlot(card) {
    document.querySelectorAll('#slotGrid .house-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('selectedSlot').value = card.dataset.slot;
    document.getElementById('submitBtn').disabled = false;
}

function selectHouse(card) {
    document.querySelectorAll('#houseGrid .house-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('selectedHouse').value = card.dataset.id;
    document.getElementById('submitBtn').disabled = false;
}

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

// Poll for ballot state changes and update the top alert accordingly
(function(){
    let last = <?= $ballot_open ? '1' : '0' ?>;
    function updateBallotUI(isOpen, closing) {
        const formAlert = document.querySelector('form .alert');
        const mainContainer = document.querySelector('.main-content') || document.body;
        const submitBtn = document.getElementById('submitBtn');

        const closingText = closing ? closing : '';

        if (isOpen) {
            // replace or add success alert inside the form area
            if (formAlert) {
                formAlert.className = 'alert alert-success';
                formAlert.textContent = 'Ballot is OPEN. Closing date: ' + closingText;
            } else {
                const d = document.createElement('div');
                d.id = 'ballotAlert';
                d.className = 'alert alert-success';
                d.textContent = 'Ballot is OPEN. Closing date: ' + closingText;
                if (mainContainer) mainContainer.insertBefore(d, mainContainer.firstChild);
            }
            if (submitBtn) submitBtn.disabled = false;
        } else {
            // show closed state
            if (formAlert) {
                formAlert.className = 'alert alert-error';
                formAlert.textContent = 'Ballot is currently closed. You cannot submit a ballot right now.';
            } else {
                // ensure a closed alert exists
                const d = document.createElement('div');
                d.className = 'alert alert-error';
                d.textContent = 'Ballot is currently closed. You cannot submit a ballot right now.';
                if (mainContainer) mainContainer.insertBefore(d, mainContainer.firstChild);
            }
            if (submitBtn) submitBtn.disabled = true;
        }
    }

    async function poll(){
        try {
            const r = await fetch('ballot_state.php', { cache: 'no-store' });
            const j = await r.json();
            if (j && j.success) {
                const isOpen = j.is_open ? 1 : 0;
                if (isOpen !== last) {
                    updateBallotUI(j.is_open, j.end_date ? (new Date(j.end_date)).toLocaleDateString() : '');
                    last = isOpen;
                }
            }
        } catch(e){ console.error('poll error', e); }
    }

    // poll periodically and also respond to storage events for cross-tab updates
    setInterval(poll, 3000);
    window.addEventListener('storage', function(e){ if (e.key === 'ballot_state_updated') poll(); });
})();
</script>
</body>
</html>
