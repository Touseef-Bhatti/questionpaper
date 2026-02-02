<?php
$pageTitle = "Finalize Paper | Intelligent Paper Builder";
$metaDescription = "Configure your paper layout, choose question counts, and generate your PDF assessment.";
require_once __DIR__ . '/../header.php';
?>
<link rel="stylesheet" href="../css/paper-builder.css">

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
                                <i class="fas fa-check text-success small"></i>
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
                                <i class="fas fa-check text-success small"></i>
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
                                <i class="fas fa-check text-success small"></i>
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
                                <i class="fas fa-check text-success small"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top border-light text-center">
                        <a href="index.php" class="btn btn-sm btn-link text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Adjust Selection
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
                
                <div class="p-4 p-md-5 bg-white">
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
                        <div class="config-section mb-4" style="<?= $showMcqs ? '' : 'display:none;' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0 text-dark">Multiple Choice Questions</h6>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small" style="font-size: 0.7rem;">MCQs</span>
                                    </div>
                                    <small class="text-muted d-block">Select the number of MCQs to generate.</small>
                                </div>
                                
                                <div class="quantity-control">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputMcqs', -1)">
                                        <i class="fas fa-minus small"></i>
                                    </button>
                                    <input type="number" name="total_mcqs" id="inputMcqs" class="config-input-number" value="<?= $showMcqs ? 10 : 0 ?>" min="0" max="100">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputMcqs', 1)">
                                        <i class="fas fa-plus small"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Short Questions Section -->
                        <div class="config-section mb-4" style="<?= $showShort ? '' : 'display:none;' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0 text-dark">Short Questions</h6>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small" style="font-size: 0.7rem;">Short</span>
                                    </div>
                                    <small class="text-muted d-block">Define how many short questions to include.</small>
                                </div>
                                
                                <div class="quantity-control">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputShort', -1)">
                                        <i class="fas fa-minus small"></i>
                                    </button>
                                    <input type="number" name="total_shorts" id="inputShort" class="config-input-number" value="<?= $showShort ? 5 : 0 ?>" min="0" max="50">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputShort', 1)">
                                        <i class="fas fa-plus small"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Long Questions Section -->
                        <div class="config-section mb-4" style="<?= $showLong ? '' : 'display:none;' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0 text-dark">Long Questions</h6>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1 small" style="font-size: 0.7rem;">Long</span>
                                    </div>
                                    <small class="text-muted d-block">Set the count for detailed long questions.</small>
                                </div>
                                
                                <div class="quantity-control">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputLong', -1)">
                                        <i class="fas fa-minus small"></i>
                                    </button>
                                    <input type="number" name="total_longs" id="inputLong" class="config-input-number" value="<?= $showLong ? 3 : 0 ?>" min="0" max="25">
                                    <button type="button" class="btn-quantity" onclick="updateQuantity('inputLong', 1)">
                                        <i class="fas fa-plus small"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5 border-light">

                        <button type="submit" class="btn-finalize">
                            <i class="fas fa-magic"></i>
                            Generate Professional Paper
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
