<?php
include '../includes/db.php';
session_start();

// Create house_update_logs table with automatic timestamp
$create_table = "CREATE TABLE IF NOT EXISTS house_update_logs (
    house_update_id VARCHAR(100) PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    house_id VARCHAR(100) NOT NULL,
    device_type VARCHAR(255),
    details TEXT NOT NULL,
    date_updated DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table)) {
    die("Error creating house_update_logs table: " . $conn->error);
}

// Function to generate next house_update_id
function getNextHouseUpdateId($conn) {
    $result = $conn->query("SELECT house_update_id FROM house_update_logs ORDER BY house_update_id DESC LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $last_id = $row['house_update_id'];
        if (preg_match('/HU(\d+)/', $last_id, $matches)) {
            $num = intval($matches[1]) + 1;
            return 'HU' . str_pad($num, 3, '0', STR_PAD_LEFT);
        }
    }
    return 'HU001';
}

// Handle AJAX: Add or Update House
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $house_id = $_POST['house_id'] ?? '';
    $house_no = $_POST['house_no'];
    $category = $_POST['category'];
    $creator = $_POST['creator'];
    $date_created = $_POST['date_created'];
    $rent = $_POST['rent'];
    $status = $_POST['status'];

    $conn->begin_transaction();
    try {
        if ($house_id === '') {
            // Create new house
            $prefix = match ($category) {
                "1 Bedroom" => "H1",
                "2 Bedroom" => "H2",
                "3 Bedroom" => "H3",
                "4 Bedroom" => "H4",
                default => "H0",
            };

            $stmt = $conn->prepare("SELECT house_id FROM houses WHERE category = ? ORDER BY house_id DESC LIMIT 1");
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();

            // Fixed house ID generation
            $newNumber = 1;
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $last_id = $row['house_id'];
                $newNumber = (int) substr($last_id, 2) + 1;
            }
            $house_id = $prefix . str_pad($newNumber, 3, "0", STR_PAD_LEFT);

            $insert = $conn->prepare("INSERT INTO houses (house_id, house_no, category, creator, date, rent, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssssis", $house_id, $house_no, $category, $creator, $date_created, $rent, $status);
            $insert->execute();

            $details = "House created: {$house_no} ({$category}) | Rent: KES {$rent} | Status: {$status}";
        } else {
            // Update existing house
            $old_data = $conn->prepare("SELECT house_no, category, rent, status FROM houses WHERE house_id = ?");
            $old_data->bind_param("s", $house_id);
            $old_data->execute();
            $old_result = $old_data->get_result();
            $old_row = $old_result->fetch_assoc();

            $update = $conn->prepare("UPDATE houses SET house_no=?, category=?, creator=?, date=?, rent=?, status=? WHERE house_id=?");
            $update->bind_param("ssssiss", $house_no, $category, $creator, $date_created, $rent, $status, $house_id);
            $update->execute();

            // Track changes
            $changes = [];
            if ($old_row['house_no'] != $house_no) $changes[] = "House No: {$old_row['house_no']} → {$house_no}";
            if ($old_row['category'] != $category) $changes[] = "Category: {$old_row['category']} → {$category}";
            if ($old_row['rent'] != $rent) $changes[] = "Rent: KES {$old_row['rent']} → KES {$rent}";
            if ($old_row['status'] != $status) $changes[] = "Status: {$old_row['status']} → {$status}";
            
            $details = "House updated: " . implode(" | ", $changes);
        }

        // Log the action - store all values in variables first
        $update_id = getNextHouseUpdateId($conn);
        $current_user_id = $_SESSION['user_id'] ?? 'system';
        $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_stmt = $conn->prepare("INSERT INTO house_update_logs 
            (house_update_id, user_id, house_id, device_type, details) 
            VALUES (?, ?, ?, ?, ?)");
        
        $log_stmt->bind_param(
            "sssss", 
            $update_id,
            $current_user_id,
            $house_id,
            $device_info,
            $details
        );

        if (!$log_stmt->execute()) {
            throw new Exception("Failed to log house update: " . $conn->error);
        }

        $conn->commit();
        echo json_encode(["success" => true, "house" => compact("house_id", "house_no", "category", "creator", "date_created", "rent", "status")]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX: Delete House
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    header('Content-Type: application/json');
    
    $conn->begin_transaction();
    try {
        $house_info = $conn->prepare("SELECT house_no, category FROM houses WHERE house_id = ?");
        $house_info->bind_param("s", $_POST['delete_id']);
        $house_info->execute();
        $house_result = $house_info->get_result();
        $house_data = $house_result->fetch_assoc();

        $stmt = $conn->prepare("DELETE FROM houses WHERE house_id = ?");
        $stmt->bind_param("s", $_POST['delete_id']);
        $stmt->execute();

        // Log deletion - store all values in variables first
        $update_id = getNextHouseUpdateId($conn);
        $current_user_id = $_SESSION['user_id'] ?? 'system';
        $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $log_details = "House deleted: {$house_data['house_no']} ({$house_data['category']})";
        
        $log_stmt = $conn->prepare("INSERT INTO house_update_logs 
            (house_update_id, user_id, house_id, device_type, details) 
            VALUES (?, ?, ?, ?, ?)");
        
        $log_stmt->bind_param(
            "sssss", 
            $update_id,
            $current_user_id,
            $_POST['delete_id'],
            $device_info,
            $log_details
        );

        $log_stmt->execute();
        $conn->commit();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

$houses = mysqli_query($conn, "SELECT * FROM houses ORDER BY house_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Houses | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .sidebar { width: 250px; background-color: #006400; color: #fff; height: 100vh; position: fixed; padding: 20px 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .top-header { background-color: #fff; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ddd; }
        .top-header h1 { color: #006400; margin: 0; }
        .user-icon { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }
        h3 { color:rgb(241, 241, 241); margin-top: 20px; }
        h2 { color:rgb(26, 119, 31); margin-top: 20px; }
        .controls { display: flex; justify-content: space-between; flex-wrap: wrap; margin-top: 20px; align-items: center; }
        .left-controls { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .filters select, #searchInput { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-green { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        thead tr { background-color: #006400; color: white; }
        .action-btns button { margin-right: 5px; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; }
        .update { background-color: #90ee90; color: #000; }
        .delete { background-color: #dc3545; color: #fff; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 999; }
        .modal-content { background: #fff; padding: 20px; width: 400px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .modal-content h2 { color: #006400; }
        .modal-content label { display: block; margin-top: 10px; }
        .modal-content input, .modal-content select { width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; border-radius: 4px; border: 1px solid #ccc; }
        .submit-btn { background-color: #28a745; color: #fff; padding: 10px; border: none; border-radius: 5px; }
        .close-btn { background-color: #6c757d; color: #fff; padding: 10px; border: none; border-radius: 5px; margin-left: 10px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h3>CS ADMIN</h3>
    <p>HOUSES</p>
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
    <header class="top-header">
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </header>

    <h2>Manage Houses</h2>

    <div class="controls">
        <div class="left-controls">
            <label>Show
    <select id="entriesSelect">
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="15">15</option>
        <option value="20">20</option>
        <option value="all">All</option>
    </select>
</label>

            <select id="categoryFilter">
                <option value="">All Categories</option>
                <option value="1 Bedroom">1 Bedroom</option>
                <option value="2 Bedroom">2 Bedroom</option>
                <option value="3 Bedroom">3 Bedroom</option>
                <option value="4 Bedroom">4 Bedroom</option>
            </select>
            <select id="statusFilter">
                <option value="">All Status</option>
                <option value="Vacant">Vacant</option>
                <option value="Occupied">Occupied</option>
                <option value="reserved">reserved</option>
            </select>
        </div>

        <div class="right-controls">
            <input type="text" id="searchInput" placeholder="Search...">
            <button class="btn-green" onclick="openModal()">+ Add House</button>
        </div>
    </div>

    <table id="housesTable">
        <thead>
            <tr>
                <th>House ID</th>
                <th>House No</th>
                <th>Category</th>
                <th>Creator</th>
                <th>Date Created</th>
                <th>Rent</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($houses)): ?>
                <tr data-id="<?= $row['house_id'] ?>">
                    <td><?= htmlspecialchars($row['house_id']) ?></td>
                    <td><?= htmlspecialchars($row['house_no']) ?></td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars($row['creator']) ?></td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['rent']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td class="action-btns">
                        <button class="update" onclick='editHouse(<?= json_encode($row) ?>)'>Update</button>
                        <button class="delete" onclick='deleteHouse("<?= $row["house_id"] ?>")'>Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="houseModal">
    <div class="modal-content">
        <h2 id="modalTitle">Add House</h2>
        <form id="addHouseForm">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="house_id" id="house_id">
            <label>House No</label>
            <input type="text" name="house_no" id="house_no" required>
            <label>Category</label>
            <select name="category" id="category" required>
                <option value="">Select Category</option>
                <option value="1 Bedroom">1 Bedroom</option>
                <option value="2 Bedroom">2 Bedroom</option>
                <option value="3 Bedroom">3 Bedroom</option>
                <option value="4 Bedroom">4 Bedroom</option>
            </select>
            <label>Creator</label>
            <input type="text" name="creator" id="creator" required>
            <label>Date Created</label>
            <input type="date" name="date_created" id="date_created" required>
            <label>Rent</label>
            <input type="number" name="rent" id="rent" required>
            <label>Status</label>
            <select name="status" id="status" required>
                <option value="Vacant">Vacant</option>
                <option value="Occupied">Occupied</option>
                <option value="reserved">reserved</option>
            </select>
            <button type="submit" class="submit-btn">Save</button>
            <button type="button" class="close-btn" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function toggleMenu() {
    alert("Profile menu coming soon.");
}
function openModal() {
    document.getElementById('modalTitle').innerText = 'Add House';
    document.getElementById('addHouseForm').reset();
    document.getElementById('house_id').value = '';
    document.getElementById('houseModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('houseModal').style.display = 'none';
}
function editHouse(data) {
    document.getElementById('modalTitle').innerText = 'Update House';
    document.getElementById('house_id').value = data.house_id;
    document.getElementById('house_no').value = data.house_no;
    document.getElementById('category').value = data.category;
    document.getElementById('creator').value = data.creator;
    document.getElementById('date_created').value = data.date;
    document.getElementById('rent').value = data.rent;
    document.getElementById('status').value = data.status;
    document.getElementById('houseModal').style.display = 'flex';
}
function deleteHouse(id) {
    if (confirm("Are you sure you want to delete this house?")) {
        $.post('', { delete_id: id }, function(response) {
            if (response.success) {
                document.querySelector(`tr[data-id="${id}"]`).remove();
            } else {
                alert("Delete failed.");
            }
        }, 'json');
    }
}
document.getElementById("searchInput").addEventListener("input", function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#housesTable tbody tr");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});
$(document).ready(function () {
    $('#addHouseForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: '',
            type: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.success) {
                    location.reload(); // or dynamically update if needed
                } else {
                    alert("Error: " + response.error);
                }
            },
            error: function () {
                alert("AJAX request failed.");
            }
        });
    });
});
</script>
<script>
function applyFilters() {
    const category = document.getElementById("categoryFilter").value.toLowerCase();
    const status = document.getElementById("statusFilter").value.toLowerCase();
    const search = document.getElementById("searchInput").value.toLowerCase();
    const entriesValue = document.getElementById("entriesSelect").value;
    const entriesLimit = entriesValue === "all" ? Infinity : parseInt(entriesValue);

    const rows = document.querySelectorAll("#housesTable tbody tr");
    let visibleCount = 0;

    rows.forEach(row => {
        const rowCategory = row.children[2].textContent.toLowerCase();
        const rowStatus = row.children[6].textContent.toLowerCase();
        const rowText = row.textContent.toLowerCase();

        const categoryMatch = !category || rowCategory === category;
        const statusMatch = !status || rowStatus === status;
        const searchMatch = rowText.includes(search);

        const shouldDisplay = categoryMatch && statusMatch && searchMatch;

        if (shouldDisplay && visibleCount < entriesLimit) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });
}

// Event listeners
document.getElementById("categoryFilter").addEventListener("change", applyFilters);
document.getElementById("statusFilter").addEventListener("change", applyFilters);
document.getElementById("searchInput").addEventListener("input", applyFilters);
document.getElementById("entriesSelect").addEventListener("change", applyFilters);

// Initial run
window.addEventListener("load", applyFilters);
</script>



</body>
</html>
