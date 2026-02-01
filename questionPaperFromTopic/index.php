<?php
$pageTitle = "Intelligent Paper Builder | AI Question Paper Generator";
$metaDescription = "The ultimate ALL type question paper generator. Create question papers for university, college, or school. Fast, quick, 1-click AI paper generation.";
require_once __DIR__ . '/../header.php';
?>

<!-- Link Professional CSS -->
<link rel="stylesheet" href="../css/paper-builder.css">

<main class="min-vh-100 bg-light position-relative">
    <!-- Ornament Background -->
    <div class="bg-ornament"></div>

    <!-- Hero Section -->
    <section class="hero-builder animate-fade-up pb-4">
        <div class="container text-center position-relative z-1">
            <span class="badge bg-primary-soft text-primary border rounded-pill px-3 py-2 mb-3 fw-bold shadow-sm animate-fade-down">
                <i class="fas fa-magic me-2"></i>AI-Powered Paper Generation
            </span>
            <h1 class="display-4 mb-3 fw-bold">
                <span class="text-gradient-primary">Intelligent Paper Builder</span>
            </h1>
            
            <p class="text-muted mb-4 small text-uppercase fw-bold tracking-wider">
                <i class="fas fa-bolt text-warning me-1"></i> Fast &middot; 
                <i class="fas fa-robot text-primary me-1"></i> AI Powered &middot; 
                <i class="fas fa-check-circle text-success me-1"></i> 1-Click Ready
            </p>

            <!-- Workflow Controls (Tab Buttons) -->
            <div class="mode-container animate-scale-in mb-0">
                <button class="mode-btn active" onclick="switchMode('mcqs')" id="btn-mcqs">
                    <i class="fas fa-check-square"></i> MCQs
                    <span class="badge" id="badge-mcqs">0</span>
                </button>
                <button class="mode-btn" onclick="switchMode('short')" id="btn-short">
                    <i class="fas fa-align-left"></i> Short Qs
                    <span class="badge" id="badge-short">0</span>
                </button>
                <button class="mode-btn" onclick="switchMode('long')" id="btn-long">
                    <i class="fas fa-align-justify"></i> Long Qs
                    <span class="badge" id="badge-long">0</span>
                </button>
            </div>
        </div>
    </section>

    <div class="container-fluid py-4 px-3 px-md-5">
        <div class="row g-4">
            <!-- Main Content Area (Search & Results) -->
            <div class="col-lg-8">
                
                <!-- Search Bar -->
                <div class="search-wrapper mb-4 animate-fade-up delay-100">
                    <div class="search-card">
                        <div class="search-input-wrapper">
                            <input type="text" id="topicSearch" class="form-control" 
                                   placeholder="Search topics for MCQs...">
                            
                            <button class="btn-search-main" onclick="handleSearch()" id="btnSearchMain">
                                <i class="fas fa-search"></i> <span class="d-none d-sm-inline">Search</span>
                            </button>

                            <button class="btn-ai-search-small d-none" id="btnAiSearchSmall" onclick="fetchAiTopics()" title="Deep AI Search">
                                <i class="fas fa-robot"></i>
                            </button>

                            <span class="search-type-indicator type-mcqs d-none d-md-inline-block" id="searchLabel">MCQs</span>
                        </div>
                    </div>
                </div>

                <!-- Active Topics Grid -->
                <div id="topicsGrid" class="row g-4 mb-5 animate-fade-up delay-200">
                    <!-- Initial State -->
                    <div class="col-12 text-center py-5">
                        <div class="text-muted opacity-25 display-1 mb-3"><i class="fas fa-search"></i></div>
                        <h4 class="fw-bold">Search for MCQs</h4>
                        <p class="text-muted">Type any topic in the search bar to find questions.</p>
                    </div>
                </div>

                <!-- AI Discovery Loader -->
                <div id="aiLoader" class="text-center py-5 d-none">
                    <div class="spinner-grow text-primary mb-3" role="status"></div>
                    <h6 class="fw-bold text-muted">AI is discovering deep topics...</h6>
                </div>
                
                <div id="aiControl" class="text-center ai-section mb-5 d-none">
                    <button class="btn btn-ai-search" onclick="fetchAiTopics()">
                        <i class="fas fa-robot"></i> Deep Search with AI
                    </button>
                </div>

            </div>

            <!-- Sidebar: Selected Collection -->
            <div class="col-lg-4">
                <div class="sticky-sidebar animate-fade-up delay-300">
                    <div id="selectedContainer" class="selected-panel p-0 rounded-4 bg-white border shadow-sm overflow-hidden">
                        <div class="p-4 border-bottom bg-light bg-opacity-50">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                                    <span class="icon-circle-sm bg-primary bg-opacity-10 text-primary me-2">
                                        <i class="fas fa-shopping-basket"></i>
                                    </span>
                                    Selected Collection
                                </h6>
                                <span class="badge bg-white text-dark border shadow-sm" id="collectionCount">0 Items</span>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-white" style="min-height: 200px; max-height: calc(100vh - 300px); overflow-y: auto;">
                            <div id="emptyState" class="text-center py-4">
                                <div class="mb-3 text-muted opacity-25 display-4">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h6 class="text-muted fw-bold">Collection is Empty</h6>
                                <p class="small text-muted mb-0">Select topics from the search results to add them here.</p>
                            </div>
                            <div id="selectedChips" class="d-flex flex-column gap-3 d-none"></div>
                        </div>

                        <div class="p-3 bg-light border-top">
                             <button class="btn btn-generate w-100 shadow-sm" onclick="finalizePaper()">
                                <span>Finalize Paper</span> <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    

</main>

<!-- Floating Action Dock (Mobile Only) -->
<div id="actionDock" class="fixed-bottom action-dock shadow-lg animate-slide-up d-md-none" style="z-index: 1050;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-7 col-md-6 d-flex align-items-center gap-3">
                <div class="count-bubble" id="totalCount">0</div>
                <div>
                    <span class="d-block fw-bold text-dark fs-5 lh-1">Selected Topics</span>
                    <small class="text-muted">Across all question types</small>
                </div>
            </div>
            
            <div class="col-5 col-md-6 d-flex justify-content-end">
                <button class="btn btn-generate" onclick="finalizePaper()">
                    <span>Finalize</span> <i class="fas fa-arrow-right ms-2 d-none d-sm-inline"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // State
    let currentMode = 'mcqs'; // mcqs, short, long
    const selectionMatrix = {
        mcqs: new Set(),
        short: new Set(),
        long: new Set()
    };
    let searchTimer;

    // Elements
    const els = {
        topicSearch: document.getElementById('topicSearch'),
        topicsGrid: document.getElementById('topicsGrid'),
        searchLabel: document.getElementById('searchLabel'),
        totalCount: document.getElementById('totalCount'),
        selectedContainer: document.getElementById('selectedContainer'),
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
        
        // Update Search Label & Placeholder
        const labels = { mcqs: 'MCQs', short: 'Short Questions', long: 'Long Questions' };
        if (els.searchLabel) {
            els.searchLabel.textContent = labels[mode];
            els.searchLabel.className = `search-type-indicator type-${mode} d-none d-md-inline-block`;
        }
        els.topicSearch.placeholder = `Search topics for ${labels[mode]}...`;
        els.topicSearch.value = ''; // Clear search
        
        // Update Grid
        renderGrid();
    };

    function renderGrid() {
        const selectedTopics = Array.from(selectionMatrix[currentMode]);
        els.topicsGrid.innerHTML = '';

        if (selectedTopics.length > 0) {
            selectedTopics.forEach(t => renderTopicCard(t, true));
        } else {
             els.topicsGrid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="text-muted opacity-25 display-1 mb-3"><i class="fas fa-search"></i></div>
                    <h4 class="fw-bold">Search for ${currentMode.toUpperCase()}</h4>
                    <p class="text-muted">Type any topic in the search bar to find questions.</p>
                </div>
            `;
        }
        
        document.getElementById('aiControl').classList.add('d-none');
    }

    // Search Logic - Triggered only by button or Enter
    function handleSearch() {
        const term = els.topicSearch.value.trim();
        if (term.length < 2) {
            alert('Please enter at least 2 characters to search.');
            return;
        }
        executeSearch(term);
    }

    els.topicSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    });

    // Remove the old input listener that was causing auto-search
    // els.topicSearch.addEventListener('input', (e) => { ... });

    async function executeSearch(term) {
        try {
            const res = await fetch(`search_topics.php?search=${term}&type[]=${currentMode}`);
            const data = await res.json();
            
            if (data.success && data.topics.length > 0) {
                // Add Results Header
                const resultsHeader = document.createElement('div');
                resultsHeader.className = 'col-12 animate-fade-up';
                resultsHeader.innerHTML = `
                    <div class="results-header">
                        <div class="results-count">
                            <span>${data.topics.length}</span> Topics found for "${term}"
                        </div>
                        <div class="results-badge">
                            ${currentMode}
                        </div>
                    </div>
                `;
                els.topicsGrid.appendChild(resultsHeader);

                data.topics.forEach(t => renderTopicCard(t, false));
                document.getElementById('btnAiSearchSmall').classList.remove('d-none');
                document.getElementById('aiControl').classList.remove('d-none');
            } else {
                els.topicsGrid.innerHTML = `
                    <div class="col-12 text-center py-5 animate-fade-up">
                        <div class="mb-3 text-muted" style="font-size: 3rem;"><i class="fas fa-search-minus"></i></div>
                        <h5 class="fw-bold text-dark">No topics found for "${term}"</h5>
                        <p class="text-muted">Our AI can help you find topics. Try Deep Search.</p>
                    </div>
                `;
                document.getElementById('btnAiSearchSmall').classList.remove('d-none');
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
                // Keep existing cards and add new ones if not duplicates
                data.topics.forEach(t => renderTopicCard(t, false));
            } else {
                alert('No additional topics found by AI.');
            }
        } catch (e) {
            document.getElementById('aiLoader').classList.add('d-none');
        }
    }

    function renderTopicCard(topic, isSelectedView) {
        const isSelected = selectionMatrix[currentMode].has(topic);
        
        // Prevent duplicate rendering in results
        const existing = Array.from(els.topicsGrid.querySelectorAll('.topic-title')).find(el => el.textContent === topic);
        if (existing) return;

        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4 animate-fade-up';
        
        const modeIcons = {
            'mcqs': 'fa-list-ul',
            'short': 'fa-align-left',
            'long': 'fa-align-justify'
        };
        const activeIcon = isSelected ? 'fa-check' : modeIcons[currentMode];

        col.innerHTML = `
            <div class="topic-card ${isSelected ? 'selected' : ''} type-${currentMode}" onclick="toggleTopic('${topic}', this)">
                <div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3 overflow-hidden">
                        <div class="topic-icon-wrapper">
                            <i class="fas ${activeIcon}"></i>
                        </div>
                        <span class="topic-title" title="${topic}">${topic}</span>
                    </div>
                    <div class="action-btn">
                        <i class="fas ${isSelected ? 'fa-times' : 'fa-plus'}"></i>
                    </div>
                </div>
            </div>
        `;
        
        els.topicsGrid.appendChild(col);
    }

    window.toggleTopic = (topic, card) => {
        const set = selectionMatrix[currentMode];
        const modeIcons = {
            'mcqs': 'fa-list-ul',
            'short': 'fa-align-left',
            'long': 'fa-align-justify'
        };

        if (set.has(topic)) {
            set.delete(topic);
            if(card) {
                card.classList.remove('selected');
                card.querySelector('.topic-icon-wrapper i').className = 'fas ' + modeIcons[currentMode];
                card.querySelector('.action-btn i').className = 'fas fa-plus';
            }
        } else {
            set.add(topic);
            if(card) {
                card.classList.add('selected');
                card.querySelector('.topic-icon-wrapper i').className = 'fas fa-check';
                card.querySelector('.action-btn i').className = 'fas fa-times';
            }
        }
        updateCounts();
        renderSelectedChips();
    };

    window.removeTopic = (topic, mode) => {
        selectionMatrix[mode].delete(topic);
        
        // If currently viewing this mode, update the card if it exists
        if (mode === currentMode) {
            const card = Array.from(els.topicsGrid.querySelectorAll('.topic-title'))
                .find(el => el.textContent === topic)
                ?.closest('.topic-card');
                
            if (card) {
                card.classList.remove('selected');
                const modeIcons = {
                    'mcqs': 'fa-list-ul',
                    'short': 'fa-align-left',
                    'long': 'fa-align-justify'
                };
                card.querySelector('.topic-icon-wrapper i').className = 'fas ' + modeIcons[mode];
                card.querySelector('.action-btn i').className = 'fas fa-plus';
            }
        }
        
        updateCounts();
        renderSelectedChips();
    };

    function renderSelectedChips() {
        els.selectedChips.innerHTML = '';
        let total = 0;
        
        const labels = {
            mcqs: { text: 'Multiple Choice', color: 'text-mcq' },
            short: { text: 'Short Questions', color: 'text-short' },
            long: { text: 'Long Questions', color: 'text-long' }
        };

        Object.keys(selectionMatrix).forEach(mode => {
            const topics = selectionMatrix[mode];
            if (topics.size > 0) {
                // Create Group Container
                const group = document.createElement('div');
                group.className = 'selected-group mb-3';
                
                // Group Header
                const header = document.createElement('div');
                header.className = `group-header-label ${labels[mode].color}`;
                header.innerHTML = `<i class="fas fa-layer-group me-2"></i> ${labels[mode].text} <span class="badge bg-light text-dark border ms-2">${topics.size}</span>`;
                group.appendChild(header);

                // Chips Container
                const chipsContainer = document.createElement('div');
                chipsContainer.className = 'd-flex flex-column gap-2';
                
                topics.forEach(topic => {
                    total++;
                    const chip = document.createElement('div');
                    chip.className = `topic-chip type-${mode} animate-scale-in`;
                    chip.innerHTML = `
                        <span>${topic}</span>
                        <div class="remove-btn" onclick="removeTopic('${topic}', '${mode}')">
                            <i class="fas fa-times"></i>
                        </div>
                    `;
                    chipsContainer.appendChild(chip);
                });
                
                group.appendChild(chipsContainer);
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
        // Update Badges
        els.badges.mcqs.textContent = selectionMatrix.mcqs.size;
        els.badges.short.textContent = selectionMatrix.short.size;
        els.badges.long.textContent = selectionMatrix.long.size;
        
        // Update Total
        const total = selectionMatrix.mcqs.size + selectionMatrix.short.size + selectionMatrix.long.size;
        els.totalCount.textContent = total;
    }

    window.finalizePaper = () => {
        const allTopics = new Set([
            ...selectionMatrix.mcqs, 
            ...selectionMatrix.short, 
            ...selectionMatrix.long
        ]);
        
        if (allTopics.size === 0) {
            alert('Please select at least one topic to generate the paper.');
            return;
        }
        
        const activeTypes = [];
        if (selectionMatrix.mcqs.size > 0) activeTypes.push('mcqs');
        if (selectionMatrix.short.size > 0) activeTypes.push('short');
        if (selectionMatrix.long.size > 0) activeTypes.push('long');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'finalize_paper.php';
        
        // Send ALL topics (fallback/legacy)
        allTopics.forEach(t => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'topics[]';
            input.value = t;
            form.appendChild(input);
        });

        // Send Categorized Topics
        selectionMatrix.mcqs.forEach(t => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'topics_mcqs[]';
            input.value = t;
            form.appendChild(input);
        });

        selectionMatrix.short.forEach(t => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'topics_short[]';
            input.value = t;
            form.appendChild(input);
        });

        selectionMatrix.long.forEach(t => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'topics_long[]';
            input.value = t;
            form.appendChild(input);
        });
        
        activeTypes.forEach(t => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'active_types[]';
            input.value = t;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };
    
    // Init
    updateCounts();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
