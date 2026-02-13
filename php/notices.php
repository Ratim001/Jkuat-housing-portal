<?php
session_start();
include '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - NOTICES | JKUAT Staff Housing Portal</title>
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
            table-layout: fixed;
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

        .action-btn {
            padding: 6px 12px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .edit-btn {
            background-color: #007bff;
            color: white;
        }

        .delete-btn {
            background-color: #dc3545;
            color: white;
        }

        .edit-btn:hover {
            background-color: #0056b3;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        .sidebar ul li a.active {
            background-color: #ffffff;
            color: #005826;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .status-revoked {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>NOTICES</p>
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

    <h2>Manage Notices</h2>

    <div class="table-controls">
        <input type="text" id="searchNotice" placeholder="Search notices...">
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Notice ID</th>
                <th style="width: 12%;">Tenant ID</th>
                <th style="width: 25%;">Details</th> 
                <th style="width: 12%;">Date Sent</th>
                <th style="width: 12%;">Notice End Date</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT * FROM notices ORDER BY date_sent DESC";
            $result = mysqli_query($conn, $query);

            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $status = strtolower($row['status'] ?? 'active');
                    $statusClass = ($status == 'active') ? 'status-active' : 'status-revoked';
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['notice_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['tenant_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['date_sent']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['notice_end_date']) . "</td>";
                    echo "<td><span class='$statusClass'>" . htmlspecialchars($status) . "</span></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No notices found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script>
    function toggleMenu() {
        alert("Profile menu coming soon.");
    }
</script>

</body>
</html>