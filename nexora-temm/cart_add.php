<?php
// cart_add.php - Add to cart API
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login']);
    exit;
}

$user = getCurrentUser();
$data = json_decode(file_get_contents('php://input'), true);

$product_id = $data['product_id'] ?? 0;
$quantity = max(1, intval($data['quantity'] ?? 1));
$selected_storage = $data['storage'] ?? '';
$selected_color = $data['color'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

$db = getDB();

// Check if product exists
$stmt = $db->prepare("SELECT stock_quantity, in_stock FROM products WHERE id = ? AND active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

if (!$product['in_stock']) {
    echo json_encode(['success' => false, 'message' => 'Out of stock']);
    exit;
}

// Check if already in cart
$stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user['id'], $product_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Update quantity
    $new_quantity = $existing['quantity'] + $quantity;
    $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->execute([$new_quantity, $existing['id']]);
} else {
    // Add new item
    $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, selected_storage, selected_color) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $product_id, $quantity, $selected_storage, $selected_color]);
}

// Get updated cart count
$stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$stmt->execute([$user['id']]);
$result = $stmt->fetch();
$cartCount = $result['total'] ?? 0;

echo json_encode([
    'success' => true,
    'message' => 'Added to cart',
    'cart_count' => $cartCount
]);
?>