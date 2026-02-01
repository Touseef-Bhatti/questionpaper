<?php
// cookie_consent.php
if (!isset($_COOKIE['cookie_consent_accepted'])) {
?>
<style>
    .cookie-consent-banner {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: rgba(30, 41, 59, 0.95);
        color: #fff;
        padding: 1rem;
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(5px);
        transform: translateY(100%);
        transition: transform 0.3s ease-out;
    }
    .cookie-consent-banner.show {
        transform: translateY(0);
    }
    .cookie-content {
        max-width: 1200px;
        display: flex;
        align-items: center;
        gap: 2rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .cookie-text {
        font-size: 0.9rem;
        line-height: 1.5;
        margin: 0;
    }
    .cookie-btn {
        background-color: #6366f1;
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.2s;
        white-space: nowrap;
    }
    .cookie-btn:hover {
        background-color: #4f46e5;
    }
    @media (max-width: 768px) {
        .cookie-content {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
    }
</style>

<div id="cookieConsentBanner" class="cookie-consent-banner">
    <div class="cookie-content">
        <p class="cookie-text">
            We use cookies to enhance your experience, manage your session, and analyze traffic. 
            By continuing to browse, you agree to our use of cookies.
        </p>
        <button id="acceptCookiesBtn" class="cookie-btn">Accept Cookies</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('cookieConsentBanner');
    const btn = document.getElementById('acceptCookiesBtn');
    
    // Show banner after a small delay
    setTimeout(() => {
        banner.classList.add('show');
    }, 1000);

    btn.addEventListener('click', function() {
        // Set cookie for 1 year
        document.cookie = "cookie_consent_accepted=true; path=/; max-age=" + (60*60*24*365) + "; SameSite=Lax";
        
        // Hide banner
        banner.classList.remove('show');
        
        // Remove from DOM after animation
        setTimeout(() => {
            banner.remove();
        }, 300);
    });
});
</script>
<?php
}
?>
