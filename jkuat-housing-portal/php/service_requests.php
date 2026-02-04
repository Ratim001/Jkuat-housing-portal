<?php
include '../includes/db.php';
session_start();

// Function to sync bill amount to service request
function syncBillAmountToServiceRequest($conn, $service_id, $amount) {
    $stmt = $conn->prepare("UPDATE service_requests SET bill_amount = ?, status = 'Done' WHERE service_id = ?");
    $stmt->bind_param("ds", $amount, $service_id);
    return $stmt->execute();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $service_id = $_POST['service_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE service_id = ?");
    $stmt->bind_param("ss", $status, $service_id);
    if ($stmt->execute()) {
        echo "Status updated successfully";
    } else {
        echo "Error updating status";
    }
    exit;
}

// Handle bill creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'], $_POST['type_of_bill'], $_POST['amount'])) {
    $service_id = $_POST['service_id'];
    $type_of_bill = $_POST['type_of_bill'];
    $amount = $_POST['amount'];
    $status = "not paid";
    $date_billed = date('Y-m-d');

    // Check if this service already has a bill
    $check_stmt = $conn->prepare("SELECT bill_id FROM bills WHERE service_id = ?");
    $check_stmt->bind_param("s", $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "This service request has already been billed"]);
        exit;
    }

    // Generate bill ID
    $prefix = "B" . substr($type_of_bill, 0, 1);
    $stmt = $conn->prepare("SELECT bill_id FROM bills WHERE type_of_bill = ? ORDER BY bill_id DESC LIMIT 1");
    $stmt->bind_param("s", $type_of_bill);
    $stmt->execute();
    $result = $stmt->get_result();

    $newNumber = 1;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNum = (int) substr($row['bill_id'], 2);
        $newNumber = $lastNum + 1;
    }

    $bill_id = $prefix . str_pad($newNumber, 3, "0", STR_PAD_LEFT);

    $conn->begin_transaction();
    try {
        // 1. Insert into bills table (without tenant_id)
        $insert = $conn->prepare("INSERT INTO bills (bill_id, service_id, type_of_bill, amount, status, date_billed) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sssdss", $bill_id, $service_id, $type_of_bill, $amount, $status, $date_billed);
        
        if (!$insert->execute()) {
            throw new Exception("Failed to insert bill: " . $conn->error);
        }

        // 2. Update service request with bill amount and mark as Done
        if (!syncBillAmountToServiceRequest($conn, $service_id, $amount)) {
            throw new Exception("Failed to update service request amount: " . $conn->error);
        }

        $conn->commit();
        echo json_encode(["success" => true, "bill_id" => $bill_id]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

$query = "
    SELECT 
        s.service_id, 
        s.tenant_id, 
        t.house_no, 
        s.type_of_service, 
        s.details, 
        s.bill_amount, 
        s.date, 
        s.status
    FROM service_requests s
    INNER JOIN tenants t ON s.tenant_id = t.tenant_id
    ORDER BY s.date DESC
";

$result = mysqli_query($conn, $query);
?>

<!-- Rest of your HTML remains the same -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Service Requests | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #fff;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #fff;
            min-height: 100vh;
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
        .main-content h2 {
            color: #006400;
            margin-bottom: 20px;
        }
        .table-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .table-controls input[type="text"] {
            padding: 8px;
            width: 300px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background-color: #006400;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ccc;
        }
        .status-pending {
            color: red;
            font-weight: bold;
        }
        .status-done {
            color: green;
            font-weight: bold;
        }
        .sidebar ul li a.active {
            background-color: #ffffff;
            color: #005826;
            font-weight: bold;
        }
        .bill-btn {
            background-color: #28a745;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .bill-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            width: 400px;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            position: relative;
        }
        .modal-content h3 {
            margin-top: 0;
            color: #006400;
        }
        .modal input, .modal select {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .btn-submit {
            margin-top: 15px;
            background-color: #006400;
            color: #fff;
            padding: 10px;
            width: 100%;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
        }
        #billMessage {
            margin-top: 10px;
            padding: 10px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            display: none;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>SERVICE REQUESTS</p>
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
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </div>

    <h2>Service Requests</h2>

    <div class="table-controls">
        <input type="text" id="searchService" placeholder="Search service requests...">
    </div>

    <table id="serviceTable">
        <thead>
            <tr>
                <th>Service ID</th>
                <th>Tenant ID</th>
                <th>House No</th>
                <th>Type</th>
                <th>Details</th>
                <th>Bill (KES)</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['service_id']) ?></td>
                    <td><?= htmlspecialchars($row['tenant_id']) ?></td>
                    <td><?= htmlspecialchars($row['house_no'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['type_of_service']) ?></td>
                    <td><?= htmlspecialchars($row['details']) ?></td>
                    <td><?= number_format((float)$row['bill_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td>
                        <select onchange="updateStatus('<?= $row['service_id'] ?>', this)" 
                            class="<?= strtolower($row['status']) === 'done' ? 'status-done' : 'status-pending' ?>">
                            <option value="Pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Done" <?= $row['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </td>
                    <td>
                        <button class="bill-btn" 
                            onclick="openBillModal('<?= $row['tenant_id'] ?>', '<?= $row['service_id'] ?>', '<?= $row['type_of_service'] ?>', '<?= $row['bill_amount'] ?>')"
                            <?= $row['status'] === 'Done' && empty($row['bill_amount']) ? 'disabled' : '' ?>>
                            Bill
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9">No service requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="billModal" class="modal">
    <div class="modal-content">
        <button class="btn-close" onclick="closeBillModal()">&times;</button>
        <h3>Bill Tenant for Service</h3>
        <div id="billMessage"></div>
        <form id="billForm">
            <input type="hidden" name="tenant_id" id="bill_tenant_id">
            <input type="hidden" name="service_id" id="bill_service_id">

            <label for="bill_type_of_bill">Type of Bill</label>
            <input type="text" name="type_of_bill" id="bill_type_of_bill" required>

            <label for="bill_amount">Amount (KES)</label>
            <input type="number" name="amount" id="bill_amount" step="0.01" min="0" required>

            <label>Date Billed</label>
            <input type="date" name="date_billed" value="<?= date('Y-m-d') ?>" required>

            <input type="hidden" name="status" value="not paid">

            <button type="submit" class="btn-submit">Submit Bill</button>
        </form>
    </div>
</div>

<script>
function toggleMenu() {
    alert("Profile menu coming soon.");
}

function openBillModal(tenantId, serviceId, serviceType, amount) {
    if (!tenantId || tenantId === 'undefined' || tenantId === 'null') {
        alert('Cannot create bill: Tenant information is missing');
        return;
    }
    
    document.getElementById('bill_tenant_id').value = tenantId;
    document.getElementById('bill_service_id').value = serviceId;
    document.getElementById('bill_type_of_bill').value = serviceType + " Service";
    document.getElementById('bill_amount').value = amount || '';
    document.getElementById('billModal').style.display = 'block';
    document.getElementById('billMessage').style.display = 'none';
}

function closeBillModal() {
    document.getElementById('billModal').style.display = 'none';
}

document.getElementById("searchService").addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#serviceTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

document.getElementById("billForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    // Validate tenant_id exists
    const tenantId = formData.get('tenant_id');
    if (!tenantId) {
        const billMessage = document.getElementById("billMessage");
        billMessage.innerText = "Error: Tenant information is missing";
        billMessage.className = "error-message";
        billMessage.style.display = 'block';
        return;
    }

    // Show loading state
    const submitBtn = form.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = "Processing...";
    
    fetch('service_requests.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.json();
    })
    .then(data => {
        const billMessage = document.getElementById("billMessage");
        if (data.success) {
            billMessage.innerText = "Bill created successfully! Bill ID: " + data.bill_id;
            billMessage.className = "";
            billMessage.style.display = 'block';
            
            // Refresh the page after 2 seconds to show updated data
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            billMessage.innerText = "Error: " + (data.error || "Failed to create bill");
            billMessage.className = "error-message";
            billMessage.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Bill";
        }
    })
    .catch(err => {
        console.error('Error:', err);
        const billMessage = document.getElementById("billMessage");
        billMessage.innerText = "Error submitting bill. Please try again.";
        billMessage.className = "error-message";
        billMessage.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit Bill";
    });
});

function updateStatus(serviceId, selectElement) {
    const status = selectElement.value;
    const formData = new FormData();
    formData.append('update_status', true);
    formData.append('service_id', serviceId);
    formData.append('status', status);
    
    // Show loading state
    selectElement.disabled = true;
    
    fetch('service_requests.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.text();
    })
    .then(data => {
        console.log(data);
        selectElement.className = (status.toLowerCase() === 'done') ? 'status-done' : 'status-pending';
        
        // Update bill button state
        const row = selectElement.closest('tr');
        const billBtn = row.querySelector('.bill-btn');
        if (billBtn) {
            if (status.toLowerCase() === 'done') {
                const billAmount = row.querySelector('td:nth-child(6)').textContent.trim();
                billBtn.disabled = billAmount === '0.00' || billAmount === '';
            } else {
                billBtn.disabled = false;
            }
        }
    })
    .catch(err => {
        console.error('Error updating status:', err);
        alert('Failed to update status. Please try again.');
        // Revert the selection
        selectElement.value = (status.toLowerCase() === 'done') ? 'Pending' : 'Done';
    })
    .finally(() => {
        selectElement.disabled = false;
    });
}
</script>

</body>
</html>