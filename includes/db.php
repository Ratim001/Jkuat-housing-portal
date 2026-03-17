<?php
/**
 * includes/db.php
 * Purpose: Database connection using environment variables with fallbacks.
 * Author: repo automation / commit: config: use env vars; add .env.example
 */

require_once __DIR__ . '/helpers.php';

// Some pages include db.php directly without includes/init.php.
// Inject shared client scripts in a safe, HTML-only way so UX stays consistent.
if (!defined('JKUAT_HTML_INJECT')) {
    define('JKUAT_HTML_INJECT', 1);
    if (php_sapi_name() !== 'cli') {
        ob_start(function ($buffer) {
            $looksLikeHtml = false;
            if (is_string($buffer)) {
                $b = ltrim($buffer);
                if (
                    stripos($b, '<!doctype') === 0 ||
                    stripos($b, '<html') === 0 ||
                    stripos($b, '<body') === 0 ||
                    stripos($b, '<head') === 0 ||
                    stripos($b, '<div') === 0 ||
                    stripos($b, '<span') === 0 ||
                    stripos($b, '<table') === 0 ||
                    stripos($b, '<p') === 0 ||
                    stripos($b, '<h1') === 0 ||
                    stripos($b, '<h2') === 0 ||
                    stripos($b, '<h3') === 0
                ) {
                    $looksLikeHtml = true;
                }
            }

            if (!$looksLikeHtml) {
                return $buffer;
            }

            // Avoid duplicate injection if scripts already exist in the HTML.
            $scriptTag = "";
            if (stripos($buffer, 'pagination-length.js') === false) {
                $scriptTag .= "\n<script src=\"../js/pagination-length.js\"></script>\n";
            }
            if (stripos($buffer, 'global-ui.js') === false) {
                $scriptTag .= "<script src=\"../js/global-ui.js\"></script>\n";
            }

            if ($scriptTag === '') {
                return $buffer;
            }
            if (stripos($buffer, '</body>') !== false) {
                return str_ireplace('</body>', $scriptTag . '</body>', $buffer);
            }
            return $buffer . $scriptTag;
        });
    }
}

// Load environment variables from .env file if not already loaded
if (!getenv('SMTP_HOST')) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if (strpos($line, '#') === 0) continue;
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                } elseif ((strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                putenv("$key=$value");
            }
        }
    }
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'staff_housing';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);

if ($conn->connect_error) {
    logs_write('error', 'DB connection failed: ' . $conn->connect_error);
    die('Database connection failed.');
}

// Set charset
$conn->set_charset('utf8mb4');
// Warn if environment variables are not set (using defaults)
foreach (['DB_HOST' => $dbHost, 'DB_PORT' => $dbPort, 'DB_USER' => $dbUser, 'DB_NAME' => $dbName] as $k => $v) {
    if (!getenv($k)) {
        logs_write('warning', "Environment variable $k not set; using default value: $v");
    }
}
?>
