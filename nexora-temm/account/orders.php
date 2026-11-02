<?php
// account/orders.php - My Orders Page
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get all orders for this user
$orders = $db->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");


$orders->execute([$user['id']]);
$orderList = $orders->fetchAll();

// Get order items for a specific order (view details)
$viewOrderId = $_GET['view'] ?? 0;
$orderItems = [];
$selectedOrder = null;

if ($viewOrderId) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$viewOrderId, $user['id']]);
    $selectedOrder = $stmt->fetch();
    
    if ($selectedOrder) {
        $stmt = $db->prepare("
            SELECT oi.*, p.name, p.slug, p.product_image, p.emoji
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$viewOrderId]);
        $orderItems = $stmt->fetchAll();
    }
}

// Check for payment success message
if (isset($_GET['payment_success'])) {
    echo '<div style="background:#dcfce7; color:#16a34a; padding:1rem; border-radius:12px; margin-bottom:1rem; text-align:center;">
            ✅ Payment completed successfully! Your order has been confirmed.
          </div>';
}

if (isset($_GET['payment_pending'])) {
    echo '<div style="background:#fef3c7; color:#d97706; padding:1rem; border-radius:12px; margin-bottom:1rem; text-align:center;">
            ⏳ Payment is pending. We will notify you once confirmed.
          </div>';
}

$pageTitle = 'My Orders';
$activeBrand = '';
$hide_header_search = true;

require_once '../includes/header.php';
?>

<div class="account-container">
    <!-- Sidebar -->
    <div class="account-sidebar">
        <div class="user-info">
            <div class="user-avatar">👤</div>
            <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="account-menu">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="profile.php">✏️ Edit Profile</a>
            <a href="orders.php" class="active">📦 My Orders</a>
            <a href="wishlist.php">❤️ Wishlist</a>
            <a href="addresses.php">📍 Addresses</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="account-content">
        <div class="account-title">
            <span>📦 My Orders</span>
        </div>
        
        <?php if ($viewOrderId && $selectedOrder): ?>
            <!-- Order Details View -->
            <div class="order-details">
                <div class="order-details-header">
                    <div>
                        <h3>Order #<?= $selectedOrder['id'] ?></h3>
                        <p>Placed on <?= date('F j, Y', strtotime($selectedOrder['created_at'])) ?></p>
                    </div>
                    <div class="order-status-badge status-<?= $selectedOrder['order_status'] ?? 'pending' ?>">
                        <?= ucfirst($selectedOrder['order_status'] ?? 'Pending') ?>
                    </div>
                    <a href="orders.php" class="back-btn">← Back to Orders</a>
                </div>
                
                <div class="order-summary">
                    <div class="summary-card">
                        <h4>Shipping Address</h4>
                        <p><?= nl2br(htmlspecialchars($selectedOrder['shipping_address'] ?? 'Not specified')) ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Payment Method</h4>
                        <p><?= ucfirst(str_replace('_', ' ', $selectedOrder['payment_method'] ?? 'Cash on Delivery')) ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Payment Status</h4>
                        <p class="payment-status payment-<?= $selectedOrder['payment_status'] ?? 'pending' ?>">
                            <?= ucfirst($selectedOrder['payment_status'] ?? 'Pending') ?>
                        </p>
                    </div>
                </div>
                
                <div class="order-items">
                    <h4>Order Items</h4>
                    <div class="items-list">
                        <?php foreach ($orderItems as $item): ?>
                        <div class="order-item">
                            <div class="order-item-img">
                                <?php if (!empty($item['product_image']) && file_exists('/cc/' . $item['product_image'])): ?>
                                    <img src="/cc/<?= $item['product_image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                <?php else: ?>
                                    <span><?= $item['emoji'] ?? '📱' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="order-item-info">
                                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="item-meta">Quantity: <?= $item['quantity'] ?></div>
                            </div>
                            <div class="order-item-price">
                                <?= formatPrice($item['price']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="order-total">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span><?= formatPrice($selectedOrder['total_amount']) ?></span>
                    </div>
                    <div class="total-row">
                        <span>Shipping</span>
                        <span>Free</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total</span>
                        <span><?= formatPrice($selectedOrder['total_amount']) ?></span>
                    </div>
                </div>
            </div>
            
        <?php elseif (empty($orderList)): ?>
            <!-- Empty Orders -->
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>No orders yet</h3>
                <p>You haven't placed any orders yet</p>
                <a href="/cc/category.php" class="btn-shop">Start Shopping →</a>
            </div>
            
        <?php else: ?>
            <!-- Orders List -->
            <div class="orders-list">
                <?php foreach ($orderList as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <span class="order-number">Order #<?= $order['id'] ?></span>
                            <span class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="order-status status-<?= $order['order_status'] ?? 'pending' ?>">
                            <?= ucfirst($order['order_status'] ?? 'Pending') ?>
                        </div>
                    </div>
                    <div class="order-body">
                        <div class="order-summary-info">
                            <span>📦 <?= $order['total_items'] ?? 0 ?> item(s)</span>
                            <span>💰 <?= formatPrice($order['total_amount']) ?></span>
                        </div>
                        <a href="?view=<?= $order['id'] ?>" class="btn-view-order">View Details →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Account Container */
.account-container {
    display: flex;
    max-width: 1300px;
    margin: 2rem auto;
    padding: 0 2rem;
    gap: 2rem;
}

.account-sidebar {
    width: 280px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    height: fit-content;
}

.account-sidebar .user-info {
    text-align: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 1rem;
}

.account-sidebar .user-avatar {
    width: 80px;
    height: 80px;
    background: var(--bg2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 0.8rem;
}

.account-sidebar .user-name {
    font-weight: 600;
    font-size: 1rem;
}

.account-sidebar .user-email {
    font-size: 0.7rem;
    color: var(--muted);
    word-break: break-all;
}

.account-menu {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.account-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    color: var(--muted);
    font-weight: 500;
    transition: all 0.2s;
}

.account-menu a:hover,
.account-menu a.active {
    background: var(--bg2);
    color: var(--red);
}

.account-content {
    flex: 1;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
}

.account-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.empty-state p {
    color: var(--muted);
    margin-bottom: 1.5rem;
}

.btn-shop {
    display: inline-block;
    background: var(--red);
    color: #fff;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
}

.btn-shop:hover {
    background: var(--red2);
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.2s;
}

.order-card:hover {
    border-color: var(--border2);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 0.8rem;
}

.order-number {
    font-weight: 600;
    color: var(--text);
}

.order-date {
    font-size: 0.75rem;
    color: var(--muted);
    margin-left: 1rem;
}

.order-status {
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-weight: 600;
}

.status-pending {
    background: #fef3c7;
    color: #d97706;
}

.status-processing {
    background: #e0f2fe;
    color: #0284c7;
}

.status-shipped {
    background: #dbeafe;
    color: #2563eb;
}

.status-delivered {
    background: #dcfce7;
    color: #16a34a;
}

.status-cancelled {
    background: #fee2e2;
    color: #dc2626;
}

.order-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.order-summary-info {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--muted);
}

.btn-view-order {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.75rem;
    transition: all 0.2s;
}

.btn-view-order:hover {
    border-color: var(--red);
    color: var(--red);
}

/* Order Details Page */
.order-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.order-details-header h3 {
    font-size: 1.1rem;
    color: var(--text);
}

.order-details-header p {
    font-size: 0.75rem;
    color: var(--muted);
}

.back-btn {
    background: var(--bg2);
    border: 1px solid var(--border);
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    color: var(--muted);
    font-size: 0.8rem;
}

.back-btn:hover {
    border-color: var(--red);
    color: var(--red);
}

.order-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: var(--bg2);
    padding: 1rem;
    border-radius: 10px;
}

.summary-card h4 {
    font-size: 0.75rem;
    color: var(--muted);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}

.summary-card p {
    font-size: 0.85rem;
    color: var(--text);
}

.payment-status {
    font-weight: 600;
}

.payment-paid {
    color: #16a34a;
}

.payment-pending {
    color: #d97706;
}

.payment-failed {
    color: #dc2626;
}

.order-items {
    margin-bottom: 2rem;
}

.order-items h4 {
    font-size: 1rem;
    margin-bottom: 1rem;
}

.items-list {
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.order-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.order-item:last-child {
    border-bottom: none;
}

.order-item-img {
    width: 60px;
    height: 60px;
    background: var(--bg2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.order-item-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.order-item-info {
    flex: 1;
}

.item-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.item-meta {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 0.2rem;
}

.order-item-price {
    font-weight: 600;
    color: var(--red);
}

.order-total {
    text-align: right;
    padding: 1rem;
    background: var(--bg2);
    border-radius: 10px;
    max-width: 300px;
    margin-left: auto;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.grand-total {
    font-weight: 700;
    font-size: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border);
    margin-top: 0.5rem;
}

/* Responsive */
@media (max-width: 800px) {
    .account-container {
        flex-direction: column;
        padding: 1rem;
    }
    .account-sidebar {
        width: 100%;
    }
    .order-summary {
        grid-template-columns: 1fr;
    }
    .order-item {
        flex-wrap: wrap;
    }
    .order-total {
        max-width: 100%;
    }
}

@media (max-width: 500px) {
    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .order-body {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>