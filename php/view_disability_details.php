<?php
/**
 * php/view_disability_details.php
 * Purpose: Admin-only page to view applicant disability details (read-only)
 * Author: repo automation
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    header('Location: manage_applicants.php');
    exit;
}

// Fetch applicant with all available data using direct query
$id_escaped = $conn->real_escape_string($id);
$result = $conn->query("SELECT * FROM applicants WHERE applicant_id = '" . $id_escaped . "' LIMIT 1");
$app = $result ? $result->fetch_assoc() : null;
if (!$app) {
    header('Location: manage_applicants.php');
    exit;
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>View Disability Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-top: 0;
            border-bottom: 2px solid #006400;
            padding-bottom: 12px;
        }
        .info-row {
            margin-bottom: 20px;
        }
        .label {
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
            display: block;
        }
        .value {
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            color: #333;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            margin-top: 6px;
        }
        .status-yes {
            background-color: #ffcccc;
            color: #c0392b;
        }
        .status-no {
            background-color: #ccffcc;
            color: #4CAF50;
        }
        .button-group {
            margin-top: 24px;
            text-align: center;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back {
            background-color: #006400;
            color: white;
            margin-left: 8px;
        }
        .btn-back:hover {
            background-color: #004d00;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Disability Details - <?= htmlspecialchars($app['applicant_id']) ?></h2>
    
    <div class="info-row">
        <label class="label">Applicant Name</label>
        <div class="value"><?= htmlspecialchars($app['name'] ?? 'N/A') ?></div>
    </div>

    <div class="info-row">
        <label class="label">Has Disability</label>
        <div>
            <?php if (!empty($app['is_disabled']) && intval($app['is_disabled']) === 1): ?>
                <span class="status-badge status-yes">Yes</span>
            <?php else: ?>
                <span class="status-badge status-no">No</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($app['is_disabled']) && intval($app['is_disabled']) === 1): ?>
        <div class="info-row">
            <label class="label">Disability Details</label>
            <div class="value">
                <?php 
                    $detailsRaw = isset($app['disability_details']) ? $app['disability_details'] : '';
                    $details = trim((string)$detailsRaw);
                    echo ($details !== '' && $details !== null) ? htmlspecialchars($details) : '<em>No details provided</em>';
                ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="button-group">
        <button type="button" class="btn btn-back" onclick="window.history.back();">Back</button>
    </div>
</div>
</body>
</html>
