<?php
/**
 * test_email.php
 * Quick test script to verify Gmail SMTP configuration
 * Access from browser: http://localhost/jkuat-housing-portal.../test_email.php
 */

// Load initialization
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/db.php';

echo "<h2>📧 Email Configuration Test</h2>";
echo "<pre>";

// Check if .env exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "✓ .env file found\n";
} else {
    echo "✗ .env file NOT found\n";
    exit;
}

// Load .env
$config = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if (strpos($line, '#') === 0 || empty($line)) continue;
    if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $config[$key] = $value;
    }
}

echo "\n=== SMTP Configuration ===\n";
echo "SMTP_HOST: " . ($config['SMTP_HOST'] ?? 'NOT SET') . "\n";
echo "SMTP_PORT: " . ($config['SMTP_PORT'] ?? 'NOT SET') . "\n";
echo "SMTP_USER: " . ($config['SMTP_USER'] ?? 'NOT SET') . "\n";
echo "SMTP_PASS: " . (isset($config['SMTP_PASS']) ? '***hidden***' : 'NOT SET') . "\n";
echo "SMTP_SECURE: " . ($config['SMTP_SECURE'] ?? 'NOT SET') . "\n";

// Check PHPMailer
echo "\n=== PHPMailer Status ===\n";
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    echo "✓ vendor/autoload.php found\n";
    require_once $autoload;
    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        echo "✓ PHPMailer class available\n";
    } else {
        echo "✗ PHPMailer class NOT found\n";
    }
} else {
    echo "✗ vendor/autoload.php NOT found\n";
}

// Test email send to test addresses
echo "\n=== Sending Test Emails ===\n";
$testEmails = [
    'ratimboru@gmail.com',
    'mohamed.boru@students.jkuat.ac.ke'
];

foreach ($testEmails as $email) {
    echo "\nSending test email to: $email\n";
    
    $subject = 'JKUAT Housing Portal - Email Test';
    $body = "<h2>Email Configuration Test</h2>
             <p>If you received this email, your Gmail SMTP is working correctly!</p>
             <p>Date: " . date('Y-m-d H:i:s') . "</p>
             <p>Sender: isaak.mohamed@jkuat.ac.ke (HR admin)</p>";
    
    try {
        $result = send_email($email, $subject, $body, true);
        if ($result) {
            echo "✓ Email sent successfully to $email\n";
        } else {
            echo "✗ Email failed for $email (check logs/emails.log)\n";
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Email Logs ===\n";
if (file_exists(__DIR__ . '/logs/emails.log')) {
    $logContent = file_get_contents(__DIR__ . '/logs/emails.log');
    echo "Last 10 entries from emails.log:\n";
    $lines = array_slice(explode("\n", $logContent), -10);
    foreach ($lines as $line) {
        if (!empty($line)) {
            echo $line . "\n";
        }
    }
} else {
    echo "No emails.log file yet\n";
}

echo "\n=== app.log Recent Entries ===\n";
if (file_exists(__DIR__ . '/logs/app.log')) {
    $logContent = file_get_contents(__DIR__ . '/logs/app.log');
    $lines = array_slice(explode("\n", $logContent), -20);
    foreach ($lines as $line) {
        if (!empty($line) && stripos($line, 'send_email') !== false) {
            echo $line . "\n";
        }
    }
} else {
    echo "No app.log file yet\n";
}

echo "</pre>";

// Link back
echo "<hr>";
echo "<a href='/jkuat-housing-portal-20260204T072537Z-3-001/jkuat-housing-portal/'>← Back to Portal</a>";
?>
