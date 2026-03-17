<?php
/**
 * Migration: Fix applications table status enum values
 * Date: 2026-02-13
 * Purpose: Update status column enum to match application requirements
 *          Old: enum('declined','accepted','pending','')
 *          New: enum('pending','approved','rejected','cancelled','won')
 */

if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db.php';
}

echo "=== Database Migration ===\n";
echo "Updating applications.status enum values...\n\n";

// Safety: later migrations change applications.status to VARCHAR.
// If the column is no longer an ENUM, do nothing (keeps the schema up-to-date).
$typeResult = $conn->query("SELECT DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'status'");
$typeRow = $typeResult ? $typeResult->fetch_assoc() : null;
$dataType = $typeRow['DATA_TYPE'] ?? null;
$columnType = $typeRow['COLUMN_TYPE'] ?? '';

if (!$dataType) {
    echo "✗ Error: applications.status column not found\n";
    exit(1);
}

if (strtolower($dataType) !== 'enum') {
    echo "✓ Skipping: applications.status is not ENUM (current: {$dataType})\n";
    echo "  This is expected if later migrations standardized it to VARCHAR.\n";
    echo "\n✓ Migration completed successfully\n";
    exit(0);
}

// Include all canonical values used by the codebase so updates/inserts won't produce
// invalid enum values: applied, pending, approved, rejected, cancelled, won,
// allocated, unsuccessful
$sql = "ALTER TABLE applications MODIFY COLUMN status ENUM('applied','pending','approved','rejected','cancelled','won','allocated','unsuccessful') DEFAULT 'pending'";

if ($conn->query($sql)) {
    echo "✓ Status column enum updated successfully\n";
    echo "  Old values: declined, accepted, pending, (empty)\n";
    echo "  New values: applied, pending, approved, rejected, cancelled, won, allocated, unsuccessful\n";
    
    // Set any empty or NULL status values to 'pending' as default
    // Normalize empty/NULL values to 'pending' and also map legacy variants to canonical values
    $update_sql = "UPDATE applications SET status = 'pending' WHERE status = '' OR status IS NULL";
    $conn->query("UPDATE applications SET status = 'unsuccessful' WHERE LOWER(status) IN ('not_successful','not successful')");
    $conn->query("UPDATE applications SET status = 'applied' WHERE LOWER(status) IN ('applied','apply')");
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
