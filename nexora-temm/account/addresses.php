<?php
// account/addresses.php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get addresses
$addresses = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addresses->execute([$user['id']]);
$addressList = $addresses->fetchAll();

// Handle address actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $address_id = $_POST['address_id'] ?? 0;
        
        if ($_POST['action'] === 'set_default') {
            // Remove default from all
            $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
            // Set new default
            $db->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$address_id, $user['id']]);
            header('Location: addresses.php');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([$address_id, $user['id']]);
            header('Location: addresses.php');
            exit;
        }
        
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if ($_POST['action'] === 'add') {
                if ($is_default) {
                    $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
                }
                $stmt = $db->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, district, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $address_line1, $address_line2, $city, $district, $postal_code, $is_default]);
            } else {
                if ($is_default) {
                    $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
                }
                $stmt = $db->prepare("UPDATE addresses SET address_line1=?, address_line2=?, city=?, district=?, postal_code=?, is_default=? WHERE id=? AND user_id=?");
                $stmt->execute([$address_line1, $address_line2, $city, $district, $postal_code, $is_default, $address_id, $user['id']]);
            }
            header('Location: addresses.php');
            exit;
        }
    }
}

// Get address for editing
$editAddress = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $user['id']]);
    $editAddress = $stmt->fetch();
}

$pageTitle = 'My Addresses';
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
            <a href="profile.php">✏️ Edit Profile</a>
            <a href="orders.php">📦 My Orders</a>
            <a href="wishlist.php">❤️ Wishlist</a>
            <a href="addresses.php" class="active">📍 Addresses</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="account-content">
        <div class="account-title">
            <span>📍 My Addresses</span>
            <button class="btn-add-address" onclick="showAddressForm()">+ Add New Address</button>
        </div>
        
        <?php if (empty($addressList)): ?>
            <div class="empty-state">
                <div class="empty-icon">📍</div>
                <h3>No addresses saved</h3>
                <p>Add a delivery address to make checkout faster</p>
                <button onclick="showAddressForm()" class="btn-shop">+ Add New Address</button>
            </div>
        <?php else: ?>
            <div class="addresses-grid">
                <?php foreach ($addressList as $address): ?>
                <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                    <?php if ($address['is_default']): ?>
                        <div class="default-badge">Default</div>
                    <?php endif; ?>
                    <div class="address-text">
                        <p><?= nl2br(htmlspecialchars($address['address_line1'])) ?></p>
                        <?php if ($address['address_line2']): ?>
                            <p><?= htmlspecialchars($address['address_line2']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['district']) ?></p>
                        <?php if ($address['postal_code']): ?>
                            <p><?= htmlspecialchars($address['postal_code']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="address-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="address_id" value="<?= $address['id'] ?>">
                            <?php if (!$address['is_default']): ?>
                                <button type="submit" name="action" value="set_default" class="btn-set-default">Set as Default</button>
                            <?php endif; ?>
                            <a href="?edit=<?= $address['id'] ?>" class="btn-edit">Edit</a>
                            <button type="submit" name="action" value="delete" class="btn-delete" onclick="return confirm('Delete this address?')">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Address Modal -->
<div id="addressModal" class="modal" style="display: <?= $editAddress ? 'flex' : 'none' ?>;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= $editAddress ? 'Edit Address' : 'Add New Address' ?></h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editAddress ? 'edit' : 'add' ?>">
            <?php if ($editAddress): ?>
                <input type="hidden" name="address_id" value="<?= $editAddress['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Address Line 1 *</label>
                <input type="text" name="address_line1" value="<?= htmlspecialchars($editAddress['address_line1'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Address Line 2</label>
                <input type="text" name="address_line2" value="<?= htmlspecialchars($editAddress['address_line2'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($editAddress['city'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>District *</label>
                    <select name="district" required>
                        <option value="">Select District</option>
                        <option value="Colombo" <?= ($editAddress['district'] ?? '') === 'Colombo' ? 'selected' : '' ?>>Colombo</option>
                        <option value="Gampaha" <?= ($editAddress['district'] ?? '') === 'Gampaha' ? 'selected' : '' ?>>Gampaha</option>
                        <option value="Kalutara" <?= ($editAddress['district'] ?? '') === 'Kalutara' ? 'selected' : '' ?>>Kalutara</option>
                        <option value="Kandy" <?= ($editAddress['district'] ?? '') === 'Kandy' ? 'selected' : '' ?>>Kandy</option>
                        <option value="Galle" <?= ($editAddress['district'] ?? '') === 'Galle' ? 'selected' : '' ?>>Galle</option>
                        <option value="Matara" <?= ($editAddress['district'] ?? '') === 'Matara' ? 'selected' : '' ?>>Matara</option>
                        <option value="Kurunegala" <?= ($editAddress['district'] ?? '') === 'Kurunegala' ? 'selected' : '' ?>>Kurunegala</option>
                        <option value="Anuradhapura" <?= ($editAddress['district'] ?? '') === 'Anuradhapura' ? 'selected' : '' ?>>Anuradhapura</option>
                        <option value="Ratnapura" <?= ($editAddress['district'] ?? '') === 'Ratnapura' ? 'selected' : '' ?>>Ratnapura</option>
                        <option value="Jaffna" <?= ($editAddress['district'] ?? '') === 'Jaffna' ? 'selected' : '' ?>>Jaffna</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="postal_code" value="<?= htmlspecialchars($editAddress['postal_code'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" value="1" <?= ($editAddress['is_default'] ?? 0) ? 'checked' : '' ?>>
                    <span>Set as default address</span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">Save Address</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.btn-add-address {
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
}

.btn-add-address:hover {
    background: var(--red2);
}

.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--muted);
    margin-bottom: 1.5rem;
}

.btn-shop {
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    cursor: pointer;
}

.addresses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.address-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    position: relative;
}

.address-card.default {
    border-color: var(--red);
    background: #fff5f5;
}

.default-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: var(--red);
    color: #fff;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
}

.address-text {
    margin-bottom: 1rem;
    padding-right: 60px;
}

.address-text p {
    margin-bottom: 0.2rem;
    font-size: 0.85rem;
}

.address-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.address-actions button,
.address-actions a {
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
}

.btn-set-default {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
}

.btn-set-default:hover {
    border-color: var(--red);
    color: var(--red);
}

.btn-edit {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
}

.btn-delete {
    background: #fee2e2;
    border: 1px solid #dc2626;
    color: #dc2626;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 500px;
    max-width: 90%;
    padding: 1.5rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--muted);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--muted);
    display: block;
    margin-bottom: 0.3rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid var(--border);
    border-radius: 8px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-save {
    flex: 1;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.6rem;
    border-radius: 8px;
    cursor: pointer;
}

.btn-cancel {
    flex: 1;
    background: var(--bg2);
    border: 1px solid var(--border);
    padding: 0.6rem;
    border-radius: 8px;
    cursor: pointer;
}

@media (max-width: 800px) {
    .account-container {
        flex-direction: column;
        padding: 1rem;
    }
    .account-sidebar {
        width: 100%;
    }
    .addresses-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

<script>
function showAddressForm() {
    document.getElementById('addressModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addressModal').style.display = 'none';
    window.location.href = 'addresses.php';
}
</script>

<?php require_once '../includes/footer.php'; ?>