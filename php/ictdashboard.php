<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ICT Admin Dashboard - JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/ictdashboard.css">
    <style>
        .user-icon { width: 40px; height: 40px; cursor: pointer; border-radius: 50%; }
        .user-menu { display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; right: 20px; top: 60px; border-radius: 5px; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: #fff; padding: 20px; border-radius: 10px; width: 400px; border: 2px solid #006400; }
        .modal-content h2 { color: #006400; text-align: center; }
        .modal-content label { display: block; margin-top: 10px; font-weight: bold; }
        .modal-content input, .modal-content select { width: 100%; padding: 8px; margin-top: 5px; border-radius: 5px; border: 1px solid #ccc; }
        .submit-btn { background: #28a745; color: #fff; border: none; padding: 10px; margin-top: 15px; border-radius: 5px; cursor: pointer; width: 100%; }
        .close-btn { background: #dc3545; color: #fff; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        
        /* Sidebar styles */
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin-bottom: 10px; }
        .sidebar li a { 
            display: block; 
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar li a:hover { background-color: #f0f0f0; }
        .sidebar li.active a { 
            background-color: #006400;
            color: white;
        }
        .sidebar .submenu { 
            margin-left: 15px;
            margin-top: 5px;
            border-left: 2px solid #ddd;
        }
        .sidebar .submenu li { margin-bottom: 5px; }
        
        /* Logs table styles */
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .logs-table th, .logs-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .logs-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .logs-table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Back button */
        .btn-back {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<?php
include '../includes/db.php';

function generateUserId($conn) {
    $result = mysqli_query($conn, "SELECT user_id FROM users ORDER BY user_id DESC LIMIT 1");
    if ($row = mysqli_fetch_assoc($result)) {
        $lastIdNum = (int) substr($row['user_id'], 4);
        $newIdNum = $lastIdNum + 1;
    } else {
        $newIdNum = 1;
    }
    return 'user' . str_pad($newIdNum, 3, '0', STR_PAD_LEFT);
}

if (isset($_POST['add_user'])) {
    $user_id = generateUserId($conn);
    $pf_no = $_POST['pf_no'];
    $username = $_POST['username'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $date_created = date('Y-m-d H:i:s');
    $status = $_POST['status'];

    $sql = "INSERT INTO users (user_id, pf_no, username, name, email, role, password, date_created, status)
            VALUES ('$user_id', '$pf_no', '$username', '$name', '$email', '$role', '$password', '$date_created', '$status')";
    mysqli_query($conn, $sql);
    header("Location: ictdashboard.php");
    exit;
}

if (isset($_POST['update_user'])) {
    $user_id = $_POST['edit_user_id'];
    $pf_no = $_POST['edit_pf_no'];
    $username = $_POST['edit_username'];
    $name = $_POST['edit_name'];
    $email = $_POST['edit_email'];
    $role = $_POST['edit_role'];
    $status = $_POST['edit_status'];

    $sql = "UPDATE users SET pf_no='$pf_no', username='$username', name='$name', email='$email', role='$role', status='$status' WHERE user_id='$user_id'";
    mysqli_query($conn, $sql);
    header("Location: ictdashboard.php");
    exit;
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM users WHERE user_id = '$delete_id'");
    header("Location: ictdashboard.php");
    exit;
}

$selectedRole = $_GET['role'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$logType = $_GET['log_type'] ?? '';

// User table query
$query = "SELECT * FROM users WHERE 1=1";
if (!empty($selectedRole)) $query .= " AND role = '$selectedRole'";
if (!empty($selectedStatus)) $query .= " AND status = '$selectedStatus'";
if (!empty($search)) {
    $searchSafe = mysqli_real_escape_string($conn, $search);
    $query .= " AND (
        user_id LIKE '%$searchSafe%' OR 
        pf_no LIKE '%$searchSafe%' OR 
        username LIKE '%$searchSafe%' OR 
        name LIKE '%$searchSafe%' OR 
        email LIKE '%$searchSafe%' OR 
        role LIKE '%$searchSafe%' OR
        status LIKE '%$searchSafe%'
    )";
}
$result = mysqli_query($conn, $query);

// Handle logs display
$houseLogs = [];
$billLogs = [];

if ($logType === 'houses') {
    $houseLogsQuery = "SELECT * FROM house_update_logs ORDER BY date_updated DESC";
    $houseLogsResult = mysqli_query($conn, $houseLogsQuery);
    while ($row = mysqli_fetch_assoc($houseLogsResult)) {
        $houseLogs[] = $row;
    }
} elseif ($logType === 'bills') {
    $billLogsQuery = "SELECT * FROM bill_update_logs ORDER BY date_updated DESC";
    $billLogsResult = mysqli_query($conn, $billLogsQuery);
    while ($row = mysqli_fetch_assoc($billLogsResult)) {
        $billLogs[] = $row;
    }
}
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <img src="../images/2logo.png" alt="Logo" class="logo">
        <h2>ICT ADMIN</h2>
        <p>DASHBOARD</p>
        <nav>
            <ul>
                <li class="<?= empty($logType) ? 'active' : '' ?>">
                    <a href="ictdashboard.php">USER MANAGEMENT</a>
                </li>
                <li>
                    <span>LOGS</span>
                    <ul class="submenu">
                        <li class="<?= $logType === 'houses' ? 'active' : '' ?>">
                            <a href="?log_type=houses">Houses Logs</a>
                        </li>
                        <li class="<?= $logType === 'bills' ? 'active' : '' ?>">
                            <a href="?log_type=bills">Bills Logs</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <h1>JKUAT STAFF HOUSING PORTAL</h1>
            <div class="header-actions">
                <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
                <div id="user-menu" class="user-menu">
                    <button class="btn-profile">Profile</button><br><br>
                    <button class="btn-logout" onclick="confirmLogout()">Log out</button>
                </div>
            </div>
        </header>

        <?php if (empty($logType)): ?>
        <!-- User Management Section -->
        <section class="controls">
            <form method="GET" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div class="entries">
                    Show entries 
                    <select name="entries">
                        <option>5</option>
                        <option>10</option>
                        <option>15</option>
                        <option value="All">All</option>
                    </select>
                </div>
                <div class="filter-search">
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    <button type="button" class="btn-add" onclick="openModal('addUserModal')">+ ADD</button>
                </div>
            </form>
        </section>

        <section class="user-table">
            <form method="GET">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>PF Number</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role<br>
                                <select name="role" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="ICT Admin" <?= $selectedRole == 'ICT Admin' ? 'selected' : '' ?>>ICT Admin</option>
                                    <option value="CS Admin" <?= $selectedRole == 'CS Admin' ? 'selected' : '' ?>>CS Admin</option>
                                </select>
                            </th>
                            <th>Date Created</th>
                            <th>Status<br>
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="Active" <?= $selectedStatus == 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Deactivated" <?= $selectedStatus == 'Deactivated' ? 'selected' : '' ?>>Deactivated</option>
                                </select>
                            </th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['user_id']); ?></td>
                                <td><?= htmlspecialchars($row['pf_no']); ?></td>
                                <td><?= htmlspecialchars($row['username']); ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['role']); ?></td>
                                <td><?= htmlspecialchars($row['date_created']); ?></td>
                                <td><?= htmlspecialchars($row['status']); ?></td>
                                <td>
                                    <button type="button" onclick="openEditModal('<?= $row['user_id'] ?>', '<?= $row['pf_no'] ?>', '<?= $row['username'] ?>', '<?= $row['name'] ?>', '<?= $row['email'] ?>', '<?= $row['role'] ?>', '<?= $row['status'] ?>')">Update</button>
                                    <button type="button" onclick="confirmDelete('<?= $row['user_id'] ?>')">Delete</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </section>
        <?php else: ?>
        <!-- Logs Section -->
        <section class="logs-section">
            <?php if ($logType === 'houses'): ?>
            <div class="logs-header">
                <h2>House Update Logs</h2>
                <button class="btn-back" onclick="window.location.href='ictdashboard.php'">Back to Users</button>
            </div>
            <div class="controls">
                <form method="GET">
                    <input type="hidden" name="log_type" value="houses">
                    <input type="text" name="search_logs" placeholder="Search logs..." value="<?= htmlspecialchars($_GET['search_logs'] ?? '') ?>">
                </form>
            </div>
            <div class="logs-content">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>House ID</th>
                            <th>Device Type</th>
                            <th>Details</th>
                            <th>Date Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($houseLogs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['house_update_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['user_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['house_id'] ?? ($log['house id'] ?? '')); ?></td>
                            <td><?= htmlspecialchars($log['device_type'] ?? ($log['device type'] ?? '')); ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['date_updated'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($logType === 'bills'): ?>
            <div class="logs-header">
                <h2>Bill Update Logs</h2>
                <button class="btn-back" onclick="window.location.href='ictdashboard.php'">Back to Users</button>
            </div>
            <div class="controls">
                <form method="GET">
                    <input type="hidden" name="log_type" value="bills">
                    <input type="text" name="search_logs" placeholder="Search logs..." value="<?= htmlspecialchars($_GET['search_logs'] ?? '') ?>">
                </form>
            </div>
            <div class="logs-content">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Bill ID</th>
                            <th>Device Type</th>
                            <th>Details</th>
                            <th>Date Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billLogs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['bill_update_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['user_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['bill_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['device_type'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($log['date_updated'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>

<!-- Modals (keep existing) -->
<div class="modal" id="addUserModal">
    <div class="modal-content">
        <h2>Add New User</h2>
        <form method="POST">
            <label>PF Number</label><input type="text" name="pf_no" required>
            <label>Username</label><input type="text" name="username" required>
            <label>Name</label><input type="text" name="name" required>
            <label>Email</label><input type="email" name="email" required>
            <label>Role</label>
            <select name="role" required><option value="ICT Admin">ICT Admin</option><option value="CS Admin">CS Admin</option></select>
            <label>Password</label><input type="password" name="password" required>
            <label>Status</label>
            <select name="status" required><option value="Active">Active</option><option value="Deactivated">Deactivated</option></select>
            <button type="submit" name="add_user" class="submit-btn">Save</button>
            <button type="button" class="close-btn" onclick="closeModal('addUserModal')">Cancel</button>
        </form>
    </div>
</div>

<div class="modal" id="editUserModal">
    <div class="modal-content">
        <h2>Edit User</h2>
        <form method="POST">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            <label>PF Number</label><input type="text" name="edit_pf_no" id="edit_pf_no" required>
            <label>Username</label><input type="text" name="edit_username" id="edit_username" required>
            <label>Name</label><input type="text" name="edit_name" id="edit_name" required>
            <label>Email</label><input type="email" name="edit_email" id="edit_email" required>
            <label>Role</label>
            <select name="edit_role" id="edit_role" required><option value="ICT Admin">ICT Admin</option><option value="CS Admin">CS Admin</option></select>
            <label>Status</label>
            <select name="edit_status" id="edit_status" required><option value="Active">Active</option><option value="Deactivated">Deactivated</option></select>
            <button type="submit" name="update_user" class="submit-btn">Update</button>
            <button type="button" class="close-btn" onclick="closeModal('editUserModal')">Cancel</button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function toggleMenu() {
    const menu = document.getElementById('user-menu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}
function confirmLogout() {
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = "logout.php";
    }
}
function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this user?")) {
        window.location.href = "ictdashboard.php?delete=" + id;
    }
}
function openEditModal(id, pf, username, name, email, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_pf_no').value = pf;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    openModal('editUserModal');
}
window.onclick = function(e) {
    if (!e.target.closest('.header-actions')) {
        const menu = document.getElementById('user-menu');
        if (menu) menu.style.display = 'none';
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const searchLogsInput = document.querySelector('input[name="search_logs"]');
    
    if (searchInput) {
        const form = searchInput.closest('form');
        searchInput.addEventListener('input', function() {
            if (this.value.trim() === '') {
                form.submit();
            }
        });
    }
    
    if (searchLogsInput) {
        const form = searchLogsInput.closest('form');
        searchLogsInput.addEventListener('input', function() {
            if (this.value.trim() === '') {
                form.submit();
            }
        });
    }
});
</script>

</body>
</html>