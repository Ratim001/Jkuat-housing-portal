<?php
/**
 * Migration runner for this project.
 *
 * Usage:
 *  php run_migrations.php
 *  php run_migrations.php --status
 *  php run_migrations.php 2026-02-13_add_notifications_title.sql
 *  php run_migrations.php 2026-02-13_fix_applications_status_enum.php
 *
 * Notes:
 * - Runs both .sql and .php migrations.
 * - Tracks applied migrations in schema_migrations (so "up-to-date" is repeatable).
 * - IMPORTANT: Backup your DB before running migrations.
 */

require_once __DIR__ . '/../includes/db.php';

if (!isset($conn) && isset($mysqli)) {
    $conn = $mysqli;
}

if (!isset($conn)) {
    echo "Unable to find database connection via includes/db.php\n";
    exit(1);
}

function ensure_schema_migrations_table(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  migration VARCHAR(255) NOT NULL UNIQUE,\n"
        . "  checksum CHAR(64) NOT NULL,\n"
        . "  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sql)) {
        echo "Failed to ensure schema_migrations table: ({$conn->errno}) {$conn->error}\n";
        exit(1);
    }
}

function migration_checksum(string $filePath): string {
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        return '';
    }
    return hash('sha256', $contents);
}

function get_applied_migrations(mysqli $conn): array {
    $applied = [];
    $result = $conn->query("SELECT migration, checksum FROM schema_migrations");
    if (!$result) {
        return $applied;
    }
    while ($row = $result->fetch_assoc()) {
        $applied[$row['migration']] = $row['checksum'];
    }
    $result->free();
    return $applied;
}

function record_migration(mysqli $conn, string $migration, string $checksum): void {
    $stmt = $conn->prepare(
        "INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?) "
        . "ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = CURRENT_TIMESTAMP"
    );
    if (!$stmt) {
        echo "Failed to prepare schema_migrations insert: ({$conn->errno}) {$conn->error}\n";
        exit(1);
    }
    $stmt->bind_param('ss', $migration, $checksum);
    if (!$stmt->execute()) {
        echo "Failed to record migration {$migration}: ({$stmt->errno}) {$stmt->error}\n";
        $stmt->close();
        exit(1);
    }
    $stmt->close();
}

function run_sql_migration(mysqli $conn, string $filePath): void {
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        echo "  Failed to read file.\n";
        exit(1);
    }

    if (!$conn->multi_query($sql)) {
        echo "  Migration failed: ({$conn->errno}) {$conn->error}\n";
        exit(1);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        echo "  Migration failed: ({$conn->errno}) {$conn->error}\n";
        exit(1);
    }
}

function run_php_migration(string $filePath, mysqli $conn): void {
    require $filePath;
}

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];

$statusOnly = in_array('--status', $argv, true);
$force = in_array('--force', $argv, true);

ensure_schema_migrations_table($conn);
$applied = get_applied_migrations($conn);

$files = [];

// Find a specific file arg (first non-flag argument)
$specific = null;
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--') === 0) {
        continue;
    }
    $specific = $arg;
    break;
}

if ($specific !== null) {
    $f = __DIR__ . '/' . $specific;
    if (!file_exists($f)) {
        echo "Migration file not found: {$f}\n";
        exit(1);
    }
    $files[] = $f;
} else {
    $sqlFiles = glob(__DIR__ . '/*.sql') ?: [];
    $phpFiles = glob(__DIR__ . '/*.php') ?: [];

    // Exclude the runner and seed script
    $phpFiles = array_values(array_filter($phpFiles, function ($p) {
        $base = basename($p);
        return $base !== 'run_migrations.php' && $base !== 'seed_admin.php';
    }));

    $files = array_merge($sqlFiles, $phpFiles);
    usort($files, function ($a, $b) {
        return strcmp(basename($a), basename($b));
    });
}

if (count($files) === 0) {
    echo "No migration files found in migrations/\n";
    exit(0);
}

$pending = [];
foreach ($files as $file) {
    $name = basename($file);
    $checksum = migration_checksum($file);
    if ($checksum === '') {
        echo "Failed to read migration for checksum: {$name}\n";
        exit(1);
    }

    if (isset($applied[$name])) {
        if ($applied[$name] !== $checksum && !$force) {
            echo "Migration already applied but file changed: {$name}\n";
            echo "  Recorded checksum: {$applied[$name]}\n";
            echo "  Current checksum:  {$checksum}\n";
            echo "If you really want to re-run it, pass --force (not recommended).\n";
            exit(1);
        }
        if (!$force) {
            continue;
        }
    }

    $pending[] = $file;
}

if ($statusOnly) {
    echo "Applied migrations: " . count($applied) . "\n";
    echo "Pending migrations: " . count($pending) . "\n";
    if (count($pending) > 0) {
        echo "Pending list:\n";
        foreach ($pending as $p) {
            echo " - " . basename($p) . "\n";
        }
    }
    exit(0);
}

if (count($pending) === 0) {
    echo "Up to date. No pending migrations.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $checksum = migration_checksum($file);

    echo "Running migration: {$name}\n";
    if ($ext === 'sql') {
        run_sql_migration($conn, $file);
    } elseif ($ext === 'php') {
        run_php_migration($file, $conn);
    } else {
        echo "  Skipping unknown migration type: {$name}\n";
        continue;
    }

    // Record only after successful run
    record_migration($conn, $name, $checksum);
    echo "  Success.\n";
}

echo "All done.\n";
