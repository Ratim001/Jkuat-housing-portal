<?php
/**
 * includes/helpers.php
 * Purpose: Common helper functions: safe_echo, safe_array_get, logs_write and Sentry stub.
 * Author: repo automation / commit: config: add helpers and logging
 */

// Load environment variables from .env file if not already loaded
if (!getenv('SMTP_HOST')) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) continue;
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
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }
}

function safe_echo($value) {
    if ($value === null || $value === '') return '<span style="color:#888">N/A</span>';
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safe_array_get($array, $key, $default = '') {
    return is_array($array) && isset($array[$key]) ? $array[$key] : $default;
}

function logs_write($level, $message) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/app.log';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] [$level] $message\n";
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * send_email
 * Prefer PHPMailer (composer) if available, otherwise use mail() and log the email.
 */
function send_email($to, $subject, $body, $isHtml = false) {
    logs_write('info', "=== SEND_EMAIL CALLED === To: $to, Subject: $subject");
    
    // Load .env variables directly to ensure they're available
    $config = [];
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
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
    }
    
    logs_write('info', "Config loaded. SMTP_HOST: " . ($config['SMTP_HOST'] ?? '[EMPTY]'));
    
    $appUrl = rtrim(getenv('APP_URL') ?: ($config['APP_URL'] ?? 'http://localhost'), '/');
    $from = 'noreply@demomailtrap.co';  // Use verified Mailtrap domain
    logs_write('info', "From address: $from");

    // Try PHPMailer if installed
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        logs_write('info', 'Autoload file found, attempting PHPMailer...');
        require_once $autoload;
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                // SMTP if configured
                $smtpHost = getenv('SMTP_HOST') ?: ($config['SMTP_HOST'] ?? null);
                logs_write('info', "SMTP_HOST value: " . ($smtpHost ?: '[NONE]'));
                
                if ($smtpHost) {
                    logs_write('info', 'Configuring SMTP connection...');
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->Port = (int)(getenv('SMTP_PORT') ?: ($config['SMTP_PORT'] ?? 587));
                    $mail->SMTPAuth = true;
                    $mail->Username = getenv('SMTP_USER') ?: ($config['SMTP_USER'] ?? '');
                    $mail->Password = getenv('SMTP_PASS') ?: ($config['SMTP_PASS'] ?? '');
                    $smtpSecure = getenv('SMTP_SECURE') ?: ($config['SMTP_SECURE'] ?? null);
                    logs_write('info', "SMTP Config - Host: {$mail->Host}, Port: {$mail->Port}, User: {$mail->Username}, Secure: " . ($smtpSecure ?: '[NONE]'));
                    if ($smtpSecure) {
                        $mail->SMTPSecure = $smtpSecure;
                    }
                } else {
                    logs_write('warning', 'No SMTP_HOST configured, using local mail() fallback');
                }
                
                $mail->setFrom($from, 'JKUAT Housing');
                $mail->addAddress($to);
                $mail->Subject = $subject;
                if ($isHtml) {
                    $mail->isHTML(true);
                }
                $mail->Body = $body;
                
                logs_write('info', 'Attempting to send email via PHPMailer...');
                $mail->send();
                logs_write('info', "✓ Email sent to $to via PHPMailer: $subject");
                return true;
            } catch (Exception $e) {
                logs_write('error', 'PHPMailer exception: ' . $e->getMessage());
                logs_write('error', 'Exception type: ' . get_class($e));
            }
        } else {
            logs_write('warning', 'PHPMailer class not found');
        }
    } else {
        logs_write('warning', 'Autoload file not found at: ' . $autoload);
    }

    // Fallback to mail()
    logs_write('info', 'Falling back to mail() function...');
    $headers = "From: $from\r\n";
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    }
    $sent = @mail($to, $subject, $body, $headers);
    if ($sent) {
        logs_write('info', "✓ Email sent to $to via mail(): $subject");
        return true;
    }

    // Last resort: log email to file
    logs_write('warning', 'mail() failed, logging email to file...');
    $emailLogDir = __DIR__ . '/../logs';
    if (!is_dir($emailLogDir)) @mkdir($emailLogDir, 0755, true);
    $logFile = $emailLogDir . '/emails.log';
    $logLine = date('c') . " EMAIL to $to -- Subject: $subject -- Body: $body\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    logs_write('warning', "Email logged to file for $to: $subject");
    return false;
}

// Sentry stub: if SENTRY_DSN provided, developer should install sentry/sdk via composer.
function init_sentry_if_present() {
    $dsn = getenv('SENTRY_DSN');
    if (!$dsn) return;
    // TODO: Add composer dependency "sentry/sdk" and initialize here.
    logs_write('info', 'SENTRY_DSN provided but Sentry SDK not installed - stubbed');
}
