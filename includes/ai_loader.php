<?php
/**
 * includes/ai_loader.php
 *
 * Reusable AI step-loader modal.
 *
 * USAGE — include this file anywhere you need the loader:
 *   <?php include __DIR__ . '/../includes/ai_loader.php'; ?>
 *   (Adjust the relative path depth as needed.)
 *
 * Also include the assets in your <head>:
 *   <link rel="stylesheet" href="path/to/css/ai_loader.css">
 *   <script src="path/to/js/ai_loader.js" defer></script>
 *
 * Then trigger from JavaScript:
 *   showAILoader(
 *     [
 *       { label: 'Analyzing topics',   duration: 3500 },
 *       { label: 'Extracting concepts', duration: 3500 },
 *       { label: 'Designing MCQs',     duration: 3500 },
 *       { label: 'Validating results',  duration: 3500 },
 *       { label: 'Finalizing paper',    duration: 3500 },
 *     ],
 *     'Our AI is generating your quiz...'  // Optional note text
 *   );
 */
?>
<!-- AI Step Loader Modal — include/ai_loader.php -->
<div class="ai-loader-overlay" id="aiLoaderModal" aria-modal="true" role="dialog" aria-label="Loading">
    <div class="ai-loader-card">

        <!-- Animated robot / custom icon -->
        <div class="ai-loader-icon-container">
            <div class="ai-loader-icon-glow"></div>
            <i class="fas fa-robot" style="color:white; z-index:2; position:relative;" id="aiLoaderIcon"></i>
        </div>

        <h2 class="ai-loader-title" id="aiLoaderTitle">Processing&hellip;</h2>

        <!-- Steps are injected dynamically by ai_loader.js -->
        <div class="ai-loader-steps" id="aiLoaderSteps"></div>

        <!-- Progress bar -->
        <div class="ai-loader-progress-wrap">
            <div class="ai-loader-progress-bar" id="aiLoaderProgressBar"></div>
        </div>

        <!-- Optional note -->
        <div class="ai-loader-note" id="aiLoaderNote">
            <i class="fas fa-info-circle"></i>
            <span>Please wait while we prepare your content&hellip;</span>
        </div>

    </div>
</div>
