<?php
/**
 * includes/init.php
 * Purpose: Application bootstrap - session hardening, error reporting and helper initialization.
 * Author: repo automation / commit: security: session and cookie hardening
 */

// Load environment variables from .env file
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

// Load helpers early
require_once __DIR__ . '/helpers.php';

// Environment
$appEnv = getenv('APP_ENV') ?: 'development';

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

// Session hardening
ini_set('session.cookie_httponly', '1');
// Only set secure when in production and APP_URL begins with https
if ($appEnv === 'production' || (stripos(getenv('APP_URL') ?: '', 'https://') === 0)) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.use_strict_mode', '1');
// PHP < 7.3 fallback for samesite via header if needed
session_set_cookie_params(['samesite' => 'Lax']);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) session_start();

// Initialize Sentry stub if configured
init_sentry_if_present();
