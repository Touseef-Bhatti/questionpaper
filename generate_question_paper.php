<?php
// Authentication will be checked only when user wants to download or print
// require_once 'auth_check.php';
include 'db_connect.php';
require_once 'services/CacheManager.php';
require_once 'services/QuestionService.php';
session_start();

// Initialize cache and question service
$cache = new CacheManager();
$questionService = new QuestionService($conn, $cache);
function toRoman($num) {
    $map = [
        'm' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400,
        'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40,
        'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1
    ];
    $return = '';
    foreach ($map as $roman => $int) {
        while ($num >= $int) {
            $return .= $roman;
            $num -= $int;
        }
    }
    return $return;
}
function displayShortSection($questions, &$idx) {
    if (!empty($questions)) {
        echo '<ol type="i">';
        foreach ($questions as $q) {
            echo '<li>';
            echo '<div class="question-row">';
            echo '<div class="question-content">';
            echo '<strong>' . strtolower(toRoman($idx)) . '.</strong> ';
            echo '<span class="q-text">' . htmlspecialchars($q['question_text']) . '</span>';
            echo '</div>';
            echo '<div class="marks-container">';
            echo '<input type="number" value="' . $q['marks'] . '" class="marks-input" />';
            echo '</div>';
            echo '</div>';
            echo '<div class="action-buttons"><button class="btn edit" onclick="makeEditable(this)">Edit</button></div>';
            echo '</li>';
            $idx++;
        }
        echo '</ol>';
    } else {
        echo '<p>No short questions found for the selected chapters.</p>';
    }
}
// Validate POST data
if (!isset($_POST['class_id'], $_POST['book_name'], $_POST['chapters'])) {
    echo("<h2 style='color:red;'>Required data is missing. Please go back and try again.</h2>");
    header('Location: select_class.php');
}
$classId = intval($_POST['class_id']);
$bookName = htmlspecialchars($_POST['book_name']);
$totalMarks = 0;
// Derive selected chapter numbers/names for header display 
$chapterHeaderLabel = '';
$chapterIdsPosted = isset($_POST['chapter_ids']) && is_array($_POST['chapter_ids']) ? array_map('intval', $_POST['chapter_ids']) : [];
$chaptersRawJson = isset($_POST['chapters']) ? html_entity_decode($_POST['chapters']) : '[]';
$chaptersArr = json_decode($chaptersRawJson, true);
$chapterIds = [];
if (!empty($chapterIdsPosted)) {
    $chapterIds = $chapterIdsPosted;
} elseif (is_array($chaptersArr)) {
    foreach ($chaptersArr as $item) {
        $parts = explode('|', $item, 2);
        $cid = isset($parts[0]) ? intval($parts[0]) : 0;
        if ($cid > 0) {
            $chapterIds[] = $cid;
        }
    }
}
if (!empty($chapterIds)) {
    $in = implode(',', array_map('intval', $chapterIds));
    $nums = [];
    $res = $conn->query("SELECT chapter_id, COALESCE(chapter_no, chapter_id) AS num FROM chapter WHERE chapter_id IN ($in) ORDER BY num ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nums[] = (string)intval($row['num']);
        }
    }
    if (!empty($nums)) {
        $nums = array_values(array_unique($nums));
        $chapterHeaderLabel = 'Chapters: ' . implode(', ', $nums);
    }
}
$classNameHeader = '';
$clsRes = $conn->query("SELECT class_name FROM class WHERE class_id = $classId LIMIT 1");
if ($clsRes && ($clsRow = $clsRes->fetch_assoc())) {
    $classNameHeader = htmlspecialchars($clsRow['class_name']);
}
$mcqByChapter = [];
if (!empty($_POST['mcqs'])) {
    foreach ($_POST['mcqs'] as $chapterId => $count) {
        $count = intval($count);
        $chapterId = intval($chapterId);
        if ($count > 0) {
            // Use optimized question service instead of ORDER BY RAND()
            $mcqs = $questionService->getRandomMCQs($chapterId, $count);
            if (!empty($mcqs)) {
                $mcqByChapter[$chapterId] = $mcqs;
            }
        }
    }
}
$shortQuestions = [];
if (!empty($_POST['short_questions'])) {
    foreach ($_POST['short_questions'] as $chapterId => $count) {
        $count = intval($count);
        if ($count > 0) {
            // Use optimized question service for short questions
            $questions = $questionService->getRandomQuestions($chapterId, 'short', $count);
            if (!empty($questions)) {
                $shortQuestions[$chapterId] = $questions;
                foreach ($questions as $q) {
                    $totalMarks += $q['marks'];
                }
            }
        }
    }
}
$allShortQs = [];
foreach ($shortQuestions as $chapterId => $questions) {
    foreach ($questions as $q) {
        $allShortQs[] = $q;
    }
}
shuffle($allShortQs);
$section1 = array_slice($allShortQs, 0, 8);
$section2 = array_slice($allShortQs, 8, 8);
$section3 = array_slice($allShortQs, 16, 8);
$longQuestions = [];
if (!empty($_POST['long_questions'])) {
    foreach ($_POST['long_questions'] as $chapterId => $count) {
        $count = intval($count);
        if ($count > 0) {
            // Use optimized question service for long questions
            $questions = $questionService->getRandomQuestions($chapterId, 'long', $count);
            if (!empty($questions)) {
                $longQuestions[$chapterId] = $questions;
                foreach ($questions as $q) {
                    $totalMarks += $q['marks'];
                }
            }
        }
    }
}
$allLongQs = [];
foreach ($longQuestions as $chapterId => $questions) {
    foreach ($questions as $q) {
        $allLongQs[] = $q;
    }
}
shuffle($allLongQs);
$allLongQs = array_slice($allLongQs, 0, 20);
$patternMode = isset($_POST['pattern_mode']) && $_POST['pattern_mode'] === 'without' ? 'without' : 'with';
// For without pattern mode, use the total_longs value directly
// Determine how many distinct pattern question numbers (Q1..Qn) are available.
// Prefer the posted Total Longs (number of printed long questions) for both modes when available.
// Determine how many distinct pattern question numbers (Q1..Qn) are available.
// We'll prefer explicit totals (total_longs or pattern_qcount) but also fall back to
// scanning the posted placements (`long_qnum`) to discover the highest Q number the form submitted.
$patternQCount = 3; // sensible default
$explicitCount = 0;
if (isset($_POST['total_longs']) && trim($_POST['total_longs']) !== '') {
    $explicitCount = intval($_POST['total_longs']);
} elseif (isset($_POST['pattern_qcount']) && trim($_POST['pattern_qcount']) !== '') {
    $explicitCount = intval($_POST['pattern_qcount']);
}

// Inspect posted placement selects (long_qnum) to find the highest referenced Q number
$maxReferencedQ = 0;
if (!empty($_POST['long_qnum']) && is_array($_POST['long_qnum'])) {
    foreach ($_POST['long_qnum'] as $chapId => $qnums) {
        if (!is_array($qnums)) continue;
        foreach ($qnums as $val) {
            $n = intval($val);
            if ($n > $maxReferencedQ) $maxReferencedQ = $n;
        }
    }
}

// Choose the largest sensible source (explicit count or max referenced), clamp to 1..10
$patternQCount = max(1, min(10, max($explicitCount, $maxReferencedQ, $patternQCount)));
// Calculate offset for re-numbering long questions to start from Q5
$minOriginalQNum = PHP_INT_MAX;
if (!empty($_POST['long_qnum']) && is_array($_POST['long_qnum'])) {
    foreach ($_POST['long_qnum'] as $chapId => $qnums) {
        if (!is_array($qnums)) continue;
        foreach ($qnums as $val) {
            $n = intval($val);
            if ($n > 0 && $n < $minOriginalQNum) {
                $minOriginalQNum = $n;
            }
        }
    }
}

if ($minOriginalQNum === PHP_INT_MAX) {
    $minOriginalQNum = 1; // Default if no valid original Q numbers are found
}

$offset = 5 - $minOriginalQNum; // Calculate the offset needed to start from Q5
$maxNewQNumGenerated = 0; // Track the highest new Q number generated for $patternQCount

$placements = [];
if ($patternMode === 'with') {
    $desiredSlots = [];
    if (!empty($_POST['long_qnum']) && is_array($_POST['long_qnum']) && !empty($_POST['long_part']) && is_array($_POST['long_part'])) {
        foreach ($_POST['long_qnum'] as $chapId => $qnums) {
            $chapId = intval($chapId);
            $parts = isset($_POST['long_part'][$chapId]) ? $_POST['long_part'][$chapId] : (isset($_POST['long_part'][strval($chapId)]) ? $_POST['long_part'][strval($chapId)] : []);
            for ($i = 0; $i < count($qnums); $i++) {
                $originalQNum = intval($qnums[$i]);
                $part = strtolower($parts[$i] ?? 'a');

                // Apply the offset to get the new display question number
                $newQNum = $originalQNum + $offset;

                // Use the newQNum for the slot key and update maxNewQNumGenerated
                // Ensure the new Q number is within a reasonable range (e.g., up to 10)
                if ($newQNum >= 5 && $newQNum <= 10 && ($part === 'a' || $part === 'b')) {
                    $desiredSlots[] = [
                        'slot' => $newQNum . $part,
                        'chapter' => $chapId
                    ];
                    if ($newQNum > $maxNewQNumGenerated) {
                        $maxNewQNumGenerated = $newQNum;
                    }
                }
            }
        }
    }
    
    // Ensure $patternQCount is at least 5 for the display loop and includes the highest re-numbered question.
    $patternQCount = max(5, $patternQCount, $maxNewQNumGenerated);
    $slotSeen = [];
    $desiredSlots = array_filter($desiredSlots, function($d) use (&$slotSeen) {
        if (isset($slotSeen[$d['slot']])) {
            return false;
        }
        $slotSeen[$d['slot']] = true;
        return true;
    });
    $requiredPerChapter = [];
    foreach ($desiredSlots as $d) {
        $requiredPerChapter[$d['chapter']] = ($requiredPerChapter[$d['chapter']] ?? 0) + 1;
    }
    foreach ($requiredPerChapter as $chapId => $need) {
        $have = isset($longQuestions[$chapId]) ? count($longQuestions[$chapId]) : 0;
        if ($need > $have) {
            die('<h2 style="color:red;">Not enough long questions in the selected chapter (ID ' . htmlspecialchars((string)$chapId) . ') to fulfill placements. Needed ' . $need . ', available ' . $have . '.</h2>');
        }
    }
    $chapterQueues = [];
    foreach ($longQuestions as $chapId => $qs) {
        $chapterQueues[intval($chapId)] = array_values($qs);
    }
    foreach ($desiredSlots as $d) {
        $chap = intval($d['chapter']);
        $slotKey = $d['slot'];
        if (!empty($chapterQueues[$chap])) {
            $placements[$slotKey] = array_shift($chapterQueues[$chap]);
        }
    }
}

// Finally, update $patternQCount to ensure the display loop goes up to the highest re-numbered question
$patternQCount = max($patternQCount, $maxNewQNumGenerated);
?>
<!-- All your HTML output code starts here -->
<!DOCTYPE html>
<html>
<head>
    <title>Generated Question Paper</title>
    <link rel="stylesheet" href="css/QPaper.css">
    <link rel="stylesheet" href="css/buttons.css">
    <link rel="stylesheet" href="css/main.css">

        <style>
            /* Minimal page-scoped CSS to keep this page self-contained and avoid
               global button rule conflicts from other CSS files. Only the rules
               required for visuals on this page are included. */

            :root {
                --gray: #6c757d;
                --dark-gray: #343a40;
                --radius-lg: 12px;
                --transition-normal: 0.3s;
            }

            /* Neutralize any global .btn dark theming while keeping button layout
               behavior consistent with the site. This avoids changing shared CSS. */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.6rem 1rem;
                font-size: 0.95rem;
                font-weight: 600;
                border-radius: var(--radius-lg);
                transition: all var(--transition-normal);
                cursor: pointer;
                border: none;
                text-decoration: none;
                position: relative;
                overflow: hidden;
                white-space: nowrap;
                min-height: 36px;
                background: transparent !important;
                color: inherit !important;
                box-shadow: none !important;
            }

            /* Lightweight edit button styling so inline Edit actions remain visible */
            .btn.edit {
                background: linear-gradient(135deg, #ffffff, #f3f4f6) !important;
                color: #212529 !important;
                border: 1px solid rgba(0,0,0,0.06) !important;
                padding: 6px 10px !important;
                font-size: 0.9rem !important;
                min-height: 32px !important;
                border-radius: 8px !important;
                box-shadow: 0 2px 6px rgba(0,0,0,0.04) !important;
            }

            /* Print / Download / Go Back button strip - center and align in a row */
            .print-buttons {
                display: flex;
                gap: 12px;
                justify-content: center;
                align-items: center;
                padding: 10px;
                background: rgba(255,255,255,0.95);
                position: fixed;
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 1200;
                border-radius: 12px;
                box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            }
            .print-buttons .go-back-btn {
                margin: 0;
                height: 44px;
                display: inline-flex;
                align-items: center;
                padding: 0 14px;
            }

            @media print {
                .print-buttons { display: none; }
            }

        </style>
   
</head>
<body>

    <div class="paper-container" id="paper"> 
        <!-- HEADER OMITTED FOR BREVITY - SAME AS YOURS --> 
        <div class="header"> 
            <table class="header-table" border="1" cellspacing="0" cellpadding="10" style="width: 100%; text-align: center; border-collapse: collapse;"> 
                <tr> 
                    <td><input style="border: none;" type="text" placeholder="Book Name" value="<?php echo $bookName; ?>"></td> 
                    <td><input style="border: none; font-weight: bold; font-size: 18px; text-align: center; width: 100%; background: transparent;" type="text" value="OPF SCHOOL SKP"></td> 
                    <td><label>ROLL#:</label><input type="text" placeholder="...................." style="width: 80%; text-align: center; border: none;"></td> 
                </tr> 
                <tr> 
                    <td><input type="text" placeholder="Chapters" value="<?php echo htmlspecialchars($chapterHeaderLabel ?: 'Chapters'); ?>" style="width: 80%; text-align: center; border: none;"></td> 
                    <td><input type="text" placeholder="First Term" value="First Term" style="border: none; text-align: center; width: 100%; background: transparent;"></td> 
                    <td><label>Time Allowed:</label><input type="text" placeholder="20 Min" style="width: 80%; text-align: center; border: none;"></td> 
                </tr> 
                <tr> 
                    <td><label>Paper Code:</label><input type="text" placeholder="2354 (Objective)" style="width: 80%; text-align: center; border: none;"></td> 
                    <td><label></label><input type="text" value="<?php echo $classNameHeader ?: 'Class'; ?>" style="width: 80%; text-align: center; border: none;"></td> 
                    <td><label>Total Marks:</label><input type="text" value="<?php echo $totalMarks; ?>" style="width: 80%; text-align: center; border: none;"></td> 
                </tr> 
            </table> 
        </div>

        <!-- <label><strong>Instructions / Notes:</strong></label> 
        <textarea  id="note" rows="3" placeholder="Write any important note about this paper here..."></textarea>  -->

        <!-- MCQs Section --> 
        <div id="mcq-section">
        <?php 
        $allMcqs = []; 
        foreach ($mcqByChapter as $cid => $qs) { 
            foreach ($qs as $q) { 
                $allMcqs[] = $q; 
            } 
        } 

        if (!empty($allMcqs)) { 
            echo '<div class="section mcq-compact">'; 
            echo '<h3>Multiple Choice Questions (MCQs)</h3>'; 
            echo '<div class="mcq-grid">'; 
            $i = 1; 
            foreach ($allMcqs as $m) { 
                echo '<div class="mcq-item">'; 
                echo '<div class="mcq-question"><strong>Q' . ($i++) . '.</strong> <span class="q-text">' . htmlspecialchars($m['question']) . '</span></div>'; 
                echo '<div class="mcq-options">'; 
                echo '<div class="option-row"><strong>A)</strong> <span class="option-text">' . htmlspecialchars($m['option_a']) . '</span></div>'; 
                echo '<div class="option-row"><strong>B)</strong> <span class="option-text">' . htmlspecialchars($m['option_b']) . '</span></div>'; 
                echo '<div class="option-row"><strong>C)</strong> <span class="option-text">' . htmlspecialchars($m['option_c']) . '</span></div>'; 
                echo '<div class="option-row"><strong>D)</strong> <span class="option-text">' . htmlspecialchars($m['option_d']) . '</span></div>'; 
                echo '</div>'; 
                echo '<div class="action-buttons"><button class="btn edit" onclick="makeEditableMcq(this)">Edit</button></div>'; 
                echo '</div>'; 
            } 
            echo '</div>'; 
            echo '</div>'; 
        } 
        ?> 
        </div>

        <!-- Short and Long Questions Section -->
        <div id="short-long-section">
        <?php if ($patternMode === 'without') { ?>
            <div class="section">
                <h3>Short Questions</h3>
                <ol type="i">
                <?php $idx = 1; foreach ($allShortQs as $q) { ?>
                    <li>
                        <div class="question-row">
                            <div class="question-content">
                                <strong><?= strtolower(toRoman($idx)) ?>.</strong> <span class="q-text"><?= htmlspecialchars($q['question_text']) ?></span>
                            </div>
                            <div class="marks-container">
                                <input type="number" value="<?= $q['marks'] ?>" class="marks-input" />
                            </div>
                        </div>
                        <div class="action-buttons"><button class="btn edit" onclick="makeEditable(this)">Edit</button></div>
                    </li>
                <?php $idx++; } ?>
                </ol>
            </div>
            <div class="section">
                <h3>Long Questions</h3>
                <ol>
                <?php $qNum = 1; foreach ($allLongQs as $q) { ?>
                    <li>
                        <strong>Q<?= $qNum ?>.</strong>
                        <div class="question-row">
                            <div class="question-content"><span class="q-text"><?= htmlspecialchars($q['question_text']) ?></span></div>
                            <div class="marks-container"><input type="number" value="<?= $q['marks'] ?>" class="marks-input" /></div>
                        </div>
                        <div class="action-buttons"><button class="btn edit" onclick="makeEditable(this)">Edit</button></div>
                    </li>
                <?php $qNum++; } ?>
                </ol>
            </div>
        <?php } else { ?>
            <div class="section"> 
                <h4>2. Write Short answers to any Five(5) questions:</h4>
 
                <?php $idx = 1; displayShortSection($section1, $idx); ?> 
            </div> 

            <div class="section"> 
               <h4>3. Write Short answers to any Five(5) questions:</h4>
                <?php $idx = 1; displayShortSection($section2, $idx); ?> 
            </div> 

            <div class="section"> 
               <h4>4. Write Short answers to any Five(5) questions:</h4>
                <?php $idx = 1; displayShortSection($section3, $idx); ?> 
            </div> 

            <!-- Long Questions --> 
          
            <div class="section"> 
                <h4>Long Questions: </h4>
                <ol> 
                    <?php 
                    for ($q = 5; $q <= $patternQCount; $q++) {
                        $aKey = $q . 'a'; 
                        $bKey = $q . 'b'; 
                        $hasA = isset($placements[$aKey]); 
                        $hasB = isset($placements[$bKey]); 
                        
                        if (!$hasA && !$hasB) continue; 
                        
                        echo '<li>'; 
                        echo '<strong>Q' . $q . '.</strong>'; 
                        
                        if ($hasA) { 
                            $qa = $placements[$aKey]; 
                            echo '<div class="question-row">'; 
                            echo '<div class="question-content">a. <span class="q-text">' . htmlspecialchars($qa['question_text']) . '</span></div>'; 
                            echo '<div class="marks-container"><input type="number" value="' . $qa['marks'] . '" class="marks-input" /></div>'; 
                            echo '</div>'; 
                        } 
                        
                        if ($hasB) { 
                            $qb = $placements[$bKey]; 
                            echo '<div class="question-row">'; 
                            echo '<div class="question-content">b. <span class="q-text">' . htmlspecialchars($qb['question_text']) . '</span></div>'; 
                            echo '<div class="marks-container"><input type="number" value="' . $qb['marks'] . '" class="marks-input" /></div>'; 
                            echo '</div>'; 
                        } 
                        
                        echo '<div class="action-buttons"><button class="btn edit" onclick="makeEditable(this)">Edit</button></div>'; 
                        echo '</li>'; 
                    } 
                    ?> 
                </ol> 
            </div> 
        <?php } ?>
        </div>
    </div>
                 
        <div class="print-buttons">
                <button class="go-back-btn" onclick="window.history.back()">⬅️ Go Back</button>

                <button onclick="window.print()" class="cssbuttons-io-button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path fill="none" d="M0 0h24v24H0z"></path><path fill="currentColor" d="M6 9h12v6H6z"/><path fill="currentColor" d="M6 3h12v2H6z"/><path fill="currentColor" d="M8 15v4h8v-4"/></svg>
                        <span>Print</span>
                </button>

                <button onclick="downloadDOCX()" class="cssbuttons-io-button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path fill="none" d="M0 0h24v24H0z"></path><path fill="currentColor" d="M1 14.5a6.496 6.496 0 0 1 3.064-5.519 8.001 8.001 0 0 1 15.872 0 6.5 6.5 0 0 1-2.936 12L7 21c-3.356-.274-6-3.078-6-6.5zm15.848 4.487a4.5 4.5 0 0 0 2.03-8.309l-.807-.503-.12-.942a6.001 6.001 0 0 0-11.903 0l-.12.942-.805.503a4.5 4.5 0 0 0 2.029 8.309l.173.013h9.35l.173-.013zM13 12h3l-4 5-4-5h3V8h2v4z"/></svg>
                        <span>Download</span>
                </button>
        </div>

    <script> 
function makeEditable(button) {
    const listItem = button.closest('li');
    // Find all question text spans within the list item
    const questionTexts = listItem.querySelectorAll('.question-content .q-text');
    const textareas = [];
    
    // Convert all spans to textareas
    questionTexts.forEach(questionText => {
        if (questionText.tagName === 'SPAN') {
            const textarea = document.createElement('textarea');
            textarea.value = questionText.textContent;
            textarea.style.width = '100%';
            textarea.style.height = 'auto';
            textarea.style.resize = 'vertical';
            questionText.replaceWith(textarea);
            textareas.push(textarea);
        } else {
            textareas.push(questionText);
        }
    });
    
    button.textContent = 'Save';
    button.onclick = function () {
        // Convert all textareas back to spans
        textareas.forEach(textarea => {
            if (textarea) {
                const span = document.createElement('span');
                span.className = 'q-text';
                span.textContent = textarea.value;
                textarea.replaceWith(span);
            }
        });
        button.textContent = 'Edit';
        button.onclick = function () { makeEditable(button); };
    };
}

function makeEditableMcq(button) {
    const mcqItem = button.closest('.mcq-item');
    
    // Find question text and option texts
    const questionText = mcqItem.querySelector('.mcq-question .q-text');
    const optionTexts = mcqItem.querySelectorAll('.mcq-options .option-text');
    const elements = [];
    
    // Convert question text to textarea
    if (questionText && questionText.tagName === 'SPAN') {
        const textarea = document.createElement('textarea');
        textarea.value = questionText.textContent;
        textarea.style.width = '100%';
        textarea.style.height = 'auto';
        textarea.style.resize = 'vertical';
        questionText.replaceWith(textarea);
        elements.push(textarea);
    } else if (questionText) {
        elements.push(questionText);
    }
    
    // Convert option texts to textareas
    optionTexts.forEach(optionText => {
        if (optionText.tagName === 'SPAN') {
            const textarea = document.createElement('textarea');
            textarea.value = optionText.textContent;
            textarea.style.width = '100%';
            textarea.style.height = 'auto';
            textarea.style.resize = 'vertical';
            optionText.replaceWith(textarea);
            elements.push(textarea);
        } else {
            elements.push(optionText);
        }
    });
    
    button.textContent = 'Save';
    button.onclick = function () {
        // Convert question textarea back to span
        if (elements[0]) {
            const span = document.createElement('span');
            span.className = 'q-text';
            span.textContent = elements[0].value;
            elements[0].replaceWith(span);
        }
        
        // Convert option textareas back to spans
        for (let i = 1; i < elements.length; i++) {
            if (elements[i]) {
                const span = document.createElement('span');
                span.className = 'option-text';
                span.textContent = elements[i].value;
                elements[i].replaceWith(span);
            }
        }
        
        button.textContent = 'Edit';
        button.onclick = function () { makeEditableMcq(button); };
    };
}

async function downloadDOCX() {
    // Ask permission before attempting multiple downloads.
    // Many mobile browsers (especially iOS Safari) block programmatic multiple downloads.
    // Offer the user a combined single-file download for best compatibility.
    const paperContainer = document.getElementById('paper');
    if (!paperContainer) {
        alert("No element with id='paper' found!");
        return;
    }

    const mcqSection = document.getElementById('mcq-section');
    const shortLongSection = document.getElementById('short-long-section');

    // Build the two HTML parts
    const mcqPaperHTML = createPaperHTML(paperContainer, mcqSection, 'MCQs');
    const shortLongPaperHTML = createPaperHTML(paperContainer, shortLongSection, 'Short & Long Questions');

    // If both parts exist, ask user permission to download both.
    const hasMcq = mcqSection && mcqSection.innerHTML.trim() !== '';
    const hasShortLong = shortLongSection && shortLongSection.innerHTML.trim() !== '';

    if (hasMcq && hasShortLong) {
        // Suggest combined for iPhone users
        const isiOS = /iP(hone|od|ad)/.test(navigator.userAgent);
        const promptMsg = isiOS
            ? 'This device may block multiple automatic downloads.\n\nChoose OK to download a single combined file (recommended), or Cancel to attempt separate downloads.'
            : 'Do you want to download both files?\n\nChoose OK to download as a single combined file (recommended), or Cancel to download files separately.';

        const useCombined = confirm(promptMsg);
        if (useCombined) {
            // Create a combined package (single HTML) and download once — best for iOS.
            const combinedHtml = combinePaperHTML(mcqPaperHTML, shortLongPaperHTML);
            const { cleanHtml } = buildCleanPaperHTML(createTempContainer(combinedHtml));
            postDoc(cleanHtml, 'Question_Paper_Complete');
            return;
        }
        // else fallthrough to separate downloads
    }

    // Original separate-download behaviour (best-effort). Keep the same timing as before.
    if (hasMcq) {
        const { cleanHtml: mcqCleanHtml } = buildCleanPaperHTML(createTempContainer(mcqPaperHTML));
        postDoc(mcqCleanHtml, 'Question_Paper_MCQs');

        setTimeout(() => {
            if (hasShortLong) {
                const { cleanHtml: shortLongCleanHtml } = buildCleanPaperHTML(createTempContainer(shortLongPaperHTML));
                postDoc(shortLongCleanHtml, 'Question_Paper_Short_Long');
            }
        }, 500);
    } else if (hasShortLong) {
        const { cleanHtml: shortLongCleanHtml } = buildCleanPaperHTML(createTempContainer(shortLongPaperHTML));
        postDoc(shortLongCleanHtml, 'Question_Paper_Short_Long');
    }
}

// Combine two paper HTML fragments into a single document for a single-download flow.
function combinePaperHTML(partAHtml, partBHtml) {
    // Wrap both parts inside a single container so postDoc/download_doc.php receives one file.
    let container = '<div class="paper-container">';
    if (partAHtml && partAHtml.trim() !== '') container += partAHtml;
    if (partBHtml && partBHtml.trim() !== '') container += partBHtml;
    container += '</div>';
    return container;
}

function createPaperHTML(paperContainer, contentSection, title) {
    // Clone the header and basic structure
    const headerElement = paperContainer.querySelector('.header');
    const noteElement = paperContainer.querySelector('#note');
    
    let paperHTML = '<div class="paper-container">';
    
    // Add header if exists
    if (headerElement) {
        paperHTML += headerElement.outerHTML;
    }
    
    // Add note if exists
    if (noteElement) {
        paperHTML += '<label><strong>Instructions / Notes:</strong></label>';
        paperHTML += noteElement.outerHTML;
    }
    
    // Add content section
    if (contentSection && contentSection.innerHTML.trim() !== '') {
        paperHTML += contentSection.outerHTML;
    }
    
    paperHTML += '</div>';
    return paperHTML;
}

function createTempContainer(html) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    return tempDiv.firstElementChild;
}

function buildCleanPaperHTML(sourceEl) {
    const clone = sourceEl.cloneNode(true);
    
    // Remove action buttons
    clone.querySelectorAll('.action-buttons').forEach(el => el.remove());
    
    // Convert inputs to spans
    clone.querySelectorAll('input').forEach(input => {
        const span = document.createElement('span');
        span.textContent = input.value || '';
        // For marks inputs, make sure to capture the current value
        if (input.classList.contains('marks-input')) {
            span.textContent = input.value || '0';
            span.style.fontWeight = 'bold';
        }
        input.replaceWith(span);
    });
    
    // Convert textareas to divs - prioritize actual value over placeholder
    clone.querySelectorAll('textarea').forEach(textarea => {
        const div = document.createElement('div');
        const textContent = textarea.value.trim();
        if (textContent) {
            div.textContent = textContent;
        } else {
            div.textContent = textarea.placeholder || '';
        }
        div.style.fontWeight = 'bold';
        textarea.replaceWith(div);
    });
    
    // REMOVE LIST TAGS TO PREVENT DOUBLE NUMBERING
    // Convert ordered lists to simple divs while preserving content
    clone.querySelectorAll('ol').forEach(ol => {
        const div = document.createElement('div');
        div.className = 'question-list';
        
        // Process each list item
        ol.querySelectorAll('li').forEach(li => {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-item';
            questionDiv.style.marginBottom = '10px';
            questionDiv.innerHTML = li.innerHTML;
            div.appendChild(questionDiv);
        });
        
        ol.replaceWith(div);
    });
    
    // Also remove any remaining ul tags
    clone.querySelectorAll('ul').forEach(ul => {
        const div = document.createElement('div');
        div.className = 'question-list';
        
        ul.querySelectorAll('li').forEach(li => {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-item';
            questionDiv.style.marginBottom = '10px';
            questionDiv.innerHTML = li.innerHTML;
            div.appendChild(questionDiv);
        });
        
        ul.replaceWith(div);
    });
    
    // Special handling for MCQ grid layout in DOCX
    clone.querySelectorAll('.mcq-grid').forEach(grid => {
        const mcqDiv = document.createElement('div');
        mcqDiv.className = 'mcq-docx-layout';
        
        // Create a table for grid layout in DOCX
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.border = '1px solid #ddd';
        
        // Determine number of columns (2 for Word doc)
        const columns = 2;
        const mcqItems = grid.querySelectorAll('.mcq-item');
        
        // Create rows with specified number of MCQs per row
        for (let i = 0; i < mcqItems.length; i += columns) {
            const row = document.createElement('tr');
            
            // Create cells for this row
            for (let j = 0; j < columns; j++) {
                if (i + j < mcqItems.length) {
                    const cell = document.createElement('td');
                    cell.style.width = (100 / columns) + '%';
                    cell.style.padding = '8px';
                    cell.style.border = '1px solid #ddd';
                    cell.style.verticalAlign = 'top';
                    
                    // Ensure proper formatting of MCQ content
                    const mcqContent = mcqItems[i + j].cloneNode(true);
                    
                    // Make sure question is bold
                    const questionEl = mcqContent.querySelector('.mcq-question');
                    if (questionEl) {
                        questionEl.style.fontWeight = 'bold';
                        questionEl.style.marginBottom = '5px';
                    }
                    
                    cell.appendChild(mcqContent);
                    row.appendChild(cell);
                } else {
                    // Empty cell to maintain table structure
                    const cell = document.createElement('td');
                    cell.style.width = (100 / columns) + '%';
                    cell.style.border = '1px solid #ddd';
                    row.appendChild(cell);
                }
            }
            
            table.appendChild(row);
        }
        
        mcqDiv.appendChild(table);
        grid.replaceWith(mcqDiv);
    });
    
    // Fix question-row layout for DOCX - ensure marks stay inline
    clone.querySelectorAll('.question-row').forEach(row => {
        const content = row.querySelector('.question-content');
        const marks = row.querySelector('.marks-container');
        
        if (content && marks) {
            const inlineDiv = document.createElement('div');
            inlineDiv.style.display = 'flex';
            inlineDiv.style.justifyContent = 'space-between';
            inlineDiv.style.alignItems = 'flex-start';
            
            const contentSpan = document.createElement('span');
            contentSpan.innerHTML = content.innerHTML;
            contentSpan.style.flex = '1';
            
            const marksSpan = document.createElement('span');
            marksSpan.innerHTML = marks.innerHTML;
            marksSpan.style.marginLeft = '20px';
            marksSpan.style.minWidth = '60px';
            marksSpan.style.textAlign = 'right';
            
            inlineDiv.appendChild(contentSpan);
            inlineDiv.appendChild(marksSpan);
            
            row.replaceWith(inlineDiv);
        }
    });
    
    return { cleanHtml: clone.outerHTML, plainText: clone.innerText.trim() };
}

function postDoc(html, fileName) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'download_doc.php';
    form.style.display = 'none';

    const htmlField = document.createElement('textarea');
    htmlField.name = 'html';
    htmlField.value = html;
    form.appendChild(htmlField);

    const nameField = document.createElement('input');
    nameField.type = 'hidden';
    nameField.name = 'file_name';
    nameField.value = fileName || 'Question_Paper';
    form.appendChild(nameField);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
function finalizeEdits() {
    // Handle regular textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        // Determine the appropriate class based on context
        let className = '';
        if (textarea.parentElement && textarea.parentElement.classList.contains('mcq-question')) {
            className = 'q-text';
        } else if (textarea.parentElement && textarea.parentElement.classList.contains('option-row')) {
            className = 'option-text';
        }
        
        const span = document.createElement('span');
        if (className) {
            span.className = className;
        }
        span.textContent = textarea.value.trim();
        textarea.replaceWith(span);
    });
}

async function downloadDOCX() {
    // First, finalize all edits so the HTML has actual text
    finalizeEdits();

    const paperContainer = document.getElementById('paper');
    if (!paperContainer) {
        alert("No element with id='paper' found!");
        return;
    }

    const mcqSection = document.getElementById('mcq-section');
    const shortLongSection = document.getElementById('short-long-section');

    const mcqPaperHTML = createPaperHTML(paperContainer, mcqSection, 'MCQs');
    const shortLongPaperHTML = createPaperHTML(paperContainer, shortLongSection, 'Short & Long Questions');

    if (mcqSection && mcqSection.innerHTML.trim() !== '') {
        const { cleanHtml: mcqCleanHtml } = buildCleanPaperHTML(createTempContainer(mcqPaperHTML));
        postDoc(mcqCleanHtml, 'Question_Paper_MCQs');
        
        setTimeout(() => {
            if (shortLongSection && shortLongSection.innerHTML.trim() !== '') {
                const { cleanHtml: shortLongCleanHtml } = buildCleanPaperHTML(createTempContainer(shortLongPaperHTML));
                postDoc(shortLongCleanHtml, 'Question_Paper_Short_Long');
            }
        }, 500);
    } else if (shortLongSection && shortLongSection.innerHTML.trim() !== '') {
        const { cleanHtml: shortLongCleanHtml } = buildCleanPaperHTML(createTempContainer(shortLongPaperHTML));
        postDoc(shortLongCleanHtml, 'Question_Paper_Short_Long');
    }
}


</script>
</body> 
</html>
