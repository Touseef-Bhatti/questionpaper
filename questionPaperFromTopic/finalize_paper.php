<?php
$pageTitle = "Finalize Paper | Intelligent Paper Builder";
$metaDescription = "Configure your paper layout, choose question counts, and generate your PDF assessment.";
require_once __DIR__ . '/../header.php';
?>
<link rel="stylesheet" href="../css/paper-builder.css?v=<?= time() . rand(11000, 12000) ?>">
<link rel="stylesheet" href="../css/buttons.css?v=<?= time() . rand(1, 1000) ?>">

<?php
// Ensure we have topics to process
$topics = $_POST['topics'] ?? [];
$activeTypes = $_POST['active_types'] ?? [];

// Get Categorized Topics
$topicsMcqs = $_POST['topics_mcqs'] ?? [];
$topicsShort = $_POST['topics_short'] ?? [];
$topicsLong = $_POST['topics_long'] ?? [];

if (empty($topics)) {
    // Redirect back if accessed directly without data
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Convert comma-separated string back to array if needed
if (!is_array($topics)) $topics = explode(',', $topics);
if (!is_array($activeTypes)) $activeTypes = explode(',', $activeTypes);
if (!is_array($topicsMcqs)) $topicsMcqs = explode(',', $topicsMcqs);
if (!is_array($topicsShort)) $topicsShort = explode(',', $topicsShort);
if (!is_array($topicsLong)) $topicsLong = explode(',', $topicsLong);

// Determine visibility based on active types or defaults
$showMcqs = in_array('mcqs', $activeTypes) || empty($activeTypes);
$showShort = in_array('short', $activeTypes) || empty($activeTypes);
$showLong = in_array('long', $activeTypes) || empty($activeTypes);
?>

<div class="hero-builder py-5 mb-4" style="padding-bottom: 2rem !important; padding-top: 3rem !important;">
    <div class="container text-center animate-fade-up">
        <h1 class="hero-title" style="font-size: 2.5rem;">Finalize Assessment</h1>
        <p class="hero-subtitle mb-0">Configure your paper structure and generate the final PDF.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <!-- Main Column -->
        <div class="col-lg-12">
            <div class="row g-4 justify-content-center">
        
        <!-- Left Column: Selected Topics Summary -->
        <div class="col-lg-4 order-lg-2 animate-fade-up delay-100">
            <div class="selected-panel sticky-sidebar" style="top: 2rem;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-layer-group text-primary"></i>
                        Selected Topics
                    </div>
                    <span class="badge bg-light text-secondary border"><?= count($topics) ?> Total</span>
                </div>
                
                <div class="panel-body">
                    <!-- MCQs Topics -->
                    <?php if (!empty($topicsMcqs)): ?>
                        <div class="selected-group-title text-primary">
                            <i class="fas fa-list-ul me-1"></i> MCQs
                        </div>
                        <?php foreach($topicsMcqs as $topic): ?>
                            <div class="selected-chip">
                                <span><?= htmlspecialchars($topic) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check text-success small"></i>
                                    <div class="btn-remove" onclick="this.closest('.selected-chip').remove()"><i class="fas fa-times"></i></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mb-4"></div>
                    <?php endif; ?>

                    <!-- Short Topics -->
                    <?php if (!empty($topicsShort)): ?>
                        <div class="selected-group-title text-success">
                            <i class="fas fa-align-left me-1"></i> Short Questions
                        </div>
                        <?php foreach($topicsShort as $topic): ?>
                            <div class="selected-chip">
                                <span><?= htmlspecialchars($topic) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check text-success small); ?>"></i>
                                    <div class="btn-remove" onclick="this.closest('.selected-chip').remove()"><i class="fas fa-times"></i></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mb-4"></div>
                    <?php endif; ?>

                    <!-- Long Topics -->
                    <?php if (!empty($topicsLong)): ?>
                        <div class="selected-group-title text-warning">
                            <i class="fas fa-align-justify me-1"></i> Long Questions
                        </div>
                        <?php foreach($topicsLong as $topic): ?>
                            <div class="selected-chip">
                                <span><?= htmlspecialchars($topic) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check text-success small"></i>
                                    <div class="btn-remove" onclick="this.closest('.selected-chip').remove()"><i class="fas fa-times"></i></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mb-4"></div>
                    <?php endif; ?>

                    <!-- Fallback for uncategorized -->
                    <?php if (empty($topicsMcqs) && empty($topicsShort) && empty($topicsLong) && !empty($topics)): ?>
                        <div class="selected-group-title">General Selection</div>
                        <?php foreach($topics as $topic): ?>
                            <div class="selected-chip">
                                <span><?= htmlspecialchars($topic) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-check text-success small"></i>
                                    <div class="btn-remove" onclick="this.closest('.selected-chip').remove()"><i class="fas fa-times"></i></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="mt-4 pt-4 border-top border-light text-center">
                        <a href="index.php" class="btn-adjust">
                            <i class="fas fa-arrow-left"></i> Adjust Selection
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Configuration Form -->
        <div class="col-lg-8 order-lg-1 animate-fade-up">
            <div class="config-card">
                <div class="config-header">
                    <h4 class="mb-1 text-white">Paper Configuration</h4>
                    <p class="mb-0 text-white-50 small">Fine-tune the number of questions for each section.</p>
                </div>
                
                <div class="p-4 p-md-5" style="background: #ffffff;">
                    <form action="generate_ai_paper.php" method="POST" id="configForm">
                        <!-- Hidden Inputs -->
                        <?php foreach($topics as $t): ?><input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                        <?php foreach($topicsMcqs as $t): ?><input type="hidden" name="topics_mcqs[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                        <?php foreach($topicsShort as $t): ?><input type="hidden" name="topics_short[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                        <?php foreach($topicsLong as $t): ?><input type="hidden" name="topics_long[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                        
                        <input type="hidden" name="source" value="topics">
                        <input type="hidden" name="class_id" value="0">
                        <input type="hidden" name="book_name" value="Professional Academic Paper">
                        <input type="hidden" name="pattern_mode" value="without">

                         <!-- MCQ Section -->
                        <div class="config-section type-mcq animate-fade-up" style="<?= $showMcqs ? '' : 'display:none;' ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="section-icon bg-type-mcq">
                                    <i class="fas fa-list-ol"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0">Multiple Choice Questions</h6>
                                    </div>
                                    <small class="text-muted">Select the quantity of MCQs.</small>
                                </div>
                                
                                <div class="quantity-control px-2">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputMcqs', -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" name="total_mcqs" id="inputMcqs" class="config-input-number" value="<?= $showMcqs ? 10 : 0 ?>" min="0" max="100">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputMcqs', 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Short Questions Section -->
                        <div class="config-section type-short animate-fade-up" style="<?= $showShort ? '' : 'display:none;' ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="section-icon bg-type-short">
                                    <i class="fas fa-align-left"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0">Short Questions</h6>
                                    </div>
                                    <small class="text-muted">Number of short answer tasks.</small>
                                </div>
                                
                                <div class="quantity-control px-2">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputShort', -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" name="total_shorts" id="inputShort" class="config-input-number" value="<?= $showShort ? 5 : 0 ?>" min="0" max="50">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputShort', 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Long Questions Section -->
                        <div class="config-section type-long animate-fade-up" style="<?= $showLong ? '' : 'display:none;' ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="section-icon bg-type-long">
                                    <i class="fas fa-align-justify"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0">Long Questions</h6>
                                    </div>
                                    <small class="text-muted">Quantity of descriptive items.</small>
                                </div>
                                
                                <div class="quantity-control px-2">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputLong', -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" name="total_longs" id="inputLong" class="config-input-number" value="<?= $showLong ? 3 : 0 ?>" min="0" max="25">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputLong', 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5" style="border-color: #e2e8f0;">

                        <div class="btn-wrapper" onclick="document.getElementById('configForm').submit();" style="cursor: pointer; transform: scale(0.9); display: flex; justify-content: center; margin: 0 auto;">
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
                    </form>
                </div>
            </div>
                </div> <!-- End Inner Row -->
            </div> <!-- End Main Column -->
        </div>
    </div>
    
    <!-- SEO Optimization Footer for Configuration -->
    <article class="mt-5 text-center animate-fade-up seo-footer" style="max-width: 1000px; margin: 0 auto;">
        <h3 class="fw-bold mb-3">Intelligent Paper Generator for Educators</h3>
        <p class="text-muted">
            Empower your teaching workflow with our high-precision assessment toolkit. We craft test frameworks by syncing precise quantities for <strong>Multiple Choice (MCQs), Short Answer components, and Essay parameters</strong> that align perfectly with standard Board, ECAT, and MDCAT rubrics. 
        </p>
        <div class="badge-group">
            <span class="badge-seo"><i class="fas fa-file-pdf"></i> PDF Output</span>
            <span class="badge-seo"><i class="fas fa-bullseye"></i> Exam-aligned Difficulty</span>
            <span class="badge-seo"><i class="fas fa-calendar-check"></i> 2026 Curriculum Standard</span>
        </div>
    </article>
</div>

<script>
    function updateQuantity(inputId, change) {
        const input = document.getElementById(inputId);
        let val = parseInt(input.value) || 0;
        const min = parseInt(input.min) || 0;
        const max = parseInt(input.max) || 100;
        
        val += change;
        if (val < min) val = min;
        if (val > max) val = max;
        
        input.value = val;
    }
</script>

<?php include __DIR__ . '/../footer.php'; ?>
