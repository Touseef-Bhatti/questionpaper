<?php
require_once __DIR__ . '/../auth/auth_check.php';
$pageTitle = "Question Paper Generator | Enterprise Edition";
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../middleware/SubscriptionCheck.php';
require_once __DIR__ . '/../services/CacheManager.php';
require_once __DIR__ . '/../services/DatabaseQueryService.php';

// Initialize services
$cache = new CacheManager();
$dbService = new DatabaseQueryService($conn, $cache);
?>

<!-- Link Professional CSS -->
<link rel="stylesheet" href="../css/paper-builder.css?v=<?= time() . rand(7000, 8000) ?>">
<link rel="stylesheet" href="../css/buttons.css?v=<?= time() . rand(1, 1000) ?>">

<main class="animate-fade-up">
    <!-- Hero Section -->
    <section class="hero-builder text-center">
        <div class="container">
            <h1 class="hero-title animate-fade-up">Question Paper Generator</h1>
            <p class="hero-subtitle animate-fade-up">
                AI-Powered curriculum mapping and custom question paper generator. <br>
                Empowering educators with precision-built academic resources.
            </p>

            <!-- Mode Selector -->
            <div class="mode-container animate-fade-up">
                <button class="mode-btn active" onclick="switchMode('mcqs')" id="btn-mcqs">
                    <i class="fas fa-layer-group"></i> MCQs
                    <span class="badge" id="badge-mcqs">0</span>
                </button>
                <button class="mode-btn" onclick="switchMode('short')" id="btn-short">
                    <i class="fas fa-align-left"></i> Short Questions
                    <span class="badge" id="badge-short">0</span>
                </button>
                <button class="mode-btn" onclick="switchMode('long')" id="btn-long">
                    <i class="fas fa-align-justify"></i> Long Questions
                    <span class="badge" id="badge-long">0</span>
                </button>
            </div>
        </div>
    </section>

    <div class="container py-5 px-4 px-xl-5">
        <div class="row justify-content-center">
            <!-- Main Application Content -->
            <div class="col-lg-10">

                <!-- Search Bar -->
                <div class="search-wrapper mb-4">
                    <div class="search-card">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="topicSearch"
                                   placeholder="Search for MCQs topics..."
                                   autocomplete="off">
                        </div>
                        <button class="btn-search-main" onclick="handleSearch()" id="btnSearchMain">
                            Search
                        </button>
                    </div>
                </div>

                <!-- Topics Discovery Box - hidden until search -->
                <div class="topics-discovery-box d-none" id="discoveryBox">
                    <div id="topicsGrid" class="row g-4">
                        <!-- Results injected here -->
                    </div>

                    <!-- AI Discovery Loader -->
                    <div id="aiLoader" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <h6 class="fw-bold text-muted">AI is analyzing topics...</h6>
                    </div>
                    
                    <div id="aiControl" class="text-center mt-3 d-none">
                        <button class="btn btn-light border shadow-sm px-4 py-2 fw-medium text-primary" onclick="fetchAiTopics()">
                            <i class="fas fa-robot me-2"></i> Load More Results
                        </button>
                    </div>
                </div>


                <!-- Generate Section - shown when topics are selected -->
                <div id="generateSection" class="generate-section d-none mt-4">
                    <div class="generate-card">
                        <div class="generate-info">
                            <i class="fas fa-file-alt generate-icon"></i>
                            <div>
                                <div class="generate-count"><span id="totalSelectedCount">0</span> Topics Selected</div>
                                <div class="generate-label">Ready to build your custom paper</div>
                            </div>
                        </div>
                        <div class="btn-wrapper generate-action-btn" onclick="finalizePaper()">
                          <button type="button" class="btn">
                            <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"></path>
                            </svg>
                            <div style="min-width: 16.2em;" class="txt-wrapper">
                              <div class="txt-1">
                                <span class="btn-letter">G</span><span class="btn-letter">e</span><span class="btn-letter">n</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">a</span><span class="btn-letter">t</span><span class="btn-letter">e</span><span style="opacity: 0;" class="btn-letter">-</span><span class="btn-letter">Q</span><span class="btn-letter">u</span><span class="btn-letter">e</span><span class="btn-letter">s</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">o</span><span class="btn-letter">n</span><span style="opacity: 0;" class="btn-letter">-</span><span class="btn-letter">P</span><span class="btn-letter">a</span><span class="btn-letter">p</span><span class="btn-letter">e</span><span class="btn-letter">r</span>
                              </div>
                              <div class="txt-2">
                                <span class="btn-letter">G</span><span class="btn-letter">e</span><span class="btn-letter">n</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">a</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">n</span><span class="btn-letter">g</span><span style="opacity: 0;" class="btn-letter">-</span><span class="btn-letter">Q</span><span class="btn-letter">u</span><span class="btn-letter">e</span><span class="btn-letter">s</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">o</span><span class="btn-letter">n</span><span style="opacity: 0;" class="btn-letter">-</span><span class="btn-letter">P</span><span class="btn-letter">a</span><span class="btn-letter">p</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">.</span><span class="btn-letter">.</span><span class="btn-letter">.</span>
                              </div>
                            </div>
                          </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // State
    let currentMode = 'mcqs';
    let selectedDesign = 1;
    const selectionMatrix = {
        mcqs: new Set(),
        short: new Set(),
        long: new Set()
    };

    // Elements
    const els = {
        topicSearch: document.getElementById('topicSearch'),
        topicsGrid: document.getElementById('topicsGrid'),
        discoveryBox: document.getElementById('discoveryBox'),
        generateSection: document.getElementById('generateSection'),
        totalSelectedCount: document.getElementById('totalSelectedCount'),
        badges: {
            mcqs: document.getElementById('badge-mcqs'),
            short: document.getElementById('badge-short'),
            long: document.getElementById('badge-long')
        }
    };

    // Mode Switching
    window.switchMode = (mode) => {
        currentMode = mode;
        document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`btn-${mode}`).classList.add('active');

        const labels = { mcqs: 'MCQs', short: 'Short Questions', long: 'Long Questions' };
        els.topicSearch.placeholder = `Search for ${labels[mode]} topics...`;
        els.topicSearch.value = '';

        // Re-render grid with only selected topics for this mode
        els.topicsGrid.innerHTML = '';
        const selected = Array.from(selectionMatrix[mode]);
        if (selected.length > 0) selected.forEach(t => renderTopicCard(t));
        
        // Hide AI controls when switching modes
        document.getElementById('aiControl').classList.add('d-none');
    };

    // Search
    function handleSearch() {
        const term = els.topicSearch.value.trim();
        if (term.length < 2) {
            alert('Please enter at least 2 characters.');
            return;
        }
        executeSearch(term);
    }

    els.topicSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSearch();
    });

    async function executeSearch(term) {
        els.topicsGrid.innerHTML = '';
        els.topicsGrid.className = 'topic-list'; // Original class from your Turn 3 Read
        
        // Hide AI controls
        document.getElementById('aiControl').classList.add('d-none');

        // Find or create results-header
        let headerDiv = document.getElementById('dynResultsHeader');
        if (!headerDiv) {
            headerDiv = document.createElement('div');
            headerDiv.id = 'dynResultsHeader';
            headerDiv.className = 'results-header';
            els.discoveryBox.insertBefore(headerDiv, els.topicsGrid);
        }

        els.discoveryBox.classList.remove('d-none'); // Show the box

        try {
            const res = await fetch(`search_topics.php?search=${encodeURIComponent(term)}&type[]=${currentMode}`);
            const data = await res.json();

            if (data.success && data.topics.length > 0) {
                headerDiv.innerHTML = `✨ Found ${data.topics.length} search results`;
                data.topics.forEach(t => renderTopicCard(t));
                // Show AI load more button after results
                document.getElementById('aiControl').classList.remove('d-none');
            } else {
                headerDiv.innerHTML = `No topics found in database for "${term}"`;
                els.topicsGrid.innerHTML = `
                    <div id="noTopicsHint" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 24px;">
                        Try a different keyword.
                    </div>
                `;
                // Show AI load more button even if no local results
                document.getElementById('aiControl').classList.remove('d-none');
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function fetchAiTopics() {
        const term = els.topicSearch.value.trim();
        if (!term) return;

        // Get currently displayed topics to exclude them
        const displayedTopics = Array.from(els.topicsGrid.querySelectorAll('.topic-name')).map(el => el.textContent);
        let excludeParams = '';
        if (displayedTopics.length > 0) {
            excludeParams = '&' + displayedTopics.map(t => `exclude[]=${encodeURIComponent(t)}`).join('&');
        }

        document.getElementById('aiLoader').classList.remove('d-none');
        document.getElementById('aiControl').classList.add('d-none');

        try {
            const res = await fetch(`fetch_more_topics.php?search=${encodeURIComponent(term)}&type[]=${currentMode}${excludeParams}`);
            const data = await res.json();

            document.getElementById('aiLoader').classList.add('d-none');

            if (data.success && data.topics.length > 0) {
                data.topics.forEach(t => renderTopicCard(t));
            } else {
                alert('No additional topics found.');
            }
            
            // Re-show button
            document.getElementById('aiControl').classList.remove('d-none');
        } catch (e) {
            console.error(e);
            document.getElementById('aiLoader').classList.add('d-none');
            document.getElementById('aiControl').classList.remove('d-none');
        }
    }

    function renderTopicCard(topic) {
        const existing = Array.from(els.topicsGrid.querySelectorAll('.topic-name'))
            .find(el => el.textContent === topic);
        if (existing) return;

        const isSelected = selectionMatrix[currentMode].has(topic);
        
        const item = document.createElement('div');
        item.className = `topic-item ${isSelected ? 'selected' : ''}`;
        item.setAttribute('onclick', `toggleTopic('${topic.replace(/'/g, "\\'")}', this)`);

        item.innerHTML = `
            <div class="topic-info">
                <div class="topic-name">${topic}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="topic-similarity">100% match</div>
                ${isSelected ? `<div class="btn-remove-lite" onclick="event.stopPropagation(); toggleTopic('${topic.replace(/'/g, "\\'")}', this.closest('.topic-item'))">×</div>` : ''}
            </div>
        `;

        els.topicsGrid.appendChild(item);
    }

    window.toggleTopic = (topic, card) => {
        const set = selectionMatrix[currentMode];
        if (set.has(topic)) {
            set.delete(topic);
            card.classList.remove('selected');
            // Remove remove-lite button if exists
            const rmBtn = card.querySelector('.btn-remove-lite');
            if (rmBtn) rmBtn.remove();
        } else {
            set.add(topic);
            card.classList.add('selected');
            // Add remove-lite button
            const controls = card.querySelector('.d-flex');
            const rmBtn = document.createElement('div');
            rmBtn.className = 'btn-remove-lite';
            rmBtn.setAttribute('onclick', `event.stopPropagation(); toggleTopic('${topic.replace(/'/g, "\\'")}', this.closest('.topic-item'))`);
            rmBtn.innerHTML = '×';
            controls.appendChild(rmBtn);
        }
        updateCounts();
    };

    function updateCounts() {
        const mcqs  = selectionMatrix.mcqs.size;
        const short = selectionMatrix.short.size;
        const long  = selectionMatrix.long.size;
        const total = mcqs + short + long;

        els.badges.mcqs.textContent  = mcqs;
        els.badges.short.textContent = short;
        els.badges.long.textContent  = long;
        els.totalSelectedCount.textContent = total;

        if (total > 0) {
            els.generateSection.classList.remove('d-none');
        } else {
            els.generateSection.classList.add('d-none');
        }
    }


    window.finalizePaper = () => {
        const allTopics = new Set([
            ...selectionMatrix.mcqs,
            ...selectionMatrix.short,
            ...selectionMatrix.long
        ]);

        if (allTopics.size === 0) {
            alert('Please select at least one topic.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'finalize_paper.php';

        ['mcqs', 'short', 'long'].forEach(type => {
            selectionMatrix[type].forEach(t => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = `topics_${type}[]`;
                inp.value = t;
                form.appendChild(inp);

                const legacy = document.createElement('input');
                legacy.type = 'hidden';
                legacy.name = 'topics[]';
                legacy.value = t;
                form.appendChild(legacy);
            });

            if (selectionMatrix[type].size > 0) {
                const typeInp = document.createElement('input');
                typeInp.type = 'hidden';
                typeInp.name = 'active_types[]';
                typeInp.value = type;
                form.appendChild(typeInp);
            }
        });

        const designInp = document.createElement('input');
        designInp.type = 'hidden';
        designInp.name = 'header_design';
        designInp.value = selectedDesign;
        form.appendChild(designInp);

        document.body.appendChild(form);
        form.submit();
    };

    // Init
    updateCounts();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
