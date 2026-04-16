<?php
session_start();
require_once __DIR__ . '/../config/env.php';

$appName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
$pageTitle       = "Free Online Question Paper Generator | MCQs, Short & Long Questions – " . $appName;
$metaDescription = "Generate MCQ question papers, short question papers, and long question papers online for free. Instantly create exam-ready papers by topic for 9th, 10th, GCSE, and university students using AI.";
$metaKeywords    = "online question paper generator, online MCQs paper generator, MCQ generator by topic, generate exam questions online, AI paper generator, question paper maker, free paper generator, 9th class MCQs, short question paper generator, long question paper generator, GCSE question generator, quiz maker AI";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary SEO -->
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords"    content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="robots"      content="index, follow">
    <meta name="author"      content="<?= htmlspecialchars($appName) ?>">

    <!-- Open Graph (Social sharing) -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:url"         content="https://<?= $_SERVER['HTTP_HOST'] ?>/questionPaperFromTopic/">
    <meta property="og:site_name"   content="<?= htmlspecialchars($appName) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary">
    <meta name="twitter:title"       content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">

    <?php
    $only_head = true;
    $skip_shell = true;
    require __DIR__ . '/../header.php';
    ?>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= $assetBase ?>css/mcqs_topic.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>css/ai_loader.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
    /* Universal Box Sizing to prevent layout overflow */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    /* Mode Switcher specific to Generator */
    .qp-modes {
        display: flex;
        flex-wrap: wrap;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 4px;
        gap: 4px;
        margin-bottom: 24px;
    }
    .qp-mode-btn {
        flex: 1 1 30%;
        min-width: 100px;
        border: none;
        background: transparent;
        padding: 12px 10px;
        border-radius: var(--radius-sm);
        font-family: 'Outfit',sans-serif;
        font-weight: 600;
        font-size: .95rem;
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    .qp-mode-btn:hover { color: var(--text-main); background: rgba(99,102,241,.06); }
    .qp-mode-btn.active {
        background: var(--primary);
        color: #FFF;
        box-shadow: 0 4px 12px rgba(99,102,241,.30);
    }
    .qp-mode-count {
        font-size: .75rem;
        font-weight: 700;
        min-width: 20px;
        padding: 2px 6px;
        border-radius: 100px;
        background: rgba(0,0,0,.08);
        text-align: center;
    }
    .qp-mode-btn.active .qp-mode-count { background: rgba(255,255,255,.25); }

    .qp-hidden { display: none !important; }

    /* Selected topics styling tags */
    .selected-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eef2ff;
        color: var(--primary-dark);
        padding: 6px 12px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 600;
        margin: 4px;
        border: 1px solid #c7d2fe;
    }
    .selected-tag.short-tag { background: #fef3c7; color: #b45309; border-color: #fde68a; }
    .selected-tag.long-tag { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    
    .remove-tag-btn {
        background: transparent;
        border: none;
        color: currentColor;
        cursor: pointer;
        font-size: 1.1rem;
        line-height: 1;
        padding: 0;
        opacity: 0.7;
    }
    .remove-tag-btn:hover { opacity: 1; text-decoration: none; }

    /* Toast Notification */
    .qp-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 14px 24px;
        border-radius: var(--radius-sm);
        z-index: 9999;
        font-weight: 600;
        font-size: .9rem;
        max-width: 340px;
        box-shadow: var(--shadow-lg);
        animation: slideIn .3s ease, slideOut .3s ease 3.7s forwards;
    }
    .qp-toast-error { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }
    .qp-toast-info  { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }

    @keyframes slideIn   { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes slideOut  { to{opacity:0;transform:translateX(40px)} }

    /* Custom Generate Button Styling */
    .professional-generate-btn {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: white;
        border: none;
        width: 100%;
        max-width: 400px;
        padding: 14px 20px;
        border-radius: var(--radius-md);
        font-size: 1.1rem;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4), 0 8px 10px -6px rgba(99, 102, 241, 0.1);
        margin: 0 auto;
    }
    
    @media (max-width: 768px) {
        .professional-generate-btn {
            font-size: 1rem;
            padding: 12px 16px;
        }
        .qp-modes {
            padding: 2px;
        }
        .qp-mode-btn {
            font-size: 0.85rem;
            padding: 10px 6px;
            min-width: 90px;
        }
    }
    
    /* Fix header overlap and mobile container sizing */
    .main-content {
        padding-top: 100px; /* Accounts for the fixed header */
        min-height: 100vh;
    }
    
    .topic-search-container {
        max-width: 800px;
        width: 100% !important;
        margin: 0 auto 40px auto !important;
    }
    
    .search-input {
        padding-right: 65px !important; /* Fix excessive padding */
    }

    @media (max-width: 768px) {
        .main-content {
            padding-top: 85px;
        }
        .topic-search-container {
            max-width: 95% !important;
            padding: 24px 16px !important;
            border-radius: 20px !important;
            margin: 0 auto 32px auto !important;
        }
        h1 {
            font-size: 1.8rem !important;
            line-height: 1.2 !important;
            margin-bottom: 12px !important;
        }
        .desc {
            font-size: 0.95rem !important;
            margin-bottom: 24px !important;
        }
        .search-input {
            padding-right: 55px !important;
        }
        .search-btn {
            width: 44px !important;
            height: 44px !important;
            right: 4px !important;
        }
    }
    
    .professional-generate-btn:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 20px 30px -10px rgba(99, 102, 241, 0.5), 0 10px 15px -8px rgba(99, 102, 241, 0.2);
    }
    
    .professional-generate-btn:active:not(:disabled) {
        transform: translateY(1px);
        box-shadow: 0 5px 10px -3px rgba(99, 102, 241, 0.3);
    }

    .professional-generate-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        box-shadow: none;
        color: #f8fafc;
    }
    </style>

    <!-- Schema.org JSON-LD -->
    <?php
    $host = 'https://' . $_SERVER['HTTP_HOST'];
    $jsonLD = [
        "@context"          => "https://schema.org",
        "@type"             => "WebApplication",
        "name"              => "Online Question Paper Generator – " . $appName,
        "alternateName"     => ["Online MCQs Paper Generator","Free Paper Maker","MCQ Generator Online"],
        "description"       => "Generate MCQ question papers, short answer papers, and long question papers online instantly using AI. Free for students and educators.",
        "url"               => $host . "/questionPaperFromTopic/",
        "applicationCategory" => "EducationalApplication",
        "operatingSystem"   => "Any"
    ];
    ?>
    <script type="application/ld+json">
    <?= json_encode($jsonLD, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>

    <script src="<?= $assetBase ?>js/ai_loader.js" defer></script>
</head>
<body>
<?php
$only_navbar = true;
require __DIR__ . '/../header.php';

require_once __DIR__ . '/../middleware/SubscriptionCheck.php';
require_once __DIR__ . '/../services/CacheManager.php';
require_once __DIR__ . '/../services/DatabaseQueryService.php';

$subscriptionStatus = getSubscriptionInfo();
$isPremium    = $subscriptionStatus && $subscriptionStatus['is_premium'];
$userPlan     = $subscriptionStatus ? $subscriptionStatus['plan_type'] : 'free';
?>

<div class="main-content">
    <div class="topic-search-container">
        <!-- TOP AD BANNER -->
        <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>

        <h1>Create Question Papers</h1>
        <p class="desc">
            The fastest online MCQs, short, and long question paper maker. Search any topic, pick question types, and get an exam-ready paper in seconds.
        </p>

        <!-- MODE SWITCHER -->
        <div class="qp-modes" role="tablist" aria-label="Select question type">
            <button class="qp-mode-btn active" id="btn-mcqs" onclick="switchMode('mcqs')" role="tab" aria-selected="true">
                <i class="fas fa-list-ul"></i> MCQs
                <span class="qp-mode-count" id="badge-mcqs">0</span>
            </button>
            <button class="qp-mode-btn" id="btn-short" onclick="switchMode('short')" role="tab" aria-selected="false">
                <i class="fas fa-align-left"></i> Short Q's
                <span class="qp-mode-count" id="badge-short">0</span>
            </button>
            <button class="qp-mode-btn" id="btn-long" onclick="switchMode('long')" role="tab" aria-selected="false">
                <i class="fas fa-align-justify"></i> Long Q's
                <span class="qp-mode-count" id="badge-long">0</span>
            </button>
        </div>

        <!-- SEARCH SECTION -->
        <div class="search-section">
            <form id="searchForm" role="search" onsubmit="handleSearch(event)">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="topic_search" 
                        id="topicSearch"
                        class="search-input" 
                        placeholder="Search topics, chapters, subjects..." 
                        autofocus
                        autocomplete="off"
                        minlength="2"
                    >
                    <button type="submit" class="search-btn" title="Search Topics">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- SELECTED TOPICS CONTAINER -->
        <div class="selected-topics-section empty" id="selectedTopicsSection">
            <div class="selected-topics-header">
                <div class="selected-topics-title">Your Selection (<span id="totalSelectedCount">0</span>)</div>
                <button type="button" class="btn-secondary btn-clear-all" onclick="clearAllTopics()">
                    <i class="fas fa-trash-alt"></i> Clear All
                </button>
            </div>
            
            <div class="selected-topics-list" id="selectedTopicsList">
                 <div class="no-selection-hint">
                    Your list is currently empty. Start by searching and selecting topics below.
                </div>
            </div>
            
            <div class="quiz-config-section" style="margin-top: 32px;">
                <button type="button" class="professional-generate-btn" id="generateBtn" onclick="finalizePaper()" disabled>
                    <i class="fas fa-file-signature"></i> Generate Paper
                </button>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/ai_loader.php'; ?>

        <!-- Loader -->
        <div id="inlineLoader">
            <div class="honeycomb"> 
               <div></div><div></div><div></div><div></div><div></div><div></div><div></div> 
            </div>
            <div class="loader-progress">
                <div class="loader-progress-bar" id="loaderProgressBar"></div>
            </div>
            <div id="loaderText" style="color: var(--primary); font-weight: 700; margin-top: 15px;">Scanning Database...</div>
        </div>

        <!-- RESULTS SECTION -->
        <div class="results-section qp-hidden" id="resultsSection">
            <div class="results-header" id="dynResultsHeader"></div>
            
            <div class="topics-scroll-container">
                <div class="topic-list" id="topicsGrid"></div>
            </div>
            
            <div class="load-more-btn-container" id="aiControl">
                 <div id="loadMoreLoader" style="display:none;">
                     <div class="loader-progress">
                         <div class="loader-progress-bar" id="loadMoreProgressBar"></div>
                     </div>
                     <div class="load-more-text" style="text-align: center; font-weight: 600; color: var(--primary); margin-top: 10px;">Our AI is exploring the knowledge graph...</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 16px; width: 100%;">
                    <button type="button" id="loadMoreTopicsBtn" onclick="fetchAiTopics()" class="btn-secondary" style="width: 100%; max-width: 400px; padding: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-magic"></i> Explore More Topics (AI)
                    </button>
                    
                    <button type="button" class="professional-generate-btn" onclick="finalizePaper()" disabled>
                        <i class="fas fa-file-signature"></i> Generate Paper
                    </button>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
'use strict';

// ================================================================
// STATE
// ================================================================
let currentMode = 'mcqs';
let selectedDesign = 1;
let isSearching    = false;
let loaderInterval;

const selectionMatrix = { mcqs: new Set(), short: new Set(), long: new Set() };

const els = {
    topicSearch:       document.getElementById('topicSearch'),
    topicsGrid:        document.getElementById('topicsGrid'),
    resultsSection:    document.getElementById('resultsSection'),
    totalSelectedCount:document.getElementById('totalSelectedCount'),
    selectedSection:   document.getElementById('selectedTopicsSection'),
    selectedList:      document.getElementById('selectedTopicsList'),
    badges: {
        mcqs:  document.getElementById('badge-mcqs'),
        short: document.getElementById('badge-short'),
        long:  document.getElementById('badge-long')
    },
    inlineLoader:   document.getElementById('inlineLoader'),
    loadMoreLoader: document.getElementById('loadMoreLoader'),
    aiControl:      document.getElementById('aiControl')
};

const isPremium   = <?= json_encode($isPremium) ?>;
const topicLimits = { mcqs:7, short:5, long:3 };
const MODE_LABELS = { mcqs:'MCQs', short:'Short Questions', long:'Long Questions' };

// ================================================================
// UTILITIES
// ================================================================
function sanitize(text){
    const d=document.createElement('div');
    d.textContent=text;
    return d.innerHTML;
}

function toast(msg, type='info'){
    const el=document.createElement('div');
    el.className=`qp-toast qp-toast-${type}`;
    el.setAttribute('role','alert');
    el.textContent=msg;
    document.body.appendChild(el);
    setTimeout(()=>el.remove(),4300);
}

// ================================================================
// LOADER
// ================================================================
function showLoader(title='Processing…'){
    const loader = els.inlineLoader;
    const txtEl  = document.getElementById('loaderText');
    const bar    = document.getElementById('loaderProgressBar');
    const grid   = els.topicsGrid;
    const header = document.getElementById('dynResultsHeader');

    if(loader){
        if(txtEl)   txtEl.textContent = title;
        loader.style.display = 'block';
        if(grid)   grid.style.display   = 'none';
        if(els.resultsSection) els.resultsSection.classList.add('qp-hidden');

        if(bar){
            bar.style.width = '0%';
            let p=0;
            if(loaderInterval) clearInterval(loaderInterval);
            loaderInterval = setInterval(()=>{
                p+=5; if(p>=95){ p=95; clearInterval(loaderInterval); }
                bar.style.width = p+'%';
            },180);
        }
    }
}

function hideLoader(){
    if(els.inlineLoader) els.inlineLoader.style.display='none';
    if(els.topicsGrid)   els.topicsGrid.style.display='grid';
    if(loaderInterval)   clearInterval(loaderInterval);
}

// ================================================================
// MODE SWITCHING
// ================================================================
window.switchMode = (mode)=>{
    if(!['mcqs','short','long'].includes(mode)) return;
    currentMode = mode;

    document.querySelectorAll('.qp-mode-btn').forEach(b=>{
        b.classList.remove('active');
        b.setAttribute('aria-selected','false');
    });
    const ab = document.getElementById('btn-'+mode);
    if(ab){ ab.classList.add('active'); ab.setAttribute('aria-selected','true'); }

    if(els.topicSearch){
        els.topicSearch.placeholder = `Search ${MODE_LABELS[mode]} topics…`;
        els.topicSearch.value = '';
    }

    if(els.topicsGrid && els.resultsSection && !els.resultsSection.classList.contains('qp-hidden')){
       // if we want to clear or auto-reload it's here
       els.resultsSection.classList.add('qp-hidden');
    }
    
    // Refresh the topic list rendering for the selected state highlights
    if(els.topicsGrid) {
        Array.from(els.topicsGrid.children).forEach(card => {
            const t = card.dataset.topic;
            if(selectionMatrix[currentMode].has(t)) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }
};

// ================================================================
// SEARCH
// ================================================================
function handleSearch(event){
    if(event) event.preventDefault();
    const term = els.topicSearch ? els.topicSearch.value.trim() : '';
    if(term.length<2){ toast('Please enter at least 2 characters.','error'); return; }
    executeSearch(term);
}

async function executeSearch(term){
    if(isSearching) return;
    isSearching=true;

    if(els.topicsGrid){ els.topicsGrid.innerHTML=''; }
    if(els.aiControl)  els.aiControl.classList.add('qp-hidden');

    showLoader('Searching topics…');

    let headerDiv = document.getElementById('dynResultsHeader');
    if(els.resultsSection) els.resultsSection.classList.remove('qp-hidden');

    try{
        const base = window.location.origin + window.location.pathname.substring(0,window.location.pathname.lastIndexOf('/'));
        const url  = new URL('search_topics.php', base+'/');
        url.searchParams.append('search', term);
        url.searchParams.append('type[]', currentMode);

        const res  = await fetch(url.toString(),{ headers:{'X-Requested-With':'XMLHttpRequest'} });
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();

        if(data.success && data.topics && data.topics.length>0){
            if(headerDiv){
                headerDiv.innerHTML=`<i class="fas fa-poll-h" style="color: var(--primary);"></i> Top Matches (${data.topics.length})`;
            }
            data.topics.forEach(t=>renderTopicCard(t));
            if(els.aiControl) els.aiControl.classList.remove('qp-hidden');
        } else {
            if(headerDiv){ headerDiv.innerHTML=`<i class="fas fa-search-minus" style="color: var(--secondary);"></i> No local matches found`; }
            if(els.topicsGrid){
                const d=document.createElement('div');
                d.style.textAlign = 'center';
                d.innerHTML=`<div style="font-size: 2.2rem; margin-bottom: 10px;">🔍</div><p style="color: var(--text-muted);">No exact matches found.<br><strong>Tip:</strong> Try different keywords or use "Explore More Topics (AI)" below.</p>`;
                d.style.gridColumn = '1 / -1';
                els.topicsGrid.appendChild(d);
            }
            if(els.aiControl) els.aiControl.classList.remove('qp-hidden');
        }
    } catch(e){
        console.error('Search error:',e);
        toast('Search failed. Please try again.','error');
        if(headerDiv){ headerDiv.innerText='Search failed. Please try again.'; }
    } finally {
        isSearching=false;
        hideLoader();
    }
}

// ================================================================
// AI TOPICS
// ================================================================
async function fetchAiTopics(){
    const term = els.topicSearch ? els.topicSearch.value.trim() : '';
    if(!term){ toast('Please enter a search term first.','error'); return; }

    const displayed = els.topicsGrid ?
        Array.from(els.topicsGrid.querySelectorAll('.topic-name')).map(e=>e.textContent) : [];

    const loader  = els.loadMoreLoader;
    const bar     = document.getElementById('loadMoreProgressBar');
    const aiBtn   = document.getElementById('loadMoreTopicsBtn');

    if(loader) loader.style.display='block';
    if(aiBtn)  aiBtn.style.display='none';

    let w=0;
    const iv=setInterval(()=>{ w=Math.min(w+5,90); if(bar) bar.style.width=w+'%'; },300);

    try{
        const base = window.location.origin + window.location.pathname.substring(0,window.location.pathname.lastIndexOf('/'));
        const url  = new URL('fetch_more_topics.php', base+'/');
        url.searchParams.append('search', term);
        url.searchParams.append('type[]', currentMode);
        displayed.forEach(t=>url.searchParams.append('exclude[]',t));

        const res  = await fetch(url.toString(),{ headers:{'X-Requested-With':'XMLHttpRequest'} });
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();

        if(data.success && data.topics && data.topics.length>0){
            if(bar) bar.style.width='100%';
            data.topics.forEach(t=>renderTopicCard(t));
            toast(`Found ${data.topics.length} additional AI-suggested topics!`,'info');
        } else {
            toast('No additional topics found. Try a different search term.','info');
        }
    } catch(e){
        console.error('AI fetch error:',e);
        toast('Failed to load AI suggestions. Please try again.','error');
    } finally {
        clearInterval(iv);
        setTimeout(()=>{ if(loader) loader.style.display='none'; if(aiBtn) aiBtn.style.display='flex'; },500);
    }
}

// ================================================================
// RENDER TOPIC CARD
// ================================================================
function renderTopicCard(topic){
    if(!els.topicsGrid || !topic) return;

    const exists = Array.from(els.topicsGrid.querySelectorAll('.topic-name')).find(e=>e.textContent===topic);
    if(exists) return;

    const isSelected = selectionMatrix[currentMode].has(topic);

    const card = document.createElement('div');
    card.className = `topic-item${isSelected?' selected':''}`;
    card.dataset.topic = topic;

    // Name
    const nameEl = document.createElement('div');
    nameEl.className='topic-name';
    nameEl.textContent=topic;

    const matchEl = document.createElement('div');
    matchEl.className='topic-similarity';
    matchEl.textContent='100% match';

    card.appendChild(nameEl);
    card.appendChild(matchEl);
    card.addEventListener('click',()=>toggleTopic(topic,card));
    
    els.topicsGrid.appendChild(card);
}

// ================================================================
// TOGGLE TOPIC
// ================================================================
window.toggleTopic = (topic, card)=>{
    const set=selectionMatrix[currentMode];

    if(set.has(topic)){
        set.delete(topic);
        if(card){
            card.classList.remove('selected');
        }   
    } else {
        if(!isPremium && set.size>=topicLimits[currentMode]){
            showUpgradeModal(); return;
        }
        set.add(topic);
        if(card){
            card.classList.add('selected');
        }
    }
    updateCounts();
    renderSelectedTopics();
};

window.removeTopicByType = (topic, type) => {
    selectionMatrix[type].delete(topic);
    
    if (currentMode === type && els.topicsGrid) {
        const card = els.topicsGrid.querySelector(`.topic-item[data-topic="${topic.replace(/"/g, '\\"')}"]`);
        if (card) card.classList.remove('selected');
    }
    
    updateCounts();
    renderSelectedTopics();
}

window.clearAllTopics = () => {
    selectionMatrix.mcqs.clear();
    selectionMatrix.short.clear();
    selectionMatrix.long.clear();
    
    if(els.topicsGrid) {
        Array.from(els.topicsGrid.children).forEach(card => card.classList.remove('selected'));
    }
    
    updateCounts();
    renderSelectedTopics();
}

// ================================================================
// UPDATE COUNTS & SELECTED LIST
// ================================================================
function updateCounts(){
    const m=selectionMatrix.mcqs.size, s=selectionMatrix.short.size, l=selectionMatrix.long.size;
    const total = m+s+l;
    
    if(els.badges.mcqs)  els.badges.mcqs.textContent=m;
    if(els.badges.short) els.badges.short.textContent=s;
    if(els.badges.long)  els.badges.long.textContent=l;
    
    if(els.totalSelectedCount) els.totalSelectedCount.textContent=total;
    
    const genBtns = document.querySelectorAll('.professional-generate-btn');
    genBtns.forEach(btn => btn.disabled = (total === 0));
    
    if(els.selectedSection) {
        if(total > 0) {
            els.selectedSection.classList.remove('empty');
        } else {
            els.selectedSection.classList.add('empty');
        }
    }
}

function renderSelectedTopics() {
    if(!els.selectedList) return;
    
    const m=selectionMatrix.mcqs.size, s=selectionMatrix.short.size, l=selectionMatrix.long.size;
    
    if(m + s + l === 0) {
        els.selectedList.innerHTML = `<div class="no-selection-hint">Your list is currently empty. Start by searching modules below.</div>`;
        return;
    }
    
    els.selectedList.innerHTML = '';
    
    // MCQs
    selectionMatrix.mcqs.forEach(t => {
        els.selectedList.innerHTML += `<div class="selected-tag"><span>(MCQ) ${sanitize(t)}</span> <button class="remove-tag-btn" onclick="removeTopicByType('${t.replace(/'/g, "\\'")}', 'mcqs')"><i class="fas fa-times"></i></button></div>`;
    });
    
    // Short
    selectionMatrix.short.forEach(t => {
        els.selectedList.innerHTML += `<div class="selected-tag short-tag"><span>(Short) ${sanitize(t)}</span> <button class="remove-tag-btn" onclick="removeTopicByType('${t.replace(/'/g, "\\'")}', 'short')"><i class="fas fa-times"></i></button></div>`;
    });
    
    // Long
    selectionMatrix.long.forEach(t => {
        els.selectedList.innerHTML += `<div class="selected-tag long-tag"><span>(Long) ${sanitize(t)}</span> <button class="remove-tag-btn" onclick="removeTopicByType('${t.replace(/'/g, "\\'")}', 'long')"><i class="fas fa-times"></i></button></div>`;
    });
}

// ================================================================
// FINALIZE PAPER
// ================================================================
window.finalizePaper = ()=>{
    const allTopics=[...selectionMatrix.mcqs,...selectionMatrix.short,...selectionMatrix.long];
    if(allTopics.length===0){ toast('Please select at least one topic.','error'); return; }

    const genBtns = document.querySelectorAll('.professional-generate-btn');
    genBtns.forEach(btn => btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...');

    if(typeof showAILoader==='function'){
        showAILoader([
            { label:'Analyzing topics',        duration:3500 },
            { label:'Extracting key concepts', duration:3500 },
            { label:'Designing Questions',     duration:3500 },
            { label:'Validating curriculum',   duration:3500 },
            { label:'Finalizing paper',        duration:3500 }
        ], 'Our AI is synthesizing questions based on board standards…');
    }

    const m=selectionMatrix.mcqs.size, s=selectionMatrix.short.size, l=selectionMatrix.long.size;
    let action='finalize_paper.php';
    if(m>0&&s===0&&l===0)          action='online-mcqs-question-paper-generator';
    else if(m===0&&s>0&&l===0)     action='online-short-question-paper-generator';
    else if(m===0&&s===0&&l>0)     action='online-long-question-paper-generator';
    else if(m>0||s>0||l>0)         action='online-mcqs-short-and-long-question-paper-generator';

    const form=document.createElement('form');
    form.method='POST'; form.action=action;

    ['mcqs','short','long'].forEach(type=>{
        selectionMatrix[type].forEach(t=>{
            const inp=document.createElement('input');
            inp.type='hidden'; inp.name=`topics_${type}[]`; inp.value=t;
            form.appendChild(inp);

            const leg=document.createElement('input');
            leg.type='hidden'; leg.name='topics[]'; leg.value=t;
            form.appendChild(leg);
        });
        if(selectionMatrix[type].size>0){
            const ti=document.createElement('input');
            ti.type='hidden'; ti.name='active_types[]'; ti.value=type;
            form.appendChild(ti);
        }
    });

    const di=document.createElement('input');
    di.type='hidden'; di.name='header_design'; di.value=selectedDesign;
    form.appendChild(di);

    document.body.appendChild(form);
    setTimeout(()=>form.submit(),500);
};

// ================================================================
// UPGRADE MODAL
// ================================================================
function showUpgradeModal(){
    if(typeof showGlobalUpgradeModal==='function') showGlobalUpgradeModal('topics');
    else toast('Limit reached! Upgrade your plan for more topics.','error');
}

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded',()=>{
    updateCounts();
    if(els.topicSearch){
        els.topicSearch.addEventListener('keypress',(e)=>{
            if(e.key==='Enter') handleSearch(null);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
