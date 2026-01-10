<?php
// Require authentication before accessing this page
// require_once 'auth_check.php';
include 'db_connect.php';


// Ensure class_id is provided
if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    // Redirect if no class_id is provided
    header('Location: select_class.php');
    exit;
}


$classId = intval($_GET['class_id']); // Sanitize class_id

// Select all books for this class
$bookQuery = "SELECT * FROM book WHERE class_id = $classId";
$result = $conn->query($bookQuery);

if (!$result) {
    die("<h2 style='color:red;'>Database error: " . htmlspecialchars($conn->error) . "</h2>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_book.css">
    <link rel="stylesheet" href="css/buttons.css">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Select a book for 9th or 10th Class to generate question papers, take online MCQs, and access notes. Ahmad Learning Hub offers up-to-date Punjab Board syllabus resources.">
    <meta name="keywords" content="9th class, 10th class, question paper generator, online mcqs, quiz, test paper, new syllabus punjab board, pakistan board up to date papers, online tests, notes">
    <title>9th & 10th Class Question Papers Generator, Online Tests & Notes | Ahmad Learning Hub</title>
  
</head>
<body>
    <?php include 'header.php'; ?>
    <h1>Select Book for Class <?= htmlspecialchars($classId) ?></h1>

    <div class="main-container">
    <div class="classes-container" id="book-box-container">

    <div class="classes-grid" id="books-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    // Example: Make some  "coming soon" book

                    $isComingSoon = in_array($row['book_id'], []);

                ?>
                <div 
                    class="class-box <?= $isComingSoon ? 'coming-soon' : '' ?>" 
                    data-book-id="<?= htmlspecialchars($row['book_id']) ?>" 
                    data-book-name="<?= htmlspecialchars($row['book_name']) ?>"
                    onclick="<?= $isComingSoon ? 'showComingSoon()' : 'navigateToChapters(this)' ?>"
                >
                    <?= htmlspecialchars($row['book_name']) ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <h3 style="color:red;">No books found for this class.</h3>
        <?php endif; ?>
    </div>
    </div>
    <button class="go-back-btn" onclick="window.history.back()">⬅ Go Back</button>

    </div>
<?php include 'footer.php'; ?>

    <script>
        const classId = '<?= urlencode($classId) ?>';
        
        function navigateToChapters(el) {
            const bookName = el.getAttribute('data-book-name');
            if (!bookName) {
                alert('Please select a valid book.');
                return;
            }
            window.location.href = `select_chapters.php?class_id=${classId}&book_name=${encodeURIComponent(bookName)}`;
        }
        
        function showComingSoon() {
            alert('coming soon!');
        }
    </script>

    
    
</body>
</html>