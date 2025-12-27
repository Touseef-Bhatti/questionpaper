(() => {
  const isIOS = () => /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isStandalone = () => (
    window.matchMedia && window.matchMedia('(display-mode: standalone)').matches
  ) || (navigator.standalone === true);
  const isMobile = () => (/Mobi|Android/i.test(navigator.userAgent));

  let deferredPrompt = null;

  function createPrompt() {
    if (document.getElementById('a2hs-prompt')) return;
    const wrapper = document.createElement('div');
    wrapper.id = 'a2hs-prompt';
    wrapper.className = 'a2hs-prompt animate-hidden';
    wrapper.innerHTML = `
      <div class="a2hs-card fade-in-up">
        <div class="a2hs-icon">📱</div>
        <h3>Add Ahmad Learning Hub to your Home Screen</h3>
        <p class="a2hs-text"></p>
        <div class="a2hs-actions">
          <button class="a2hs-primary">Add</button>
          <button class="a2hs-secondary">Not now</button>
        </div>
      </div>`;
    document.body.appendChild(wrapper);

    const textEl = wrapper.querySelector('.a2hs-text');
    const primaryBtn = wrapper.querySelector('.a2hs-primary');
    const secondaryBtn = wrapper.querySelector('.a2hs-secondary');

    if (isIOS()) {
      textEl.textContent = 'Tap the Share button, then choose "Add to Home Screen".';
      primaryBtn.style.display = 'none';
    } else {
      textEl.textContent = 'Quick access like an app. Tap Add to install.';
      primaryBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return hidePrompt();
        deferredPrompt.prompt();
        try { await deferredPrompt.userChoice; } catch (_) {}
        deferredPrompt = null;
        hidePrompt();
        localStorage.setItem('a2hsDismissed', '1');
      });
    }

    secondaryBtn.addEventListener('click', () => {
      hidePrompt();
      localStorage.setItem('a2hsDismissed', '1');
    });
  }

  function showPrompt() {
    const el = document.getElementById('a2hs-prompt');
    if (el) el.classList.add('a2hs-open');
  }

  function hidePrompt() {
    const el = document.getElementById('a2hs-prompt');
    if (el) el.classList.remove('a2hs-open');
    setTimeout(() => el && el.remove(), 250);
  }

  // Service worker registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/pwa/sw.js').catch(() => {});
    });
  }

  // Capture the install prompt for Android/Chrome
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (localStorage.getItem('a2hsDismissed') === '1') return;
    if (!isMobile() || isStandalone()) return;
    createPrompt();
    showPrompt();
  });

  // Fallback for iOS (no beforeinstallprompt)
  document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('a2hsDismissed') === '1') return;
    if (!isMobile() || isStandalone()) return;
    if (isIOS()) {
      createPrompt();
      showPrompt();
    }
  });
})();
