<?php
// account/profile.php - COMPLETE WITH SIDEBAR
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $subscribe = isset($_POST['subscribe']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($phone)) $errors[] = 'Phone number is required';
    
    if (!empty($password)) {
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        $db = getDB();
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, gender=?, subscribe_newsletter=?, password_hash=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $phone, $gender, $subscribe, $hash, $user['id']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, gender=?, subscribe_newsletter=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $phone, $gender, $subscribe, $user['id']]);
        }
        $success = 'Profile updated successfully!';
        $user = getCurrentUser();
    }
}

$pageTitle = 'Edit Profile';
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
            <a href="profile.php" class="active">✏️ Edit Profile</a>
            <a href="orders.php">📦 My Orders</a>
            <a href="wishlist.php">❤️ Wishlist</a>
            <a href="addresses.php">📍 Addresses</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="account-content">
        <div class="account-title">
            <span>✏️ Edit Profile</span>
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success">✅ <?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $e): ?>❌ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="profile-form">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                <small>Email cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">Select gender</option>
                    <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="subscribe" <?= $user['subscribe_newsletter'] ? 'checked' : '' ?>>
                    <span>Subscribe to newsletter</span>
                </label>
            </div>
            
            <div class="password-section">
                <h3>🔐 Change Password</h3>
                <p>Leave blank to keep current password</p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password">
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">💾 Save Changes</button>
                <a href="dashboard.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
/* Account Container - Same as Dashboard */
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
}

/* Alerts */
.alert-success {
    background: #dcfce7;
    color: #16a34a;
    padding: 0.8rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    padding: 0.8rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

/* Form Styles */
.profile-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.profile-form .form-group {
    margin-bottom: 1rem;
}

.profile-form .form-group label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--muted);
    display: block;
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-form .form-group input,
.profile-form .form-group select {
    width: 100%;
    padding: 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
}

.profile-form .form-group input:focus,
.profile-form .form-group select:focus {
    outline: none;
    border-color: var(--red);
}

.profile-form .form-group input:disabled {
    background: var(--bg2);
    color: var(--muted);
    cursor: not-allowed;
}

.profile-form .form-group small {
    font-size: 0.7rem;
    color: var(--muted);
    display: block;
    margin-top: 0.3rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: var(--red);
}

.checkbox-label span {
    font-size: 0.85rem;
    color: var(--muted);
}

.password-section {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.password-section h3 {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.password-section p {
    font-size: 0.75rem;
    color: var(--muted);
    margin-bottom: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-save {
    flex: 1;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.8rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: var(--red2);
}

.btn-cancel {
    flex: 1;
    text-align: center;
    background: var(--bg2);
    color: var(--text);
    border: 1px solid var(--border);
    padding: 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: var(--bg3);
}

/* Responsive */
@media (max-width: 800px) {
    .account-container {
        flex-direction: column;
        padding: 1rem;
    }
    .account-sidebar {
        width: 100%;
    }
    .profile-form .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>