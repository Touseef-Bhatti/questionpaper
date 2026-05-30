<?php
session_start();
require_once __DIR__ . '/../config/env.php';
// require_once __DIR__ . '/../auth/auth_check.php';
$pageTitle = "Exam Paper Settings & Configuration | MCQ Maker & Test Creator – " . (EnvLoader::get('APP_NAME', 'Ahmad Learning Hub'));
$metaDescription = "Configure your question paper settings: select quantities for MCQs, short and long questions, set difficulty level (Easy to Hard), and generate professional PDFs. Global exam builder for USA, UK, Europe educators.";
$metaKeywords = "exam paper generator, MCQ maker, test creator, online paper builder, question settings, exam difficulty, classroom assessment builder, teacher test maker, online exam builder";

require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../middleware/SubscriptionCheck.php';

// Subscription Info
$subscriptionStatus = getSubscriptionInfo();
$isPremium = $subscriptionStatus && $subscriptionStatus['is_premium'];
$userPlan = $subscriptionStatus ? $subscriptionStatus['plan_type'] : 'free';

// Prepare JSON-LD structured data
$jsonLD = [
    "@context" => "https://schema.org",
    "@type" => "WebApplication",
    "name" => "Question Paper Configuration",
    "description" => "Configure and generate professional question papers",
    "url" => "https://" . $_SERVER['HTTP_HOST'] . "/questionPaperFromTopic/finalize_paper.php",
    "applicationCategory" => "EducationalApplication"
];
?><link rel="stylesheet" href="<?= $assetBase ?>css/paper-builder.css?v=<?= time() . rand(11000, 12000) ?>">
<link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css?v=<?= time() . rand(1, 1000) ?>">
<link rel="stylesheet" href="<?= $assetBase ?>css/search-results.css?v=<?= time() . rand(1, 1000) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════
   FINALIZE PAPER — COMPLETE REDESIGN
   Premium glassmorphic stepper layout
   ═══════════════════════════════════════════════ */

:root {
    --fp-primary: #6366f1;
    --fp-primary-dark: #4f46e5;
    --fp-surface: #ffffff;
    --fp-bg: #f1f5f9;
    --fp-text: #0f172a;
    --fp-text-secondary: #475569;
    --fp-text-muted: #94a3b8;
    --fp-border: #e2e8f0;
    --fp-border-light: #f1f5f9;
    --fp-radius-xl: 24px;
    --fp-radius-lg: 16px;
    --fp-radius-md: 12px;
    --fp-shadow-card: 0 1px 3px rgba(15,23,42,0.04), 0 10px 40px -12px rgba(15,23,42,0.08);
    --fp-shadow-hover: 0 20px 50px -16px rgba(15,23,42,0.12);
    --fp-transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Hero ─────────────────────────────────── */
.fp-hero {
    background: linear-gradient(160deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    padding: 3.5rem 1rem 3rem;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.fp-hero::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
    top: -200px;
    left: 50%;
    transform: translateX(-50%);
    pointer-events: none;
}
.fp-hero::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(to top, var(--fp-bg), transparent);
    pointer-events: none;
}
.fp-hero-title {
    font-family: 'Outfit', sans-serif;
    font-weight: 900;
    font-size: clamp(1.8rem, 4vw, 2.6rem);
    color: #fff;
    margin: 0 0 0.5rem;
    letter-spacing: -0.03em;
    position: relative;
}
.fp-hero-sub {
    color: #94a3b8;
    font-size: 1.05rem;
    font-weight: 500;
    margin: 0;
    position: relative;
}
.fp-hero-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 100px;
    padding: 8px 20px;
    margin-bottom: 1.25rem;
    color: #cbd5e1;
    font-size: 0.82rem;
    font-weight: 600;
    position: relative;
    backdrop-filter: blur(10px);
}
.fp-hero-breadcrumb i { opacity: 0.5; }
.fp-hero-breadcrumb a {
    color: var(--fp-primary);
    text-decoration: none;
}

/* ── Main Layout ──────────────────────────── */
.fp-wrapper {
    max-width: 920px;
    margin: -2rem auto 0;
    padding: 0 1rem 4rem;
    position: relative;
    z-index: 1;
}

/* ── Step Cards ───────────────────────────── */
.fp-card {
    background: var(--fp-surface);
    border: 1px solid var(--fp-border);
    border-radius: var(--fp-radius-xl);
    box-shadow: var(--fp-shadow-card);
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow var(--fp-transition), transform var(--fp-transition);
}
.fp-card:hover {
    box-shadow: var(--fp-shadow-hover);
}
.fp-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 28px;
    border-bottom: 1px solid var(--fp-border-light);
}
.fp-step-badge {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1rem;
    color: #fff;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--fp-primary), var(--fp-primary-dark));
    box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.fp-card-header-text h3 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.15rem;
    color: var(--fp-text);
    margin: 0;
    letter-spacing: -0.01em;
}
.fp-card-header-text p {
    font-size: 0.85rem;
    color: var(--fp-text-muted);
    margin: 2px 0 0;
}
.fp-card-body {
    padding: 24px 28px 28px;
}

/* ── Question Type Rows ───────────────────── */
.fp-qtype-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: var(--fp-bg);
    border: 1px solid var(--fp-border);
    border-radius: var(--fp-radius-lg);
    margin-bottom: 14px;
    transition: all var(--fp-transition);
}
.fp-qtype-row:last-child { margin-bottom: 0; }
.fp-qtype-row:hover {
    background: #fff;
    border-color: #cbd5e1;
    box-shadow: 0 8px 24px rgba(15,23,42,0.04);
    transform: translateY(-1px);
}
.fp-qtype-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--fp-radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.fp-qtype-icon.mcq { background: #eff6ff; color: #3b82f6; }
.fp-qtype-icon.short { background: #ecfdf5; color: #10b981; }
.fp-qtype-icon.long { background: #fef3c7; color: #d97706; }
.fp-qtype-info { flex: 1; min-width: 0; }
.fp-qtype-info h6 {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--fp-text);
    margin: 0;
}
.fp-qtype-info span {
    font-size: 0.8rem;
    color: var(--fp-text-muted);
}

/* ── Quantity Stepper ─────────────────────── */
.fp-stepper {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1.5px solid var(--fp-border);
    border-radius: var(--fp-radius-md);
    padding: 3px;
    gap: 0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    flex-shrink: 0;
}
.fp-stepper-btn {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    color: var(--fp-text-secondary);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
}
.fp-stepper-btn:hover {
    background: var(--fp-bg);
    color: var(--fp-text);
}
.fp-stepper-btn:active {
    transform: scale(0.92);
}
.fp-stepper-input {
    width: 44px;
    border: none;
    background: transparent;
    text-align: center;
    font-weight: 800;
    font-size: 1.05rem;
    color: var(--fp-text);
    font-family: 'Outfit', sans-serif;
    -moz-appearance: textfield;
}
.fp-stepper-input::-webkit-outer-spin-button,
.fp-stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.fp-stepper-input:focus { outline: none; }

/* ── Difficulty Pills ─────────────────────── */
.fp-diff-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 520px) {
    .fp-diff-grid { grid-template-columns: 1fr; }
}
.fp-diff-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 20px 12px;
    border: 2px solid var(--fp-border);
    border-radius: var(--fp-radius-lg);
    background: var(--fp-bg);
    cursor: pointer;
    transition: all var(--fp-transition);
    text-align: center;
    font-family: inherit;
    position: relative;
}
.fp-diff-pill:hover {
    background: #fff;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.04);
}
.fp-diff-pill .fp-diff-emoji { font-size: 1.6rem; line-height: 1; }
.fp-diff-pill .fp-diff-label {
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--fp-text);
}
.fp-diff-pill .fp-diff-desc {
    font-size: 0.72rem;
    color: var(--fp-text-muted);
    line-height: 1.3;
}
.fp-diff-pill.active[data-diff="easy"] {
    border-color: #22c55e;
    background: linear-gradient(to bottom, #f0fdf4, #dcfce7);
    box-shadow: 0 8px 24px rgba(34,197,94,0.12);
}
.fp-diff-pill.active[data-diff="medium"] {
    border-color: #f59e0b;
    background: linear-gradient(to bottom, #fffbeb, #fef3c7);
    box-shadow: 0 8px 24px rgba(245,158,11,0.12);
}
.fp-diff-pill.active[data-diff="hard"] {
    border-color: #ef4444;
    background: linear-gradient(to bottom, #fef2f2, #fecaca);
    box-shadow: 0 8px 24px rgba(239,68,68,0.12);
}
.fp-diff-pill.active::after {
    content: '✓';
    position: absolute;
    top: 8px;
    right: 10px;
    font-size: 0.7rem;
    font-weight: 900;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.fp-diff-pill.active[data-diff="easy"]::after { background: #22c55e; }
.fp-diff-pill.active[data-diff="medium"]::after { background: #f59e0b; }
.fp-diff-pill.active[data-diff="hard"]::after { background: #ef4444; }

/* ── Topics Sidebar (Collapsible on mobile) ── */
.fp-topics-card {
    background: var(--fp-surface);
    border: 1px solid var(--fp-border);
    border-radius: var(--fp-radius-xl);
    box-shadow: var(--fp-shadow-card);
    overflow: hidden;
}
.fp-topics-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid var(--fp-border);
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}
.fp-topics-header:hover { background: #f1f5f9; }
.fp-topics-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.fp-topics-header-left i {
    color: var(--fp-primary);
    font-size: 1rem;
}
.fp-topics-header-left span {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 1rem;
    color: var(--fp-text);
}
.fp-topics-count {
    background: var(--fp-primary);
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 3px 10px;
    border-radius: 100px;
}
.fp-topics-toggle {
    color: var(--fp-text-muted);
    font-size: 0.85rem;
    transition: transform 0.3s;
}
.fp-topics-body {
    padding: 20px 24px;
    max-height: 400px;
    overflow-y: auto;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.fp-topics-body.collapsed {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    overflow: hidden;
}
.fp-topics-body::-webkit-scrollbar { width: 4px; }
.fp-topics-body::-webkit-scrollbar-track { background: transparent; }
.fp-topics-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.fp-topic-group-label {
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
    padding-left: 4px;
}
.fp-topic-group-label.mcq { color: #3b82f6; }
.fp-topic-group-label.short { color: #10b981; }
.fp-topic-group-label.long { color: #d97706; }
.fp-topic-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--fp-bg);
    border-radius: 10px;
    margin-bottom: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--fp-text);
    transition: all 0.2s;
    border: 1px solid transparent;
}
.fp-topic-chip:hover {
    background: #fff;
    border-color: var(--fp-border);
    transform: translateX(3px);
}
.fp-topic-chip .fp-chip-remove {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--fp-border);
    color: var(--fp-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.65rem;
    flex-shrink: 0;
}
.fp-topic-chip .fp-chip-remove:hover {
    background: #ef4444;
    color: #fff;
}
.fp-topic-spacer { height: 16px; }
.fp-topics-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--fp-border-light);
    text-align: center;
}
.fp-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--fp-text-muted);
    font-weight: 600;
    font-size: 0.88rem;
    text-decoration: none;
    padding: 8px 18px;
    border-radius: 10px;
    transition: all 0.2s;
}
.fp-back-link:hover {
    color: var(--fp-text);
    background: var(--fp-bg);
}

/* ── Generate CTA ─────────────────────────── */
.fp-generate-section {
    text-align: center;
    margin-top: 8px;
}

/* ── SEO Footer ───────────────────────────── */
.fp-seo-footer {
    background: var(--fp-surface);
    border: 1px solid var(--fp-border);
    border-radius: var(--fp-radius-xl);
    box-shadow: var(--fp-shadow-card);
    padding: 36px 32px;
    text-align: center;
    margin-top: 32px;
}
.fp-seo-footer h3 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.2rem;
    color: var(--fp-text);
    margin: 0 0 10px;
}
.fp-seo-footer p {
    color: var(--fp-text-secondary);
    font-size: 0.9rem;
    line-height: 1.7;
    margin: 0 0 20px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.fp-seo-badges {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}
.fp-seo-badge {
    background: var(--fp-bg);
    border: 1px solid var(--fp-border);
    color: var(--fp-text-secondary);
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}
.fp-seo-badge i { font-size: 0.75rem; color: var(--fp-primary); }

/* ── Animations ───────────────────────────── */
.fp-fade-up {
    animation: fpFadeUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.fp-delay-1 { animation-delay: 80ms; }
.fp-delay-2 { animation-delay: 160ms; }
.fp-delay-3 { animation-delay: 240ms; }
.fp-delay-4 { animation-delay: 320ms; }
@keyframes fpFadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ───────────────────────────── */
@media (max-width: 768px) {
    .fp-wrapper { padding: 0 0.75rem 3rem; margin-top: -1.5rem; }
    .fp-card-header { padding: 18px 20px; }
    .fp-card-body { padding: 18px 20px 22px; }
    .fp-qtype-row { flex-wrap: wrap; gap: 12px; padding: 14px 16px; }
    .fp-stepper { margin-left: auto; }
    .fp-hero { padding: 2.5rem 1rem 2rem; }
}
</style>

<!-- SEO: JSON-LD Structured Data -->
<script type="application/ld+json">
<?= json_encode($jsonLD, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<?php
// Ensure we have topics to process
$topics = $_POST['topics'] ?? [];
$activeTypes = $_POST['active_types'] ?? [];

// Get Categorized Topics
$topicsMcqs = $_POST['topics_mcqs'] ?? [];
$topicsShort = $_POST['topics_short'] ?? [];
$topicsLong = $_POST['topics_long'] ?? [];

if (empty($topics)) {
    echo "<script>window.location.href = '" . ($assetBase ?? '../') . "index.php';</script>";
    exit;
}

if (!is_array($topics)) $topics = explode(',', $topics);
if (!is_array($activeTypes)) $activeTypes = explode(',', $activeTypes);
if (!is_array($topicsMcqs)) $topicsMcqs = explode(',', $topicsMcqs);
if (!is_array($topicsShort)) $topicsShort = explode(',', $topicsShort);
if (!is_array($topicsLong)) $topicsLong = explode(',', $topicsLong);

$showMcqs = in_array('mcqs', $activeTypes) || empty($activeTypes);
$showShort = in_array('short', $activeTypes) || empty($activeTypes);
$showLong = in_array('long', $activeTypes) || empty($activeTypes);
?>

<!-- ═══ HERO ═══ -->
<div class="fp-hero">
    <div class="fp-hero-breadcrumb fp-fade-up">
        <i class="fas fa-home"></i>
        <a href="index.php">Topics</a>
        <i class="fas fa-chevron-right" style="font-size:0.6rem;"></i>
        <span>Finalize</span>
    </div>
    <h1 class="fp-hero-title fp-fade-up fp-delay-1">Finalize Your Paper</h1>
    <p class="fp-hero-sub fp-fade-up fp-delay-2">Set quantities, pick difficulty, and generate your PDF.</p>
</div>

<!-- ═══ MAIN CONTENT ═══ -->
<div class="fp-wrapper">

    <!-- ── STEP 1: Question Quantities ── -->
    <div class="fp-card fp-fade-up fp-delay-1">
        <div class="fp-card-header">
            <div class="fp-step-badge">1</div>
            <div class="fp-card-header-text">
                <h3>Question Quantities</h3>
                <p>Set how many questions for each section</p>
            </div>
        </div>
        <div class="fp-card-body">
            <form action="generate_ai_paper.php" method="POST" id="configForm">
                <!-- Hidden Inputs -->
                <?php foreach($topics as $t): ?><input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                <?php foreach($topicsMcqs as $t): ?><input type="hidden" name="topics_mcqs[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                <?php foreach($topicsShort as $t): ?><input type="hidden" name="topics_short[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                <?php foreach($topicsLong as $t): ?><input type="hidden" name="topics_long[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                <input type="hidden" name="source" value="<?= htmlspecialchars($_POST['source'] ?? 'topics') ?>">
                <input type="hidden" name="file_hash" value="<?= htmlspecialchars($_POST['file_hash'] ?? '') ?>">
                <input type="hidden" name="class_id" value="0">
                <input type="hidden" name="book_name" value="Professional Academic Paper">
                <input type="hidden" name="pattern_mode" value="without">
                <input type="hidden" name="header_design" value="<?= htmlspecialchars($_POST['header_design'] ?? '1') ?>">

                <!-- MCQs -->
                <div class="fp-qtype-row" style="<?= $showMcqs ? '' : 'display:none;' ?>">
                    <div class="fp-qtype-icon mcq"><i class="fas fa-list-ol"></i></div>
                    <div class="fp-qtype-info">
                        <h6>Multiple Choice Questions</h6>
                        <span>Objective-type questions with 4 options</span>
                    </div>
                    <div class="fp-stepper">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputMcqs', -1)"><i class="fas fa-minus"></i></button>
                        <input type="number" name="total_mcqs" id="inputMcqs" class="fp-stepper-input" value="<?= $showMcqs ? 10 : 0 ?>" min="0" max="100">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputMcqs', 1)"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                <!-- Short Questions -->
                <div class="fp-qtype-row" style="<?= $showShort ? '' : 'display:none;' ?>">
                    <div class="fp-qtype-icon short"><i class="fas fa-align-left"></i></div>
                    <div class="fp-qtype-info">
                        <h6>Short Questions</h6>
                        <span>Brief answers, definitions & explanations</span>
                    </div>
                    <div class="fp-stepper">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputShort', -1)"><i class="fas fa-minus"></i></button>
                        <input type="number" name="total_shorts" id="inputShort" class="fp-stepper-input" value="<?= $showShort ? 5 : 0 ?>" min="0" max="50">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputShort', 1)"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                <!-- Long Questions -->
                <div class="fp-qtype-row" style="<?= $showLong ? '' : 'display:none;' ?>">
                    <div class="fp-qtype-icon long"><i class="fas fa-align-justify"></i></div>
                    <div class="fp-qtype-info">
                        <h6>Long Questions</h6>
                        <span>Detailed essays & numerical problems</span>
                    </div>
                    <div class="fp-stepper">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputLong', -1)"><i class="fas fa-minus"></i></button>
                        <input type="number" name="total_longs" id="inputLong" class="fp-stepper-input" value="<?= $showLong ? 3 : 0 ?>" min="0" max="25">
                        <button type="button" class="fp-stepper-btn" onclick="updateQuantity('inputLong', 1)"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
        </div>
    </div>

    <!-- ── STEP 2: Difficulty Level ── -->
    <div class="fp-card fp-fade-up fp-delay-2">
        <div class="fp-card-header">
            <div class="fp-step-badge">2</div>
            <div class="fp-card-header-text">
                <h3>Difficulty Level</h3>
                <p>Choose the complexity of generated questions</p>
            </div>
        </div>
        <div class="fp-card-body">
            <input type="hidden" name="difficulty" id="difficultyInput" value="medium">
            <div class="fp-diff-grid">
                <button type="button" class="fp-diff-pill" data-diff="easy" onclick="setDifficulty('easy')">
                    <span class="fp-diff-emoji">🟢</span>
                    <span class="fp-diff-label">Easy</span>
                    <span class="fp-diff-desc">Definitions & basic examples</span>
                </button>
                <button type="button" class="fp-diff-pill active" data-diff="medium" onclick="setDifficulty('medium')">
                    <span class="fp-diff-emoji">🟡</span>
                    <span class="fp-diff-label">Medium</span>
                    <span class="fp-diff-desc">Concepts, examples & numericals</span>
                </button>
                <button type="button" class="fp-diff-pill" data-diff="hard" onclick="setDifficulty('hard')">
                    <span class="fp-diff-emoji">🔴</span>
                    <span class="fp-diff-label">Hard</span>
                    <span class="fp-diff-desc">Explanations & numerical problems</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── STEP 3: Selected Topics ── -->
    <div class="fp-topics-card fp-fade-up fp-delay-3">
        <div class="fp-topics-header" onclick="toggleTopics()">
            <div class="fp-topics-header-left">
                <i class="fas fa-layer-group"></i>
                <span>Selected Topics</span>
                <span class="fp-topics-count"><?= count($topics) ?></span>
            </div>
            <i class="fas fa-chevron-down fp-topics-toggle" id="topicsToggleIcon"></i>
        </div>
        <div class="fp-topics-body" id="topicsBody">
            <?php if (!empty($topicsMcqs)): ?>
                <div class="fp-topic-group-label mcq"><i class="fas fa-list-ul me-1"></i> MCQs</div>
                <?php foreach($topicsMcqs as $topic): ?>
                    <div class="fp-topic-chip">
                        <span><?= htmlspecialchars($topic) ?></span>
                        <div class="fp-chip-remove" onclick="this.closest('.fp-topic-chip').remove()"><i class="fas fa-times"></i></div>
                    </div>
                <?php endforeach; ?>
                <div class="fp-topic-spacer"></div>
            <?php endif; ?>

            <?php if (!empty($topicsShort)): ?>
                <div class="fp-topic-group-label short"><i class="fas fa-align-left me-1"></i> Short Questions</div>
                <?php foreach($topicsShort as $topic): ?>
                    <div class="fp-topic-chip">
                        <span><?= htmlspecialchars($topic) ?></span>
                        <div class="fp-chip-remove" onclick="this.closest('.fp-topic-chip').remove()"><i class="fas fa-times"></i></div>
                    </div>
                <?php endforeach; ?>
                <div class="fp-topic-spacer"></div>
            <?php endif; ?>

            <?php if (!empty($topicsLong)): ?>
                <div class="fp-topic-group-label long"><i class="fas fa-align-justify me-1"></i> Long Questions</div>
                <?php foreach($topicsLong as $topic): ?>
                    <div class="fp-topic-chip">
                        <span><?= htmlspecialchars($topic) ?></span>
                        <div class="fp-chip-remove" onclick="this.closest('.fp-topic-chip').remove()"><i class="fas fa-times"></i></div>
                    </div>
                <?php endforeach; ?>
                <div class="fp-topic-spacer"></div>
            <?php endif; ?>

            <?php if (empty($topicsMcqs) && empty($topicsShort) && empty($topicsLong) && !empty($topics)): ?>
                <div class="fp-topic-group-label" style="color: var(--fp-text-secondary);">General Selection</div>
                <?php foreach($topics as $topic): ?>
                    <div class="fp-topic-chip">
                        <span><?= htmlspecialchars($topic) ?></span>
                        <div class="fp-chip-remove" onclick="this.closest('.fp-topic-chip').remove()"><i class="fas fa-times"></i></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="fp-topics-footer">
            <a href="index.php" class="fp-back-link">
                <i class="fas fa-arrow-left"></i> Adjust Selection
            </a>
        </div>
    </div>

    <!-- ── Generate Button ── -->
    <div class="fp-generate-section fp-fade-up fp-delay-4">
        <div class="btn-wrapper" onclick="handleGeneratePaper()">
          <button type="button" class="btn">
            <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"></path>
            </svg>
            <div class="txt-wrapper">
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
    </form>

    <!-- ── SEO Footer ── -->
    <article class="fp-seo-footer fp-fade-up fp-delay-4">
        <h3>Intelligent Paper Generator for Educators</h3>
        <p>Empower your teaching workflow with our high-precision assessment toolkit. We craft test frameworks syncing <strong>MCQs, Short Answers, and Essays</strong> aligned with Board, ECAT, and MDCAT rubrics.</p>
        <div class="fp-seo-badges">
            <span class="fp-seo-badge"><i class="fas fa-file-pdf"></i> PDF Output</span>
            <span class="fp-seo-badge"><i class="fas fa-bullseye"></i> Exam-aligned</span>
            <span class="fp-seo-badge"><i class="fas fa-calendar-check"></i> 2026 Curriculum</span>
        </div>
    </article>
</div>

<script>
    const isPremium = <?= json_encode($isPremium) ?>;
    const questionLimits = { inputMcqs: 10, inputShort: 10, inputLong: 3 };

    function updateQuantity(inputId, change) {
        const input = document.getElementById(inputId);
        let val = parseInt(input.value) || 0;
        const min = parseInt(input.min) || 0;
        const max = parseInt(input.max) || 100;
        const newVal = val + change;

        if (!isPremium && change > 0 && newVal > questionLimits[inputId]) {
            showUpgradeModal();
            return;
        }

        input.value = Math.max(min, Math.min(max, newVal));
    }

    function handleGeneratePaper() {
        const mcqs = parseInt(document.getElementById('inputMcqs').value) || 0;
        const shorts = parseInt(document.getElementById('inputShort').value) || 0;
        const longs = parseInt(document.getElementById('inputLong').value) || 0;

        if (!isPremium) {
            if (mcqs > questionLimits.inputMcqs || shorts > questionLimits.inputShort || longs > questionLimits.inputLong) {
                showUpgradeModal();
                return;
            }
        }
        document.getElementById('configForm').submit();
    }

    function showUpgradeModal() {
        if (typeof showGlobalUpgradeModal === 'function') {
            showGlobalUpgradeModal('questions');
        } else {
            alert("Limit reached! Please upgrade your plan for more questions.");
        }
    }

    // Live check for manual input
    if (!isPremium) {
        ['inputMcqs', 'inputShort', 'inputLong'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function() {
                    if ((parseInt(this.value) || 0) > questionLimits[id]) {
                        this.value = questionLimits[id];
                        showUpgradeModal();
                    }
                });
            }
        });
    }

    function setDifficulty(level) {
        document.getElementById('difficultyInput').value = level;
        document.querySelectorAll('.fp-diff-pill').forEach(btn => btn.classList.remove('active'));
        const active = document.querySelector(`.fp-diff-pill[data-diff="${level}"]`);
        if (active) active.classList.add('active');
    }

    function toggleTopics() {
        const body = document.getElementById('topicsBody');
        const icon = document.getElementById('topicsToggleIcon');
        body.classList.toggle('collapsed');
        icon.style.transform = body.classList.contains('collapsed') ? 'rotate(-90deg)' : 'rotate(0)';
    }
</script>
</script>

<?php include __DIR__ . '/../footer.php'; ?>
