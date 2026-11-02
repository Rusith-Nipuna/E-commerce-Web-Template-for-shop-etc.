<?php
// product.php - COMPLETE WITH MULTIPLE IMAGES GALLERY, LIGHTBOX, ZOOM
require_once 'includes/config.php';
require_once 'includes/header.php';


// Handle direct buy data via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['direct_buy'])) {
        $_SESSION['checkout_data'] = $input['data'];
        echo json_encode(['success' => true]);
        exit;
    }
}

$db = getDB();
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header('Location: index.php');
    exit;
}

// Get product
$stmt = $db->prepare("
    SELECT p.*, b.name as brand_name, b.slug as brand_slug
    FROM products p
    JOIN brands b ON p.brand_id = b.id
    WHERE p.slug = ? AND p.active = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: category.php');
    exit;
}

// Get MULTIPLE PRODUCT IMAGES
$productImages = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
$productImages->execute([$product['id']]);
$productImages = $productImages->fetchAll();

// Get main product image (if no images in product_images, use product_image column)
$mainImage = '';
if (!empty($productImages)) {
    foreach ($productImages as $img) {
        if ($img['is_main']) {
            $mainImage = $img['image_url'];
            break;
        }
    }
    if (empty($mainImage)) {
        $mainImage = $productImages[0]['image_url'];
    }
} else {
    $mainImage = $product['product_image'] ?? '';
}

// Get colors
$colors = $db->prepare("SELECT * FROM product_colors WHERE product_id = ? ORDER BY sort_order");
$colors->execute([$product['id']]);
$colors = $colors->fetchAll();

// Get storage
$storage = $db->prepare("SELECT * FROM product_storage WHERE product_id = ? AND available = 1 ORDER BY price_add ASC");
$storage->execute([$product['id']]);
$storage = $storage->fetchAll();

// Get specs
$specs = $db->prepare("SELECT * FROM product_specs WHERE product_id = ? ORDER BY sort_order");
$specs->execute([$product['id']]);
$specs = $specs->fetchAll();

$activeBrand = $product['brand_slug'];
$pageTitle = $product['name'];
?>

<style>
/* Product Page Styles */
.product-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Product Gallery Styles */
.product-gallery {
    position: relative;
    width: auto;
    height: 680px;
}

.main-image-container {
    position: relative;
    background: #f7f7f5;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 1rem;
    cursor: zoom-in;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.main-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.main-image.zoomed {
    transform: scale(1.5);
    cursor: zoom-out;
}

.thumbnail-list {
    display: flex;
    gap: 0.8rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.thumbnail-list::-webkit-scrollbar {
    height: 4px;
}

.thumbnail-list::-webkit-scrollbar-track {
    background: var(--border);
    border-radius: 4px;
}

.thumbnail-list::-webkit-scrollbar-thumb {
    background: var(--red);
    border-radius: 4px;
}

.thumbnail {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 12px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s;
    background: #f7f7f5;
    flex-shrink: 0;
}

.thumbnail:hover {
    border-color: var(--red);
    transform: translateY(-2px);
}

.thumbnail.active {
    border-color: var(--red);
    box-shadow: 0 0 0 2px rgba(215,44,44,0.2);
}

.image-counter {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    z-index: 10;
}

/* Lightbox Modal */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.lightbox.active {
    display: flex;
}

.lightbox img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    z-index: 2001;
}

.lightbox-prev, .lightbox-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.5rem;
    transition: all 0.2s;
}

.lightbox-prev:hover, .lightbox-next:hover {
    background: var(--red);
}

.lightbox-prev {
    left: 20px;
}

.lightbox-next {
    right: 20px;
}

/* Product Info Styles */
.product-brand {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 2px;
    color: var(--red);
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.product-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stock-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.stock-badge.in_stock {
    background: #dcfce7;
    color: #16a34a;
}

.stock-badge.out_of_stock {
    background: #fee2e2;
    color: #dc2626;
}

.price {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--red);
    margin: 0.5rem 0;
}

.option-section {
    margin: 1.5rem 0;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.option-label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.8rem;
    display: flex;
    
}

.color-options, .storage-options {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.color-btn {
    transition: all 0.2s;
    cursor: pointer;
}

.color-btn:hover {
    transform: scale(1.1);
}

.storage-btn {
    padding: 0.5rem 1.2rem;
    border: 1px solid var(--border);
    border-radius: 30px;
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
}

.storage-btn:hover,
.storage-btn.active {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.qty-btn {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg2);
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.2s;
}

.qty-btn:hover {
    background: var(--red);
    color: #fff;
}

.emi-section {
    margin: 1.5rem 0;
    padding: 1rem;
    background: var(--bg);
    border-radius: 16px;
}

.emi-title {
    font-weight: 600;
    margin-bottom: 1rem;
}

.emi-options {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.emi-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.8rem 1.2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.emi-card:hover {
    transform: translateY(-2px);
    border-color: var(--red);
}

.emi-card.selected {
    border-color: var(--red);
    background: #fff5f5;
}

.emi-month {
    font-weight: 600;
    font-size: 0.85rem;
}

.emi-amount {
    font-size: 0.7rem;
    color: var(--muted);
}

.clear-emi-btn {
    margin-top: 0.8rem;
    background: transparent;
    border: none;
    color: var(--red);
    cursor: pointer;
    font-size: 0.7rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.buy-btn {
    padding: 0.8rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.buy-btn:hover {
    transform: translateY(-2px);
}

.wishlist-btn {
    width: 100%;
    margin-top: 0.8rem;
    background: transparent;
    border: 1px solid var(--border);
    padding: 0.7rem;
    border-radius: 8px;
    cursor: pointer;
    color: var(--muted);
    transition: all 0.2s;
}

.wishlist-btn:hover {
    border-color: var(--red);
    color: var(--red);
}

.specs-list {
    list-style: none;
    padding: 0;
}

.specs-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.spec-key {
    font-weight: 600;
    color: var(--text);
}

.spec-value {
    color: var(--muted);
}

.delivery-info {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg);
    border-radius: 12px;
}

/* Responsive */
@media (max-width: 900px) {
    .product-wrapper {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .product-title { font-size: 1.5rem; }
    .price { font-size: 1.5rem; }
}

@media (max-width: 576px) {
    .thumbnail { width: 60px; height: 60px; }
    .lightbox-prev, .lightbox-next { width: 35px; height: 35px; font-size: 1rem; }
    .emi-options { gap: 0.5rem; }
    .emi-card { padding: 0.5rem 0.8rem; }
}
</style>

<!-- BREADCRUMB -->
<div class="breadcrumb-bar">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="sep">›</span>
        <a href="category.php?brand=<?= e($product['brand_slug']) ?>"><?= e($product['brand_name']) ?></a>
        <span class="sep">›</span>
        <span class="current"><?= e($product['name']) ?></span>
    </div>
</div>

<!-- PRODUCT DETAILS -->
<div class="product-wrapper">
   
    <!-- GALLERY WITH MULTIPLE IMAGES -->
    <div class="product-gallery">
        <div class="main-image-container" id="mainImageContainer">
            <?php 
            $mainImageFullPath = '';
            if (!empty($mainImage)) {
                $mainImageFullPath = $_SERVER['DOCUMENT_ROOT'] . '/cc/' . $mainImage;
            }
            ?>
            <?php if (!empty($mainImage) && file_exists($mainImageFullPath)): ?>
                <img src="/cc/<?= $mainImage ?>" 
                     id="mainProductImage"
                     alt="<?= htmlspecialchars($product['name']) ?>" 
                     class="main-image">
            <?php else: ?>
                <div class="main-image" style="display: flex; align-items: center; justify-content: center; background: var(--bg);">
                    <span style="font-size: 8rem;"><?= $product['emoji'] ?? '📱' ?></span>
                </div>
            <?php endif; ?>
            <?php if (count($productImages) > 0): ?>
            <div class="image-counter">📸 <?= count($productImages) ?> images</div>
            <?php endif; ?>
        </div>
        
        <!-- Thumbnails -->
        <?php if (!empty($productImages) && count($productImages) > 1): ?>
        <div class="thumbnail-list" id="thumbnailList">
            <?php foreach ($productImages as $index => $img): ?>
            <img src="/cc/<?= $img['image_url'] ?>" 
                 class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                 data-image="/cc/<?= $img['image_url'] ?>"
                 data-index="<?= $index ?>"
                 onclick="changeImage(this, <?= $index ?>)"
                 alt="Thumbnail <?= $index+1 ?>">
            <?php endforeach; ?>
        </div>
        <?php elseif (!empty($mainImage) && file_exists($mainImageFullPath) && count($productImages) <= 1): ?>
        <div class="thumbnail-list">
            <img src="/cc/<?= $mainImage ?>" class="thumbnail active" data-image="/cc/<?= $mainImage ?>" data-index="0">
        </div>
        <?php endif; ?>
    </div>

    <!-- PRODUCT INFO -->
    <div class="product-info">
        <div class="product-brand"><?= e(strtoupper($product['brand_name'])) ?></div>
        <h1 class="product-title"><?= e($product['name']) ?></h1>
        
        <div class="stock-badge <?= $product['in_stock'] ? 'in_stock' : 'out_of_stock' ?>">
            <?= $product['in_stock'] ? '✅ In Stock' : '❌ Out of Stock' ?>
        </div>
        
        <div class="product-rating" style="display: flex; align-items: center; gap: 8px; margin: 0.5rem 0;">
            <span style="color: #f59e0b;">★★★★★</span>
            <span style="color: var(--muted); font-size: 0.75rem;">(5 customer reviews)</span>
        </div>
        
        <!-- PRICE DISPLAY -->
        <div class="price" id="displayPrice"><?= formatPrice($product['base_price']) ?></div>
        
        <?php if ($product['old_price'] && $product['old_price'] > $product['base_price']): ?>
        <div style="text-decoration: line-through; color: var(--muted); margin-top: -0.5rem; font-size: 0.85rem;">
            <?= formatPrice($product['old_price']) ?>
        </div>
        <?php endif; ?>

        <?php if ($product['short_desc']): ?>
        <div style="margin: 1rem 0; color: var(--muted); line-height: 1.6;"><?= e($product['short_desc']) ?></div>
        <?php endif; ?>

        <!-- COLORS -->
        <?php if (!empty($colors)): ?>
        <div class="option-section">
            <div class="option-label">
                <span>🎨 Colors:</span> 
                <span class="selected-val" id="colorLabel" style="color: var(--red); font-weight: 600;"><?= e($colors[0]['name']) ?></span>
            </div>
            <div class="color-options" id="colorOptions">
                <?php foreach ($colors as $i => $c): ?>
                <button class="color-btn <?= $i === 0 ? 'active' : '' ?>"
                        style="background: <?= e($c['hex_code']) ?>; width: 40px; height: 40px; border-radius: 50%; border: 2px solid <?= $i === 0 ? 'var(--red)' : 'transparent' ?>; outline: none;"
                        data-color="<?= e($c['name']) ?>"
                        onclick="selectColor(this, <?= $i ?>)"
                        title="<?= e($c['name']) ?>">
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- STORAGE (SIZES) -->
        <?php if (!empty($storage)): ?>
        <div class="option-section">
            <div class="option-label">
                <span>📦 Size / Storage:</span> 
                <span class="selected-val" id="storageLabel" style="color: var(--red); font-weight: 600;"><?= e($storage[0]['label']) ?></span>
            </div>
            <div class="storage-options" id="storageOptions">
                <?php foreach ($storage as $i => $s): ?>
                <button class="storage-btn <?= $i === 0 ? 'active' : '' ?>"
                        data-label="<?= e($s['label']) ?>"
                        data-add="<?= (float)$s['price_add'] ?>"
                        onclick="selectStorage(this)">
                    <?= e($s['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- QUANTITY -->
        <div class="option-section">
            <div class="option-label">🔢 Quantity:</div>
            <div class="quantity-selector">
                <button class="qty-btn" onclick="changeQty(-1)">−</button>
                <input type="number" id="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?? 10 ?>" style="width: 70px; text-align: center; padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px;" readonly>
                <button class="qty-btn" onclick="changeQty(1)">+</button>
                <span style="color: var(--muted); font-size: 0.75rem;">(Stock: <?= $product['stock_quantity'] ?? 0 ?> units)</span>
            </div>
        </div>

        <!-- EMI SECTION -->
        <div class="emi-section">
            <div class="emi-title">💳 EMI & Bank Offers</div>
            <div class="emi-options" id="emiOptions">
                <div class="emi-card" data-months="6">
                    <div class="emi-month">6 months</div>
                    <div class="emi-amount">LKR 0/mo</div>
                </div>
                <div class="emi-card" data-months="12">
                    <div class="emi-month">12 months</div>
                    <div class="emi-amount">LKR 0/mo</div>
                </div>
                <div class="emi-card" data-months="24">
                    <div class="emi-month">24 months</div>
                    <div class="emi-amount">LKR 0/mo</div>
                </div>
            </div>
            
            <div class="emi-details" id="emiDetails" style="display: none;">
                <span>📱 Selected EMI Plan</span>
                <div>
                    <strong id="selectedEmiText"></strong>
                </div>
            </div>
            
            <button class="clear-emi-btn" onclick="clearEMIPlan()">✕ Clear EMI Plan</button>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <?php if ($product['in_stock']): ?>
                <button class="buy-btn" onclick="addToCart()" style="flex: 2; background: var(--red); color: white;">
                    🛒 Add to Cart
                </button>
                <button class="buy-btn" onclick="buyNow()" style="flex: 1; background: var(--text); color: white;">
                    Buy Now →
                </button>
            <?php else: ?>
                <button class="buy-btn" onclick="notifyMe()" style="width: 100%; background: #9b9b97; color: white;">
                    🔔 Notify Me When Available
                </button>
            <?php endif; ?>
        </div>
        
        <button class="wishlist-btn" onclick="addToWishlist()">
            ❤️ Add to Wishlist
        </button>

        <!-- SPECIFICATIONS -->
        <?php if (!empty($specs)): ?>
        <div class="option-section">
            <h3 style="font-size: 1rem; margin-bottom: 0.8rem;">📋 Product Specifications</h3>
            <ul class="specs-list">
                <?php foreach ($specs as $spec): ?>
                <li>
                    <span class="spec-key"><?= e($spec['spec_key']) ?></span>
                    <span class="spec-value"><?= e($spec['spec_value']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Delivery Info -->
        <div class="delivery-info">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <span style="font-size: 1.5rem;">🚚</span>
                <div>
                    <strong>Free Delivery</strong>
                    <p style="font-size: 0.75rem; color: var(--muted);">Free shipping on orders over LKR 50,000</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem;">
                <span style="font-size: 1.5rem;">🔄</span>
                <div>
                    <strong>7 Day Return Policy</strong>
                    <p style="font-size: 0.75rem; color: var(--muted);">Easy returns within 7 days</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <button class="lightbox-prev" onclick="event.stopPropagation(); prevImage()">‹</button>
    <img id="lightboxImg" src="">
    <button class="lightbox-next" onclick="event.stopPropagation(); nextImage()">›</button>
</div>

<script>
// ============================================
// MULTIPLE IMAGES GALLERY JAVASCRIPT
// ============================================
let currentImageIndex = 0;
let zoomActive = false;

// Get all images array from PHP
const allImages = <?php 
    $images_array = [];
    if (!empty($productImages)) {
        foreach ($productImages as $img) {
            $images_array[] = '/cc/' . $img['image_url'];
        }
    } elseif (!empty($mainImage) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/cc/' . $mainImage)) {
        $images_array[] = '/cc/' . $mainImage;
    }
    echo json_encode($images_array);
?>;

function changeImage(element, index) {
    currentImageIndex = index;
    const mainImage = document.getElementById('mainProductImage');
    if (mainImage && element.dataset.image) {
        mainImage.src = element.dataset.image;
        if (zoomActive) {
            mainImage.classList.remove('zoomed');
            zoomActive = false;
        }
    }
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

// Zoom functionality
const mainImg = document.getElementById('mainProductImage');
const mainContainer = document.getElementById('mainImageContainer');

if (mainContainer && mainImg) {
    mainContainer.addEventListener('click', function(e) {
        e.stopPropagation();
        if (allImages.length > 0) {
            openLightbox(currentImageIndex);
        }
    });
}

// Lightbox functions
function openLightbox(index) {
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    if (lightbox && lightboxImg && allImages.length > 0) {
        currentImageIndex = index;
        lightboxImg.src = allImages[currentImageIndex];
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function prevImage() {
    if (allImages.length === 0) return;
    currentImageIndex = (currentImageIndex - 1 + allImages.length) % allImages.length;
    const lightboxImg = document.getElementById('lightboxImg');
    if (lightboxImg) {
        lightboxImg.src = allImages[currentImageIndex];
    }
}

function nextImage() {
    if (allImages.length === 0) return;
    currentImageIndex = (currentImageIndex + 1) % allImages.length;
    const lightboxImg = document.getElementById('lightboxImg');
    if (lightboxImg) {
        lightboxImg.src = allImages[currentImageIndex];
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('lightbox');
    if (lightbox && lightbox.classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            prevImage();
        } else if (e.key === 'ArrowRight') {
            nextImage();
        }
    }
});

// ============================================
// PRODUCT DATA & FUNCTIONS
// ============================================
const BASE_PRICE = <?= (float)$product['base_price']; ?>;
let selectedAddPrice = 0;
let selectedEMIPlan = null;
let currentQuantity = 1;

function formatPrice(price) {
    return 'LKR ' + Math.round(price).toLocaleString();
}

function getUnitPrice() {
    return BASE_PRICE + selectedAddPrice;
}

function getTotalPrice() {
    return getUnitPrice() * currentQuantity;
}

function updateDisplay() {
    const totalPrice = getTotalPrice();
    const priceElement = document.getElementById('displayPrice');
    if (priceElement) {
        priceElement.innerHTML = formatPrice(totalPrice);
    }
    updateEMI(totalPrice);
}

function updateEMI(totalPrice) {
    const monthsList = [6, 12, 24];
    const emiCards = document.querySelectorAll('.emi-card');
    
    emiCards.forEach((card, index) => {
        const months = monthsList[index];
        const monthly = Math.ceil(totalPrice / months);
        
        card.setAttribute('data-months', months);
        card.setAttribute('data-monthly', monthly);
        
        const monthSpan = card.querySelector('.emi-month');
        const amountSpan = card.querySelector('.emi-amount');
        
        if (monthSpan) monthSpan.innerHTML = months + ' months';
        if (amountSpan) amountSpan.innerHTML = formatPrice(monthly) + '/mo';
    });
    
    if (selectedEMIPlan) {
        const newMonthly = Math.ceil(totalPrice / selectedEMIPlan.months);
        selectedEMIPlan.monthly = newMonthly;
        updateEMIDisplay();
    }
}

function updateEMIDisplay() {
    const emiDetails = document.getElementById('emiDetails');
    const selectedText = document.getElementById('selectedEmiText');
    
    if (selectedEMIPlan && emiDetails) {
        const total = selectedEMIPlan.monthly * selectedEMIPlan.months;
        selectedText.innerHTML = `${selectedEMIPlan.months} months · ${formatPrice(selectedEMIPlan.monthly)}/month · Total ${formatPrice(total)}`;
        emiDetails.style.display = 'flex';
    } else if (emiDetails) {
        emiDetails.style.display = 'none';
    }
}

function clearEMIPlan() {
    document.querySelectorAll('.emi-card').forEach(c => c.classList.remove('selected'));
    selectedEMIPlan = null;
    updateEMIDisplay();
}

function changeQty(delta) {
    let newQty = currentQuantity + delta;
    const maxStock = <?= $product['stock_quantity'] ?? 10 ?>;
    if (newQty < 1) newQty = 1;
    if (newQty > maxStock && maxStock > 0) newQty = maxStock;
    currentQuantity = newQty;
    
    const qtyInput = document.getElementById('quantity');
    if (qtyInput) qtyInput.value = currentQuantity;
    updateDisplay();
}

function selectColor(btn, idx) {
    document.querySelectorAll('.color-btn').forEach((b, i) => {
        b.classList.toggle('active', i === idx);
        b.style.border = i === idx ? '2px solid var(--red)' : '2px solid transparent';
    });
    
    const colorLabel = document.getElementById('colorLabel');
    if (colorLabel) colorLabel.textContent = btn.dataset.color;
}

function selectStorage(btn) {
    document.querySelectorAll('.storage-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const storageLabel = document.getElementById('storageLabel');
    if (storageLabel) storageLabel.textContent = btn.dataset.label;
    
    selectedAddPrice = parseFloat(btn.dataset.add) || 0;
    currentQuantity = 1;
    const qtyInput = document.getElementById('quantity');
    if (qtyInput) qtyInput.value = currentQuantity;
    updateDisplay();
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#16a34a' : '#dc2626'};
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

function addToCart() {
    <?php if (!isset($_SESSION['user_id'])): ?>
        if (confirm('Please login to add items to cart. Go to login page?')) {
            window.location.href = '/cc/account/login.php';
        }
        return;
    <?php endif; ?>
    
    const selectedColor = document.querySelector('.color-btn.active');
    const selectedStorage = document.querySelector('.storage-btn.active');
    const productId = <?= $product['id'] ?>;
    const quantity = currentQuantity;
    
    const addBtn = event.target.closest('.buy-btn');
    const originalText = addBtn.innerHTML;
    addBtn.innerHTML = '⏳ Adding...';
    addBtn.disabled = true;
    
    fetch('/cc/cart_add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            product_id: productId,
            quantity: quantity,
            storage: selectedStorage ? selectedStorage.dataset.label : '',
            color: selectedColor ? selectedColor.dataset.color : ''
        })
    })
    .then(response => response.json())
    .then(data => {
        addBtn.innerHTML = originalText;
        addBtn.disabled = false;
        
        if (data.success) {
            showNotification('✅ Product added to cart!', 'success');
            const cartLink = document.querySelector('a[href*="cart.php"]');
            if (cartLink) {
                cartLink.innerHTML = `🛍️ Cart (${data.cart_count})`;
            }
        } else {
            if (data.message === 'login') {
                if (confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = '/cc/account/login.php';
                }
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        }
    })
    .catch(error => {
        addBtn.innerHTML = originalText;
        addBtn.disabled = false;
        showNotification('❌ Failed to add to cart', 'error');
    });
}

function buyNow() {
    if (!<?= $product['in_stock'] ? 'true' : 'false' ?>) {
        alert('Out of stock! Call us at <?= SITE_PHONE ?>');
        return;
    }
    
    // Get selected options
    const selectedColor = document.querySelector('.color-btn.active');
    const selectedStorage = document.querySelector('.storage-btn.active');
    const quantity = currentQuantity;
    const unitPrice = getUnitPrice();
    const totalPrice = getTotalPrice();
    const productId = <?= $product['id'] ?>;
    
    // Create checkout data object
    const checkoutData = {
        product_id: productId,
        quantity: quantity,
        color: selectedColor ? selectedColor.dataset.color : '',
        storage: selectedStorage ? selectedStorage.dataset.label : '',
        unit_price: unitPrice,
        total_price: totalPrice,
        product_name: '<?= htmlspecialchars($product['name']) ?>',
        product_image: '<?= $product['product_image'] ?? '' ?>',
        emi_plan: selectedEMIPlan ? selectedEMIPlan.months : null
    };
    
    // Show loading state
    const buyBtn = event.target.closest('.buy-btn');
    const originalText = buyBtn.innerHTML;
    buyBtn.innerHTML = '⏳ Redirecting...';
    buyBtn.disabled = true;
    
    // Method 1: Try AJAX to store in session
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ direct_buy: true, data: checkoutData })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/cc/checkout.php?direct_buy=1';
        } else {
            // Fallback - use URL parameters
            const params = new URLSearchParams({
                direct_buy: 1,
                product_id: productId,
                quantity: quantity,
                unit_price: unitPrice,
                total_price: totalPrice,
                product_name: '<?= htmlspecialchars($product['name']) ?>',
                color: selectedColor ? selectedColor.dataset.color : '',
                storage: selectedStorage ? selectedStorage.dataset.label : '',
                product_image: '<?= $product['product_image'] ?? '' ?>'
            });
            window.location.href = '/cc/checkout.php?' + params.toString();
        }
    })
    .catch(error => {
        // Fallback - use URL parameters
        const params = new URLSearchParams({
            direct_buy: 1,
            product_id: productId,
            quantity: quantity,
            unit_price: unitPrice,
            total_price: totalPrice,
            product_name: '<?= htmlspecialchars($product['name']) ?>',
            color: selectedColor ? selectedColor.dataset.color : '',
            storage: selectedStorage ? selectedStorage.dataset.label : '',
            product_image: '<?= $product['product_image'] ?? '' ?>'
        });
        window.location.href = '/cc/checkout.php?' + params.toString();
    });
}
function notifyMe() {
    alert('Call us at <?= SITE_PHONE ?>');
}

function addToWishlist() {
    <?php if (!isset($_SESSION['user_id'])): ?>
        if (confirm('Please login to add to wishlist. Go to login page?')) {
            window.location.href = 'account/login.php';
        }
        return;
    <?php endif; ?>
    
    const productId = <?= $product['id'] ?>;
    fetch('/cc/wishlist_add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('❤️ Product added to wishlist!', 'success');
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Failed to add to wishlist', 'error');
    });
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    const firstStorage = document.querySelector('.storage-btn');
    if (firstStorage) {
        firstStorage.classList.add('active');
        selectedAddPrice = parseFloat(firstStorage.dataset.add) || 0;
        updateDisplay();
    }
    
    document.querySelectorAll('.emi-card').forEach(card => {
        card.addEventListener('click', function() {
            const months = parseInt(this.dataset.months);
            const monthly = parseInt(this.dataset.monthly);
            
            if (this.classList.contains('selected')) {
                clearEMIPlan();
            } else {
                document.querySelectorAll('.emi-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedEMIPlan = { months: months, monthly: monthly };
                updateEMIDisplay();
            }
        });
    });
});

// Add animation styles
if (!document.querySelector('#product-styles')) {
    const style = document.createElement('style');
    style.id = 'product-styles';
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