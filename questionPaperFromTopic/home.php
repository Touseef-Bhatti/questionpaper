<?php
session_start();
// require_once __DIR__ . '/../auth/auth_check.php';
$pageTitle = "Free AI Question Paper Generator for Any Topic (MCQs, Short & Long Questions)";

$metaDescription = "Instantly generate MCQs, short and long questions from any topic using AI. Perfect for 9th, 10th, GCSE, and university students. Create exam-ready question papers in seconds.";

$metaKeywords = "AI question generator free, MCQ generator by topic, generate exam questions online, AI paper generator, biology MCQs chapter wise, 9th class MCQs, GCSE question generator, quiz maker AI, assignment question generator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    
    <!-- monetag ads -->
    <script>(function(s){s.dataset.zone='10846367',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>

      <!-- monetag ads -->

    <?php 
    $only_head = true;
    require __DIR__ . '/../header.php'; 
    ?>

    <!-- Link Professional CSS -->
    <link rel="stylesheet" href="<?= $assetBase ?>css/paper-builder.css?v=<?= time() . rand(7000, 8000) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css?v=<?= time() . rand(1, 1000) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/search-results.css?v=<?= time() . rand(1, 1000) ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/ai_loader.css?v=<?= time() ?>">

    <style>
    /* Honeycomb & Progress Loader Styles (Matched with mcqs_topic.php) */
    #inlineLoader {
        display: none;
        padding: 60px 0;
        text-align: center;
        background: #ffffff; /* Solid sharp background */
        border-radius: 30px;
        margin: 40px 0;
        border: 1px solid var(--border, #e2e8f0);
        box-shadow: var(--sh-subtle);
        animation: loaderFadeIn 0.5s ease;
    }

    @keyframes loaderFadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #loaderText {
        color: var(--primary);
        font-weight: 900;
        font-size: 1.4rem;
        margin-top: 24px;
        letter-spacing: -0.02em;
    }

    .honeycomb {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
        height: 40px;
    }

    .honeycomb div {
        width: 12px;
        height: 32px;
        background: var(--primary);
        border-radius: 100px;
        animation: honeyWave 1.2s ease-in-out infinite;
    }

    .honeycomb div:nth-child(1) { animation-delay: 0.1s; background: #6366f1; }
    .honeycomb div:nth-child(2) { animation-delay: 0.2s; background: #818cf8; }
    .honeycomb div:nth-child(3) { animation-delay: 0.3s; background: #a855f7; }
    .honeycomb div:nth-child(4) { animation-delay: 0.4s; background: #c084fc; }
    .honeycomb div:nth-child(5) { animation-delay: 0.5s; background: #a855f7; }
    .honeycomb div:nth-child(6) { animation-delay: 0.6s; background: #818cf8; }
    .honeycomb div:nth-child(7) { animation-delay: 0.7s; background: #6366f1; }

    @keyframes honeyWave {
        0%, 100% { transform: scaleY(0.4); opacity: 0.5; }
        50% { transform: scaleY(1); opacity: 1; }
    }

    .loader-progress {
        width: 100%;
        max-width: 400px;
        height: 8px;
        background: #e2e8f0;
        border-radius: 100px;
        margin: 32px auto 0;
        overflow: hidden;
        position: relative;
    }

    .loader-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #818cf8);
        width: 0%;
        border-radius: 100px;
        transition: width 0.4s ease;
        position: relative;
        overflow: hidden;
    }

    .loader-progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loaderShimmer 1.5s infinite;
    }

    @keyframes loaderShimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    /* Load More Loader */
    #loadMoreLoader {
        display: none;
        margin-top: 20px;
    }

    .load-more-text {
        margin-top: 12px;
        font-weight: 600;
        color: var(--primary);
    }
    </style>

    <script src="<?= $assetBase ?>js/ai_loader.js" defer></script>

    <!-- SEO: JSON-LD Structured Data -->
    <?php
    // Prepare JSON-LD structured data
    $jsonLD = [
        "@context" => "https://schema.org",
        "@type" => "WebApplication",
        "name" => "Question Paper Generator",
        "description" => "AI-powered tool to create custom question papers",
        "url" => "https://" . $_SERVER['HTTP_HOST'] . "/questionPaperFromTopic/",
        "applicationCategory" => "EducationalApplication",
        "offers" => [
            [
                "@type" => "Offer",
                "priceCurrency" => "PKR",
                "price" => "0",
                "name" => "Free Plan"
            ]
        ]
    ];
    ?>
    <script type="application/ld+json">
    <?= json_encode($jsonLD, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
</head>
<body>
    <?php 
    $only_navbar = true;
    require __DIR__ . '/../header.php'; 
    ?>
    
    <?php
    require_once __DIR__ . '/../middleware/SubscriptionCheck.php';
    require_once __DIR__ . '/../services/CacheManager.php';
    require_once __DIR__ . '/../services/DatabaseQueryService.php';

    // Initialize services
    $cache = new CacheManager();
    $dbService = new DatabaseQueryService($conn, $cache);

    // Subscription Info
    $subscriptionStatus = getSubscriptionInfo();
    $isPremium = $subscriptionStatus && $subscriptionStatus['is_premium'];
    $userPlan = $subscriptionStatus ? $subscriptionStatus['plan_type'] : 'free';
    ?>

<main class="main-content">


<div class="hero-builder mb-4">
    <div class="container text-center animate-fade-up">
        <h1 class="hero-title">Create Custom Question Papers</h1>
        <p class="hero-subtitle">AI-Powered curriculum mapping and custom question paper generator. Empowering educators with precision-built academic resources.</p>
    </div>
</div>

<div class="container py-5 px-4 px-xl-5">
    <div class="row justify-content-center">
        <!-- Main Application Content -->
        <div class="col-lg-10 paper-builder-main-content">
            
            <!-- Professional Quick Guide -->
            <div class="pro-guide-container animate-fade-up">
                <div class="pro-guide-header">
                    <span class="pro-guide-badge">Getting Started</span>
                    <h3 class="pro-guide-title">How to use the Paper Generator</h3>
                </div>
                <div class="pro-guide-content">
                    <div class="pro-step">
                        <div class="pro-step-icon"><i class="fas fa-list-ol"></i></div>
                        <div class="pro-step-text">
                            <strong>1. Select Type</strong>
                            <p>Choose between MCQs, Short, or Long questions from the selector above.</p>
                        </div>
                    </div>
                    <div class="pro-step">
                        <div class="pro-step-icon"><i class="fas fa-search"></i></div>
                        <div class="pro-step-text">
                            <strong>2. Search Topic</strong>
                            <p>Search for your topic. Use "Suggest More Topics" if you need AI help.</p>
                        </div>
                    </div>
                    <div class="pro-step">
                        <div class="pro-step-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="pro-step-text">
                            <strong>3. Pick Topics</strong>
                            <p>Click the topics you want. You can mix and match from different types.</p>
                        </div>
                    </div>
                    <div class="pro-step">
                        <div class="pro-step-icon"><i class="fas fa-file-export"></i></div>
                        <div class="pro-step-text">
                            <strong>4. Finalize</strong>
                            <p>Review your selection and click "Generate Question Paper" to build it.</p>
                        </div>
                    </div>
                </div>
            </div>

            <br><br><br>

            <!-- Mode Selector -->
            <div class="text-center mb-5">
                <div class="mode-container animate-fade-up" role="tablist" aria-label="Question type selector">
                    <button class="mode-btn active" onclick="switchMode('mcqs')" id="btn-mcqs" role="tab" aria-selected="true" aria-controls="mcqs-content">
                        <i class="fas fa-layer-group" aria-hidden="true"></i> MCQs
                        <span class="badge" id="badge-mcqs" aria-label="MCQs count">0</span>
                    </button>
                    <button class="mode-btn" onclick="switchMode('short')" id="btn-short" role="tab" aria-selected="false" aria-controls="short-content">
                        <i class="fas fa-align-left" aria-hidden="true"></i> Short Questions
                        <span class="badge" id="badge-short" aria-label="Short questions count">0</span>
                    </button>
                    <button class="mode-btn" onclick="switchMode('long')" id="btn-long" role="tab" aria-selected="false" aria-controls="long-content">
                        <i class="fas fa-align-justify" aria-hidden="true"></i> Long Questions
                        <span class="badge" id="badge-long" aria-label="Long questions count">0</span>
                    </button>
                </div>
            </div>

            <br><br><br><br><br>
                <!-- Search Bar with Info -->
                <div class="search-section mb-5 animate-fade-up">
                    <div class="search-intro text-center mb-5">
                        <h3 class="fw-bold mb-2" style="color: #0f172a; font-family: 'Outfit', sans-serif;">Find Topics to Build From</h3>
                        <p class="text-muted small">Search for specific topics or let AI suggest related topics</p>
                    </div>
<br><br><br><br>
                    <!-- Search Bar -->
                    <form class="search-wrapper mb-4" id="searchForm" role="search" onsubmit="handleSearch(event)">
                        <div class="search-card">
                            <div class="search-input-wrapper">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="text" 
                                       id="topicSearch"
                                       class="search-input"
                                       placeholder="search MCQs topics"
                                       aria-label="Search topics"
                                       autocomplete="off"
                                       minlength="2">
                            </div>
                            <button type="submit" class="btn-search-main" id="btnSearchMain" aria-label="Search button">
                                Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Topics Discovery Box - hidden until search -->
                <div class="topics-discovery-box d-none" id="discoveryBox" role="region" aria-live="polite" aria-label="Search results">
                    <div class="results-header-section">
                        <div id="dynResultsHeader" class="results-header d-none" role="status">
                            <!-- Results counter injected here -->
                        </div>
                    </div>

                    <!-- Simple Search Loader (Matched with mcqs_topic.php) -->
                    <div id="inlineLoader">
                        <div class="honeycomb"> 
                           <div></div><div></div><div></div><div></div><div></div><div></div><div></div> 
                        </div>
                        <div class="loader-progress">
                            <div class="loader-progress-bar" id="loaderProgressBar"></div>
                        </div>
                        <div id="loaderText">Scanning Database...</div>
                    </div>

                    <div id="topicsGrid" class="topics-grid row g-3" role="list">
                        <!-- Results injected here -->
                    </div>

                    <!-- Load More Button -->
                    <div id="aiControl" class="text-center mt-5 d-none">
                        <div class="ad-placement-results mb-4">
                            
                        </div>
                        <button class="btn-ai-discovery" onclick="fetchAiTopics()" aria-label="Load more AI suggestions">
                            <span class="shimmer" aria-hidden="true"></span>
                            <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Suggest More Topics
                        </button>

                        <!-- Load More Loader (Matched with mcqs_topic.php) -->
                        <div id="loadMoreLoader">
                             <div class="loader-progress">
                                 <div class="loader-progress-bar" id="loadMoreProgressBar"></div>
                             </div>
                             <div class="load-more-text">Our AI is exploring the knowledge graph...</div>
                        </div>

                        <br>
                        <br>
                        <br>
                        <br><br>
                        <?= renderAd('banner', 'Results Section Banner', 'ad-results-banner') ?>
                    </div>
                </div>

                <?php include_once __DIR__ . '/../includes/ai_loader.php'; ?>


                <!-- Generate Section - shown when topics are selected -->
                <div id="generateSection" class="generate-section d-none mt-4">
                    <div class="generate-card">
                        <div class="generate-info">
                            <i class="fas fa-file-alt generate-icon" aria-hidden="true"></i>
                            <div>
                                <div class="generate-count"><span id="totalSelectedCount" aria-live="polite">0</span> Topics Selected</div>
                                <div class="generate-label">Ready to build your custom paper</div>
                            </div>
                        </div>
                        <div class="btn-wrapper generate-action-btn">
                          <button type="button" class="btn" id="generateBtn" onclick="this.classList.add('active'); finalizePaper()" aria-label="Generate Question Paper">
                            <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"></path>
                            </svg>
                            <div class="txt-wrapper">
                              <div class="txt-1">
                                <span class="btn-letter">G</span><span class="btn-letter">e</span><span class="btn-letter">n</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">a</span><span class="btn-letter">t</span><span class="btn-letter">e</span><span style="opacity: 0;" class="btn-letter" aria-hidden="true">-</span><span class="btn-letter">Q</span><span class="btn-letter">u</span><span class="btn-letter">e</span><span class="btn-letter">s</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">o</span><span class="btn-letter">n</span><span style="opacity: 0;" class="btn-letter" aria-hidden="true">-</span><span class="btn-letter">P</span><span class="btn-letter">a</span><span class="btn-letter">p</span><span class="btn-letter">e</span><span class="btn-letter">r</span>
                              </div>
                              <div class="txt-2">
                                <span class="btn-letter">G</span><span class="btn-letter">e</span><span class="btn-letter">n</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">a</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">n</span><span class="btn-letter">g</span><span style="opacity: 0;" class="btn-letter" aria-hidden="true">-</span><span class="btn-letter">Q</span><span class="btn-letter">u</span><span class="btn-letter">e</span><span class="btn-letter">s</span><span class="btn-letter">t</span><span class="btn-letter">i</span><span class="btn-letter">o</span><span class="btn-letter">n</span><span style="opacity: 0;" class="btn-letter" aria-hidden="true">-</span><span class="btn-letter">P</span><span class="btn-letter">a</span><span class="btn-letter">p</span><span class="btn-letter">e</span><span class="btn-letter">r</span><span class="btn-letter">.</span><span class="btn-letter">.</span><span class="btn-letter">.</span>
                              </div>
                            </div>
                          </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO Optimization Footer for Home Page (Matched with finalize_paper.php style) -->
        <article class="mt-5 text-center animate-fade-up seo-footer" style="max-width: 1000px; margin: 0 auto;">
            <h3 class="fw-bold mb-3">Intelligent Paper Generator for Educators</h3>
            <p class="text-muted">
                Empower your teaching workflow with our high-precision assessment toolkit. We craft test frameworks by syncing precise quantities for <strong>Multiple Choice (MCQs), Short Answer components, and Essay parameters</strong> that align perfectly with standard Board, ECAT, and MDCAT rubrics. 
            </p>
            <div class="badge-group">
                <span class="badge-seo"><i class="fas fa-magic"></i> AI-Powered</span>
                <span class="badge-seo"><i class="fas fa-bullseye"></i> Exam-aligned Difficulty</span>
                <span class="badge-seo"><i class="fas fa-calendar-check"></i> 2026 Curriculum Standard</span>
            </div>
        </article>
    </div>
</main>

<script>
'use strict';

// State
let currentMode = 'mcqs';
let selectedDesign = 1;
let isSearching = false;
let loaderProgressInterval; // For inlineLoader
const selectionMatrix = {
    mcqs: new Set(),
    short: new Set(),
    long: new Set()
};

// DOM Elements Cache
const els = {
    searchForm: document.getElementById('searchForm'),
    topicSearch: document.getElementById('topicSearch'),
    topicsGrid: document.getElementById('topicsGrid'),
    discoveryBox: document.getElementById('discoveryBox'),
    generateSection: document.getElementById('generateSection'),
    totalSelectedCount: document.getElementById('totalSelectedCount'),
    badges: {
        mcqs: document.getElementById('badge-mcqs'),
        short: document.getElementById('badge-short'),
        long: document.getElementById('badge-long')
    },
    inlineLoader: document.getElementById('inlineLoader'),
    loadMoreLoader: document.getElementById('loadMoreLoader'),
    aiControl: document.getElementById('aiControl')
};

/**
 * Show Inline Loader (Matched with mcqs_topic.php)
 */
function showLoader(title = 'Processing...', subtitle = '') {
    const loader = els.inlineLoader;
    const titleEl = document.getElementById('loaderText');
    const progressBar = document.getElementById('loaderProgressBar');
    const resultsGrid = els.topicsGrid;
    const headerDiv = document.getElementById('dynResultsHeader');
    
    if (loader) {
        if (titleEl) titleEl.textContent = title;
        loader.style.display = 'block';
        if (resultsGrid) resultsGrid.style.display = 'none';
        if (headerDiv) headerDiv.classList.add('d-none');
        
        if (progressBar) {
            progressBar.style.width = '0%';
            let progress = 0;
            if (loaderProgressInterval) clearInterval(loaderProgressInterval);
            loaderProgressInterval = setInterval(function() {
                progress += 5;
                if (progress >= 95) { progress = 95; clearInterval(loaderProgressInterval); }
                progressBar.style.width = progress + '%';
            }, 200);
        }
    }
}

/**
 * Hide Inline Loader
 */
function hideLoader() {
    if (els.inlineLoader) {
        els.inlineLoader.style.display = 'none';
    }
    if (els.topicsGrid) {
        els.topicsGrid.style.display = 'grid';
    }
    if (loaderProgressInterval) clearInterval(loaderProgressInterval);
}

// Validate DOM elements exist
if (!els.searchForm || !els.topicSearch) {
    console.error('Critical DOM elements not found');
}

// State Constants
const isPremium = <?= json_encode($isPremium) ?>;
const topicLimits = {
    mcqs: 7,
    short: 5,
    long: 3
};

const MODE_LABELS = {
    mcqs: 'MCQs',
    short: 'Short Questions',
    long: 'Long Questions'
};

/**
 * Sanitize text for safe HTML display
 */
function sanitizeText(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Switch between question types
 */
window.switchMode = (mode) => {
    if (!['mcqs', 'short', 'long'].includes(mode)) {
        console.warn('Invalid mode:', mode);
        return;
    }
    
    currentMode = mode;
    
    // Update active button
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });
    const activeBtn = document.getElementById(`btn-${mode}`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-selected', 'true');
    }

    // Update placeholder
    if (els.topicSearch) {
        els.topicSearch.placeholder = `search ${MODE_LABELS[mode]} topics`;
        els.topicSearch.value = '';
    }

    // Re-render grid with only selected topics for this mode
    if (els.topicsGrid) {
        els.topicsGrid.innerHTML = '';
        const selected = Array.from(selectionMatrix[mode]);
        if (selected.length > 0) {
            selected.forEach(t => renderTopicCard(t));
        }
    }

    // Hide AI controls
    if (els.aiControl) {
        els.aiControl.classList.add('d-none');
    }
};

/**
 * Handle search form submission
 */
function handleSearch(event) {
    if (event) {
        event.preventDefault();
    }
    
    const term = els.topicSearch ? els.topicSearch.value.trim() : '';
    
    if (term.length < 2) {
        showNotification('Please enter at least 2 characters.', 'error');
        return;
    }
    
    executeSearch(term);
}

/**
 * Show notification to user
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.setAttribute('role', 'alert');
    notification.textContent = message;
    
    // Add basic styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
        background: ${type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'error' ? '#721c24' : '#0c5460'};
        border: 1px solid ${type === 'error' ? '#f5c6cb' : '#bee5eb'};
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 4000);
}

/**
 * Execute topic search
 */
async function executeSearch(term) {
    if (isSearching) return; // Prevent duplicate searches
    
    isSearching = true;
    
    if (els.topicsGrid) {
        els.topicsGrid.innerHTML = '';
        els.topicsGrid.className = 'topic-list';
    }

    if (els.aiControl) {
        els.aiControl.classList.add('d-none');
    }

    // Show Loader
    showLoader('Searching Topics...', 'Deep search in progress.');

    // Find or create results-header
    let headerDiv = document.getElementById('dynResultsHeader');
    if (!headerDiv && els.discoveryBox) {
        headerDiv = document.createElement('div');
        headerDiv.id = 'dynResultsHeader';
        headerDiv.className = 'results-header';
        headerDiv.setAttribute('role', 'status');
        headerDiv.setAttribute('aria-live', 'polite');
        els.discoveryBox.insertBefore(headerDiv, els.topicsGrid);
    }

    if (els.discoveryBox) {
        els.discoveryBox.classList.remove('d-none');
    }

    try {
        const url = new URL('search_topics.php', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')));
        url.searchParams.append('search', term);
        url.searchParams.append('type[]', currentMode);
        
        const res = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();

        if (data.success && data.topics && data.topics.length > 0) {
            if (headerDiv) {
                headerDiv.classList.remove('d-none');
                headerDiv.textContent = `Found ${data.topics.length} topic${data.topics.length !== 1 ? 's' : ''} matching "${sanitizeText(term)}"`;
            }
            data.topics.forEach(t => renderTopicCard(t));
            if (els.aiControl) {
                els.aiControl.classList.remove('d-none');
            }
        } else {
            if (headerDiv) {
                headerDiv.classList.remove('d-none');
                headerDiv.textContent = `No topics found for "${sanitizeText(term)}"`;
            }
            if (els.topicsGrid) {
                const noTopicsDiv = document.createElement('div');
                noTopicsDiv.id = 'noTopicsHint';
                noTopicsDiv.style.cssText = 'grid-column: 1 / -1;';
                noTopicsDiv.innerHTML = `
                    <div style="font-size: 2rem; margin-bottom: 1rem;">🔍</div>
                    <p style="margin: 0; font-size: 0.95rem;">
                        No exact matches found.<br>
                        <strong>Tip:</strong> Try searching with different keywords or use "Suggest More Topics with AI" button.
                    </p>
                `;
                els.topicsGrid.appendChild(noTopicsDiv);
            }
            if (els.aiControl) {
                els.aiControl.classList.remove('d-none');
            }
        }
    } catch (e) {
        console.error('Search error:', e);
        showNotification('Search failed. Please try again.', 'error');
        if (headerDiv) {
            headerDiv.classList.remove('d-none');
            headerDiv.textContent = 'Search failed. Please try again.';
        }
    } finally {
        isSearching = false;
        hideLoader();
    }
}

/**
 * Fetch additional topics from AI
 */
async function fetchAiTopics() {
    const term = els.topicSearch ? els.topicSearch.value.trim() : '';
    if (!term) {
        showNotification('Please enter a search term first.', 'error');
        return;
    }

    // Get currently displayed topics to exclude them
    const displayedTopics = els.topicsGrid ? 
        Array.from(els.topicsGrid.querySelectorAll('.topic-name')).map(el => el.textContent) : 
        [];

    const loader = els.loadMoreLoader;
    const progressBar = document.getElementById('loadMoreProgressBar');
    const aiControlBtn = document.querySelector('.btn-ai-discovery');

    if (loader) {
        loader.style.display = 'block';
    }
    if (aiControlBtn) {
        aiControlBtn.style.display = 'none';
    }

    let width = 0;
    const interval = setInterval(() => {
        width = Math.min(width + 5, 90);
        if (progressBar) progressBar.style.width = width + '%';
    }, 300);

    try {
        const url = new URL('fetch_more_topics.php', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')));
        url.searchParams.append('search', term);
        url.searchParams.append('type[]', currentMode);
        
        displayedTopics.forEach(t => {
            url.searchParams.append('exclude[]', t);
        });

        const res = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();

        if (data.success && data.topics && data.topics.length > 0) {
            if (progressBar) progressBar.style.width = '100%';
            data.topics.forEach(t => renderTopicCard(t));
            showNotification(`Found ${data.topics.length} additional AI-suggested topics!`, 'info');
        } else {
            showNotification('No additional topics found. Try a different search term.', 'info');
        }

    } catch (e) {
        console.error('AI fetch error:', e);
        showNotification('Failed to load AI suggestions. Please try again.', 'error');
    } finally {
        clearInterval(interval);
        setTimeout(() => {
            if (loader) loader.style.display = 'none';
            if (aiControlBtn) aiControlBtn.style.display = 'inline-block';
        }, 500);
    }
}

/**
 * Render topic card
 */
function renderTopicCard(topic) {
    if (!els.topicsGrid || !topic) return;
    
    // Check if already rendered
    const existing = Array.from(els.topicsGrid.querySelectorAll('.topic-name'))
        .find(el => el.textContent === topic);
    if (existing) return;

    const isSelected = selectionMatrix[currentMode].has(topic);
    
    const item = document.createElement('div');
    item.className = `topic-item ${isSelected ? 'selected' : ''}`;
    item.setAttribute('role', 'listitem');

    // Create safe event handler using data attribute
    item.dataset.topic = topic;

    // Information section
    const infoDiv = document.createElement('div');
    infoDiv.className = 'topic-info';
    
    const nameDiv = document.createElement('div');
    nameDiv.className = 'topic-name';
    nameDiv.textContent = topic; // Safe - uses textContent
    
    infoDiv.appendChild(nameDiv);

    // Controls section
    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'd-flex align-items-center gap-2';

    const similarityDiv = document.createElement('div');
    similarityDiv.className = 'topic-similarity';
    similarityDiv.textContent = '100% match';
    controlsDiv.appendChild(similarityDiv);

    if (isSelected) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-remove-lite';
        removeBtn.setAttribute('aria-label', `Remove ${topic}`);
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleTopic(topic, item);
        });
        controlsDiv.appendChild(removeBtn);
    }

    item.appendChild(infoDiv);
    item.appendChild(controlsDiv);
    
    // Add click event
    item.addEventListener('click', () => {
        toggleTopic(topic, item);
    });

    els.topicsGrid.appendChild(item);
}

/**
 * Toggle topic selection
 */
window.toggleTopic = (topic, card) => {
    const set = selectionMatrix[currentMode];
    
    if (set.has(topic)) {
        set.delete(topic);
        if (card) {
            card.classList.remove('selected');
            const rmBtn = card.querySelector('.btn-remove-lite');
            if (rmBtn) rmBtn.remove();
        }
    } else {
        // Check limits for free users
        if (!isPremium) {
            if (set.size >= topicLimits[currentMode]) {
                showUpgradeModal();
                return;
            }
        }
        set.add(topic);
        if (card) {
            card.classList.add('selected');
            // Add remove button
            const controls = card.querySelector('.d-flex');
            if (controls) {
                const rmBtn = document.createElement('button');
                rmBtn.type = 'button';
                rmBtn.className = 'btn-remove-lite';
                rmBtn.setAttribute('aria-label', `Remove ${topic}`);
                rmBtn.textContent = '×';
                rmBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleTopic(topic, card);
                });
                controls.appendChild(rmBtn);
            }
        }
    }
    updateCounts();
};

/**
 * Update selection counts
 */
function updateCounts() {
    const mcqs  = selectionMatrix.mcqs.size;
    const short = selectionMatrix.short.size;
    const long  = selectionMatrix.long.size;
    const total = mcqs + short + long;

    if (els.badges.mcqs) els.badges.mcqs.textContent = mcqs;
    if (els.badges.short) els.badges.short.textContent = short;
    if (els.badges.long) els.badges.long.textContent = long;
    if (els.totalSelectedCount) els.totalSelectedCount.textContent = total;

    if (els.generateSection) {
        if (total > 0) {
            els.generateSection.classList.remove('d-none');
        } else {
            els.generateSection.classList.add('d-none');
        }
    }
}

/**
 * Finalize paper generation
 */
window.finalizePaper = () => {
    const allTopics = new Set([
        ...selectionMatrix.mcqs,
        ...selectionMatrix.short,
        ...selectionMatrix.long
    ]);

    if (allTopics.size === 0) {
        showNotification('Please select at least one topic.', 'error');
        return;
    }

    // Show AI Step Loader (Matched with mcqs_topic.php)
    if (typeof showAILoader === 'function') {
        showAILoader(
            [
                { label: 'Analyzing topics',       duration: 3500 },
                { label: 'Extracting key concepts', duration: 3500 },
                { label: 'Designing Questions',     duration: 3500 },
                { label: 'Validating curriculum',   duration: 3500 },
                { label: 'Finalizing paper',        duration: 3500 }
            ],
            'Our AI is synthesizing questions based on 2026 board standards\u2026'
        );
    }

    const mcqs  = selectionMatrix.mcqs.size;
    const short = selectionMatrix.short.size;
    const long  = selectionMatrix.long.size;

    let finalAction = 'finalize_paper.php'; // Default

    if (mcqs > 0 && short === 0 && long === 0) {
        finalAction = 'online-mcqs-question-paper-generator';
    } else if (mcqs === 0 && short > 0 && long === 0) {
        finalAction = 'online-short-question-paper-generator';
    } else if (mcqs === 0 && short === 0 && long > 0) {
        finalAction = 'online-long-question-paper-generator';
    } else if (mcqs > 0 || short > 0 || long > 0) {
        // Any combination of more than one type or all three
        finalAction = 'online-mcqs-short-and-long-question-paper-generator';
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = finalAction;

    ['mcqs', 'short', 'long'].forEach(type => {
        selectionMatrix[type].forEach(t => {
            // Create typed input
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `topics_${type}[]`;
            inp.value = t;
            form.appendChild(inp);

            // Legacy input for backward compatibility
            const legacy = document.createElement('input');
            legacy.type = 'hidden';
            legacy.name = 'topics[]';
            legacy.value = t;
            form.appendChild(legacy);
        });

        // Add type indicator if topics exist for this type
        if (selectionMatrix[type].size > 0) {
            const typeInp = document.createElement('input');
            typeInp.type = 'hidden';
            typeInp.name = 'active_types[]';
            typeInp.value = type;
            form.appendChild(typeInp);
        }
    });

    // Add header design
    const designInp = document.createElement('input');
    designInp.type = 'hidden';
    designInp.name = 'header_design';
    designInp.value = selectedDesign;
    form.appendChild(designInp);

    document.body.appendChild(form);
    
    // Delay submission slightly to allow loader animation to start
    setTimeout(() => {
        form.submit();
    }, 500);
};

/**
 * Show upgrade modal
 */
function showUpgradeModal() {
    if (typeof showGlobalUpgradeModal === 'function') {
        showGlobalUpgradeModal('topics');
    } else {
        showNotification('Limit reached! Please upgrade your plan to add more topics.', 'error');
    }
}

/**
 * Initialize
 */
document.addEventListener('DOMContentLoaded', () => {
    updateCounts();
    
    // Setup enter key on search input
    if (els.topicSearch) {
        els.topicSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleSearch(null);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
