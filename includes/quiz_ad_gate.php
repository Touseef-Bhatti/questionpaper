<?php
if (defined('ALH_QUIZ_AD_GATE_RENDERED')) {
    return;
}
define('ALH_QUIZ_AD_GATE_RENDERED', true);
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap');

    .quiz-ad-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(43, 34, 0, 0.45);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        font-family: 'Fredoka', system-ui, -apple-system, sans-serif;
    }

    .quiz-ad-modal.is-open {
        display: flex;
        animation: adGateFadeIn 0.25s ease-out;
    }

    @keyframes adGateFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes adGatePopIn {
        0% { transform: scale(0.8) rotate(-2deg); opacity: 0; }
        70% { transform: scale(1.05) rotate(1deg); opacity: 1; }
        100% { transform: scale(1) rotate(0deg); opacity: 1; }
    }

    .quiz-ad-card {
        position: relative;
        width: 100%;
        max-width: 400px;
        border-radius: 28px;
        background: linear-gradient(185deg, #fffdf2 0%, #fff6d1 100%);
        padding: 32px 20px 24px;
        text-align: center;
        border: 4px solid #1e1e1e;
        box-shadow: 0px 10px 0px #1e1e1e;
        animation: adGatePopIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Decorative badge to look like a friendly educational app offer */
    .quiz-ad-card::before {
        content: "💡 SPONSOR OFFER";
        display: inline-block;
        position: absolute;
        top: -16px;
        left: 50%;
        transform: translateX(-50%);
        background: #ffd000;
        color: #1e1e1e;
        border: 3px solid #1e1e1e;
        padding: 4px 16px;
        font-size: 0.85rem;
        font-weight: 700;
        border-radius: 99px;
        box-shadow: 0px 4px 0px #1e1e1e;
        letter-spacing: 0.03em;
    }

    .quiz-ad-mascot {
        margin: 12px auto 8px;
        width: 120px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .quiz-ad-mascot svg {
        width: 100%;
        height: 100%;
    }

    .quiz-ad-msg {
        display: block;
        margin: 12px 0 16px;
        color: #40340b;
        font-size: 1.15rem;
        font-weight: 500;
        line-height: 1.4;
    }

    .quiz-ad-msg strong {
        color: #d97706;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }
    
    .quiz-ad-msg strong::after {
        content: "";
        position: absolute;
        bottom: 2px;
        left: 0;
        right: 0;
        height: 8px;
        background: rgba(254, 240, 138, 0.7);
        z-index: -1;
        border-radius: 2px;
    }

    /* Trust tags block */
    .quiz-ad-trust-tags {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        margin: 0 auto 20px;
        max-width: 320px;
    }

    .quiz-ad-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        color: #6b5c21;
        background: rgba(254, 240, 138, 0.4);
        padding: 4px 10px;
        border-radius: 99px;
        border: 1px solid rgba(30, 30, 30, 0.1);
        font-weight: 600;
    }

    .quiz-ad-tag svg {
        width: 12px;
        height: 12px;
        color: #d97706;
        stroke-width: 2.5px;
    }

    /* Watch Ad button */
    .quiz-ad-watch-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        max-width: 290px;
        height: 52px;
        border: 3px solid #1e1e1e;
        border-radius: 20px;
        background: linear-gradient(135deg, #ffd000, #ff9500);
        color: #1e1e1e;
        font-family: inherit;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0px 5px 0px #1e1e1e;
        transition: transform 0.1s ease, box-shadow 0.1s ease;
        -webkit-tap-highlight-color: transparent;
        position: relative;
        overflow: hidden;
    }

    /* Shiny reflection sweep animation to attract positive clicks */
    .quiz-ad-watch-btn::after {
        content: "";
        position: absolute;
        top: 0;
        left: -50%;
        width: 20%;
        height: 100%;
        background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%);
        transform: skewX(-25deg);
        animation: shineSweep 3s infinite ease-in-out;
    }

    @keyframes shineSweep {
        0% { left: -50%; }
        30% { left: 150%; }
        100% { left: 150%; }
    }

    .quiz-ad-watch-btn:active {
        transform: translateY(3px);
        box-shadow: 0px 2px 0px #1e1e1e;
    }

    .quiz-ad-watch-btn svg {
        width: 22px;
        height: 22px;
        flex-shrink: 0;
    }

    /* Special style for the green continue button state to look extremely inviting */
    .quiz-ad-watch-btn.btn-continue-state {
        background: linear-gradient(135deg, #4ade80, #22c55e);
        color: #1e1e1e;
    }

    @media (min-width: 481px) {
        .quiz-ad-watch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0px 7px 0px #1e1e1e;
        }
        .quiz-ad-watch-btn:hover:active {
            transform: translateY(3px);
            box-shadow: 0px 2px 0px #1e1e1e;
        }
    }
</style>

<div class="quiz-ad-modal" id="quizAdModal" role="dialog" aria-modal="true" aria-labelledby="quizAdMsg">
    <div class="quiz-ad-card">
        <!-- Cute Smiling TV Mascot -->
        <div class="quiz-ad-mascot">
            <svg viewBox="0 0 100 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Antennas -->
                <path d="M40 20L30 10" stroke="#1e1e1e" stroke-width="3" stroke-linecap="round"/>
                <path d="M60 20L70 10" stroke="#1e1e1e" stroke-width="3" stroke-linecap="round"/>
                <circle cx="30" cy="10" r="3" fill="#ff9500" stroke="#1e1e1e" stroke-width="2"/>
                <circle cx="70" cy="10" r="3" fill="#ff9500" stroke="#1e1e1e" stroke-width="2"/>
                
                <!-- TV Body -->
                <rect x="15" y="20" width="70" height="52" rx="12" fill="#ffd000" stroke="#1e1e1e" stroke-width="4"/>
                
                <!-- Inner Screen -->
                <rect x="23" y="28" width="42" height="36" rx="8" fill="#fff" stroke="#1e1e1e" stroke-width="3"/>
                
                <!-- Cartoon Eyes on Screen -->
                <circle cx="38" cy="42" r="3.5" fill="#1e1e1e"/>
                <circle cx="50" cy="42" r="3.5" fill="#1e1e1e"/>
                <path d="M41 48C41 50 47 50 47 48" stroke="#1e1e1e" stroke-width="2" stroke-linecap="round"/>
                
                <!-- Screen Play Symbol Sparkle -->
                <polygon points="56,38 60,46 52,46" fill="#ff9500" stroke="#1e1e1e" stroke-width="2" stroke-linejoin="round"/>
                
                <!-- Knobs and Speaker -->
                <circle cx="75" cy="34" r="4" fill="#ff9500" stroke="#1e1e1e" stroke-width="2"/>
                <circle cx="75" cy="45" r="4" fill="#ffd000" stroke="#1e1e1e" stroke-width="2"/>
                <line x1="72" y1="56" x2="78" y2="56" stroke="#1e1e1e" stroke-width="2" stroke-linecap="round"/>
                <line x1="72" y1="60" x2="78" y2="60" stroke="#1e1e1e" stroke-width="2" stroke-linecap="round"/>
                <line x1="72" y1="64" x2="78" y2="64" stroke="#1e1e1e" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>

        <p class="quiz-ad-msg" id="quizAdMsg">
            Unlock <strong>1 Hour</strong> of free quiz practice!
        </p>

        <!-- Trust Tags block -->
        <div class="quiz-ad-trust-tags">
            <span class="quiz-ad-tag">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                100% Safe & Free
            </span>
            <span class="quiz-ad-tag">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                Instant Access
            </span>
            <span class="quiz-ad-tag">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                Supports Free Education
            </span>
        </div>

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

    const btnDefault = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Watch Ad`;
    const btnContinue = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg> Continue`;

    let pendingContinue = null;
    let currentStorageKey = 'alh_quiz_ad_seen_until';
    let adOpened = false;
    let adOpenedTime = 0;

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
        adOpenedTime = 0;
        watchButton.innerHTML = btnDefault;
        watchButton.classList.remove('btn-continue-state');
        watchButton.disabled = false;
    }

    function getPageStorageKey(baseKey) {
        const pageId = window.location.pathname + window.location.search;
        const cleanPageId = pageId.replace(/[^a-zA-Z0-9_-]/g, '_').replace(/__+/g, '_').replace(/^_|_$/g, '');
        return `${baseKey}_${cleanPageId}`;
    }

    function triggerContinue() {
        const onContinue = pendingContinue;
        pendingContinue = null;
        modal.classList.remove('is-open');
        resetModal();

        if (typeof onContinue === 'function') {
            onContinue();
        }
    }

    function handleAutoContinue() {
        // Auto-continue when returning to the tab, with a small safety cooldown (1.5s) to avoid instant triggers
        if (adOpened && pendingContinue && (Date.now() - adOpenedTime > 1500)) {
            triggerContinue();
        }
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
            adOpenedTime = Date.now();
            openAdPage();
            watchButton.innerHTML = btnContinue;
            watchButton.classList.add('btn-continue-state');
            return;
        }

        triggerContinue();
    });

    // Auto continue event listeners when returning to tab
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            handleAutoContinue();
        }
    });

    window.addEventListener('focus', () => {
        handleAutoContinue();
    });
})();
</script>
