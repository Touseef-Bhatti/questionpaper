<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';
requireAdminAuth();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch exam details
$stmt = $conn->prepare("SELECT e.*, c.class_name, b.book_name 
                        FROM exam_preparations e
                        JOIN class c ON e.class_id = c.class_id
                        JOIN book b ON e.book_id = b.book_id
                        WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die("Exam not found.");
}

// Fetch chapter numbers for display
$chapter_numbers = [];
if (!empty($exam['chapter_ids'])) {
    $c_ids = explode(',', $exam['chapter_ids']);
    $placeholders = implode(',', array_fill(0, count($c_ids), '?'));
    $c_stmt = $conn->prepare("SELECT chapter_no FROM chapter WHERE chapter_id IN ($placeholders) ORDER BY chapter_no ASC");
    $c_stmt->bind_param(str_repeat('i', count($c_ids)), ...$c_ids);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    while ($row = $c_res->fetch_assoc()) {
        $chapter_numbers[] = $row['chapter_no'];
    }
    $c_stmt->close();
}
$chapters_display = !empty($chapter_numbers) ? implode(', ', $chapter_numbers) : 'None';

include_once __DIR__ . '/../header.php';

// Prepare to fetch selected questions if manual
$questions_data = ['mcqs' => [], 'short' => [], 'long' => []];
if ($exam['selection_type'] === 'manual' && $exam['question_ids']) {
    $qids = json_decode($exam['question_ids'], true);
    if (is_array($qids)) {
        $mcq_ids = [];
        $other_ids = [];
        foreach ($qids as $qid) {
            if (strpos($qid, 'mcq_') === 0) {
                $mcq_ids[] = intval(substr($qid, 4));
            } else {
                $other_ids[] = intval(substr($qid, 2));
            }
        }

        if (!empty($mcq_ids)) {
            $ids_str = implode(',', $mcq_ids);
            $res = $conn->query("SELECT * FROM mcqs WHERE mcq_id IN ($ids_str)");
            while ($r = $res->fetch_assoc()) $questions_data['mcqs'][] = $r;
        }
        if (!empty($other_ids)) {
            $ids_str = implode(',', $other_ids);
            $res = $conn->query("SELECT * FROM questions WHERE id IN ($ids_str)");
            while ($r = $res->fetch_assoc()) {
                if ($r['question_type'] === 'short') $questions_data['short'][] = $r;
                else $questions_data['long'][] = $r;
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-eye"></i> Exam Details: <?= htmlspecialchars($exam['title']) ?></h2>
        <div>
            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">General Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Class:</strong> <?= htmlspecialchars($exam['class_name']) ?></p>
                    <p><strong>Book:</strong> <?= htmlspecialchars($exam['book_name']) ?></p>
                    <p><strong>Chapters:</strong> <?= htmlspecialchars($chapters_display) ?></p>
                    <p><strong>Selection Type:</strong> 
                        <span class="badge bg-<?= $exam['selection_type'] === 'manual' ? 'warning' : 'info' ?>">
                            <?= ucfirst($exam['selection_type']) ?>
                        </span>
                    </p>
                    <p><strong>Created At:</strong> <?= date('Y-m-d H:i', strtotime($exam['created_at'])) ?></p>
                    <hr>
                    <h6>Configuration:</h6>
                    <p>MCQs: <?= $exam['mcq_count'] ?></p>
                    <p>Short Questions: <?= $exam['short_count'] ?></p>
                    <p>Long Questions: <?= $exam['long_count'] ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Questions Preview</h5>
                </div>
                <div class="card-body" style="max-height: 700px; overflow-y: auto;">
                    <?php if ($exam['selection_type'] === 'manual'): ?>
                        <?php if (empty($questions_data['mcqs']) && empty($questions_data['short']) && empty($questions_data['long'])): ?>
                            <p class="text-muted">No questions selected manually.</p>
                        <?php else: ?>
                            <!-- MCQs -->
                            <?php if (!empty($questions_data['mcqs'])): ?>
                                <h6 class="text-primary mt-3">Objective (MCQs)</h6>
                                <ol>
                                    <?php foreach ($questions_data['mcqs'] as $q): ?>
                                        <li class="mb-2"><?= htmlspecialchars($q['question']) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>

                            <!-- Short Questions -->
                            <?php if (!empty($questions_data['short'])): ?>
                                <h6 class="text-info mt-3">Short Questions</h6>
                                <ol>
                                    <?php foreach ($questions_data['short'] as $q): ?>
                                        <li class="mb-2"><?= htmlspecialchars($q['question_text']) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>

                            <!-- Long Questions -->
                            <?php if (!empty($questions_data['long'])): ?>
                                <h6 class="text-warning mt-3">Long Questions</h6>
                                <ol>
                                    <?php foreach ($questions_data['long'] as $q): ?>
                                        <li class="mb-2"><?= htmlspecialchars($q['question_text']) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This exam uses <strong>System Random</strong> selection. Questions will be picked dynamically for each student based on the configuration shown on the left.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


