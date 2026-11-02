<?php
// pay/payment_return.php - After PayHere payment completion
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();

$order_id = $_GET['order_id'] ?? '';
$status_code = $_GET['status_code'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';

// Redirect to appropriate page based on payment status
if ($status_code == '2') {
    // Payment successful
    try {
        // Update order status
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE order_number = ?");
        $stmt->execute([$order_id]);
        
        // Get user ID from order to clear cart
        $stmt = $db->prepare("SELECT user_id FROM orders WHERE order_number = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order && isset($_SESSION['user_id'])) {
            // Clear user's cart
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$order['user_id']]);
        }
        
        // Redirect to orders page
        header('Location: ../account/orders.php?payment_success=1&order=' . $order_id);
        exit;
        
    } catch (Exception $e) {
        error_log("Payment return error: " . $e->getMessage());
        header('Location: ../cart.php?payment_error=1');
        exit;
    }
    
} elseif ($status_code == '0') {
    // Payment pending
    header('Location: ../account/orders.php?payment_pending=1');
    exit;
    
} else {
    // Payment failed or cancelled
    header('Location: ../cart.php?payment_failed=1');
    exit;
}
?>