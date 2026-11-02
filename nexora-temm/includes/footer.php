<?php
// includes/footer.php
?>


<style>
.footer-logo img {
    height: 45px;
    width: auto;
    object-fit: contain;
}
/* Fallback if image fails to load */
.footer-logo img.error {
    display: none;
}
</style>

<script>
// Fallback if image fails to load
document.querySelector('.footer-logo img')?.addEventListener('error', function() {
    this.style.display = 'none';
    this.insertAdjacentHTML('afterend', '<div class="logo-mark">RZ</div><div class="logo-text">RedZone <span>Phone Gallery</span></div>');
});
</script>
<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="footer-logo">
                <!-- Favicon image as logo -->
                <img src="/cc/includes/imglogo/n.png" alt="RedZone Logo" style="height: 45px; width: auto; margin-bottom: 0.5rem;">
                <div class="logo-text">NEXORA <span>Phone Gallery</span></div>
            </div>
            <p>Sri Lanka's trusted mobile phone destination in colombo. Latest smartphones, genuine accessories & expert repair services.</p>
        </div>
        <div>
            <h5>Brands</h5>
            <div class="footer-links">
                <?php
                $db = getDB();
                $brands = $db->query("SELECT * FROM brands WHERE active=1 ORDER BY sort_order")->fetchAll();
                foreach($brands as $brand):
                ?>
                <a href="/cc/category.php?brand=<?= e($brand['slug']) ?>"><?= e($brand['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h5>Support</h5>
            <div class="footer-links">
                <a href="/cc/repairs.php">Phone Repairs</a>
               <!-- <a href="/cc/installments.php">Installment Plans</a> //if you want active this pages (remove the comment commands)
                <a href="/cc/warranty.php">Warranty Info</a>
                <a href="/cc/trade-in.php">Trade-In Offer</a>-->
            </div>
        </div>
        <div>
            <h5>Contact</h5>
            <div class="footer-contact">
                <div class="footer-contact-item">
                    <span>📍</span>
                    <p>Branch 01: colombo</p>
                </div>
                <div class="footer-contact-item">
                    <span>📍</span>
                    <p>Branch 02: rathnapura</p>
                </div>
                <div class="footer-contact-item">
                    <span>📞</span>
                    <p><?= SITE_PHONE ?></p>
                </div>
                <div class="footer-contact-item">
                    <span>🕐</span>
                    <p>Open from 09:30 AM To 7.30 PM daily</p>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© <?= date('Y') ?> NEXORA Phone Gallery · colombo, Sri Lanka · All rights reserved</p>
        <p>Powered by NEXORA Team(rusith).</p>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>