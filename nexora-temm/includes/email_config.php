<?php
// includes/email_config.php - Email Service Configuration
require_once __DIR__ . '/config.php';

class EmailService {
    private $db;
    private $smtp_host = 'smtp.gmail.com'; // Change to your SMTP (Use your hosting SMTP)
    private $smtp_port = 587;
    private $smtp_user = 'noreply@nexora.lk'; // Your email
    private $smtp_pass = 'your-email-password'; // Your email password
    private $from_email = 'noreply@nexora.lk';
    private $from_name = 'Nexora.lk';
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Send Email using PHPMailer
    public function sendEmail($to, $subject, $body, $altBody = '') {
        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';
        require_once __DIR__ . '/PHPMailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_user;
            $mail->Password   = $this->smtp_pass;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            $mail->send();
            
            // Log to database
            $this->logEmail($to, $subject, 'sent');
            
            return ['success' => true, 'message' => 'Email sent'];
        } catch (Exception $e) {
            // Log error
            $this->logEmail($to, $subject, 'failed', $mail->ErrorInfo);
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }
    
    // Log email to database
    private function logEmail($to, $subject, $status, $error = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO email_logs (recipient, subject, status, error_message, sent_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$to, $subject, $status, $error]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
    
    // ========== CUSTOMER EMAIL TEMPLATES ==========
    
    // Welcome Email (After Registration)
    public function sendWelcomeEmail($user) {
        $subject = "Welcome to RedZone.lk!";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .content{color:#333;line-height:1.6}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='content'>
                    <h2>Welcome {$user['first_name']}! 🎉</h2>
                    <p>Thank you for joining RedZone.lk! We're excited to have you as part of our community.</p>
                    <p>With your RedZone account, you can:</p>
                    <ul>
                        <li>🛍️ Shop the latest smartphones and accessories</li>
                        <li>📦 Track your orders in real-time</li>
                        <li>❤️ Save items to your wishlist</li>
                        <li>🔔 Get exclusive offers and discounts</li>
                    </ul>
                    <center><a href='https://nexora.lk' class='btn'>Start Shopping →</a></center>
                    <p>Need help? Contact us at <strong>support@nexora.lk</strong> or call <strong>077 123 4567</strong></p>
                </div>
                <div class='footer'>
                    © 2024 nexora.lk | All Rights Reserved<br>
                    <small>You're receiving this email because you registered on nexora.lk</small>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Password Reset Request Email
    public function sendPasswordResetEmail($user, $reset_token) {
        $reset_link = "https://nexora.lk/account/reset_password.php?token=" . urlencode($reset_token);
        
        $subject = "Reset Your Password - nexora.lk";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .warning{background:#fef3c7;padding:12px;border-radius:8px;margin:15px 0;font-size:13px}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>nexora.LK</div>
                </div>
                <div class='content'>
                    <h2>Password Reset Request 🔐</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <center><a href='{$reset_link}' class='btn'>Reset Password →</a></center>
                    <div class='warning'>
                        ⚠️ This link will expire in 1 hour.<br>
                        If you didn't request this, please ignore this email.
                    </div>
                    <p>Or copy this link: <br><small style='color:#666;word-break:break-all'>{$reset_link}</small></p>
                    <p>Need help? Contact support@redzone.lk</p>
                </div>
                <div class='footer'>
                    © 2024 RedZone.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Password Changed Successfully Email
    public function sendPasswordChangedEmail($user) {
        $subject = "Password Changed Successfully - RedZone.lk";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='content'>
                    <h2>Password Changed 🔒</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>Your RedZone.lk account password has been successfully changed.</p>
                    <p>If you made this change, you can ignore this email.</p>
                    <div class='warning' style='background:#fee2e2;'>
                        ⚠️ If you didn't change your password, please contact us immediately at support@redzone.lk
                    </div>
                    <center><a href='https://redzone.lk/account/login.php' class='btn'>Login to Account →</a></center>
                </div>
                <div class='footer'>
                    © 2024 RedZone.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Order Confirmation Email
    public function sendOrderConfirmationEmail($user, $order, $order_items) {
        $subject = "Order Confirmation #{$order['order_number']} - RedZone.lk";
        
        $items_html = "";
        foreach ($order_items as $item) {
            $items_html .= "
            <tr>
                <td style='padding:8px;border-bottom:1px solid #eee'>{$item['product_name']}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:center'>{$item['quantity']}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right'>LKR " . number_format($item['price'], 2) . "</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right'>LKR " . number_format($item['total'], 2) . "</td>
            </tr>
            ";
        }
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .order-info{background:#f7f7f5;padding:15px;border-radius:12px;margin:15px 0}
                table{width:100%;border-collapse:collapse}
                th{text-align:left;padding:8px;background:#f7f7f5}
                .total{font-size:18px;font-weight:700;color:#d72c2c}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='content'>
                    <h2>Order Confirmed! 🎉</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>Thank you for shopping with RedZone.lk! Your order has been successfully placed.</p>
                    
                    <div class='order-info'>
                        <strong>Order #:</strong> {$order['order_number']}<br>
                        <strong>Order Date:</strong> " . date('F d, Y', strtotime($order['created_at'])) . "<br>
                        <strong>Payment Method:</strong> " . str_replace('_', ' ', $order['payment_method']) . "<br>
                        <strong>Shipping Address:</strong><br>
                        " . nl2br($order['shipping_address']) . "<br>
                        {$order['shipping_city']}, {$order['shipping_district']}
                    </div>
                    
                    <h3>Order Items</h3>
                    <table>
                        <thead>
                            <tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            {$items_html}
                        </tbody>
                        <tfoot>
                            <tr><td colspan='3' style='text-align:right;padding:8px'><strong>Total:</strong></td>
                            <td style='padding:8px;text-align:right'><strong class='total'>LKR " . number_format($order['total_amount'], 2) . "</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <center><a href='https://redzone.lk/account/orders.php' class='btn' style='background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0'>Track Order →</a></center>
                    
                    <p>We'll notify you when your order is shipped.</p>
                    <p>Need help? Contact support@redzone.lk</p>
                </div>
                <div class='footer'>
                    © 2024 RedZone.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Payment Success Email
    public function sendPaymentSuccessEmail($user, $order, $payment_details) {
        $subject = "Payment Successful - RedZone.lk";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .success{background:#dcfce7;padding:15px;border-radius:12px;text-align:center;margin:15px 0}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='success'>
                    ✅ <strong>Payment Successful!</strong><br>
                    Amount Paid: LKR " . number_format($order['total_amount'], 2) . "
                </div>
                <div class='content'>
                    <h2>Thank you for your payment! 💰</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>We have received your payment for Order #{$order['order_number']}.</p>
                    <p>Your order is now being processed and will be shipped soon.</p>
                    <center><a href='https://nexora.lk/account/orders.php' class='btn'>View Order Details →</a></center>
                </div>
                <div class='footer'>
                    © 2024 nexora.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Order Cancelled Email
    public function sendOrderCancelledEmail($user, $order) {
        $subject = "Order Cancelled #{$order['order_number']} - RedZone.lk";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .cancelled{background:#fee2e2;padding:15px;border-radius:12px;text-align:center;margin:15px 0}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='cancelled'>
                    ❌ <strong>Order Cancelled</strong><br>
                    Order #: {$order['order_number']}
                </div>
                <div class='content'>
                    <h2>Order Cancelled</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>Your order #{$order['order_number']} has been cancelled as requested.</p>";
                    
        if ($order['payment_status'] === 'paid') {
            $body .= "<p>💳 A refund will be processed to your original payment method within 5-7 business days.</p>";
        }
        
        $body .= "
                    <p>If you have any questions, please contact support@redzone.lk</p>
                    <center><a href='https://redzone.lk/shop.php' class='btn'>Continue Shopping →</a></center>
                </div>
                <div class='footer'>
                    © 2024 RedZone.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    // Order Status Update Email
    public function sendOrderStatusUpdateEmail($user, $order, $old_status, $new_status) {
        $status_messages = [
            'processing' => "⚙️ Your order is now being processed.",
            'shipped' => "🚚 Your order has been shipped!",
            'delivered' => "✅ Your order has been delivered!",
            'cancelled' => "❌ Your order has been cancelled."
        ];
        
        $message = $status_messages[$new_status] ?? "Your order status has been updated to " . ucfirst($new_status);
        
        $subject = "Order Status Update #{$order['order_number']} - RedZone.lk";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:20px}
                .container{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;padding:30px}
                .header{text-align:center;border-bottom:2px solid #d72c2c;padding-bottom:20px;margin-bottom:20px}
                .logo{background:#d72c2c;color:#fff;display:inline-block;padding:8px 15px;border-radius:8px;font-weight:700}
                .btn{background:#d72c2c;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;display:inline-block;margin:20px 0}
                .footer{text-align:center;font-size:12px;color:#999;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>REDZONE.LK</div>
                </div>
                <div class='content'>
                    <h2>Order Status Updated</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>{$message}</p>
                    <p><strong>Order #:</strong> {$order['order_number']}</p>
                    <p><strong>New Status:</strong> " . ucfirst($new_status) . "</p>
                    <center><a href='https://redzone.lk/account/orders.php' class='btn'>Track Order →</a></center>
                </div>
                <div class='footer'>
                    © 2024 RedZone.lk | All Rights Reserved
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($user['email'], $subject, $body);
    }
}
?>