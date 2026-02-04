<?php 
session_start();
include '../includes/db.php';

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
        $pf_number = trim($_POST['pf_number']);
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("SELECT * FROM applicants WHERE pf_no = ? OR username = ?");
        $stmt->bind_param("ss", $pf_number, $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $error = "PF Number or Username already exists.";
        } else {
            $query = mysqli_query($conn, "SELECT applicant_id FROM applicants ORDER BY applicant_id DESC LIMIT 1");
            if ($row = mysqli_fetch_assoc($query)) {
                $lastIdNum = (int)substr($row['applicant_id'], 1);
                $newIdNum = $lastIdNum + 1;
            } else {
                $newIdNum = 1;
            }
            $applicant_id = 'A' . str_pad($newIdNum, 3, '0', STR_PAD_LEFT);

            $insert = $conn->prepare("INSERT INTO applicants (applicant_id, pf_no, username, password) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $applicant_id, $pf_number, $username, $password);
            if ($insert->execute()) {
                $success = "Registration successful. You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['login_role'];

        if ($role === 'tenant') {
            $stmt = $conn->prepare("SELECT * FROM tenants WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
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
                            <a href="#">Forgot Password?</a>
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
