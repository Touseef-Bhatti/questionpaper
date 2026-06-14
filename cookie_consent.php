<?php
$cookieConsent = $_COOKIE['alh_cookie_consent'] ?? '';
?>
<style>
    .cookie-consent-banner {
        position: fixed;
        inset: auto 0 0;
        z-index: 99999;
        display: none;
        padding: 1rem;
        color: #fff;
        background: rgba(15, 23, 42, 0.98);
        box-shadow: 0 -6px 24px rgba(0, 0, 0, 0.2);
    }
    .cookie-consent-banner.show { display: block; }
    .cookie-content {
        width: min(1100px, 100%);
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .cookie-text { margin: 0; max-width: 720px; font-size: 0.92rem; line-height: 1.55; }
    .cookie-text a { color: #bfdbfe; }
    .cookie-actions { display: flex; gap: 0.65rem; flex-wrap: wrap; }
    .cookie-btn {
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 7px;
        padding: 0.65rem 1rem;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }
    .cookie-btn.accept { color: #fff; background: #4f46e5; border-color: #4f46e5; }
    .cookie-btn.reject { color: #fff; background: transparent; }
    .cookie-settings-btn {
        border: 0;
        padding: 0;
        color: inherit;
        background: none;
        font: inherit;
        text-decoration: underline;
        cursor: pointer;
    }
    @media (max-width: 760px) {
        .cookie-content { align-items: stretch; flex-direction: column; }
        .cookie-actions { width: 100%; }
        .cookie-btn { flex: 1; }
    }
</style>

<div id="cookieConsentBanner" class="cookie-consent-banner" role="dialog" aria-modal="true" aria-labelledby="cookieConsentTitle">
    <div class="cookie-content">
        <p class="cookie-text">
            <strong id="cookieConsentTitle">Your privacy choices</strong><br>
            Essential cookies keep login, security, and requested site features working. Optional Google Analytics loads only if you accept it. Third-party advertising is currently disabled. You can accept or reject optional cookies and change this choice later. Read our <a href="<?= htmlspecialchars(($assetBase ?? '') . 'privacy-policy') ?>">Privacy Policy</a>.
        </p>
        <div class="cookie-actions">
            <button type="button" id="rejectCookiesBtn" class="cookie-btn reject">Reject optional</button>
            <button type="button" id="acceptCookiesBtn" class="cookie-btn accept">Accept optional</button>
        </div>
    </div>
</div>

<script>
(function () {
    var banner = document.getElementById('cookieConsentBanner');
    var acceptButton = document.getElementById('acceptCookiesBtn');
    var rejectButton = document.getElementById('rejectCookiesBtn');
    var existingConsent = <?= json_encode(in_array($cookieConsent, ['accepted', 'rejected'], true)) ?>;

    function saveConsent(value) {
        document.cookie = 'alh_cookie_consent=' + value + '; path=/; max-age=31536000; SameSite=Lax; Secure';
        banner.classList.remove('show');
        window.dispatchEvent(new CustomEvent('alh-consent-updated', { detail: value }));
    }

    window.ALHCookieConsent = {
        open: function () { banner.classList.add('show'); }
    };

    acceptButton.addEventListener('click', function () { saveConsent('accepted'); });
    rejectButton.addEventListener('click', function () { saveConsent('rejected'); });

    if (!existingConsent) {
        window.setTimeout(function () { banner.classList.add('show'); }, 400);
    }
})();
</script>
