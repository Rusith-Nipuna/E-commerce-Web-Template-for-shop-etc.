<?php
// admin/index.php - COMPLETE ADMIN DASHBOARD (FIXED - NO WARNINGS)
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();

// Check admin login
function isAdminLoggedIn() {
    return isset($_SESSION['rz_admin_id']) && isset($_SESSION['rz_admin']);
}

// Login
if ($_POST['action'] ?? '' === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['rz_admin_id'] = $admin['id'];
            $_SESSION['rz_admin'] = true;
            $_SESSION['rz_admin_name'] = $admin['full_name'];
            $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            header('Location: index.php');
            exit;
        } else {
            $loginError = 'Invalid username or password!';
        }
    } else {
        $loginError = 'Please enter username and password';
    }
}

if ($_GET['logout'] ?? '' === '1') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isAdminLoggedIn()) {
    showLogin($loginError ?? '');
    exit;
}

// Handle Actions
$msg = '';
$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ========== ADD PRODUCT WITH IMAGE UPLOAD ==========
if ($action === 'add_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'])));
    
    $product_image = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file['type'], $allowed_types) && in_array($file_ext, $allowed_ext)) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = $slug . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $product_image = 'uploads/products/' . $new_filename;
            } else {
                $error = 'Image upload failed';
            }
        } else {
            $error = 'Only JPG, PNG, WEBP images allowed';
        }
    }
    
    $stmt = $db->prepare("INSERT INTO products (brand_id, name, slug, short_desc, description, base_price, old_price, emoji, bg_color, badge, in_stock, stock_quantity, product_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['brand_id'], 
        $_POST['name'], 
        $slug,
        $_POST['short_desc'], 
        $_POST['description'],
        $_POST['base_price'], 
        $_POST['old_price'] ?: null,
        '📱', 
        '#f7f7f5',
        $_POST['badge'] ?: null, 
        isset($_POST['in_stock']) ? 1 : 0,
        $_POST['stock_quantity'] ?? 0,
        $product_image
    ]);
    $pid = $db->lastInsertId();
    
    foreach (($_POST['color_name'] ?? []) as $i => $cn) {
        if (trim($cn)) {
            $db->prepare("INSERT INTO product_colors (product_id, name, hex_code, sort_order) VALUES (?,?,?,?)")
               ->execute([$pid, $cn, $_POST['color_hex'][$i] ?? '#000', $i]);
        }
    }
    
    foreach (($_POST['storage_label'] ?? []) as $i => $sl) {
        if (trim($sl)) {
            $db->prepare("INSERT INTO product_storage (product_id, label, price_add, sort_order) VALUES (?,?,?,?)")
               ->execute([$pid, $sl, $_POST['storage_add'][$i] ?? 0, $i]);
        }
    }

    // ========== ADD MULTIPLE PRODUCT IMAGES ==========
if ($pid && isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
    $upload_dir = '../uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    
    $image_count = 0;
    foreach ($_FILES['product_images']['tmp_name'] as $key => $tmp_name) {
        if ($image_count >= 5) break; // Max 5 images
        
        if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $slug . '_' . time() . '_' . $key . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $is_main = ($image_count === 0 && !$product_image) ? 1 : 0;
                    $image_url = 'uploads/products/' . $new_filename;
                    
                    $stmt_img = $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_main) VALUES (?, ?, ?, ?)");
                    $stmt_img->execute([$pid, $image_url, $image_count, $is_main]);
                    
                    $image_count++;
                }
            }
        }
    }
    if ($image_count > 0 && !$error) {
        $msg_add = " + {$image_count} additional images";
    }
}
    
    if (!$error) $msg = '✅ Product added successfully!';
}

// ========== ADD/EDIT BRAND ==========
if ($action === 'add_brand' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'])));
    $stmt = $db->prepare("INSERT INTO brands (name, slug, emoji, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $slug, $_POST['emoji'] ?? '📱', $_POST['sort_order'] ?? 0]);
    $msg = '✅ Brand added successfully!';
}

if ($action === 'edit_brand' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $editBrand = $stmt->fetch();
}

if ($action === 'update_brand' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("UPDATE brands SET name = ?, sort_order = ? WHERE id = ?");
    $stmt->execute([$_POST['name'], $_POST['sort_order'], $_POST['brand_id']]);
    $msg = '✅ Brand updated successfully!';
    header('Location: index.php?tab=brands');
    exit;
}

// ========== BRAND IMAGE UPLOAD ==========
if ($action === 'upload_brand_image' && isset($_FILES['brand_image'])) {
    $brand_id = $_POST['brand_id'];
    $file = $_FILES['brand_image'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($file['type'], $allowed) && in_array($file_ext, $extensions)) {
        $stmt = $db->prepare("SELECT name, slug FROM brands WHERE id = ?");
        $stmt->execute([$brand_id]);
        $brand = $stmt->fetch();
        
        if ($brand) {
            $new_filename = $brand['slug'] . '.' . $file_ext;
            $upload_dir = '../uploads/brands/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $db->prepare("UPDATE brands SET image_url = ?, image_alt = ? WHERE id = ?")
                   ->execute(['uploads/brands/' . $new_filename, $brand['name'], $brand_id]);
                $msg = '✅ Brand image uploaded successfully!';
            } else {
                $error = 'Failed to upload image';
            }
        }
    } else {
        $error = 'Only JPG, PNG, WEBP images allowed';
    }
}

// ========== TOGGLE PRODUCT STATUS ==========
if ($action === 'toggle' && isset($_GET['id'])) {
    $db->prepare("UPDATE products SET in_stock = 1 - in_stock WHERE id = ?")->execute([$_GET['id']]);
    header('Location: index.php?tab=products');
    exit;
}


// ========== DELETE PRODUCT (with images) ==========
if ($action === 'delete' && isset($_GET['id'])) {
    // Get product images to delete files
    $stmt = $db->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
    $stmt->execute([$_GET['id']]);
    $images = $stmt->fetchAll();
    foreach ($images as $img) {
        if ($img['image_url'] && file_exists('../' . $img['image_url'])) {
            unlink('../' . $img['image_url']);
        }
    }
    $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$_GET['id']]);
    $db->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$_GET['id']]);
    $db->prepare("DELETE FROM product_storage WHERE product_id = ?")->execute([$_GET['id']]);
    $db->prepare("UPDATE products SET active = 0 WHERE id = ?")->execute([$_GET['id']]);
    header('Location: index.php?tab=products');
    exit;
}

// ========== UPDATE PRODUCT ==========
if ($action === 'update_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'])));
    
    // Update product details
    $stmt = $db->prepare("UPDATE products SET brand_id = ?, name = ?, slug = ?, short_desc = ?, description = ?, base_price = ?, old_price = ?, badge = ?, in_stock = ?, stock_quantity = ? WHERE id = ?");
    $stmt->execute([
        $_POST['brand_id'],
        $_POST['name'],
        $slug,
        $_POST['short_desc'],
        $_POST['description'],
        $_POST['base_price'],
        $_POST['old_price'] ?: null,
        $_POST['badge'] ?: null,
        isset($_POST['in_stock']) ? 1 : 0,
        $_POST['stock_quantity'] ?? 0,
        $product_id
    ]);
    
    // Handle main image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_ext)) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            // Delete old main image
            $old = $db->prepare("SELECT product_image FROM products WHERE id = ?");
            $old->execute([$product_id]);
            $old_img = $old->fetch();
            if ($old_img['product_image'] && file_exists('../' . $old_img['product_image'])) {
                unlink('../' . $old_img['product_image']);
            }
            
            $new_filename = $slug . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $stmt = $db->prepare("UPDATE products SET product_image = ? WHERE id = ?");
                $stmt->execute(['uploads/products/' . $new_filename, $product_id]);
            }
        }
    }
    
    // Handle multiple images upload
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $upload_dir = '../uploads/products/';
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        // Get current image count
        $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM product_images WHERE product_id = ?");
        $count_stmt->execute([$product_id]);
        $current_count = $count_stmt->fetch()['total'];
        
        $image_count = 0;
        foreach ($_FILES['product_images']['tmp_name'] as $key => $tmp_name) {
            if ($current_count + $image_count >= 5) break;
            
            if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION));
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = $slug . '_' . time() . '_' . $key . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $image_url = 'uploads/products/' . $new_filename;
                        $stmt_img = $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_main) VALUES (?, ?, ?, 0)");
                        $stmt_img->execute([$product_id, $image_url, $current_count + $image_count]);
                        $image_count++;
                    }
                }
            }
        }
    }
    
    // Update colors (delete old and insert new)
    $db->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$product_id]);
    foreach (($_POST['color_name'] ?? []) as $i => $cn) {
        if (trim($cn)) {
            $db->prepare("INSERT INTO product_colors (product_id, name, hex_code, sort_order) VALUES (?,?,?,?)")
               ->execute([$product_id, $cn, $_POST['color_hex'][$i] ?? '#000', $i]);
        }
    }
    
    // Update storage (delete old and insert new)
    $db->prepare("DELETE FROM product_storage WHERE product_id = ?")->execute([$product_id]);
    foreach (($_POST['storage_label'] ?? []) as $i => $sl) {
        if (trim($sl)) {
            $db->prepare("INSERT INTO product_storage (product_id, label, price_add, sort_order) VALUES (?,?,?,?)")
               ->execute([$product_id, $sl, $_POST['storage_add'][$i] ?? 0, $i]);
        }
    }
    
    header('Location: index.php?tab=products&msg=updated');
    exit;
}

// ========== DELETE PRODUCT IMAGE ==========
if ($action === 'delete_product_image' && isset($_GET['img_id'])) {
    $img_id = $_GET['img_id'];
    $stmt = $db->prepare("SELECT image_url, product_id FROM product_images WHERE id = ?");
    $stmt->execute([$img_id]);
    $img = $stmt->fetch();
    
    if ($img && $img['image_url'] && file_exists('../' . $img['image_url'])) {
        unlink('../' . $img['image_url']);
    }
    
    $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$img_id]);
    
    header('Location: index.php?tab=edit_product&id=' . $img['product_id'] . '&msg=image_deleted');
    exit;
}


// ========== UPDATE ORDER STATUS ==========
if ($action === 'update_order' && isset($_POST['order_id'])) {
    $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?")
       ->execute([$_POST['order_status'], $_POST['order_id']]);
    header('Location: index.php?tab=orders');
    exit;
}

// ========== DELETE PRODUCT IMAGE ==========
if ($action === 'delete_product_image' && isset($_GET['img_id'])) {
    $img_id = $_GET['img_id'];
    $stmt = $db->prepare("SELECT image_url, product_id FROM product_images WHERE id = ?");
    $stmt->execute([$img_id]);
    $img = $stmt->fetch();
    
    if ($img && $img['image_url'] && file_exists('../' . $img['image_url'])) {
        unlink('../' . $img['image_url']);
    }
    
    $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$img_id]);
    
    // If this was main image, set another as main
    $check = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $check->execute([$img['product_id']]);
    if ($check->fetchColumn() > 0) {
        $set_main = $db->prepare("UPDATE product_images SET is_main = 1 WHERE product_id = ? ORDER BY sort_order LIMIT 1");
        $set_main->execute([$img['product_id']]);
    }
    
    header('Location: index.php?tab=edit_product&id=' . $img['product_id'] . '&msg=image_deleted');
    exit;
}
// ========== BANNER MANAGEMENT ==========

// Get banners for display
$bannersList = $db->query("SELECT * FROM banners ORDER BY sort_order ASC")->fetchAll();

// Add Banner - FIXED (No getimagesize warnings)
if ($action === 'add_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $tag = $_POST['tag'];
    $brand = $_POST['brand'] ?? '';
    $year = $_POST['year'] ?? '';
    $button_text = $_POST['button_text'];
    $button_link = $_POST['button_link'];
    $button_type = $_POST['button_type'];
    $bg_color = $_POST['bg_color'];
    $sort_order = $_POST['sort_order'];
    $image_alt = $_POST['image_alt'] ?? '';
    
    $image_url = null;
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['banner_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if ($file['size'] > $max_size) {
            $error = 'Image size must be less than 2MB (Current: ' . round($file['size']/1024) . 'KB)';
        } elseif (in_array($file['type'], $allowed_types) && in_array($file_ext, $allowed_ext)) {
            $upload_dir = '../uploads/banners/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'banner_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $image_url = 'uploads/banners/' . $new_filename;
            } else {
                $error = 'Image upload failed';
            }
        } else {
            $error = 'Only JPG, PNG, WEBP images allowed';
        }
    } else {
        $error = 'Please select an image file';
    }
    
    if (!$error) {
        $stmt = $db->prepare("INSERT INTO banners (title, subtitle, tag, brand, year, image_url, image_alt, button_text, button_link, button_type, bg_color, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$title, $subtitle, $tag, $brand, $year, $image_url, $image_alt, $button_text, $button_link, $button_type, $bg_color, $sort_order]);
        $msg = '✅ Banner added successfully!';
    }
}

// Edit Banner
if ($action === 'edit_banner' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $editBanner = $stmt->fetch();
}

// Update Banner - FIXED (No getimagesize warnings)
if ($action === 'update_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $banner_id = $_POST['banner_id'];
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $tag = $_POST['tag'];
    $brand = $_POST['brand'] ?? '';
    $year = $_POST['year'] ?? '';
    $button_text = $_POST['button_text'];
    $button_link = $_POST['button_link'];
    $button_type = $_POST['button_type'];
    $bg_color = $_POST['bg_color'];
    $sort_order = $_POST['sort_order'];
    $image_alt = $_POST['image_alt'] ?? '';
    
    $stmt = $db->prepare("SELECT image_url FROM banners WHERE id = ?");
    $stmt->execute([$banner_id]);
    $current = $stmt->fetch();
    $image_url = $current['image_url'];
    
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['banner_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024;
        
        if ($file['size'] > $max_size) {
            $error = 'Image size must be less than 2MB';
        } elseif (in_array($file['type'], $allowed_types) && in_array($file_ext, $allowed_ext)) {
            $upload_dir = '../uploads/banners/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if ($image_url && file_exists('../' . $image_url)) {
                unlink('../' . $image_url);
            }
            
            $new_filename = 'banner_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $image_url = 'uploads/banners/' . $new_filename;
            } else {
                $error = 'Image upload failed';
            }
        } else {
            $error = 'Only JPG, PNG, WEBP images allowed';
        }
    }
    
    if (!$error) {
        $stmt = $db->prepare("UPDATE banners SET title=?, subtitle=?, tag=?, brand=?, year=?, image_url=?, image_alt=?, button_text=?, button_link=?, button_type=?, bg_color=?, sort_order=? WHERE id=?");
        $stmt->execute([$title, $subtitle, $tag, $brand, $year, $image_url, $image_alt, $button_text, $button_link, $button_type, $bg_color, $sort_order, $banner_id]);
        $msg = '✅ Banner updated successfully!';
        header('Location: index.php?tab=banners');
        exit;
    }
}

// Delete Banner
if ($action === 'delete_banner' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT image_url FROM banners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $banner = $stmt->fetch();
    
    if ($banner && $banner['image_url'] && file_exists('../' . $banner['image_url'])) {
        unlink('../' . $banner['image_url']);
    }
    
    $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$_GET['id']]);
    header('Location: index.php?tab=banners');
    exit;
}

// Toggle Banner Status
if ($action === 'toggle_banner' && isset($_GET['id'])) {
    $db->prepare("UPDATE banners SET active = 1 - active WHERE id = ?")->execute([$_GET['id']]);
    header('Location: index.php?tab=banners');
    exit;
}

// Get Data for Dashboard
$products = $db->query("SELECT p.*, b.name as brand_name FROM products p JOIN brands b ON p.brand_id = b.id WHERE p.active = 1 ORDER BY p.id DESC")->fetchAll();
$brands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY sort_order")->fetchAll();
$orders = $db->query("SELECT o.*, u.first_name, u.last_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 20")->fetchAll();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

$lowStock = $db->query("SELECT * FROM products WHERE stock_quantity < 5 AND stock_quantity > 0 AND active = 1 ORDER BY stock_quantity ASC LIMIT 10")->fetchAll();
$outOfStock = $db->query("SELECT * FROM products WHERE stock_quantity = 0 AND active = 1 ORDER BY id DESC LIMIT 10")->fetchAll();

$currentTab = $_GET['tab'] ?? 'dashboard';

function showLogin($err = '') {
    ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:#f7f7f5;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border:1px solid #e8e8e4;border-radius:16px;padding:2.5rem;width:360px;text-align:center}
.logo{display:flex;justify-content:center;margin-bottom:1.5rem}
.lm{background:#d72c2c;color:#fff;font-size:1rem;font-weight:700;padding:8px 12px;border-radius:6px 0 0 6px}
.ls{background:#111;color:#fff;font-size:0.6rem;font-weight:700;text-transform:uppercase;padding:8px 9px;border-radius:0 6px 6px 0}
h2{font-size:1rem;color:#6b6b67;margin-bottom:1.5rem}
input{width:100%;border:1.5px solid #e8e8e4;border-radius:8px;padding:0.7rem 1rem;margin-bottom:1rem}
input:focus{border-color:#d72c2c;outline:none}
button{width:100%;background:#d72c2c;color:#fff;border:none;padding:0.75rem;border-radius:8px;font-weight:600;cursor:pointer}
.err{color:#d72c2c;margin-bottom:0.8rem}
.note{font-size:0.7rem;color:#9b9b97;margin-top:1rem}
</style>
</head>
<body>
<div class="box">
    <div class="logo"><div class="lm">REDZONE</div><div class="ls">ADMIN</div></div>
    <h2>Enter credentials</h2>
    <?php if($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="text" name="username" placeholder="Username" autofocus>
        <input type="password" name="password" placeholder="Password">
        <button type="submit">Login →</button>
    </form>
    <div class="note">Default: admin / admin123</div>
</div>
</body>
</html>
    <?php exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard – RedZone</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--red:#d72c2c;--red2:#b91c1c;--bg:#f7f7f5;--bg2:#fff;--border:#e8e8e4;--text:#111110;--muted:#6b6b67}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
        
        nav{background:#111110;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:56px}
        .nav-logo{display:flex;align-items:center;text-decoration:none}
        .lm{background:var(--red);color:#fff;font-size:0.85rem;font-weight:700;padding:6px 10px;border-radius:6px 0 0 6px}
        .ls{background:#2a2a28;color:#fff;font-size:0.55rem;font-weight:700;text-transform:uppercase;padding:6px 8px;border-radius:0 6px 6px 0}
        .la{color:#9b9b97;font-size:0.75rem;margin-left:0.8rem}
        nav a.logout{color:#9b9b97;text-decoration:none;font-size:0.8rem}
        nav a.logout:hover{color:#fff}
        
        .admin-container{display:flex;min-height:calc(100vh - 56px)}
        .sidebar{width:260px;background:#fff;border-right:1px solid var(--border);padding:1.5rem}
        .sidebar-menu{display:flex;flex-direction:column;gap:0.3rem}
        .sidebar-menu a{display:flex;align-items:center;gap:10px;padding:0.7rem 1rem;border-radius:10px;text-decoration:none;color:var(--muted);font-weight:500;transition:all 0.2s}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:var(--bg);color:var(--red)}
        
        .main-content{flex:1;padding:2rem;overflow-x:auto}
        .wrap{max-width:1400px;margin:0 auto}
        
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
        .stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:1.2rem}
        .stat-title{font-size:0.7rem;font-weight:600;text-transform:uppercase;color:var(--muted)}
        .stat-value{font-size:2rem;font-weight:700;margin-top:0.3rem}
        
        .alert-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.2rem;margin-bottom:1rem}
        .alert-warning{border-left:3px solid #f59e0b}
        .alert-danger{border-left:3px solid #dc2626}
        .alert-title{font-weight:600;margin-bottom:0.5rem}
        .alert-list li{padding:0.5rem 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between}
        
        .data-table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        .data-table th{text-align:left;padding:1rem;background:var(--bg);color:var(--muted);font-weight:600;font-size:0.7rem;text-transform:uppercase}
        .data-table td{padding:1rem;border-bottom:1px solid var(--border)}
        .data-table tr:hover td{background:var(--bg)}
        
        .badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.7rem;font-weight:600}
        .badge-success{background:#dcfce7;color:#16a34a}
        .badge-warning{background:#fef3c7;color:#d97706}
        .badge-danger{background:#fee2e2;color:#dc2626}
        
        .form-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .form-group{display:flex;flex-direction:column;gap:0.4rem}
        .form-group label{font-size:0.75rem;font-weight:600;color:var(--muted)}
        .form-group input,.form-group select,.form-group textarea{padding:0.6rem 0.8rem;border:1px solid var(--border);border-radius:8px}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--red)}
        .btn-primary{background:var(--red);color:#fff;border:none;padding:0.6rem 1.2rem;border-radius:8px;font-weight:600;cursor:pointer}
        .btn-secondary{background:var(--bg);border:1px solid var(--border);padding:0.6rem 1.2rem;border-radius:8px;cursor:pointer}
        
        .brand-image-preview{width:60px;height:60px;background:var(--bg);border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .brand-image-preview img{width:100%;height:100%;object-fit:contain}
        .dynamic-row{display:flex;gap:0.5rem;margin-bottom:0.5rem}
        .dynamic-row input{flex:1}
        .dynamic-row input[type=color]{width:50px}
        
        @media(max-width:900px){.stats-grid{grid-template-columns:1fr 1fr}.sidebar{width:200px}}
        @media(max-width:700px){.admin-container{flex-direction:column}.sidebar{width:100%}}
        /* Multiple images styles */
.product-images-list {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.product-image-item {
    position: relative;
    width: 80px;
    height: 80px;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}
.product-image-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.product-image-item .delete-img {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--red);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 12px;
}
.images-preview {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.preview-item {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.remove-img {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--red);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
}
    </style>
</head>
<body>

<nav>
    <div style="display:flex;align-items:center">
        <a class="nav-logo" href="index.php">
            <div class="lm">REDZONE</div><div class="ls">.LK</div>
        </a>
        <span class="la">/ Admin Dashboard</span>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;">
        <span style="color:#9b9b97;font-size:0.75rem;">Welcome, <?= $_SESSION['rz_admin_name'] ?? 'Admin' ?></span>
        <a class="logout" href="?logout=1">Logout →</a>
    </div>
</nav>

<div class="admin-container">
    <div class="sidebar">
        <div class="sidebar-menu">
            <a href="?tab=dashboard" class="<?= $currentTab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="?tab=products" class="<?= $currentTab === 'products' ? 'active' : '' ?>">📱 Products</a>
            <a href="?tab=add_product" class="<?= $currentTab === 'add_product' ? 'active' : '' ?>">➕ Add Product</a>
            <a href="?tab=brands" class="<?= $currentTab === 'brands' ? 'active' : '' ?>">🏷️ Brands</a>
            <a href="?tab=banners" class="<?= $currentTab === 'banners' ? 'active' : '' ?>">🎨 Banners</a>
            <a href="?tab=orders" class="<?= $currentTab === 'orders' ? 'active' : '' ?>">📦 Orders</a>
            <a href="?tab=customers" class="<?= $currentTab === 'customers' ? 'active' : '' ?>">👥 Customers</a>
            <a href="?tab=low_stock" class="<?= $currentTab === 'low_stock' ? 'active' : '' ?>">⚠️ Low Stock</a>
        </div>
    </div>

    <div class="main-content">
        <div class="wrap">
            
            <?php if ($msg): ?>
                <div style="background:#dcfce7;color:#16a34a;padding:0.8rem 1rem;border-radius:8px;margin-bottom:1rem">✅ <?= $msg ?></div>
            <?php endif; ?>
            <?php if (isset($error) && $error): ?>
                <div style="background:#fee2e2;color:#dc2626;padding:0.8rem 1rem;border-radius:8px;margin-bottom:1rem">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- DASHBOARD -->
            <?php if ($currentTab === 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-title">Total Products</div><div class="stat-value"><?= count($products) ?></div></div>
                <div class="stat-card"><div class="stat-title">Total Orders</div><div class="stat-value"><?= count($orders) ?></div></div>
                <div class="stat-card"><div class="stat-title">Total Customers</div><div class="stat-value"><?= count($users) ?></div></div>
                <div class="stat-card"><div class="stat-title">Total Brands</div><div class="stat-value"><?= count($brands) ?></div></div>
            </div>

            <?php if (!empty($lowStock)): ?>
            <div class="alert-card alert-warning">
                <div class="alert-title">⚠️ Low Stock Alert (Stock < 5)</div>
                <ul class="alert-list">
                    <?php foreach ($lowStock as $p): ?>
                    <li><span><?= $p['name'] ?></span><span class="badge badge-warning">Stock: <?= $p['stock_quantity'] ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($outOfStock)): ?>
            <div class="alert-card alert-danger">
                <div class="alert-title">❌ Out of Stock Products</div>
                <ul class="alert-list">
                    <?php foreach ($outOfStock as $p): ?>
                    <li><span><?= $p['name'] ?></span><span class="badge badge-danger">Out of Stock</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="form-card">
                <h3 style="margin-bottom:1rem">📦 Recent Orders</h3>
                <table class="data-table">
                    <thead></tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                            <td><?= formatPrice($order['total_amount']) ?></td>
                            <td><span class="badge badge-warning"><?= ucfirst($order['order_status'] ?? 'Pending') ?></span></td>
                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

          <!-- PRODUCTS WITH LIVE SEARCH -->
<?php if ($currentTab === 'products'): 
    // Get all products for live search
    $allProducts = $db->query("
        SELECT p.*, b.name as brand_name 
        FROM products p 
        JOIN brands b ON p.brand_id = b.id 
        WHERE p.active = 1 
        ORDER BY p.id DESC
    ")->fetchAll();
?>

<div class="form-card">
    <!-- Header with Search Box -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h3>📱 All Products (<span id="productCount"><?= count($allProducts) ?></span>)</h3>
        
        <!-- Live Search Input -->
        <div style="position: relative;">
            <input type="text" id="liveProductSearch" 
                   placeholder="🔍 Search by name, brand, or ID..." 
                   autocomplete="off"
                   style="padding: 0.6rem 1rem; border: 1px solid var(--border); border-radius: 8px; width: 280px; font-size: 0.85rem;">
            <span id="searchLoader" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.8rem;">⏳</span>
        </div>
    </div>
    
    <!-- Products Table -->
    <div style="overflow-x: auto;">
        <table class="data-table" id="productsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <?php foreach ($allProducts as $p): ?>
                <tr data-product-id="<?= $p['id'] ?>" 
                    data-product-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" 
                    data-brand-name="<?= strtolower(htmlspecialchars($p['brand_name'])) ?>">
                    <td><?= $p['id'] ?></td>
                    <td>
                        <?php if (!empty($p['product_image']) && file_exists('../' . $p['product_image'])): ?>
                            <img src="/cc/<?= $p['product_image'] ?>" style="width: 40px; height: 40px; object-fit: contain; background:#f7f7f5; border-radius:6px;">
                        <?php else: ?>
                            <span style="font-size:1.5rem"><?= $p['emoji'] ?? '📱' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['brand_name']) ?></td>
                    <td><?= formatPrice($p['base_price']) ?></td>
                    <td><span class="badge <?= $p['stock_quantity'] > 0 ? 'badge-success' : 'badge-danger' ?>"><?= $p['stock_quantity'] ?? 0 ?></span></td>
                    <td><span class="badge <?= $p['in_stock'] ? 'badge-success' : 'badge-danger' ?>"><?= $p['in_stock'] ? 'In Stock' : 'Out' ?></span></td>
                    <td class="act-btns">
                        <a class="btn-secondary" href="?action=toggle&id=<?= $p['id'] ?>&tab=products" style="padding:0.2rem 0.5rem">Toggle</a>
                        <a class="btn-secondary" href="?tab=edit_product&id=<?= $p['id'] ?>" style="padding:0.2rem 0.5rem">Edit</a>
                        <a class="btn-secondary" href="../product.php?slug=<?= $p['slug'] ?>" target="_blank" style="padding:0.2rem 0.5rem">View</a>
                        <a class="btn-secondary" href="?action=delete&id=<?= $p['id'] ?>&tab=products" onclick="return confirm('Delete?')" style="padding:0.2rem 0.5rem;color:#dc2626">Del</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- No Results Message -->
    <div id="noResultsMsg" style="display: none; text-align: center; padding: 2rem; color: var(--muted);">
        🔍 No products found matching your search.
    </div>
</div>

<!-- Live Search JavaScript -->
<script>
(function() {
    const searchInput = document.getElementById('liveProductSearch');
    const tableBody = document.getElementById('productsTableBody');
    const productCountSpan = document.getElementById('productCount');
    const noResultsDiv = document.getElementById('noResultsMsg');
    const loader = document.getElementById('searchLoader');
    let searchTimeout;
    
    if (!searchInput || !tableBody) return;
    
    // Store all original rows
    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    const totalProducts = allRows.length;
    
    function performSearch() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        
        // Show loader
        if (loader) loader.style.display = 'block';
        
        // Debounce search
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            let visibleCount = 0;
            
            allRows.forEach(row => {
                const productName = row.getAttribute('data-product-name') || '';
                const brandName = row.getAttribute('data-brand-name') || '';
                const productId = row.getAttribute('data-product-id') || '';
                
                // Search in product name, brand name, and ID
                const matches = searchTerm === '' || 
                               productName.includes(searchTerm) || 
                               brandName.includes(searchTerm) || 
                               productId.includes(searchTerm);
                
                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count
            if (productCountSpan) {
                productCountSpan.textContent = visibleCount;
            }
            
            // Show/hide no results message
            if (noResultsDiv) {
                if (searchTerm !== '' && visibleCount === 0) {
                    noResultsDiv.style.display = 'block';
                } else {
                    noResultsDiv.style.display = 'none';
                }
            }
            
            // Hide loader
            if (loader) loader.style.display = 'none';
        }, 300);
    }
    
    // Event listeners
    searchInput.addEventListener('keyup', performSearch);
    searchInput.addEventListener('search', performSearch);
    
    // Clear search on Escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchInput.value = '';
            performSearch();
        }
    });
})();
</script>
<?php endif; ?>

            <!-- ADD PRODUCT -->
            <?php if ($currentTab === 'add_product'): ?>
            <div class="form-card">
                <h3>➕ Add New Product</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-grid">
                        <div class="form-group"><label>Brand *</label><select name="brand_id" required><?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Product Name *</label><input name="name" required></div>
                        <div class="form-group"><label>Base Price (LKR) *</label><input name="base_price" type="number" required></div>
                        <div class="form-group"><label>Old Price (LKR)</label><input name="old_price" type="number"></div>
                        <div class="form-group"><label>Stock Quantity</label><input name="stock_quantity" type="number" value="0"></div>
                        <div class="form-group"><label>Badge</label><select name="badge"><option value="">None</option><option>New</option><option>Hot</option><option>Best Value</option></select></div>
                        <div class="form-group"><label>📷 Product Image (JPG, PNG, WEBP)</label><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></div>
                        <div class="form-group"><label>In Stock</label><select name="in_stock"><option value="1">Yes</option><option value="0">No</option></select></div>
                        <!-- Multiple Images Upload - Add this AFTER the main product image field -->
<div style="margin:1rem 0">
    <label>📸 Multiple Product Images (Max 5 images)</label>
    <input type="file" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple id="multiImagesInput">
    <div id="imagePreview" class="images-preview"></div>
    <small style="color:var(--muted)">Select up to 5 images. First image will be main if no main image selected. JPG, PNG, WEBP only.</small>
</div>
                    </div>
                    
                    <div class="form-group"><label>Short Description</label><input name="short_desc"></div>
                    <div class="form-group"><label>Full Description</label><textarea name="description" rows="3"></textarea></div>
                    
                    <div style="margin:1rem 0"><label>🎨 Colors</label><div id="colorsWrap"><div class="dynamic-row"><input type="color" name="color_hex[]" value="#000000"><input name="color_name[]" placeholder="Color name"></div></div>
                    <button type="button" class="btn-secondary" onclick="addColor()">+ Add Color</button></div>
                    
                    <div style="margin:1rem 0"><label>💾 Storage Options</label><div id="storageWrap"><div class="dynamic-row"><input name="storage_label[]" placeholder="Label"><input name="storage_add[]" type="number" placeholder="Price add" value="0"></div></div>
                    <button type="button" class="btn-secondary" onclick="addStorage()">+ Add Storage</button></div>
                    
                    <button type="submit" class="btn-primary">💾 Save Product</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- EDIT PRODUCT TAB -->
<?php if ($currentTab === 'edit_product' && isset($_GET['id'])):
    $edit_id = $_GET['id'];
    
    // Get product details
    $stmt = $db->prepare("SELECT p.*, b.name as brand_name FROM products p JOIN brands b ON p.brand_id = b.id WHERE p.id = ? AND p.active = 1");
    $stmt->execute([$edit_id]);
    $editProduct = $stmt->fetch();
    
    if (!$editProduct) {
        echo '<div class="form-card"><div style="color:red;text-align:center;">Product not found!</div></div>';
    } else {
        // Get product colors
        $colors = $db->prepare("SELECT * FROM product_colors WHERE product_id = ? ORDER BY sort_order");
        $colors->execute([$edit_id]);
        $existingColors = $colors->fetchAll();
        
        // Get product storage
        $storage = $db->prepare("SELECT * FROM product_storage WHERE product_id = ? ORDER BY sort_order");
        $storage->execute([$edit_id]);
        $existingStorage = $storage->fetchAll();
        
        // Get product multiple images
        $productImages = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
        $productImages->execute([$edit_id]);
        $productImages = $productImages->fetchAll();
?>
<div class="form-card">
    <h3>✏️ Edit Product: <?= htmlspecialchars($editProduct['name']) ?></h3>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'image_deleted'): ?>
        <div style="background:#dcfce7;color:#16a34a;padding:0.8rem;border-radius:8px;margin-bottom:1rem">✅ Image deleted successfully!</div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_product">
        <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Brand *</label>
                <select name="brand_id" required>
                    <?php 
                    $brands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY name")->fetchAll();
                    foreach($brands as $b): 
                    ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $editProduct['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Product Name *</label>
                <input name="name" value="<?= htmlspecialchars($editProduct['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Base Price (LKR) *</label>
                <input name="base_price" type="number" step="0.01" value="<?= $editProduct['base_price'] ?>" required>
            </div>
            
            <div class="form-group">
                <label>Old Price (LKR)</label>
                <input name="old_price" type="number" step="0.01" value="<?= $editProduct['old_price'] ?>">
            </div>
            
            <div class="form-group">
                <label>Stock Quantity</label>
                <input name="stock_quantity" type="number" value="<?= $editProduct['stock_quantity'] ?>">
            </div>
            
            <div class="form-group">
                <label>Badge</label>
                <select name="badge">
                    <option value="">None</option>
                    <option value="New" <?= $editProduct['badge'] == 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Hot" <?= $editProduct['badge'] == 'Hot' ? 'selected' : '' ?>>Hot</option>
                    <option value="Best Value" <?= $editProduct['badge'] == 'Best Value' ? 'selected' : '' ?>>Best Value</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>📷 New Main Image</label>
                <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($editProduct['product_image']) && file_exists('../' . $editProduct['product_image'])): ?>
                    <small>Current: <img src="/cc/<?= $editProduct['product_image'] ?>" style="height:30px; border-radius:4px;"></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>In Stock</label>
                <select name="in_stock">
                    <option value="1" <?= $editProduct['in_stock'] ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= !$editProduct['in_stock'] ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Short Description</label>
            <input name="short_desc" value="<?= htmlspecialchars($editProduct['short_desc']) ?>">
        </div>
        
        <div class="form-group">
            <label>Full Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($editProduct['description']) ?></textarea>
        </div>
        
        <!-- ========== MULTIPLE IMAGES SECTION ========== -->
        <?php if (!empty($productImages)): ?>
        <div style="margin:1rem 0">
            <label>📸 Product Images (<?= count($productImages) ?>/5)</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                <?php foreach ($productImages as $img): ?>
                <div style="position: relative; width: 80px; height: 80px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #f7f7f5;">
                    <img src="/cc/<?= $img['image_url'] ?>" style="width: 100%; height: 100%; object-fit: contain;">
                    <a href="?action=delete_product_image&img_id=<?= $img['id'] ?>&tab=edit_product&id=<?= $editProduct['id'] ?>" 
                       style="position: absolute; top: -8px; right: -8px; background: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 12px; font-weight: bold;"
                       onclick="return confirm('Delete this image?')">✕</a>
                </div>
                <?php endforeach; ?>
            </div>
            <small style="color:var(--muted); margin-top:5px; display:block;">Click ✕ to delete image</small>
        </div>
        <?php endif; ?>
        
        <!-- Add More Images -->
        <?php if (count($productImages) < 5): ?>
        <div style="margin:1rem 0">
            <label>➕ Add More Images (Max <?= 5 - count($productImages) ?> more)</label>
            <input type="file" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple id="editMultiImagesInput">
            <div id="editImagePreview" class="images-preview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;"></div>
            <small style="color:var(--muted)">Select up to <?= 5 - count($productImages) ?> images. JPG, PNG, WEBP only.</small>
        </div>
        <?php endif; ?>
        
        <!-- ========== COLORS SECTION ========== -->
        <div style="margin:1rem 0">
            <label>🎨 Colors</label>
            <div id="colorsWrap">
                <?php if (!empty($existingColors)): ?>
                    <?php foreach ($existingColors as $c): ?>
                    <div class="dynamic-row">
                        <input type="color" name="color_hex[]" value="<?= $c['hex_code'] ?>">
                        <input name="color_name[]" value="<?= htmlspecialchars($c['name']) ?>" placeholder="Color name">
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dynamic-row">
                        <input type="color" name="color_hex[]" value="#000000">
                        <input name="color_name[]" placeholder="Color name">
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-secondary" onclick="addColor()" style="margin-top: 0.5rem;">+ Add Color</button>
        </div>
        
        <!-- ========== STORAGE SECTION ========== -->
        <div style="margin:1rem 0">
            <label>💾 Storage Options</label>
            <div id="storageWrap">
                <?php if (!empty($existingStorage)): ?>
                    <?php foreach ($existingStorage as $s): ?>
                    <div class="dynamic-row">
                        <input name="storage_label[]" value="<?= htmlspecialchars($s['label']) ?>" placeholder="Label">
                        <input name="storage_add[]" type="number" value="<?= $s['price_add'] ?>" placeholder="Price add">
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dynamic-row">
                        <input name="storage_label[]" placeholder="Label">
                        <input name="storage_add[]" type="number" placeholder="Price add" value="0">
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-secondary" onclick="addStorage()" style="margin-top: 0.5rem;">+ Add Storage</button>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <button type="submit" class="btn-primary">💾 Update Product</button>
            <a href="?tab=products" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php 
    }
endif; 
?>

            <!-- BRANDS -->
            <?php if ($currentTab === 'brands'): ?>
            <div class="form-card">
                <h3>➕ Add New Brand</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_brand">
                    <div class="form-grid">
                        <div class="form-group"><label>Brand Name</label><input name="name" required></div>
                        <div class="form-group"><label>Emoji (fallback)</label><input name="emoji" value="📱"></div>
                        <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" value="0"></div>
                    </div>
                    <button type="submit" class="btn-primary">➕ Add Brand</button>
                </form>
            </div>

            <div class="form-card">
                <h3>🏷️ All Brands & Image Upload</h3>
                <table class="data-table">
                    <thead><tr><th>Logo</th><th>Brand</th><th>Image URL</th><th>Upload Image</th><!--<th>Actions</th>--></tr></thead>
                    <tbody>
                        <?php foreach ($brands as $b): ?>
                        <tr>
                            <td>
                                <div class="brand-image-preview">
                                    <?php if (!empty($b['image_url']) && file_exists('../' . $b['image_url'])): ?>
                                        <img src="/cc/<?= $b['image_url'] ?>" alt="<?= $b['name'] ?>">
                                    <?php else: ?>
                                        <span style="font-size:1.5rem"><?= $b['emoji'] ?? '📱' ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?= $b['name'] ?></strong><br><small><?= $b['slug'] ?></small></td>
                            <td><small><?= $b['image_url'] ?? 'Not set' ?></small></td>
                            <td>
                                <form method="POST" enctype="multipart/form-data" style="display:flex;gap:0.5rem">
                                    <input type="hidden" name="action" value="upload_brand_image">
                                    <input type="hidden" name="brand_id" value="<?= $b['id'] ?>">
                                    <input type="file" name="brand_image" accept="image/jpeg,image/png,image/webp" required style="font-size:0.7rem">
                                    <button type="submit" class="btn-primary" style="padding:0.3rem 0.8rem">Upload</button>
                                </form>
                            </td>
                            <!--<td><a href="?tab=brands&action=edit_brand&id=<?= $b['id'] ?>" class="btn-secondary" style="padding:0.3rem 0.6rem">Edit</a></td>
                        </tr>-->
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- BANNERS TAB -->
            <?php if ($currentTab === 'banners'): ?>

            <!-- Add Banner Form -->
            <div class="form-card">
                <h3>➕ Add New Banner</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_banner">
                    <div class="form-grid">
                        <div class="form-group"><label>Brand Name</label><input name="brand" placeholder="SAMSUNG"></div>
                        <div class="form-group"><label>Year</label><input name="year" value="2026"></div>
                        <div class="form-group"><label>Title *</label><input name="title" required placeholder="Galaxy S26 Ultra"></div>
                        <div class="form-group"><label>Tag</label><input name="tag" placeholder="New Arrival · 2026"></div>
                        <div class="form-group"><label>Button Text</label><input name="button_text" value="Shop Now"></div>
                        <div class="form-group"><label>Button Link</label><input name="button_link" value="category.php"></div>
                        <div class="form-group"><label>Button Type</label>
                            <select name="button_type">
                                <option value="primary">Primary (Red)</option>
                                <option value="ghost">Ghost (White)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Background Color</label><input type="color" name="bg_color" value="#f7f7f5"></div>
                        <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" value="0"></div>
                        <div class="form-group"><label>Image Alt Text</label><input name="image_alt" placeholder="Banner description"></div>
                    </div>
                    <div class="form-group"><label>Subtitle</label><textarea name="subtitle" rows="2"></textarea></div>
                    <div class="form-group">
                        <label>📷 Banner Image (JPG, PNG, WEBP) - Max 2MB</label>
                        <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                        <small style="color:var(--muted)">Recommended size: 1200 x 500 pixels, Max 2MB</small>
                    </div>
                    <button type="submit" class="btn-primary">➕ Add Banner</button>
                </form>
            </div>

            <!-- Banners List -->
            <div class="form-card">
                <h3>🎨 All Banners (<?= count($bannersList) ?>)</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Brand/Title</th>
                            <th>Tag</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bannersList as $b): ?>
                        <tr>
                            <td><?= $b['id'] ?></td>
                            <td>
                                <?php if (!empty($b['image_url']) && file_exists('../' . $b['image_url'])): ?>
                                    <img src="/cc/<?= $b['image_url'] ?>" style="width: 80px; height: 45px; object-fit: cover; border-radius: 6px;">
                                <?php else: ?>
                                    <span style="font-size: 1.5rem;">📷</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['brand'] ?? '') ?></strong><br>
                                <small><?= htmlspecialchars(substr($b['title'], 0, 40)) ?></small>
                            </td>
                            <td><?= htmlspecialchars($b['tag']) ?></td>
                            <td><?= $b['sort_order'] ?></td>
                            <td>
                                <span class="badge <?= $b['active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $b['active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="act-btns">
                                <a class="btn-secondary" href="?tab=banners&action=edit_banner&id=<?= $b['id'] ?>" style="padding:0.2rem 0.5rem">Edit</a>
                                <a class="btn-secondary" href="?action=toggle_banner&id=<?= $b['id'] ?>&tab=banners" style="padding:0.2rem 0.5rem">Toggle</a>
                                <a class="btn-secondary" href="?action=delete_banner&id=<?= $b['id'] ?>&tab=banners" onclick="return confirm('Delete this banner?')" style="padding:0.2rem 0.5rem;color:#dc2626">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Banner Modal -->
            <?php if (isset($editBanner)): ?>
            <div class="modal-overlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:1000;">
                <div class="modal-content" style="background:#fff; border-radius:16px; padding:2rem; max-width:650px; width:90%; max-height:90%; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3>✏️ Edit Banner #<?= $editBanner['id'] ?></h3>
                        <a href="?tab=banners" style="font-size:1.5rem; text-decoration:none; color:var(--muted);">&times;</a>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_banner">
                        <input type="hidden" name="banner_id" value="<?= $editBanner['id'] ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>Brand Name</label><input name="brand" value="<?= htmlspecialchars($editBanner['brand']) ?>"></div>
                            <div class="form-group"><label>Year</label><input name="year" value="<?= htmlspecialchars($editBanner['year']) ?>"></div>
                            <div class="form-group"><label>Title *</label><input name="title" value="<?= htmlspecialchars($editBanner['title']) ?>" required></div>
                            <div class="form-group"><label>Tag</label><input name="tag" value="<?= htmlspecialchars($editBanner['tag']) ?>"></div>
                            <div class="form-group"><label>Button Text</label><input name="button_text" value="<?= htmlspecialchars($editBanner['button_text']) ?>"></div>
                            <div class="form-group"><label>Button Link</label><input name="button_link" value="<?= htmlspecialchars($editBanner['button_link']) ?>"></div>
                            <div class="form-group"><label>Button Type</label>
                                <select name="button_type">
                                    <option value="primary" <?= $editBanner['button_type'] === 'primary' ? 'selected' : '' ?>>Primary</option>
                                    <option value="ghost" <?= $editBanner['button_type'] === 'ghost' ? 'selected' : '' ?>>Ghost</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Background Color</label><input type="color" name="bg_color" value="<?= $editBanner['bg_color'] ?>"></div>
                            <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" value="<?= $editBanner['sort_order'] ?>"></div>
                            <div class="form-group"><label>Image Alt Text</label><input name="image_alt" value="<?= htmlspecialchars($editBanner['image_alt']) ?>"></div>
                        </div>
                        <div class="form-group"><label>Subtitle</label><textarea name="subtitle" rows="2"><?= htmlspecialchars($editBanner['subtitle']) ?></textarea></div>
                        
                        <?php if (!empty($editBanner['image_url']) && file_exists('../' . $editBanner['image_url'])): ?>
                        <div class="form-group">
                            <label>Current Image</label><br>
                            <img src="/cc/<?= $editBanner['image_url'] ?>" style="width: 200px; height: auto; border-radius: 8px; border:1px solid var(--border);">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>📷 New Image (leave empty to keep current)</label>
                            <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp">
                            <small>Max 2MB, recommended 1200x600px</small>
                        </div>
                        <button type="submit" class="btn-primary">💾 Save Changes</button>
                        <a href="?tab=banners" class="btn-secondary" style="margin-left:0.5rem;">Cancel</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

            <!-- ORDERS -->
            <!-- ORDERS - IMPROVED VERSION WITH ALL DETAILS (FIXED - No transaction_id) -->
            <?php if ($currentTab === 'orders'): 
                // Get detailed orders with all information from addresses table
                $detailedOrders = $db->query("
                    SELECT o.*, 
                           u.first_name, u.last_name, u.email, u.phone,
                           a.address_line1 as address, a.city, a.district,
                           o.shipping_address, o.shipping_city, o.shipping_district,
                           o.payment_method, o.payment_status,
                           o.order_status, o.total_amount,
                           o.created_at, o.updated_at
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    LEFT JOIN addresses a ON u.id = a.user_id AND a.is_default = 1
                    ORDER BY o.created_at DESC
                ")->fetchAll();
                
                // Helper function to get order items with product details
                function getOrderItems($orderId, $db) {
                    $stmt = $db->prepare("
                        SELECT oi.*, p.name as product_name, p.product_image, p.slug
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?
                    ");
                    $stmt->execute([$orderId]);
                    return $stmt->fetchAll();
                }
            ?>
            <div class="form-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <h3>📦 All Orders (<?= count($detailedOrders) ?>)</h3>
                    <div>
                        <input type="text" id="orderSearch" placeholder="🔍 Search by name, phone, order #..." style="padding:0.5rem 1rem; border:1px solid var(--border); border-radius:8px; width:250px;">
                    </div>
                </div>
                
                <style>
                    .order-card-hover { transition: all 0.2s ease; }
                    .order-card-hover:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
                    .status-badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; }
                </style>
                
                <div id="ordersList">
                <?php if (empty($detailedOrders)): ?>
                    <div style="text-align:center; padding:3rem; color:var(--muted);">
                        📭 No orders found
                    </div>
                <?php else: ?>
                <?php foreach ($detailedOrders as $order): 
                    $orderItems = getOrderItems($order['id'], $db);
                    $orderIdPadded = str_pad($order['id'], 6, '0', STR_PAD_LEFT);
                ?>
                <div class="order-card-hover order-item" data-search="<?= strtolower($order['first_name'] . ' ' . $order['last_name'] . ' ' . $order['phone'] . ' ' . $orderIdPadded) ?>" style="background:#fff; border:1px solid var(--border); border-radius:12px; margin-bottom:1.5rem; overflow:hidden;">
                    <!-- Order Header -->
                    <div style="background:var(--bg); padding:0.8rem 1.2rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                        <div style="display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap;">
                            <strong style="font-size:1rem;">🆔 Order #<?= $orderIdPadded ?></strong>
                            <?php
                                $statusColor = match($order['order_status'] ?? 'pending') {
                                    'delivered' => '#16a34a',
                                    'processing' => '#d97706', 
                                    'shipped' => '#3b82f6',
                                    'cancelled' => '#dc2626',
                                    default => '#9b9b97'
                                };
                                $paymentStatusColor = ($order['payment_status'] ?? 'pending') === 'paid' ? '#16a34a' : '#dc2626';
                                $paymentStatusBg = ($order['payment_status'] ?? 'pending') === 'paid' ? '#dcfce7' : '#fee2e2';
                            ?>
                            <span class="status-badge" style="background:<?= $statusColor ?>15; color:<?= $statusColor ?>; border:1px solid <?= $statusColor ?>30;">
                                <?= match($order['order_status'] ?? 'pending') {
                                    'delivered' => '✅ Delivered',
                                    'processing' => '⚙️ Processing',
                                    'shipped' => '🚚 Shipped',
                                    'cancelled' => '❌ Cancelled',
                                    default => '⏳ Pending'
                                } ?>
                            </span>
                            <span class="status-badge" style="background:<?= $paymentStatusBg ?>; color:<?= $paymentStatusColor ?>;">
                                <?= ($order['payment_status'] ?? 'pending') === 'paid' ? '💰 Paid' : '⏰ Pending Payment' ?>
                            </span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--muted);">
                            📅 <?= date('F d, Y h:i A', strtotime($order['created_at'])) ?>
                        </div>
                    </div>
                    
                    <!-- Order Body - 3 Columns -->
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; padding:1.2rem;">
                        <!-- Customer Info -->
                        <div style="border-right:1px solid var(--border); padding-right:1rem;">
                            <div style="font-weight:600; margin-bottom:0.6rem; color:var(--red);">👤 Customer Details</div>
                            <div style="font-size:0.85rem; line-height:1.6;">
                                <div><strong>Name:</strong> <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div>
                                <div><strong>📞 Phone:</strong> <a href="tel:<?= htmlspecialchars($order['phone']) ?>" style="color:var(--red); text-decoration:none; font-weight:500;"><?= htmlspecialchars($order['phone']) ?></a></div>
                                <div><strong>✉️ Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
                            </div>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div style="border-right:1px solid var(--border); padding-right:1rem;">
                            <div style="font-weight:600; margin-bottom:0.6rem; color:var(--red);">📍 Shipping Address</div>
                            <div style="font-size:0.85rem; line-height:1.6;">
                                <?php 
                                    $address = !empty($order['shipping_address']) ? $order['shipping_address'] : ($order['address'] ?? 'N/A');
                                    $city = !empty($order['shipping_city']) ? $order['shipping_city'] : ($order['city'] ?? 'N/A');
                                    $district = !empty($order['shipping_district']) ? $order['shipping_district'] : ($order['district'] ?? 'N/A');
                                ?>
                                <div><?= nl2br(htmlspecialchars($address)) ?></div>
                                <div><?= htmlspecialchars($city) ?></div>
                                <div><?= htmlspecialchars($district) ?></div>
                            </div>
                        </div>
                        
                        <!-- Payment & Order Info -->
                        <div>
                            <div style="font-weight:600; margin-bottom:0.6rem; color:var(--red);">💰 Payment Details</div>
                            <div style="font-size:0.85rem; line-height:1.6;">
                                <?php 
                                    $method = $order['payment_method'] ?? 'N/A';
                                    $methodDisplay = match(strtolower($method)) {
                                        'card' => '💳 Card Payment',
                                        'cash_on_delivery', 'cod' => '🚚 Cash on Delivery',
                                        'bank_transfer', 'bank' => '🏦 Bank Transfer',
                                        'mobile' => '📱 Mobile Payment',
                                        default => '💰 ' . str_replace('_', ' ', ucfirst($method))
                                    };
                                ?>
                                <div><strong>Method:</strong> <?= $methodDisplay ?></div>
                                <div style="margin-top:0.6rem; padding-top:0.5rem; border-top:1px dashed var(--border); font-size:1rem;">
                                    <strong>Total Amount:</strong> 
                                    <span style="color:var(--red); font-size:1.1rem;"><?= formatPrice($order['total_amount']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items Table -->
                    <div style="border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:1rem 1.2rem; background:var(--bg);">
                        <div style="font-weight:600; margin-bottom:0.8rem;">🛍️ Order Items (<?= count($orderItems) ?>)</div>
                        <table style="width:100%; font-size:0.8rem; border-collapse:collapse;">
                            <thead style="border-bottom:1px solid var(--border);">
                                <tr style="color:var(--muted);">
                                    <th style="text-align:left; padding:0.5rem 0;">Product</th>
                                    <th style="text-align:center; width:80px;">Qty</th>
                                    <th style="text-align:right; width:110px;">Unit Price</th>
                                    <th style="text-align:right; width:110px;">Total</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td style="padding:0.6rem 0;">
                                        <?php if (!empty($item['product_image']) && file_exists('../' . $item['product_image'])): ?>
                                            <img src="/cc/<?= htmlspecialchars($item['product_image']) ?>" style="width:32px; height:32px; object-fit:cover; border-radius:8px; vertical-align:middle; margin-right:0.8rem;">
                                        <?php else: ?>
                                            <span style="font-size:1.2rem; margin-right:0.8rem;">📱</span>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                      </td>
                                    <td style="text-align:center;">✕ <?= (int)$item['quantity'] ?></td>
                                    <td style="text-align:right;"><?= formatPrice($item['price']) ?></td>
                                    <td style="text-align:right; font-weight:500;"><?= formatPrice($item['total']) ?></td>
                                 </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td colspan="3" style="text-align:right; padding:0.8rem 0.5rem 0.5rem 0;"><strong>Grand Total:</strong></td>
                                    <td style="text-align:right; padding:0.8rem 0 0.5rem 0;"><strong style="color:var(--red); font-size:1rem;"><?= formatPrice($order['total_amount']) ?></strong></td>
                                  </tr>
                            </tfoot>
                         </table>
                    </div>
                    
                    <!-- Order Actions -->
                    <div style="padding:0.8rem 1.2rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_order">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="order_status" onchange="this.form.submit()" style="padding:0.4rem 0.8rem; border-radius:6px; border:1px solid var(--border); background:#fff; cursor:pointer;">
                                <option value="pending" <?= ($order['order_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                <option value="processing" <?= ($order['order_status'] ?? '') === 'processing' ? 'selected' : '' ?>>⚙️ Processing</option>
                                <option value="shipped" <?= ($order['order_status'] ?? '') === 'shipped' ? 'selected' : '' ?>>🚚 Shipped</option>
                                <option value="delivered" <?= ($order['order_status'] ?? '') === 'delivered' ? 'selected' : '' ?>>✅ Delivered</option>
                                <option value="cancelled" <?= ($order['order_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                            </select>
                        </form>
                        <div style="display:flex; gap:0.5rem;">
                            <button onclick="printOrder(<?= $order['id'] ?>)" class="btn-secondary" style="padding:0.4rem 0.8rem; cursor:pointer;">🖨️ Print</button>
                            <button onclick="window.open('../order-details.php?id=<?= $order['id'] ?>', '_blank')" class="btn-secondary" style="padding:0.4rem 0.8rem; cursor:pointer;">📄 View Details</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
            
            <script>
            // Search functionality
            const searchInput = document.getElementById('orderSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const orders = document.querySelectorAll('.order-item');
                    
                    orders.forEach(order => {
                        const searchData = order.getAttribute('data-search') || '';
                        if (searchData.includes(searchTerm) || searchTerm === '') {
                            order.style.display = 'block';
                        } else {
                            order.style.display = 'none';
                        }
                    });
                });
            }
            
            function printOrder(orderId) {
                const orders = document.querySelectorAll('.order-item');
                let orderDiv = null;
                
                for (let i = 0; i < orders.length; i++) {
                    if (orders[i].getAttribute('data-search') && orders[i].getAttribute('data-search').includes(orderId)) {
                        orderDiv = orders[i];
                        break;
                    }
                }
                
                if (orderDiv) {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <html>
                        <head>
                            <title>Order #${String(orderId).padStart(6, '0')} - RedZone.lk</title>
                            <style>
                                body { font-family: 'DM Sans', sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto; }
                                .header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #d72c2c; padding-bottom: 1rem; }
                                .print-order-details { margin-bottom: 2rem; }
                                table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
                                th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
                                .total { font-size: 1.2rem; font-weight: bold; color: #d72c2c; text-align: right; }
                                @media print { body { margin: 0; padding: 1rem; } }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <h1>REDZONE.LK</h1>
                                <p>Order Invoice</p>
                            </div>
                            <div class="print-order-details">
                                ${orderDiv.cloneNode(true).innerHTML}
                            </div>
                            <p style="text-align:center; margin-top:2rem; font-size:0.8rem; color:#666;">
                                Thank you for shopping with RedZone.lk
                            </p>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                }
            }
            </script>
            <?php endif; ?>

            <!-- CUSTOMERS -->
            <?php if ($currentTab === 'customers'): ?>
            <div class="form-card">
                <h3>👥 Registered Customers</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered Date</th><th>Orders</th><tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <?php $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?"); $orderCount->execute([$u['id']]); ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= $u['email'] ?></td>
                            <td><?= $u['phone'] ?? '-' ?></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td><?= $orderCount->fetchColumn() ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- LOW STOCK -->
            <?php if ($currentTab === 'low_stock'): ?>
            <div class="form-card">
                <h3>⚠️ Low Stock Products (Stock < 10)</h3>
                <table class="data-table">
                    <thead><tr><th>Image</th><th>Product</th><th>Brand</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php $lowStockAll = $db->query("SELECT p.*, b.name as brand_name FROM products p JOIN brands b ON p.brand_id = b.id WHERE p.stock_quantity < 10 AND p.active = 1 ORDER BY p.stock_quantity ASC")->fetchAll(); ?>
                        <?php foreach ($lowStockAll as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['product_image']) && file_exists('../' . $p['product_image'])): ?>
                                    <img src="/cc/<?= $p['product_image'] ?>" style="width: 40px; height: 40px; object-fit: contain;">
                                <?php else: ?>
                                    <span style="font-size:1.2rem"><?= $p['emoji'] ?? '📱' ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= $p['brand_name'] ?></td>
                            <td><span class="badge <?= $p['stock_quantity'] == 0 ? 'badge-danger' : 'badge-warning' ?>"><?= $p['stock_quantity'] ?></span></td>
                            <td><?= $p['in_stock'] ? 'Active' : 'Inactive' ?></td>
                            <td><a href="?tab=products" class="btn-secondary">Edit</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>
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
    div.innerHTML = '<input name="storage_label[]" placeholder="Label"><input name="storage_add[]" type="number" placeholder="Price add" value="0">';
    document.getElementById('storageWrap').appendChild(div);
}

// Image preview for add product
const multiInput = document.getElementById('multiImagesInput');
const previewDiv = document.getElementById('imagePreview');

if (multiInput) {
    multiInput.addEventListener('change', function() {
        previewDiv.innerHTML = '';
        const files = Array.from(this.files);
        const maxFiles = Math.min(files.length, 5);
        for (let i = 0; i < maxFiles; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview"><div class="remove-img" onclick="this.parentElement.remove()">✕</div>`;
                previewDiv.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
}

// Image preview for edit product
const editMultiInput = document.getElementById('editMultiImagesInput');
const editPreviewDiv = document.getElementById('editImagePreview');

if (editMultiInput) {
    editMultiInput.addEventListener('change', function() {
        editPreviewDiv.innerHTML = '';
        const files = Array.from(this.files);
        const maxFiles = Math.min(files.length, 5);
        for (let i = 0; i < maxFiles; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview"><div class="remove-img" onclick="this.parentElement.remove()">✕</div>`;
                editPreviewDiv.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>
</body>
</html>