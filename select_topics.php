<?php
include 'db_connect.php';

if (!isset($_POST['chapters']) || empty($_POST['chapters'])) {
    die("<h2 style='color:red;'>No chapters selected. Please go back and select chapters.</h2>");
}

$classId = intval($_POST['class_id']);
$book_name = trim($conn->real_escape_string($_POST['book_name']));
$selectedChapters = json_decode(htmlspecialchars_decode($_POST['chapters']), true);

// Fetch topics based on selected chapters
$chapterIds = array_map(function($chapter) { return explode('|', $chapter)[0]; }, $selectedChapters);
$chapterIdsStr = implode(',', $chapterIds);

// Query to get topics for the selected chapters
$query = "SELECT id, topic FROM questions WHERE chapter_id IN ($chapterIdsStr)";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("<h2>No topics available for the selected chapters.</h2>");
}

?>

<div class="container">
    <h3>Select Topics for Book: <?= htmlspecialchars($book_name) ?> (Class <?= htmlspecialchars($classId) ?>)</h3>

    <form method="POST" action="generate_question_paper.php">
        <input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
        <input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">
        <input type="hidden" name="chapters" value="<?= htmlspecialchars($_POST['chapters']) ?>">

        <h4>Select Topics:</h4>
        <div class="topics-list">
            <?php while ($topic = $result->fetch_assoc()) { ?>
                <label>
                    <input type="checkbox" name="selected_topics[]" value="<?= $topic['id'] ?>">
                    <?= htmlspecialchars($topic['topic']) ?>
                </label><br>
            <?php } ?>
        </div>

        <button type="submit">Generate Question Paper</button>
    </form>
</div>

<style>
    .container {
        width: 90%;
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h3, h4 {
        color: #333;
        margin-bottom: 15px;
    }

    .topics-list label {
        font-size: 16px;
        margin-bottom: 10px;
        display: block;
    }

    button {
        padding: 12px 20px;
        font-size: 16px;
        border: none;
        background: #28a745;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
        margin-top: 15px;
    }

    button:hover {
        background: #218838;
    }
</style>
