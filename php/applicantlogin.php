<?php 
require_once '../includes/init.php';
require_once '../includes/db.php';
require_once '../includes/validation.php';
require_once '../includes/db.php';

$error = '';
$success = '';

function getTenantId($conn, $applicant_id) {
    $stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE applicant_id = ?");
    $stmt->bind_param("s", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['tenant_id'];
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        // Collect and validate inputs
        $pf_number = trim($_POST['pf_number'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $passwordRaw = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $next_of_kin_name = trim($_POST['next_of_kin_name'] ?? '');
        $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');
        
        // Debug logging
        logs_write('info', "Registration attempt: pf=$pf_number, username=$username, name=$name, kin_name=$next_of_kin_name, kin_contact=$next_of_kin_contact");

        $validationErrors = [];
        if (!validate_name($name)) $validationErrors[] = 'Name must be at least 2 characters.';
        if (!validate_email($email)) $validationErrors[] = 'Invalid email address.';
        if (!validate_phone($contact)) $validationErrors[] = 'Invalid contact number.';
        if (!validate_phone($next_of_kin_contact)) $validationErrors[] = 'Invalid next-of-kin contact.';
        if (!validate_username($username)) $validationErrors[] = 'Username must be at least 3 characters and contain only letters, numbers, underscore, dot or hyphen.';
        if (!validate_password($passwordRaw)) $validationErrors[] = 'Password must be at least 8 characters.';

        if (count($validationErrors) === 0) {
            // Check uniqueness
            $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE pf_no = ? OR username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("sss", $pf_number, $username, $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $error = "PF Number, Username, or Email already exists.";
            } else {
                // Generate applicant id
                $query = $conn->query("SELECT applicant_id FROM applicants ORDER BY applicant_id DESC LIMIT 1");
                if ($row = $query->fetch_assoc()) {
                    $lastIdNum = (int)substr($row['applicant_id'], 1);
                    $newIdNum = $lastIdNum + 1;
                } else {
                    $newIdNum = 1;
                }
                $applicant_id = 'A' . str_pad($newIdNum, 3, '0', STR_PAD_LEFT);

                $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
                $emailToken = bin2hex(random_bytes(24));
                $status = 'Pending';

                $insert = $conn->prepare("INSERT INTO applicants (applicant_id, pf_no, username, password, name, email, contact, next_of_kin_name, next_of_kin_contact, is_email_verified, email_verification_token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
                $insert->bind_param("sssssssssss", $applicant_id, $pf_number, $username, $passwordHash, $name, $email, $contact, $next_of_kin_name, $next_of_kin_contact, $emailToken, $status);
                if ($insert->execute()) {
                    logs_write('info', "Registration successful: applicant_id=$applicant_id, kin_name=$next_of_kin_name, kin_contact=$next_of_kin_contact");
                    $success = "Registration created. A verification email has been sent; please verify before logging in.";

                    // Send verification email (fallback to log if SMTP not configured)
                    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
                    $verifyLink = $appUrl . '/php/verify_email.php?token=' . urlencode($emailToken);
                    $subject = 'Verify your email';
                    // Render HTML template if available
                    $tpl = __DIR__ . '/../templates/emails/verify_email.html';
                    if (file_exists($tpl)) {
                        $html = file_get_contents($tpl);
                        $bodyHtml = str_replace(['{{name}}','{{link}}'], [htmlspecialchars($name), $verifyLink], $html);
                        send_email($email, $subject, $bodyHtml, true);
                    } else {
                        $body = "Hello $name,\n\nPlease verify your email by clicking the link: $verifyLink\n\nIf you did not register, ignore this message.";
                        send_email($email, $subject, $body, false);
                    }

                    // Do not auto-login until email verified. Redirect to profile to allow completion if desired.
                    header('Location: applicant_profile.php?registered=1');
                    exit;
                } else {
                    // Enhanced error handling: detect migration issues vs other errors
                    $dbError = $conn->error;
                    $errorLog = 'Applicant insert failed: ' . $dbError;
                    logs_write('error', $errorLog);
                    
                    // Check if this is a missing column error (indicates migration wasn't run)
                    if (stripos($dbError, 'Unknown column') !== false) {
                        $error = "Database schema error detected. Please contact the system administrator to run the required database migration.";
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        } else {
            $error = implode(' ', $validationErrors);
        }

    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['login_role'] ?? '';

        if ($role === 'tenant') {
            $stmt = $conn->prepare("SELECT * FROM tenants WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: mytenants.php");
                    exit;
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Tenant not found.";
            }
        } else {
            $stmt = $conn->prepare("SELECT * FROM applicants WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $applicant_id = $user['applicant_id'];
                    session_regenerate_id(true);

                    // Check if profile is complete before allowing access
                    if (empty($user['name']) || empty($user['email']) || empty($user['contact'])) {
                        $_SESSION['applicant_id'] = $applicant_id;
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['profile_incomplete'] = true;
                        header("Location: applicant_profile.php?redirect=applicants.php");
                        exit;
                    }

                    // Check if promoted to tenant
                    $tenant_id = getTenantId($conn, $applicant_id);
                    if ($tenant_id) {
                        $_SESSION['tenant_id'] = $tenant_id;
                        $_SESSION['username'] = $user['username'];
                        header("Location: mytenants.php");
                        exit;
                    } else {
                        $_SESSION['applicant_id'] = $applicant_id;
                        $_SESSION['username'] = $user['username'];
                        header("Location: applicants.php");
                        exit;
                    }
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Applicant not found.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Login | JKUAT Staff Housing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: Arial, sans-serif; }
        .container {
            display: flex;
            height: 100vh;
        }

        .left-panel {
            flex: 1;
            background: url('../images/jkuat-bg.jpg') no-repeat center center;
            background-size: cover;
        }

        .right-panel {
            flex: 1;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            width: 80%;
            max-width: 400px;
            background: white;
            display: flex;
            flex-direction: column;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 40px;
        }

        .form-container img {
            display: block;
            margin: 0 auto 10px;
            width: 80px;
        }

        h2 {
            text-align: center;
            color: #28a745;
            margin-bottom: 10px;
        }

        .message { text-align: center; color: red; margin-bottom: 10px; }
        .success { color: green; }

        form label { display: block; margin-top: 15px; font-weight: bold; }
        form input[type="text"],
        form input[type="password"],
        form select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 14px;
        }

        .remember-forgot label {
            font-weight: normal;
        }

        .remember-forgot a {
            color: #006400;
            text-decoration: none;
        }

        .btn {
            width: 100%;
            background-color: #28a745;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            margin-top: 20px;
            cursor: pointer;
            font-weight: bold;
        }

        .toggle-link {
            text-align: center;
            margin-top: 15px;
        }

        .toggle-link a {
            color: #006400;
            font-weight: bold;
            text-decoration: none;
        }

        .show-password {
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left-panel"></div>
    <div class="right-panel">
        <div class="card">
            <div class="form-container">
                <img src="../images/2logo.png" alt="JKUAT Logo">
                <h2><?= isset($_GET['register']) ? 'Register as Applicant' : 'Hi, welcome back' ?></h2>
                <p style="text-align:center; margin-bottom: 10px; color: #888;">Please fill in your details to <?= isset($_GET['register']) ? 'register' : 'log in' ?></p>

                <?php if ($error): ?><div class="message"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="message success"><?= $success ?></div><?php endif; ?>

                <?php if (isset($_GET['register'])): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <label>PF Number</label>
                        <input type="text" name="pf_number" required>
                        <label>Username</label>
                        <input type="text" name="username" required>
                        <label>Full name</label>
                        <input type="text" name="name" required>
                        <label>Email</label>
                        <input type="email" name="email" required>
                        <label>Contact</label>
                        <input type="text" name="contact" required>
                        <label>Next of Kin Name</label>
                        <input type="text" name="next_of_kin_name" required>
                        <label>Next of Kin Contact</label>
                        <input type="text" name="next_of_kin_contact" required>
                        <label>Password</label>
                        <input type="password" name="password" id="regPassword" required>
                        <div class="show-password">
                            <input type="checkbox" onclick="togglePassword('regPassword')"> Show Password
                        </div>
                        <button type="submit" class="btn">Register</button>
                    </form>
                    <div class="toggle-link">
                        Already have an account? <a href="applicantlogin.php">Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        
                        <label>Username</label>
                        <input type="text" name="username" required>
                        <label>Password</label>
                        <input type="password" name="password" id="loginPassword" required>
                        <div class="show-password">
                            <input type="checkbox" onclick="togglePassword('loginPassword')"> Show Password
                        </div>
                        <div class="remember-forgot">
                            <label><input type="checkbox" name="remember"> Remember me</label>
                            <a href="request_password_reset.php">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn">Sign In</button>
                    </form>
                    <div class="toggle-link">
                        Don't have an account? <a href="?register=1">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
        } else {
            field.type = "password";
        }
    }
</script>

</body>
</html>
