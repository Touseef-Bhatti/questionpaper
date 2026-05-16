<?php
/**
 * Debugging Test File for MCQ Verification
 * This file helps identify why explanations are missing for class/book selected MCQs.
 * Enhanced with Class, Book, and Chapter selection.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mcq_generator.php';

header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>MCQ Verification Debugger</title>";
echo "<style>
    body { font-family: 'Inter', sans-serif; line-height: 1.5; padding: 20px; background: #f4f7f6; color: #1e293b; }
    .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 700; text-transform: uppercase; }
    .status-verified { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-missing { background: #fee2e2; color: #991b1b; }
    pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; margin: 15px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { text-align: left; padding: 14px; border-bottom: 1px solid #e2e8f0; }
    th { background: #f8fafc; font-weight: 600; color: #64748b; font-size: 0.85rem; }
    .debug-info { color: #64748b; font-size: 0.9em; margin-bottom: 15px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.9rem; }
    select, input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: #fff; }
    .btn { background: #4f46e5; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
    .btn:hover { background: #4338ca; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .filter-section { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
</style>
<script>
    function updateBooks() {
        const classId = document.getElementById('class_id').value;
        window.location.href = '?class_id=' + classId;
    }
    function updateChapters() {
        const classId = document.getElementById('class_id').value;
        const bookId = document.getElementById('book_id').value;
        window.location.href = '?class_id=' + classId + '&book_id=' + bookId;
    }
</script>
</head><body>";

echo "<h1>MCQ Verification Debugger</h1>";

// 1. Setup Parameters
$class_id = intval($_GET['class_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);
$chapter_id = intval($_GET['chapter_id'] ?? 0);
$limit = intval($_GET['limit'] ?? 10);

// Fetch all classes for the dropdown
$classes = [];
$c_res = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
while ($row = $c_res->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch books for the selected class
$books = [];
if ($class_id > 0) {
    $b_stmt = $conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_name ASC");
    $b_stmt->bind_param('i', $class_id);
    $b_stmt->execute();
    $b_res = $b_stmt->get_result();
    while ($row = $b_res->fetch_assoc()) {
        $books[] = $row;
    }
}

// Fetch chapters for the selected book
$chapters = [];
if ($book_id > 0) {
    $ch_stmt = $conn->prepare("SELECT chapter_id, chapter_name, chapter_no FROM chapter WHERE book_id = ? ORDER BY chapter_no ASC");
    $ch_stmt->bind_param('i', $book_id);
    $ch_stmt->execute();
    $ch_res = $ch_stmt->get_result();
    while ($row = $ch_res->fetch_assoc()) {
        $chapters[] = $row;
    }
}

// Filter Form
echo "<div class='filter-section'>";
echo "<form method='GET' action=''>";
echo "<div class='grid'>";

// Class Selection
echo "<div class='form-group'><label>Select Class</label>";
echo "<select name='class_id' id='class_id' onchange='updateBooks()'>";
echo "<option value='0'>-- Choose Class --</option>";
foreach ($classes as $c) {
    $sel = ($c['class_id'] == $class_id) ? 'selected' : '';
    echo "<option value='{$c['class_id']}' $sel>{$c['class_name']}</option>";
}
echo "</select></div>";

// Book Selection
echo "<div class='form-group'><label>Select Book</label>";
echo "<select name='book_id' id='book_id' onchange='updateChapters()'>";
echo "<option value='0'>-- Choose Book --</option>";
foreach ($books as $b) {
    $sel = ($b['book_id'] == $book_id) ? 'selected' : '';
    echo "<option value='{$b['book_id']}' $sel>{$b['book_name']}</option>";
}
echo "</select></div>";

// Chapter Selection
echo "<div class='form-group'><label>Select Chapter (Optional)</label>";
echo "<select name='chapter_id' id='chapter_id'>";
echo "<option value='0'>All Chapters</option>";
foreach ($chapters as $ch) {
    $sel = ($ch['chapter_id'] == $chapter_id) ? 'selected' : '';
    $chLabel = !empty($ch['chapter_no']) ? "Ch {$ch['chapter_no']}: " : "";
    echo "<option value='{$ch['chapter_id']}' $sel>{$chLabel}{$ch['chapter_name']}</option>";
}
echo "</select></div>";

// Limit Selection
echo "<div class='form-group'><label>Question Limit</label>";
echo "<input type='number' name='limit' value='$limit' min='1' max='50'></div>";

echo "</div>";
echo "<div style='margin-top: 15px;'><button type='submit' class='btn'>Run Debugger</button></div>";
echo "</form></div>";

if ($class_id > 0 && $book_id > 0) {
    // 2. Fetch MCQs (Simulating quiz.php)
    $whereConditions = ['m.correct_option IS NOT NULL', 'm.correct_option != ""', 'm.class_id = ?', 'm.book_id = ?'];
    $params = [$class_id, $book_id];
    $types = 'ii';

    if ($chapter_id > 0) {
        $whereConditions[] = 'm.chapter_id = ?';
        $params[] = $chapter_id;
        $types .= 'i';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    $sql = "SELECT m.mcq_id, m.question, m.correct_option, m.explanation as main_explanation, 
                   v.verification_status, v.explanation as verify_explanation
            FROM mcqs m
            LEFT JOIN MCQsVerification v ON m.mcq_id = v.mcq_id
            $whereClause 
            ORDER BY RAND() 
            LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $mcqs = [];
    while ($row = $result->fetch_assoc()) {
        $mcqs[] = $row;
    }
    $stmt->close();

    echo "<div class='card'>";
    echo "<h3>Analysis Results</h3>";
    if (empty($mcqs)) {
        echo "<p style='color:red;'>No MCQs found for this combination.</p>";
    } else {
        echo "<table><thead><tr>
            <th>ID</th>
            <th>Question (Snippet)</th>
            <th>Main Table Exp</th>
            <th>Verify Table Status</th>
            <th>Verify Table Exp</th>
        </tr></thead><tbody>";

        $missingIds = [];
        foreach ($mcqs as $m) {
            $hasMainExp = !empty(trim($m['main_explanation'] ?? ''));
            $hasVerifyExp = !empty(trim($m['verify_explanation'] ?? ''));
            $status = $m['verification_status'] ?? 'None';
            
            $isMissing = (!$hasMainExp && !$hasVerifyExp);
            if ($isMissing) {
                $missingIds[] = $m['mcq_id'];
            }

            echo "<tr>";
            echo "<td>" . $m['mcq_id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($m['question'], 0, 50)) . "...</td>";
            echo "<td>" . ($hasMainExp ? "<span class='status-badge status-verified'>Yes</span>" : "<span class='status-badge status-missing'>No</span>") . "</td>";
            echo "<td><span class='status-badge status-" . strtolower($status) . "'>$status</span></td>";
            echo "<td>" . ($hasVerifyExp ? "<span class='status-badge status-verified'>Yes</span>" : "<span class='status-badge status-missing'>No</span>") . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    echo "</div>";

    // 3. Test Verification Logic
    if (!empty($missingIds)) {
        echo "<div class='card'>";
        echo "<h3>Step 2: Testing Verification for Missing MCQs</h3>";
        echo "<p>Found <strong>" . count($missingIds) . "</strong> MCQs missing explanations. Triggering AI verification...</p>";
        
        $startTime = microtime(true);
        $res = checkMCQsWithAI(count($missingIds), null, null, 'mcqs', $missingIds);
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        echo "<h4>AI Result (Duration: {$duration}s):</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT)) . "</pre>";

        if ($res['success']) {
            echo "<p style='color:green; font-weight:bold;'>✅ Verification call successful! Re-checking database state...</p>";
            
            $idList = implode(',', $missingIds);
            $recheckSql = "SELECT m.mcq_id, m.explanation as main_explanation, v.verification_status, v.explanation as verify_explanation
                           FROM mcqs m
                           LEFT JOIN MCQsVerification v ON m.mcq_id = v.mcq_id
                           WHERE m.mcq_id IN ($idList)";
            $recheckRes = $conn->query($recheckSql);
            
            echo "<table><thead><tr>
                <th>ID</th>
                <th>Updated Main Exp</th>
                <th>Updated Verify Status</th>
                <th>Updated Verify Exp</th>
            </tr></thead><tbody>";
            while ($row = $recheckRes->fetch_assoc()) {
                $hasMain = !empty(trim($row['main_explanation'] ?? ''));
                $hasVerify = !empty(trim($row['verify_explanation'] ?? ''));
                echo "<tr>";
                echo "<td>" . $row['mcq_id'] . "</td>";
                echo "<td>" . ($hasMain ? "✅ Updated" : "❌ Still Missing") . "</td>";
                echo "<td><span class='status-badge status-" . strtolower($row['verification_status']) . "'>{$row['verification_status']}</span></td>";
                echo "<td>" . ($hasVerify ? "✅ Updated" : "❌ Still Missing") . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p style='color:red;'>❌ Verification failed: " . htmlspecialchars($res['message'] ?? 'Unknown error') . "</p>";
        }
        echo "</div>";
    }
} else {
    echo "<div class='card' style='background: #f8fafc; text-align: center; padding: 40px;'>";
    echo "<i class='fas fa-info-circle' style='font-size: 3rem; color: #cbd5e1; margin-bottom: 20px;'></i>";
    echo "<h3>Select a Class and Book to start debugging</h3>";
    echo "<p class='debug-info'>The debugger will randomly pick MCQs from your selection and check their verification status.</p>";
    echo "</div>";
}

echo "<div class='card'>";
echo "<h3>System Status</h3>";
echo "<ul>";
echo "<li><strong>RECHECK_API_KEY:</strong> " . (!empty(getRecheckApiKey()) ? "✅ Configured" : "❌ Missing") . "</li>";
echo "<li><strong>RECHECK_MODEL:</strong> <code>" . getRecheckModel() . "</code></li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
