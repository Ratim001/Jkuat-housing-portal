<?php
require_once '../includes/init.php';
require_once '../includes/db.php';
require_once '../includes/validation.php';

// Check if user is logged in as applicant
if (!isset($_SESSION['applicant_id'])) {
    header('Location: applicantlogin.php');
    exit;
}

$applicant_id = $_SESSION['applicant_id'];
$error = '';
$success = '';

// Fetch current profile information
$stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("s", $applicant_id);
$stmt->execute();
$result = $stmt->get_result();
$applicant = $result->fetch_assoc();

// Check if profile is already complete
$profile_incomplete = empty($applicant['name']) || empty($applicant['email']) || empty($applicant['contact']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
    $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');

    // Validation
    // Validation using shared helpers
    if (!validate_name($name) || !validate_email($email) || !validate_phone($contact) || !validate_phone($next_of_kin_contact)) {
        $error = 'Please enter valid values for all required fields.';
    } else {
        // Check if email already exists for another applicant
        $check_stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE email = ? AND applicant_id != ?");
        $check_stmt->bind_param("ss", $email, $applicant_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'Email address is already in use by another applicant.';
        } else {
            // Update profile using prepared statements (normalized next_of_kin_* columns only)
            $update_stmt = $conn->prepare("UPDATE applicants SET name = ?, email = ?, contact = ?, next_of_kin_name = ?, next_of_kin_contact = ? WHERE applicant_id = ?");
            $update_stmt->bind_param("ssssss", $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $applicant_id);

            if ($update_stmt && $update_stmt->execute()) {
                $success = 'Profile updated successfully!';
                // Refresh applicant data
                $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
                $stmt->bind_param("s", $applicant_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $applicant = $result->fetch_assoc();
                $profile_incomplete = false;
            } else {
                $error = 'An error occurred while updating your profile. Please try again.';
            }
        }
    }
}

// Redirect to applicants page if coming back
$redirect_to = $_GET['redirect'] ?? 'applicants.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Profile | JKUAT Staff Housing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { 
            height: 100%; 
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        
        .container {
            display: flex;
            height: 100vh;
        }

        .left-panel {
            flex: 1;
            background: url('../images/jkuat-bg.jpg') no-repeat center center;
            background-size: cover;
            background-color: #004225;
        }

        .right-panel {
            flex: 1;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }

        .card {
            width: 100%;
            max-width: 500px;
            background: white;
            display: flex;
            flex-direction: column;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 40px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header img {
            width: 100px;
            margin-bottom: 15px;
        }

        .form-header h1 {
            color: #006400;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 14px;
        }

        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #006400;
            box-shadow: 0 0 5px rgba(0, 100, 0, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .required-note {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-submit {
            background-color: #006400;
            color: white;
        }

        .btn-submit:hover {
            background-color: #005a00;
        }

        .btn-cancel {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-cancel:hover {
            background-color: #d0d0d0;
        }

        .info-box {
            background-color: #f0f8ff;
            border-left: 4px solid #006400;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
        }

        .info-box strong {
            color: #006400;
        }

        .profile-status {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
            color: #999;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                padding: 10px;
            }

            .card {
                padding: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="card">
            <div class="form-header">
                <img src="../images/2logo.png" alt="JKUAT Logo">
                <h1>Complete Your Profile</h1>
                <p>Please provide your personal details to complete your registration</p>
            </div>

            <?php if ($profile_incomplete): ?>
                <div class="info-box">
                    <strong>⚠️ Important:</strong> Your profile is incomplete. Please fill in all required information to proceed.
                </div>
            <?php else: ?>
                <div class="profile-status">Profile: <span style="color: #006400; font-weight: 600;">✓ Complete</span></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($applicant['name'] ?? '') ?>" required>
                    <div class="required-note">Your full legal name as it appears in official documents</div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($applicant['email'] ?? '') ?>" required>
                    <div class="required-note">We'll use this to send important notifications</div>
                </div>

                <div class="form-group">
                    <label for="contact">Phone Number *</label>
                    <input type="tel" id="contact" name="contact" value="<?= htmlspecialchars($applicant['contact'] ?? '') ?>" placeholder="+254..." required>
                    <div class="required-note">Include country code (e.g., +254)</div>
                </div>

                <div class="form-group">
                    <label for="next_of_kin_name">Next of Kin Name *</label>
                    <input type="text" id="next_of_kin_name" name="next_of_kin_name" value="<?= htmlspecialchars($applicant['next_of_kin_name'] ?? '') ?>" required>
                    <div class="required-note">Full name of your emergency contact</div>
                </div>

                <div class="form-group">
                    <label for="next_of_kin_contact">Next of Kin Phone Number *</label>
                    <input type="tel" id="next_of_kin_contact" name="next_of_kin_contact" value="<?= htmlspecialchars($applicant['next_of_kin_contact'] ?? '') ?>" placeholder="+254..." required>
                    <div class="required-note">Phone number of your emergency contact</div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-submit">Save Profile</button>
                    <?php if (!$profile_incomplete): ?>
                        <button type="button" class="btn btn-cancel" onclick="window.location.href='<?= htmlspecialchars($redirect_to) ?>';">Back</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
