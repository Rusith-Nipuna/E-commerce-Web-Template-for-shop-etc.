<?php
// payment_instructions.php - COMPLETE VERSION
require_once 'includes/config.php';
require_once 'includes/header.php';

$order_number = $_GET['order'] ?? '';
$db = getDB();

// Get order details to show amount
$order = null;
if ($order_number && isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();
}

$pageTitle = 'Payment Instructions';
$hide_header_search = true;
?>

<style>
.payment-instructions {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.instruction-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 2rem;
    text-align: center;
}

.instruction-card h2 {
    margin: 1rem 0 0.5rem;
}

.bank-details {
    background: var(--bg2);
    border-radius: 16px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    text-align: left;
}

.bank-details p {
    margin: 0.8rem 0;
    display: flex;
    flex-wrap: wrap;
}

.bank-details strong {
    width: 140px;
    color: var(--text);
}

.amount-box {
    background: #dcfce7;
    padding: 1rem;
    border-radius: 12px;
    margin: 1rem 0;
    text-align: center;
}

.amount-box .amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #16a34a;
}

.order-number {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--red);
    margin: 0.5rem 0;
    font-family: monospace;
}

.warning-note {
    background: #fef3c7;
    padding: 0.8rem;
    border-radius: 10px;
    margin: 1rem 0;
    font-size: 0.85rem;
    color: #92400e;
}

.btn-continue {
    display: inline-block;
    background: var(--red);
    color: #fff;
    padding: 0.8rem 2rem;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 1rem;
    transition: background 0.2s;
}

.btn-continue:hover {
    background: var(--red2);
}

.btn-whatsapp {
    display: inline-block;
    background: #25D366;
    color: #fff;
    padding: 0.8rem 2rem;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 1rem;
    margin-left: 0.5rem;
}

.btn-whatsapp:hover {
    background: #128C7E;
}

@media (max-width: 600px) {
    .bank-details p {
        flex-direction: column;
        gap: 0.3rem;
    }
    .bank-details strong {
        width: auto;
    }
}
</style>

<div class="payment-instructions">
    <div class="instruction-card">
        <div style="font-size: 3rem;">🏦</div>
        <h2>Bank Transfer Instructions</h2>
        <p style="color: var(--muted);">Complete your payment to confirm your order</p>
        
        <?php if ($order): ?>
        <div class="amount-box">
            <div style="font-size: 0.8rem; color: #6b6b67;">Amount to Pay</div>
            <div class="amount"><?php echo formatPrice($order['total_amount']); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="bank-details">
            <p><strong>🏦 Bank Name:</strong> Commercial Bank of Ceylon</p>
            <p><strong>📛 Account Name:</strong> RedZone Phone Gallery</p>
            <p><strong>🔢 Account Number:</strong> 123 456 7890</p>
            <p><strong>📍 Branch:</strong> Horana</p>
            <p><strong>📌 Bank Code:</strong> 7083</p>
        </div>
        
        <div class="warning-note">
            ⚠️ <strong>Important:</strong> Please use your Order Number as the reference when transferring.
        </div>
        
        <div class="order-number">Order #: <?php echo htmlspecialchars($order_number); ?></div>
        
        <p>After transferring, please WhatsApp your payment slip to <strong><?php echo SITE_PHONE; ?></strong></p>
        <p style="font-size: 0.75rem; color: var(--muted);">Your order will be confirmed within 2 hours.</p>
        
        <div>
            <a href="account/orders.php" class="btn-continue">📦 View My Orders</a>
            <a href="https://wa.me/<?php echo SITE_WHATSAPP; ?>?text=Payment%20for%20Order%20<?php echo urlencode($order_number); ?>&amount=<?php echo urlencode($order['total_amount'] ?? ''); ?>" 
               class="btn-whatsapp" target="_blank">
                📱 Send via WhatsApp
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>