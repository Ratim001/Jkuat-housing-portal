<?php
/**
 * Migration: Fix applications table status enum values
 * Date: 2026-02-13
 * Purpose: Update status column enum to match application requirements
 *          Old: enum('declined','accepted','pending','')
 *          New: enum('pending','approved','rejected','cancelled','won')
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== Database Migration ===\n";
echo "Updating applications.status enum values...\n\n";

$sql = "ALTER TABLE applications MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'cancelled', 'won') DEFAULT 'pending'";

if ($conn->query($sql)) {
    echo "✓ Status column enum updated successfully\n";
    echo "  Old values: declined, accepted, pending, (empty)\n";
    echo "  New values: pending, approved, rejected, cancelled, won\n";
    
    // Set any empty or NULL status values to 'pending' as default
    $update_sql = "UPDATE applications SET status = 'pending' WHERE status = '' OR status IS NULL";
    if ($conn->query($update_sql)) {
        echo "\n✓ Default values set: empty/NULL statuses set to 'pending'\n";
    }
} else {
    echo "✗ Error: " . $conn->error . "\n";
    exit(1);
}

// Verify
echo "\n=== Verification ===\n";
$result = $conn->query("DESCRIBE applications");
while($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'status') {
        echo "Status column type: " . $row['Type'] . "\n";
        echo "Status column default: " . ($row['Default'] ?? '(none)') . "\n";
    }
}

// Show summary
$count_result = $conn->query("SELECT COUNT(*) as total, COUNT(NULLIF(status, '')) as with_status FROM applications");
$row = $count_result->fetch_assoc();
echo "\n✓ Applications table: " . $row['total'] . " total records, " . $row['with_status'] . " with status\n";

echo "\n✓ Migration completed successfully\n";
?>
