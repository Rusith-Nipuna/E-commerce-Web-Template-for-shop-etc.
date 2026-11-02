<?php
// account/wishlist.php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get wishlist items
$wishlist = $db->prepare("
    SELECT w.*, p.name, p.slug, p.base_price, p.old_price, p.product_image, p.emoji, b.name as brand_name
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    JOIN brands b ON p.brand_id = b.id
    WHERE w.user_id = ? AND p.active = 1
    ORDER BY w.added_at DESC
");
$wishlist->execute([$user['id']]);
$wishlistItems = $wishlist->fetchAll();

// Remove from wishlist
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    $db->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?")->execute([$remove_id, $user['id']]);
    header('Location: wishlist.php');
    exit;
}

// Clear all wishlist
if (isset($_GET['clear'])) {
    $db->prepare("DELETE FROM wishlist WHERE user_id = ?")->execute([$user['id']]);
    header('Location: wishlist.php');
    exit;
}

$pageTitle = 'My Wishlist';
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
            <a href="orders.php">📦 My Orders</a>
            <a href="wishlist.php" class="active">❤️ Wishlist</a>
            <a href="addresses.php">📍 Addresses</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="account-content">
        <div class="account-title">
            <span>❤️ My Wishlist</span>
            <?php if (!empty($wishlistItems)): ?>
                <a href="?clear=1" class="clear-all" onclick="return confirm('Remove all items from wishlist?')">Clear All</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($wishlistItems)): ?>
            <div class="empty-state">
                <div class="empty-icon">❤️</div>
                <h3>Your wishlist is empty</h3>
                <p>Save your favorite items here</p>
                <a href="/cc/category.php" class="btn-shop">Start Shopping →</a>
            </div>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlistItems as $item): ?>
                <div class="wishlist-item">
                    <div class="wishlist-item-img">
                        <?php if (!empty($item['product_image']) && file_exists('/cc/' . $item['product_image'])): ?>
                            <img src="/cc/<?= $item['product_image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        <?php else: ?>
                            <span class="item-emoji"><?= $item['emoji'] ?? '📱' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="wishlist-item-info">
                        <div class="item-brand"><?= htmlspecialchars($item['brand_name']) ?></div>
                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="item-price">
                            <span class="current-price"><?= formatPrice($item['base_price']) ?></span>
                            <?php if ($item['old_price'] && $item['old_price'] > $item['base_price']): ?>
                                <span class="old-price"><?= formatPrice($item['old_price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wishlist-item-actions">
                        <a href="/cc/product.php?slug=<?= $item['slug'] ?>" class="btn-view">View Details</a>
                        <a href="?remove=<?= $item['id'] ?>" class="btn-remove" onclick="return confirm('Remove from wishlist?')">Remove</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.clear-all {
    font-size: 0.8rem;
    color: var(--red);
    text-decoration: none;
    font-weight: 500;
}

.clear-all:hover {
    text-decoration: underline;
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

/* Wishlist Grid */
.wishlist-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.wishlist-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.2s;
}

.wishlist-item:hover {
    border-color: var(--border2);
    background: var(--bg2);
}

.wishlist-item-img {
    width: 80px;
    height: 80px;
    background: var(--bg2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wishlist-item-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.item-emoji {
    font-size: 2.5rem;
}

.wishlist-item-info {
    flex: 1;
}

.item-brand {
    font-size: 0.7rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.item-name {
    font-weight: 500;
    font-size: 1rem;
    margin: 0.2rem 0;
}

.item-price {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.current-price {
    font-weight: 600;
    color: var(--red);
}

.old-price {
    font-size: 0.8rem;
    color: var(--muted);
    text-decoration: line-through;
}

.wishlist-item-actions {
    display: flex;
    gap: 0.8rem;
}

.btn-view {
    background: var(--text);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
}

.btn-view:hover {
    background: var(--red);
}

.btn-remove {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.8rem;
}

.btn-remove:hover {
    border-color: var(--red);
    color: var(--red);
}

@media (max-width: 800px) {
    .account-container {
        flex-direction: column;
        padding: 1rem;
    }
    .account-sidebar {
        width: 100%;
    }
    .wishlist-item {
        flex-wrap: wrap;
    }
    .wishlist-item-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 500px) {
    .wishlist-item {
        flex-direction: column;
        text-align: center;
    }
    .wishlist-item-actions {
        justify-content: center;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>