<?php
// wishlist_add.php - Toggle wishlist (Add/Remove)
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user = getCurrentUser();
$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

$db = getDB();

// Check if already in wishlist
$stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user['id'], $product_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Remove from wishlist
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $result = $stmt->execute([$user['id'], $product_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove']);
    }
} else {
    // Add to wishlist
    $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $result = $stmt->execute([$user['id'], $product_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add']);
    }
}
?>