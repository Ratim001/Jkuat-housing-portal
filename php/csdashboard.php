<?php
session_start();
include '../includes/db.php';

$applicantsCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applicants"));
$requestsCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM service_requests"));
$receivedCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM notices")); // received
$sentCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM notifications")); // sent

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin Dashboard - JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', 'Inter', 'Roboto', Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .sidebar {
            width: 220px; background-color: #004225; color: #fff;
            height: 100vh; position: fixed; padding: 20px 0;
        }
        .sidebar img { width: 60px; margin-bottom: 10px; }
        .sidebar h2, .sidebar p { margin-bottom: 10px; }
        .sidebar nav ul { list-style: none; padding: 0; }
        .sidebar nav ul li { margin: 15px 0; }
        .sidebar nav ul li a {
            color: #fff; text-decoration: none; font-weight: bold;
        }

        .main-content { margin-left: 220px; padding: 40px; }

        .top-header {
            background-color: #fff; padding: 15px;
            display: flex; justify-content: space-between;
            align-items: center; border-bottom: 2px solid #ddd;
        }

        .top-header h1 { color: #006400; margin: 0; }

        .user-icon {
            width: 40px; height: 40px; cursor: pointer;
            border-radius: 50%;
        }

        .profile-menu {
            display: none;
            position: absolute;
            right: 20px;
            top: 70px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 150px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-menu a {
            display: block;
            padding: 10px;
            color: #006400;
            text-decoration: none;
        }

        .profile-menu a:hover {
            background-color: #f0f0f0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #006400;
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #006400;
        }

        .card h3 { margin: 10px 0; color: #333; }
        .card p {
            font-size: 22px;
            color: #004225;
            font-weight: bold;
        }

        #chart-container {
            margin-top: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            max-width: 100%;
        }

        footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
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
                padding: 15px;
            }
            .top-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            .top-header h1 {
                font-size: 18px;
                flex: 1;
                min-width: 200px;
            }
            .cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            .card {
                padding: 15px;
            }
            .card i {
                font-size: 24px;
            }
        }
        @media (max-width: 480px) {
            .sidebar {
                width: 200px;
            }
            .top-header h1 {
                font-size: 14px;
            }
            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>DASHBOARD</p>
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
        <button class="hamburger-menu" id="hamburgerBtn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <div style="position: relative;">
            <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
            <div class="profile-menu" id="profileMenu">
                <a href="#">Profile</a>
                <a href="login.php">Logout</a>
            </div>
        </div>
    </header>

    <div class="cards">
        <div class="card">
            <i class="fas fa-users"></i>
            <h3>Applicants</h3>
            <p><?= $applicantsCount ?></p>
        </div>
        <div class="card">
            <i class="fas fa-tools"></i>
            <h3>Service Requests</h3>
            <p><?= $requestsCount ?></p>
        </div>
        <div class="card">
            <i class="fas fa-inbox"></i>
            <h3>Received Notices</h3>
            <p><?= $receivedCount ?></p>
        </div>
        <div class="card">
            <i class="fas fa-paper-plane"></i>
            <h3>Sent Notices</h3>
            <p><?= $sentCount ?></p>
        </div>
    </div>

    <div id="chart-container">
        <canvas id="summaryChart"></canvas>
    </div>

    <footer>
        
    </footer>
</div>

<script>
function toggleMenu() {
    const menu = document.getElementById("profileMenu");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
}

window.onclick = function(e) {
    if (!e.target.matches('.user-icon')) {
        const dropdown = document.getElementById("profileMenu");
        if (dropdown && dropdown.style.display === "block") {
            dropdown.style.display = "none";
        }
    }
}

// Chart.js
const ctx = document.getElementById('summaryChart').getContext('2d');
const summaryChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Applicants', 'Service Requests', 'Received Notices', 'Sent Notices'],
        datasets: [{
            label: 'Total',
            data: [<?= $applicantsCount ?>, <?= $requestsCount ?>, <?= $receivedCount ?>, <?= $sentCount ?>],
            backgroundColor: [
                '#28a745', '#ffc107', '#17a2b8', '#dc3545'
            ],
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
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
</script>

</body>
</html>
