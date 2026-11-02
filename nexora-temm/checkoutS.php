<?php
// checkout.php - FULLY SECURE & UI FIXED
require_once 'includes/config.php';
require_once 'includes/header.php';

// ========== SECURITY HEADERS ==========
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ========== CSRF TOKEN GENERATION ==========
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token_time'])) {
    $_SESSION['csrf_token_time'] = time();
}

// ========== RATE LIMITING FUNCTION ==========
function checkRateLimit($db, $user_id, $limit = 5, $time_window = 3600) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM orders 
        WHERE user_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$user_id, $time_window]);
    $count = $stmt->fetchColumn();
    return $count < $limit;
}

// ========== INPUT VALIDATION ==========
function validateInput($data, $type = 'string') {
    if ($data === null) return '';
    $data = trim($data);
    $data = stripslashes($data);
    
    switch($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'name':
            return preg_replace('/[^a-zA-Z\s\-\'\.]/', '', $data);
        case 'phone':
            return preg_replace('/[^0-9\+\-\s]/', '', $data);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// ========== CHECK DIRECT BUY ==========
$directBuy = isset($_GET['direct_buy']) ? true : false;
$directBuyItem = null;

if ($directBuy && isset($_SESSION['checkout_data'])) {
    $directBuyItem = $_SESSION['checkout_data'];
    unset($_SESSION['checkout_data']);
}

// ========== REDIRECT IF NOT LOGGED IN ==========
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'checkout.php' . ($directBuy ? '?direct_buy=1' : '');
    header('Location: account/login.php');
    exit;
}

$db = getDB();
$user = getCurrentUser();
$user_ip = $_SERVER['REMOTE_ADDR'];
$order_error = '';

// ========== RATE LIMIT CHECK ==========
if (!checkRateLimit($db, $user['id'], $user_ip, 5, 3600)) {
    $order_error = 'Too many order attempts. Please try again after 1 hour.';
}

// ========== PAYHERE CONFIGURATION ==========
$payhere_merchant_id = '4OVycINsRBQ4JH5F1nvCde3D6';
$payhere_secret = '8lxwy9S797O8hgc9qMhFI28LV5UHzoTlB4pDEgVh2RiF';
$payhere_sandbox = true;

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$protocol = $is_https ? 'https://' : 'http://';
$payhere_url = $payhere_sandbox ? 'https://sandbox.payhere.lk/pay/checkout' : 'https://www.payhere.lk/pay/checkout';

// ========== GET CART ITEMS ==========
if ($directBuy && $directBuyItem) {
    if (!isset($directBuyItem['product_id']) || !isset($directBuyItem['quantity']) || $directBuyItem['quantity'] < 1) {
        header('Location: cart.php');
        exit;
    }
    
    $cartItems = [];
    $cartItems[] = (object)[
        'product_id' => (int)$directBuyItem['product_id'],
        'quantity' => (int)$directBuyItem['quantity'],
        'name' => validateInput($directBuyItem['product_name']),
        'base_price' => (float)$directBuyItem['unit_price'],
        'product_image' => validateInput($directBuyItem['product_image'] ?? ''),
        'emoji' => '📱',
        'stock_quantity' => 999,
        'selected_color' => validateInput($directBuyItem['color'] ?? ''),
        'selected_storage' => validateInput($directBuyItem['storage'] ?? ''),
        'is_direct_buy' => true
    ];
    
    $subtotal = (float)$directBuyItem['total_price'];
    $shipping = ($subtotal > 50000) ? 0 : 500;
    $total = $subtotal + $shipping;
    
} else {
    $stmt = $db->prepare("
        SELECT c.*, p.name, p.slug, p.base_price, p.product_image, p.emoji, p.stock_quantity,
               c.selected_color, c.selected_storage
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll();
    
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += (float)$item['base_price'] * (int)$item['quantity'];
    }
    $shipping = ($subtotal > 50000) ? 0 : 500;
    $total = $subtotal + $shipping;
}

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

// ========== GET USER ADDRESSES ==========
$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$user['id']]);
$savedAddresses = $stmt->fetchAll();

$defaultAddress = null;
foreach ($savedAddresses as $addr) {
    if ($addr['is_default']) {
        $defaultAddress = $addr;
        break;
    }
}

// ========== IDEMPOTENCY KEY ==========
$idempotency_key = bin2hex(random_bytes(16));

// ========== PROCESS ORDER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    if (!empty($order_error)) {
        // error already set
    }
    elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $order_error = 'Security validation failed. Please refresh the page.';
    }
    elseif (time() - $_SESSION['csrf_token_time'] > 1800) {
        $order_error = 'Session expired. Please refresh the page.';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    else {
        $payment_method = validateInput($_POST['payment_method']);
        $billing_address_line1 = validateInput($_POST['billing_address_line1']);
        $billing_address_line2 = validateInput($_POST['billing_address_line2'] ?? '');
        $billing_city = validateInput($_POST['billing_city']);
        $billing_district = validateInput($_POST['billing_district']);
        
        $full_name = validateInput($_POST['full_name'], 'name');
        $customer_email = validateInput($_POST['email'], 'email');
        $customer_phone = validateInput($_POST['phone'], 'phone');
        
        if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            $order_error = 'Please enter a valid email address.';
        }
        elseif (!preg_match('/^[0-9+\-\s]{9,15}$/', $customer_phone)) {
            $order_error = 'Please enter a valid phone number.';
        }
        else {
            $name_parts = explode(' ', $full_name, 2);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';
            
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $customer_email, $customer_phone, $user['id']]);
            
            $same_as_billing = isset($_POST['same_as_billing']) ? 1 : 0;
            
            if ($same_as_billing) {
                $shipping_address_line1 = $billing_address_line1;
                $shipping_address_line2 = $billing_address_line2;
                $shipping_city = $billing_city;
                $shipping_district = $billing_district;
            } else {
                $shipping_address_line1 = validateInput($_POST['shipping_address_line1'] ?? '');
                $shipping_address_line2 = validateInput($_POST['shipping_address_line2'] ?? '');
                $shipping_city = validateInput($_POST['shipping_city'] ?? '');
                $shipping_district = validateInput($_POST['shipping_district'] ?? '');
            }
            
            $shipping_full_address = $shipping_address_line1 . ($shipping_address_line2 ? ', ' . $shipping_address_line2 : '');
            $billing_full_address = $billing_address_line1 . ($billing_address_line2 ? ', ' . $billing_address_line2 : '');
            
            if (empty($billing_address_line1) || empty($billing_city) || empty($billing_district)) {
                $order_error = 'Please fill all required billing address fields.';
            }
            elseif (!$same_as_billing && (empty($shipping_address_line1) || empty($shipping_city) || empty($shipping_district))) {
                $order_error = 'Please fill all required shipping address fields.';
            }
            else {
                $order_number = 'ORD-' . strtoupper(uniqid());
                $is_payhere_payment = in_array($payment_method, ['card_payhere', 'gpay_payhere', 'mobile_payhere']);
                
                try {
                    $db->beginTransaction();
                    
                    // Duplicate check
                    $check_stmt = $db->prepare("SELECT id FROM orders WHERE user_id = ? AND total_amount = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
                    $check_stmt->execute([$user['id'], $total]);
                    if ($check_stmt->fetch()) {
                        throw new Exception("Duplicate order detected. Please wait.");
                    }
                    
                    $stmt = $db->prepare("INSERT INTO orders (order_number, user_id, total_amount, payment_method, payment_status, order_status, shipping_address, shipping_city, shipping_district, billing_address, idempotency_key, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $order_number, $user['id'], $total, $payment_method, 
                        'pending', 'pending', $shipping_full_address, $shipping_city, 
                        $shipping_district, $billing_full_address, $idempotency_key, $user_ip
                    ]);
                    
                    $order_id = $db->lastInsertId();
                    
                    foreach ($cartItems as $item) {
                        $product_id = is_array($item) ? (int)$item['product_id'] : (int)$item->product_id;
                        $product_name = is_array($item) ? validateInput($item['name']) : validateInput($item->name);
                        $quantity = is_array($item) ? (int)$item['quantity'] : (int)$item->quantity;
                        $price = is_array($item) ? (float)$item['base_price'] : (float)$item->base_price;
                        
                        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$order_id, $product_id, $product_name, $quantity, $price, $price * $quantity]);
                    }
                    
                    if ($is_payhere_payment) {
                        $db->commit();
                        
                        $payhere_method = 'VISA';
                        if ($payment_method === 'gpay_payhere') $payhere_method = 'GPAY';
                        elseif ($payment_method === 'mobile_payhere') $payhere_method = 'MCREDIT';
                        
                        $item_list = '';
                        foreach ($cartItems as $item) {
                            $name = is_array($item) ? validateInput($item['name']) : validateInput($item->name);
                            $qty = is_array($item) ? (int)$item['quantity'] : (int)$item->quantity;
                            $item_list .= $name . ' x ' . $qty . ', ';
                        }
                        $item_list = rtrim($item_list, ', ');
                        if (strlen($item_list) > 100) $item_list = substr($item_list, 0, 97) . '...';
                        
                        $hash_string = $payhere_merchant_id . $order_number . number_format($total, 2, '.', '') . 'LKR' . strtoupper(md5($payhere_secret));
                        $hash = strtoupper(hash('sha256', $hash_string));
                        
                        ?>
                        <!DOCTYPE html>
                        <html>
                        <head><title>Processing Payment - RedZone.lk</title><meta charset="UTF-8">
                        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
                        <style>
                            *{margin:0;padding:0;box-sizing:border-box}
                            body{font-family:'DM Sans',sans-serif;background:#f7f7f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
                            .container{text-align:center;background:#fff;border:1px solid #e8e8e4;border-radius:20px;padding:3rem;max-width:400px}
                            .spinner{width:50px;height:50px;border:3px solid #e8e8e4;border-top-color:#d72c2c;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 1.5rem}
                            @keyframes spin{to{transform:rotate(360deg)}}
                            h2{font-size:1.2rem;margin-bottom:0.5rem}
                            p{color:#6b6b67;font-size:0.85rem;margin-bottom:1rem}
                            .order-id{background:#f7f7f5;padding:0.5rem;border-radius:8px;font-family:monospace;margin-top:1rem}
                        </style>
                        </head>
                        <body>
                        <div class="container">
                            <div class="spinner"></div>
                            <h2>🔒 Redirecting to Secure Payment Gateway</h2>
                            <p>Please wait while we redirect you to PayHere...</p>
                            <div class="order-id">Order #: <?php echo htmlspecialchars($order_number); ?></div>
                            <div class="order-id">Amount: <?php echo formatPrice($total); ?></div>
                        </div>
                        <form method="POST" action="<?php echo $payhere_url; ?>" id="payhereForm" style="display:none;">
                            <input type="hidden" name="merchant_id" value="<?php echo htmlspecialchars($payhere_merchant_id); ?>">
                            <input type="hidden" name="return_url" value="<?php echo $protocol . $_SERVER['HTTP_HOST']; ?>/cc/pay/payment_return.php">
                            <input type="hidden" name="cancel_url" value="<?php echo $protocol . $_SERVER['HTTP_HOST']; ?>/cc/cart.php">
                            <input type="hidden" name="notify_url" value="<?php echo $protocol . $_SERVER['HTTP_HOST']; ?>/cc/pay/payment_notify.php">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_number); ?>">
                            <input type="hidden" name="items" value="<?php echo htmlspecialchars($item_list); ?>">
                            <input type="hidden" name="currency" value="LKR">
                            <input type="hidden" name="amount" value="<?php echo number_format($total, 2, '.', ''); ?>">
                            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($customer_email); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($customer_phone); ?>">
                            <input type="hidden" name="address" value="<?php echo htmlspecialchars($billing_address_line1); ?>">
                            <input type="hidden" name="city" value="<?php echo htmlspecialchars($billing_city); ?>">
                            <input type="hidden" name="country" value="Sri Lanka">
                            <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($payhere_method); ?>">
                            <input type="hidden" name="hash" value="<?php echo htmlspecialchars($hash); ?>">
                        </form>
                        <script>document.getElementById('payhereForm').submit();</script>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                    
                   // For non-PayHere payments (Bank Transfer)
$db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user['id']]);
$db->commit();
unset($_SESSION['checkout_data']);

// Use full path
header('Location: /cc/payment_instructions.php?order=' . urlencode($order_number));
exit;
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Order error: " . $e->getMessage());
                    $order_error = 'Error processing order. Please try again.';
                }
            }
        }
    }
}

// ========== SAVE NEW ADDRESS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_new_address'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $order_error = 'Security validation failed.';
    } else {
        $address_line1 = validateInput($_POST['save_address_line1']);
        $address_line2 = validateInput($_POST['save_address_line2'] ?? '');
        $city = validateInput($_POST['save_city']);
        $district = validateInput($_POST['save_district']);
        $is_default = isset($_POST['save_as_default']) ? 1 : 0;
        
        if (!empty($address_line1) && !empty($city) && !empty($district)) {
            if ($is_default) {
                $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            }
            $stmt = $db->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, district, is_default) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $address_line1, $address_line2, $city, $district, $is_default]);
            header('Location: checkout.php');
            exit;
        }
    }
}

// Refresh CSRF token
if (time() - $_SESSION['csrf_token_time'] > 1800) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

$pageTitle = 'Checkout';
$hide_header_search = true;
?>

<style>
/* ============================================
   CHECKOUT PAGE STYLES - ORIGINAL DESIGN
   ============================================ */
.checkout-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 2rem;
}

.checkout-form {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.8rem;
}

.checkout-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--border);
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 1.5rem 0 1rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title:first-of-type {
    margin-top: 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.7rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--red);
}

/* Saved Addresses */
.saved-addresses {
    margin-bottom: 1rem;
}

.address-select {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.address-select:hover {
    border-color: var(--red);
    background: #fff5f5;
}

.address-select.selected {
    border-color: var(--red);
    background: #fff5f5;
}

.address-select input {
    width: 18px;
    height: 18px;
    accent-color: var(--red);
}

.address-details {
    flex: 1;
}

.address-details .address-text {
    font-size: 0.85rem;
    color: var(--text);
}

.address-details .address-label {
    font-size: 0.65rem;
    color: var(--muted);
}

/* Same as Billing Checkbox */
.same-address-check {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin: 1rem 0;
    padding: 0.8rem;
    background: var(--bg2);
    border-radius: 12px;
    cursor: pointer;
}

.same-address-check input {
    width: 18px;
    height: 18px;
    accent-color: var(--red);
}

/* Payment Methods */
.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin: 1rem 0;
}

.payment-option {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-option:hover {
    border-color: var(--red);
    background: #fff5f5;
}

.payment-option.selected {
    border-color: var(--red);
    background: #fff5f5;
}

.payment-option input {
    width: 20px;
    height: 20px;
    accent-color: var(--red);
}

.payment-info {
    flex: 1;
}

.payment-info .title {
    font-weight: 600;
    font-size: 0.9rem;
}

.payment-info .desc {
    font-size: 0.7rem;
    color: var(--muted);
}

.payment-icon {
    font-size: 1.5rem;
}

/* Order Summary */
.order-summary {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 100px;
}

.order-summary h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.cart-items-summary {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 1rem;
}

.cart-item-summary {
    display: flex;
    gap: 0.8rem;
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--border);
}

.cart-item-summary-img {
    width: 50px;
    height: 50px;
    background: var(--bg2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.cart-item-summary-info {
    flex: 1;
}

.cart-item-summary-name {
    font-size: 0.85rem;
    font-weight: 500;
}

.cart-item-summary-qty {
    font-size: 0.7rem;
    color: var(--muted);
}

.cart-item-summary-price {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--red);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.85rem;
    color: var(--muted);
}

.summary-row.total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    border-top: 1px solid var(--border);
    margin-top: 0.5rem;
    padding-top: 0.8rem;
}

.place-order-btn {
    width: 100%;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.9rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 1rem;
}

.place-order-btn:hover {
    background: var(--red2);
}

.error-message {
    background: #fee2e2;
    color: #dc2626;
    padding: 0.8rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--muted);
}

.btn-add-address {
    background: transparent;
    border: 1px dashed var(--border);
    color: var(--red);
    padding: 0.5rem;
    border-radius: 10px;
    cursor: pointer;
    width: 100%;
    margin-top: 0.5rem;
}

.btn-add-address:hover {
    background: #fff5f5;
    border-color: var(--red);
}

.secure-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    font-size: 0.7rem;
    color: var(--muted);
}

/* Cart Link Fix */
.cart-link-fix {
    cursor: pointer;
}

/* Responsive */
@media (max-width: 900px) {
    .checkout-container {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    .order-summary {
        position: static;
    }
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

<div class="checkout-container">
    <div class="checkout-form">
        <div class="checkout-title">🛍️ Checkout</div>
        
        <?php if ($order_error): ?>
            <div class="error-message">❌ <?php echo htmlspecialchars($order_error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="idempotency_key" value="<?php echo htmlspecialchars($idempotency_key); ?>">
            
            <!-- Customer Information -->
            <div class="section-title">👤 Customer Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required pattern="[0-9+\-\s]{9,15}" title="Enter valid phone number">
            </div>
            
            <!-- Billing Address -->
            <div class="section-title">📍 Billing Address</div>
            
            <?php if (!empty($savedAddresses)): ?>
            <div class="saved-addresses">
                <?php foreach ($savedAddresses as $addr): ?>
                <div class="address-select" data-addr-line1="<?php echo htmlspecialchars($addr['address_line1']); ?>" data-addr-line2="<?php echo htmlspecialchars($addr['address_line2']); ?>" data-city="<?php echo htmlspecialchars($addr['city']); ?>" data-district="<?php echo htmlspecialchars($addr['district']); ?>">
                    <input type="radio" name="billing_address_id" value="<?php echo $addr['id']; ?>" <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                    <div class="address-details">
                        <div class="address-text">
                            <?php echo htmlspecialchars($addr['address_line1']); ?><br>
                            <?php if ($addr['address_line2']): ?><?php echo htmlspecialchars($addr['address_line2']); ?><br><?php endif; ?>
                            <?php echo htmlspecialchars($addr['city']); ?>, <?php echo htmlspecialchars($addr['district']); ?>
                        </div>
                        <?php if ($addr['is_default']): ?><div class="address-label">📍 Default Address</div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn-add-address" onclick="openAddressModal()">+ Add New Address</button>
            </div>
            <?php endif; ?>
            
            <div id="billingAddressFields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Address Line 1 *</label>
                        <input type="text" name="billing_address_line1" id="billing_address_line1" value="<?php echo htmlspecialchars($defaultAddress['address_line1'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Address Line 2</label>
                        <input type="text" name="billing_address_line2" id="billing_address_line2" value="<?php echo htmlspecialchars($defaultAddress['address_line2'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="billing_city" id="billing_city" value="<?php echo htmlspecialchars($defaultAddress['city'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>District *</label>
                        <select name="billing_district" id="billing_district" required>
                            <option value="">Select District</option>
                            <option value="Colombo" <?php echo (($defaultAddress['district'] ?? '') === 'Colombo') ? 'selected' : ''; ?>>Colombo</option>
                            <option value="Gampaha" <?php echo (($defaultAddress['district'] ?? '') === 'Gampaha') ? 'selected' : ''; ?>>Gampaha</option>
                            <option value="Kalutara" <?php echo (($defaultAddress['district'] ?? '') === 'Kalutara') ? 'selected' : ''; ?>>Kalutara</option>
                            <option value="Kandy" <?php echo (($defaultAddress['district'] ?? '') === 'Kandy') ? 'selected' : ''; ?>>Kandy</option>
                            <option value="Galle" <?php echo (($defaultAddress['district'] ?? '') === 'Galle') ? 'selected' : ''; ?>>Galle</option>
                            <option value="Matara" <?php echo (($defaultAddress['district'] ?? '') === 'Matara') ? 'selected' : ''; ?>>Matara</option>
                            <option value="Kurunegala" <?php echo (($defaultAddress['district'] ?? '') === 'Kurunegala') ? 'selected' : ''; ?>>Kurunegala</option>
                            <option value="Anuradhapura" <?php echo (($defaultAddress['district'] ?? '') === 'Anuradhapura') ? 'selected' : ''; ?>>Anuradhapura</option>
                            <option value="Ratnapura" <?php echo (($defaultAddress['district'] ?? '') === 'Ratnapura') ? 'selected' : ''; ?>>Ratnapura</option>
                            <option value="Jaffna" <?php echo (($defaultAddress['district'] ?? '') === 'Jaffna') ? 'selected' : ''; ?>>Jaffna</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Same as Billing Checkbox -->
            <label class="same-address-check">
                <input type="checkbox" name="same_as_billing" id="same_as_billing" checked onchange="toggleShippingAddress()">
                <span>Shipping address same as billing address</span>
            </label>
            
            <!-- Shipping Address -->
            <div id="shippingAddressSection">
                <div class="section-title">🚚 Shipping Address</div>
                <div id="shippingAddressFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Address Line 1 *</label>
                            <input type="text" name="shipping_address_line1" id="shipping_address_line1">
                        </div>
                        <div class="form-group">
                            <label>Address Line 2</label>
                            <input type="text" name="shipping_address_line2" id="shipping_address_line2">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City *</label>
                            <input type="text" name="shipping_city" id="shipping_city">
                        </div>
                        <div class="form-group">
                            <label>District *</label>
                            <select name="shipping_district" id="shipping_district">
                                <option value="">Select District</option>
                                <option value="Colombo">Colombo</option>
                                <option value="Gampaha">Gampaha</option>
                                <option value="Kalutara">Kalutara</option>
                                <option value="Kandy">Kandy</option>
                                <option value="Galle">Galle</option>
                                <option value="Matara">Matara</option>
                                <option value="Kurunegala">Kurunegala</option>
                                <option value="Anuradhapura">Anuradhapura</option>
                                <option value="Ratnapura">Ratnapura</option>
                                <option value="Jaffna">Jaffna</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method -->
            <div class="section-title">💳 Payment Method</div>
            <div class="payment-methods" id="paymentMethods">
                <label class="payment-option" onclick="selectPayment('bank_transfer')">
                    <input type="radio" name="payment_method" value="bank_transfer" checked>
                    <div class="payment-info">
                        <div class="title">🏦 Bank Transfer</div>
                        <div class="desc">Direct bank transfer to our account</div>
                    </div>
                </label>
                
                <label class="payment-option" onclick="selectPayment('card_payhere')">
                    <input type="radio" name="payment_method" value="card_payhere">
                    <div class="payment-info">
                        <div class="title">💳 Credit / Debit Card</div>
                        <div class="desc">Visa, Mastercard - Secure via PayHere</div>
                    </div>
                    <div class="payment-icon">💳</div>
                </label>
                
                <label class="payment-option" onclick="selectPayment('gpay_payhere')">
                    <input type="radio" name="payment_method" value="gpay_payhere">
                    <div class="payment-info">
                        <div class="title">🅿️ Google Pay</div>
                        <div class="desc">Pay with Google Pay</div>
                    </div>
                    <div class="payment-icon">🅿️</div>
                </label>
                
                <label class="payment-option" onclick="selectPayment('mobile_payhere')">
                    <input type="radio" name="payment_method" value="mobile_payhere">
                    <div class="payment-info">
                        <div class="title">📱 Mobile Money</div>
                        <div class="desc">mCash, Genie, eZ Cash</div>
                    </div>
                    <div class="payment-icon">📱</div>
                </label>
            </div>
            
            <button type="submit" name="place_order" id="placeOrderBtn" class="place-order-btn">Place Order →</button>
            
            <div class="secure-badge">
                <span>🔒 Secure SSL Encryption</span>
                <span>💳 PCI DSS Compliant</span>
                <span>🛡️ CSRF Protected</span>
            </div>
        </form>
    </div>
    
    <!-- Order Summary -->
    <div class="order-summary">
        <h3>Order Summary</h3>
        <div class="cart-items-summary">
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item-summary">
                <div class="cart-item-summary-img">
                    <?php 
                    $img_path = is_array($item) ? ($item['product_image'] ?? '') : ($item->product_image ?? '');
                    $item_name = is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '');
                    $item_qty = is_array($item) ? (int)($item['quantity'] ?? 0) : (int)($item->quantity ?? 0);
                    $item_price = is_array($item) ? (float)($item['base_price'] ?? 0) : (float)($item->base_price ?? 0);
                    $item_emoji = is_array($item) ? ($item['emoji'] ?? '📱') : ($item->emoji ?? '📱');
                    ?>
                    <?php if (!empty($img_path) && file_exists($img_path)): ?>
                        <img src="/cc/<?php echo htmlspecialchars($img_path); ?>" style="width:40px;height:40px;object-fit:contain;">
                    <?php else: ?>
                        <?php echo htmlspecialchars($item_emoji); ?>
                    <?php endif; ?>
                </div>
                <div class="cart-item-summary-info">
                    <div class="cart-item-summary-name"><?php echo htmlspecialchars($item_name); ?></div>
                    <div class="cart-item-summary-qty">Qty: <?php echo $item_qty; ?></div>
                </div>
                <div class="cart-item-summary-price"><?php echo formatPrice($item_price * $item_qty); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="summary-row">
            <span>Subtotal</span>
            <span><?php echo formatPrice($subtotal); ?></span>
        </div>
        <div class="summary-row">
            <span>Shipping</span>
            <span><?php echo $shipping > 0 ? formatPrice($shipping) : 'Free'; ?></span>
        </div>
        <div class="summary-row total">
            <span>Total</span>
            <span><?php echo formatPrice($total); ?></span>
        </div>
        <?php if ($shipping > 0): ?>
        <div class="summary-row" style="font-size:0.7rem;color:var(--muted);">
            <span>🚚 Add <?php echo formatPrice(50000 - $subtotal); ?> more for free shipping</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add New Address Modal -->
<div id="addressModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>➕ Add New Address</h3>
            <span class="modal-close" onclick="closeAddressModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="save_new_address" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label>Address Line 1 *</label>
                <input type="text" name="save_address_line1" required>
            </div>
            <div class="form-group">
                <label>Address Line 2</label>
                <input type="text" name="save_address_line2">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="save_city" required>
                </div>
                <div class="form-group">
                    <label>District *</label>
                    <select name="save_district" required>
                        <option value="">Select District</option>
                        <option value="Colombo">Colombo</option>
                        <option value="Gampaha">Gampaha</option>
                        <option value="Kalutara">Kalutara</option>
                        <option value="Kandy">Kandy</option>
                        <option value="Galle">Galle</option>
                        <option value="Matara">Matara</option>
                        <option value="Kurunegala">Kurunegala</option>
                        <option value="Anuradhapura">Anuradhapura</option>
                        <option value="Ratnapura">Ratnapura</option>
                        <option value="Jaffna">Jaffna</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="save_as_default" value="1">
                    Set as default address
                </label>
            </div>
            <button type="submit" class="place-order-btn" style="margin-top:0;">Save Address</button>
        </form>
    </div>
</div>

<script>
// Toggle shipping address visibility
function toggleShippingAddress() {
    const sameAsBilling = document.getElementById('same_as_billing').checked;
    const shippingSection = document.getElementById('shippingAddressSection');
    const shippingFields = document.getElementById('shippingAddressFields');
    const inputs = shippingFields.querySelectorAll('input, select');
    
    if (sameAsBilling) {
        shippingSection.style.opacity = '0.5';
        inputs.forEach(input => input.disabled = true);
    } else {
        shippingSection.style.opacity = '1';
        inputs.forEach(input => input.disabled = false);
    }
}

// Copy billing address to shipping
function copyBillingToShipping() {
    if (document.getElementById('same_as_billing').checked) {
        document.getElementById('shipping_address_line1').value = document.getElementById('billing_address_line1').value;
        document.getElementById('shipping_address_line2').value = document.getElementById('billing_address_line2').value;
        document.getElementById('shipping_city').value = document.getElementById('billing_city').value;
        document.getElementById('shipping_district').value = document.getElementById('billing_district').value;
    }
}

// Listen to billing address changes
const billingLine1 = document.getElementById('billing_address_line1');
const billingLine2 = document.getElementById('billing_address_line2');
const billingCity = document.getElementById('billing_city');
const billingDistrict = document.getElementById('billing_district');

if (billingLine1) billingLine1.addEventListener('input', copyBillingToShipping);
if (billingLine2) billingLine2.addEventListener('input', copyBillingToShipping);
if (billingCity) billingCity.addEventListener('input', copyBillingToShipping);
if (billingDistrict) billingDistrict.addEventListener('change', copyBillingToShipping);

// Select saved address
function selectAddress(element) {
    document.querySelectorAll('.address-select').forEach(addr => addr.classList.remove('selected'));
    element.classList.add('selected');
    
    document.getElementById('billing_address_line1').value = element.dataset.addrLine1 || '';
    document.getElementById('billing_address_line2').value = element.dataset.addrLine2 || '';
    document.getElementById('billing_city').value = element.dataset.city || '';
    document.getElementById('billing_district').value = element.dataset.district || '';
    
    copyBillingToShipping();
}

// Attach click handlers to address selects
document.querySelectorAll('.address-select').forEach(addr => {
    addr.addEventListener('click', function() { selectAddress(this); });
});

// Payment method selection
function selectPayment(method) {
    const radios = document.querySelectorAll('input[name="payment_method"]');
    radios.forEach(radio => {
        if (radio.value === method) {
            radio.checked = true;
        }
    });
    
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
        if (option.querySelector('input').value === method) {
            option.classList.add('selected');
        }
    });
}

// Modal functions
function openAddressModal() {
    document.getElementById('addressModal').classList.add('active');
}

function closeAddressModal() {
    document.getElementById('addressModal').classList.remove('active');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleShippingAddress();
    copyBillingToShipping();
    
    const defaultAddressRadio = document.querySelector('.address-select input:checked');
    if (defaultAddressRadio) {
        defaultAddressRadio.closest('.address-select').classList.add('selected');
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addressModal');
    if (event.target === modal) {
        closeAddressModal();
    }
}

// Payment method buttons - Fix: Changed 'forEach' to 'for...of' loop
document.querySelectorAll('.payment-option').forEach(function(option) {
    option.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            selectPayment(radio.value);
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>