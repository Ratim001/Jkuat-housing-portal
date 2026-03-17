<?php
require_once 'includes/db.php';

echo "Running migration: 2026-03-11_add_disability_details.sql\n";

$sql = file_get_contents('migrations/2026-03-11_add_disability_details.sql');

if ($conn->multi_query($sql)) {
    while ($conn->next_result()) {
        // Process all results
    }
    echo "✓ Migration completed successfully!\n";
    echo "✓ disability_details column added to applicants table.\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}
