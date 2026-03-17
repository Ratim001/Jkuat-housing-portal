<?php
// Diagnostic script to check applicant eligibility for post-forfeit requests
require_once __DIR__ . '/includes/db.php';

$applicant_id = 'AP001';  // The applicant from the email

echo "=== Checking eligibility for applicant: $applicant_id ===\n\n";

// Check 1: Does applicant exist?
$app_check = $conn->query("SELECT * FROM applicants WHERE applicant_id = '$applicant_id' LIMIT 1");
if ($app_check && $app_check->num_rows > 0) {
    $app = $app_check->fetch_assoc();
    echo "✓ Applicant found\n";
    echo "  Name: " . ($app['name'] ?? '-') . "\n";
    echo "  Email: " . ($app['email'] ?? '-') . "\n";
} else {
    echo "✗ Applicant NOT found\n";
    exit;
}

echo "\n";

// Check 2: Does applicant have ballot records?
$ballot_check = $conn->query("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = '$applicant_id'");
if ($ballot_check) {
    $row = $ballot_check->fetch_assoc();
    $ballot_count = $row['cnt'] ?? 0;
    if ($ballot_count > 0) {
        echo "✓ Applicant has ballot participation ($ballot_count records)\n";
    } else {
        echo "✗ Applicant has NO ballot records\n";
    }
}

echo "\n";

// Check 3: Does applicant have active applications?
$app_status_check = $conn->query("SELECT COUNT(*) as cnt FROM applications WHERE applicant_id = '$applicant_id' AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved','won','allocated','tenant')");
if ($app_status_check) {
    $row = $app_status_check->fetch_assoc();
    $active_app_count = $row['cnt'] ?? 0;
    if ($active_app_count > 0) {
        echo "✓ Applicant has active applications ($active_app_count records)\n";
    } else {
        echo "✗ Applicant has NO active applications\n";
    }
}

echo "\n";

// Check 4: Overall eligibility
$is_eligible = ($ballot_count > 0) || ($active_app_count > 0);
if ($is_eligible) {
    echo "► CONCLUSION: Applicant IS ELIGIBLE for post-forfeit requests\n";
    echo "  The forfeit form should be visible to this applicant\n";
} else {
    echo "► CONCLUSION: Applicant is NOT ELIGIBLE\n";
    echo "  They need either:\n";
    echo "  - Ballot participation records in the balloting table, OR\n";
    echo "  - An active application with status in: applied, pending, approved, won, allocated, tenant\n";
}

echo "\n";

// Check 5: Show any existing forfeit requests for this applicant
$forfeit_check = $conn->query("SELECT * FROM post_forfeit_requests WHERE applicant_id = '$applicant_id' ORDER BY created_at DESC");
if ($forfeit_check && $forfeit_check->num_rows > 0) {
    echo "=== Existing post-forfeit requests for $applicant_id ===\n";
    while ($row = $forfeit_check->fetch_assoc()) {
        echo "ID: " . $row['request_id'] . " | Status: " . $row['status'] . " | Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "No post-forfeit requests found for $applicant_id\n";
}

?>
