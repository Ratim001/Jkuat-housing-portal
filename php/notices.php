<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
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
            font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif;
            background-color: #fff;
        }

        .main-content {
            margin-left: 220px;
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
        .status-fulfilled {
            background-color: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-select { padding:6px;border-radius:6px;border:1px solid #ccc }
        /* colored select states */
        .status-select.active { background-color: #28a745; color: #fff; }
        .status-select.revoked { background-color: #dc3545; color: #fff; }
        .status-select.fulfilled { background-color: #17a2b8; color: #fff; }

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
            .top-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            .top-header h1 {
                font-size: 18px;
                flex: 1;
            }
        }
        @media (max-width: 480px) {
            .sidebar {
                width: 220px;
            }
            .top-header h1 {
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
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </div>

    <h2>Manage Notices</h2>

    <div class="table-controls">
        <form method="get" action="notices.php">
            <input type="text" id="searchNotice" name="search" value="<?php echo isset($_GET['search'])?htmlspecialchars($_GET['search']):''; ?>" placeholder="Search by Notice ID or Tenant ID...">
            <button type="submit" class="action-btn" style="background:#006400;color:#fff;margin-left:8px;">Search</button>
            <a href="notices.php" class="action-btn" style="background:#ccc;color:#000;margin-left:8px;">Reset</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Notice ID</th>
                <th style="width: 12%;">Tenant ID</th>
                <th style="width: 25%;">Details</th> 
                <th style="width: 12%;">Date Sent</th>
                <th style="width: 12%;">Move Out Date</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $search = $_GET['search'] ?? '';
            if ($search !== '') {
                $s = $conn->real_escape_string($search);
                $query = "SELECT * FROM notices WHERE notice_id LIKE '%{$s}%' OR tenant_id LIKE '%{$s}%' ORDER BY date_sent DESC";
            } else {
                $query = "SELECT * FROM notices ORDER BY date_sent DESC";
            }
            $result = mysqli_query($conn, $query);

                if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $status = strtolower($row['status'] ?? 'active');
                    $statusClass = ($status == 'active') ? 'status-active' : (($status == 'revoked') ? 'status-revoked' : 'status-fulfilled');
                    
                    echo "<tr data-notice-id='" . htmlspecialchars($row['notice_id']) . "'>";
                    echo "<td>" . htmlspecialchars($row['notice_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['tenant_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['date_sent']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['notice_end_date']) . "</td>";
                                // Status dropdown and update button
                                echo "<td>";
                                // give the select a class matching the current status so it displays the colored background
                                $safeId = htmlspecialchars($row['notice_id']);
                                echo "<select class='status-select {$status}' id='status_{$safeId}' data-prev='{$status}' onchange=\"changeStatusSelect('{$safeId}')\">";
                                $opts = ['active','revoked','fulfilled'];
                                foreach ($opts as $o) {
                                    $sel = ($status === $o) ? " selected" : "";
                                    echo "<option value='" . $o . "'" . $sel . ">" . ucfirst($o) . "</option>";
                                }
                                echo "</select>";
                                // no update button: selection change is final
                                echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No notices found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script>
    function toggleMenu() {
        alert("Profile menu coming soon.");
    }

    function changeStatus(noticeId, status) {
        // Deprecated: kept for backward compatibility
        changeStatusSelect(noticeId);
    }

    function changeStatusSelect(noticeId) {
        const sel = document.getElementById('status_' + noticeId);
        if (!sel) return alert('Status selector not found');
        const newStatus = sel.value;
        const prev = sel.dataset.prev || '';
        // optimistically set the class so user sees color change immediately
        sel.classList.remove('active','revoked','fulfilled');
        sel.classList.add(newStatus);
        sel.disabled = true;
        fetch('update_notice_status.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'notice_id=' + encodeURIComponent(noticeId) + '&status=' + encodeURIComponent(newStatus)
        }).then(r=>r.json()).then(j=>{
            sel.disabled = false;
            if (j.success) {
                sel.dataset.prev = newStatus;
            } else {
                // revert
                sel.classList.remove('active','revoked','fulfilled');
                sel.classList.add(prev);
                sel.value = prev;
                alert('Failed: ' + (j.error||'unknown'));
            }
        }).catch(e=>{ console.error(e); sel.disabled = false; sel.classList.remove('active','revoked','fulfilled'); sel.classList.add(prev); sel.value = prev; alert('Error updating status'); });
    }

    // Initialize select visuals on load
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.status-select').forEach(s=>{
            const v = s.value || '';
            s.classList.remove('active','revoked','fulfilled');
            if (v) s.classList.add(v);
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
</script>

</body>
</html>