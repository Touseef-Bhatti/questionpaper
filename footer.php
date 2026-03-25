<link rel="stylesheet" href="<?= $assetBase ?>css/footer.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<!-- GLOBAL PRE-FOOTER AD BANNER -->
<?= renderAd('banner', 'Place Pre-Footer Banner Here', '', 'margin-top: 40px; margin-bottom: 0;') ?>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-section footer-brand">
            <a href="<?= $assetBase ?? '' ?>index" class="footer-logo">Ahmad Learning Hub</a>
            <p class="footer-tagline">Your ultimate solution for 9th & 10th class past papers, online tests, solved notes, and guess papers for Punjab Board exam preparation.</p>
        </div>
        <div class="footer-section footer-links-group">
            <h3>Quick Links</h3>
            <ul class="footer-menu">
                <li><a href="<?= $assetBase ?? '' ?>index"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="<?= $assetBase ?? '' ?>select_class"><i class="fas fa-file-alt"></i> Generate Paper</a></li>
                <li><a href="<?= $assetBase ?? '' ?>quiz_setup"><i class="fas fa-question-circle"></i> Online Quiz</a></li>
                <li><a href="<?= $assetBase ?? '' ?>note"><i class="fas fa-book"></i> Notes</a></li>
            </ul>
        </div>
        <div class="footer-section footer-links-group">
            <h3>About Us</h3>
            <ul class="footer-menu">
                <li><a href="<?= $assetBase ?? '' ?>about"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="<?= $assetBase ?? '' ?>contact"><i class="fas fa-envelope"></i> Contact</a></li>
                <li><a href="<?= $assetBase ?? '' ?>privacy-policy"><i class="fas fa-shield-alt"></i> Privacy Policy</a></li>
                <li><a href="<?= $assetBase ?? '' ?>terms-and-conditions"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= $assetBase ?? '' ?>profile"><i class="fas fa-user-circle"></i> Profile</a></li>
                <?php else: ?>
                    <li><a href="<?= $assetBase ?? '' ?>login"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-section footer-contact">
            <h3>Contact Us</h3>
            <p><i class="fas fa-map-marker-alt"></i> Gujranwala</p>
            <p><a href="https://wa.me/923206472460" target="_blank" aria-label="Chat with us on WhatsApp" style="color: aliceblue;"><i class="fab fa-whatsapp" ></i> +92 3206472460</a></p>
            <p> <a href="mailto:touseef12345bhatt@gmail.com" target="_blank" aria-label="Email us" style="color: aliceblue;"><i class="fas fa-envelope"></i> touseef12345bhatt@gmail.com</a></p>
            <div class="footer-social">
                <a href="https://facebook.com/" target="_blank" aria-label="Follow us on Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://twitter.com/" target="_blank" aria-label="Follow us on Twitter"><i class="fab fa-twitter"></i></a>
                <a href="https://linkedin.com/" target="_blank" aria-label="Follow us on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p class="footer-copyright">&copy; <?php echo date('Y'); ?> Ahmad Learning Hub. All rights reserved. | Built with ❤️ for Education</p>
    </div>
</footer>
<?php include __DIR__ . '/cookie_consent.php'; ?>
<?php include __DIR__ . '/user_type_selection.php'; ?>
<?= renderMonetagScripts() ?>
