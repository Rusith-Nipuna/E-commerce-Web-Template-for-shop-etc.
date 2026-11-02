<?php
// find-store.php
require_once 'includes/config.php';
require_once 'includes/header.php';

$pageTitle = 'Find a Store';
$activeBrand = '';
?>

<style>
/* ── Mobile-first responsive overrides ── */

/* Branch grid: 2-col on desktop, 1-col on mobile */
.branch-grid {
    max-width: 1100px;
    margin: 0 auto;
    padding: 2.5rem 2.5rem 5rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

/* Info strip: 4-col on desktop */
.info-strip-inner {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

/* Page hero inner */
.hero-inner {
    max-width: 1100px;
    margin: 0 auto;
}

@media (max-width: 900px) {
    /* Info strip: 2-col on tablet */
    .info-strip-inner {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    /* Hero */
    .page-hero {
        padding: 2rem 1.2rem 1.8rem !important;
    }
    .page-hero h1 {
        font-size: 2rem !important;
    }

    /* Branch grid: single column */
    .branch-grid {
        grid-template-columns: 1fr;
        padding: 1.5rem 1.2rem 3rem;
        gap: 1.2rem;
    }

    /* Branch card actions: stack on very small screens */
    .branch-actions {
        flex-direction: column !important;
    }

    /* Info strip */
    .info-strip {
        padding: 1.5rem 1.2rem !important;
    }
    .info-strip-inner {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
}

@media (max-width: 400px) {
    /* Info strip: single col on tiny screens */
    .info-strip-inner {
        grid-template-columns: 1fr;
    }

    .branch-header h3 {
        font-size: 0.95rem;
    }
}
</style>

<!-- PAGE HERO -->
<div class="page-hero" style="background: var(--bg2); border-bottom: 1px solid var(--border); padding: 3rem 2.5rem 2.5rem;">
    <div class="hero-inner">
        <div class="breadcrumb" style="margin-bottom: 1.2rem;">
            <a href="index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">Find a Store</span>
        </div>
        <h1 style="font-family: 'DM Serif Display', serif; font-size: 2.8rem; font-style: italic;">
            Find a <b style="color: var(--red);">Store</b>
        </h1>
        <p style="color: var(--muted); max-width: 520px;">Visit either of our two branches in Horana. Both locations are stocked with the latest phones, accessories, and offer repair services.</p>
    </div>
</div>

<!-- BRANCH CARDS -->
<div class="branch-grid">

    <!-- Branch 01 -->
    <div class="branch-card" style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
        <div class="branch-header" style="background: var(--text); padding: 1.3rem 1.6rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <h3 style="color: #fff; margin: 0;">Red Zone Phone Gallery</h3>
            <span class="branch-num" style="background: var(--red); color: #fff; padding: 0.25rem 0.7rem; border-radius: 5px; font-size: 0.68rem; white-space: nowrap; flex-shrink: 0;">Branch 01</span>
        </div>
        <div class="branch-body" style="padding: 1.6rem;">
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border);">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">📍</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Address</h5><p style="font-size: 0.85rem; color: var(--muted); margin: 0;">33 Suderis Silva Mawata,<br>Horana 12400, Sri Lanka</p></div>
            </div>
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border);">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">📞</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Phone</h5><a href="tel:<?= SITE_PHONE ?>" style="color: var(--red); text-decoration: none;"><?= SITE_PHONE ?></a></div>
            </div>
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0;">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">🕐</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Hours</h5><p style="font-size: 0.85rem; color: var(--muted); margin: 0;">Open from 09:30 AM To 7.30 PM daily</p></div>
            </div>
        </div>
        <div class="branch-actions" style="padding: 0 1.6rem 1.6rem; display: flex; gap: 0.7rem;">
            <a href="tel:<?= SITE_PHONE ?>" class="act-btn primary" style="background: var(--red); color: #fff; padding: 0.65rem; border-radius: 9px; text-align: center; text-decoration: none; flex: 1;">📞 Call Now</a>
            <a href="https://www.google.com/maps/search/Red+Zone+Phone+Gallery+33+Suderis+Silva+Mawata+Horana" target="_blank" class="act-btn ghost" style="background: var(--bg2); color: var(--text); border: 1px solid var(--border); padding: 0.65rem; border-radius: 9px; text-align: center; text-decoration: none; flex: 1;">🗺️ Directions</a>
        </div>
    </div>

    <!-- Branch 02 -->
    <div class="branch-card" style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
        <div class="branch-header" style="background: var(--text); padding: 1.3rem 1.6rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <h3 style="color: #fff; margin: 0;">Red Zone Phone Gallery (PVT) LTD</h3>
            <span class="branch-num" style="background: var(--red); color: #fff; padding: 0.25rem 0.7rem; border-radius: 5px; font-size: 0.68rem; white-space: nowrap; flex-shrink: 0;">Branch 02</span>
        </div>
        <div class="branch-body" style="padding: 1.6rem;">
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border);">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">📍</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Address</h5><p style="font-size: 0.85rem; color: var(--muted); margin: 0;">No. 128 Rathnapura Road,<br>Horana 12400, Sri Lanka</p></div>
            </div>
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border);">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">📞</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Phone</h5><a href="tel:<?= SITE_PHONE ?>" style="color: var(--red); text-decoration: none;"><?= SITE_PHONE ?></a></div>
            </div>
            <div class="branch-row" style="display: flex; gap: 1rem; padding: 1rem 0;">
                <div class="branch-ico" style="width: 38px; height: 38px; min-width: 38px; background: var(--bg2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">🕐</div>
                <div><h5 style="font-size: 0.82rem; margin: 0 0 0.25rem;">Hours</h5><p style="font-size: 0.85rem; color: var(--muted); margin: 0;">Open from 09:30 AM To 7.30 PM daily</p></div>
            </div>
        </div>
        <div class="branch-actions" style="padding: 0 1.6rem 1.6rem; display: flex; gap: 0.7rem;">
            <a href="tel:<?= SITE_PHONE ?>" class="act-btn primary" style="background: var(--red); color: #fff; padding: 0.65rem; border-radius: 9px; text-align: center; text-decoration: none; flex: 1;">📞 Call Now</a>
            <a href="https://www.google.com/maps/search/Red+Zone+Phone+Gallery+128+Rathnapura+Road+Horana" target="_blank" class="act-btn ghost" style="background: var(--bg2); color: var(--text); border: 1px solid var(--border); padding: 0.65rem; border-radius: 9px; text-align: center; text-decoration: none; flex: 1;">🗺️ Directions</a>
        </div>
    </div>

</div>

<!-- INFO STRIP -->
<div class="info-strip" style="background: var(--bg2); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 2rem 2.5rem;">
    <div class="info-strip-inner">
        <div style="display: flex; gap: 0.9rem;">
            <span style="font-size: 1.4rem;">🛒</span>
            <div><h4 style="font-size: 0.88rem; margin: 0 0 0.2rem;">New & Used Phones</h4><p style="font-size: 0.78rem; color: var(--muted); margin: 0;">Full range of smartphones.</p></div>
        </div>
        <div style="display: flex; gap: 0.9rem;">
            <span style="font-size: 1.4rem;">🔧</span>
            <div><h4 style="font-size: 0.88rem; margin: 0 0 0.2rem;">Repair Services</h4><p style="font-size: 0.78rem; color: var(--muted); margin: 0;">Fast screen & battery repairs.</p></div>
        </div>
        <div style="display: flex; gap: 0.9rem;">
            <span style="font-size: 1.4rem;">💳</span>
            <div><h4 style="font-size: 0.88rem; margin: 0 0 0.2rem;">Easy Installments</h4><p style="font-size: 0.78rem; color: var(--muted); margin: 0;">Monthly payment plans.</p></div>
        </div>
        <div style="display: flex; gap: 0.9rem;">
            <span style="font-size: 1.4rem;">✅</span>
            <div><h4 style="font-size: 0.88rem; margin: 0 0 0.2rem;">Genuine Warranty</h4><p style="font-size: 0.78rem; color: var(--muted); margin: 0;">Official manufacturer warranty.</p></div>
        </div>
    </div>
</div>

<script>
// Store search filter
document.getElementById('storeSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.branch-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>