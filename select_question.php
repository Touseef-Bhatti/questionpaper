<?php
include 'db_connect.php';

if (!isset($_POST['chapters']) || empty($_POST['chapters'])) {
    echo("<h2 style='color:red;'>No chapters selected. Please go back and select chapters.</h2>");
    header('Location: select_class.php');
    exit;
}

$classId = intval($_POST['class_id']);
$book_name = trim($conn->real_escape_string($_POST['book_name']));
$selectedChapters = $_POST['chapters'];
$shortQuestions = $_POST['short_questions'];
$mcqs = isset($_POST['mcqs']) ? $_POST['mcqs'] : [];
$longQuestions = $_POST['long_questions'];
$chaptersSerialized = htmlspecialchars(json_encode($selectedChapters));
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <link rel="stylesheet" href="css/main.css">
     <link rel="stylesheet" href="css/buttons.css">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Questions</title>
    <?php include 'header.php'; ?>
</head>
<body>
    

<div class="container">
    <h3>Generate Question Paper for Book: <?= htmlspecialchars($book_name) ?> (Class <?= htmlspecialchars($classId) ?>)</h3>

    <form method="POST" action="select_topics.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= $chaptersSerialized ?>">

        <h4>Specify the total number of questions for each chapter:</h4>
        <?php
        foreach ($selectedChapters as $chapter) {
            list($chapterId, $chapterName) = explode('|', $chapter);
            $shortCount = isset($shortQuestions[$chapterId]) ? intval($shortQuestions[$chapterId]) : 0;
            $mcqCount = isset($mcqs[$chapterId]) ? intval($mcqs[$chapterId]) : 0;
            $longCount = isset($longQuestions[$chapterId]) ? intval($longQuestions[$chapterId]) : 0;
            echo "<div style='margin-bottom: 15px;'>";
            echo "<strong>" . htmlspecialchars($chapterName) . "</strong><br>";
            echo "MCQs: <input type='number' name='mcqs[$chapterId]' value='$mcqCount' min='0' style='margin-right: 10px; padding: 5px; width: 80px;' readonly>";
            echo "Short Questions: <input type='number' name='short_questions[$chapterId]' value='$shortCount' min='0' style='margin-right: 10px; padding: 5px; width: 80px;' readonly>";
            echo "Long Questions: <input type='number' name='long_questions[$chapterId]' value='$longCount' min='0' style='padding: 5px; width: 80px;' readonly>";
            echo "</div>";
        }
        ?>

       
    </form>

    <form method="POST" action="generate_question_paper.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= $chaptersSerialized ?>">
        <?php
        // Pass pattern mode and question count forward
        $patternMode = isset($_POST['pattern_mode']) && $_POST['pattern_mode'] === 'without' ? 'without' : 'with';
        $patternQCount = isset($_POST['pattern_qcount']) ? intval($_POST['pattern_qcount']) : 3;
        echo "<input type='hidden' name='pattern_mode' value='" . htmlspecialchars($patternMode) . "'>";
        echo "<input type='hidden' name='pattern_qcount' value='" . htmlspecialchars($patternQCount) . "'>";

        // Pass per-chapter long placements forward if provided
        if (!empty($_POST['long_qnum']) && is_array($_POST['long_qnum']) && !empty($_POST['long_part']) && is_array($_POST['long_part'])) {
            foreach ($_POST['long_qnum'] as $chapId => $qnums) {
                $chapIdSafe = htmlspecialchars($chapId);
                $parts = isset($_POST['long_part'][$chapId]) ? $_POST['long_part'][$chapId] : [];
                for ($i = 0; $i < count($qnums); $i++) {
                    $qVal = htmlspecialchars($qnums[$i]);
                    $pVal = htmlspecialchars(isset($parts[$i]) ? $parts[$i] : 'a');
                    echo "<input type='hidden' name='long_qnum[$chapIdSafe][]' value='$qVal'>";
                    echo "<input type='hidden' name='long_part[$chapIdSafe][]' value='$pVal'>";
                }
            }
        }
        // Pass all chapter question counts
        foreach ($selectedChapters as $chapter) {
            list($chapterId, $chapterName) = explode('|', $chapter);
            $shortCount = isset($shortQuestions[$chapterId]) ? intval($shortQuestions[$chapterId]) : 0;
            $mcqCount = isset($mcqs[$chapterId]) ? intval($mcqs[$chapterId]) : 0;
            $longCount = isset($longQuestions[$chapterId]) ? intval($longQuestions[$chapterId]) : 0;
            echo "<input type='hidden' name='chapter_ids[]' value='" . htmlspecialchars($chapterId) . "'>";
            echo "<input type='hidden' name='mcqs[$chapterId]' value='$mcqCount'>";
            echo "<input type='hidden' name='short_questions[$chapterId]' value='$shortCount'>";
            echo "<input type='hidden' name='long_questions[$chapterId]' value='$longCount'>";
        }
        ?>

       <div class="btn-wrapper">
  <button type="submit" class="btn">
    <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"
      ></path>
    </svg>



    <div style="min-width: 16.2em;" class="txt-wrapper">
      <div class="txt-1">
        <span class="btn-letter">G</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">e</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">Q</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">s</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">o</span>
        <span class="btn-letter">n</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">P</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">p</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
      </div>

      <div class="txt-2">
        <span class="btn-letter">G</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">n</span>
        <span class="btn-letter">g</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">Q</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">s</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">i</span>
        <span class="btn-letter">o</span>
        <span class="btn-letter">n</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">P</span>
        <span class="btn-letter">a</span>
        <span class="btn-letter">p</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">r</span>
        <span class="btn-letter">.</span>
        <span class="btn-letter">.</span>
        <span class="btn-letter">.</span>
      </div>
    </div>
  </button>
</div>


        <br><br>

           
    </form>
    
<button class="go-back-btn" onclick="window.history.back()">⬅ Go Back to Chapters</button>
</div>

<?php include 'footer.php' ?>
</body>
</html>
<style>
    .container {
        
        width: 90%;
        max-width: 800px;
        margin: 10% auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        
    }
    .topic_btn {
        display: inline-block;
    }
    h3, h4 {
        color: #333;
        margin-bottom: 15px;
    }

    input[type="number"] {
        width: 80px;
        padding: 5px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    

    
</style>
