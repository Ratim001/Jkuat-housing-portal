<?php
// Redirect to the full registration form (applicant_profile.php) and ensure
// any temporary registration-related session state is cleared so the page
// shows the registration inputs rather than a profile-completion view.
require_once '../includes/init.php';

// Clear any leftover registration/profile session flags
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['applicant_id'], $_SESSION['username'], $_SESSION['profile_incomplete']);

// Send user to the applicant profile page which contains the full
// registration form for new users (no session applicant_id present).
header('Location: applicant_profile.php');
exit;
