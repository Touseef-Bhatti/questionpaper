<?php
session_start();
include '../db_connect.php';

// Function to create a slug from a string
function createSlug($string) {
    // Convert to lowercase
    $slug = strtolower($string);
    // Replace spaces and other separators with hyphens
    $slug = preg_replace('/[\s_]+/', '-', $slug);
    // Remove special characters
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    // Remove leading and trailing hyphens
    return trim($slug, '-');
}

// Function to get class_id from slug (like "9th", "10th", "11th", "12th" or just "10")
function getClassIdFromSlug($slug, $conn) {
    $slug = preg_replace('/[^0-9]/', '', $slug);
    $class_id = intval($slug);
    if (in_array($class_id, [9, 10, 11, 12])) {
        return $class_id;
    }
    return 0;
}

// Function to get book_id from slug and class_id
function getBookIdFromSlug($book_slug, $class_id, $conn) {
    // First, try exact match with slugs stored in database
    $allBooksStmt = $conn->prepare("SELECT book_id, book_name FROM book WHERE class_id = ?");
    $allBooksStmt->bind_param('i', $class_id);
    $allBooksStmt->execute();
    $allBooksResult = $allBooksStmt->get_result();
    
    while ($book = $allBooksResult->fetch_assoc()) {
        $bookNameSlug = createSlug($book['book_name']);
        if ($bookNameSlug === $book_slug || strpos($bookNameSlug, $book_slug) !== false || strpos($book_slug, $bookNameSlug) !== false) {
            $allBooksStmt->close();
            return $book['book_id'];
        }
    }
    
    $allBooksStmt->close();
    return 0;
}

// Get parameters
$class_id = 0;
$book_id = 0;
$mcq_count = 10;

// Check POST first
if (isset($_POST['class_id']) && isset($_POST['book_id'])) {
    $class_id = intval($_POST['class_id']);
    $book_id = intval($_POST['book_id']);
    $mcq_count = intval($_POST['mcq_count'] ?? 10);
} 
// Check GET for class_id and book_id
elseif (isset($_GET['class_id']) && isset($_GET['book_id'])) {
    $class_id = intval($_GET['class_id']);
    $book_id = intval($_GET['book_id']);
    $mcq_count = intval($_GET['mcq_count'] ?? 10);
}
// Check for slug-based URL (like /9th/physics-MCQs-quiz)
elseif (isset($_GET['class_slug']) && isset($_GET['book_slug'])) {
    $class_id = getClassIdFromSlug($_GET['class_slug'], $conn);
    $book_id = getBookIdFromSlug($_GET['book_slug'], $class_id, $conn);
    $mcq_count = intval($_GET['mcq_count'] ?? 10);
}

// Validate
if (!$class_id || !$book_id) {
    header('Location: quiz_setup.php');
    exit;
}

// Fetch class and book details for SEO
$classStmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$classStmt->bind_param('i', $class_id);
$classStmt->execute();
$classResult = $classStmt->get_result();
$classData = $classResult->fetch_assoc();
$classStmt->close();

$bookStmt = $conn->prepare("SELECT book_name FROM book WHERE book_id = ? AND class_id = ?");
$bookStmt->bind_param('ii', $book_id, $class_id);
$bookStmt->execute();
$bookResult = $bookStmt->get_result();
$bookData = $bookResult->fetch_assoc();
$bookStmt->close();

if (!$classData || !$bookData) {
    header('Location: quiz_setup.php');
    exit;
}

$class_name = $classData['class_name'];
$book_name = $bookData['book_name'];

// Create SEO slug
$book_slug = createSlug($book_name);
// Add 2026 suffix to SEO URL (main ranking page)
$seoUrl = "class-{$class_id}-{$book_slug}-mcqs-2026";
$canonicalUrl = "https://{$_SERVER['HTTP_HOST']}/{$seoUrl}";

// SEO setup - include high-value search patterns for Pakistan & India boards
$pageTitle = "{$class_name} {$book_name} MCQs 2026 — Chapter Wise Online Test with Answers | Ahmad Learning Hub";
$pageDesc = "Free {$class_name} {$book_name} chapter wise MCQs online test with answers for 2026 board exams. Punjab Board, Federal Board (FBISE), Sindh Board & CBSE. Instant grading, solved MCQs, important guess MCQs & funny mode quiz.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords" content="<?= strtolower($class_name) ?> <?= strtolower($book_name) ?> mcqs, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> mcqs chapter wise, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> online test, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> important mcqs, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> guess mcqs, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> solved mcqs, <?= strtolower($class_name) ?> <?= strtolower($book_name) ?> mcqs with answers, chapter wise mcqs <?= strtolower($book_name) ?>, online mcqs test, board exam mcqs 2026, matric mcqs, fsc mcqs, cbse mcqs, funny mode quiz, Punjab Board, FBISE, Sindh Board, CBSE, Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og.jpg">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="twitter:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og.jpg">
    
    <!-- JSON-LD Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Quiz",
      "name": "<?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQ Quiz",
      "description": "<?= htmlspecialchars($pageDesc) ?>",
      "educationalLevel": "Secondary and Higher Secondary Education",
      "about": {
        "@type": "Course",
        "name": "<?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?>",
        "provider": {
          "@type": "EducationalOrganization",
          "name": "Ahmad Learning Hub",
          "url": "https://ahmadlearninghub.com.pk"
        }
      },
      "audience": {"@type": "EducationalAudience", "educationalRole": "student"},
      "provider": {
        "@type": "EducationalOrganization",
        "name": "Ahmad Learning Hub",
        "url": "https://ahmadlearninghub.com.pk"
      }
    }
    </script>

    <!-- FAQ Structured Data for Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Are these <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs free?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes — every chapter wise MCQs online test on Ahmad Learning Hub is 100% free with no registration required."
          }
        },
        {
          "@type": "Question",
          "name": "Which boards do these MCQs cover?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Our MCQs cover the textbooks prescribed by Punjab Board, Federal Board (FBISE), Sindh Board and CBSE."
          }
        },
        {
          "@type": "Question",
          "name": "What is funny mode and how does it help?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Funny mode rewrites answer explanations with humour and pop-culture references. Research shows humour increases memory retention by up to 20%, making it easier to remember complex concepts."
          }
        },
        {
          "@type": "Question",
          "name": "Can I select specific chapters for the quiz?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. You can select one or more chapters, or leave the selection empty to include all chapters from the book."
          }
        },
        {
          "@type": "Question",
          "name": "How many MCQs can I practise per session?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "You can set any number between 1 and 100. We recommend 20–50 MCQs for a focused practice session."
          }
        }
      ]
    }
    </script>

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php include '../header.php'; ?>

<div class="main-content">
    <div class="quiz-setup-container">
        <header class="setup-header">
          <h1><?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs 2026</h1>
          <p class="desc">Chapter-wise <?= htmlspecialchars($book_name) ?> MCQs for <?= htmlspecialchars($class_name) ?> — practice online with instant grading, topic filters, and timed tests for board exam success in 2026.</p>
          <p class="lead" style="margin-top:12px;">Top searches: "<?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs 2026", "online mcqs Pakistan 2026", "chapter wise mcqs <?= htmlspecialchars($book_name) ?>"</p>
        </header>

        <form id="quizForm" method="POST" action="<?= htmlspecialchars($assetBase . 'class-' . $class_id . '-' . $book_slug . '-mcqs-test-2026', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="class_id" value="<?= $class_id ?>">
            <input type="hidden" name="book_id" value="<?= $book_id ?>">
            
            <div class="section-wrapper">
                <label for="mcq_count">
                    Number of MCQs
                </label>
                <div style="margin-bottom: 8px;">
                    <input type="number" id="mcq_count" name="mcq_count" min="1" max="100" value="<?= $mcq_count ?>" required>
                </div>
                <div class="hint" style="margin-bottom: 0;">Recommended: 20–50 for a full practice session.</div>
            </div>
            
            <div class="section-wrapper">
                <label for="chapters">
                    Chapters (optional, multi-select)
                </label>
                <div class="chapter-selector" id="chapterSelector">
                    <div class="selector-hint">Loading chapters...</div>
                </div>
                <div class="chapter-actions">
                    <button type="button" class="chapter-action-btn select-all" id="selectAllBtn">Select All</button>
                    <button type="button" class="chapter-action-btn deselect-all" id="deselectAllBtn">Deselect All</button>
                </div>
                <input type="hidden" name="chapter_ids" id="chapter_ids">
                <div class="hint" style="margin-bottom: 0;">If you don't select any chapters, we'll include all chapters from the book.</div>
            </div>

            <div class="actions">
                <button type="button" class="btn secondary" id="backBtn" onclick="window.location.href='quiz_setup.php'">Back</button>
                <button type="submit" class="btn primary">Start Quiz</button>
            </div>
        </form>
    </div>
    
    <!-- SEO Article Section - Comprehensive Blog Style -->
    <article class="seo-article-section blog-layout">
        <div class="blog-container">
            <header class="blog-header">
                <h2 class="blog-title">Complete Guide to <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs — Chapter Wise Online Test with Answers (2026)</h2>
                <div class="blog-meta">
                    <span class="category"><?= htmlspecialchars($class_name) ?> Board Exams 2026</span>
                    <span class="read-time">14 min read</span>
                </div>
            </header>
            <section class="blog-content">
                <p class="lead">
                    If you are searching for <strong><?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs</strong>, you have landed on the right page. Ahmad Learning Hub provides a completely free, chapter wise online MCQs test with instant grading — designed specifically for students preparing for <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong>, <strong>Sindh Board</strong> and <strong>CBSE</strong> examinations in 2026. Whether you need <strong><?= htmlspecialchars(strtolower($class_name)) ?> <?= htmlspecialchars(strtolower($book_name)) ?> chapter wise MCQs with answers</strong>, <strong>important MCQs</strong>, <strong>guess MCQs</strong> or a full <strong>online test</strong>, this page has everything you need to score top marks in the objective section of your board exam.
                </p>

                <div class="blog-featured-box">
                    <h4>What You Get on This Page</h4>
                    <ul>
                        <li><strong>Chapter wise MCQs</strong> — select any combination of chapters from <?= htmlspecialchars($book_name) ?> and practise only what you need.</li>
                        <li><strong>Customisable quiz length</strong> — choose anywhere from 1 to 100 MCQs per session (we recommend 20–50 for optimal revision).</li>
                        <li><strong>Instant grading</strong> — see your score, correct answers and explanations the moment you finish.</li>
                        <li><strong>Solved MCQs</strong> — every question comes with a one-line answer explanation so you learn from mistakes.</li>
                        <li><strong>Funny mode</strong> — a unique humour-infused quiz experience that makes studying actually enjoyable (and boosts memory retention).</li>
                    </ul>
                </div>

                <h2>Why <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQ Practice Matters</h2>
                <p>
                    Many school and board assessments include an objective section, so regular MCQ practice can help students check recall, interpretation, and application of textbook concepts. The exact format, marks, syllabus, and question style depend on the relevant board and examination year.
                </p>
                <p>
                    <strong>Chapter-wise practice</strong> makes weak areas easier to identify. The chapters shown here represent content currently available in the question bank; they do not guarantee complete syllabus or official board coverage. Check your current textbook and board instructions before relying on a practice set.
                </p>

                <h3>A Practical Chapter-Wise Revision Routine</h3>
                <p>
                    Select one chapter, attempt a manageable number of questions, and review every mistake before moving on. Return to the relevant textbook section when an answer or explanation is unclear.
                </p>

                <h2>How to Use This <?= htmlspecialchars($book_name) ?> MCQ Page</h2>
                <ol>
                    <li><strong>Choose a quiz length</strong> that you can complete and review carefully.</li>
                    <li><strong>Select chapters</strong> that match what you have studied, or use the available full-book selection.</li>
                    <li><strong>Complete the quiz</strong> without checking notes when you want a realistic self-assessment.</li>
                    <li><strong>Review the result</strong> and verify uncertain answers with a textbook or teacher.</li>
                    <li><strong>Repeat weak chapters</strong> after revising the concepts you missed.</li>
                </ol>

                <h2>Important MCQs for <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?></h2>
                <p>
                    No practice website can reliably predict the questions that will appear in an examination. Use this question bank to test concepts from the available chapters, and use official past papers when you want to study recurring examination patterns.
                </p>

                <h2>Review Answers, Not Only Scores</h2>
                <p>
                    Do not treat an MCQ as only a choice of letters. Where an explanation is available, use it to understand the underlying idea. Some stored or AI-generated questions may not include a complete explanation, so confirm uncertain answers with reliable course material.
                </p>

                <h2>Using Funny Mode Responsibly</h2>
                <p>
                    Funny mode presents some explanations with simple humour or familiar analogies. It can make revision less repetitive, but the standard explanation and your textbook should remain the main reference. If a humorous explanation is unclear, switch back to the standard version and verify the concept.
                </p>

                <h2>Who Is This Page For?</h2>
                <ul>
                    <li><strong>Class 9-12 students</strong> practising subjects and chapters available in the question bank.</li>
                    <li><strong>Independent learners</strong> using short quizzes for self-checking after studying a topic.</li>
                    <li><strong>Teachers and tutors</strong> using the quiz as an informal activity after reviewing the selected questions.</li>
                </ul>

                <h2>Tips for Better MCQ Practice</h2>
                <ol>
                    <li>Read the relevant textbook chapter before attempting a quiz.</li>
                    <li>Use smaller sets when you need time to review every answer.</li>
                    <li>Record topics you repeatedly answer incorrectly.</li>
                    <li>Return after revision and compare your new result.</li>
                    <li>Use official board material to confirm the current syllabus and exam format.</li>
                </ol>

                <h2>Frequently Asked Questions</h2>
                <h3>Are these <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs free?</h3>
                <p>Yes — every chapter wise MCQs online test on Ahmad Learning Hub is 100 % free with no registration or sign-up required.</p>

                <h3>Which boards do these MCQs cover?</h3>
                <p>Our MCQs are written from the textbooks prescribed by <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong>, <strong>Sindh Board</strong> and <strong>CBSE</strong>. The question bank is updated annually to reflect any syllabus changes.</p>

                <h3>What is funny mode and how does it help?</h3>
                <p><strong>Funny mode</strong> rewrites answer explanations with humour, analogies and pop-culture references. Research shows humour increases memory retention by up to 20 %, making it easier to remember complex concepts during the exam.</p>

                <h3>Can I select specific chapters for the quiz?</h3>
                <p>Absolutely. Use the chapter selector above to tick one or more chapters. If you leave all chapters unselected, the quiz will include MCQs from the entire book.</p>

                <h3>How many MCQs can I practise per session?</h3>
                <p>You can set any number between 1 and 100. For a focused revision session, we recommend 20–50 MCQs.</p>

                <div class="blog-cta-box">
                    <h3>Ready to Start? Select Your Chapters Above!</h3>
                    <p>Don't just read about preparing — actually prepare. Choose your chapters, set your MCQ count, and hit "Start Quiz". Try <strong>funny mode</strong> for a study experience you will genuinely enjoy. Your board exam success starts with one click.</p>
                </div>
            </section>
        </div>
    </article>
</div>

<?php include __DIR__ . '/../includes/ai_loader.php'; ?>
<?php include '../footer.php'; ?>

<script>
const classId = <?= $class_id ?>;
const bookId = <?= $book_id ?>;
const chapterSelector = document.getElementById('chapterSelector');
const chapterIdsInput = document.getElementById('chapter_ids');
const selectAllBtn = document.getElementById('selectAllBtn');
const deselectAllBtn = document.getElementById('deselectAllBtn');
let selectedChapterIds = [];
let allChapters = [];

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

async function loadChapters() {
  chapterSelector.innerHTML = '<div class="selector-hint">Loading chapters...</div>';
  
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'chapters', class_id: classId, book_id: bookId }));
    allChapters = await res.json();
    
    if (allChapters.length === 0) {
      chapterSelector.innerHTML = '<div class="selector-hint">No chapters found for this book</div>';
      return;
    }
    
    // Create checkbox list for chapters
    const chapterHTML = allChapters.map(chapter => `
      <div class="chapter-item" data-id="${chapter.chapter_id}" onclick="toggleChapter(${chapter.chapter_id}, this)">
        <input type="checkbox" id="ch_${chapter.chapter_id}" value="${chapter.chapter_id}" onchange="handleChapterChange(${chapter.chapter_id})" onclick="event.stopPropagation()">
        <label for="ch_${chapter.chapter_id}" onclick="event.stopPropagation()">${chapter.chapter_name}</label>
      </div>
    `).join('');
    
    chapterSelector.innerHTML = chapterHTML;
  } catch (error) {
    chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
    console.error('Error loading chapters:', error);
  }
}

function toggleChapter(chapterId, itemElement) {
  const checkbox = itemElement.querySelector('input[type="checkbox"]');
  checkbox.checked = !checkbox.checked;
  handleChapterChange(chapterId);
}

function handleChapterChange(chapterId) {
  const itemElement = document.querySelector(`.chapter-item[data-id="${chapterId}"]`);
  if (selectedChapterIds.includes(chapterId)) {
    selectedChapterIds = selectedChapterIds.filter(id => id !== chapterId);
    itemElement.classList.remove('selected');
  } else {
    selectedChapterIds.push(chapterId);
    itemElement.classList.add('selected');
  }
  updateChapterInput();
}

function updateChapterInput() {
  chapterIdsInput.value = selectedChapterIds.join(',');
}

// Select all chapters
selectAllBtn.addEventListener('click', function() {
  selectedChapterIds = [];
  allChapters.forEach(chapter => {
    selectedChapterIds.push(chapter.chapter_id);
    const itemElement = document.querySelector(`.chapter-item[data-id="${chapter.chapter_id}"]`);
    const checkbox = itemElement.querySelector('input[type="checkbox"]');
    checkbox.checked = true;
    itemElement.classList.add('selected');
  });
  updateChapterInput();
});

// Deselect all chapters
deselectAllBtn.addEventListener('click', function() {
  selectedChapterIds = [];
  allChapters.forEach(chapter => {
    const itemElement = document.querySelector(`.chapter-item[data-id="${chapter.chapter_id}"]`);
    const checkbox = itemElement.querySelector('input[type="checkbox"]');
    checkbox.checked = false;
    itemElement.classList.remove('selected');
  });
  updateChapterInput();
});

// Load chapters on page load
loadChapters();
</script>
</body>
</html>
