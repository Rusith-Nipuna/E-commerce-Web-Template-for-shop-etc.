<?php
// admin/brand_upload.php - Complete Brand Image Upload System
require_once '../includes/config.php';

session_start();
if (!isset($_SESSION['rz_admin'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['brand_image'])) {
    $brand_id = $_POST['brand_id'] ?? 0;
    $image_alt = $_POST['image_alt'] ?? '';
    
    if ($brand_id <= 0) {
        $error = 'Please select a brand';
    } else {
        $file = $_FILES['brand_image'];
        
        // Allowed file types
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_type = $file['type'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (in_array($file_type, $allowed_types) && in_array($file_ext, $allowed_ext)) {
                
                // Get brand name for filename
                $stmt = $db->prepare("SELECT name, slug FROM brands WHERE id = ?");
                $stmt->execute([$brand_id]);
                $brand = $stmt->fetch();
                
                if ($brand) {
                    // Create unique filename
                    $new_filename = $brand['slug'] . '_' . time() . '.' . $file_ext;
                    $upload_dir = '../uploads/brands/';
                    
                    // Create folder if not exists
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old image if exists
                        $stmt = $db->prepare("SELECT image_url FROM brands WHERE id = ?");
                        $stmt->execute([$brand_id]);
                        $old = $stmt->fetch();
                        if ($old && $old['image_url'] && file_exists($old['image_url'])) {
                            unlink($old['image_url']);
                        }
                        
                        // Update database with new image URL
                        $image_url = 'uploads/brands/' . $new_filename;
                        $stmt = $db->prepare("UPDATE brands SET image_url = ?, image_alt = ? WHERE id = ?");
                        $stmt->execute([$image_url, $image_alt ?: $brand['name'], $brand_id]);
                        
                        $message = 'Brand image uploaded successfully!';
                    } else {
                        $error = 'Failed to upload image';
                    }
                } else {
                    $error = 'Brand not found';
                }
            } else {
                $error = 'Only JPG, PNG, WEBP images are allowed';
            }
        } else {
            $error = 'Please select an image file';
        }
    }
}

// Get all brands
$brands = $db->query("SELECT * FROM brands ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Image Upload - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f7f7f5;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #111110;
        }
        .message {
            background: #dcfce7;
            color: #16a34a;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 3px solid #16a34a;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 3px solid #dc2626;
        }
        .brands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .brand-card {
            background: #fff;
            border: 1px solid #e8e8e4;
            border-radius: 16px;
            padding: 1.5rem;
            transition: box-shadow 0.2s;
        }
        .brand-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .brand-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e8e8e4;
        }
        .brand-preview {
            width: 70px;
            height: 70px;
            background: #f7f7f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .brand-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .brand-preview span {
            font-size: 2.5rem;
        }
        .brand-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            color: #111110;
        }
        .brand-info p {
            font-size: 0.75rem;
            color: #6b6b67;
        }
        .current-image {
            margin: 1rem 0;
            padding: 0.8rem;
            background: #f7f7f5;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #6b6b67;
            word-break: break-all;
        }
        .upload-form {
            margin-top: 1rem;
        }
        .upload-form input[type="text"] {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e8e8e4;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            margin-bottom: 0.8rem;
        }
        .upload-form input[type="file"] {
            width: 100%;
            padding: 0.4rem;
            border: 1px solid #e8e8e4;
            border-radius: 8px;
            margin-bottom: 0.8rem;
        }
        .upload-form button {
            width: 100%;
            background: #d72c2c;
            color: #fff;
            border: none;
            padding: 0.6rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .upload-form button:hover {
            background: #b91c1c;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: #6b6b67;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .back-link:hover {
            color: #d72c2c;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📸 Brand Image Upload</h1>
    
    <?php if ($message): ?>
        <div class="message">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="brands-grid">
        <?php foreach ($brands as $brand): ?>
        <div class="brand-card">
            <div class="brand-header">
                <div class="brand-preview">
                    <?php if (!empty($brand['image_url']) && file_exists($brand['image_url'])): ?>
                        <img src="<?= $brand['image_url'] ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
                    <?php else: ?>
                        <span><?= $brand['emoji'] ?? '📱' ?></span>
                    <?php endif; ?>
                </div>
                <div class="brand-info">
                    <h3><?= htmlspecialchars($brand['name']) ?></h3>
                    <p>Slug: <?= htmlspecialchars($brand['slug']) ?></p>
                </div>
            </div>
            
            <?php if (!empty($brand['image_url']) && file_exists($brand['image_url'])): ?>
            <div class="current-image">
                📷 Current: <?= basename($brand['image_url']) ?>
            </div>
            <?php endif; ?>
            
            <form class="upload-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="brand_id" value="<?= $brand['id'] ?>">
                <input type="text" name="image_alt" placeholder="Image alt text (e.g., Apple Logo)" value="<?= htmlspecialchars($brand['name']) ?>">
                <input type="file" name="brand_image" accept="image/jpeg,image/png,image/webp" required>
                <button type="submit">Upload Image</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    
    <a href="index.php" class="back-link">← Back to Admin Dashboard</a>
</div>
</body>
</html>