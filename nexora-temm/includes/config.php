<?php
// includes/config.php - FULLY SECURE VERSION
ob_start();

// ========== ENVIRONMENT DETECTION ==========
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// ========== ERROR REPORTING ==========
if ($is_localhost) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-error.log');
}

// ========== SESSION SECURITY SETTINGS ==========
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict for better redirect compatibility
ini_set('session.gc_maxlifetime', 1800); // 30 minutes

// Secure cookie flag (only on HTTPS)
if ($is_https) {
    ini_set('session.cookie_secure', 1);
}

// Session start with proper handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== SESSION TIMEOUT CHECK ==========
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();

// ========== SESSION REGENERATION FUNCTION ==========
function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['LAST_ACTIVITY'] = time();
}

// ========== LOGIN ATTEMPT TRACKING ==========
function checkLoginAttempts($ip) {
    try {
        $db = getDB();
        // Clean old attempts
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute();
        
        // Count recent attempts
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() < 5;
    } catch (Exception $e) {
        return true; // Allow if table doesn't exist
    }
}

function logLoginAttempt($ip) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
        $stmt->execute([$ip]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// ========== SECURITY HEADERS ==========
function setSecurityHeaders() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CSP for production only (can be strict on localhost)
    if (!isset($_SERVER['SERVER_NAME']) || ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1')) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
    }
}

// Call headers (but not if output already started)
if (!headers_sent()) {
    setSecurityHeaders();
}

// ========== DATABASE CONFIGURATION ==========
define('DB_HOST', 'sql112.infinityfree.com');
define('DB_NAME', 'if0_41792070_redzone_db');
define('DB_USER', 'if0_41792070');
define('DB_PASS', 'yC25Uy7mc5NLb');

// ========== SITE CONFIGURATION ==========
define('SITE_NAME', 'Nexora Phone Gallery');
define('SITE_PHONE', '94225444585');
define('SITE_WHATSAPP', '94225444585');
define('SITE_EMAIL', 'info@nexora.lk');
define('SITE_URL', $is_https ? 'https://' . $_SERVER['HTTP_HOST'] . '/cc' : 'http://' . $_SERVER['HTTP_HOST'] . '/cc');

// ========== DATABASE CONNECTION ==========
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            // Don't show database errors in production
            global $is_localhost;
            if ($is_localhost) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection error. Please try again later.");
            }
        }
    }
    return $pdo;
}

// ========== HELPER FUNCTIONS ==========
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatPrice($price) {
    if ($price === null || $price === '') {
        return 'LKR 0.00';
    }
    return 'LKR ' . number_format((float)$price, 2);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

function getCartCount() {
    if (isLoggedIn()) {
        $db = getDB();
        $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    return 0;
}

function logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
?>