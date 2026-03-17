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

// Inject client-side pagination-length script before closing </body> on pages
// We use output buffering to safely append the script tag into HTML pages.
if (php_sapi_name() !== 'cli') {
    if (!defined('JKUAT_HTML_INJECT')) {
        define('JKUAT_HTML_INJECT', 1);
    }
    ob_start(function($buffer){
        // Only inject into HTML responses. Never mutate JSON/API/text responses.
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
        
        // Don't inject global-ui.js (back button) on login/registration pages
        $currentFile = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $isLoginPage = in_array($currentFile, ['applicantlogin.php', 'login.php', 'register.php']);
        
        if (!$isLoginPage && stripos($buffer, 'global-ui.js') === false) {
            $scriptTag .= "<script src=\"../js/global-ui.js\"></script>\n";
        }

        if ($scriptTag === '') {
            return $buffer;
        }
        if (stripos($buffer, '</body>') !== false) {
            return str_ireplace('</body>', $scriptTag . '</body>', $buffer);
        }
        // If no body tag found, append at end
        return $buffer . $scriptTag;
    });
}
