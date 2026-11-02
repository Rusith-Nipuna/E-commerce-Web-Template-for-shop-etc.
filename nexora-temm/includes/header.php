<?php
// includes/header.php - COMPLETE WITH AUTO HIDE ON ACCOUNT PAGES + MOBILE RESPONSIVE
if(!isset($pageTitle)) $pageTitle = SITE_NAME;
if(!isset($activeBrand)) $activeBrand = '';

$current_script = $_SERVER['SCRIPT_NAME'];
$is_account_page = (strpos($current_script, '/account/') !== false);

$hide_search = isset($hide_header_search) ? $hide_header_search : false;
$hide_search = $hide_search || $is_account_page;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cc/assets/css/style.css">
    
    <!-- Your favicon image -->
    <link rel="icon" type="image/png" href="/cc/includes/imglogo/n.png">
    <link rel="shortcut icon" href="/cc/includes/imglogo/n.png">
    <link rel="apple-touch-icon" href="/cc/includes/imglogo/n.png">
    
    <!-- Rest of your styles -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cc/assets/css/style.css">
    <style>
        /* ── Search Suggestions ── */
        .search-container {
            position: relative;
            flex: 1;
            max-width: 560px;
            margin: 0 auto;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-top: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            z-index: 2000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }

        .search-results.show { display: block; }

        .search-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .search-item:last-child { border-bottom: none; }
        .search-item:hover { background: var(--bg2); }
        .search-item-info { flex: 1; }
        .search-item-name  { font-weight: 500; font-size: 0.85rem; }
        .search-item-brand { font-size: 0.7rem; color: var(--muted); }
        .search-item-price { font-weight: 600; color: var(--red); font-size: 0.85rem; }
        .search-loading, .search-no-results { padding: 16px; text-align: center; color: var(--muted); }

        /* ── Account Pages ── */
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
            width: 80px; height: 80px;
            background: var(--bg2); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; margin: 0 auto 0.8rem;
        }
        .account-sidebar .user-name  { font-weight: 600; font-size: 1rem; }
        .account-sidebar .user-email { font-size: 0.7rem; color: var(--muted); word-break: break-all; }
        .account-menu { display: flex; flex-direction: column; gap: 0.3rem; }
        .account-menu a {
            display: flex; align-items: center; gap: 12px;
            padding: 0.8rem 1rem; border-radius: 10px;
            text-decoration: none; color: var(--muted); font-weight: 500; transition: all 0.2s;
        }
        .account-menu a:hover, .account-menu a.active { background: var(--bg2); color: var(--red); }
        .account-content {
            flex: 1; background: #fff;
            border: 1px solid var(--border); border-radius: 16px; padding: 2rem;
        }
        .account-title {
            font-size: 1.3rem; font-weight: 600;
            margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);
        }

        /* ── Auth Pages ── */
        .auth-wrapper { max-width: 500px; margin: 3rem auto; padding: 0 1.5rem; }
        .auth-card { background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 2rem; }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header .auth-icon { font-size: 3rem; }
        .auth-header h1 { font-family: 'DM Serif Display', serif; font-size: 1.8rem; margin-top: 0.5rem; }
        .auth-header p { color: var(--muted); font-size: 0.85rem; }
        .alert-error { background: #fee2e2; color: #dc2626; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-size: 0.7rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 0.3rem; text-transform: uppercase; }
        .form-group input, .form-group select {
            width: 100%; padding: 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--red); }
        .btn-primary { background: var(--red); color: #fff; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer; }
        .btn-primary:hover { background: var(--red2); }
        .auth-footer { text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .auth-footer a { color: var(--red); text-decoration: none; }


        /* ══════════════════════════════════════
           MOBILE HAMBURGER MENU
        ══════════════════════════════════════ */

        /* Hamburger button — hidden on desktop */
        .mob-menu-btn {
            display: none;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .mob-menu-btn:hover { background: var(--bg2); }
        .mob-menu-btn span {
            display: block;
            width: 22px; height: 2px;
            background: var(--text);
            border-radius: 2px;
            transition: transform 0.3s, opacity 0.3s;
            transform-origin: center;
        }
        /* Animated X when open */
        .mob-menu-btn.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .mob-menu-btn.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
        .mob-menu-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* Mobile drawer overlay */
        .mob-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 1200;
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .mob-overlay.open { display: block; opacity: 1; }

        /* Mobile drawer */
        .mob-drawer {
            position: fixed;
            top: 0; right: 0; bottom: 0;
            width: min(300px, 85vw);
            background: #fff;
            z-index: 1300;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.32s cubic-bezier(0.4,0,0.2,1);
            box-shadow: -8px 0 32px rgba(0,0,0,0.12);
        }
        .mob-drawer.open { transform: translateX(0); }

        .mob-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.4rem;
            border-bottom: 1px solid var(--border);
            background: var(--text);
        }
        .mob-drawer-header .logo-wrap { display: flex; align-items: baseline; gap: 2px; }
        .mob-drawer-header .logo-mark { color: #fff; font-weight: 800; font-size: 1.1rem; letter-spacing: -0.5px; }
        .mob-drawer-header .logo-store { color: var(--red); font-weight: 700; font-size: 1rem; }

        .mob-close-btn {
            background: rgba(255,255,255,0.12);
            border: none; cursor: pointer;
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.1rem; transition: background 0.2s;
        }
        .mob-close-btn:hover { background: rgba(255,255,255,0.22); }

        /* Mobile search inside drawer */
        .mob-search-wrap {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border);
        }
        .mob-search-wrap .nav-search-bar {
            width: 100%;
        }

        /* Drawer nav links */
        .mob-nav-links {
            flex: 1;
            overflow-y: auto;
            padding: 0.6rem 0;
        }
        .mob-nav-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 1.4rem;
            text-decoration: none;
            color: var(--text);
            font-size: 0.92rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.18s;
        }
        .mob-nav-links a:hover, .mob-nav-links a.active {
            background: var(--bg2);
            color: var(--red);
            border-left-color: var(--red);
        }
        .mob-nav-section {
            padding: 0.5rem 1.4rem 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-top: 0.4rem;
        }

        /* Drawer footer — call button */
        .mob-drawer-footer {
            padding: 1rem 1.2rem;
            border-top: 1px solid var(--border);
        }
        .mob-call-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--red);
            color: #fff;
            text-decoration: none;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .mob-call-btn:hover { opacity: 0.88; }

        /* Mobile search bar below header (full width) */
        .mob-search-bar-top {
            display: none;
            padding: 0.65rem 1rem;
            background: #fff;
            border-bottom: 1px solid var(--border);
        }
        .mob-search-bar-top .search-container {
            max-width: 100%;
            margin: 0;
        }


        /* ══════════════════════════════════════
           RESPONSIVE BREAKPOINTS
        ══════════════════════════════════════ */
        @media (max-width: 900px) {
            /* Show hamburger, hide desktop icon links */
            .mob-menu-btn { display: flex; }
            .nav-icons { display: none !important; }

            /* Keep logo + hamburger in nav-top */
            .nav-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 1rem;
                gap: 0.5rem;
            }

            /* Hide desktop search bar in nav-top on mobile */
            .nav-top .search-container { display: none; }

            /* Show full-width search bar below header */
            .mob-search-bar-top { display: block; }

            /* Horizontal scrollable category nav */
            .nav-bottom {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-wrap: nowrap !important;
                scrollbar-width: none;
                padding: 0 0.75rem;
                gap: 0.25rem;
            }
            .nav-bottom::-webkit-scrollbar { display: none; }
            .nav-bottom a {
                white-space: nowrap;
                flex-shrink: 0;
            }
        }

        @media (max-width: 640px) {
            .nav-logo .logo-mark { font-size: 1.05rem; }
            .nav-logo .logo-store { font-size: 0.95rem; }
        }

        /* Account / auth responsive (unchanged) */
        @media (max-width: 800px) {
            .account-container { flex-direction: column; padding: 1rem; }
            .account-sidebar { width: 100%; }
            .auth-wrapper { margin: 2rem auto; }
            .auth-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="nav-top">
        <a class="nav-logo" href="/cc/index.php">
            <div class="logo-mark">REDZONE</div>
            <div class="logo-store">.LK</div>
        </a>

        <!-- DESKTOP SEARCH BAR -->
        <?php if (!$hide_search): ?>
        <div class="search-container">
            <div class="nav-search-bar">
                <span>🔍</span>
                <input type="text" id="searchInput" placeholder="Search products, services, and support" autocomplete="off">
            </div>
            <div id="searchResults" class="search-results"></div>
        </div>
        <?php else: ?>
        <div style="flex:1; max-width:560px; margin:0 auto;"></div>
        <?php endif; ?>

        <!-- DESKTOP NAV ICONS -->
        <div class="nav-icons">
            <a class="nav-icon-btn" href="/cc/find-store.php">📍 Find a Store</a>
            <div class="nav-divider"></div>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']): ?>
                <a class="nav-icon-btn" href="/cc/account/dashboard.php">👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?></a>
                <a class="nav-icon-btn" href="/cc/account/logout.php">🚪 Logout</a>
            <?php else: ?>
                <a class="nav-icon-btn" href="/cc/account/login.php">🔐 Login</a>
                <a class="nav-icon-btn" href="/cc/account/register.php">📝 Register</a>
            <?php endif; ?>
            <a class="nav-icon-btn" href="/cc/cart.php" id="cartLink">🛍️ Cart (<?= getCartCount() ?>)</a>
            <div class="nav-divider"></div>
            <a href="tel:<?= SITE_PHONE ?>" class="nav-call-top">📞 <?= SITE_PHONE ?></a>
        </div>

        <!-- HAMBURGER BUTTON (mobile only) -->
        <button class="mob-menu-btn" id="mobMenuBtn" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- MOBILE FULL-WIDTH SEARCH BAR (below header bar) -->
    <?php if (!$hide_search): ?>
    <div class="mob-search-bar-top">
        <div class="search-container">
            <div class="nav-search-bar">
                <span>🔍</span>
                <input type="text" id="searchInputMob" placeholder="Search phones, accessories…" autocomplete="off">
            </div>
            <div id="searchResultsMob" class="search-results"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CATEGORY TABS -->
    <?php if (!$hide_search): ?>
    <nav class="nav-bottom">
        <?php
        $db = getDB();
        $brands = $db->query("SELECT * FROM brands WHERE active = 1 ORDER BY sort_order LIMIT 7")->fetchAll();
        foreach($brands as $brand):
        ?>
        <a href="/cc/category.php?brand=<?= e($brand['slug']) ?>" class="<?= $activeBrand === $brand['slug'] ? 'active' : '' ?>">
            <?= e($brand['name']) ?>
        </a>
        <?php endforeach; ?>
        <a href="/cc/repairs.php">Repairs</a>
    </nav>
    <?php endif; ?>
</header>


<!-- MOBILE OVERLAY -->
<div class="mob-overlay" id="mobOverlay"></div>

<!-- MOBILE DRAWER -->
<div class="mob-drawer" id="mobDrawer" role="dialog" aria-modal="true" aria-label="Navigation menu">
    <div class="mob-drawer-header">
        <div class="logo-wrap">
            <span class="logo-mark">Nexora</span>
            <span class="logo-store">.LK</span>
        </div>
        <button class="mob-close-btn" id="mobCloseBtn" aria-label="Close menu">✕</button>
    </div>

    <div class="mob-nav-links">

        <!-- Store & Cart -->
        <div class="mob-nav-section">Store</div>
        <a href="/cc/find-store.php">📍 Find a Store</a>
        <a href="/cc/cart.php">🛍️ Cart (<?= getCartCount() ?>)</a>
        <a href="tel:<?= SITE_PHONE ?>">📞 <?= SITE_PHONE ?></a>

        <!-- Account -->
        <div class="mob-nav-section">Account</div>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']): ?>
            <a href="/cc/account/dashboard.php">👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'My Account') ?></a>
            <a href="/cc/account/logout.php">🚪 Logout</a>
        <?php else: ?>
            <a href="/cc/account/login.php">🔐 Login</a>
            <a href="/cc/account/register.php">📝 Register</a>
        <?php endif; ?>

        <!-- Brands -->
        <?php if (!$hide_search): ?>
        <div class="mob-nav-section">Browse by Brand</div>
        <?php
        // $brands already fetched above if !$hide_search; re-use
        if (!empty($brands)):
            foreach($brands as $brand):
        ?>
        <a href="/cc/category.php?brand=<?= e($brand['slug']) ?>" class="<?= $activeBrand === $brand['slug'] ? 'active' : '' ?>">
            <?= e($brand['name']) ?>
        </a>
        <?php
            endforeach;
        endif;
        ?>
        <a href="/cc/repairs.php">🔧 Repairs</a>
        <?php endif; ?>
    </div>

    <div class="mob-drawer-footer">
        <a href="tel:<?= SITE_PHONE ?>" class="mob-call-btn">📞 Call Us Now</a>
    </div>
</div>


<script>
/* ── Hamburger / Drawer ── */
(function() {
    const btn     = document.getElementById('mobMenuBtn');
    const drawer  = document.getElementById('mobDrawer');
    const overlay = document.getElementById('mobOverlay');
    const closeBtn = document.getElementById('mobCloseBtn');

    function openMenu() {
        drawer.classList.add('open');
        overlay.classList.add('open');
        btn.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        drawer.classList.remove('open');
        overlay.classList.remove('open');
        btn.classList.remove('open');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function() {
        drawer.classList.contains('open') ? closeMenu() : openMenu();
    });
    closeBtn.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);

    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenu();
    });
})();


/* ── Live Search (shared logic) ── */
function initSearch(inputEl, resultsEl) {
    if (!inputEl || !resultsEl) return;
    let timeout;

    async function performSearch(query) {
        if (query.length < 2) { resultsEl.classList.remove('show'); resultsEl.innerHTML = ''; return; }
        resultsEl.innerHTML = '<div class="search-loading">🔍 Searching…</div>';
        resultsEl.classList.add('show');
        try {
            const res  = await fetch(`/cc/search.php?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            if (data.length === 0) {
                resultsEl.innerHTML = `<div class="search-no-results">No products found for "${escapeHtml(query)}"</div>`;
            } else {
                resultsEl.innerHTML = data.map(p => `
                    <a href="product.php?slug=${p.slug}" class="search-item">
                        <div class="search-item-info">
                            <div class="search-item-name">${escapeHtml(p.name)}</div>
                            <div class="search-item-brand">${escapeHtml(p.brand_name || '')}</div>
                            <div class="search-item-price">LKR ${parseInt(p.base_price).toLocaleString()}</div>
                        </div>
                    </a>`).join('');
            }
        } catch(err) {
            resultsEl.innerHTML = '<div class="search-no-results">Error loading results</div>';
        }
    }

    inputEl.addEventListener('input', function(e) {
        clearTimeout(timeout);
        timeout = setTimeout(() => performSearch(e.target.value.trim()), 300);
    });
    inputEl.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const q = inputEl.value.trim();
            if (q) window.location.href = `category.php?search=${encodeURIComponent(q)}`;
        }
    });
    document.addEventListener('click', function(e) {
        if (!inputEl.contains(e.target) && !resultsEl.contains(e.target)) {
            resultsEl.classList.remove('show');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

// Init both desktop and mobile search bars
initSearch(document.getElementById('searchInput'),    document.getElementById('searchResults'));
initSearch(document.getElementById('searchInputMob'), document.getElementById('searchResultsMob'));
</script>