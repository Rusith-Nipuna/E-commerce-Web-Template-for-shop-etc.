<?php
// account/login.php - COMPLETE WITH FIXED REDIRECT
require_once '../includes/config.php';
require_once '../includes/header.php';

if (isLoggedIn()) {
    $redirect = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? 'dashboard.php';
    unset($_SESSION['redirect_after_login']);
    
    // Fix redirect paths
    if ($redirect == 'cart.php') {
        $redirect = '/cc/cart.php';
    } elseif ($redirect == 'checkout.php') {
        $redirect = '/cc/checkout.php';
    } elseif ($redirect == 'wishlist.php') {
        $redirect = '/cc/account/wishlist.php';
    } elseif (strpos($redirect, 'http') === false && strpos($redirect, '/') !== 0) {
        $redirect = '/cc/account/' . $redirect;
    }
    
    header("Location: $redirect");
    exit;
}

$error = '';
$hide_header_search = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Get redirect URL
            $redirect = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            
            // Fix redirect paths
            if ($redirect == 'cart.php') {
                $redirect = '/cc/cart.php';
            } elseif ($redirect == 'checkout.php') {
                $redirect = '/cc/checkout.php';
            } elseif ($redirect == 'wishlist.php') {
                $redirect = '/cc/account/wishlist.php';
            } elseif (strpos($redirect, 'http') === false && strpos($redirect, '/') !== 0) {
                $redirect = '/cc/account/' . $redirect;
            }
            
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$pageTitle = 'Login';
$activeBrand = '';
?>

<!-- LOGIN PAGE CONTENT ONLY - Header already included -->
<style>
/* Login Page Additional Styles */
.auth-wrapper {
    max-width: 500px;
    margin: 3rem auto;
    padding: 0 1.5rem;
    flex: 1;
}

.auth-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}

.auth-card h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.8rem;
    margin-top: 0.5rem;
}

.form-field {
    margin-bottom: 1rem;
}

.form-field label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--muted);
    display: block;
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-field input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    background: #fff;
    transition: all 0.2s;
}

.form-field input:focus {
    outline: none;
    border-color: var(--red);
    box-shadow: 0 0 0 2px rgba(215,44,44,0.1);
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    padding: 0.9rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    border-left: 3px solid #dc2626;
}

.btn-login {
    width: 100%;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.85rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 0.5rem;
}

.btn-login:hover {
    background: var(--red2);
}

.divider {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.divider span {
    color: var(--muted);
    font-size: 0.85rem;
}

.divider a {
    color: var(--red);
    text-decoration: none;
    font-weight: 500;
}

.back-link {
    text-align: center;
    margin-top: 1rem;
}

.back-link a {
    color: var(--muted);
    text-decoration: none;
    font-size: 0.8rem;
}

@media (max-width: 600px) {
    .auth-wrapper {
        margin: 2rem auto;
        padding: 0 1rem;
    }
    .auth-card {
        padding: 1.5rem;
    }
    .auth-card h1 {
        font-size: 1.5rem;
    }
}
</style>

<!-- LOGIN FORM -->
<div class="auth-wrapper">
    <div class="auth-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 3rem;">🔐</div>
            <h1>Welcome Back</h1>
            <p style="color: var(--muted); font-size: 0.85rem; margin-top: 0.5rem;">Login to your RedZone account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-field">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="customer@redzone.lk" required>
            </div>
            
            <div class="form-field">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn-login">
                Login →
            </button>
        </form>
        
        <div class="divider">
            <span>Don't have an account?</span>
            <a href="register.php"> Create one now</a>
        </div>

        <div class="divider">
            <span>Forgot your password?</span>
            <a href="forgot_password.php"> Reset password</a>
        </div>
        
        <div class="back-link">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>