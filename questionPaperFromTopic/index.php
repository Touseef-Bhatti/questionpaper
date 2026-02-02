<?php
$pageTitle = "Paper Builder | Enterprise Edition";
require_once __DIR__ . '/../header.php';
?>

<!-- Link Professional CSS -->
<link rel="stylesheet" href="../css/paper-builder.css">

<main>
    <!-- Hero Section -->
    <section class="hero-builder">
        <div class="container text-center">
            <h1 class="hero-title animate-fade-up">Paper Builder</h1>
            <p class="hero-subtitle animate-fade-up" style="animation-delay: 0.1s;">
                Generate professional question papers in seconds using our AI-driven engine. Select your topics below to get started.
            </p>

            <!-- Workflow Controls (Tab Buttons) -->
            <div class="mode-container animate-fade-up" style="animation-delay: 0.2s;">
                <button class="mode-btn active" onclick="switchMode('mcqs')" id="btn-mcqs">
                    <i class="fas fa-list-ul"></i> MCQs
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

    <div class="container-fluid py-4 px-3 px-xl-5">
        <div class="row g-4">
            <!-- Main Content Area (Search & Results) -->
            <div class="col-lg-8">
                
                <!-- Search Bar -->
                <div class="search-wrapper mb-5 animate-fade-up" style="animation-delay: 0.3s;">
                    <div class="search-card">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search text-muted ms-3"></i>
                            <input type="text" id="topicSearch" 
                                   placeholder="Search for topics (e.g. 'Organic Chemistry')..." 
                                   autocomplete="off">
                        </div>
                        <button class="btn-search-main" onclick="handleSearch()" id="btnSearchMain">
                            Search
                        </button>
                    </div>
                </div>

                <!-- Active Topics Grid -->
                <div id="topicsGrid" class="row g-3 animate-fade-up" style="animation-delay: 0.4s;">
                    <!-- Initial State -->
                    <div class="col-12">
                        <div class="text-center py-5">
                            <div class="text-muted opacity-25 mb-3" style="font-size: 4rem;">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Ready to Search</h4>
                            <p class="text-muted">Enter a topic above to browse our question database.</p>
                        </div>
                    </div>
                </div>

                <!-- AI Discovery Loader -->
                <div id="aiLoader" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h6 class="fw-bold text-muted">AI is analyzing topics...</h6>
                </div>
                
                <div id="aiControl" class="text-center mt-4 d-none">
                    <button class="btn btn-light border shadow-sm px-4 py-2 fw-medium text-primary" onclick="fetchAiTopics()">
                        <i class="fas fa-robot me-2"></i> Load More Results
                    </button>
                </div>

            </div>

            <!-- Sidebar: Selected Collection -->
            <div class="col-lg-4">
                <div class="sticky-sidebar animate-fade-up" style="animation-delay: 0.5s;">
                    <div class="selected-panel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <i class="fas fa-layer-group text-primary"></i>
                                Selected Topics
                            </div>
                            <span class="badge bg-light text-dark border" id="collectionCount">0 Items</span>
                        </div>
                        
                        <div class="panel-body">
                            <div id="emptyState" class="empty-state">
                                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                                <h6 class="fw-bold mb-1">No topics selected</h6>
                                <p class="small mb-0">Your selected topics will appear here.</p>
                            </div>
                            <div id="selectedChips" class="d-none"></div>
                        </div>

                        <div class="panel-footer">
                             <button class="btn-finalize" onclick="finalizePaper()">
                                <span>Generate Paper</span>
                                <i class="fas fa-arrow-right"></i>
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
    let currentMode = 'mcqs'; // mcqs, short, long
    const selectionMatrix = {
        mcqs: new Set(),
        short: new Set(),
        long: new Set()
    };

    // Elements
    const els = {
        topicSearch: document.getElementById('topicSearch'),
        topicsGrid: document.getElementById('topicsGrid'),
        selectedChips: document.getElementById('selectedChips'),
        emptyState: document.getElementById('emptyState'),
        collectionCount: document.getElementById('collectionCount'),
        badges: {
            mcqs: document.getElementById('badge-mcqs'),
            short: document.getElementById('badge-short'),
            long: document.getElementById('badge-long')
        }
    };

    // Mode Switching
    window.switchMode = (mode) => {
        currentMode = mode;
        
        // Update Buttons
        document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`btn-${mode}`).classList.add('active');
        
        // Update Placeholder
        const labels = { mcqs: 'MCQs', short: 'Short Questions', long: 'Long Questions' };
        els.topicSearch.placeholder = `Search for ${labels[mode]} topics...`;
        els.topicSearch.value = ''; 
        
        // Update Grid
        renderGrid();
    };

    function renderGrid() {
        const selectedTopics = Array.from(selectionMatrix[currentMode]);
        els.topicsGrid.innerHTML = '';

        if (selectedTopics.length > 0) {
            selectedTopics.forEach(t => renderTopicCard(t));
        } else {
             els.topicsGrid.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <div class="text-muted opacity-25 mb-3" style="font-size: 4rem;">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Search for ${currentMode.toUpperCase()}</h4>
                        <p class="text-muted">Enter a topic to find questions.</p>
                    </div>
                </div>
            `;
        }
        
        document.getElementById('aiControl').classList.add('d-none');
    }

    // Search Logic
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
        try {
            const res = await fetch(`search_topics.php?search=${term}&type[]=${currentMode}`);
            const data = await res.json();
            
            els.topicsGrid.innerHTML = ''; // Clear previous

            if (data.success && data.topics.length > 0) {
                // Results Header
                const header = document.createElement('div');
                header.className = 'col-12 mb-2 animate-fade-up';
                header.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold mb-0">${data.topics.length} results for "${term}"</h6>
                        <span class="badge bg-light text-muted border text-uppercase">${currentMode}</span>
                    </div>
                `;
                els.topicsGrid.appendChild(header);

                data.topics.forEach(t => renderTopicCard(t));
                document.getElementById('aiControl').classList.remove('d-none');
            } else {
                els.topicsGrid.innerHTML = `
                    <div class="col-12 text-center py-5 animate-fade-up">
                        <div class="mb-3 text-muted" style="font-size: 3rem;"><i class="fas fa-search-minus"></i></div>
                        <h5 class="fw-bold">No results found</h5>
                        <p class="text-muted">Try a different keyword or use AI search.</p>
                    </div>
                `;
                document.getElementById('aiControl').classList.remove('d-none');
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function fetchAiTopics() {
        const term = els.topicSearch.value.trim();
        if (!term) return;
        
        document.getElementById('aiLoader').classList.remove('d-none');
        document.getElementById('aiControl').classList.add('d-none');
        
        try {
            const res = await fetch(`fetch_more_topics.php?search=${term}&type[]=${currentMode}`);
            const data = await res.json();
            
            document.getElementById('aiLoader').classList.add('d-none');
            
            if (data.success) {
                data.topics.forEach(t => renderTopicCard(t));
            } else {
                alert('No additional topics found.');
            }
        } catch (e) {
            document.getElementById('aiLoader').classList.add('d-none');
        }
    }

    function renderTopicCard(topic) {
        // Prevent duplicates
        const existing = Array.from(els.topicsGrid.querySelectorAll('.topic-text')).find(el => el.textContent === topic);
        if (existing) return;

        const isSelected = selectionMatrix[currentMode].has(topic);
        const col = document.createElement('div');
        col.className = 'col-md-4 col-sm-6 animate-fade-up';
        
        const modeIcons = {
            'mcqs': 'fa-list-ul',
            'short': 'fa-align-left',
            'long': 'fa-align-justify'
        };

        col.innerHTML = `
            <div class="topic-card ${isSelected ? 'selected' : ''}" onclick="toggleTopic('${topic}', this)">
                <div class="card-body">
                    <div class="topic-info">
                        <div class="topic-icon">
                            <i class="fas ${modeIcons[currentMode]}"></i>
                        </div>
                        <span class="topic-text" title="${topic}">${topic}</span>
                    </div>
                    <div class="selection-indicator">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>
        `;
        
        els.topicsGrid.appendChild(col);
    }

    window.toggleTopic = (topic, card) => {
        const set = selectionMatrix[currentMode];
        if (set.has(topic)) {
            set.delete(topic);
            card.classList.remove('selected');
        } else {
            set.add(topic);
            card.classList.add('selected');
        }
        updateCounts();
        renderSelectedChips();
    };

    window.removeTopic = (topic, mode) => {
        selectionMatrix[mode].delete(topic);
        
        // Update Grid Card if visible
        if (mode === currentMode) {
            const card = Array.from(els.topicsGrid.querySelectorAll('.topic-text'))
                .find(el => el.textContent === topic)
                ?.closest('.topic-card');
            if (card) card.classList.remove('selected');
        }
        
        updateCounts();
        renderSelectedChips();
    };

    function renderSelectedChips() {
        els.selectedChips.innerHTML = '';
        let total = 0;
        
        const labels = {
            mcqs: 'MCQs',
            short: 'Short Questions',
            long: 'Long Questions'
        };

        Object.keys(selectionMatrix).forEach(mode => {
            const topics = selectionMatrix[mode];
            if (topics.size > 0) {
                const group = document.createElement('div');
                group.className = 'mb-3 animate-fade-up';
                
                const title = document.createElement('div');
                title.className = 'selected-group-title';
                title.innerHTML = `<span>${labels[mode]}</span> <span class="badge bg-light text-dark border">${topics.size}</span>`;
                group.appendChild(title);

                const container = document.createElement('div');
                topics.forEach(topic => {
                    total++;
                    const chip = document.createElement('div');
                    chip.className = 'selected-chip';
                    chip.innerHTML = `
                        <span>${topic}</span>
                        <div class="btn-remove" onclick="removeTopic('${topic}', '${mode}')">
                            <i class="fas fa-times"></i>
                        </div>
                    `;
                    container.appendChild(chip);
                });
                
                group.appendChild(container);
                els.selectedChips.appendChild(group);
            }
        });
        
        els.collectionCount.textContent = `${total} Items`;
        
        if (total > 0) {
            els.emptyState.classList.add('d-none');
            els.selectedChips.classList.remove('d-none');
        } else {
            els.emptyState.classList.remove('d-none');
            els.selectedChips.classList.add('d-none');
        }
    }

    function updateCounts() {
        els.badges.mcqs.textContent = selectionMatrix.mcqs.size;
        els.badges.short.textContent = selectionMatrix.short.size;
        els.badges.long.textContent = selectionMatrix.long.size;
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
        
        // Populate Form
        ['mcqs', 'short', 'long'].forEach(type => {
            selectionMatrix[type].forEach(t => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `topics_${type}[]`;
                input.value = t;
                form.appendChild(input);

                // Legacy Support
                const legacyInput = document.createElement('input');
                legacyInput.type = 'hidden';
                legacyInput.name = 'topics[]';
                legacyInput.value = t;
                form.appendChild(legacyInput);
            });
            
            if (selectionMatrix[type].size > 0) {
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'active_types[]';
                typeInput.value = type;
                form.appendChild(typeInput);
            }
        });

        document.body.appendChild(form);
        form.submit();
    };
    
    // Init
    updateCounts();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
