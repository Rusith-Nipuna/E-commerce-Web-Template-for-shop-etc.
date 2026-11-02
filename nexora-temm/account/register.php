<?php
// account/register.php - COMPLETE
require_once '../includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $subscribe = isset($_POST['subscribe']) ? 1 : 0;
    
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone number must be 10 digits';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (empty($address_line1)) $errors[] = 'Address line 1 is required';
    if (empty($city)) $errors[] = 'City is required';
    if (empty($district)) $errors[] = 'District is required';
    
    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, gender, subscribe_newsletter) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $gender, $subscribe]);
            
            $user_id = $db->lastInsertId();
            
            $stmt = $db->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, district, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $address_line1, $address_line2, $city, $district, $postal_code]);
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            header('Location: dashboard.php');
            $emailService = new EmailService();
$emailService->sendWelcomeEmail($user_data);
            exit;
        } else {
            $errors[] = 'Email already registered';
        }
    }
    
}


$pageTitle = 'Register';
$activeBrand = '';
$hide_header_search = true;

require_once '../includes/header.php';
?>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <div class="register-icon">📝</div>
            <h1>Create Account</h1>
            <p>Join RedZone for exclusive offers</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $e): ?>❌ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Personal Information -->
            <div class="form-section">
                <h3>👤 Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="0771234567" required>
                    <small>10 digits (e.g., 0771234567)</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            
            <!-- Delivery Address -->
            <div class="form-section">
                <h3>📍 Delivery Address</h3>
                
                <div class="form-group">
                    <label>Address Line 1 *</label>
                    <input type="text" name="address_line1" value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>" placeholder="House number, street name" required>
                </div>
                
                <div class="form-group">
                    <label>Address Line 2 (Optional)</label>
                    <input type="text" name="address_line2" value="<?= htmlspecialchars($_POST['address_line2'] ?? '') ?>" placeholder="Apartment, building">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="e.g., Colombo" required>
                    </div>
                    <div class="form-group">
                        <label>District *</label>
                        <select name="district" required>
                            <option value="">Select District</option>
                            <option value="Colombo">Colombo</option>
                            <option value="Gampaha">Gampaha</option>
                            <option value="Kalutara">Kalutara</option>
                            <option value="Kandy">Kandy</option>
                            <option value="Galle">Galle</option>
                            <option value="Matara">Matara</option>
                            <option value="Kurunegala">Kurunegala</option>
                            <option value="Anuradhapura">Anuradhapura</option>
                            <option value="Ratnapura">Ratnapura</option>
                            <option value="Jaffna">Jaffna</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>" placeholder="e.g., 00500">
                </div>
            </div>
            
            <!-- Newsletter -->
            <div class="form-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="subscribe" <?= isset($_POST['subscribe']) ? 'checked' : '' ?>>
                    <span>Subscribe to our email list for offers</span>
                </label>
            </div>
            
            <button type="submit" class="btn-register">Create Account →</button>
        </form>
        
        <div class="register-footer">
            <span>Already have an account?</span>
            <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<style>
.register-container {
    max-width: 700px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.register-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 2rem;
}

.register-header {
    text-align: center;
    margin-bottom: 2rem;
}

.register-icon {
    font-size: 3rem;
}

.register-header h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.8rem;
    margin-top: 0.5rem;
    color: var(--text);
}

.register-header p {
    color: var(--muted);
    font-size: 0.85rem;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    padding: 0.8rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.form-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.form-section h3 {
    font-size: 1rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
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
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--red);
}

.form-group small {
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

.btn-register {
    width: 100%;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 0.8rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-register:hover {
    background: var(--red2);
}

.register-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.register-footer span {
    color: var(--muted);
    font-size: 0.85rem;
}

.register-footer a {
    color: var(--red);
    text-decoration: none;
    font-weight: 500;
}

@media (max-width: 600px) {
    .register-container {
        margin: 1rem auto;
    }
    .register-card {
        padding: 1.5rem;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>