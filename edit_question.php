<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - Ahmad Learning Hub</title>
</head>
<body>
<?php include 'header.php'; ?>
<h2>Edit Question</h2>
<p>Use this page to edit an existing question in the database.</p>
<!-- Edit question form or content here -->
<?php
include 'db_connect.php';
// Validate and retrieve question ID and type
if (!isset($_GET['id'], $_GET['type'])) {
    die("<h2 style='color:red;'>Invalid request. Question ID and type are required.</h2>");
}
$questionId = intval($_GET['id']);
$questionType = htmlspecialchars($_GET['type']);
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = trim($conn->real_escape_string($_POST['question_text']));
    $topic = trim($conn->real_escape_string($_POST['topic']));
    // Update the question in the database
    $query = "UPDATE questions SET question_text = '$questionText', topic = '$topic' WHERE id  = $questionId";
    if ($conn->query($query)) {
        $successMessage = "Question updated successfully!";
    } else {
        $errorMessage = "Error updating question: " . $conn->error;
    }
}
// Fetch the question details
$query = "SELECT question_text, topic FROM questions WHERE id = $questionId";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $question = $result->fetch_assoc();
} else {
    die("<h2 style='color:red;'>Question not found.</h2>");
}
?>
<div class="container">
    <h3>Edit Question</h3>
    <?php if (isset($successMessage)): ?>
        <div class="message success"> <?= htmlspecialchars($successMessage) ?> </div>
    <?php endif; ?>
    <?php if (isset($errorMessage)): ?>
        <div class="message error"> <?= htmlspecialchars($errorMessage) ?> </div>
    <?php endif; ?>
    <form method="POST" action="">
        <label for="question_text">Question Text:</label>
        <textarea name="question_text" id="question_text" rows="4" required><?= htmlspecialchars($question['question_text']) ?></textarea>
        <label for="topic">Topic:</label>
        <input type="text" name="topic" id="topic" value="<?= htmlspecialchars($question['topic']) ?>" required>
        <button type="submit">Update Question</button>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>