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

// Check if a column exists in a table (returns bool)
function column_exists_db($conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $schemaRes = $conn->query("SELECT DATABASE() as db");
    $schema = $schemaRes ? $schemaRes->fetch_assoc()['db'] : null;
    if (!$schema) return false;
    $q = $conn->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->bind_param('sss', $schema, $t, $c);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return ($r && $r['cnt'] > 0);
}

// Insert a notification safely; uses `title` column when present
function notify_insert_safe($conn, $notificationId, $adminId, $recipientType, $recipientId, $message, $dateSent, $status = 'unread', $title = null) {
    static $hasTitle = null;
    if ($hasTitle === null) {
        $hasTitle = column_exists_db($conn, 'notifications', 'title');
    }

    // Ensure the provided adminId exists in the `users` table; if not, fall back to 'system'.
    $checkStmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('s', $adminId);
        $checkStmt->execute();
        $r = $checkStmt->get_result();
        $exists = (bool) ($r && $r->fetch_assoc());
        $checkStmt->close();
        if (!$exists) {
            $systemId = 'system';
            // create system user if missing
            $c2 = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
            if ($c2) {
                $c2->bind_param('s', $systemId);
                $c2->execute();
                $r2 = $c2->get_result();
                $sysExists = (bool) ($r2 && $r2->fetch_assoc());
                $c2->close();
                if (!$sysExists) {
                    $now = date('Y-m-d H:i:s');
                    $insSql = "INSERT INTO users (user_id, username, name, email, role, date_created, status) VALUES (?, ?, ?, ?, 'system', ?, 'active')";
                    $ins = $conn->prepare($insSql);
                    if ($ins) {
                        $ins->bind_param('sssss', $systemId, $systemId, $systemId, $systemId, $now);
                        if (!$ins->execute()) {
                            logs_write('error', 'Failed to create system user in notify_insert_safe: ' . $ins->error);
                        }
                        $ins->close();
                    } else {
                        logs_write('error', 'Prepare failed creating system user in notify_insert_safe: ' . $conn->error);
                    }
                }
            }
            $adminId = $systemId;
        }
    }

    $msgEsc = mysqli_real_escape_string($conn, $message);
    $notificationIdEsc = $conn->real_escape_string($notificationId);
    $adminIdEsc = $conn->real_escape_string($adminId);
    $recipientTypeEsc = $conn->real_escape_string($recipientType);
    $recipientIdEsc = $conn->real_escape_string($recipientId);
    $dateSentEsc = $conn->real_escape_string($dateSent);
    $statusEsc = $conn->real_escape_string($status);

    if ($hasTitle && $title !== null) {
        $titleEsc = $conn->real_escape_string($title);
        $sql = "INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, title, message, date_sent, date_received, status) VALUES ('{$notificationIdEsc}', '{$adminIdEsc}', '{$recipientTypeEsc}', '{$recipientIdEsc}', '{$titleEsc}', '{$msgEsc}', '{$dateSentEsc}', '{$dateSentEsc}', '{$statusEsc}')";
    } else {
        $sql = "INSERT INTO notifications (notification_id, user_id, recipient_type, recipient_id, message, date_sent, date_received, status) VALUES ('{$notificationIdEsc}', '{$adminIdEsc}', '{$recipientTypeEsc}', '{$recipientIdEsc}', '{$msgEsc}', '{$dateSentEsc}', '{$dateSentEsc}', '{$statusEsc}')";
    }
    return $conn->query($sql);
}

/**
 * notify_and_email
 * Inserts a notification into the database (if table exists) and attempts to send an email.
 * Parameters:
 *  - $conn: mysqli connection
 *  - $recipientType: 'applicant'|'tenant'|'admin' etc
 *  - $recipientId: id corresponding to recipientType (applicant_id, tenant_id, or literal 'admin')
 *  - $email: recipient email address (may be null)
 *  - $subject: email subject
 *  - $htmlBody: HTML content for the email (string)
 *  - $title: optional short title to store in notifications.title when present
 */
function notify_and_email($conn, $recipientType, $recipientId, $email, $subject, $htmlBody, $title = null) {
    $adminId = $_SESSION['user_id'] ?? 'system';
    // Ensure the chosen adminId exists in `users`. If it doesn't, fall back to a dedicated `system` user.
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = (bool) ($res && $res->fetch_assoc());
        $stmt->close();
        if (!$exists) {
            // Ensure a `system` user exists; create it if missing
            $systemId = 'system';
            $stmt2 = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('s', $systemId);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $sysExists = (bool) ($res2 && $res2->fetch_assoc());
                $stmt2->close();
                if (!$sysExists) {
                    $now = date('Y-m-d H:i:s');
                    // Insert a minimal `system` user record. Use matching placeholders and bindings.
                    $insSql = "INSERT INTO users (user_id, username, name, email, role, date_created, status) VALUES (?, ?, ?, ?, 'system', ?, 'active')";
                    $ins = $conn->prepare($insSql);
                    if ($ins) {
                        $ins->bind_param('sssss', $systemId, $systemId, $systemId, $systemId, $now);
                        if (!$ins->execute()) {
                            logs_write('error', 'Failed to create system user: ' . $ins->error);
                        }
                        $ins->close();
                    } else {
                        logs_write('error', 'Prepare failed when creating system user: ' . $conn->error);
                    }
                }
            }
            $adminId = $systemId;
        }
    }
    $dateSent = date('Y-m-d H:i:s');
    $notificationId = uniqid('NT');
    // Fallback message: strip tags for notification body
    $message = trim(strip_tags($htmlBody));
    // Insert notification if notifications table exists (helper handles missing title column)
    if (function_exists('notify_insert_safe')) {
        notify_insert_safe($conn, $notificationId, $adminId, $recipientType, $recipientId, $message, $dateSent, 'unread', $title);
    }

    // Send email if an email address is provided
    $sent = false;
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Try to send HTML email; send_email already logs failures and returns bool
        try {
            $sent = send_email($email, $subject, $htmlBody, true);
        } catch (Exception $e) {
            logs_write('error', 'notify_and_email send_email failed: ' . $e->getMessage());
            $sent = false;
        }
    }

    return $sent;
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
    $from = getenv('SMTP_USER') ?: ($config['SMTP_USER'] ?? 'isaak.mohamed@jkuat.ac.ke');  // Gmail sender address
    $fromName = 'HR admin';  // Sender's display name
    logs_write('info', "From address: $from (Display: $fromName)");

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
                // Ensure UTF-8 charset so characters like em-dash render correctly
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'quoted-printable';
                $mail->setFrom($from, $fromName);
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

/**
 * get_tenant_for_applicant
 * Returns associative tenant row for given applicant_id or null when none.
 */
function get_tenant_for_applicant($conn, $applicant_id) {
    if (empty($applicant_id) || !$conn) return null;
    $stmt = $conn->prepare("SELECT tenant_id, applicant_id, house_no, move_in_date, move_out_date, status FROM tenants WHERE applicant_id = ? AND status = 'active' LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $applicant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * ensure_session_tenant
 * Convenience: set $_SESSION['tenant_id'] if applicant has an active tenant record.
 */
function ensure_session_tenant($conn) {
    if (empty($_SESSION['applicant_id'])) return null;
    if (!empty($_SESSION['tenant_id'])) return $_SESSION['tenant_id'];
    $t = get_tenant_for_applicant($conn, $_SESSION['applicant_id']);
    if ($t && !empty($t['tenant_id'])) {
        $_SESSION['tenant_id'] = $t['tenant_id'];
        return $_SESSION['tenant_id'];
    }
    return null;
}
