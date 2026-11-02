<?php
// account/change_password.php (Add email notification part)
require_once '../includes/config.php';
require_once '../includes/email_config.php';

// ... existing code ...

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // ... existing validation ...
    
    if ($stmt->rowCount() > 0) {
        // Send email notification
        $emailService = new EmailService();
        $user = getCurrentUser();
        $emailService->sendPasswordChangedEmail($user);
        
        $_SESSION['password_changed'] = 'Password changed successfully!';
        header('Location: profile.php');
        exit;
    }
}
?>