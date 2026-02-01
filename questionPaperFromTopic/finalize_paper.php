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

// Convert comma-separated string back to array if needed (though it should be array from form)
if (!is_array($topics)) $topics = explode(',', $topics);
if (!is_array($activeTypes)) $activeTypes = explode(',', $activeTypes);
if (!is_array($topicsMcqs)) $topicsMcqs = explode(',', $topicsMcqs);
if (!is_array($topicsShort)) $topicsShort = explode(',', $topicsShort);
if (!is_array($topicsLong)) $topicsLong = explode(',', $topicsLong);

// If categorized arrays are empty but we have topics and types (legacy/direct handling), try to distribute or default
// Ideally we rely on the inputs. If all categorized are empty, we might fall back to showing all under "General" or active types.

// Determine visibility based on active types or defaults
$showMcqs = in_array('mcqs', $activeTypes) || empty($activeTypes);
$showShort = in_array('short', $activeTypes) || empty($activeTypes);
$showLong = in_array('long', $activeTypes) || empty($activeTypes);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="text-center mb-5 animate-fade-up">
                <h1 class="display-5 fw-bold text-primary mb-3">Finalize Your Assessment</h1>
                <p class="lead text-muted">Review selected topics and configure your paper structure.</p>
            </div>

            <div class="row g-4">
                <!-- Left Column: Selected Topics -->
                <div class="col-md-5 order-md-2 animate-fade-up delay-100">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white" style="border: 1px solid rgba(0,0,0,0.05) !important;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark d-flex align-items-center">
                                <span class="icon-circle-sm bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="fas fa-list-ul"></i>
                                </span>
                                Selected Topics
                            </h5>
                            
                            <!-- MCQs Topics -->
                            <?php if (!empty($topicsMcqs)): ?>
                                <div class="mb-4">
                                    <div class="small fw-bold text-uppercase mb-2 text-primary opacity-75" style="letter-spacing: 0.05em;">
                                        <i class="fas fa-layer-group me-1"></i> Multiple Choice <span class="badge bg-white text-dark border ms-1"><?= count($topicsMcqs) ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($topicsMcqs as $topic): ?>
                                            <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm d-flex align-items-center" style="border-left: 3px solid #3b82f6 !important;">
                                                <?= htmlspecialchars($topic) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Short Topics -->
                            <?php if (!empty($topicsShort)): ?>
                                <div class="mb-4">
                                    <div class="small fw-bold text-uppercase mb-2 text-success opacity-75" style="letter-spacing: 0.05em;">
                                        <i class="fas fa-layer-group me-1"></i> Short Questions <span class="badge bg-white text-dark border ms-1"><?= count($topicsShort) ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($topicsShort as $topic): ?>
                                            <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm d-flex align-items-center" style="border-left: 3px solid #10b981 !important;">
                                                <?= htmlspecialchars($topic) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Long Topics -->
                            <?php if (!empty($topicsLong)): ?>
                                <div class="mb-4">
                                    <div class="small fw-bold text-uppercase mb-2 text-warning opacity-75" style="letter-spacing: 0.05em;">
                                        <i class="fas fa-layer-group me-1"></i> Long Questions <span class="badge bg-white text-dark border ms-1"><?= count($topicsLong) ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($topicsLong as $topic): ?>
                                            <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm d-flex align-items-center" style="border-left: 3px solid #f59e0b !important;">
                                                <?= htmlspecialchars($topic) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Fallback for uncategorized/legacy - but try to categorize if possible -->
                            <?php if (empty($topicsMcqs) && empty($topicsShort) && empty($topicsLong) && !empty($topics)): ?>
                                <!-- If we have topics but no explicit categories (legacy submit), show them all under a general 'Selected Topics' group -->
                                <div class="mb-4">
                                    <div class="small fw-bold text-uppercase mb-2 text-secondary opacity-75" style="letter-spacing: 0.05em;">
                                        <i class="fas fa-layer-group me-1"></i> General Selection <span class="badge bg-white text-dark border ms-1"><?= count($topics) ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php foreach($topics as $topic): ?>
                                            <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm d-flex align-items-center">
                                                <i class="fas fa-check-circle text-success me-2 small"></i>
                                                <?= htmlspecialchars($topic) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="alert alert-info border-0 bg-white shadow-sm rounded-3 mt-4">
                                <small><i class="fas fa-info-circle me-1"></i> Our AI will generate questions based specifically on these topics.</small>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                    <i class="fas fa-arrow-left me-1"></i> Edit Selection
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Configuration Form -->
                <div class="col-md-7 order-md-1 animate-fade-up">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header card-header-gradient text-white p-4 border-0">
                            <h4 class="mb-0 fw-bold d-flex align-items-center">
                                <span class="icon-circle-sm bg-white bg-opacity-25 text-white me-3">
                                    <i class="fas fa-sliders-h"></i>
                                </span>
                                Paper Configuration
                            </h4>
                        </div>
                        <div class="card-body p-5">
                            <form action="generate_ai_paper.php" method="POST" id="configForm">
                                <!-- Pass topics forward -->
                                <?php foreach($topics as $t): ?>
                                    <input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>">
                                <?php endforeach; ?>
                                
                                <!-- Pass Categorized Topics -->
                                <?php foreach($topicsMcqs as $t): ?>
                                    <input type="hidden" name="topics_mcqs[]" value="<?= htmlspecialchars($t) ?>">
                                <?php endforeach; ?>
                                <?php foreach($topicsShort as $t): ?>
                                    <input type="hidden" name="topics_short[]" value="<?= htmlspecialchars($t) ?>">
                                <?php endforeach; ?>
                                <?php foreach($topicsLong as $t): ?>
                                    <input type="hidden" name="topics_long[]" value="<?= htmlspecialchars($t) ?>">
                                <?php endforeach; ?>
                                
                                <input type="hidden" name="source" value="topics">
                                <input type="hidden" name="class_id" value="0">
                                <input type="hidden" name="book_name" value="Professional Academic Paper">
                                <input type="hidden" name="pattern_mode" value="without">

                                <!-- MCQ Section -->
                                <div class="config-section type-mcq-section mb-4" style="<?= $showMcqs ? '' : 'display:none;' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold mb-0 text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Multiple Choice Questions</label>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill border border-primary px-3">MCQs</span>
                                    </div>
                                    <div class="range-wrap">
                                        <div class="d-flex align-items-center gap-3">
                                            <input type="range" class="form-range flex-grow-1" id="rangeMcqs" min="0" max="50" value="<?= $showMcqs ? 10 : 0 ?>" oninput="syncInput('rangeMcqs', 'inputMcqs')">
                                            <input type="number" name="total_mcqs" id="inputMcqs" class="form-control config-input-number text-center" style="width: 80px;" value="<?= $showMcqs ? 10 : 0 ?>" min="0" max="100" oninput="syncInput('inputMcqs', 'rangeMcqs')">
                                        </div>
                                    </div>
                                </div>

                                <!-- Short Questions Section -->
                                <div class="config-section type-short-section mb-4" style="<?= $showShort ? '' : 'display:none;' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold mb-0 text-dark"><i class="fas fa-align-left me-2 text-success"></i>Short Questions</label>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill border border-success px-3">Short</span>
                                    </div>
                                    <div class="range-wrap">
                                        <div class="d-flex align-items-center gap-3">
                                            <input type="range" class="form-range flex-grow-1" id="rangeShort" min="0" max="30" value="<?= $showShort ? 5 : 0 ?>" oninput="syncInput('rangeShort', 'inputShort')">
                                            <input type="number" name="total_shorts" id="inputShort" class="form-control config-input-number text-center" style="width: 80px;" value="<?= $showShort ? 5 : 0 ?>" min="0" max="50" oninput="syncInput('inputShort', 'rangeShort')">
                                        </div>
                                    </div>
                                </div>

                                <!-- Long Questions Section -->
                                <div class="config-section type-long-section mb-4" style="<?= $showLong ? '' : 'display:none;' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold mb-0 text-dark"><i class="fas fa-align-justify me-2 text-warning"></i>Long Questions</label>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill border border-warning px-3">Long</span>
                                    </div>
                                    <div class="range-wrap">
                                        <div class="d-flex align-items-center gap-3">
                                            <input type="range" class="form-range flex-grow-1" id="rangeLong" min="0" max="10" value="<?= $showLong ? 3 : 0 ?>" oninput="syncInput('rangeLong', 'inputLong')">
                                            <input type="number" name="total_longs" id="inputLong" class="form-control config-input-number text-center" style="width: 80px;" value="<?= $showLong ? 3 : 0 ?>" min="0" max="25" oninput="syncInput('inputLong', 'rangeLong')">
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-generate w-100 shadow-lg">
                                        <i class="fas fa-magic me-2"></i> Generate Professional Paper
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function syncInput(sourceId, targetId) {
        const val = document.getElementById(sourceId).value;
        document.getElementById(targetId).value = val;
    }
</script>

<?php include __DIR__ . '/../footer.php'; ?>
