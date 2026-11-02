<?php
// pay/payment_success.php - Simplified success handler (without webhook)
require_once '../includes/config.php';

session_start();
$db = getDB();

$order_id = $_GET['order_id'] ?? '';
$status_code = $_GET['status_code'] ?? '';

if ($status_code == '2' && !empty($order_id)) {
    try {
        // Update order status
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE order_number = ?");
        $stmt->execute([$order_id]);
        
        // Get user ID and clear cart
        $stmt = $db->prepare("SELECT user_id FROM orders WHERE order_number = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order && isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$order['user_id']]);
        }
        
        // Set success message
        $_SESSION['payment_success_msg'] = "Payment completed successfully! Order #: " . $order_id;
        
        header('Location: ../account/orders.php?payment_success=1');
        exit;
        
    } catch (Exception $e) {
        error_log("Payment success error: " . $e->getMessage());
        header('Location: ../cart.php?payment_error=1');
        exit;
    }
} else {
    header('Location: ../cart.php?payment_failed=1');
    exit;
}
?>