<?php
/**
 * includes/email.php
 * Purpose: Build email subjects and bodies for verification and password reset.
 */

/**
 * Build a standard email wrapper with JKUAT logo and motto.
 */
function build_email_wrapper(string $bodyHtml): string {
    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $logoUrl = $appUrl . '/images/logo.png';
    $motto = 'Technology for Development';
    $header = "<div style=\"font-family: Arial, Helvetica, sans-serif; max-width:700px;margin:0 auto;border:1px solid #e6e6e6;\">"
        . "<div style=\"background:#ffffff;padding:18px 20px;display:flex;align-items:center;gap:12px;\">"
        . "<img src=\"" . htmlspecialchars($logoUrl) . "\" alt=\"JKUAT\" style=\"height:64px;object-fit:contain;\">"
        . "<div style=\"font-size:18px;color:#064b1a;font-weight:700;\">JKUAT Housing Portal</div>"
        . "</div>";
    $mottoBar = "<div style=\"background:#f6f9f6;padding:10px 20px;color:#333;\">" . htmlspecialchars($motto) . "</div>";
    $footer = "<div style=\"padding:12px 20px;font-size:12px;color:#666;background:#fff;\">"
        . "<div>To manage your notifications, sign in to the portal.</div>"
        . "</div></div>";

    return $header . $mottoBar . "<div style=\"padding:18px 20px;\">" . $bodyHtml . "</div>" . $footer;
}

function build_verification_email(string $name, string $link): array {
    $subject = 'Please verify your email address';
    $body = "<p>Hello " . htmlspecialchars($name) . ",</p>\n" .
        "<p>Please verify your email by clicking the link below:</p>\n" .
        "<p><a href=\"" . htmlspecialchars($link) . "\">Verify email</a></p>\n" .
        "<p>This link will expire in 48 hours.</p>\n";
    $html = build_email_wrapper($body);
    $plain = "Hello $name\n\nPlease verify your email by visiting: $link\n\nThis link will expire in 48 hours.";
    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

function build_password_reset_email(string $name, string $link): array {
    $subject = 'Password reset instructions';
    $body = "<p>Hello " . htmlspecialchars($name) . ",</p>\n" .
        "<p>To reset your password click the link below (valid 1 hour):</p>\n" .
        "<p><a href=\"" . htmlspecialchars($link) . "\">Reset my password</a></p>\n";
    $html = build_email_wrapper($body);
    $plain = "Hello $name\n\nTo reset your password, visit: $link\n\nThis link is valid for 1 hour.";
    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}
