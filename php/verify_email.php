<?php
/**
 * php/verify_email.php
 * Purpose: Verify an email verification token, mark applicant as verified.
 * Author: repo automation / commit: auth: implement email verification
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: applicantlogin.php?msg=' . urlencode('Invalid token'));
    exit;
}

$stmt = $conn->prepare('SELECT applicant_id FROM applicants WHERE email_verification_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $applicant_id = $row['applicant_id'];
    $u = $conn->prepare('UPDATE applicants SET is_email_verified = 1, email_verification_token = NULL WHERE applicant_id = ?');
    $u->bind_param('s', $applicant_id);
    if ($u->execute()) {
        logs_write('info', 'Email verified for applicant ' . $applicant_id);
        header('Location: applicantlogin.php?msg=' . urlencode('Email verified successfully. You can now log in.'));
        exit;
    }
}

header('Location: applicantlogin.php?msg=' . urlencode('Invalid or expired token'));
exit;
