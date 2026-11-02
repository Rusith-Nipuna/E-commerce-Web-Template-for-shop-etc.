<?php
// search.php - COMPLETE FIXED VERSION
require_once 'includes/config.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');
error_reporting(0); // Turn off HTML errors for JSON

$db = getDB();
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $searchTerm = "%$query%";
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.slug, p.base_price, p.product_image, p.emoji, b.name as brand_name
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE p.active = 1 AND (p.name LIKE ? OR b.name LIKE ?)
        LIMIT 10
    ");
    
    $stmt->execute([$searchTerm, $searchTerm]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean data - remove any HTML tags from product names
    foreach ($products as &$product) {
        $product['name'] = strip_tags($product['name']);
        $product['brand_name'] = strip_tags($product['brand_name']);
    }
    
    echo json_encode($products);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>