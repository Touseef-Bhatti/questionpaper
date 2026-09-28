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
.fp-progress {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin: 0 auto 18px;
    padding: 7px 12px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 999px;
    background: rgba(15,23,42,0.22);
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 800;
}
.fp-progress-step { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
.fp-progress-step span,
.fp-progress-step.done i {
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(148,163,184,0.2);
    font-size: 0.62rem;
}
.fp-progress-step.done { color: #bbf7d0; }
.fp-progress-step.done i { background: #16a34a; color: #fff; }
.fp-progress-step.active { color: #fff; }
.fp-progress-step.active span { background: var(--fp-primary); color: #fff; }
.fp-progress-line { width: 22px; height: 1px; background: rgba(148,163,184,0.35); }

.fp-source-status {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    margin-bottom: 20px;
    border: 1px solid #c7d2fe;
    border-radius: var(--fp-radius-lg);
    background: linear-gradient(135deg, #eef2ff, #f8fafc);
    box-shadow: 0 8px 24px rgba(79,70,229,0.07);
}
.fp-source-status-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #4f46e5;
    color: #fff;
    font-size: 1.05rem;
}
.fp-source-status-copy { min-width: 0; flex: 1; }
.fp-source-status-copy strong,
.fp-source-status-copy span,
.fp-source-status-copy small { display: block; }
.fp-source-status-copy strong { color: #1e1b4b; font-weight: 800; }
.fp-source-status-copy span { margin-top: 2px; color: #475569; font-size: 0.84rem; }
.fp-source-status-copy small { margin-top: 4px; color: #6366f1; font-size: 0.76rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-source-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    color: #4338ca;
    background: #fff;
    font-size: 0.72rem;
    font-weight: 800;
    white-space: nowrap;
}
.fp-topics-step-badge {
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--fp-primary), var(--fp-primary-dark));
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
}
.fp-generation-summary {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.fp-generation-summary > div {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 13px;
    border: 1px solid var(--fp-border);
    border-radius: 999px;
    background: #fff;
    color: var(--fp-text-secondary);
    font-size: 0.78rem;
    font-weight: 600;
}
.fp-generation-summary i { color: var(--fp-primary); }
.fp-generation-summary strong { color: var(--fp-text); }
.fp-generate-help { margin: 0 auto 16px; color: var(--fp-text-muted); font-size: 0.82rem; }

@media (max-width: 768px) {
    .fp-wrapper { padding: 0 0.75rem 3rem; margin-top: -1.5rem; }
    .fp-card-header { padding: 18px 20px; }
    .fp-card-body { padding: 18px 20px 22px; }
    .fp-qtype-row { flex-wrap: wrap; gap: 12px; padding: 14px 16px; }
    .fp-stepper { margin-left: auto; }
    .fp-hero { padding: 2.5rem 1rem 2rem; }
    .fp-source-status { align-items: flex-start; }
    .fp-source-status-badge { display: none; }
    .fp-progress { max-width: 100%; }
}
/* ================================================================
   Editorial study desk redesign
   ================================================================ */
:root {
    --fp-ink: var(--ink-200, #1e293b);
    --fp-ink-soft: var(--ink-300, #334155);
    --fp-paper: var(--surface, #ffffff);
    --fp-canvas: var(--ink-950, #f8fafc);
    --fp-line: var(--ink-800, #e2e8f0);
    --fp-muted: var(--ink-500, #64748b);
    --fp-coral: var(--primary, #6366f1);
    --fp-coral-dark: var(--primary-dark, #4f46e5);
    --fp-teal: var(--accent, #a855f7);
    --fp-gold: var(--accent-warm, #f59e0b);
    --fp-shadow: var(--sh-card, 0 10px 30px rgba(15,23,42,.08));
}

body:has(.fp-hero) { background: var(--fp-canvas); color: var(--fp-ink); font-family: var(--font-body, 'Inter', sans-serif); }
.fp-hero {
    min-height: 310px;
    padding: 42px 24px 104px;
    text-align: left;
    background: linear-gradient(160deg, #0f0c29 0%, #1a1145 55%, #302b63 100%);
    isolation: isolate;
}
.fp-hero::before {
    width: 820px;
    height: 420px;
    top: -220px;
    left: 72%;
    background: radial-gradient(circle, rgba(99,102,241,.28) 0%, rgba(99,102,241,0) 68%);
    transform: translateX(-50%);
}
.fp-hero::after { height: 120px; background: linear-gradient(to top, var(--fp-canvas), transparent); }
.fp-hero > * { max-width: 1100px; margin-left: auto; margin-right: auto; }
.fp-hero-breadcrumb { margin-bottom: 26px; margin-left: auto; margin-right: auto; width: fit-content; align-self: center; }
.fp-hero-title { font-family: var(--font-heading, 'Outfit', sans-serif); font-size: clamp(2.25rem, 5vw, 4.3rem); line-height: .98; letter-spacing: -.055em; max-width: 1100px; }
.fp-hero-sub { max-width: 1100px; margin-top: 18px; color: var(--ink-700, #cbd5e1); font-size: 1rem; }
.fp-progress { margin: 0 0 22px; background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.16); }
.fp-wrapper { max-width: 1100px; margin: -55px auto 0; padding: 0 20px 64px; }
.fp-source-status { position: relative; z-index: 2; margin-bottom: 20px; border: 1px solid rgba(99,102,241,.2); border-radius: 18px; background: var(--fp-paper); box-shadow: var(--fp-shadow); }
.fp-source-status-icon { background: var(--fp-coral); }
.fp-source-status-copy strong { color: var(--fp-ink); font-family: var(--font-heading, 'Outfit', sans-serif); }
.fp-source-status-copy span { color: var(--ink-400, #475569); }
.fp-source-status-copy small { color: var(--fp-coral-dark); }
.fp-source-status-badge { border-color: var(--primary-light, #c7d2fe); color: var(--fp-coral-dark); }
.fp-builder-form { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 20px; align-items: start; }
.fp-card, .fp-topics-card, .fp-seo-footer { border-color: var(--fp-line); border-radius: 18px; background: var(--fp-paper); box-shadow: var(--fp-shadow); }
.fp-card { margin: 0; }
.fp-quantity-card { grid-column: 1; }
.fp-difficulty-card { grid-column: 1; }
.fp-topics-panel { grid-column: 2; grid-row: 1 / span 2; position: sticky; top: 82px; }
.fp-card:hover { box-shadow: 0 22px 50px rgba(15,23,42,.12); transform: translateY(-1px); }
.fp-card-header { padding: 22px 24px; border-color: var(--fp-line); }
.fp-card-header-text h3, .fp-topics-header-left span { font-family: var(--font-heading, 'Outfit', sans-serif); }
.fp-card-header-text h3 { color: var(--fp-ink); }
.fp-card-header-text p { color: var(--fp-muted); }
.fp-step-badge, .fp-topics-step-badge { background: var(--fp-ink); box-shadow: none; }
.fp-card-body { padding: 20px 24px 24px; }
.fp-qtype-row { padding: 14px 16px; margin-bottom: 10px; border-color: var(--fp-line); border-radius: 14px; background: var(--primary-soft, #f8f8ff); }
.fp-qtype-row:hover { border-color: var(--primary-light, #a5b4fc); box-shadow: 0 8px 22px rgba(15,23,42,.06); }
.fp-qtype-info h6 { color: var(--fp-ink); font-family: var(--font-heading, 'Outfit', sans-serif); }
.fp-qtype-info span { color: var(--fp-muted); }
.fp-stepper { border-color: var(--fp-line); }
.fp-stepper-btn:hover { color: var(--fp-coral-dark); background: var(--primary-light, #eef2ff); }
.fp-stepper-input { color: var(--fp-ink); }
.fp-diff-grid { gap: 10px; }
.fp-diff-pill { padding: 16px 10px; border-color: var(--fp-line); background: var(--primary-soft, #f8f8ff); }
.fp-diff-pill:hover { border-color: var(--primary-light, #a5b4fc); box-shadow: 0 8px 20px rgba(15,23,42,.06); }
.fp-diff-pill.active[data-diff="medium"] { border-color: var(--fp-coral); background: var(--primary-light, #eef2ff); box-shadow: 0 8px 24px rgba(99,102,241,.14); }
.fp-topics-header { padding: 17px 20px; background: var(--primary-light, #eef2ff); border-color: var(--fp-line); }
.fp-topics-header-left { gap: 8px; }
.fp-topics-header-left i { color: var(--fp-coral); }
.fp-topics-count { background: var(--fp-coral); }
.fp-topics-body { padding: 18px 20px; max-height: 430px; }
.fp-topic-chip { background: var(--primary-soft, #f8f8ff); border-color: transparent; color: var(--fp-ink); }
.fp-topic-chip:hover { border-color: var(--primary-light, #a5b4fc); }
.fp-topic-chip .fp-chip-remove { background: var(--ink-800, #e2e8f0); }
.fp-topic-chip .fp-chip-remove:hover { background: var(--fp-coral); }
.fp-topics-footer { border-color: var(--fp-line); }
.fp-back-link:hover { color: var(--fp-coral-dark); background: var(--primary-light, #eef2ff); }
.fp-generate-section { grid-column: 1 / -1; margin: 0; padding: 6px 0 0; text-align: center; }
.fp-generation-summary { margin-bottom: 14px; }
.fp-generation-summary > div { border-color: var(--fp-line); background: var(--fp-paper); }
.fp-generation-summary i { color: var(--fp-coral); }
.fp-generate-help { color: var(--fp-muted); }
.fp-primary-cta {
    width: min(100%, 580px);
    min-height: 70px;
    display: inline-flex;
    align-items: center;
    gap: 14px;
    padding: 12px 18px;
    border: 0;
    border-radius: 17px;
    color: #fff;
    background: var(--fp-coral);
    box-shadow: 0 14px 28px rgba(99,102,241,.27);
    cursor: pointer;
    text-align: left;
    transition: transform .2s ease, background .2s ease, box-shadow .2s ease;
}
.fp-primary-cta:hover { transform: translateY(-2px); background: var(--fp-coral-dark); box-shadow: 0 18px 32px rgba(99,102,241,.34); }
.fp-primary-cta:focus-visible { outline: 3px solid var(--primary-light, #a5b4fc); outline-offset: 4px; }
.fp-primary-cta:disabled { opacity: .86; cursor: wait; transform: none; box-shadow: 0 10px 22px rgba(99,102,241,.18); }
.fp-primary-cta-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 12px; background: rgba(255,255,255,.18); font-size: 1.1rem; }
.fp-primary-cta-copy { display: grid; gap: 2px; flex: 1; }
.fp-primary-cta-copy strong { font: 700 1rem var(--font-heading, 'Outfit', sans-serif); }
.fp-primary-cta-copy small { color: rgba(255,255,255,.78); font-size: .78rem; }
.fp-primary-cta-arrow { font-size: .95rem; }
.fp-legacy-generate-button { display: none !important; }
.fp-seo-footer { margin-top: 28px; padding: 28px 24px; }
.fp-seo-footer h3 { font-family: var(--font-heading, 'Outfit', sans-serif); color: var(--fp-ink); }

.fp-generation-overlay { position: fixed; inset: 0; z-index: 11000; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.68); backdrop-filter: blur(7px); opacity: 0; visibility: hidden; transition: opacity .2s ease, visibility .2s ease; }
.fp-generation-overlay.is-visible { opacity: 1; visibility: visible; }
.fp-loader-card { width: min(390px, 100%); padding: 30px 26px 25px; border: 1px solid rgba(255,255,255,.55); border-radius: 22px; background: var(--fp-paper); box-shadow: 0 24px 70px rgba(0,0,0,.24); text-align: center; }
.fp-loader-sheet { position: relative; width: 68px; height: 78px; margin: 0 auto 19px; padding: 18px 13px 10px; border: 2px solid var(--fp-coral); border-radius: 8px; background: #fff; box-shadow: 8px 8px 0 var(--primary-light, #c7d2fe); animation: fpSheetFloat 2.1s ease-in-out infinite; }
.fp-loader-sheet span { display: block; height: 5px; margin-bottom: 7px; border-radius: 99px; background: var(--ink-800, #e2e8f0); }
.fp-loader-sheet span:nth-child(2) { width: 78%; }
.fp-loader-sheet span:nth-child(3) { width: 58%; }
.fp-loader-sheet i { position: absolute; right: -11px; bottom: -8px; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; color: #fff; background: var(--fp-coral); font-size: .75rem; }
.fp-loader-card h2 { margin: 0 0 8px; color: var(--fp-ink); font: 700 1.25rem var(--font-heading, 'Outfit', sans-serif); }
.fp-loader-card p { margin: 0 auto 18px; max-width: 290px; color: var(--fp-muted); font-size: .88rem; line-height: 1.5; }
.fp-loader-track { height: 6px; overflow: hidden; border-radius: 99px; background: var(--ink-800, #e2e8f0); }
.fp-loader-track span { display: block; width: 42%; height: 100%; border-radius: inherit; background: var(--fp-coral); animation: fpLoaderProgress 1.45s ease-in-out infinite; }
.fp-loader-card small { display: block; margin-top: 15px; color: var(--ink-500, #64748b); font-size: .75rem; }
.fp-loader-card small i { margin-right: 4px; color: var(--fp-teal); }
body.fp-generation-lock { overflow: hidden; }
@keyframes fpSheetFloat { 0%,100% { transform: translateY(0) rotate(-1deg); } 50% { transform: translateY(-5px) rotate(1deg); } }
@keyframes fpLoaderProgress { 0% { transform: translateX(-140%); } 55%,100% { transform: translateX(250%); } }

/* Keep the configuration surface on Ahmad Learning Hub's existing indigo theme. */
.fp-hero::before { background: radial-gradient(circle, rgba(99,102,241,.28) 0%, rgba(99,102,241,0) 68%); }
.fp-source-status { border-color: rgba(99,102,241,.2); }
.fp-source-status-copy span { color: var(--ink-400, #475569); }
.fp-source-status-badge { border-color: var(--primary-light, #c7d2fe); }
.fp-generate-section { text-align: left; padding-left: clamp(0px, 2.5vw, 30px); }
.fp-generation-summary { justify-content: flex-start; }
.fp-generate-help { margin-left: 0; margin-right: 0; text-align: left; }
.fp-primary-cta { display: flex; margin-left: 0; }
.fp-qtype-row, .fp-diff-pill, .fp-topic-chip { background: var(--primary-soft, #f8f8ff); }
.fp-qtype-row:hover, .fp-diff-pill:hover, .fp-topic-chip:hover { border-color: var(--primary-light, #a5b4fc); }
.fp-stepper-btn:hover, .fp-back-link:hover { background: var(--primary-light, #eef2ff); }
.fp-diff-pill.active[data-diff="medium"] { background: var(--primary-light, #eef2ff); box-shadow: 0 8px 24px rgba(99,102,241,.14); }
.fp-topics-header { background: var(--primary-light, #eef2ff); }
.fp-topic-chip .fp-chip-remove { background: var(--ink-800, #e2e8f0); }
.fp-loader-sheet { box-shadow: 8px 8px 0 var(--primary-light, #c7d2fe); }
.fp-loader-sheet span { background: var(--ink-800, #e2e8f0); }
.fp-loader-track { background: var(--ink-800, #e2e8f0); }
.fp-loader-card small { color: var(--ink-500, #64748b); }
.fp-loader-card small i { color: var(--accent-warm, #f59e0b); }

@media (max-width: 820px) {
    .fp-hero { min-height: 280px; padding: 30px 18px 90px; }
    .fp-hero-title { font-size: clamp(2.1rem, 10vw, 3.3rem); }
    .fp-wrapper { margin-top: -42px; padding: 0 14px 45px; }
    .fp-builder-form { display: flex; flex-direction: column; gap: 14px; }
    .fp-quantity-card, .fp-difficulty-card, .fp-topics-panel, .fp-generate-section { width: 100%; }
    .fp-topics-panel { position: static; order: 3; }
    .fp-quantity-card { order: 1; }
    .fp-difficulty-card { order: 2; }
    .fp-generate-section { order: 4; }
    .fp-card-header { padding: 18px; }
    .fp-card-body { padding: 16px 18px 20px; }
    .fp-source-status { padding: 14px; }
    .fp-source-status-badge { display: none; }
    .fp-generate-section { padding-left: 0; text-align: center; }
    .fp-generation-summary { justify-content: center; }
    .fp-generate-help { text-align: center; }
    .fp-primary-cta { margin-left: auto; margin-right: auto; }
}
@media (max-width: 520px) {
    .fp-progress { width: 100%; justify-content: center; gap: 6px; padding: 7px 8px; font-size: .66rem; }
    .fp-progress-line { width: 12px; }
    .fp-hero-breadcrumb { font-size: .72rem; padding: 7px 13px; }
    .fp-source-status { align-items: flex-start; gap: 10px; }
    .fp-source-status-icon { width: 36px; height: 36px; flex-basis: 36px; border-radius: 10px; }
    .fp-source-status-copy span { font-size: .78rem; }
    .fp-qtype-row { display: grid; grid-template-columns: 42px minmax(0,1fr); gap: 10px; }
    .fp-qtype-icon { width: 42px; height: 42px; }
    .fp-stepper { grid-column: 2; justify-self: start; }
    .fp-diff-grid { grid-template-columns: 1fr; }
    .fp-diff-pill { display: grid; grid-template-columns: 34px 1fr; grid-template-rows: auto auto; align-items: center; text-align: left; padding: 12px 14px; }
    .fp-diff-emoji { grid-row: 1 / span 2; }
    .fp-diff-label, .fp-diff-desc { grid-column: 2; }
    .fp-primary-cta { min-height: 62px; }
    .fp-primary-cta-copy strong { font-size: .92rem; }
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
$isFileUpload = ($_POST['source'] ?? '') === 'file_upload';
$primaryTopic = trim((string)($topics[0] ?? ''));
?>

<!-- ═══ HERO ═══ -->
<div class="fp-hero">
    <div class="fp-hero-breadcrumb fp-fade-up">
        <i class="fas fa-home"></i>
        <a href="index.php">Topics</a>
        <i class="fas fa-chevron-right" style="font-size:0.6rem;"></i>
        <span>Finalize</span>
    </div>
    <div class="fp-progress fp-fade-up fp-delay-1" aria-label="Paper creation progress">
        <span class="fp-progress-step done"><i class="fas fa-check"></i> Source</span>
        <span class="fp-progress-line"></span>
        <span class="fp-progress-step active"><span>2</span> Configure</span>
        <span class="fp-progress-line"></span>
        <span class="fp-progress-step"><span>3</span> Generate</span>
    </div>
    <h1 class="fp-hero-title fp-fade-up fp-delay-1">Finalize Your Paper</h1>
    <p class="fp-hero-sub fp-fade-up fp-delay-2">Review your source, set the paper mix, and generate your PDF.</p>
</div>

<!-- ═══ MAIN CONTENT ═══ -->
<div class="fp-wrapper">

    <div class="fp-source-status fp-fade-up" role="status">
        <div class="fp-source-status-icon"><i class="fas <?= $isFileUpload ? 'fa-file-alt' : 'fa-layer-group' ?>"></i></div>
        <div class="fp-source-status-copy">
            <strong><?= $isFileUpload ? 'File uploaded successfully' : 'Topic selection ready' ?></strong>
            <span><?= $isFileUpload ? 'Questions will be generated only from the uploaded file content.' : 'Your selected topics are ready for paper configuration.' ?></span>
            <?php if ($primaryTopic !== ''): ?><small>Source: <?= htmlspecialchars($primaryTopic) ?></small><?php endif; ?>
        </div>
        <span class="fp-source-status-badge"><i class="fas fa-shield-alt"></i> <?= $isFileUpload ? 'File-based' : 'Topic-based' ?></span>
    </div>

    <!-- ── STEP 1: Question Quantities ── -->
    <form action="generate_ai_paper.php" method="POST" id="configForm" class="fp-builder-form">
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

    <div class="fp-card fp-quantity-card fp-fade-up fp-delay-1">
        <div class="fp-card-header">
            <div class="fp-step-badge">1</div>
            <div class="fp-card-header-text">
                <h3>Question Quantities</h3>
                <p>Set how many questions for each section</p>
            </div>
        </div>
        <div class="fp-card-body">
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
    <div class="fp-card fp-difficulty-card fp-fade-up fp-delay-2">
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
    <div class="fp-topics-card fp-topics-panel fp-fade-up fp-delay-3">
        <div class="fp-topics-header" onclick="toggleTopics()">
            <div class="fp-topics-header-left">
                <span class="fp-topics-step-badge">3</span>
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
        <div class="fp-generation-summary" aria-live="polite">
            <div><i class="fas fa-list-ol"></i><span><strong id="summaryTotal"><?= (int)(($showMcqs ? 10 : 0) + ($showShort ? 5 : 0) + ($showLong ? 3 : 0)) ?></strong> total questions</span></div>
            <div><i class="fas fa-sliders-h"></i><span><strong id="summaryDifficulty">Medium</strong> difficulty</span></div>
            <div><i class="fas fa-file-pdf"></i><span>PDF output</span></div>
        </div>
        <p class="fp-generate-help">Everything is ready. Generate your paper when you are happy with the counts and difficulty.</p>
        <button type="button" class="fp-primary-cta" id="generatePaperButton" onclick="handleGeneratePaper()">
            <span class="fp-primary-cta-icon"><i class="fas fa-magic"></i></span>
            <span class="fp-primary-cta-copy"><strong>Generate question paper</strong><small>Create PDF from these settings</small></span>
            <i class="fas fa-arrow-right fp-primary-cta-arrow"></i>
        </button>
        <div class="btn-wrapper fp-legacy-generate-button" onclick="handleGeneratePaper()">
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
    <div class="fp-generation-overlay" id="generationOverlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="generationOverlayTitle">
        <div class="fp-loader-card">
            <div class="fp-loader-sheet" aria-hidden="true">
                <span></span><span></span><span></span>
                <i class="fas fa-magic"></i>
            </div>
            <h2 id="generationOverlayTitle">Building your paper</h2>
            <p id="generationOverlayMessage">Your questions are being prepared. This usually takes a few moments.</p>
            <div class="fp-loader-track" aria-hidden="true"><span></span></div>
            <small><i class="fas fa-lock"></i> Keep this tab open while we generate your PDF.</small>
        </div>
    </div>

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
    let paperGenerationStarted = false;

    function showGenerationLoader() {
        if (paperGenerationStarted) return;

        paperGenerationStarted = true;
        const overlay = document.getElementById('generationOverlay');
        const button = document.getElementById('generatePaperButton');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.innerHTML = '<span class="fp-primary-cta-icon"><i class="fas fa-spinner fa-spin"></i></span><span class="fp-primary-cta-copy"><strong>Starting generation…</strong><small>Preparing your paper</small></span>';
        }

        if (overlay) {
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
        }

        document.body.classList.add('fp-generation-lock');
    }

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
        updatePaperSummary();
    }

    function handleGeneratePaper() {
        if (paperGenerationStarted) return;

        const mcqs = parseInt(document.getElementById('inputMcqs').value) || 0;
        const shorts = parseInt(document.getElementById('inputShort').value) || 0;
        const longs = parseInt(document.getElementById('inputLong').value) || 0;

        if (mcqs + shorts + longs === 0) {
            alert('Add at least one question before generating the paper.');
            return;
        }

        if (!isPremium) {
            if (mcqs > questionLimits.inputMcqs || shorts > questionLimits.inputShort || longs > questionLimits.inputLong) {
                showUpgradeModal();
                return;
            }
        }

        showGenerationLoader();
        window.setTimeout(() => document.getElementById('configForm').submit(), 80);
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
                    updatePaperSummary();
                });
            }
        });
    }

    function setDifficulty(level) {
        document.getElementById('difficultyInput').value = level;
        document.querySelectorAll('.fp-diff-pill').forEach(btn => btn.classList.remove('active'));
        const active = document.querySelector(`.fp-diff-pill[data-diff="${level}"]`);
        if (active) active.classList.add('active');
        const summaryDifficulty = document.getElementById('summaryDifficulty');
        if (summaryDifficulty) summaryDifficulty.textContent = level.charAt(0).toUpperCase() + level.slice(1);
    }

    function updatePaperSummary() {
        const mcqs = parseInt(document.getElementById('inputMcqs')?.value) || 0;
        const shorts = parseInt(document.getElementById('inputShort')?.value) || 0;
        const longs = parseInt(document.getElementById('inputLong')?.value) || 0;
        const total = document.getElementById('summaryTotal');
        if (total) total.textContent = mcqs + shorts + longs;
    }

    function toggleTopics() {
        const body = document.getElementById('topicsBody');
        const icon = document.getElementById('topicsToggleIcon');
        body.classList.toggle('collapsed');
        icon.style.transform = body.classList.contains('collapsed') ? 'rotate(-90deg)' : 'rotate(0)';
    }

    document.getElementById('configForm')?.addEventListener('submit', function(event) {
        if (paperGenerationStarted) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        handleGeneratePaper();
    });

    updatePaperSummary();
</script>

<?php include __DIR__ . '/../footer.php'; ?>
