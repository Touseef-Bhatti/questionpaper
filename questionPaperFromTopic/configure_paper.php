<?php
require_once __DIR__ . '/../header.php';
$questionType = $_POST['question_type'] ?? 'mcqs';
$topics = $_POST['topics'] ?? [];
$total_mcqs = $_POST['total_mcqs'] ?? 10;
$total_shorts = $_POST['total_shorts'] ?? 5;
$total_longs = $_POST['total_longs'] ?? 3;

if (empty($topics)) {
    echo "<script>alert('Please select at least one topic.'); window.history.back();</script>";
    exit;
}
?>

<style>
    .page-wrapper {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center; /* Perfect vertical centering */
        padding: 20px;
        background: #f1f5f9;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        transform: translateZ(0);
    }

    .config-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        padding: 40px;
        width: 100%;
        max-width: 650px;
        animation: cardAppear 0.4s ease-out;
        will-change: transform, opacity;
        transform: translateZ(0);
    }

    @keyframes cardAppear {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .config-card:hover {
        border-color: #cbd5e1;
    }

    .section-header {
        text-align: center;
        margin-bottom: 35px;
    }

    .section-header i {
        font-size: 3.5rem;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 20px;
        display: inline-block;
    }

    .section-header h3 {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
        margin: 0 0 8px 0;
    }

    .section-header p {
        color: #64748b;
        font-size: 1rem;
        margin: 0;
    }

    .form-group {
        margin-bottom: 30px;
    }

    .form-label {
        font-size: 0.8rem;
        font-weight: 800;
        color: #94a3b8;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .badge-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .topic-badge {
        padding: 8px 16px;
        border-radius: 100px;
        background: #f8fafc;
        color: #4f46e5;
        border: 1px solid #e2e8f0;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .topic-badge:hover {
        background: #eef2ff;
        transform: translateY(-1px);
    }

    .selection-group {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 40px;
    }

    .count-input-wrapper {
        display: flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        overflow: hidden;
        transition: border-color 0.2s ease;
    }

    .count-input-wrapper:focus-within {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .count-input-icon {
        background: #f1f5f9;
        padding: 12px 16px;
        color: #64748b;
        border-right: 1px solid #e2e8f0;
    }

    .form-control-custom {
        flex: 1;
        border: none;
        padding: 12px 16px;
        font-weight: 700;
        color: #1e293b;
        outline: none;
        width: 100%;
        background: transparent;
        font-size: 1rem;
    }

    .input-item {
        margin-bottom: 24px;
    }

    .input-item:last-child {
        margin-bottom: 0;
    }

    .btn-generate {
        background: #4f46e5;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 20px;
        font-weight: 700;
        font-size: 1.1rem;
        width: 100%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.2s ease;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .btn-generate:hover {
        background: #4338ca;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
    }

    @media (max-width: 650px) {
        .page-wrapper {
            padding: 10px;
            align-items: flex-start; /* Better for long content on mobile */
        }
        .config-card {
            padding: 24px 16px;
            border-radius: 20px;
            margin-top: 20px;
        }
        .section-header h3 {
            font-size: 1.75rem;
        }
        .selection-group {
            padding: 20px;
        }
        .btn-generate {
            padding: 16px;
            font-size: 1rem;
        }
    }
</style>

<div class="page-wrapper">
    <div class="config-card">
        <div class="section-header">
            <i class="fas fa-sliders-h"></i>
            <h3>Configure Your Paper</h3>
            <p>Customize the layout and question distribution</p>
        </div>
        
        <form action="generate_ai_paper.php" method="POST">
            <input type="hidden" name="source" value="topics">
            <input type="hidden" name="question_type" value="<?= htmlspecialchars($questionType) ?>">
            <?php foreach($topics as $t): ?>
                <input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>">
            <?php endforeach; ?>
            
            <div class="form-group">
                <label class="form-label"><i class="fas fa-tags text-primary"></i> Selected Topics</label>
                <div class="badge-container">
                    <?php foreach($topics as $t): ?>
                        <span class="topic-badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="selection-group">
                <?php if ($questionType === 'mcqs' || $questionType === 'all'): ?>
                <div class="input-item">
                    <label class="form-label"><i class="fas fa-list-ul text-primary"></i> Total MCQs</label>
                    <div class="count-input-wrapper">
                        <div class="count-input-icon"><i class="fas fa-hashtag"></i></div>
                        <input type="number" name="total_mcqs" class="form-control-custom" value="<?= htmlspecialchars($total_mcqs) ?>" min="0" max="100" required>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($questionType === 'short' || $questionType === 'all'): ?>
                <div class="input-item">
                    <label class="form-label"><i class="fas fa-align-left text-primary"></i> Total Short Questions</label>
                    <div class="count-input-wrapper">
                        <div class="count-input-icon"><i class="fas fa-hashtag"></i></div>
                        <input type="number" name="total_shorts" class="form-control-custom" value="<?= htmlspecialchars($total_shorts) ?>" min="0" max="100" required>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($questionType === 'long' || $questionType === 'all'): ?>
                <div class="input-item">
                    <label class="form-label"><i class="fas fa-paragraph text-primary"></i> Total Long Questions</label>
                    <div class="count-input-wrapper">
                        <div class="count-input-icon"><i class="fas fa-hashtag"></i></div>
                        <input type="number" name="total_longs" class="form-control-custom" value="<?= htmlspecialchars($total_longs) ?>" min="0" max="100" required>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filler fields to satisfy standard paper generation defaults -->
            <input type="hidden" name="class_id" value="0">
            <input type="hidden" name="book_name" value="Custom Topic Paper">
            <input type="hidden" name="pattern_mode" value="without">
            
            <button type="submit" class="btn-generate">
                <i class="fas fa-magic"></i> Generate Professional Paper
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

