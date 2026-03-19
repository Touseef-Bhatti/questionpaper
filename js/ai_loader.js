/**
 * ai_loader.js — Reusable AI Step Loader
 *
 * Usage:
 *   showAILoader(steps, noteText);
 *
 * steps: array of objects, e.g.
 *   [
 *     { label: 'Analyzing topics',    duration: 3500 },
 *     { label: 'Designing MCQs',      duration: 3500 },
 *     ...
 *   ]
 *
 * The loader HTML must already be present via the ai_loader.php include.
 * Uses Date.now() timestamps instead of nested setTimeout so it works
 * correctly on mobile browsers that throttle timers when the page is
 * backgrounded or the screen is locked.
 */
function showAILoader(steps, noteText) {
    var modal       = document.getElementById('aiLoaderModal');
    var progressBar = document.getElementById('aiLoaderProgressBar');
    var noteEl      = document.getElementById('aiLoaderNote');

    if (!modal) return;

    // Update note text if provided
    if (noteText && noteEl) {
        noteEl.textContent = noteText;
    }

    // Lock scroll
    document.body.style.overflow = 'hidden';
    modal.style.display = 'flex';

    var totalDuration = steps.reduce(function(acc, s) { return acc + s.duration; }, 0);

    // Populate step rows (clear any previous)
    var stepsContainer = document.getElementById('aiLoaderSteps');
    if (stepsContainer) {
        stepsContainer.innerHTML = '';
        steps.forEach(function(step, idx) {
            var row = document.createElement('div');
            row.className = 'ai-loader-step';
            row.id = 'ai-step-' + idx;
            row.innerHTML =
                '<div class="ai-loader-step-icon" id="ai-icon-' + idx + '">' +
                    '<i class="fas fa-circle-notch"></i>' +
                '</div>' +
                '<div class="ai-loader-step-text">' + step.label + '</div>';
            stepsContainer.appendChild(row);
        });
    }

    // Reset progress
    if (progressBar) progressBar.style.width = '0%';

    // Timestamp-based interval — reliable on mobile
    var startTime    = Date.now();
    var lastStepIdx  = -1;

    var interval = setInterval(function() {
        var elapsed  = Date.now() - startTime;
        var progress = Math.min((elapsed / totalDuration) * 100, 99);

        if (progressBar) progressBar.style.width = progress + '%';

        // Determine active step by cumulative time
        var cumulative    = 0;
        var activeIdx     = steps.length - 1;
        for (var i = 0; i < steps.length; i++) {
            cumulative += steps[i].duration;
            if (elapsed < cumulative) { activeIdx = i; break; }
        }

        // Only touch the DOM when step changes
        if (activeIdx !== lastStepIdx) {
            lastStepIdx = activeIdx;
            steps.forEach(function(step, idx) {
                var stepEl = document.getElementById('ai-step-' + idx);
                var iconEl = document.getElementById('ai-icon-' + idx);
                if (!stepEl) return;
                if (idx < activeIdx) {
                    stepEl.className = 'ai-loader-step completed';
                    iconEl.innerHTML = '<i class="fas fa-check"></i>';
                } else if (idx === activeIdx) {
                    stepEl.className = 'ai-loader-step active';
                    iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                } else {
                    stepEl.className = 'ai-loader-step';
                    iconEl.innerHTML = '<i class="fas fa-circle-notch"></i>';
                }
            });
        }

        // When done, mark all complete and stop
        if (elapsed >= totalDuration) {
            clearInterval(interval);
            steps.forEach(function(step, idx) {
                var stepEl = document.getElementById('ai-step-' + idx);
                var iconEl = document.getElementById('ai-icon-' + idx);
                if (stepEl) {
                    stepEl.className = 'ai-loader-step completed';
                    iconEl.innerHTML = '<i class="fas fa-check"></i>';
                }
            });
            if (progressBar) progressBar.style.width = '99%';
        }
    }, 250);
}
