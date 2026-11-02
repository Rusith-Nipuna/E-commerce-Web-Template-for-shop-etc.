<?php
// account/reset_password.php
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

$token = $_GET['token'] ?? '';
$valid_token = false;
$user = null;

if ($token) {
    // Verify token - also check if not used and not expired
    $stmt = $db->prepare("
        SELECT pr.*, u.* 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if ($result) {
        $valid_token = true;
        $user = $result;
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Verify token again
        $stmt = $db->prepare("
            SELECT pr.*, u.* 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $result['user_id']]);
            
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Send confirmation email
            $user_data = [
                'id' => $result['user_id'],
                'first_name' => $result['first_name'],
                'email' => $result['email']
            ];
            $emailService->sendPasswordChangedEmail($user_data);
            
            $_SESSION['reset_success'] = 'Password changed successfully. Please login with your new password.';
            header('Location: login.php');
            exit;
        } else {
            $error = 'Invalid or expired reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Password - RedZone.lk</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:#f7f7f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
        .container{background:#fff;border:1px solid #e8e8e4;border-radius:20px;padding:2.5rem;width:450px;max-width:90%}
        .logo{text-align:center;margin-bottom:1.5rem}
        .logo span{background:#d72c2c;color:#fff;padding:8px 16px;border-radius:8px;font-weight:700}
        h1{font-size:1.3rem;margin-bottom:0.5rem}
        .desc{color:#6b6b67;font-size:0.85rem;margin-bottom:1.5rem}
        .form-group{margin-bottom:1rem}
        label{display:block;font-size:0.7rem;font-weight:600;color:#6b6b67;margin-bottom:0.3rem}
        input{width:100%;padding:0.8rem;border:1px solid #e8e8e4;border-radius:10px;font-size:0.9rem}
        input:focus{outline:none;border-color:#d72c2c}
        button{width:100%;background:#d72c2c;color:#fff;border:none;padding:0.8rem;border-radius:10px;font-weight:600;cursor:pointer}
        button:hover{background:#b91c1c}
        .error{background:#fee2e2;color:#dc2626;padding:0.8rem;border-radius:10px;margin-bottom:1rem;font-size:0.85rem}
        .password-requirements{font-size:0.7rem;color:#6b6b67;margin-top:0.3rem}
        .back-link{text-align:center;margin-top:1rem}
        .back-link a{color:#d72c2c;text-decoration:none}
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><span>REDZONE.LK</span></div>
        
        <?php if ($valid_token): ?>
            <h1>Reset Password 🔑</h1>
            <p class="desc">Create a new password for your account.</p>
            
            <?php if ($error): ?>
                <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required minlength="6">
                    <div class="password-requirements">Minimum 6 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password">Reset Password →</button>
            </form>
        <?php else: ?>
            <h1>Invalid Link 🔒</h1>
            <p class="desc"><?php echo htmlspecialchars($error ?: 'The password reset link is invalid or has expired.'); ?></p>
            <div class="back-link">
                <a href="forgot_password.php">Request New Reset Link →</a>
            </div>
            <div class="back-link" style="margin-top:0.5rem">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>