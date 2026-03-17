<?php
// End-to-end test script for post-forfeit request submission
require_once __DIR__ . '/includes/db.php';

echo "=== POST-FORFEIT REQUEST END-TO-END TEST ===\n\n";

// Test with applicant AP001
$test_applicant_id = 'AP001';
$test_reason = 'Test forfeit request - ' . date('Y-m-d H:i:s');
$test_attachment = '';

echo "Test Parameters:\n";
echo "- Applicant: $test_applicant_id\n";
echo "- Reason: $test_reason\n";
echo "- Attachment: (empty)\n\n";

// Step 1: Eligibility check
echo "STEP 1: Checking eligibility...\n";
$has_ballot = false;

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM balloting WHERE applicant_id = ?");
if ($stmt) {
    $stmt->bind_param('s', $test_applicant_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && (int)$r['cnt'] > 0) {
        $has_ballot = true;
        echo "  ✓ Has ballot participation\n";
    }
}

if (!$has_ballot) {
    $stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE applicant_id = ? AND LOWER(COALESCE(status,'')) IN ('applied','pending','approved','won','allocated','tenant')");
    if ($stmt2) {
        $stmt2->bind_param('s', $test_applicant_id);
        $stmt2->execute();
        $r2 = $stmt2->get_result()->fetch_assoc();
        if ($r2 && (int)$r2['cnt'] > 0) {
            $has_ballot = true;
            echo "  ✓ Has active applications\n";
        }
    }
}

if (!$has_ballot) {
    echo "  ✗ NOT ELIGIBLE - test cannot proceed\n";
    exit;
}

echo "\n";

// Step 2: Generate request ID
echo "STEP 2: Generating request ID...\n";
$date = date('Ymd');
$prefix = 'PFRT-' . $date . '-';  // Using PFRT to distinguish test IDs
$like = $conn->real_escape_string($prefix) . '%';
$q = $conn->prepare("SELECT request_id FROM post_forfeit_requests WHERE request_id LIKE ? ORDER BY created_at DESC LIMIT 1");
$n = 1;
if ($q) {
    $q->bind_param('s', $like);
    $q->execute();
    $res = $q->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $last = $row['request_id'];
        $parts = explode('-', $last);
        $n = intval(end($parts)) + 1;
    }
}
$requestId = $prefix . str_pad($n, 3, '0', STR_PAD_LEFT);
echo "  Generated: $requestId\n\n";

// Step 3: Insert into database
echo "STEP 3: Inserting into database...\n";
$stmt = $conn->prepare("INSERT INTO post_forfeit_requests (request_id, applicant_id, reason, attachment, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
if (!$stmt) {
    echo "  ✗ Prepare failed: " . $conn->error . "\n";
    exit;
}

$att = $test_attachment === null ? '' : $test_attachment;
$stmt->bind_param('ssss', $requestId, $test_applicant_id, $test_reason, $att);

if ($stmt->execute()) {
    echo "  ✓ Insert successful\n";
    echo "  Request ID: $requestId\n";
    echo "  Inserted ID: " . $conn->insert_id . "\n\n";
} else {
    echo "  ✗ Execute failed: " . $stmt->error . "\n";
    exit;
}

// Step 4: Verify in database
echo "STEP 4: Verifying record in database...\n";
$verify = $conn->query("SELECT * FROM post_forfeit_requests WHERE request_id = '$requestId'");
if ($verify && $verify->num_rows > 0) {
    echo "  ✓ Record found\n";
    $row = $verify->fetch_assoc();
    echo "  Request ID: " . $row['request_id'] . "\n";
    echo "  Applicant: " . $row['applicant_id'] . "\n";
    echo "  Status: " . $row['status'] . "\n";
    echo "  Created: " . $row['created_at'] . "\n\n";
} else {
    echo "  ✗ Record NOT found in database\n";
    exit;
}

// Step 5: Check admin visibility
echo "STEP 5: Checking admin panel visibility...\n";
$admin_query = $conn->query("SELECT pfr.*, a.name AS applicant_name FROM post_forfeit_requests pfr LEFT JOIN applicants a ON pfr.applicant_id = a.applicant_id WHERE pfr.request_id = '$requestId'");
if ($admin_query && $admin_query->num_rows > 0) {
    echo "  ✓ Record visible in admin query\n";
    $admin_row = $admin_query->fetch_assoc();
    echo "  Display name: " . ($admin_row['applicant_name'] ?: $admin_row['applicant_id']) . "\n";
    echo "  Reason: " . substr($admin_row['reason'], 0, 50) . "...\n\n";
} else {
    echo "  ✗ Record NOT visible in admin query\n";
    exit;
}

// Final status
echo "=== TEST RESULT ===\n";
echo "✓ ALL TESTS PASSED\n";
echo "Post-forfeit request feature is working correctly!\n";

?>
