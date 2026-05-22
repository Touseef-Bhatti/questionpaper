<?php
if (defined('ALH_ADSTRA_ON_CLICK_ADS_RENDERED')) {
    return;
}
define('ALH_ADSTRA_ON_CLICK_ADS_RENDERED', true);
?>
<style>
    .quiz-ad-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background:
            radial-gradient(circle at top, rgba(14, 165, 233, 0.24), transparent 34%),
            rgba(15, 23, 42, 0.78);
        backdrop-filter: blur(10px);
    }

    .quiz-ad-modal.is-open {
        display: flex;
    }

    .quiz-ad-card {
        position: relative;
        overflow: hidden;
        width: min(460px, 100%);
        border-radius: 22px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        padding: 26px;
        box-shadow: 0 28px 90px rgba(15, 23, 42, 0.34);
        text-align: center;
        font-family: Inter, system-ui, sans-serif;
        border: 1px solid rgba(255, 255, 255, 0.7);
    }

    .quiz-ad-card::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 7px;
        background: linear-gradient(90deg, #10b981, #0ea5e9, #6366f1);
    }

    .quiz-ad-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 13px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #047857;
        font-size: 0.82rem;
        font-weight: 800;
        margin-bottom: 14px;
    }

    .quiz-ad-icon {
        width: 64px;
        height: 64px;
        display: grid;
        place-items: center;
        margin: 0 auto 14px;
        border-radius: 18px;
        background: linear-gradient(135deg, #10b981, #0ea5e9);
        color: #fff;
        font-size: 1.9rem;
        box-shadow: 0 14px 32px rgba(14, 165, 233, 0.28);
    }

    .quiz-ad-card h3 {
        margin: 0 0 8px;
        color: #0f172a;
        font-size: clamp(1.35rem, 4vw, 1.7rem);
        line-height: 1.15;
    }

    .quiz-ad-card p {
        margin: 0 auto 20px;
        color: #475569;
        line-height: 1.55;
        max-width: 34ch;
    }

    .quiz-ad-perks {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 0 0 20px;
    }

    .quiz-ad-perk {
        border-radius: 14px;
        background: #f1f5f9;
        color: #334155;
        padding: 11px 10px;
        font-size: 0.86rem;
        font-weight: 700;
    }

    .quiz-ad-actions {
        display: grid;
        gap: 10px;
    }

    .quiz-ad-actions .btn {
        width: 100%;
        justify-content: center;
        min-height: 48px;
        border-radius: 14px;
        font-weight: 800;
    }

    .quiz-ad-actions .primary {
        background: linear-gradient(135deg, #10b981, #0ea5e9);
        border: 0;
        box-shadow: 0 14px 28px rgba(14, 165, 233, 0.28);
        transform: translateY(0);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .quiz-ad-actions .primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 36px rgba(14, 165, 233, 0.34);
    }

    .quiz-ad-note {
        margin-top: 12px;
        color: #64748b;
        font-size: 0.8rem;
    }

    @media (max-width: 480px) {
        .quiz-ad-modal {
            align-items: flex-end;
            padding: 12px;
        }

        .quiz-ad-card {
            border-radius: 20px;
            padding: 24px 18px 18px;
        }

        .quiz-ad-perks {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="quiz-ad-modal" id="quizAdModal" role="dialog" aria-modal="true" aria-labelledby="quizAdModalTitle">
    <div class="quiz-ad-card">
        <div class="quiz-ad-badge">Free access unlock</div>
        <div class="quiz-ad-icon">&#9654;</div>
        <h3 id="quizAdModalTitle">Everything is ready</h3>
        <p>Watch one quick ad in a new tab to unlock this quiz now. Return here and continue for 1 hour without this popup.</p>
        <div class="quiz-ad-perks">
            <div class="quiz-ad-perk">Instant quiz start</div>
            <div class="quiz-ad-perk">1 hour unlocked</div>
        </div>
        <div class="quiz-ad-actions">
            <button type="button" class="btn primary" id="watchQuizAdBtn">Watch Ads & Continue</button>
            <button type="button" class="btn secondary" id="quizAdPremiumBtn">Go Premium</button>
        </div>
        <div class="quiz-ad-note">Premium removes ads from your learning flow.</div>
    </div>
</div>

<script>
(function() {
    if (window.ALHQuizAdGate) return;

    const modal = document.getElementById('quizAdModal');
    const watchButton = document.getElementById('watchQuizAdBtn');
    const premiumButton = document.getElementById('quizAdPremiumBtn');
    const ttlMs = 60 * 60 * 1000;
    const adUrl = 'https://www.effectivecpmnetwork.com/v0uzsjuw?key=1f575b11219ec29bdc53a937a8aaac03';

    let pendingContinue = null;
    let currentStorageKey = 'alh_quiz_ad_seen_until';
    let currentPremiumHref = 'subscription.php';
    let adOpened = false;

    function hasActivePass(storageKey) {
        const storedUntil = Number(localStorage.getItem(storageKey) || 0);
        return storedUntil > Date.now();
    }

    function setPass(storageKey) {
        localStorage.setItem(storageKey, String(Date.now() + ttlMs));
    }

    function openAdPage() {
        window.open(adUrl, '_blank', 'noopener,noreferrer');
    }

    function resetModal() {
        adOpened = false;
        watchButton.textContent = 'Watch Ads & Continue';
        watchButton.disabled = false;
    }

    function getPageStorageKey(baseKey) {
        // Uniquely identify the page based on the pathname and query parameters
        const pageId = window.location.pathname + window.location.search;
        // Sanitize to create a clean and safe localStorage key
        const cleanPageId = pageId.replace(/[^a-zA-Z0-9_-]/g, '_').replace(/__+/g, '_').replace(/^_|_$/g, '');
        return `${baseKey}_${cleanPageId}`;
    }

    window.ALHQuizAdGate = {
        gate(options) {
            const baseStorageKey = options.storageKey || currentStorageKey;
            const storageKey = getPageStorageKey(baseStorageKey);
            const onContinue = options.onContinue;

            if (hasActivePass(storageKey)) {
                onContinue();
                return;
            }

            currentStorageKey = storageKey;
            currentPremiumHref = options.premiumHref || currentPremiumHref;
            pendingContinue = onContinue;
            resetModal();
            modal.classList.add('is-open');
        }
    };

    watchButton.addEventListener('click', () => {
        if (!adOpened) {
            setPass(currentStorageKey);
            adOpened = true;
            openAdPage();
            watchButton.textContent = 'Continue Quiz';
            return;
        }

        const onContinue = pendingContinue;
        pendingContinue = null;
        modal.classList.remove('is-open');
        resetModal();

        if (typeof onContinue === 'function') {
            onContinue();
        }
    });

    premiumButton.addEventListener('click', () => {
        window.location.href = currentPremiumHref;
    });
})();
</script>
