<?php
session_start();
if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

// Determine which page to load
$page = isset($_GET['page']) ? $_GET['page'] : 'apply';
$allowed_pages = ['apply', 'ballot', 'notifications'];
if (!in_array($page, $allowed_pages)) {
    $page = 'apply';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Dashboard | JKUAT Staff Housing</title>
    <link rel="stylesheet" href="../css/applicants.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: #006400;
            color: white;
            height: 100vh;
            padding: 20px 10px;
            position: fixed;
        }

        .sidebar img {
            width: 60px;
            margin-bottom: 10px;
        }

        .sidebar h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .sidebar p {
            font-size: 14px;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            padding: 12px;
            margin-bottom: 10px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 6px;
            background-color: #228B22;
        }

        .sidebar a.active, .sidebar a:hover {
            background-color: #90EE90;
            color: black;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }

        .top-header {
            background-color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ddd;
        }

        .top-header h1 {
            color: #006400;
            margin: 0;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
        }

        footer {
            text-align: center;
            margin-top: 40px;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="../images/2logo.png" alt="JKUAT Logo">
    <h2>Applicant Portal</h2>
    <p>Navigation</p>
    <a href="?page=apply" class="<?= $page === 'apply' ? 'active' : '' ?>">🏠 Apply</a>
    <a href="?page=ballot" class="<?= $page === 'ballot' ? 'active' : '' ?>">🎲 Balloting</a>
    <a href="?page=notifications" class="<?= $page === 'notifications' ? 'active' : '' ?>">🔔 Notifications</a>
    <a href="../logout.php" style="margin-top: 20px;">🚪 Logout</a>
</div>

<div class="main-content">
    <header class="top-header">
        <h1>JKUAT STAFF HOUSING</h1>
        <img src="../images/p-icon.png" class="user-icon" alt="Profile" onclick="alert('Profile coming soon')">
    </header>

    <?php include "content/{$page}.php"; ?>

    <footer>
        &copy; 2025 - JKUAT Staff Housing Portal
    </footer>
</div>

</body>
</html>
