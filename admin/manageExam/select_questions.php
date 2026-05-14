<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$exam_id = intval($_GET['id'] ?? 0);
if (!$exam_id) header("Location: index.php");

// Fetch exam details
$stmt = $conn->prepare("SELECT * FROM exam_preparations WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) die("Exam not found.");

include_once __DIR__ . '/../header.php';

// Prepare query for questions
$chapter_ids = $exam['chapter_ids'];
$where_chapters = $chapter_ids ? "AND ch.chapter_id IN ($chapter_ids)" : "";

// Handle saving selected questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selection'])) {
    $selected_ids = isset($_POST['question_ids']) ? json_encode($_POST['question_ids']) : '[]';
    $upd = $conn->prepare("UPDATE exam_preparations SET question_ids = ? WHERE id = ?");
    $upd->bind_param("si", $selected_ids, $exam_id);
    if ($upd->execute()) {
        header("Location: index.php?msg=created");
        exit;
    }
}

// Current selected questions
$current_selection = json_decode($exam['question_ids'] ?? '[]', true);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>🔍 Select Questions for: <?= htmlspecialchars($exam['title']) ?></h2>
            <p class="text-muted">Class ID: <?= $exam['class_id'] ?> | Book ID: <?= $exam['book_id'] ?> | Chapters: <?= $exam['chapter_ids'] ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>

    <form method="POST">
        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Selected Questions: <span id="selected-count"><?= count($current_selection) ?></span></h5>
                <button type="submit" name="save_selection" class="btn btn-success">Save Selection</button>
            </div>
        </div>

        <div class="row">
            <!-- MCQs Section -->
            <div class="col-12 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">MCQs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">Select</th>
                                        <th>Question</th>
                                        <th>Chapter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $mcq_query = "SELECT m.*, ch.chapter_name FROM mcqs m 
                                                 JOIN chapter ch ON m.chapter_id = ch.chapter_id
                                                 WHERE m.class_id = {$exam['class_id']} 
                                                 AND m.book_id = {$exam['book_id']} 
                                                 $where_chapters";
                                    $mcqs = $conn->query($mcq_query);
                                    while ($m = $mcqs->fetch_assoc()):
                                        $qid = "mcq_" . $m['mcq_id'];
                                        $checked = in_array($qid, $current_selection) ? 'checked' : '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="question_ids[]" value="<?= $qid ?>" class="q-check" <?= $checked ?>></td>
                                            <td><?= htmlspecialchars($m['question']) ?></td>
                                            <td><?= htmlspecialchars($m['chapter_name']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Short Questions Section -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Short Questions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">Select</th>
                                        <th>Question</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $short_query = "SELECT q.*, ch.chapter_name FROM questions q 
                                                   JOIN chapter ch ON q.chapter_id = ch.chapter_id
                                                   WHERE q.class_id = {$exam['class_id']} 
                                                   AND q.book_id = {$exam['book_id']} 
                                                   AND q.question_type = 'short'
                                                   $where_chapters";
                                    $shorts = $conn->query($short_query);
                                    while ($s = $shorts->fetch_assoc()):
                                        $qid = "q_" . $s['id'];
                                        $checked = in_array($qid, $current_selection) ? 'checked' : '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="question_ids[]" value="<?= $qid ?>" class="q-check" <?= $checked ?>></td>
                                            <td><?= htmlspecialchars($s['question_text']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Long Questions Section -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Long Questions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">Select</th>
                                        <th>Question</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $long_query = "SELECT q.*, ch.chapter_name FROM questions q 
                                                   JOIN chapter ch ON q.chapter_id = ch.chapter_id
                                                   WHERE q.class_id = {$exam['class_id']} 
                                                   AND q.book_id = {$exam['book_id']} 
                                                   AND q.question_type = 'long'
                                                   $where_chapters";
                                    $longs = $conn->query($long_query);
                                    while ($l = $longs->fetch_assoc()):
                                        $qid = "q_" . $l['id'];
                                        $checked = in_array($qid, $current_selection) ? 'checked' : '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="question_ids[]" value="<?= $qid ?>" class="q-check" <?= $checked ?>></td>
                                            <td><?= htmlspecialchars($l['question_text']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.q-check').forEach(cb => {
    cb.addEventListener('change', function() {
        const count = document.querySelectorAll('.q-check:checked').length;
        document.getElementById('selected-count').textContent = count;
    });
});
</script>

