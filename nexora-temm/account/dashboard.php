<?php
// account/dashboard.php - COMPLETE
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get order count
$orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$orderCount->execute([$user['id']]);
$totalOrders = $orderCount->fetchColumn();

$pageTitle = 'My Dashboard';
$activeBrand = '';
$hide_header_search = true;

require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="user-info">
            <div class="user-avatar">👤</div>
            <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="dashboard-menu">
            <a href="dashboard.php" class="active">📊 Dashboard</a>
            <a href="profile.php">✏️ Edit Profile</a>
            <a href="orders.php">📦 My Orders</a>
            <a href="wishlist.php">❤️ Wishlist</a>
            <a href="addresses.php">📍 Addresses</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="dashboard-content">
        <div class="dashboard-title">Welcome, <?= htmlspecialchars($user['first_name']) ?>! 👋</div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?= $totalOrders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❤️</div>
                <div class="stat-value">0</div>
                <div class="stat-label">Wishlist</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📍</div>
                <div class="stat-value">1</div>
                <div class="stat-label">Addresses</div>
            </div>
        </div>
        
        <div style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem; font-size: 1rem;">Recent Activity</h3>
            <?php if ($totalOrders > 0): ?>
                <p><a href="orders.php" style="color: var(--red);">View your orders →</a></p>
            <?php else: ?>
                <p style="color: var(--muted);">No recent orders. <a href="/cc/category.php" style="color: var(--red);">Start shopping →</a></p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <h3 style="margin-bottom: 1rem; font-size: 1rem;">Quick Actions</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="/cc/category.php" class="action-btn action-btn-primary">🛒 Continue Shopping</a>
                <a href="profile.php" class="action-btn action-btn-secondary">✏️ Edit Profile</a>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    display: flex;
    max-width: 1300px;
    margin: 2rem auto;
    padding: 0 2rem;
    gap: 2rem;
}

.dashboard-sidebar {
    width: 280px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    height: fit-content;
}

.dashboard-sidebar .user-info {
    text-align: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 1rem;
}

.dashboard-sidebar .user-avatar {
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

.dashboard-sidebar .user-name {
    font-weight: 600;
    font-size: 1rem;
}

.dashboard-sidebar .user-email {
    font-size: 0.7rem;
    color: var(--muted);
    word-break: break-all;
}

.dashboard-menu {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.dashboard-menu a {
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

.dashboard-menu a:hover,
.dashboard-menu a.active {
    background: var(--bg2);
    color: var(--red);
}

.dashboard-content {
    flex: 1;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
}

.dashboard-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg2);
    padding: 1.2rem;
    border-radius: 12px;
    text-align: center;
}

.stat-card .stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.stat-card .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}

.stat-card .stat-label {
    font-size: 0.7rem;
    color: var(--muted);
    text-transform: uppercase;
}

.action-btn {
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
}

.action-btn-primary {
    background: var(--text);
    color: #fff;
}

.action-btn-primary:hover {
    background: var(--red);
}

.action-btn-secondary {
    background: var(--bg2);
    color: var(--text);
    border: 1px solid var(--border);
}

.action-btn-secondary:hover {
    background: var(--bg3);
}

@media (max-width: 800px) {
    .dashboard-container {
        flex-direction: column;
        padding: 1rem;
    }
    .dashboard-sidebar {
        width: 100%;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>