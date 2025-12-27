<?php
include 'db_connect.php';
if (!isset($_GET['class_id']) || !isset($_GET['book_id'])) { header('Location: select_class.php'); exit; }
$classId = intval($_GET['class_id']);
$bookId = intval($_GET['book_id']);
$bookName = isset($_GET['book_name']) ? trim($_GET['book_name']) : '';

$hasBookNameCol = false;
$colChk = $conn->query("SHOW COLUMNS FROM questions LIKE 'book_name'");
if ($colChk && $colChk->num_rows > 0) { $hasBookNameCol = true; }

$viewCreateOk = true;
if ($hasBookNameCol) {
    $res = $conn->query("CREATE OR REPLACE VIEW view_topics_by_book AS 
        SELECT class_id, book_id, book_name, chapter_id, TRIM(topic) AS topic, COUNT(*) AS question_count 
        FROM questions 
        WHERE topic IS NOT NULL AND topic <> '' 
        GROUP BY class_id, book_id, book_name, chapter_id, TRIM(topic)");
    if ($res === false) { $viewCreateOk = false; }
} else {
    $res = $conn->query("CREATE OR REPLACE VIEW view_topics_by_book AS 
        SELECT q.class_id, q.book_id, b.book_name, q.chapter_id, TRIM(q.topic) AS topic, COUNT(*) AS question_count 
        FROM questions q 
        LEFT JOIN book b ON b.book_id = q.book_id 
        WHERE q.topic IS NOT NULL AND q.topic <> '' 
        GROUP BY q.class_id, q.book_id, b.book_name, q.chapter_id, TRIM(q.topic)");
    if ($res === false) { $viewCreateOk = false; }
}

$viewHasChapterCol = false;
if ($viewCreateOk) {
    $colCheckView = $conn->query("SHOW COLUMNS FROM view_topics_by_book LIKE 'chapter_id'");
    if ($colCheckView && $colCheckView->num_rows > 0) { $viewHasChapterCol = true; }
}

if ($viewHasChapterCol) {
    $stmt = $conn->prepare("SELECT v.topic, v.book_name, v.question_count, v.chapter_id, c.chapter_name 
                            FROM view_topics_by_book v 
                            LEFT JOIN chapter c ON c.chapter_id = v.chapter_id 
                            WHERE v.class_id = ? AND v.book_id = ? 
                            ORDER BY c.chapter_name, v.topic");
    if ($stmt) {
        $stmt->bind_param('ii', $classId, $bookId);
        $stmt->execute();
        $topicsRes = $stmt->get_result();
    } else {
        $viewHasChapterCol = false;
    }
}
if (!$viewHasChapterCol) {
    $stmt = $conn->prepare("SELECT q.topic, b.book_name, COUNT(*) AS question_count, q.chapter_id, c.chapter_name 
                            FROM questions q 
                            LEFT JOIN book b ON b.book_id = q.book_id 
                            LEFT JOIN chapter c ON c.chapter_id = q.chapter_id 
                            WHERE q.class_id = ? AND q.book_id = ? AND q.topic IS NOT NULL AND q.topic <> '' 
                            GROUP BY q.topic, q.chapter_id, c.chapter_name, b.book_name 
                            ORDER BY c.chapter_name, q.topic");
    $stmt->bind_param('ii', $classId, $bookId);
    $stmt->execute();
    $topicsRes = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Topics</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/buttons.css">
    <?php include 'header.php'; ?>
</head>
<body>
<div class="main-content">
    <div class="container" style="max-width:980px;margin:20px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:20px;">
        <h3>Select Topics for <?= htmlspecialchars($bookName) ?> (Class <?= htmlspecialchars((string)$classId) ?>)</h3>
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:10px 0;">
            <label>MCQs: <input type="number" name="topics_mcq_count" min="0" max="100" value="10" style="padding:6px;border:1px solid #e5e7eb;border-radius:6px;width:90px;"></label>
            <label>Short: <input type="number" name="topics_short_count" min="0" max="200" value="10" style="padding:6px;border:1px solid #e5e7eb;border-radius:6px;width:90px;"></label>
            <label>Long: <input type="number" name="topics_long_count" min="0" max="20" value="3" style="padding:6px;border:1px solid #e5e7eb;border-radius:6px;width:90px;"></label>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin:10px 0;">
            <input type="text" id="topicSearch" placeholder="Search topics..." style="flex:1;padding:10px;border:1px solid #e5e7eb;border-radius:8px;">
            <button type="button" class="btn auto-fill-btn" id="autoSelectBtn">Auto Select</button>
        </div>
        <?php if ($topicsRes && $topicsRes->num_rows > 0): ?>
        <?php
            $byChapter = [];
            while ($row = $topicsRes->fetch_assoc()) {
                $chName = $row['chapter_name'] ?: ('Chapter ' . (int)$row['chapter_id']);
                $byChapter[$chName][] = $row;
            }
        ?>
        <form method="POST" action="generate_question_paper.php" id="topicsForm">
            <input type="hidden" name="class_id" value="<?= htmlspecialchars((string)$classId) ?>">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars((string)$bookId) ?>">
            <input type="hidden" name="book_name" value="<?= htmlspecialchars($bookName) ?>">
            <input type="hidden" name="pattern_mode" value="without">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;">
                <?php foreach ($byChapter as $chapterName => $rows): ?>
                    <div class="chapter-topics" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
                        <div style="font-weight:700;margin-bottom:8px;"><?= htmlspecialchars($chapterName) ?></div>
                        <?php foreach ($rows as $row): ?>
                            <label class="topic-item" style="display:flex;align-items:center;gap:8px;border:1px solid #eef2f7;border-radius:8px;padding:8px;margin-bottom:6px;">
                                <input type="checkbox" name="topics[]" value="<?= htmlspecialchars($row['topic']) ?>">
                                <span class="topic-text" style="flex:1;"><?= htmlspecialchars($row['topic']) ?></span>
                                <span style="color:#6b7280;">(<?= (int)$row['question_count'] ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <button type="button" class="btn" onclick="history.back()">Back</button>
                <button type="submit" class="btn btn-primary">Generate Paper</button>
            </div>
        </form>
        <?php else: ?>
            <p>No topics found for this selection.</p>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
<script>
    // Search filter
    const searchInput = document.getElementById('topicSearch');
    searchInput && searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.topic-item').forEach(item => {
            const text = item.querySelector('.topic-text').textContent.toLowerCase();
            item.style.display = text.includes(q) ? 'flex' : 'none';
        });
    });
    // Auto select visible topics
    const autoBtn = document.getElementById('autoSelectBtn');
    autoBtn && autoBtn.addEventListener('click', function(){
        document.querySelectorAll('.topic-item').forEach(item => {
            if (item.style.display !== 'none') {
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = true;
            }
        });
        alert('Auto-selected visible topics.');
    });
</script>
</body>
</html>
