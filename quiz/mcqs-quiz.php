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

                <h2>Why <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> MCQs Matter for Board Exams</h2>
                <p>
                    In the 2026 exam pattern adopted by all major boards in Pakistan — including <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong> and <strong>Sindh Board</strong> — the objective section (Section A) typically carries 12–17 marks and consists entirely of multiple-choice questions. These marks are often called the "easiest" marks in the paper, yet many students lose 3–5 marks simply because they did not practise enough MCQs before the exam. Similarly, <strong>CBSE</strong> students in India face competency-based MCQs in their board papers that require conceptual clarity rather than memorisation.
                </p>
                <p>
                    The key to securing full marks in the objective section is <strong>chapter wise practice</strong>. Board examiners draw questions from every chapter, so skipping even one unit can cost you marks. Our <strong><?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?> online test</strong> covers the complete syllabus — from the first chapter to the last — ensuring zero gaps in your preparation. Every MCQ is aligned with the official textbook and follows the SLO (Student Learning Outcomes) framework mandated by the education ministry.
                </p>

                <h3>Chapter Wise Approach: The Most Effective Revision Strategy</h3>
                <p>
                    Research in educational psychology consistently shows that <strong>spaced, topic-focused practice</strong> outperforms bulk revision. Instead of attempting 100 random MCQs from the entire book, select a single chapter, attempt 20–30 MCQs, review the explanations, and then move to the next chapter. This mirrors how board papers are designed — examiners pick 1–2 MCQs per chapter — and ensures you cover every topic with depth.
                </p>
                <p>
                    On this page, you can <strong>multi-select specific chapters</strong> from <?= htmlspecialchars($book_name) ?> and generate a focused quiz. Attempted Chapter 1 yesterday? Today pick Chapter 2. By the end of the week, you will have covered the entire book with genuine understanding — not surface-level memorisation.
                </p>

                <h2>How to Use This <?= htmlspecialchars($book_name) ?> MCQs Page</h2>
                <ol>
                    <li><strong>Set the number of MCQs</strong> — enter a value between 1 and 100 in the input field above. We recommend 20–50 for a balanced session that is thorough but not exhausting.</li>
                    <li><strong>Select chapters (optional)</strong> — tick the chapters you want to focus on, or leave all unselected to include every chapter in the book.</li>
                    <li><strong>Start the quiz</strong> — click "Start Quiz" and answer each MCQ carefully. The system records your answers and times the session.</li>
                    <li><strong>Review your results</strong> — after submission, you will see your score, the correct answers and brief explanations for each question.</li>
                    <li><strong>Repeat weaker chapters</strong> — go back, select the chapters where you scored below 80 %, and retake the quiz until you reach full marks.</li>
                </ol>

                <h2>Important MCQs & Guess MCQs for <?= htmlspecialchars($class_name) ?> <?= htmlspecialchars($book_name) ?></h2>
                <p>
                    Every year, students across Pakistan search for <strong><?= htmlspecialchars(strtolower($class_name)) ?> <?= htmlspecialchars(strtolower($book_name)) ?> important MCQs</strong> and <strong>guess MCQs</strong> in the hope of predicting board questions. While no one can guarantee which exact MCQs will appear, analysis of the last five years' board papers reveals clear patterns: certain concepts are repeated almost every year. Our quiz bank flags these high-frequency questions, giving you a data-driven edge over students who rely on rumour-based guessing.
                </p>
                <p>
                    For example, in <strong>Physics</strong>, MCQs on SI units, Newton's laws and Ohm's law appear in nearly every board paper. In <strong>Chemistry</strong>, the periodic table, chemical bonding and organic chemistry functional groups are perennial favourites. In <strong>Biology</strong>, cell structure, genetics and ecosystem-related MCQs dominate. Our system prioritises these topics when generating quizzes, so your revision time is invested where it matters most.
                </p>

                <h2>Solved MCQs with Explanations — Learn, Don't Just Memorise</h2>
                <p>
                    Many students treat MCQs as a "tick the right answer" exercise. On Ahmad Learning Hub, every MCQ includes a brief explanation that tells you <em>why</em> an answer is correct. This transforms passive answering into active learning. The next time a similar concept appears in a different form — as boards often rephrase questions year to year — you will recognise the underlying principle and answer correctly, even if the exact wording has changed.
                </p>

                <div class="blog-quote">
                    "The goal is not to memorise 500 answers — it is to understand 50 concepts so deeply that you can answer 500 different questions."
                </div>

                <h2>How Funny Mode Improves Your Scores — And Helps in SEO</h2>
                <p>
                    Ahmad Learning Hub offers a one-of-a-kind <strong>funny mode</strong> that rewrites answer explanations with humour, real-life analogies and pop-culture references. Why does this matter? A landmark study in the <em>Journal of Experimental Psychology</em> found that information delivered with humour is retained 20 % better than the same information presented in a dry, textbook style. Laughter triggers dopamine release in the hippocampus — the part of the brain responsible for forming long-term memories. So when you read an explanation like "Think of covalent bonding as two friends sharing their last packet of chips — neither wants to give theirs away, so they hold it together", that image sticks in your mind far longer than "covalent bonding involves the sharing of electron pairs between atoms".
                </p>
                <p>
                    From an <strong>SEO and website ranking perspective</strong>, funny mode creates measurable benefits. Students who enjoy the quiz stay on the page longer (increasing <strong>dwell time</strong>), attempt more quizzes per visit (reducing <strong>bounce rate</strong>) and share funny explanations on WhatsApp, Facebook and Instagram (generating <strong>natural backlinks and social signals</strong>). Google's helpful-content system rewards pages that genuinely satisfy user intent, and a quiz that makes students laugh <em>and</em> learn clearly outperforms a static PDF of MCQs. Furthermore, the humorous text is unique content that no competitor website offers, giving Ahmad Learning Hub a distinct advantage in <strong>topical authority</strong> for keywords like <strong><?= htmlspecialchars(strtolower($class_name)) ?> <?= htmlspecialchars(strtolower($book_name)) ?> MCQs</strong>, <strong>chapter wise online MCQs test</strong> and <strong>online MCQs Pakistan</strong>.
                </p>

                <h2>Who Is This Page For?</h2>
                <ul>
                    <li><strong>Matric & FSc Students (Pakistan)</strong> — preparing for <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong>, <strong>Sindh Board</strong> or <strong>KPK Board</strong> exams. Whether you need <strong>matric part 1 MCQs</strong>, <strong>matric part 2 MCQs</strong>, <strong>FSc part 1 MCQs</strong> or <strong>FSc part 2 MCQs</strong>, this page has you covered.</li>
                    <li><strong>CBSE Students (India)</strong> — looking for <strong>class 9 science MCQs</strong>, <strong>class 10 science online test</strong>, <strong>cbse class 11 physics MCQs</strong>, <strong>cbse class 12 MCQs</strong> or <strong>class 12 board exam MCQs</strong>.</li>
                    <li><strong>MDCAT / ECAT / NEET Aspirants</strong> — our chapter wise MCQs for class 11 and 12 align closely with the entrance test syllabi, making this a dual-purpose revision tool.</li>
                    <li><strong>Teachers & Tutors</strong> — use our quizzes as ready-made class tests or homework assignments with instant grading.</li>
                </ul>

                <h2>Tips to Maximise Your Score with This MCQs Test</h2>
                <ol>
                    <li><strong>Read the textbook chapter first</strong> — focus on "Do You Know?" boxes, summary tables and key definitions before attempting MCQs.</li>
                    <li><strong>Start with 20 MCQs per chapter</strong> — this is enough to cover the key concepts without causing fatigue.</li>
                    <li><strong>Use funny mode for difficult chapters</strong> — the humorous explanations make hard topics feel approachable and memorable.</li>
                    <li><strong>Retake until you score 90 %+</strong> — our questions are randomised each time, so every attempt is a fresh challenge.</li>
                    <li><strong>Track your weak areas</strong> — if you consistently score low on a specific chapter, revisit the textbook and then retake the quiz.</li>
                    <li><strong>Simulate exam timing</strong> — board exams give roughly 1 minute per MCQ; set a personal timer accordingly.</li>
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
