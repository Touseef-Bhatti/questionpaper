<?php
require_once __DIR__ . '/../header.php';

$topics = $_POST['topics'] ?? [];
if (empty($topics)) {
    echo "<script>alert('Please select at least one topic.'); window.history.back();</script>";
    exit;
}
?>

<div class="container main-content pt-5">
    <div class="card shadow-sm" style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h3 class="text-center mb-4">Configure Your Paper</h3>
        
        <form action="../generate_question_paper.php" method="POST">
            <input type="hidden" name="source" value="topics">
            <?php foreach($topics as $t): ?>
                <input type="hidden" name="topics[]" value="<?= htmlspecialchars($t) ?>">
            <?php endforeach; ?>
            
            <div class="mb-4">
                <label class="form-label fw-bold">Selected Topics:</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($topics as $t): ?>
                        <span class="badge bg-primary"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Total MCQs</label>
                <select name="total_mcqs" class="form-select" required>
                    <option value="10">10 MCQs</option>
                    <option value="20" selected>20 MCQs</option>
                    <option value="30">30 MCQs</option>
                    <option value="40">40 MCQs</option>
                    <option value="50">50 MCQs</option>
                </select>
                <div class="form-text">Questions will be randomly selected from the above topics.</div>
            </div>

            <!-- Filler fields to satisfy standard paper generation defaults -->
            <input type="hidden" name="class_id" value="0">
            <input type="hidden" name="book_name" value="Custom Topic Paper">
            <input type="hidden" name="pattern_mode" value="without">
            
            <button type="submit" class="btn btn-success btn-lg w-100">Generate Paper</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
