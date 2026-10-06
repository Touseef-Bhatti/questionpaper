<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/seo.php';
$appName = EnvLoader::get('APP_NAME', 'Ahmad Learning Hub');
$pageTitle       = "Online Question Paper & AI Exam Maker | " . $appName;
$metaDescription = "Free instant exam maker for all grades and subjects. Turn topics, PDFs, images or files into AI question papers with printable MCQs, short and long questions.";
$metaKeywords    = "online question paper, online question paper generator, instant exam maker, free question paper maker, AI question paper, AI exam paper generator, image to paper, image to question paper, PDF to paper, PDF question paper generator, any file to paper, online exam paper maker, MCQ paper maker, printable question paper PDF, all grades question paper";
$pageUrl         = alh_seo_absolute_url('/online-question-paper-generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
<?php // AdSense review: third-party ads disabled. include_once dirname(__DIR__) . '/includes/monetag_ads.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php alh_render_seo_head([
        'title' => $pageTitle,
        'description' => $metaDescription,
        'keywords' => $metaKeywords,
        'canonical' => $pageUrl,
        'page_type' => 'WebPage',
        'content_language' => 'en',
        'audience_type' => 'students, teachers, tutors, schools, colleges and universities worldwide',
        // header.php owns the title, description and keyword tags for this route.
        'include_title' => false,
        'include_description' => false,
        'include_keywords' => false,
    ]); ?>

    <?php
    $only_head = true;
    $skip_shell = true;
    require __DIR__ . '/../header.php';
    ?>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= $assetBase ?>css/mcqs_topic.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/css/mcqs_topic.css') ?>">
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

    /* SEO Article Section - Premium Blog Layout */
    .seo-blog-section.blog-layout {
        max-width: 100%;
        margin: 80px auto 40px auto;
        padding: 0 20px;
        font-family: 'Inter', sans-serif;
        color: #334155;
        line-height: 1.8;
    }

    .blog-container {
        background: #ffffff;
        padding: 60px;
        border-radius: 24px;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
        text-align: left;
    }

    .blog-header {
        margin-bottom: 40px;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 30px;
    }

    .blog-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .blog-meta {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .blog-meta .category {
        background: #eef2ff;
        color: #4f46e5;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .blog-meta .read-time {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .blog-content h2 {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        margin: 50px 0 25px;
        letter-spacing: -0.02em;
        border-left: 4px solid #6366f1;
        padding-left: 15px;
    }

    .blog-content h3 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        margin: 40px 0 20px;
        letter-spacing: -0.01em;
    }

    .blog-content p {
        margin-bottom: 24px;
        font-size: 1.1rem;
        color: #475569;
    }

    .blog-content .lead {
        font-size: 1.25rem;
        color: #475569;
        font-weight: 500;
        line-height: 1.6;
    }

    .blog-content strong {
        color: #0f172a;
    }

    .blog-content ul, 
    .blog-content ol {
        margin-bottom: 30px;
        padding-left: 25px;
    }

    .blog-content li {
        margin-bottom: 12px;
        font-size: 1.05rem;
        color: #475569;
    }

    /* Blockquote Style */
    .blog-quote {
        font-style: italic;
        font-size: 1.3rem;
        color: #4f46e5;
        border-left: none;
        padding: 30px;
        background: #f5f3ff;
        border-radius: 16px;
        margin: 40px 0;
        position: relative;
        text-align: center;
    }

    .blog-quote::before {
        content: '"';
        font-size: 4rem;
        position: absolute;
        top: -10px;
        left: 20px;
        opacity: 0.1;
        font-family: serif;
    }

    /* Featured Content Box */
    .blog-featured-box {
        background: #f8fafc;
        border-left: 5px solid #6366f1;
        padding: 30px;
        border-radius: 0 16px 16px 0;
        margin: 40px 0;
    }

    .blog-featured-box h4 {
        margin: 0 0 15px;
        font-size: 1.25rem;
        color: #1e293b;
        font-weight: 700;
    }

    .blog-featured-box ul {
        margin: 0;
        padding-left: 20px;
    }

    .blog-featured-box li {
        margin-bottom: 10px;
    }

    /* CTA Box */
    .blog-cta-box {
        margin-top: 60px;
        padding: 40px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border-radius: 20px;
        text-align: center;
    }

    .blog-cta-box h3 {
        color: white !important;
        margin-top: 0;
        font-size: 1.5rem;
    }

    .blog-cta-box p {
        margin-bottom: 0;
        opacity: 0.9;
        color: white;
    }

    /* SEO Cards Grid for Global Context */
    .seo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    .seo-card {
        padding: 24px;
        background: #f8fafc;
        border-radius: 16px;
        border: 1px solid #f1f5f9;
        transition: transform 0.3s ease;
    }
    .seo-card:hover {
        transform: translateY(-5px);
        background: #ffffff;
        box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05);
    }
    .seo-card i {
        font-size: 2rem;
        color: #4f46e5;
        margin-bottom: 16px;
        display: block;
    }
    .seo-card h4 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 12px;
    }

    .seo-toc {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 30px;
        padding: 16px 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }
    .seo-toc a {
        color: #4338ca;
        font-weight: 700;
        text-decoration: none;
    }
    .seo-toc a:hover,
    .seo-toc a:focus-visible {
        text-decoration: underline;
    }
    .answer-box {
        margin: 0 0 34px;
        padding: 28px 30px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 16px;
    }
    .answer-box h2 {
        margin-top: 0;
    }
    .answer-box ul {
        margin-bottom: 0;
    }
    .trust-note {
        padding: 18px 20px;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 12px;
        color: #78350f;
    }
    .seo-link-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin: 24px 0 36px;
    }
    .seo-link-grid a {
        display: block;
        padding: 16px;
        color: #3730a3;
        background: #ffffff;
        border: 1px solid #c7d2fe;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
    }
    .seo-link-grid a:hover,
    .seo-link-grid a:focus-visible {
        border-color: #6366f1;
        background: #eef2ff;
    }

    @media (max-width: 768px) {
        .seo-blog-section.blog-layout {
            margin: 40px auto;
        }
        
        .blog-container {
            padding: 30px 20px;
            border-radius: 0;
            box-shadow: none;
            border-left: none;
            border-right: none;
        }

        .blog-title {
            font-size: 1.8rem;
        }
        
        .blog-content h2 {
            font-size: 1.6rem;
        }

        .blog-content h3 {
            font-size: 1.4rem;
        }
    }
    </style>

    <?php
    $jsonLD = [
        "@context"          => "https://schema.org",
        "@type"             => "WebApplication",
        "@id"               => $pageUrl . "#application",
        "name"              => "Online Question Paper Generator - Ahmad Learning Hub",
        "alternateName"     => ["Instant exam maker", "Free question paper maker", "AI question paper", "PDF to paper maker", "Image to paper maker"],
        "description"       => $metaDescription,
        "url"               => $pageUrl,
        "applicationCategory" => "EducationalApplication",
        "operatingSystem"   => "Any",
        "audience"          => [
            "@type" => "EducationalAudience",
            "audienceType" => "students, teachers, tutors, schools, colleges and universities worldwide"
        ],
        "featureList" => [
            "Search topics and chapters to build an online question paper for any grade",
            "Convert supported PDF, DOCX, PPTX, TXT or image files into paper content",
            "Generate MCQs, short questions and long questions",
            "Choose easy, medium or hard difficulty",
            "Generate a printable PDF question paper instantly"
        ],
        "isPartOf"          => ["@id" => alh_seo_site_url() . "#website"],
        "publisher"         => ["@id" => alh_seo_site_url() . "#organization"]
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

        <h1>Online Question Paper &amp; Instant Exam Maker</h1>
        <p class="desc">
            Use our <strong>AI question paper maker</strong> to turn a topic, chapter, PDF, image or supported file into <strong>MCQs, short questions and long questions</strong>. Review the paper and download a printable PDF for tests, revision and exam preparation.
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
                    <button type="button" class="file-upload-btn" title="Upload File" onclick="checkLoginAndOpenUpload()">
                        <i class="fas fa-file-upload"></i>
                    </button>
                    <button type="submit" class="search-btn" title="Search Topics">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- FILE UPLOAD TRIGGER CARD -->
        <div class="text-upload-trigger" id="textUploadTrigger" onclick="checkLoginAndOpenUpload()">
            <div class="text-upload-trigger-icon">
                <i class="fas fa-file-upload"></i>
            </div>
            <div class="text-upload-trigger-content">
                <div class="text-upload-trigger-title">Upload a File & Generate Questions</div>
                <div class="text-upload-trigger-desc">PDF, DOCX, TXT, PPT/PPTX, or images (JPG/PNG) — generate MCQs, short & long questions from your file (max 10 MB)</div>
            </div>
            <div class="text-upload-trigger-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- FILE UPLOAD MODAL -->
        <div class="text-upload-modal" id="textUploadModal">
            <div class="text-upload-modal-card">
                <div class="text-upload-modal-header">
                    <div>
                        <h3 class="text-upload-modal-title"><i class="fas fa-magic"></i> AI File Analyzer</h3>
                        <p class="text-upload-modal-subtitle">Upload one file; questions are generated only from its content</p>
                    </div>
                    <button type="button" class="text-upload-close-btn" onclick="closeTextUploadModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="text-upload-modal-body">
                    <div class="text-upload-file-wrapper" id="documentUploadWrapper">
                        <label class="text-upload-file-label" for="documentUploadInput">
                            <i class="fas fa-paperclip"></i> Choose file
                        </label>
                        <input type="file" id="documentUploadInput" class="text-upload-file-input" name="document" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.webp,.gif,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/*">
                        <div class="text-upload-file-meta">
                            <span id="documentUploadFilename" class="text-upload-filename">No file selected</span>
                            <span id="documentUploadFileDetails" class="text-upload-file-details">Choose one file to begin.</span>
                            <span class="text-upload-hint" title="Legacy .doc/.ppt may need to be saved as PDF or DOCX/PPTX if upload fails">PDF, DOCX, TXT, PPT, PPTX, PNG, JPG, WEBP, GIF · max 10 MB</span>
                        </div>
                        <div class="text-upload-status" id="documentUploadStatus" role="status" aria-live="polite">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                <strong id="documentUploadStatusTitle">Ready when you are</strong>
                                <small id="documentUploadStatusText">Questions will be generated only from the selected file.</small>
                            </span>
                        </div>
                    </div>

                    <div class="text-upload-error" id="textUploadError" style="display:none;"></div>

                    <div class="text-upload-progress" id="textUploadProgress" style="display:none;">
                        <div class="text-upload-progress-bar-track">
                            <div class="text-upload-progress-bar" id="textProgressBar"></div>
                        </div>
                        <div class="text-upload-progress-text" id="textProgressText">Reading your file...</div>
                    </div>

                    <div class="text-upload-results" id="textUploadResults" style="display:none;"></div>
                </div>
                <div class="text-upload-modal-footer">
                    <button type="button" class="text-upload-cancel-btn" onclick="closeTextUploadModal()">Cancel</button>
                    <button type="button" class="text-upload-generate-btn" id="textGenerateBtn" onclick="generateFromFile()" disabled>
                        <i class="fas fa-magic"></i> Analyze selected file
                    </button>
                </div>
            </div>
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
                    <i class="fas fa-arrow-right"></i> Continue to Settings
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
                        <i class="fas fa-arrow-right"></i> Continue to Settings
                    </button>
                </div>
            </div>
        </div>
</div>
        <article class="seo-blog-section blog-layout" style="margin-top: 40px; margin-bottom: 0;" aria-labelledby="generator-guide-title">
            <div class="blog-container">
                <nav class="seo-toc" aria-label="Question paper generator guide">
                    <strong>On this page:</strong>
                    <a href="#about-generator">Create instantly</a>
                    <a href="#how-to-use">How it works</a>
                    <a href="#who-can-use">Use cases</a>
                    <a href="#generator-faq">FAQs</a>
                </nav>

                <section class="answer-box" id="about-generator" aria-labelledby="about-generator-title">
                    <h2 id="about-generator-title">Create a question paper instantly from any topic or file</h2>
                    <p>Build an <strong>online question paper</strong> in minutes with Ahmad Learning Hub. This <strong>instant exam maker</strong> helps teachers, tutors and students worldwide turn searchable topics, PDF notes, images and supported files into an AI-assisted question paper that can be reviewed and downloaded as a printable PDF.</p>
                    <ul>
                        <li><strong>Free question paper maker:</strong> Start with the available online paper-building workflow for class tests, practice and revision.</li>
                        <li><strong>AI question paper:</strong> Search topics or use AI suggestions to collect relevant question areas.</li>
                        <li><strong>PDF to paper and image to paper:</strong> Upload supported PDF, DOC/DOCX, PPT/PPTX, TXT or image notes.</li>
                        <li><strong>Any supported file to paper:</strong> Select MCQs, short questions, long questions and difficulty before PDF generation.</li>
                        <li><strong>Easy and clean:</strong> Follow the simple browser workflow from topic selection to printable paper.</li>
                        <li><strong>For every learner:</strong> Use it for primary, middle, high school, college, university and professional learning.</li>
                    </ul>
                </section>

                <header class="blog-header">
                    <h2 class="blog-title" id="generator-guide-title">Build an online question paper in five simple steps</h2>
                    <div class="blog-meta">
                        <span class="category">Teacher assessment tool</span>
                        <span class="read-time">Updated September 28, 2026</span>
                    </div>
                    <p class="lead">Create a free question paper draft without starting from a blank document. Search your subject, add the topics you want to assess, select the paper mix and review the final settings before generation.</p>
                </header>

                <section class="blog-content" id="how-to-use">
                    <div class="step-guide">
                        <div style="margin-bottom: 30px;">
                            <h3><span style="color: #10b981;">01.</span> Select a question type</h3>
                            <p>Choose <strong>MCQs</strong>, <strong>short questions</strong> or <strong>long questions</strong>. You can build a paper around one format or select topics for more than one section.</p>
                        </div>
                        <div style="margin-bottom: 30px;">
                            <h3><span style="color: #10b981;">02.</span> Search a topic or convert a file</h3>
                            <p>Search for a subject, chapter or topic. Or sign in and use the <strong>PDF to paper</strong>, <strong>image to paper</strong> and supported-file workflow with one PDF, DOC/DOCX, PPT/PPTX, TXT or image file up to 10 MB.</p>
                        </div>
                        <div style="margin-bottom: 30px;">
                            <h3><span style="color: #10b981;">03.</span> Add topics to your paper</h3>
                            <p>Review the matching topic cards and click the topics you want. The selected list shows how many MCQ, short and long-question topics are ready.</p>
                        </div>
                        <div style="margin-bottom: 30px;">
                            <h3><span style="color: #10b981;">04.</span> Add AI question suggestions</h3>
                            <p>Use <strong>Explore More Topics (AI)</strong> when a search needs broader suggestions. Check every AI-suggested topic before adding it to an assessment.</p>
                        </div>
                        <div style="margin-bottom: 30px;">
                            <h3><span style="color: #10b981;">05.</span> Generate the exam paper PDF</h3>
                            <p>Continue to settings, choose the quantity for each question type and select easy, medium or hard difficulty. Generate the question paper PDF, then review it before printing or sharing.</p>
                        </div>
                    </div>

                    <div class="blog-featured-box" id="who-can-use">
                        <h3>Instant exam maker for tests, worksheets and revision</h3>
                        <p>Use this online question paper maker for a chapter test, weekly quiz, revision worksheet, academy assessment, college assignment or broader practice paper. It is designed for all grades, subjects and curricula; review every AI-assisted paper against your own course requirements before formal use.</p>
                        <div class="seo-link-grid">
                            <a href="<?= $assetBase ?>class-9th-and-10th-online-question-paper-generator">Class 9 and 10 subject paper generator</a>
                            <a href="<?= $assetBase ?>class-11-and-12-online-question-paper-generator">Class 11 and 12 subject paper generator</a>
                            <a href="<?= $assetBase ?>class-9-and-10-online-mcqs-prepation-test">Online MCQs tests</a>
                            <a href="<?= $assetBase ?>study-material-for-board-exam-preparations">Study material and revision notes</a>
                        </div>
                    </div>

                    <h2>AI question paper maker features</h2>
                    <div class="seo-grid">
                        <div class="seo-card">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>
                            <h3>Online question paper builder</h3>
                            <p>Combine selected topics and chapters in one assessment instead of copying questions into a document manually.</p>
                        </div>
                        <div class="seo-card">
                            <i class="fas fa-file-upload" aria-hidden="true"></i>
                            <h3>PDF to paper and image to paper</h3>
                            <p>Upload supported PDF, Word, PowerPoint, text or image notes so the file-based workflow can prepare questions from your source.</p>
                        </div>
                        <div class="seo-card">
                            <i class="fas fa-sliders-h" aria-hidden="true"></i>
                            <h3>Free MCQ paper maker</h3>
                            <p>Set the number of MCQs, short questions and long questions, then choose an easy, medium or hard difficulty level.</p>
                        </div>
                        <div class="seo-card">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i>
                            <h3>Any supported file to paper</h3>
                            <p>Generate a paper you can inspect and download as a printable PDF for classroom use, revision or an academy test.</p>
                        </div>
                    </div>

                    <div class="trust-note">
                        <strong>Review before use:</strong> AI-assisted questions can contain errors or may not match the latest textbook, syllabus, curriculum or assessment instructions. A teacher or learner should check the wording, answer choices, difficulty and marks before using a paper in a formal assessment.
                    </div>

                    <h2 id="generator-faq">Frequently asked questions</h2>
                    <div class="blog-featured-box" style="background: #f1f5f9; border-left-color: #94a3b8;">
                        <details>
                            <summary><strong>Can I make a free question paper online?</strong></summary>
                            <p>Yes. You can start the online paper-building workflow for classroom tests, revision and practice, then review the available settings before generating a paper.</p>
                        </details>
                        <details>
                            <summary><strong>Can I convert a PDF or image to a question paper?</strong></summary>
                            <p>Yes. Sign in, upload a supported PDF or image up to 10 MB, and use the PDF-to-paper or image-to-paper workflow to prepare questions from its content.</p>
                        </details>
                        <details>
                            <summary><strong>Can I turn any file into an exam paper?</strong></summary>
                            <p>You can upload supported PDF, DOC/DOCX, PPT/PPTX, TXT and image files. The file-to-paper workflow prepares the source for MCQs, short questions and long questions.</p>
                        </details>
                        <details>
                            <summary><strong>Can AI create MCQs, short and long questions?</strong></summary>
                            <p>Yes. Select the question type, choose topics or upload source material, then set the quantity and difficulty before generating the AI-assisted paper.</p>
                        </details>
                        <details>
                            <summary><strong>Is this a Punjab Board question paper generator?</strong></summary>
                            <p>The main tool is a general topic and source-based generator for users worldwide. If you follow a specific board or curriculum, verify every paper against the current official syllabus and assessment pattern.</p>
                        </details>
                        <details>
                            <summary><strong>Can the instant exam maker create a printable PDF?</strong></summary>
                            <p>Yes. After you select topics, question quantities and difficulty, the next step generates a PDF that you can review and print.</p>
                        </details>
                        <details>
                            <summary><strong>Who can use this online question paper maker?</strong></summary>
                            <p>Teachers, tutors, academies, school staff and students can use it to prepare classroom tests, revision papers and practice assessments.</p>
                        </details>
                    </div>

                    <div class="blog-cta-box">
                        <h3>Build your next question paper</h3>
                        <p>Search a topic above, upload your study notes or turn your next lesson into a clean, printable question paper.</p>
                    </div>
                </section>
            </div>
        </article>

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

const isLoggedIn = <?= json_encode(isset($_SESSION['user_id'])) ?>;
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
    genBtns.forEach(btn => btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...');

    // ALWAYS go to finalize_paper.php (settings page) — never generate directly
    const form=document.createElement('form');
    form.method='POST'; form.action='finalize_paper.php';

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
    setTimeout(()=>form.submit(),300);
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
    document.getElementById('documentUploadInput')?.addEventListener('change', function() {
        updateSelectedUpload(this.files && this.files[0] ? this.files[0] : null);
    });
});

// ================================================================
// TEXT UPLOAD MODAL
// ================================================================
function checkLoginAndOpenUpload() {
    if (!isLoggedIn) {
        if (typeof showLoginModal === 'function') {
            showLoginModal();
        } else {
            window.location.href = '../login.php';
        }
        return;
    }
    openTextUploadModal();
}

function openTextUploadModal() {
    const modal = document.getElementById('textUploadModal');
    const fin = document.getElementById('documentUploadInput');
    if(fin) fin.value = '';
    resetUploadUi();
    if(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

const uploadAllowedExtensions = new Set(['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif']);

function formatUploadSize(bytes) {
    if (!Number.isFinite(bytes) || bytes < 1024) return `${bytes || 0} B`;
    const units = ['KB', 'MB', 'GB'];
    let size = bytes;
    let unit = -1;
    do {
        size /= 1024;
        unit++;
    } while (size >= 1024 && unit < units.length - 1);
    return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function setUploadStatus(kind, title, detail) {
    const status = document.getElementById('documentUploadStatus');
    const titleEl = document.getElementById('documentUploadStatusTitle');
    const detailEl = document.getElementById('documentUploadStatusText');
    if (!status) return;
    status.className = `text-upload-status${kind ? ` ${kind}` : ''}`;
    if (titleEl) titleEl.textContent = title;
    if (detailEl) detailEl.textContent = detail;
    const icon = status.querySelector('i');
    if (icon) {
        icon.className = kind === 'success' ? 'fas fa-check-circle' :
            kind === 'loading' ? 'fas fa-spinner fa-spin' :
            kind === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';
    }
}

function resetUploadUi() {
    const wrapper = document.getElementById('documentUploadWrapper');
    const filename = document.getElementById('documentUploadFilename');
    const details = document.getElementById('documentUploadFileDetails');
    const errorEl = document.getElementById('textUploadError');
    const generateBtn = document.getElementById('textGenerateBtn');
    if (wrapper) wrapper.classList.remove('has-file', 'has-error');
    if (filename) filename.textContent = 'No file selected';
    if (details) details.textContent = 'Choose one file to begin.';
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> Analyze selected file';
    }
    setUploadStatus('', 'Ready when you are', 'Questions will be generated only from the selected file.');
}

function updateSelectedUpload(file) {
    const wrapper = document.getElementById('documentUploadWrapper');
    const filename = document.getElementById('documentUploadFilename');
    const details = document.getElementById('documentUploadFileDetails');
    const errorEl = document.getElementById('textUploadError');
    const generateBtn = document.getElementById('textGenerateBtn');
    if (!file) {
        resetUploadUi();
        return;
    }

    const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
    const invalidExtension = !uploadAllowedExtensions.has(extension);
    const tooLarge = file.size > 10 * 1024 * 1024;
    if (filename) filename.textContent = file.name;
    if (details) details.textContent = `${extension ? extension.toUpperCase() : 'FILE'} · ${formatUploadSize(file.size)}`;
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }

    if (invalidExtension || tooLarge) {
        if (wrapper) {
            wrapper.classList.remove('has-file');
            wrapper.classList.add('has-error');
        }
        if (generateBtn) generateBtn.disabled = true;
        const message = invalidExtension
            ? 'This file type is not supported. Please choose PDF, DOC/DOCX, PPT/PPTX, TXT, or an image.'
            : 'File is too large. Maximum size is 10 MB.';
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
        setUploadStatus('error', 'File cannot be analyzed', message);
        return;
    }

    if (wrapper) {
        wrapper.classList.remove('has-error');
        wrapper.classList.add('has-file');
    }
    if (generateBtn) generateBtn.disabled = false;
    setUploadStatus('success', 'File selected successfully', 'Ready to upload and analyze. Questions will use only this file.');
}

function closeTextUploadModal() {
    const modal = document.getElementById('textUploadModal');
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }, 300);
    }
}

function adjustTextCount(inputId, delta) {
    const inp = document.getElementById(inputId);
    if(!inp) return;
    let val = parseInt(inp.value) || 1;
    val = Math.max(parseInt(inp.min)||1, Math.min(val + delta, parseInt(inp.max)||30));
    inp.value = val;
}

async function generateFromFile() {
    const fileInput = document.getElementById('documentUploadInput');
    const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    const errorEl = document.getElementById('textUploadError');
    const progressEl = document.getElementById('textUploadProgress');
    const progressBar = document.getElementById('textProgressBar');
    const progressText = document.getElementById('textProgressText');
    const resultsEl = document.getElementById('textUploadResults');
    const genBtn = document.getElementById('textGenerateBtn');

    errorEl.style.display = 'none';
    resultsEl.style.display = 'none';

    if(!file) {
        errorEl.textContent = 'Please choose a file to upload.';
        errorEl.style.display = 'block';
        setUploadStatus('error', 'Choose a file first', 'Select one supported file before starting the analysis.');
        return;
    }
    if(file.size > 10 * 1024 * 1024) {
        errorEl.textContent = 'File is too large. Maximum size is 10 MB.';
        errorEl.style.display = 'block';
        setUploadStatus('error', 'File cannot be analyzed', 'Maximum file size is 10 MB.');
        return;
    }

    const types = [];
    if(document.getElementById('textTypeMcqs')?.checked) types.push('mcqs');
    if(document.getElementById('textTypeShort')?.checked) types.push('short');
    if(document.getElementById('textTypeLong')?.checked) types.push('long');

    // The file-upload modal currently has no separate type checkboxes. A
    // question-paper upload should therefore analyze the file for every paper
    // section; the next settings step still controls the final counts.
    if(types.length === 0) {
        types.push('mcqs', 'short', 'long');
    }

    progressEl.style.display = 'block';
    genBtn.disabled = true;
    genBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading & analyzing...';
    setUploadStatus('loading', 'Uploading file…', 'Reading the file and detecting its topic.');

    const progressSteps = [
        'Reading your file...',
        'Extracting content...',
        'Detecting topic...',
        'Finalizing...'
    ];
    let stepIdx = 0;
    let pVal = 0;
    const pInterval = setInterval(() => {
        pVal = Math.min(pVal + 3, 92);
        if(progressBar) progressBar.style.width = pVal + '%';
        if(pVal % 25 === 0 && stepIdx < progressSteps.length - 1) {
            stepIdx++;
            if(progressText) progressText.textContent = progressSteps[stepIdx];
        }
    }, 400);

    try {
        const formData = new FormData();
        formData.append('document', file);
        formData.append('detect_topic_only', '1');
        formData.append('upload_category', 'question_paper');
        types.forEach(type => formData.append('question_types[]', type));

        const base = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const res = await fetch(base + '/generate_from_upload.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        clearInterval(pInterval);
        if(progressBar) progressBar.style.width = '100%';

        if(data.success) {
            progressEl.style.display = 'none';
            const topicLabel = (data.detected_topic && String(data.detected_topic).trim()) ? String(data.detected_topic).trim() : 'AI Generated from Upload';
            setUploadStatus('success', 'File uploaded successfully', `Content analyzed. Preparing questions from “${topicLabel}”.`);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'finalize_paper.php';

            const addHidden = (name, val) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = name; inp.value = val;
                form.appendChild(inp);
            };

            addHidden('topics[]', topicLabel);
            addHidden('topics_mcqs[]', topicLabel);
            addHidden('topics_short[]', topicLabel);
            addHidden('topics_long[]', topicLabel);
            addHidden('source', 'file_upload');
            if (data.file_hash) {
                addHidden('file_hash', data.file_hash);
            }

            document.body.appendChild(form);
            await new Promise(resolve => setTimeout(resolve, 450));
            form.submit();
        } else {
            progressEl.style.display = 'none';
            errorEl.textContent = 'Please try again.';
            errorEl.style.display = 'block';
            setUploadStatus('error', 'Please try again', 'The file could not be processed right now.');
        }
    } catch(e) {
        clearInterval(pInterval);
        progressEl.style.display = 'none';
        errorEl.textContent = 'Please try again.';
        errorEl.style.display = 'block';
        setUploadStatus('error', 'Please try again', 'The file could not be processed right now.');
        console.error('Analyze file error:', e);
    } finally {
        genBtn.disabled = false;
        genBtn.innerHTML = '<i class="fas fa-magic"></i> Analyze selected file';
    }
}

function displayTextResults(data, types) {
    const resultsEl = document.getElementById('textUploadResults');
    if(!resultsEl) return;

    let html = '<div class="text-results-header"><i class="fas fa-check-circle"></i> Questions Generated Successfully!</div>';
    if(data.detected_topic) {
        html += `<div class="text-preview-section" style="margin:10px 0;color:var(--text-muted);font-weight:600;">Topic: ${sanitize(data.detected_topic)}</div>`;
    }
    if(data.recheck_status === 'pending') {
        html += '<div class="text-preview-section" style="margin:8px 0;font-size:0.9rem;color:var(--text-muted);"><i class="fas fa-sync-alt fa-spin" style="margin-right:6px;"></i> Verifying answers and adding explanations in the background (you can continue).</div>';
    }
    html += '<div class="text-results-summary">';
    if(data.mcqs?.length) html += `<span class="text-result-badge mcq-badge">${data.mcqs.length} MCQs</span>`;
    if(data.short?.length) html += `<span class="text-result-badge short-badge">${data.short.length} Short</span>`;
    if(data.long?.length) html += `<span class="text-result-badge long-badge">${data.long.length} Long</span>`;
    html += '</div>';

    // Preview some questions
    html += '<div class="text-results-preview">';
    if(data.mcqs?.length) {
        html += '<div class="text-preview-section"><strong>Sample MCQ:</strong> ' + sanitize(data.mcqs[0].question) + '</div>';
    }
    if(data.short?.length) {
        html += '<div class="text-preview-section"><strong>Sample Short:</strong> ' + sanitize(data.short[0].question) + '</div>';
    }
    if(data.long?.length) {
        html += '<div class="text-preview-section"><strong>Sample Long:</strong> ' + sanitize(data.long[0].question) + '</div>';
    }
    html += '</div>';

    html += '<button type="button" class="text-upload-submit-btn" onclick="submitGeneratedPaper()"><i class="fas fa-file-signature"></i> View & Download Full Paper</button>';

    resultsEl.innerHTML = html;
    resultsEl.style.display = 'block';

    // Store data globally for submission
    window._generatedTextData = data;
    window._generatedTextTypes = types;
}

function submitGeneratedPaper() {
    // This function is no longer used for file upload flow.
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
