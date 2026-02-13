<?php
/**
 * create_password_hash.php
 * TEMPORARY UTILITY - Delete after use
 * 
 * Purpose: Generate bcrypt password hashes for testing admin account creation
 * Usage: 
 *   1. Visit this file in your browser
 *   2. Enter a plain password
 *   3. Copy the generated hash
 *   4. Paste into phpMyAdmin's password field when creating admin user
 *   5. DELETE THIS FILE when done
 */

$hash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Password Hash - Testing Only</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .instructions {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .result {
            background: #f8f9fa;
            border: 2px solid #28a745;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .result h3 {
            color: #28a745;
            margin-bottom: 10px;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            min-height: 100px;
            background: white;
            color: #333;
        }
        
        .copy-button {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .copy-button:hover {
            background: #218838;
        }
        
        .important {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .steps {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        
        .steps ol {
            margin-left: 20px;
        }
        
        .steps li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Hash Generator</h1>
        
        <div class="warning">
            <strong>⚠️ TEMPORARY TESTING UTILITY</strong><br>
            Delete this file after creating your test admin account. Never commit it to source control.
        </div>
        
        <div class="instructions">
            <strong>How to use:</strong> Enter a password below, copy the generated hash, and paste it into phpMyAdmin's password field when creating an admin user.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Enter a password (plain text):</label>
                <input 
                    type="text" 
                    id="password" 
                    name="password" 
                    placeholder="e.g., MySecurePass123" 
                    required
                >
                <small style="color: #666; display: block; margin-top: 5px;">
                    💡 Use a strong password with letters, numbers, and special characters
                </small>
            </div>
            
            <button type="submit">Generate Bcrypt Hash</button>
        </form>
        
        <?php if ($hash): ?>
            <div class="result">
                <h3>✅ Your Bcrypt Hash:</h3>
                <textarea readonly><?= htmlspecialchars($hash) ?></textarea>
                <button class="copy-button" onclick="copyToClipboard()">📋 Copy to Clipboard</button>
                
                <div class="important">
                    ⚠️ <strong>IMPORTANT:</strong> Remember the password you entered above!<br>
                    You'll need it to log in. The hash is just for the database.
                </div>
                
                <div class="steps">
                    <strong>Next steps:</strong>
                    <ol>
                        <li>Open phpMyAdmin → staff_housing → users table</li>
                        <li>Click <strong>"Insert"</strong></li>
                        <li>Fill in the fields:
                            <ul style="margin-left: 20px; margin-top: 5px;">
                                <li><strong>user_id:</strong> U005 (or next available)</li>
                                <li><strong>pf_no:</strong> 0000 (or staff ID)</li>
                                <li><strong>username:</strong> mboru (or desired username)</li>
                                <li><strong>name:</strong> Mohamed Boru</li>
                                <li><strong>email:</strong> ratimboru@gmail.com</li>
                                <li><strong>role:</strong> CS Admin (or ICT Admin)</li>
                                <li><strong>password:</strong> <span style="background: #fff3cd; padding: 2px 5px;">PASTE THE HASH ABOVE</span></li>
                                <li><strong>status:</strong> Active</li>
                            </ul>
                        </li>
                        <li>Click <strong>"Go"</strong> or <strong>"Insert"</strong></li>
                        <li>Log in with username: <strong><?= htmlspecialchars($_POST['password'] ?? 'password') ?></strong> (the plain password you entered above)</li>
                        <li><strong>DELETE THIS FILE</strong></li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyToClipboard() {
            const textarea = document.querySelector('textarea');
            textarea.select();
            document.execCommand('copy');
            alert('Hash copied to clipboard! 📋');
        }
    </script>
</body>
</html>
