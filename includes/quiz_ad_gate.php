<?php
if (defined('ALH_QUIZ_AD_GATE_RENDERED')) {
    return;
}
define('ALH_QUIZ_AD_GATE_RENDERED', true);
?>
<style>
    .quiz-ad-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }

    .quiz-ad-modal.is-open {
        display: flex;
        animation: adGateFadeIn 0.25s ease-out;
    }

    @keyframes adGateFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes adGateSlideUp {
        from { transform: scale(0.92); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .quiz-ad-card {
        position: relative;
        width: 100%;
        max-width: 420px;
        border-radius: 20px;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        padding: 20px 20px 24px;
        text-align: center;
        font-family: Inter, system-ui, -apple-system, sans-serif;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
        animation: adGateSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* Pill handle */
    .quiz-ad-card::before {
        content: "";
        display: block;
        width: 36px;
        height: 4px;
        border-radius: 4px;
        background: rgba(255, 255, 255, 0.18);
        margin: 0 auto 16px;
    }

    .quiz-ad-msg {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin: 0 0 16px;
        color: #cbd5e1;
        font-size: 0.85rem;
        font-weight: 500;
        line-height: 1.4;
    }

    .quiz-ad-msg svg {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        color: #38bdf8;
    }

    .quiz-ad-msg strong {
        color: #f1f5f9;
        font-weight: 700;
    }

    /* Watch Ad button */
    .quiz-ad-watch-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        max-width: 280px;
        height: 46px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #06b6d4, #3b82f6);
        color: #fff;
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(14, 165, 233, 0.35);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .quiz-ad-watch-btn:active {
        transform: scale(0.97);
        box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);
    }

    .quiz-ad-watch-btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    @media (min-width: 481px) {

        .quiz-ad-watch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.4);
        }
    }
</style>

<div class="quiz-ad-modal" id="quizAdModal" role="dialog" aria-modal="true" aria-labelledby="quizAdMsg">
    <div class="quiz-ad-card">
        <p class="quiz-ad-msg" id="quizAdMsg">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Watch <strong>1 ad</strong> to continue free for <strong>1 hour</strong>
        </p>
        <button type="button" class="quiz-ad-watch-btn" id="watchQuizAdBtn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            Watch Ad
        </button>
    </div>
</div>

<script>
(function() {
    if (window.ALHQuizAdGate) return;

    const modal = document.getElementById('quizAdModal');
    const watchButton = document.getElementById('watchQuizAdBtn');
    const ttlMs = 60 * 60 * 1000;
    const adUrl = 'https://omg10.com/4/10866850';

    const btnDefault = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M8 5v14l11-7z"/></svg> Watch Ad`;
    const btnContinue = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><polyline points="9 18 15 12 9 6"/></svg> Continue`;

    let pendingContinue = null;
    let currentStorageKey = 'alh_quiz_ad_seen_until';
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
        watchButton.innerHTML = btnDefault;
        watchButton.disabled = false;
    }

    function getPageStorageKey(baseKey) {
        const pageId = window.location.pathname + window.location.search;
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
            watchButton.innerHTML = btnContinue;
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
})();
</script>
