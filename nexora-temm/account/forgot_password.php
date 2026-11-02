<?php
// account/forgot_password.php - COMPLETE WORKING VERSION
require_once '../includes/config.php';
require_once '../includes/email_config.php';

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();
$emailService = new EmailService();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email exists
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // First, delete any existing unused tokens for this user
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0");
            $stmt->execute([$user['id']]);
            
            // Save new token to database
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, used) VALUES (?, ?, ?, 0)");
            $stmt->execute([$user['id'], $reset_token, $expires_at]);
            
            // Send email
            $result = $emailService->sendPasswordResetEmail($user, $reset_token);
            
            if ($result['success']) {
                $message = 'Password reset link has been sent to your email address. Please check your inbox.';
            } else {
                $error = 'Failed to send email. Please try again later.';
                // Log error for debugging
                error_log("Password reset email failed: " . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            // Don't reveal if email exists for security
            $message = 'If an account exists with this email, you will receive a password reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - RedZone.lk</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f7f7f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .container {
            background: #fff;
            border: 1px solid #e8e8e4;
            border-radius: 24px;
            padding: 2.5rem;
            width: 480px;
            max-width: 100%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo span {
            background: #d72c2c;
            color: #fff;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        
        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .desc {
            color: #6b6b67;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b6b67;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input {
            width: 100%;
            padding: 0.9rem;
            border: 1.5px solid #e8e8e4;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.2s;
        }
        
        input:focus {
            outline: none;
            border-color: #d72c2c;
            box-shadow: 0 0 0 2px rgba(215,44,44,0.1);
        }
        
        button {
            width: 100%;
            background: #d72c2c;
            color: #fff;
            border: none;
            padding: 0.9rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }
        
        button:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .message {
            background: #dcfce7;
            color: #16a34a;
            padding: 0.9rem;
            border-radius: 12px;
            margin-bottom: 1.2rem;
            font-size: 0.85rem;
            border-left: 3px solid #16a34a;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.9rem;
            border-radius: 12px;
            margin-bottom: 1.2rem;
            font-size: 0.85rem;
            border-left: 3px solid #dc2626;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: #d72c2c;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .back-link a:hover {
            color: #b91c1c;
            text-decoration: underline;
        }
        
        .info-text {
            font-size: 0.7rem;
            color: #9b9b97;
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e8e8e4;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 1.8rem;
            }
            h1 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <span>REDZONE.LK</span>
        </div>
        
        <h1>Forgot Password? 🔐</h1>
        <p class="desc">Enter your email address and we'll send you a link to reset your password.</p>
        
        <?php if ($message): ?>
            <div class="message">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="your@email.com" autofocus>
            </div>
            <button type="submit">Send Reset Link →</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
        
        <div class="info-text">
            🔒 We'll send a password reset link to your registered email address.
        </div>
    </div>
</body>
</html>