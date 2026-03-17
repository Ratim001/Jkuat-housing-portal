<?php
session_start();
include '../includes/db.php';

// Check if user is admin (you may need to adjust based on your auth system)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$admin_error = '';
$admin_success = '';

// Handle creating a raffle draw
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_raffle'])) {
    $category = trim($_POST['category']);
    $house_id = trim($_POST['house_id']);
    $created_by = $_SESSION['user_id'];
    
    $category_err = '';
    $house_err = '';
    
    if (empty($category)) {
        $category_err = 'Category is required';
    }
    if (empty($house_id)) {
        $house_err = 'House is required';
    }
    
    if (empty($category_err) && empty($house_err)) {
        // Check if house exists and is vacant
        $houseCheck = $conn->prepare("SELECT house_no FROM houses WHERE house_id = ? AND status = 'Vacant' LIMIT 1");
        $houseCheck->bind_param('s', $house_id);
        $houseCheck->execute();
        if (!$houseCheck->get_result()->fetch_assoc()) {
            $admin_error = 'Selected house not found or not vacant.';
        } else {
            // Count DISTINCT applicants for this category (all active applications).
            // Treat NULL/blank status as active (pending) to avoid missing newly-created rows.
            $countStmt = $conn->prepare("SELECT COUNT(DISTINCT applicant_id) as cnt FROM applications WHERE category = ? AND COALESCE(LOWER(REPLACE(NULLIF(TRIM(status),''), '_', ' ')), 'pending') NOT IN ('rejected','cancelled','unsuccessful','not successful')");
            $countStmt->bind_param('s', $category);
            $countStmt->execute();
            $cnt = $countStmt->get_result()->fetch_assoc()['cnt'];
            
            if ($cnt < 2) {
                $admin_error = 'At least 2 applicants required for raffle. Current count: ' . $cnt;
            } else {
                // Create raffle draw
                $draw_id = 'DRAW' . uniqid();
                $stmt = $conn->prepare("INSERT INTO raffle_draws (draw_id, house_id, category, total_slots, status, created_by) VALUES (?, ?, ?, ?, 'open', ?)");
                if ($stmt) {
                    $stmt->bind_param('sssss', $draw_id, $house_id, $category, $cnt, $created_by);
                    if ($stmt->execute()) {
                        // Create placeholder slots (no applicant assigned yet). Applicants pick a slot later.
                        for ($slotNum = 1; $slotNum <= intval($cnt); $slotNum++) {
                            $slotId = 'SLOT' . uniqid() . $slotNum;
                            $slotInsert = $conn->prepare("INSERT INTO raffle_slots (slot_id, draw_id, applicant_id, slot_number, status) VALUES (?, ?, NULL, ?, 'available')");
                            if ($slotInsert) {
                                $slotInsert->bind_param('ssi', $slotId, $draw_id, $slotNum);
                                $slotInsert->execute();
                            }
                        }
                        
                        $admin_success = "Raffle draw created successfully! Draw ID: $draw_id with $cnt slots.";
                    } else {
                        $admin_error = 'Error creating raffle draw.';
                    }
                }
            }
        }
    } else {
        $admin_error = 'Please fill in all fields.';
    }
}

// Handle drawing a winner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_winner'])) {
    $draw_id = trim($_POST['draw_id']);
    $num_winners = intval($_POST['num_winners'] ?? 1);
    $num_winners = max(1, min($num_winners, 20)); // Limit to 20 max
    
    // Check draw exists and is open
    $drawCheck = $conn->prepare("SELECT draw_id, house_id, category FROM raffle_draws WHERE draw_id = ? AND status = 'open' LIMIT 1");
    $drawCheck->bind_param('s', $draw_id);
    $drawCheck->execute();
    $drawData = $drawCheck->get_result()->fetch_assoc();
    
    if (!$drawData) {
        $admin_error = 'Draw not found or already completed.';
    } else {
        // Get all picked slots
        $pickedSlots = [];
        $slotsStmt = $conn->prepare("SELECT slot_id, slot_number, applicant_id FROM raffle_slots WHERE draw_id = ? AND status = 'picked'");
        $slotsStmt->bind_param('s', $draw_id);
        $slotsStmt->execute();
        $slotsRes = $slotsStmt->get_result();
        
        while ($slot = $slotsRes->fetch_assoc()) {
            $pickedSlots[] = $slot;
        }
        
        if (empty($pickedSlots)) {
            $admin_error = 'No applicants have picked slots yet.';
        } else if (count($pickedSlots) < $num_winners) {
            $admin_error = 'Not enough applicants have picked slots. Picked: ' . count($pickedSlots) . ', Needed: ' . $num_winners;
        } else {
            // Randomly select multiple winners
            $winnerSlots = array_rand($pickedSlots, $num_winners);
            // array_rand returns single key if N=1, array if N>1. Make it always an array
            if ($num_winners === 1) {
                $winnerSlots = [$winnerSlots];
            }
            
            $winning_applicants = [];
            foreach ($winnerSlots as $idx) {
                $winning_applicants[] = $pickedSlots[$idx]['applicant_id'];
            }
            
            // Update raffle draw - record first winner (or comma-separated list)
            $winning_list = implode(',', $winning_applicants);
            $updateDraw = $conn->prepare("UPDATE raffle_draws SET status = 'completed', winning_applicant_id = ?, draw_date = NOW() WHERE draw_id = ?");
            $updateDraw->bind_param('ss', $winning_list, $draw_id);
            
            if ($updateDraw->execute()) {
                // Mark all slots as winner or loser
                foreach ($pickedSlots as $slot) {
                    $slotStatus = in_array($slot['applicant_id'], $winning_applicants) ? 'winner' : 'loser';
                    $updateSlot = $conn->prepare("UPDATE raffle_slots SET status = ? WHERE slot_id = ?");
                    $updateSlot->bind_param('ss', $slotStatus, $slot['slot_id']);
                    $updateSlot->execute();
                }
                
                // Update application statuses and create tenant records for winners
                $adminId = $_SESSION['user_id'] ?? 'user002';
                $dateSent = date('Y-m-d H:i:s');
                // Resolve house_no for this draw's house_id (if available)
                $houseNo = null;
                if (!empty($drawData['house_id'])) {
                    $hStmt = $conn->prepare("SELECT house_no FROM houses WHERE house_id = ? LIMIT 1");
                    if ($hStmt) {
                        $hStmt->bind_param('s', $drawData['house_id']);
                        $hStmt->execute();
                        $hRow = $hStmt->get_result()->fetch_assoc();
                        $houseNo = $hRow['house_no'] ?? null;
                    }
                }

                foreach ($winning_applicants as $winner_id) {
                    $updateWinner = $conn->prepare("UPDATE applications SET status = 'won' WHERE applicant_id = ? AND category = ? LIMIT 1");
                    $updateWinner->bind_param('ss', $winner_id, $drawData['category']);
                    $updateWinner->execute();

                    // Create tenant record if we have a house_no
                    if ($houseNo) {
                        $lastTenant = mysqli_query($conn, "SELECT tenant_id FROM tenants ORDER BY tenant_id DESC LIMIT 1");
                        $nextId = 'T001';
                        if ($t = mysqli_fetch_assoc($lastTenant)) {
                            $num = (int)substr($t['tenant_id'], 1) + 1;
                            $nextId = 'T' . str_pad($num, 3, '0', STR_PAD_LEFT);
                        }
                        $today = date('Y-m-d');
                        $ins = $conn->prepare("INSERT INTO tenants (tenant_id, applicant_id, house_no, move_in_date) VALUES (?, ?, ?, ?)");
                        if ($ins) {
                            $ins->bind_param('ssss', $nextId, $winner_id, $houseNo, $today);
                            $ins->execute();
                        }
                        $conn->query("UPDATE houses SET status = 'Occupied' WHERE house_no = '" . $conn->real_escape_string($houseNo) . "'");
                    }

                    // Mark winner as tenant (set status and role)
                    $conn->query("UPDATE applicants SET status = 'Tenant', role = 'tenant' WHERE applicant_id = '" . $conn->real_escape_string($winner_id) . "'");
                    @mysqli_query($conn, "UPDATE balloting SET status = 'won' WHERE applicant_id = '" . $conn->real_escape_string($winner_id) . "'");

                    // Notify winner and optionally email
                    $resName = $conn->prepare("SELECT name, email FROM applicants WHERE applicant_id = ?");
                    $winnerName = 'Applicant';
                    $winnerEmail = null;
                    if ($resName) {
                        $resName->bind_param('s', $winner_id);
                        $resName->execute();
                        $rN = $resName->get_result()->fetch_assoc();
                        $winnerName = $rN['name'] ?? 'Applicant';
                        $winnerEmail = $rN['email'] ?? null;
                    }
                    $notificationId = uniqid('NT');
                    $title = 'Raffle';
                    $message = "Dear \"{$winnerName}\": Congratulations, you have been allocated house " . ($houseNo ?: '') . ".";
                    insert_notification_safe($conn, $notificationId, $adminId, 'applicant', $winner_id, $message, $dateSent, 'unread', $title);
                    if ($winnerEmail) {
                        try {
                            require_once __DIR__ . '/../includes/helpers.php';
                            require_once __DIR__ . '/../includes/email.php';
                            $htmlBody = build_email_wrapper('<p>' . htmlspecialchars($message) . '</p>');
                            if (function_exists('notify_and_email')) {
                                notify_and_email($conn, 'applicant', $winner_id, $winnerEmail, 'Congratulations — Raffle Win', $htmlBody, 'Raffle Win');
                            } else {
                                send_email($winnerEmail, 'Congratulations — Raffle Win', $htmlBody, true);
                            }
                        } catch (Exception $e) {
                            error_log('Failed sending raffle winner email to ' . $winnerEmail . ': ' . $e->getMessage());
                        }
                    }
                }
                
                // Losers: mark as 'unsuccessful'
                foreach ($pickedSlots as $slot) {
                    if (!in_array($slot['applicant_id'], $winning_applicants)) {
                        $updateLoser = $conn->prepare("UPDATE applications SET status = 'unsuccessful' WHERE applicant_id = ? AND category = ? LIMIT 1");
                        $updateLoser->bind_param('ss', $slot['applicant_id'], $drawData['category']);
                        $updateLoser->execute();
                    }
                }
                
                $admin_success = "Draw completed! Selected $num_winners winner" . ($num_winners > 1 ? 's' : '') . ": " . implode(', ', $winning_applicants);
            } else {
                $admin_error = 'Error completing draw.';
            }
        }
    }
}

// Fetch all raffle draws
$raffles = [];
try {
    $result = $conn->query("SELECT d.*, COUNT(s.slot_id) as total_picked FROM raffle_draws d LEFT JOIN raffle_slots s ON d.draw_id = s.draw_id AND s.status = 'picked' GROUP BY d.draw_id ORDER BY d.created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $raffles[] = $row;
    }
} catch (Exception $e) {}

$current = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Raffle Draw Management | JKUAT Housing</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { font-size: 24px; color: #004225; margin-bottom: 20px; font-weight: bold; }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 4px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #006400; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #004225; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #006400; color: white; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .action-btn { background: #0066cc; padding: 6px 12px; font-size: 12px; }
        .action-btn:hover { background: #0052a3; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">🎰 Raffle Draw Management</div>
    
    <?php if ($admin_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($admin_error) ?></div>
    <?php endif; ?>
    
    <?php if ($admin_success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($admin_success) ?></div>
    <?php endif; ?>
    
    <div class="section">
        <h3>Create New Raffle Draw</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr 200px; gap: 15px;">
                <div class="form-group" style="grid-column: 1;">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="">-- Select Category --</option>
                        <option value="1 Bedroom">1 Bedroom</option>
                        <option value="2 Bedroom">2 Bedroom</option>
                        <option value="3 Bedroom">3 Bedroom</option>
                        <option value="4 Bedroom">4 Bedroom</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 2;">
                    <label>House</label>
                    <select name="house_id" required>
                        <option value="">-- Select House --</option>
                        <?php
                        $houseQuery = $conn->query("SELECT house_id, house_no, category FROM houses WHERE status = 'Vacant' ORDER BY category, house_no");
                        while ($house = $houseQuery->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($house['house_id']) . "'>" . htmlspecialchars($house['house_no']) . " (" . htmlspecialchars($house['category']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 3; display: flex; align-items: flex-end;">
                    <button type="submit" name="create_raffle">Create Draw</button>
                </div>
            </div>
        </form>
    </div>
    
    <div class="section">
        <h3>Active Raffle Draws</h3>
        <?php if (empty($raffles)): ?>
            <p style="color: #999;">No raffle draws found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Draw ID</th>
                        <th>House No</th>
                        <th>Category</th>
                        <th>Total Slots</th>
                        <th>Slots Picked</th>
                        <th>Status</th>
                        <th>Winner (Slot)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($raffles as $raffle): ?>
                        <tr>
                            <td><?= htmlspecialchars($raffle['draw_id']) ?></td>
                            <td><?= htmlspecialchars($raffle['house_id']) ?></td>
                            <td><?= htmlspecialchars($raffle['category']) ?></td>
                            <td><?= htmlspecialchars($raffle['total_slots']) ?></td>
                            <td><?= htmlspecialchars($raffle['total_picked'] ?? 0) ?></td>
                            <td><?= ucfirst(htmlspecialchars($raffle['status'])) ?></td>
                            <td><?= !empty($raffle['winning_slot']) ? 'Slot ' . htmlspecialchars($raffle['winning_slot']) : '-' ?></td>
                            <td>
                                <?php if ($raffle['status'] === 'open'): ?>
                                    <form method="POST" style="display: inline; display: flex; gap: 5px; align-items: center;">
                                        <input type="hidden" name="draw_id" value="<?= htmlspecialchars($raffle['draw_id']) ?>">
                                        <input type="number" name="num_winners" value="1" min="1" max="<?= htmlspecialchars($raffle['total_picked'] ?? 1) ?>" style="width: 50px; padding: 4px;" title="Number of winners to select">
                                        <button type="submit" name="draw_winner" class="action-btn" style="margin: 0;">Draw</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999;">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
