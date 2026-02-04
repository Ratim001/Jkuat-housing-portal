<?php 
include '../includes/db.php';
session_start();

// Create/update the bill_update_logs table
$create_table = "CREATE TABLE IF NOT EXISTS bill_update_logs (
    bill_update_id VARCHAR(100) PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    bill_id VARCHAR(100) NOT NULL,
    device_type VARCHAR(255),
    details TEXT NOT NULL,
    old_amount DECIMAL(10, 2) NOT NULL,
    new_amount DECIMAL(10, 2) NOT NULL,
    date_updated DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table)) {
    die("Error creating bill_update_logs table: " . $conn->error);
}

// Function to generate next bill_update_id
function getNextBillUpdateId($conn) {
    $result = $conn->query("SELECT bill_update_id FROM bill_update_logs ORDER BY bill_update_id DESC LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $last_id = $row['bill_update_id'];
        if (preg_match('/BU(\d+)/', $last_id, $matches)) {
            $num = intval($matches[1]) + 1;
            return 'BU' . str_pad($num, 3, '0', STR_PAD_LEFT);
        }
    }
    return 'BU001';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle status update
    if (isset($_POST['bill_id'], $_POST['new_status'])) {
        $bill_id = $_POST['bill_id'];
        $new_status = $_POST['new_status'];

        if ($new_status === 'paid') {
            // Use custom date if provided, otherwise current date
            $date_settled = isset($_POST['date_settled']) && !empty($_POST['date_settled']) 
                ? $_POST['date_settled'] 
                : date('Y-m-d');
            
            $stmt = $conn->prepare("UPDATE bills SET status = ?, date_settled = ? WHERE bill_id = ?");
            $stmt->bind_param("sss", $new_status, $date_settled, $bill_id);
        } else {
            $stmt = $conn->prepare("UPDATE bills SET status = ?, date_settled = NULL WHERE bill_id = ?");
            $stmt->bind_param("ss", $new_status, $bill_id);
        }

        if ($stmt->execute()) {
            echo 'status_updated';
        } else {
            echo 'status_update_failed';
        }
        exit;
    }

    // Handle amount update with old amount tracking
    if (isset($_POST['edit_bill_id'], $_POST['new_amount'])) {
        $edit_bill_id = $_POST['edit_bill_id'];
        $new_amount = $_POST['new_amount'];

        // Get bill info
        $query = $conn->prepare("SELECT amount, type_of_bill FROM bills WHERE bill_id = ?");
        $query->bind_param("s", $edit_bill_id);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $old_amount = $row['amount'];
            $bill_type = $row['type_of_bill'];

            $conn->begin_transaction();
            try {
                // Update bill amount
                $update_stmt = $conn->prepare("UPDATE bills SET amount = ? WHERE bill_id = ?");
                $update_stmt->bind_param("ds", $new_amount, $edit_bill_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update bill amount: " . $update_stmt->error);
                }

                // Get valid user_id
                $user_id = null;
                if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                    // Verify user exists
                    $user_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
                    $user_check->bind_param("s", $_SESSION['user_id']);
                    $user_check->execute();
                    if ($user_check->get_result()->num_rows > 0) {
                        $user_id = $_SESSION['user_id'];
                    }
                }
                
                // Fallback to admin user if needed
                if (empty($user_id)) {
                    $admin_query = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
                    if ($admin_query && $admin_row = $admin_query->fetch_assoc()) {
                        $user_id = $admin_row['user_id'];
                    } else {
                        throw new Exception("No valid user ID available for logging");
                    }
                }

                // Prepare detailed log entry
                $bill_update_id = getNextBillUpdateId($conn);
                $device_type = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $username = $_SESSION['username'] ?? 'system';
                
                $details = "[{$bill_type}] Amount updated by {$username} | Changed from KES {$old_amount} to KES {$new_amount} | " . 
                           date('Y-m-d H:i:s');

                // Insert into log
                $log_stmt = $conn->prepare("INSERT INTO bill_update_logs 
                    (bill_update_id, user_id, bill_id, device_type, details, old_amount, new_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $log_stmt->bind_param("sssssdd", 
                    $bill_update_id, 
                    $user_id, 
                    $edit_bill_id, 
                    $device_type, 
                    $details, 
                    $old_amount,
                    $new_amount);

                if (!$log_stmt->execute()) {
                    throw new Exception("Log insertion failed: " . $log_stmt->error);
                }

                $conn->commit();
                echo 'success';
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Transaction error: " . $e->getMessage());
                echo 'fail: ' . $e->getMessage();
            }
        } else {
            echo 'bill_not_found';
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - BILLS | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        .filter-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
        }
        th {
            background-color: #006400;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: white;
            min-height: 100vh;
        }
        .main-content h2 {
            color: #005826;
        }
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f1f1;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }
        .top-header h1 {
            margin: 0;
            font-size: 24px;
            color: #006400;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            cursor: pointer;
        }
        .sidebar ul li a.active {
            background-color: #ffffff;
            color: #005826;
            font-weight: bold;
        }
        select.status-dropdown {
            padding: 5px;
        }
        button.edit-btn {
            padding: 5px 10px;
            margin-left: 10px;
            background-color: #005826;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        #modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .modal-content h3 {
            margin-top: 0;
            color: #005826;
        }
        .modal-content label {
            display: block;
            margin-top: 10px;
        }
        .modal-content input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .modal-content button {
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #005826;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .date-settled {
            color: #006400;
            font-weight: bold;
        }
        /* New status badge styles */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            margin-right: 8px;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-disputed {
            background-color: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>BILLS</p>
    <nav>
        <ul>
            <li><a href="csdashboard.php">Dashboard</a></li>
            <li><a href="houses.php">Houses</a></li>
            <li><a href="tenants.php">Tenants</a></li>
            <li><a href="service_requests.php">Service Requests</a></li>
            <li><a href="manage_applicants.php">Manage Applicants</a></li>
            <li><a href="notices.php">Notices</a></li>
            <li><a href="bills.php">Bills</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
    </nav>
</div>
<div class="main-content">
    <div class="top-header">
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="Profile Icon" class="user-icon" onclick="alert('Profile menu coming soon.')">
    </div>
    <h2>Bills Overview</h2>
    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search...">
        <select id="statusFilter">
            <option value="">All Statuses</option>
            <option value="paid">Paid</option>
            <option value="not paid">Not Paid</option>
        </select>
    </div>
    <table id="billsTable">
        <thead>
        <tr>
            <th>Bill ID</th>
            <th>Service ID</th>
            <th>Type of Bill</th>
            <th>Amount</th>
            <th>Date Billed</th>
            <th>Date Settled</th>
            <th>Payment Status</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $query = "SELECT * FROM bills ORDER BY date_billed DESC";
        $result = mysqli_query($conn, $query);

        while ($row = mysqli_fetch_assoc($result)) {
            $status = strtolower(trim($row['status']));
            $statuses = isset($row['statuses']) ? strtolower($row['statuses']) : 'active';
            $statusesClass = $statuses == 'active' ? 'status-active' : 'status-disputed';
            
            echo "<tr data-bill-id='{$row['bill_id']}'>";
            echo "<td>{$row['bill_id']}</td>";
            echo "<td>{$row['service_id']}</td>";
            echo "<td>{$row['type_of_bill']}</td>";
            echo "<td><span class='amount-text'>KES {$row['amount']}</span></td>";
            echo "<td>{$row['date_billed']}</td>";
            echo "<td class='date-settled'>{$row['date_settled']}</td>";
            echo "<td>";
            echo "<select class='status-dropdown'>";
            echo "<option value='not paid'" . ($status == 'not paid' ? ' selected' : '') . ">not paid</option>";
            echo "<option value='paid'" . ($status == 'paid' ? ' selected' : '') . ">paid</option>";
            echo "</select>";
            echo "</td>";
            echo "<td>";
            echo "<span class='status-badge $statusesClass'>{$statuses}</span>";
            echo "<button class='edit-btn'>Update</button>";
            echo "</td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>
<div id="modal">
    <div class="modal-content">
        <h3>Edit Bill Amount</h3>
        <form id="editForm">
            <label for="editAmount">New Amount (KES):</label>
            <input type="number" id="editAmount" name="editAmount" required step="0.01" min="0">
            <input type="hidden" id="editBillId" name="editBillId">
            <button type="submit" class="update-btn">Update</button>
        </form>
    </div>
</div>
<script>
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const rows = document.querySelectorAll("#billsTable tbody tr");

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const status = statusFilter.value;

        rows.forEach(row => {
            const cells = row.querySelectorAll("td");
            const rowText = Array.from(cells).map(td => td.textContent.toLowerCase()).join(" ");
            const rowStatus = row.querySelector(".status-dropdown").value.toLowerCase();

            const matchesSearch = rowText.includes(searchText);
            const matchesStatus = !status || rowStatus === status;

            row.style.display = matchesSearch && matchesStatus ? "" : "none";
        });
    }

    searchInput.addEventListener("input", filterTable);
    statusFilter.addEventListener("change", filterTable);

    document.querySelectorAll('.status-dropdown').forEach(select => {
        select.addEventListener('change', async function() {
            const row = this.closest('tr');
            const billId = row.getAttribute('data-bill-id');
            const newStatus = this.value;

            if (newStatus === 'paid') {
                // Prompt for settlement date
                const settlementDate = prompt("Enter settlement date (YYYY-MM-DD) or leave blank for today:", "");
                
                if (settlementDate === null) {
                    // User cancelled, revert dropdown
                    this.value = 'not paid';
                    return;
                }

                // Validate date format if provided
                if (settlementDate && !/^\d{4}-\d{2}-\d{2}$/.test(settlementDate)) {
                    alert("Please enter date in YYYY-MM-DD format or leave blank for today");
                    this.value = 'not paid';
                    return;
                }
                
                const formData = new FormData();
                formData.append('bill_id', billId);
                formData.append('new_status', newStatus);
                if (settlementDate) formData.append('date_settled', settlementDate);

                try {
                    const response = await fetch('bills.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (await response.text() === 'status_updated') {
                        location.reload();
                    } else {
                        throw new Error('Status update failed');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to update status');
                    this.value = 'not paid';
                }
            } else {
                // For 'not paid' status, proceed normally
                fetch('bills.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `bill_id=${billId}&new_status=${newStatus}`
                }).then(() => location.reload());
            }
        });
    });

    const modal = document.getElementById("modal");
    const editForm = document.getElementById("editForm");
    const editAmount = document.getElementById("editAmount");
    const editBillId = document.getElementById("editBillId");

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const billId = row.getAttribute('data-bill-id');
            const amountCell = row.querySelector('.amount-text');
            const currentAmount = parseFloat(amountCell.textContent.replace('KES ', ''));

            editAmount.value = currentAmount.toFixed(2);
            editBillId.value = billId;
            modal.style.display = 'block';
        });
    });

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }

    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const amount = parseFloat(editAmount.value).toFixed(2);
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid positive amount');
            return;
        }
        fetch('bills.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `edit_bill_id=${editBillId.value}&new_amount=${amount}`
        })
        .then(res => res.text())
        .then(response => {
            if (response.trim() === 'success') {
                location.reload();
            } else {
                alert('Failed to update amount: ' + response);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the amount.');
        });
    });
</script>
</body>
</html>