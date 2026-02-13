<?php
/**
 * includes/email.php
 * Purpose: Build email subjects and bodies for verification and password reset.
 */

function build_verification_email(string $name, string $link): array {
    $subject = 'Please verify your email address';
    $html = "<p>Hello " . htmlspecialchars($name) . ",</p>\n" .
            "<p>Please verify your email by clicking the link below:</p>\n" .
            "<p><a href=\"" . htmlspecialchars($link) . "\">Verify email</a></p>\n" .
            "<p>This link will expire in 48 hours.</p>\n";
    $plain = "Hello $name\n\nPlease verify your email by visiting: $link\n\nThis link will expire in 48 hours.";
    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

function build_password_reset_email(string $name, string $link): array {
    $subject = 'Password reset instructions';
    $html = "<p>Hello " . htmlspecialchars($name) . ",</p>\n" .
            "<p>To reset your password click the link below (valid 1 hour):</p>\n" .
            "<p><a href=\"" . htmlspecialchars($link) . "\">Reset my password</a></p>\n";
    $plain = "Hello $name\n\nTo reset your password, visit: $link\n\nThis link is valid for 1 hour.";
    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}
