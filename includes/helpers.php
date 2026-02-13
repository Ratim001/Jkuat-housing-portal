<?php
/**
 * includes/helpers.php
 * Purpose: Common helper functions: safe_echo, safe_array_get, logs_write and Sentry stub.
 * Author: repo automation / commit: config: add helpers and logging
 */

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
    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $from = 'no-reply@' . (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');

    // Try PHPMailer if installed
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                // SMTP if configured
                if (getenv('SMTP_HOST')) {
                    $mail->isSMTP();
                    $mail->Host = getenv('SMTP_HOST');
                    $mail->Port = getenv('SMTP_PORT') ?: 587;
                    $mail->SMTPAuth = true;
                    $mail->Username = getenv('SMTP_USER');
                    $mail->Password = getenv('SMTP_PASS');
                    if (getenv('SMTP_SECURE')) $mail->SMTPSecure = getenv('SMTP_SECURE');
                }
                $mail->setFrom($from, 'JKUAT Housing');
                $mail->addAddress($to);
                $mail->Subject = $subject;
                if ($isHtml) $mail->isHTML(true);
                $mail->Body = $body;
                $mail->send();
                logs_write('info', "Email sent to $to via PHPMailer: $subject");
                return true;
            } catch (Exception $e) {
                logs_write('error', 'PHPMailer error: ' . $e->getMessage());
            }
        }
    }

    // Fallback to mail()
    $headers = "From: $from\r\n";
    if ($isHtml) $headers .= "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $sent = @mail($to, $subject, $body, $headers);
    if ($sent) {
        logs_write('info', "Email sent to $to via mail(): $subject");
        return true;
    }

    // Last resort: log email to file
    $emailLogDir = __DIR__ . '/../logs';
    if (!is_dir($emailLogDir)) @mkdir($emailLogDir, 0755, true);
    $logFile = $emailLogDir . '/emails.log';
    $logLine = date('c') . " EMAIL to $to -- Subject: $subject -- Body: $body\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    logs_write('warning', "Email logged for $to: $subject");
    return false;
}

// Sentry stub: if SENTRY_DSN provided, developer should install sentry/sdk via composer.
function init_sentry_if_present() {
    $dsn = getenv('SENTRY_DSN');
    if (!$dsn) return;
    // TODO: Add composer dependency "sentry/sdk" and initialize here.
    logs_write('info', 'SENTRY_DSN provided but Sentry SDK not installed - stubbed');
}
