<?php
// category.php - WITH WORKING WISHLIST BUTTON
require_once 'includes/config.php';
require_once 'includes/header.php';

$db = getDB();
$brandSlug = $_GET['brand'] ?? '';
$sort = $_GET['sort'] ?? 'default';

// Get brand
$brand = null;
if ($brandSlug) {
    $stmt = $db->prepare("SELECT * FROM brands WHERE slug = ? AND active = 1");
    $stmt->execute([$brandSlug]);
    $brand = $stmt->fetch();
}

// Get all brands for sidebar
$allBrands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY sort_order")->fetchAll();

// Sort order
$orderBy = match($sort) {
    'price-low' => 'p.base_price ASC',
    'price-high' => 'p.base_price DESC',
    'name' => 'p.name ASC',
    default => 'p.id DESC'
};

// Get products
if ($brand) {
    $stmt = $db->prepare("
        SELECT p.*, b.name as brand_name, b.slug as brand_slug
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE p.brand_id = ? AND p.active = 1
        ORDER BY $orderBy
    ");
    $stmt->execute([$brand['id']]);
    $products = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT p.*, b.name as brand_name, b.slug as brand_slug
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE p.active = 1
        ORDER BY $orderBy
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
}

// Get user's wishlist items (if logged in)
$wishlistIds = [];
if (isLoggedIn()) {
    $user = getCurrentUser();
    $wishlistStmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wishlistStmt->execute([$user['id']]);
    $wishlistIds = $wishlistStmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = $brand ? $brand['name'] . ' Phones' : 'All Products';
$activeBrand = $brandSlug;
?>

<!-- BREADCRUMB -->
<div class="breadcrumb-bar">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="sep">›</span>
        <?php if ($brand): ?>
            <span class="current"><?= e($brand['name']) ?> Page </span>
        <?php else: ?>
            <span class="current">All Products</span>
        <?php endif; ?>
    </div>
</div>

<div class="page-wrap">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-title">Browse by Brand</div>
            <div class="sidebar-links">
                <a href="category.php" class="<?= !$brandSlug ? 'active' : '' ?>">All Products</a>
                <?php foreach ($allBrands as $b): ?>
                <a href="category.php?brand=<?= e($b['slug']) ?>" 
                   class="<?= $brandSlug === $b['slug'] ? 'active' : '' ?>">
                    <?= e($b['emoji']) ?> <?= e($b['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="cat-header">
            <div>
                <div class="cat-title">
                    <?php if ($brand): ?>
                        <b><?= e($brand['name']) ?></b>
                    <?php else: ?>
                        <b>All</b> Products
                    <?php endif; ?>
                </div>
                <div class="cat-meta"><?= count($products) ?> products found</div>
            </div>
            <div class="sort-row">
                <span class="sort-label">Sort by</span>
                <select class="sort-select" onchange="location.href=this.value">
                    <?php
                    $baseUrl = 'category.php' . ($brandSlug ? "?brand=$brandSlug&" : '?') . 'sort=';
                    $sorts = ['default' => 'Featured', 'price-low' => 'Price: Low to High', 
                              'price-high' => 'Price: High to Low', 'name' => 'Name A–Z'];
                    foreach ($sorts as $key => $label):
                    ?>
                    <option value="<?= $baseUrl . $key ?>" <?= $sort === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- PRODUCT GRID -->
        <?php if (empty($products)): ?>
        <div style="text-align: center; padding: 4rem; color: var(--muted);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
            <p>No products found in this category.</p>
        </div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $p): ?>
            <div class="product-card">
                <?php if ($p['badge']): ?>
                <span class="card-badge <?= strtolower($p['badge']) === 'hot' ? 'badge-hot' : (strtolower($p['badge']) === 'best value' ? 'badge-off' : 'badge-new') ?>">
                    <?= e($p['badge']) ?>
                </span>
                <?php endif; ?>
                
                <!-- WISHLIST BUTTON - TOP RIGHT CORNER -->
                <button class="wishlist-btn <?= in_array($p['id'], $wishlistIds) ? 'active' : '' ?>" 
                        onclick="event.stopPropagation(); addToWishlist(<?= $p['id'] ?>, this)">
                    <?= in_array($p['id'], $wishlistIds) ? '❤️' : '🤍' ?>
                </button>
                
                <!-- PRODUCT IMAGE (Click to view product) -->
                <div class="card-img" onclick="location.href='product.php?slug=<?= e($p['slug'])?>'">
                    <?php 
                    $product_image = $p['product_image'] ?? '';
                    $image_full_path = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $product_image;
                    ?>
                    <?php if (!empty($product_image) && file_exists($image_full_path)): ?>
                        <img src="/cc/<?= $product_image ?>" 
                             alt="<?= htmlspecialchars($p['name']) ?>" 
                             style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <span style="font-size: 5rem;"><?= $p['emoji'] ?? '📱' ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- PRODUCT INFO -->
                <div class="card-brand"><?= e($p['brand_name']) ?></div>
                <div class="card-name" onclick="location.href='product.php?slug=<?= e($p['slug'])?>'"><?= e($p['name']) ?></div>
                <div class="card-starting">Starting at</div>
                <div class="card-price-row">
                    <span class="card-price"><?= formatPrice($p['base_price']) ?></span>
                    <?php if ($p['old_price'] && $p['old_price'] > $p['base_price']): ?>
                    <span class="card-price-old"><?= formatPrice($p['old_price']) ?></span>
                    <span class="card-discount">
                        <?= round((1 - $p['base_price'] / $p['old_price']) * 100) ?>% OFF
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- VIEW DETAILS BUTTON -->
                <button class="card-btn" onclick="location.href='product.php?slug=<?= e($p['slug'])?>'">View Details →</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Wishlist Button Styles - Top Right Corner */
.product-card {
    position: relative;
}

.wishlist-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid var(--border);
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.wishlist-btn:hover {
    transform: scale(1.1);
    border-color: var(--red);
}

.wishlist-btn.active {
    background: #fff5f5;
    border-color: var(--red);
    color: var(--red);
}
</style>

<script>
// ============================================
// WISHLIST FUNCTIONALITY
// ============================================

function addToWishlist(productId, buttonElement) {
    <?php if (!isset($_SESSION['user_id'])): ?>
        if (confirm('Please login to add items to wishlist. Go to login page?')) {
            window.location.href = '/cc/account/login.php';
        }
        return;
    <?php endif; ?>
    
    // Show loading state
    const originalText = buttonElement.innerHTML;
    buttonElement.innerHTML = '⏳';
    buttonElement.disabled = true;
    
    fetch('/cc/wishlist_add.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                buttonElement.innerHTML = '❤️';
                buttonElement.classList.add('active');
                showNotification('❤️ Added to wishlist!', 'success');
            } else {
                buttonElement.innerHTML = '🤍';
                buttonElement.classList.remove('active');
                showNotification('💔 Removed from wishlist!', 'info');
            }
        } else {
            buttonElement.innerHTML = originalText;
            showNotification('❌ ' + data.message, 'error');
        }
        buttonElement.disabled = false;
    })
    .catch(error => {
        buttonElement.innerHTML = originalText;
        buttonElement.disabled = false;
        showNotification('❌ Failed to update wishlist', 'error');
    });
}

// Notification function
function showNotification(message, type) {
    // Remove existing notification
    const existingNotification = document.querySelector('.wishlist-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `wishlist-notification ${type}`;
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#16a34a' : (type === 'error' ? '#dc2626' : '#3b82f6')};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<script>
// Global wishlist toggle function
function toggleWishlist(productId, buttonElement) {
    <?php if (!isset($_SESSION['user_id'])): ?>
        if (confirm('Please login to add items to wishlist. Go to login page?')) {
            window.location.href = '/cc/account/login.php';
        }
        return;
    <?php endif; ?>
    
    const originalText = buttonElement.innerHTML;
    buttonElement.innerHTML = '⏳';
    buttonElement.disabled = true;
    
    fetch('/cc/wishlist_add.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                buttonElement.innerHTML = '❤️';
                buttonElement.classList.add('active');
                showWishlistNotification('❤️ Added to wishlist!', 'success');
            } else {
                buttonElement.innerHTML = '🤍';
                buttonElement.classList.remove('active');
                showWishlistNotification('💔 Removed from wishlist!', 'info');
            }
        } else {
            buttonElement.innerHTML = originalText;
            showWishlistNotification('❌ ' + data.message, 'error');
        }
        buttonElement.disabled = false;
    })
    .catch(error => {
        buttonElement.innerHTML = originalText;
        buttonElement.disabled = false;
        showWishlistNotification('❌ Failed to update wishlist', 'error');
    });
}

function showWishlistNotification(message, type) {
    const existingNotification = document.querySelector('.wishlist-notification');
    if (existingNotification) existingNotification.remove();
    
    const notification = document.createElement('div');
    notification.className = 'wishlist-notification';
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#16a34a' : (type === 'error' ? '#dc2626' : '#3b82f6')};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animation styles if not exists
if (!document.querySelector('#wishlist-animation-styles')) {
    const style = document.createElement('style');
    style.id = 'wishlist-animation-styles';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>