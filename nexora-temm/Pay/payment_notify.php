<?php
// pay/payment_notify.php - PayHere Webhook (Background notification)
require_once '../includes/config.php';

// Get PayHere response (POST data)
$merchant_id = $_POST['merchant_id'] ?? '';
$order_id = $_POST['order_id'] ?? '';
$payment_id = $_POST['payment_id'] ?? '';
$status_code = $_POST['status_code'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';

// PayHere Secret - Same as checkout.php
$payhere_secret = '8lxwy9S797O8hgc9qMhFI28LV5UHzoTlB4pDEgVh2RiF';

// Verify hash to ensure request is from PayHere
$local_md5sig = strtoupper(md5($merchant_id . $order_id . $payment_id . $status_code . strtoupper(md5($payhere_secret))));

// Log for debugging
error_log("PayHere Notification - Order: $order_id, Status: $status_code");
error_log("Received MD5: $md5sig");
error_log("Calculated MD5: $local_md5sig");

if ($md5sig === $local_md5sig) {
    $db = getDB();
    
    if ($status_code == '2') {
        // Payment successful
        try {
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE order_number = ?");
            $stmt->execute([$order_id]);
            
            // Get user ID
            $stmt = $db->prepare("SELECT user_id FROM orders WHERE order_number = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Clear cart
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$order['user_id']]);
            }
            
            error_log("Payment notification processed successfully for order: $order_id");
            
        } catch (Exception $e) {
            error_log("Payment notification error: " . $e->getMessage());
        }
        
    } elseif ($status_code == '0') {
        // Payment pending - update status
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'pending' WHERE order_number = ?");
        $stmt->execute([$order_id]);
        error_log("Payment pending for order: $order_id");
        
    } elseif ($status_code == '-1') {
        // Payment cancelled
        $stmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = 'failed' WHERE order_number = ?");
        $stmt->execute([$order_id]);
        error_log("Payment cancelled for order: $order_id");
        
    } elseif ($status_code == '-2') {
        // Payment failed
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE order_number = ?");
        $stmt->execute([$order_id]);
        error_log("Payment failed for order: $order_id");
    }
    
    // Always return 200 OK to PayHere
    http_response_code(200);
    echo "OK";
    
} else {
    // Invalid hash - possible fraud attempt
    error_log("Invalid hash received for order: $order_id");
    http_response_code(400);
    echo "Invalid Hash";
}
?>