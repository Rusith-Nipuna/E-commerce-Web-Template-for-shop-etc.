<?php
// admin/edit_product.php - COMPLETE FIXED VERSION
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rz_admin_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$product_id = $_GET['id'] ?? 0;
$msg = '';
$error = '';

// Check if product exists
$stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php?tab=products');
    exit;
}

// Get brands for dropdown
$brands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get colors - FIXED: Check if table exists first
$colors = [];
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'product_colors'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->prepare("SELECT * FROM product_colors WHERE product_id = ? ORDER BY sort_order");
        $stmt->execute([$product_id]);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Table doesn't exist, ignore
    $colors = [];
}

// Get storage - FIXED: Check if table exists first
$storage = [];
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'product_storage'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->prepare("SELECT * FROM product_storage WHERE product_id = ? ORDER BY sort_order");
        $stmt->execute([$product_id]);
        $storage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Table doesn't exist, ignore
    $storage = [];
}

// Update product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name)));
    $brand_id = $_POST['brand_id'];
    $base_price = $_POST['base_price'];
    $old_price = $_POST['old_price'] ?: null;
    $short_desc = $_POST['short_desc'];
    $description = $_POST['description'];
    $badge = $_POST['badge'] ?: null;
    $in_stock = isset($_POST['in_stock']) ? 1 : 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    
    // Handle image upload
    $product_image = $product['product_image'];
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file['type'], $allowed) && in_array($file_ext, $extensions)) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            // Delete old image
            if ($product_image && file_exists('../' . $product_image)) {
                unlink('../' . $product_image);
            }
            
            $new_filename = $slug . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $product_image = 'uploads/products/' . $new_filename;
            }
        }
    }
    
    // Update product
    $stmt = $db->prepare("UPDATE products SET name=?, slug=?, brand_id=?, base_price=?, old_price=?, short_desc=?, description=?, badge=?, in_stock=?, stock_quantity=?, product_image=? WHERE id=?");
    $stmt->execute([$name, $slug, $brand_id, $base_price, $old_price, $short_desc, $description, $badge, $in_stock, $stock_quantity, $product_image, $product_id]);
    
    // Update colors (if table exists)
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'product_colors'");
        if ($checkTable->rowCount() > 0) {
            $db->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$product_id]);
            foreach (($_POST['color_name'] ?? []) as $i => $cn) {
                if (trim($cn)) {
                    $db->prepare("INSERT INTO product_colors (product_id, name, hex_code, sort_order) VALUES (?,?,?,?)")
                       ->execute([$product_id, $cn, $_POST['color_hex'][$i] ?? '#000', $i]);
                }
            }
        }
    } catch (PDOException $e) {
        // Table doesn't exist, skip
    }
    
    // Update storage (if table exists)
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'product_storage'");
        if ($checkTable->rowCount() > 0) {
            $db->prepare("DELETE FROM product_storage WHERE product_id = ?")->execute([$product_id]);
            foreach (($_POST['storage_label'] ?? []) as $i => $sl) {
                if (trim($sl)) {
                    $db->prepare("INSERT INTO product_storage (product_id, label, price_add, sort_order) VALUES (?,?,?,?)")
                       ->execute([$product_id, $sl, $_POST['storage_add'][$i] ?? 0, $i]);
                }
            }
        }
    } catch (PDOException $e) {
        // Table doesn't exist, skip
    }
    
    $msg = '✅ Product updated successfully!';
    
    // Refresh product data
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Refresh colors
    try {
        $stmt = $db->prepare("SELECT * FROM product_colors WHERE product_id = ? ORDER BY sort_order");
        $stmt->execute([$product_id]);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $colors = [];
    }
    
    // Refresh storage
    try {
        $stmt = $db->prepare("SELECT * FROM product_storage WHERE product_id = ? ORDER BY sort_order");
        $stmt->execute([$product_id]);
        $storage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $storage = [];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:#f7f7f5;padding:2rem}
        .container{max-width:900px;margin:0 auto}
        .card{background:#fff;border:1px solid #e8e8e4;border-radius:16px;padding:2rem;margin-bottom:1.5rem}
        h1{font-size:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .form-group{display:flex;flex-direction:column;gap:0.4rem;margin-bottom:1rem}
        .form-group label{font-size:0.75rem;font-weight:600;color:#6b6b67;text-transform:uppercase;letter-spacing:0.5px}
        .form-group input,.form-group select,.form-group textarea{padding:0.6rem 0.8rem;border:1px solid #e8e8e4;border-radius:8px;font-family:'DM Sans',sans-serif}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:#d72c2c}
        .dynamic-row{display:flex;gap:0.5rem;margin-bottom:0.5rem}
        .dynamic-row input{flex:1}
        .dynamic-row input[type=color]{width:50px}
        .btn-primary{background:#d72c2c;color:#fff;border:none;padding:0.6rem 1.2rem;border-radius:8px;font-weight:600;cursor:pointer}
        .btn-secondary{background:#f7f7f5;border:1px solid #e8e8e4;padding:0.6rem 1.2rem;border-radius:8px;cursor:pointer;text-decoration:none;color:#111}
        .current-image{margin:1rem 0;padding:1rem;background:#f7f7f5;border-radius:12px;display:flex;align-items:center;gap:1rem}
        .current-image img{width:80px;height:80px;object-fit:contain;background:#fff;border-radius:8px}
        .msg{background:#dcfce7;color:#16a34a;padding:0.8rem 1rem;border-radius:8px;margin-bottom:1rem}
        .error{background:#fee2e2;color:#dc2626;padding:0.8rem 1rem;border-radius:8px;margin-bottom:1rem}
        button{cursor:pointer}
        hr{margin:1rem 0;border-color:#e8e8e4}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>✏️ Edit Product: <?= htmlspecialchars($product['name']) ?></h1>
        
        <?php if ($msg): ?>
            <div class="msg">✅ <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Brand *</label>
                    <select name="brand_id" required>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $product['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Product Name *</label>
                    <input name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Base Price (LKR) *</label>
                    <input name="base_price" type="number" value="<?= $product['base_price'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Old Price (LKR)</label>
                    <input name="old_price" type="number" value="<?= $product['old_price'] ?>">
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input name="stock_quantity" type="number" value="<?= $product['stock_quantity'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Badge</label>
                    <select name="badge">
                        <option value="">None</option>
                        <option value="New" <?= $product['badge'] == 'New' ? 'selected' : '' ?>>New</option>
                        <option value="Hot" <?= $product['badge'] == 'Hot' ? 'selected' : '' ?>>Hot</option>
                        <option value="Best Value" <?= $product['badge'] == 'Best Value' ? 'selected' : '' ?>>Best Value</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>In Stock</label>
                    <select name="in_stock">
                        <option value="1" <?= $product['in_stock'] ? 'selected' : '' ?>>Yes - In Stock</option>
                        <option value="0" <?= !$product['in_stock'] ? 'selected' : '' ?>>No - Out of Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>📷 Product Image (JPG, PNG, WEBP)</label>
                    <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                    <small style="color:#6b6b67">Leave empty to keep current image</small>
                </div>
            </div>
            
            <?php if (!empty($product['product_image']) && file_exists('../' . $product['product_image'])): ?>
            <div class="current-image">
                <img src="/cc/<?= $product['product_image'] ?>" alt="Current image">
                <div>
                    <strong>Current Image</strong><br>
                    <small><?= basename($product['product_image']) ?></small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Short Description</label>
                <input name="short_desc" value="<?= htmlspecialchars($product['short_desc'] ?? '') ?>" placeholder="e.g., 12GB RAM · 128GB Storage · 5G">
            </div>
            
            <div class="form-group">
                <label>Full Description</label>
                <textarea name="description" rows="4" placeholder="Enter product features..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>
            
            <hr>
            
            <!-- Colors Section -->
            <div style="margin:1rem 0">
                <label style="font-weight:600">🎨 Colors</label>
                <div id="colorsWrap">
                    <?php if (!empty($colors)): ?>
                        <?php foreach ($colors as $c): ?>
                        <div class="dynamic-row">
                            <input type="color" name="color_hex[]" value="<?= $c['hex_code'] ?>">
                            <input name="color_name[]" value="<?= htmlspecialchars($c['name']) ?>" placeholder="Color name">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dynamic-row">
                            <input type="color" name="color_hex[]" value="#000000">
                            <input name="color_name[]" placeholder="Color name (e.g., Midnight Black)">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-secondary" style="margin-top:0.5rem" onclick="addColor()">+ Add Color</button>
                <small style="display:block; margin-top:0.3rem; color:#6b6b67">Colors will appear on product page for customer selection</small>
            </div>
            
            <!-- Storage Section -->
            <div style="margin:1rem 0">
                <label style="font-weight:600">💾 Storage Options</label>
                <div id="storageWrap">
                    <?php if (!empty($storage)): ?>
                        <?php foreach ($storage as $s): ?>
                        <div class="dynamic-row">
                            <input name="storage_label[]" value="<?= htmlspecialchars($s['label']) ?>" placeholder="Label (e.g., 256GB)">
                            <input name="storage_add[]" type="number" value="<?= $s['price_add'] ?>" placeholder="Price add (LKR)">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dynamic-row">
                            <input name="storage_label[]" placeholder="Label (e.g., 256GB)">
                            <input name="storage_add[]" type="number" placeholder="Price add (LKR)" value="0">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-secondary" style="margin-top:0.5rem" onclick="addStorage()">+ Add Storage Option</button>
                <small style="display:block; margin-top:0.3rem; color:#6b6b67">Storage options will appear on product page. Price add is extra cost from base price.</small>
            </div>
            
            <div style="display:flex; gap:1rem; margin-top:1rem">
                <button type="submit" class="btn-primary">💾 Save Changes</button>
                <a href="index.php?tab=products" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addColor() {
    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.innerHTML = '<input type="color" name="color_hex[]" value="#000000"><input name="color_name[]" placeholder="Color name">';
    document.getElementById('colorsWrap').appendChild(div);
}
function addStorage() {
    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.innerHTML = '<input name="storage_label[]" placeholder="Label (e.g., 512GB)"><input name="storage_add[]" type="number" placeholder="Price add" value="0">';
    document.getElementById('storageWrap').appendChild(div);
}
</script>
</body>
</html>