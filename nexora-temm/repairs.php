<?php
// repairs.php - Online Appointment System Coming Soon
require_once 'includes/config.php';
require_once 'includes/header.php';

$pageTitle = 'Phone Repairs - Online Appointment Coming Soon';
$activeBrand = '';

// Handle notification signup
$notify_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_email'])) {
    $email = trim($_POST['notify_email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Save to database (create repair_notifications table)
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO repair_notifications (email, created_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE created_at = NOW()");
        $stmt->execute([$email]);
        $notify_msg = '✅ Thank you! We will notify you when the appointment system launches.';
    } else {
        $notify_msg = '❌ Please enter a valid email address.';
    }
}
?>

<style>
/* Repair Page Styles */
.repair-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 4rem 2rem;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.repair-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(215,44,44,0.2) 0%, transparent 70%);
    border-radius: 50%;
}

.repair-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(215,44,44,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.repair-hero h1 {
    font-size: 2.8rem;
    font-weight: 700;
    margin-bottom: 1rem;
    font-family: 'DM Serif Display', serif;
    position: relative;
    z-index: 1;
}

.repair-hero p {
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

/* Coming Soon Main Card */
.coming-soon-container {
    max-width: 1100px;
    margin: -2rem auto 4rem;
    padding: 0 2rem;
}

.coming-soon-card {
    background: #fff;
    border-radius: 28px;
    padding: 3rem;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.coming-soon-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--red), #ff6b6b, #ff9999, var(--red));
    background-size: 200% 100%;
    animation: gradientMove 3s ease infinite;
}

@keyframes gradientMove {
    0% { background-position: 0% 0%; }
    100% { background-position: 200% 0%; }
}

.clock-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: pulse 2s ease infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.coming-soon-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--red), var(--red2));
    color: white;
    padding: 0.4rem 1.2rem;
    border-radius: 40px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 1.5rem;
}

.coming-soon-card h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.coming-soon-subtitle {
    color: var(--muted);
    font-size: 1rem;
    margin-bottom: 2rem;
}

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin: 2.5rem 0;
}

.feature-card {
    background: var(--bg2);
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.feature-card:hover {
    transform: translateY(-5px);
    border-color: var(--red);
    box-shadow: 0 10px 25px rgba(215,44,44,0.1);
}

.feature-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.feature-card h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.feature-card p {
    font-size: 0.75rem;
    color: var(--muted);
    line-height: 1.5;
}

/* Countdown Timer */
.countdown-container {
    background: var(--bg2);
    border-radius: 20px;
    padding: 1.5rem;
    margin: 2rem 0;
}

.countdown-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 1rem;
}

.countdown {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
}

.countdown-item {
    text-align: center;
}

.countdown-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--red);
    background: #fff;
    padding: 0.5rem 1rem;
    border-radius: 12px;
    min-width: 70px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}

.countdown-label {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 0.3rem;
    display: block;
}

/* Repair Services List */
.services-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 2rem 0;
    text-align: left;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem;
    background: var(--bg2);
    border-radius: 12px;
    transition: all 0.2s;
}

.service-item:hover {
    background: #fff5f5;
    transform: translateX(5px);
}

.service-item .service-icon {
    font-size: 1.8rem;
}

.service-item .service-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.service-item .service-info p {
    font-size: 0.7rem;
    color: var(--muted);
}

/* Price Estimate */
.price-estimate {
    background: linear-gradient(135deg, #fff5f5, #fff);
    border-radius: 16px;
    padding: 1.2rem;
    margin: 1.5rem 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    border: 1px solid rgba(215,44,44,0.2);
}

.price-estimate span:first-child {
    font-weight: 600;
    color: var(--text);
}

.price-estimate span:last-child {
    font-size: 0.85rem;
    color: var(--muted);
}

/* Notification Form */
.notify-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.notify-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.notify-form {
    display: flex;
    gap: 0.5rem;
    max-width: 450px;
    margin: 0 auto;
}

.notify-input {
    flex: 1;
    padding: 0.8rem 1.2rem;
    border: 1px solid var(--border);
    border-radius: 50px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    outline: none;
    transition: all 0.2s;
}

.notify-input:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 2px rgba(215,44,44,0.1);
}

.notify-btn {
    background: var(--red);
    color: white;
    border: none;
    padding: 0.8rem 1.8rem;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.notify-btn:hover {
    background: var(--red2);
    transform: translateY(-1px);
}

.notify-message {
    margin-top: 1rem;
    font-size: 0.8rem;
}

.notify-message.success {
    color: #16a34a;
}

.notify-message.error {
    color: #dc2626;
}

/* Contact Support */
.contact-support {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.contact-support p {
    font-size: 0.85rem;
    color: var(--muted);
}

.contact-support a {
    color: var(--red);
    text-decoration: none;
    font-weight: 600;
}

.contact-support a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 900px) {
    .features-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .services-list {
        grid-template-columns: 1fr;
    }
    .coming-soon-card {
        padding: 2rem;
    }
    .coming-soon-card h2 {
        font-size: 1.5rem;
    }
}

@media (max-width: 600px) {
    .features-grid {
        grid-template-columns: 1fr;
    }
    .countdown {
        gap: 0.8rem;
    }
    .countdown-number {
        font-size: 1.2rem;
        min-width: 50px;
        padding: 0.3rem 0.6rem;
    }
    .notify-form {
        flex-direction: column;
    }
    .repair-hero h1 {
        font-size: 2rem;
    }
}
</style>

<!-- Hero Section -->
<div class="repair-hero">
    <h1>🔧 Phone Repairs</h1>
    <p>Professional repair services with online appointment system</p>
</div>

<div class="coming-soon-container">
    <div class="coming-soon-card">
        <div class="clock-icon">⏰</div>
        <div class="coming-soon-badge">Online Appointment System</div>
        <h2>Coming Soon!</h2>
        <div class="coming-soon-subtitle">Book your repair appointment online - Fast, Easy, Convenient</div>

        <!-- Features Preview -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h4>Online Booking</h4>
                <p>Schedule your repair appointment anytime, anywhere</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⏱️</div>
                <h4>Same Day Service</h4>
                <p>Most repairs completed within hours</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h4>Price Estimate</h4>
                <p>Get instant price estimates online</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h4>Warranty</h4>
                <p>3 months warranty on all repairs</p>
            </div>
        </div>

        <!-- Countdown Timer -->
        <div class="countdown-container">
            <div class="countdown-title">🎯 Launching In</div>
            <div class="countdown" id="countdown">
                <div class="countdown-item">
                    <div class="countdown-number" id="days">00</div>
                    <span class="countdown-label">Days</span>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="hours">00</div>
                    <span class="countdown-label">Hours</span>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="minutes">00</div>
                    <span class="countdown-label">Minutes</span>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="seconds">00</div>
                    <span class="countdown-label">Seconds</span>
                </div>
            </div>
        </div>

        <!-- Repair Services We Offer -->
        <div class="services-list">
            <div class="service-item">
                <div class="service-icon">📱</div>
                <div class="service-info">
                    <h4>Screen Replacement</h4>
                    <p>Cracked or broken screen</p>
                </div>
            </div>
            <div class="service-item">
                <div class="service-icon">🔋</div>
                <div class="service-info">
                    <h4>Battery Replacement</h4>
                    <p>Fast draining or swollen battery</p>
                </div>
            </div>
            <div class="service-item">
                <div class="service-icon">🔌</div>
                <div class="service-info">
                    <h4>Charging Port Repair</h4>
                    <p>Not charging properly</p>
                </div>
            </div>
            <div class="service-item">
                <div class="service-icon">📷</div>
                <div class="service-info">
                    <h4>Camera Repair</h4>
                    <p>Blurry or broken camera</p>
                </div>
            </div>
            <div class="service-item">
                <div class="service-icon">🔊</div>
                <div class="service-info">
                    <h4>Speaker/Mic Repair</h4>
                    <p>No sound or microphone issues</p>
                </div>
            </div>
            <div class="service-item">
                <div class="service-icon">💧</div>
                <div class="service-info">
                    <h4>Water Damage Repair</h4>
                    <p>Liquid damage recovery</p>
                </div>
            </div>
        </div>

        

        <!-- Notify Me Form -->
        <div class="notify-section">
            <div class="notify-title">🔔 Get Notified When We Launch</div>
            <form class="notify-form" method="POST">
                <input type="email" name="notify_email" class="notify-input" placeholder="Enter your email address" required>
                <button type="submit" class="notify-btn">Notify Me</button>
            </form>
            <?php if ($notify_msg): ?>
            <div class="notify-message <?= strpos($notify_msg, '✅') !== false ? 'success' : 'error' ?>">
                <?= $notify_msg ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Contact Support -->
        <div class="contact-support">
            <p>📞 Need immediate assistance? <a href="tel:<?= SITE_PHONE ?>">Call us at <?= SITE_PHONE ?></a></p>
            <p style="font-size: 0.7rem; margin-top: 0.5rem;">📍 Visit our store for phone purchases | Horana branches</p>
        </div>
    </div>
</div>

<script>
// Countdown Timer (Set launch date - change as needed)
const launchDate = new Date('June 1, 2026 00:00:00').getTime();

function updateCountdown() {
    const now = new Date().getTime();
    const distance = launchDate - now;
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    document.getElementById('days').innerHTML = days.toString().padStart(2, '0');
    document.getElementById('hours').innerHTML = hours.toString().padStart(2, '0');
    document.getElementById('minutes').innerHTML = minutes.toString().padStart(2, '0');
    document.getElementById('seconds').innerHTML = seconds.toString().padStart(2, '0');
    
    if (distance < 0) {
        document.getElementById('countdown').innerHTML = '<div style="text-align:center; width:100%;">🎉 Now Available! Visit our store 🎉</div>';
    }
}

setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php
// Create notification table if not exists
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS repair_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>

<?php require_once 'includes/footer.php'; ?>