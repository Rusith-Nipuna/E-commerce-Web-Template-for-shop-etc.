<?php
// cart.php - Shopping Cart Page (Fixed Shipping: Free over 50,000)
require_once 'includes/config.php';
require_once 'includes/header.php';

$db = getDB();

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: account/login.php?redirect=cart.php');
    exit;
}

$user = getCurrentUser();

// Handle cart actions
$message = '';
$error = '';


// Check for payment failure
if (isset($_GET['payment_failed'])) {
    echo '<div style="background:#fee2e2; color:#dc2626; padding:1rem; border-radius:12px; margin-bottom:1rem; text-align:center;">
            ❌ Payment failed or was cancelled. Please try again.
          </div>';
}

if (isset($_GET['payment_error'])) {
    echo '<div style="background:#fee2e2; color:#dc2626; padding:1rem; border-radius:12px; margin-bottom:1rem; text-align:center;">
            ❌ An error occurred during payment. Please contact support.
          </div>';
}

// Add to cart (AJAX or direct)
if (isset($_POST['action'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $selected_storage = $_POST['storage'] ?? '';
    $selected_color = $_POST['color'] ?? '';
    
    if ($_POST['action'] === 'add') {
        // Check if product already in cart
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user['id'], $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $new_qty = $existing['quantity'] + $quantity;
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_qty, $existing['id']]);
            $message = 'Cart updated!';
        } else {
            // Add new item
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, selected_storage, selected_color) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $product_id, $quantity, $selected_storage, $selected_color]);
            $message = 'Product added to cart!';
        }
        header('Location: cart.php');
        exit;
    }
}

// Update cart (update quantity)
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $cart_id => $qty) {
        $qty = max(0, intval($qty));
        if ($qty > 0) {
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$qty, $cart_id, $user['id']]);
        } else {
            $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $user['id']]);
        }
    }
    header('Location: cart.php');
    exit;
}

// Remove item
if (isset($_GET['remove'])) {
    $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['remove'], $user['id']]);
    header('Location: cart.php');
    exit;
}

// Clear cart
if (isset($_GET['clear'])) {
    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    header('Location: cart.php');
    exit;
}

// Get cart items
$cartItems = $db->prepare("
    SELECT c.*, p.name, p.slug, p.base_price, p.product_image, p.emoji, p.stock_quantity, b.name as brand_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN brands b ON p.brand_id = b.id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$cartItems->execute([$user['id']]);
$cartItems = $cartItems->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['base_price'] * $item['quantity'];
}

// FIXED: Free shipping for orders over LKR 50,000 (was 5000)
$shipping = ($subtotal > 50000) ? 0 : 500;
$tax = $subtotal * 0.00; // 0% tax
$total = $subtotal + $shipping + $tax;

$pageTitle = 'Shopping Cart';
$activeBrand = '';
$hide_header_search = false;
?>

<div class="cart-page">
    <div class="cart-container">
        <h1 class="cart-title">🛍️ Your Shopping Cart</h1>
        
        <?php if ($message): ?>
            <div class="cart-message success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (empty($cartItems)): ?>
            <!-- Empty Cart -->
            <div class="cart-empty">
                <div class="empty-icon">🛒</div>
                <h2>Your Cart is Empty</h2>
                <p>Looks like you haven't added anything to your cart yet.<br>Explore our collection and start shopping!</p>
                <a href="category.php" class="btn-start-shopping">Start Shopping →</a>
            </div>
        <?php else: ?>
            <!-- Cart with items -->
            <form method="POST">
                <div class="cart-content">
                    <div class="cart-items">
                        <div class="cart-header">
                            <div class="cart-col-product">Product</div>
                            <div class="cart-col-price">Price</div>
                            <div class="cart-col-quantity">Quantity</div>
                            <div class="cart-col-total">Total</div>
                            <div class="cart-col-remove"></div>
                        </div>
                        
                        <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item">
                            <div class="cart-item-product">
                                <div class="cart-item-img">
                                    <?php if (!empty($item['product_image']) && file_exists('/cc/' . $item['product_image'])): ?>
                                        <img src="/cc/<?= $item['product_image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php else: ?>
                                        <span class="item-emoji"><?= $item['emoji'] ?? '📱' ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cart-item-info">
                                    <div class="item-brand"><?= htmlspecialchars($item['brand_name']) ?></div>
                                    <div class="item-name">
                                        <a href="product.php?slug=<?= $item['slug'] ?>"><?= htmlspecialchars($item['name']) ?></a>
                                    </div>
                                    <?php if ($item['selected_storage']): ?>
                                        <div class="item-variant">Storage: <?= htmlspecialchars($item['selected_storage']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($item['selected_color']): ?>
                                        <div class="item-variant">Color: <?= htmlspecialchars($item['selected_color']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="cart-item-price">
                                <?= formatPrice($item['base_price']) ?>
                            </div>
                            <div class="cart-item-quantity">
                                <input type="number" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?? 10 ?>" class="qty-input">
                            </div>
                            <div class="cart-item-total">
                                <?= formatPrice($item['base_price'] * $item['quantity']) ?>
                            </div>
                            <div class="cart-item-remove">
                                <a href="?remove=<?= $item['id'] ?>" class="remove-btn" onclick="return confirm('Remove this item?')">✕</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-actions">
                            <button type="submit" name="update_cart" class="btn-update">Update Cart</button>
                            <a href="?clear=1" class="btn-clear" onclick="return confirm('Clear all items from cart?')">Clear Cart</a>
                        </div>
                    </div>
                    
                    <div class="cart-summary">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?= formatPrice($subtotal) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span><?= $shipping > 0 ? formatPrice($shipping) : 'Free' ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?= formatPrice($total) ?></span>
                        </div>
                        
                        <?php if ($shipping > 0): ?>
                        <div class="shipping-note">
                            🚚 Free shipping on orders over LKR 50,000<br>
                            Add <?= formatPrice(50000 - $subtotal) ?> more to get free shipping!
                        </div>
                        <?php else: ?>
                        <div class="shipping-note" style="background: #dcfce7; color: #16a34a;">
                            🎉 You've qualified for FREE shipping!
                        </div>
                        <?php endif; ?>
                        
                        <a href="checkout.php" class="btn-checkout">Proceed to Checkout →</a>
                        <a href="category.php" class="btn-continue">Continue Shopping</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* Cart Page Styles */
.cart-page {
    background: var(--bg);
    min-height: calc(100vh - 200px);
    padding: 2rem 0;
}

.cart-container {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 2rem;
}

.cart-title {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 2rem;
    color: var(--text);
}

.cart-message {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.cart-message.success {
    background: #dcfce7;
    color: #16a34a;
    border-left: 3px solid #16a34a;
}

/* Empty Cart */
.cart-empty {
    text-align: center;
    padding: 4rem;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 18px;
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.cart-empty h2 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.cart-empty p {
    color: var(--muted);
    margin-bottom: 1.5rem;
}

.btn-start-shopping {
    display: inline-block;
    background: var(--red);
    color: #fff;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-start-shopping:hover {
    background: var(--red2);
}

/* Cart Content */
.cart-content {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
}

.cart-items {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.cart-header {
    display: grid;
    grid-template-columns: 3fr 1fr 1fr 1fr 50px;
    background: var(--bg2);
    padding: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    border-bottom: 1px solid var(--border);
}

.cart-item {
    display: grid;
    grid-template-columns: 3fr 1fr 1fr 1fr 50px;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.cart-item:hover {
    background: var(--bg2);
}

.cart-item-product {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.cart-item-img {
    width: 80px;
    height: 80px;
    background: var(--bg2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cart-item-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.item-emoji {
    font-size: 2.5rem;
}

.cart-item-info {
    flex: 1;
}

.item-brand {
    font-size: 0.7rem;
    color: var(--muted);
    text-transform: uppercase;
}

.item-name a {
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
    font-size: 0.9rem;
}

.item-name a:hover {
    color: var(--red);
}

.item-variant {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 0.2rem;
}

.cart-item-price {
    font-weight: 500;
    color: var(--text);
}

.qty-input {
    width: 70px;
    padding: 0.4rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
}

.cart-item-total {
    font-weight: 600;
    color: var(--red);
}

.remove-btn {
    color: var(--muted);
    text-decoration: none;
    font-size: 1.2rem;
    transition: color 0.2s;
}

.remove-btn:hover {
    color: var(--red);
}

.cart-actions {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg2);
    border-top: 1px solid var(--border);
}

.btn-update, .btn-clear {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-update {
    background: var(--text);
    color: #fff;
    border: none;
}

.btn-update:hover {
    background: var(--red);
}

.btn-clear {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
}

.btn-clear:hover {
    border-color: var(--red);
    color: var(--red);
}

/* Cart Summary */
.cart-summary {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 80px;
}

.cart-summary h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.8rem;
    font-size: 0.9rem;
    color: var(--muted);
}

.summary-row.total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    padding-top: 0.8rem;
    border-top: 1px solid var(--border);
    margin-top: 0.5rem;
}

.shipping-note {
    background: var(--bg2);
    padding: 0.8rem;
    border-radius: 8px;
    font-size: 0.75rem;
    color: var(--muted);
    margin: 1rem 0;
    text-align: center;
}

.btn-checkout {
    display: block;
    width: 100%;
    background: var(--red);
    color: #fff;
    text-align: center;
    padding: 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 0.8rem;
    transition: background 0.2s;
}

.btn-checkout:hover {
    background: var(--red2);
}

.btn-continue {
    display: block;
    width: 100%;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    text-align: center;
    padding: 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.btn-continue:hover {
    border-color: var(--red);
    color: var(--red);
}

/* Responsive */
@media (max-width: 900px) {
    .cart-content {
        grid-template-columns: 1fr;
    }
    
    .cart-summary {
        position: static;
    }
}

@media (max-width: 700px) {
    .cart-header {
        display: none;
    }
    
    .cart-item {
        grid-template-columns: 1fr;
        gap: 0.8rem;
        text-align: center;
    }
    
    .cart-item-product {
        justify-content: center;
    }
    
    .cart-item-price, .cart-item-quantity, .cart-item-total {
        text-align: center;
    }
    
    .cart-item-remove {
        text-align: center;
    }
    
    .cart-actions {
        justify-content: center;
    }
}

@media (max-width: 500px) {
    .cart-container {
        padding: 0 1rem;
    }
    
    .cart-title {
        font-size: 1.4rem;
    }
    
    .cart-item-img {
        width: 60px;
        height: 60px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>