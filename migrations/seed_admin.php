<?php
/**
 * migrations/seed_admin.php
 * Purpose: Insert a single admin user into `users` table. Reads DB credentials from environment variables.
 * Author: repo automation / commit: migrations: add seed_admin
 *
 * Usage: php migrations/seed_admin.php
 * Ensure environment variables DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS are set.
 */

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'staff_housing';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connection failed: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

$adminId = 'U001';
$username = 'admin';
$email = 'admin@example.com';
$role = 'CS Admin';
$status = 'Active';

// Generate a random password (do NOT commit or store this in source control).
$rawPassword = bin2hex(random_bytes(8)); // 16 hex chars (~128 bits)
$passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    echo "Admin user already exists.\n";
    exit(0);
}

$insert = $mysqli->prepare("INSERT INTO users (user_id, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
$insert->bind_param('ssssss', $adminId, $username, $email, $passwordHash, $role, $status);
if ($insert->execute()) {
    echo "Admin user inserted (username: admin).\n";
    echo "Generated password (store securely): $rawPassword\n";
    echo "IMPORTANT: Save this password securely and change it after first login. This script does NOT store plaintext passwords in source.\n";
} else {
    fwrite(STDERR, "Insert failed: " . $mysqli->error . PHP_EOL);
    exit(1);
}

$mysqli->close();
