<?php
// index.php - WITH SLIDER (Multiple Banners from Database, Auto Slide)
require_once 'includes/config.php';
require_once 'includes/header.php';

$db = getDB();

// Get ALL active banners from database (for slider)
$banners = $db->query("
    SELECT * FROM banners 
    WHERE active = 1 
    ORDER BY sort_order ASC 
")->fetchAll();

$totalBanners = count($banners);

// Get bestsellers (PHONES ONLY - NOT accessories)
$bestsellers = $db->query("
    SELECT p.*, b.name as brand_name 
    FROM products p 
    JOIN brands b ON p.brand_id = b.id 
    WHERE p.active = 1 
    AND b.slug != 'accessories'
    ORDER BY p.id DESC 
    LIMIT 8
")->fetchAll();

// Get accessories (ONLY from accessories brand)
$accessories = $db->query("
    SELECT p.*, b.name as brand_name 
    FROM products p 
    JOIN brands b ON p.brand_id = b.id 
    WHERE b.slug = 'accessories' AND p.active = 1 
    ORDER BY p.id DESC 
    LIMIT 8
")->fetchAll();

// Get brands for brand cards
$brands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY sort_order LIMIT 9")->fetchAll();

$activeBrand = '';
$pageTitle = 'Home';
?>

<!-- HERO SLIDER -->

<style>
    

.slider-wrap { position: relative; width: 100%; height: 580px; overflow: hidden; border-radius: 12px; background: #1a1a2e; margin-bottom: 2rem; }
.slides { display: flex; height: 100%; transition: transform 0.5s ease-in-out; }
.slide { flex: 0 0 100%; width: 100%; height: 100%; position: relative; }
.slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
.overlay { position: absolute; inset: 0; background: linear-gradient(to right, rgba(0,0,0,0.72) 40%, rgba(0,0,0,0.15) 100%); display: flex; align-items: center; padding: 2.5rem 3rem; }
.content { color: #fff; max-width: 380px; }
.brand-tag { font-size: 11px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #e53935; margin-bottom: 10px; }
.title { font-size: 2.2rem; font-weight: 800; line-height: 1.1; margin-bottom: 8px; font-family: Georgia, serif; }
.year { font-size: 13px; color: rgba(255,255,255,0.7); margin-bottom: 12px; letter-spacing: 2px; }
.badge { display: inline-block; background: rgba(229,57,53,0.25); border: 1px solid rgba(229,57,53,0.5); color: #ff8a80; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-bottom: 14px; letter-spacing: 1px; text-transform: uppercase; }
.desc { font-size: 13px; color: rgba(255,255,255,0.75); margin-bottom: 20px; line-height: 1.6; }
.btn { display: inline-block; background: #e53935; color: #fff; padding: 10px 24px; border-radius: 30px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; letter-spacing: 0.5px; transition: background 0.2s; text-decoration: none; }
.btn:hover { background: #b71c1c; }
.arr { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.45); color: #fff; border: none; width: 38px; height: 38px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; z-index: 10; transition: background 0.2s; }
.arr:hover { background: #e53935; }
.arr.prev { left: 14px; }
.arr.next { right: 14px; }
.dots { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.4); border: none; cursor: pointer; transition: all 0.3s; }
.dot.active { background: #e53935; width: 22px; border-radius: 4px; }
.placeholder-img { width: 100%; height: 100%; object-fit: cover; display: block; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); }

@media (max-width: 768px) {
    .slider-wrap { height: 350px; }
    .overlay { padding: 1.5rem; }
    .title { font-size: 1.5rem; }
    .desc { display: none; }
    .arr { width: 30px; height: 30px; font-size: 16px; }
}
@media (max-width: 576px) {
    .slider-wrap { height: 280px; }
    .title { font-size: 1.2rem; }
    .brand-tag { font-size: 9px; }
    .year { font-size: 10px; }
    .badge { font-size: 9px; }
    .btn { padding: 6px 16px; font-size: 11px; }
}
</style>

<div class="slider-wrap">
  <div class="slides" id="slides">
    <?php if (empty($banners)): ?>
    <!-- Default slide if no banners -->
    <div class="slide">
      <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);"></div>
      <div class="overlay">
        <div class="content">
          <div class="brand-tag">REDZONE</div>
          <div class="title">Welcome to RedZone</div>
          <div class="year">2026</div>
          <div class="badge">Your Trusted Phone Gallery</div>
          <div class="desc">Get the latest smartphones at the best prices in Horana</div>
          <a href="category.php" class="btn">Shop Now →</a>
        </div>
      </div>
    </div>
    <?php else: ?>
      <?php foreach ($banners as $index => $banner): ?>
      <div class="slide">
        <?php 
        // Fix image path
        $img_src = '';
        if (!empty($banner['image_url'])) {
            $clean = ltrim($banner['image_url'], '/');
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $clean;
            if (file_exists($full_path)) {
                $img_src = '/cc/' . $clean;
            } else {
                $full_path2 = $_SERVER['DOCUMENT_ROOT'] . '/' . $clean;
                if (file_exists($full_path2)) {
                    $img_src = '/' . $clean;
                }
            }
        }
        ?>
        <?php if (!empty($img_src)): ?>
          <img src="<?= $img_src ?>" alt="<?= e($banner['title']) ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);"></div>
        <?php endif; ?>
        <div class="overlay">
          <div class="content">
            <div class="brand-tag"><?= e($banner['brand'] ?? 'REDZONE') ?></div>
            <div class="title"><?= e($banner['title']) ?></div>
            <div class="year"><?= e($banner['year'] ?? '2026') ?></div>
            <?php if (!empty($banner['tag'])): ?>
            <div class="badge"><?= e($banner['tag']) ?></div>
            <?php endif; ?>
            <?php if (!empty($banner['subtitle'])): ?>
            <div class="desc"><?= e($banner['subtitle']) ?></div>
            <?php endif; ?>
            <a href="<?= e($banner['button_link']) ?>" class="btn"><?= e($banner['button_text'] ?? 'Shop Now') ?> →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <?php if ($totalBanners > 1): ?>
  <button class="arr prev" onclick="go(-1)">‹</button>
  <button class="arr next" onclick="go(1)">›</button>
  <div class="dots" id="dots">
    <?php for($i = 0; $i < $totalBanners; $i++): ?>
    <button class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="goTo(<?= $i ?>)"></button>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<script>
let cur = 0;
const total = <?= $totalBanners > 0 ? $totalBanners : 1 ?>;
const slidesEl = document.getElementById('slides');
let autoInterval;

function goTo(n) {
    if (total <= 1) return;
    cur = (n + total) % total;
    if (slidesEl) {
        slidesEl.style.transform = 'translateX(-' + (cur * 100) + '%)';
    }
    const dots = document.querySelectorAll('.dot');
    dots.forEach((d, i) => d.classList.toggle('active', i === cur));
}

function go(dir) {
    goTo(cur + dir);
}

// Auto slide every 5 seconds if more than 1 banner
if (total > 1) {
    autoInterval = setInterval(() => go(1), 5000);
}

// Pause auto slide on hover
const sliderWrap = document.querySelector('.slider-wrap');
if (sliderWrap) {
    sliderWrap.addEventListener('mouseenter', () => {
        if (autoInterval) clearInterval(autoInterval);
    });
    sliderWrap.addEventListener('mouseleave', () => {
        if (total > 1) {
            autoInterval = setInterval(() => go(1), 5000);
        }
    });
}

// Touch support for mobile
let touchStartX = 0;
let touchEndX = 0;
if (slidesEl) {
    slidesEl.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });
    slidesEl.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        if (touchEndX < touchStartX - 50) go(1);
        if (touchEndX > touchStartX + 50) go(-1);
    });
}
</script>
<section class="section brands-section" id="products">
    <div class="section-header brand-section-title">
        <h2 class="section-title">Brands</h2>
        <a href="category.php" class="section-link">View all →</a>
    </div>
    <div class="section" style="padding-bottom:0">
        <div class="brand-cards">
            <?php 
            $brandCount = count($brands);
            for($i = 0; $i < min(4, $brandCount); $i++): 
                $brand = $brands[$i];
                $image_url = $brand['image_url'] ?? '';
                $full_path = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $image_url;
            ?>
            <a href="category.php?brand=<?= e($brand['slug']) ?>" class="brand-card">
                <?php if (!empty($image_url) && file_exists($full_path)): ?>
                    <img src="/cc/<?= $image_url ?>" 
                         alt="<?= e($brand['name']) ?>" 
                         style="width: 150px; height: 150px; object-fit: contain;">
                <?php else: ?>
                    <span class="brand-card-icon"><?= e($brand['emoji'] ?? '📱') ?></span>
                <?php endif; ?>
                <span class="brand-card-name"><?= e($brand['name']) ?></span>
                <span class="brand-card-count">Shop Now →</span>
            </a>
            <?php endfor; ?>
            <?php if($brandCount < 4): ?>
                <?php for($i = $brandCount; $i < 4; $i++): ?>
                <div class="brand-card" style="opacity:0.5;">
                    <span class="brand-card-icon">📱</span>
                    <span class="brand-card-name">Coming Soon</span>
                    <span class="brand-card-count">-</span>
                </div>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
        
        <?php if($brandCount > 4): ?>
        <div class="brand-cards" style="margin-top:1px;">
            <?php for($i = 4; $i < min(8, $brandCount); $i++): 
                $brand = $brands[$i];
                $image_url = $brand['image_url'] ?? '';
                $full_path = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $image_url;
            ?>
            <a href="category.php?brand=<?= e($brand['slug']) ?>" class="brand-card">
                <?php if (!empty($image_url) && file_exists($full_path)): ?>
                    <img src="/cc/<?= $image_url ?>" 
                         alt="<?= e($brand['name']) ?>" 
                         style="width: 120px; height: 120px; object-fit: contain;">
                <?php else: ?>
                    <span class="brand-card-icon"><?= e($brand['emoji'] ?? '📱') ?></span>
                <?php endif; ?>
                <span class="brand-card-name"><?= e($brand['name']) ?></span>
                <span class="brand-card-count">Shop Now →</span>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* Hide Brands Section on Mobile */
@media (max-width: 768px) {
    .brands-section {
        display: none;
    }
}
</style>

<style>
/* Wishlist Button Styles for Homepage */
.product-card {
    position: relative;
}

.wishlist-btn-home {
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

.wishlist-btn-home:hover {
    transform: scale(1.1);
    border-color: var(--red);
}

.wishlist-btn-home.active {
    background: #fff5f5;
    border-color: var(--red);
    color: var(--red);
}
</style>

<!-- BESTSELLERS - PHONES ONLY WITH WISHLIST -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Bestseller</h2>
        <a href="category.php" class="section-link">View all →</a>
    </div>
    <div class="products-grid" style="border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
        <?php if (empty($bestsellers)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--muted); grid-column: span 4;">No products found. Add products via admin panel.</div>
        <?php else: ?>
            <?php foreach ($bestsellers as $p): ?>
            <div class="product-card" onclick="location.href='product.php?slug=<?= e($p['slug']) ?>'">
                <?php if ($p['badge']): ?>
                <span class="card-badge <?= strtolower($p['badge']) === 'hot' ? 'badge-hot' : (strtolower($p['badge']) === 'best value' ? 'badge-off' : 'badge-new') ?>">
                    <?= e($p['badge']) ?>
                </span>
                <?php endif; ?>
                
                <!-- WISHLIST BUTTON -->
                <button class="wishlist-btn-home" 
                        onclick="event.stopPropagation(); toggleWishlist(<?= $p['id'] ?>, this)"
                        data-product-id="<?= $p['id'] ?>">
                    <?php 
                    // Check if product is in wishlist
                    $inWishlist = false;
                    if (isLoggedIn()) {
                        $checkWishlist = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
                        $checkWishlist->execute([$_SESSION['user_id'], $p['id']]);
                        $inWishlist = $checkWishlist->fetch() ? true : false;
                    }
                    echo $inWishlist ? '❤️' : '🤍';
                    ?>
                </button>
                
                <div class="card-img">
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
                
                <div class="card-brand"><?= e($p['brand_name']) ?></div>
                <div class="card-name"><?= e($p['name']) ?></div>
                <div class="card-starting">Starting at</div>
                <div class="card-price-row">
                    <span class="card-price"><?= formatPrice((float)$p['base_price']) ?></span>
                    <?php if ($p['old_price'] && (float)$p['old_price'] > 0): ?>
                    <span class="card-price-old"><?= formatPrice((float)$p['old_price']) ?></span>
                    <span class="card-discount">
                        <?= round((1 - (float)$p['base_price'] / (float)$p['old_price']) * 100) ?>% OFF
                    </span>
                    <?php endif; ?>
                </div>
                <button class="card-btn">View Details →</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- PROMO BANNERS -->
<section class="section" style="padding-top:0">
    <div class="promo-grid">
        <div class="promo-card">
            <span class="promo-emoji">📦</span>
            <div class="promo-content">
                <div class="promo-tag">Installment Plans</div>
                <div class="promo-title">Pay Monthly.<br>Take Home Today.</div>
                <a href="tel:<?= SITE_PHONE ?>" class="promo-link">Learn More →</a>
            </div>
        </div>
        <div class="promo-card dark">
            <span class="promo-emoji">🔧</span>
            <div class="promo-content">
                <div class="promo-tag">Repair Service</div>
                <div class="promo-title">Screen & Battery<br>Repairs.</div>
                <a href="/cc/repairs.php" class="promo-link">Book Now →</a>
            </div>
        </div>
    </div>
</section>

<!-- ACCESSORIES SECTION -->
<section class="section" style="padding-top:0">
    <div class="section-header">
        <h2 class="section-title">Accessories</h2>
        <a href="category.php?brand=accessories" class="section-link">View all →</a>
    </div>
    <div class="products-grid" style="border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
        <?php if (empty($accessories)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--muted); grid-column: span 4;">No accessories found. Add accessories via admin panel.</div>
        <?php else: ?>
            <?php foreach ($accessories as $acc): ?>
            <div class="product-card" onclick="location.href='product.php?slug=<?= e($acc['slug']) ?>'">
                <?php if ($acc['badge']): ?>
                <span class="card-badge <?= strtolower($acc['badge']) === 'hot' ? 'badge-hot' : 'badge-new' ?>">
                    <?= e($acc['badge']) ?>
                </span>
                <?php endif; ?>
                
                <div class="card-img" style="font-size: 4rem;">
                    <?php 
                    $acc_image = $acc['product_image'] ?? '';
                    $acc_image_path = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $acc_image;
                    ?>
                    <?php if (!empty($acc_image) && file_exists($acc_image_path)): ?>
                        <img src="/cc/<?= $acc_image ?>" 
                             alt="<?= htmlspecialchars($acc['name']) ?>" 
                             style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <?= $acc['emoji'] ?? '🎧' ?>
                    <?php endif; ?>
                </div>
                
                <div class="card-brand"><?= e($acc['brand_name']) ?></div>
                <div class="card-name"><?= e($acc['name']) ?></div>
                <div class="card-starting">Starting at</div>
                <div class="card-price-row">
                    <span class="card-price"><?= formatPrice((float)$acc['base_price']) ?></span>
                    <?php if ($acc['old_price'] && (float)$acc['old_price'] > 0): ?>
                    <span class="card-price-old"><?= formatPrice((float)$acc['old_price']) ?></span>
                    <span class="card-discount">
                        <?= round((1 - (float)$acc['base_price'] / (float)$acc['old_price']) * 100) ?>% OFF
                    </span>
                    <?php endif; ?>
                </div>
                <button class="card-btn">View Details →</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- SERVICES SECTION -->
<div class="services-bg">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Our Services</h2>
        </div>
        <div class="services-grid">
            <div class="service-card">
                <span class="service-icon">🛒</span>
                <h4>New & Used Phones</h4>
                <p>Wide range of brand new and certified pre-owned smartphones at the best prices in Horana.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">🔧</span>
                <h4>Phone Repairs</h4>
                <p>Screen replacements, battery swaps, charging port repairs and all hardware fixes.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">💳</span>
                <h4>Easy Installments</h4>
                <p>Flexible monthly payment plans. Get your dream phone without paying full price upfront.</p>
            </div>
            <div class="service-card">
                <span class="service-icon">✅</span>
                <h4>Genuine Warranty</h4>
                <p>All products come with official warranty. Your purchase is always protected at RedZone.</p>
            </div>
        </div>
    </div>
</div>

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