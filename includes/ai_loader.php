<?php
/**
 * includes/ai_loader.php
 *
 * Single-file reusable AI loader (markup + CSS + JS).
 * Include once on any page, then call:
 *
 * showAILoader(
 *   [
 *     { label: 'Analyzing topics', duration: 2500 },
 *     { label: 'Generating questions', duration: 2500 },
 *     { label: 'Finalizing output', duration: 2500 }
 *   ],
 *   'Please wait while we prepare your content...',
 *   'AI Processing'
 * );
 */
?>
<style>
  .ai-loader-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.28), transparent 42%),
                radial-gradient(circle at 80% 80%, rgba(59, 130, 246, 0.22), transparent 45%),
                rgba(2, 6, 23, 0.88);
    z-index: 99999;
    padding: 16px;
  }
  .ai-loader-card {
    width: 100%;
    max-width: 520px;
    color: #e2e8f0;
    background: rgba(15, 23, 42, 0.82);
    border: 1px solid rgba(148, 163, 184, 0.25);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 18px;
    padding: 22px 20px;
    box-shadow: 0 18px 40px rgba(2, 6, 23, 0.5), 0 0 0 1px rgba(99, 102, 241, 0.1) inset;
    position: relative;
    overflow: hidden;
  }
  .ai-loader-card::before {
    content: '';
    position: absolute;
    inset: -1px;
    pointer-events: none;
    border-radius: 18px;
    border: 1px solid rgba(129, 140, 248, 0.28);
    box-shadow: 0 0 34px rgba(99, 102, 241, 0.28), 0 0 56px rgba(56, 189, 248, 0.2);
  }
  .ai-loader-card::after {
    content: '';
    position: absolute;
    width: 180px;
    height: 180px;
    top: -90px;
    right: -70px;
    border-radius: 50%;
    pointer-events: none;
    background: radial-gradient(circle, rgba(129, 140, 248, 0.28) 0%, rgba(129, 140, 248, 0) 70%);
  }
  .ai-loader-title {
    margin: 0 0 14px;
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: 0.2px;
    color: #f8fafc;
    text-align: center;
    text-shadow: 0 0 14px rgba(99, 102, 241, 0.62), 0 0 24px rgba(56, 189, 248, 0.35);
  }
  .ai-loader-steps {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .ai-loader-step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(15, 23, 42, 0.35);
    color: #cbd5e1;
    font-size: 0.95rem;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
  }
  .ai-loader-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #64748b;
    flex: 0 0 14px;
    box-shadow: 0 0 0 2px rgba(100, 116, 139, 0.2);
  }
  .ai-loader-step.active {
    border-color: rgba(96, 165, 250, 0.75);
    background: rgba(37, 99, 235, 0.26);
    transform: translateX(3px);
    color: #f8fafc;
    box-shadow: 0 0 16px rgba(37, 99, 235, 0.2) inset;
  }
  .ai-loader-step.active .ai-loader-dot {
    border-color: #60a5fa;
    background: #60a5fa;
    box-shadow: 0 0 14px rgba(96, 165, 250, 0.9), 0 0 24px rgba(99, 102, 241, 0.42);
  }
  .ai-loader-step.completed {
    border-color: rgba(52, 211, 153, 0.5);
    background: rgba(16, 185, 129, 0.12);
    color: #bbf7d0;
  }
  .ai-loader-step.completed .ai-loader-dot {
    border-color: #34d399;
    background: #34d399;
    box-shadow: 0 0 10px rgba(52, 211, 153, 0.7);
  }
  .ai-loader-progress-wrap {
    margin-top: 12px;
    height: 7px;
    background: rgba(71, 85, 105, 0.5);
    border-radius: 999px;
    overflow: hidden;
  }
  .ai-loader-progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 45%, #60a5fa 100%);
    box-shadow: 0 0 14px rgba(96, 165, 250, 0.6);
    transition: width 0.2s linear;
  }
  .ai-loader-note {
    margin-top: 12px;
    font-size: 0.83rem;
    color: #94a3b8;
    text-align: center;
  }
  @media (max-width: 768px) {
    .ai-loader-overlay { padding: 12px; }
    .ai-loader-card { max-width: 100%; border-radius: 14px; padding: 16px 14px; }
    .ai-loader-title { font-size: 1rem; margin-bottom: 10px; }
    .ai-loader-step { font-size: 0.9rem; padding: 8px 9px; margin-bottom: 6px; gap: 10px; }
    .ai-loader-dot { width: 12px; height: 12px; flex-basis: 12px; }
    .ai-loader-note { font-size: 0.78rem; }
  }
  @media (prefers-reduced-motion: reduce) {
    .ai-loader-step { transition: none; }
    .ai-loader-progress-bar { transition: none; }
  }
</style>

<div class="ai-loader-overlay" id="aiLoaderModal" aria-modal="true" role="dialog" aria-label="Loading">
  <div class="ai-loader-card">
    <h2 class="ai-loader-title" id="aiLoaderTitle">Processing...</h2>
    <ul class="ai-loader-steps" id="aiLoaderSteps"></ul>
    <div class="ai-loader-progress-wrap">
      <div class="ai-loader-progress-bar" id="aiLoaderProgressBar"></div>
    </div>
    <div class="ai-loader-note" id="aiLoaderNote">Please wait while we prepare your content...</div>
  </div>
</div>

<script>
  (function () {
    if (window.__aiLoaderInitialized) return;
    window.__aiLoaderInitialized = true;

    function getDefaultSteps() {
      return [
        { label: 'Analyzing request', duration: 2500 },
        { label: 'Preparing content', duration: 2500 },
        { label: 'Finalizing output', duration: 2500 }
      ];
    }

    function normalizeSteps(steps) {
      if (!Array.isArray(steps) || steps.length === 0) return getDefaultSteps();
      return steps.map(function (s, i) {
        var label = (s && s.label) ? String(s.label) : ('Step ' + (i + 1));
        var duration = Number(s && s.duration);
        if (!Number.isFinite(duration) || duration < 300) duration = 300;
        return { label: label, duration: duration };
      });
    }

    function renderSteps(container, steps) {
      container.innerHTML = '';
      steps.forEach(function (step, idx) {
        var li = document.createElement('li');
        li.className = 'ai-loader-step';
        li.id = 'ai-loader-step-' + idx;

        var dot = document.createElement('span');
        dot.className = 'ai-loader-dot';

        var text = document.createElement('span');
        text.className = 'ai-loader-label';
        text.textContent = step.label;

        li.appendChild(dot);
        li.appendChild(text);
        container.appendChild(li);
      });
    }

    window.hideAILoader = function () {
      var modal = document.getElementById('aiLoaderModal');
      if (!modal) return;
      modal.style.display = 'none';
      document.body.style.overflow = '';
      if (window.__aiLoaderTimer) {
        clearInterval(window.__aiLoaderTimer);
        window.__aiLoaderTimer = null;
      }
    };

    window.showAILoader = function (stepsInput, noteText, titleText) {
      var modal = document.getElementById('aiLoaderModal');
      var titleEl = document.getElementById('aiLoaderTitle');
      var stepsEl = document.getElementById('aiLoaderSteps');
      var progressBar = document.getElementById('aiLoaderProgressBar');
      var noteEl = document.getElementById('aiLoaderNote');
      if (!modal || !stepsEl) return;

      var steps = normalizeSteps(stepsInput);
      renderSteps(stepsEl, steps);

      if (titleEl) titleEl.textContent = titleText || 'Processing...';
      if (noteEl) noteEl.textContent = noteText || 'Please wait while we prepare your content...';
      if (progressBar) progressBar.style.width = '0%';

      if (window.__aiLoaderTimer) {
        clearInterval(window.__aiLoaderTimer);
        window.__aiLoaderTimer = null;
      }

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';

      var totalDuration = steps.reduce(function (acc, step) {
        return acc + step.duration;
      }, 0);
      var startTime = Date.now();
      var lastActiveIdx = -1;

      window.__aiLoaderTimer = setInterval(function () {
        var elapsed = Date.now() - startTime;
        var progress = Math.min((elapsed / totalDuration) * 100, 99);
        if (progressBar) progressBar.style.width = progress + '%';

        var cumulative = 0;
        var activeIdx = steps.length - 1;
        for (var i = 0; i < steps.length; i++) {
          cumulative += steps[i].duration;
          if (elapsed < cumulative) {
            activeIdx = i;
            break;
          }
        }

        if (activeIdx !== lastActiveIdx) {
          lastActiveIdx = activeIdx;
          steps.forEach(function (_, idx) {
            var stepEl = document.getElementById('ai-loader-step-' + idx);
            if (!stepEl) return;
            if (idx < activeIdx) {
              stepEl.className = 'ai-loader-step completed';
            } else if (idx === activeIdx) {
              stepEl.className = 'ai-loader-step active';
            } else {
              stepEl.className = 'ai-loader-step';
            }
          });
        }

        if (elapsed >= totalDuration) {
          clearInterval(window.__aiLoaderTimer);
          window.__aiLoaderTimer = null;
          steps.forEach(function (_, idx) {
            var stepEl = document.getElementById('ai-loader-step-' + idx);
            if (stepEl) stepEl.className = 'ai-loader-step completed';
          });
          if (progressBar) progressBar.style.width = '99%';
        }
      }, 200);
    };
  })();
</script>
