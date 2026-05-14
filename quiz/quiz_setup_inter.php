<?php
session_start();
// quiz_setup_inter.php - Public quiz setup page for Class 11 & 12
include '../db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online MCQs Test For 11th and 12th (FSc, ICS) Board Exams - Ahmad Learning Hub</title>
    
    <!-- Enhanced SEO Meta Tags -->
    <meta name="description" content="Chapter Wise MCQs for HSSC Part 1 & 2. Online MCQs test for Class 11 and 12 Board Exams (Physics, Chemistry, Biology, Math). Prep for MDCAT & ECAT - Ahmad Learning Hub">
    <meta name="keywords" content="FSc MCQs, 11th class MCQs, 12th class MCQs, MDCAT preparation, ECAT preparation, online MCQs practice, HSSC MCQs, Physics FSc MCQs, Biology MDCAT MCQs, Chemistry ECAT MCQs, All board MCQs, Ahmad Learning Hub, automatic test generator">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ahmadlearninghub.com.pk/class-11-and-12-online-mcqs-prepation-test">
    <meta property="og:title" content="Online MCQs Test For 11th and 12th (FSc, ICS) Board Exams - Ahmad Learning Hub">
    <meta property="og:description" content="Prepare for FSc and Entry Tests (MDCAT/ECAT) with 100% accurate MCQs based on the latest 2026 syllabus using advanced AI.">
    <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og-inter.jpg">

    <!-- JSON-LD Structured Data for SEO Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalApplication",
      "name": "Ahmad Learning Hub Intermediate Quiz Builder",
      "description": "An AI-powered application to generate custom MCQs for FSc and ICS students preparing for board and entry tests.",
      "applicationCategory": "Education",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      }
    }
    </script>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://ahmadlearninghub.com.pk/class-11-and-12-online-mcqs-prepation-test">
    <meta property="twitter:title" content="Online MCQs Test For 11th and 12th (FSc, ICS) Board Exams - Ahmad Learning Hub">
    <meta property="twitter:description" content="Tailor your FSc study sessions with our advanced MCQ generator. Practice for Board and MDCAT.">
    <meta property="twitter:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og-inter.jpg">

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php include_once '../header.php'; ?>

<div class="main-content">
    <div class="quiz-setup-container">
        <header class="setup-header">
            <h1>Online MCQs Test For 11th and 12th (Intermediate)</h1>
            <p class="desc">Prepare for HSSC Part 1 & 2 Board Exams and Entry Tests (MDCAT/ECAT). Select your Class below to generate a focused MCQ practice session.</p>
        </header>

        <form id="quizForm" method="POST" action="quiz.php">
            <br>
            <div class="grid">
                <div>
                    <label for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id" required>
                        <option value="">Select a class</option>
                        <?php
                        // Load classes 11 and 12 specifically
                        $cls = $conn->query("SELECT class_id, class_name FROM class WHERE class_id IN (11, 12) ORDER BY class_id ASC");
                        if ($cls && $cls->num_rows > 0) {
                            while ($row = $cls->fetch_assoc()) {
                                echo '<option value="' . (int)$row['class_id'] . '">' . htmlspecialchars($row['class_name']) . '</option>';
                            }
                        } else {
                            // Fallback if class IDs are different in DB
                            echo '<option value="11">11th Class (FSc Part 1)</option>';
                            echo '<option value="12">12th Class (FSc Part 2)</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="book_id">Book</label>
                    <div class="input-with-action">
                        <select id="book_id" name="book_id" required disabled>
                            <option value="">Select a book</option>
                        </select>
                        <button type="button" class="btn topic-btn" onclick="window.location.href='topic-wise-mcqs-test'">Topic</button>
                    </div>
                    <div class="hint">Books are filtered by class.</div>
                </div>
            </div>

            <div class="grid full">
                <div>
                    <label for="chapters">Chapters (optional, multi-select)</label>
                    <div class="chapter-selector" id="chapterSelector">
                        <div class="selector-hint">Select a book first to see available chapters</div>
                    </div>
                    <input type="hidden" name="chapter_ids" id="chapter_ids">
                    <div class="hint">If you don't select any chapters, we'll include all chapters from the selected book.</div>
                </div>
            </div>

            <div class="grid full">
                <div>
                    <label for="mcq_count">Number of MCQs</label>
                    <input type="number" id="mcq_count" name="mcq_count" min="1" max="100" value="10" required>
                    <div class="hint">We will pick this many MCQs randomly from your selection.</div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn secondary" id="resetBtn">Reset</button>
                <button type="submit" class="btn primary">Start Quiz</button>
            </div>
            <br>
        </form>
    </div>

    <!-- SEO Article Section - Comprehensive Blog Style for Inter -->
    <article class="seo-article-section blog-layout">
        <div class="blog-container">
            <header class="blog-header">
                <h1 class="blog-title">The Ultimate Guide to FSc Part 1 & 2 Online MCQs Test & Entry Test Prep 2026</h1>
                <div class="blog-meta">
                    <span class="category">HSSC & MDCAT 2026</span>
                    <span class="read-time">15 min read</span>
                </div>
            </header>

            <section class="blog-content">
                <p class="lead">
                    For Intermediate students in Pakistan, the journey through 11th and 12th class is not just about board exams but also about securing a seat in medical and engineering universities. Utilizing an <strong>online mcqs test</strong> platform is the most efficient way to balance <strong>HSSC exam preparation</strong> with <strong>MDCAT and ECAT</strong> requirements.
                </p>

                <div class="blog-featured-box">
                    <h4>What’s New for 2026?</h4>
                    <ul>
                        <li><strong>Conceptual SLO-based Questions</strong> for Federal and Punjab Boards.</li>
                        <li>High-yield <strong>MDCAT Biology</strong> and <strong>ECAT Mathematics</strong> MCQs.</li>
                        <li>Chapter-wise <strong>FSc Part 1 & 2 Physics</strong> numericals.</li>
                        <li>Timed <strong>online test</strong> sessions to simulate entry test pressure.</li>
                    </ul>
                </div>

                <h2>Mastering FSc Part 1 & 2 All Subjects MCQs</h2>
                <p>
                    The Intermediate years are highly competitive. Whether you are in the Pre-Medical, Pre-Engineering, or ICS group, our platform offers a specialized <strong>online mcqs test</strong> experience for all your core subjects.
                </p>

                <h3>11th & 12th Class Physics: Cracking the MDCAT/ECAT Code</h3>
                <p>
                    Physics at the HSSC level is heavily focused on logic and calculations. From <em>Circular Motion</em> in Part 1 to <em>Electronics</em> in Part 2, our <strong>FSc Physics MCQs</strong> cover all the key topics that frequently appear in both board exams and entry tests. Focus on SI units, dimensional analysis, and short numerical shortcuts.
                </p>

                <h3>Chemistry & Biology for Pre-Medical Students</h3>
                <p>
                    For MDCAT aspirants, Biology and Chemistry are the highest-scoring areas. Our <strong>online test preparation</strong> includes detailed MCQs on <em>Genetics, Evolution, and Bioenergetics</em>. In Chemistry, we emphasize <strong>Organic Chemistry</strong> mechanisms and periodic trends, which are the backbone of the HSSC Part 2 curriculum.
                </p>

                <h2>Mathematics & Computer Science: The ECAT Edge</h2>
                <p>
                    For the Pre-Engineering and ICS groups, speed is everything. Our <strong>online mcqs test</strong> for 11th and 12th Class Mathematics focuses on <em>Calculus, Trigonometry, and Vectors</em>. For Computer Science students, we offer updated questions on <strong>C Language and Database Concepts</strong> to ensure you are ready for both theory and practical-based objectives.
                </p>

                <div class="blog-quote">
                    "Success in HSSC and Entry Tests is 30% knowledge and 70% practice. Regular <strong>online test preparation</strong> is the bridge between an average score and a top position."
                </div>

                <h2>Why Our Platform is Best for Intermediate Students?</h2>
                <p>
                    We understand the pressure of HSSC exams. Our <strong>exam preparation</strong> tool offers:
                </p>
                <ul>
                    <li><strong>MDCAT/ECAT Standard Questions:</strong> Questions that go beyond the textbook to test your logic.</li>
                    <li><strong>Instant Analytical Results:</strong> See your score and the correct options immediately.</li>
                    <li><strong>Chapter-Wise Mastery:</strong> Focus on your weak chapters to improve your aggregate score.</li>
                </ul>

                <h3>Top Strategies for HSSC Board Exam Success</h3>
                <ol>
                    <li><strong>Master the Textbooks:</strong> All board MCQs are derived from the official Punjab or Federal textbooks.</li>
                    <li><strong>Practice Past Entry Tests:</strong> Familiarize yourself with the difficulty level of UHS, ETEA, and NUST exams.</li>
                    <li><strong>Daily Quiz Habit:</strong> Use our <strong>online mcqs test</strong> for just 20 minutes a day to stay sharp.</li>
                </ol>

                <div class="blog-cta-box">
                    <h3>Kickstart Your MDCAT/ECAT Prep Today!</h3>
                    <p>Select your Intermediate Class and Subject above to start your journey toward academic excellence. Your future starts with one click!</p>
                </div>
            </section>
        </div>
    </article>

    <br>
</div>

<?php include __DIR__ . '/../includes/ai_loader.php'; ?>
<?php include '../footer.php'; ?>

<script>
const classSel = document.getElementById('class_id');
const bookSel = document.getElementById('book_id');
const chapterSelector = document.getElementById('chapterSelector');
const chapterIdsInput = document.getElementById('chapter_ids');
const resetBtn = document.getElementById('resetBtn');

let selectedChapterIds = [];

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

async function loadBooks() {
  bookSel.innerHTML = '<option value="">Loading...</option>';
  bookSel.disabled = true;
  clearChapters();
  
  const cid = classSel.value;
  if (!cid) { 
    bookSel.innerHTML = '<option value="">Select a book</option>'; 
    return; 
  }
  
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'books', class_id: cid }));
    const data = await res.json();
    bookSel.innerHTML = '<option value="">Select a book</option>' + data.map(b => `<option value="${b.book_id}">${b.book_name}</option>`).join('');
    bookSel.disabled = false;
  } catch (error) {
    bookSel.innerHTML = '<option value="">Error loading books</option>';
    console.error('Error loading books:', error);
  }
}

async function loadChapters() {
  clearChapters();
  chapterSelector.innerHTML = '<div class="selector-hint">Loading chapters...</div>';
  
  const cid = classSel.value; 
  const bid = bookSel.value;
  
  if (!cid || !bid) {
    chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
    return;
  }
  
  try {
    const res = await fetch('quiz_data.php?' + toQuery({ type: 'chapters', class_id: cid, book_id: bid }));
    const data = await res.json();
    
    if (data.length === 0) {
      chapterSelector.innerHTML = '<div class="selector-hint">No chapters found for this book</div>';
      return;
    }
    
    const chapterHTML = data.map(chapter => `
      <div class="chapter-item" onclick="toggleChapter(${chapter.chapter_id}, this)">
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
  if (selectedChapterIds.includes(chapterId)) {
    selectedChapterIds = selectedChapterIds.filter(id => id !== chapterId);
  } else {
    selectedChapterIds.push(chapterId);
  }
  updateChapterInput();
}

function updateChapterInput() {
  chapterIdsInput.value = selectedChapterIds.join(',');
}

function clearChapters() {
  selectedChapterIds = [];
  chapterIdsInput.value = '';
  chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
}

classSel.addEventListener('change', loadBooks);
bookSel.addEventListener('change', loadChapters);

// Pre-fill form from URL
(async function() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlClassId = urlParams.get('class_id');
  const urlBookId = urlParams.get('book_id');
  const urlChapterId = urlParams.get('chapter_id');

  if (urlClassId) {
    classSel.value = urlClassId;
    await loadBooks();
    
    if (urlBookId) {
      bookSel.value = urlBookId;
      await loadChapters();
      
      if (urlChapterId) {
        setTimeout(() => {
          const chapterCheckbox = document.getElementById(`ch_${urlChapterId}`);
          if (chapterCheckbox) {
            chapterCheckbox.checked = true;
            handleChapterChange(parseInt(urlChapterId));
          }
        }, 500);
      }
    }
  }
})();

resetBtn.addEventListener('click', () => {
  const form = document.getElementById('quizForm');
  form.reset();
  bookSel.innerHTML = '<option value="">Select a book</option>';
  bookSel.disabled = true;
  clearChapters();
});

document.getElementById('quizForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const classText = classSel.options[classSel.selectedIndex].text.trim().toLowerCase();
    const bookText = bookSel.options[bookSel.selectedIndex].text.trim().toLowerCase().replace(/\s+/g, '-');
    const classMatch = classText.match(/^(\d+(st|nd|rd|th))/i);
    const classSlug = classMatch ? classMatch[0] : classText.replace(/\s+/g, '-');

    let chapterSlug = 'All-Chapter';
    if (selectedChapterIds.length > 0) {
        const selectedItems = chapterSelector.querySelectorAll('.chapter-item input:checked');
        const chapterNums = [];
        selectedItems.forEach(input => {
            const label = input.nextElementSibling.textContent.trim();
            const numMatch = label.match(/Chapter\s+(\d+)/i) || label.match(/^(\d+)/);
            if (numMatch) {
                chapterNums.push(numMatch[1]);
            }
        });
        
        if (chapterNums.length > 0) {
            chapterNums.sort((a, b) => a - b);
            chapterSlug = chapterNums.join('-');
        }
    }

    const seoUrl = `${classSlug}/${bookText}/${chapterSlug}-MCQs-quiz`;
    this.action = seoUrl;
    this.submit();

    if (typeof showAILoader === 'function') {
        showAILoader(
            [
                { label: 'Analyzing syllabus', duration: 3500 },
                { label: 'Selecting MDCAT/ECAT questions', duration: 3500 },
                { label: 'Loading content', duration: 3500 },
                { label: 'Applying difficulty', duration: 3500 },
                { label: 'Starting quiz', duration: 3500 }
            ],
            'Preparing your personalized intermediate quiz session...',
            'Preparing Your Quiz',
            null
        );
    }
});
</script>
</body>
</html>
