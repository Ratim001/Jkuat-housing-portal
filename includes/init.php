<?php
/**
 * includes/init.php
 * Purpose: Application bootstrap - session hardening, error reporting and helper initialization.
 * Author: repo automation / commit: security: session and cookie hardening
 */

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
