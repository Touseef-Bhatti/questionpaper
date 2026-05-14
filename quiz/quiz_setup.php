<?php
session_start();
// quiz_setup.php - Public quiz setup page
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
    <title>Online MCQs Test For 9th and 10th Board Exams - Ahmad Learning Hub</title>
    <!-- Enhanced SEO Meta Tags -->
    <meta name="description" content="Chapter Wise MCQs for all books , Online MCQs test for Class 9 and 10 Board Exams.Practice Your Exam Preparation With Fast and Easy Online Tool - Ahmad Learning Hub">


    <meta name="keywords" content="AI MCQs, AI quiz generator, new syllabus MCQs 2026, board exam preparation, online MCQs practice, Matric MCQs, FSc MCQs, Biology MCQs, Chemistry MCQs, Physics MCQs, All board MCQs, Ahmad Learning Hub, automatic test generator">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ahmadlearninghub.com.pk/class-9-and-10-online-mcqs-prepation-test">
    <meta property="og:title" content="Online MCQs Test For 9th and 10th Board Exams - Ahmad Learning Hub">
    <meta property="og:description" content="Generate 100% accurate MCQs based on the latest 2026 syllabus using advanced AI. All subjects covered - science and arts.">
    <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og.jpg">

    <!-- JSON-LD Structured Data for SEO Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalApplication",
      "name": "Ahmad Learning Hub Quiz Builder",
      "description": "An AI-powered application to generate custom MCQs for students preparing for board exams.",
      "applicationCategory": "Education",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      },
      "educationalAlignment": [
        {
          "@type": "AlignmentObject",
          "educationalFramework": "National Curriculum",
          "targetName": "Matric / FSc / MDCAT",
          "alignmentType": "teaches"
        }
      ]
    }
    </script>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://ahmadlearninghub.com.pk/class-9-and-10-online-mcqs-prepation-test">
    <meta property="twitter:title" content="Online MCQs Test For 9th and 10th Board Exams - Ahmad Learning Hub">
    <meta property="twitter:description" content="Tailor your study sessions with our advanced MCQ generator. Practice by class, book, or specific chapters.">
    <meta property="twitter:image" content="https://paper.bhattichemicalsindustry.com.pk/assets/images/quiz-og.jpg">

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS -->

<div class="main-content">
    <div class="quiz-setup-container">
        <!-- TOP AD BANNER MOVED HERE FROM HEADER -->

        <header class="setup-header">
            <h1>Online MCQs Test For 9th and 10th Board Exams</h1>
            <p class="desc">Ahmad Learning Hub provides a personalized learning experience. Select your Class below to generate a focused MCQ practice session tailored to your syllabus.</p>
        </header>

        <form id="quizForm" method="POST" action="quiz.php">
            <!-- SELECTION TOP AD -->
            <br>
            <div class="grid">
                <div>
                    <label for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id" required>
                        <option value="">Select a class</option>
                        <?php
                        // Load classes 9 and 10 specifically
                        $cls = $conn->query("SELECT class_id, class_name FROM class WHERE class_id IN (9, 10) ORDER BY class_id ASC");
                        if ($cls) {
                            while ($row = $cls->fetch_assoc()) {
                                echo '<option value="' . (int)$row['class_id'] . '">' . htmlspecialchars($row['class_name']) . '</option>';
                            }
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

            <!-- MIDDLE AD BANNER -->
            <br>
        </form>
    </div>

    <!-- SEO Article Section -->
<!-- SEO Article Section - Comprehensive Blog Style -->
<article class="seo-article-section blog-layout">
    <div class="blog-container">
        <header class="blog-header">
            <h1 class="blog-title">The Ultimate Guide to 9th and 10th Class Online MCQs Test & Board Exam Preparation 2026</h1>
            <div class="blog-meta">
                <span class="category">Board Exams 2026</span>
                <span class="read-time">12 min read</span>
            </div>
        </header>

        <section class="blog-content">
            <p class="lead">
                In the current educational landscape of Pakistan, the transition toward <strong>SLO-based (Student Learning Outcomes)</strong> examinations has made <strong>online MCQs tests</strong> a critical component of every student's <strong>exam preparation</strong> strategy. Whether you are in Matric Part 1 or Part 2, mastering objective questions is the quickest way to secure a top position in your board results.
            </p>

            <div class="blog-featured-box">
                <h4>At a Glance: What We Cover</h4>
                <ul>
                    <li><strong>Chapter-wise MCQs</strong> for all Science and Arts subjects.</li>
                    <li>Latest 2026 patterns for <strong>Punjab Board, Federal Board (FBISE)</strong>, and others.</li>
                    <li>Pro tips for <strong>9th Class Physics</strong> and <strong>10th Class Chemistry</strong>.</li>
                    <li>Free <strong>online test</strong> sessions with instant grading.</li>
                </ul>
            </div>

            <h2>Mastering 9th Class All Subjects MCQs</h2>
            <p>
                The 9th class is the foundation of your professional career. High scores in the <strong>Matric board exams</strong> are essential for securing admission to top colleges. Our platform provides a comprehensive <strong>online mcqs test</strong> experience for all major books.
            </p>

            <h3>9th Class Physics MCQs: Numericals and Concepts</h3>
            <p>
                Physics requires a deep understanding of concepts rather than rote learning. Focus on <em>Unit 1: Physical Quantities</em> for SI units and <em>Unit 3: Dynamics</em> for laws of motion. Our <strong>Class 9 Physics MCQs</strong> include both theoretical questions and numerical-based objective problems that are frequently repeated in board papers.
            </p>

            <h3>9th Class Chemistry & Biology Preparation</h3>
            <p>
                For Chemistry, pay close attention to the Periodic Table and chemical bonding. In Biology, focus on the structural diagrams and biological terms in chapters like <em>Cell Biology</em> and <em>Bioenergetics</em>. Practicing <strong>chapter-wise MCQs</strong> helps you retain complex scientific terminology.
            </p>

            <h2>10th Class Board Exam MCQs: The Final Sprint</h2>
            <p>
                The 10th class board exams determine your future path (FSc Pre-Medical, Pre-Engineering, or ICS). Therefore, your <strong>exam preparation</strong> must be flawless.
            </p>

            <h3>10th Class Mathematics & Computer Science</h3>
            <p>
                Math MCQs often involve quick formulas from <em>Algebra</em> and <em>Geometry</em>. For Computer Science, focus on C++ basics and logic gates. Our <strong>online test</strong> system simulates the real exam environment, helping you manage your time effectively.
            </p>

            <h3>10th Class Physics: Optics and Electricity</h3>
            <p>
                In the 10th class, Physics becomes more technical. <strong>Online test preparation</strong> should prioritize <em>Unit 12: Geometrical Optics</em> and <em>Unit 14: Current Electricity</em>. These chapters are heavy on MCQs related to lens formulas, circuit diagrams, and Ohm’s Law. Understanding the behavior of light and the flow of electrons is key to scoring 12/12 in the objective portion.
            </p>

            <h3>10th Class Chemistry: Organic and Biochemistry</h3>
            <p>
                Organic Chemistry is the backbone of the 10th-class curriculum. Mastering functional groups and hydrocarbon structures is essential for your <strong>exam preparation</strong>. Our <strong>online mcqs test</strong> provides targeted questions from <em>Unit 11: Organic Chemistry</em> and <em>Unit 13: Biochemistry</em>, helping you memorize complex chemical reactions and biological processes with ease.
            </p>

            <div class="blog-quote">
                "Consistency is the key to mastering the objective portion of the board exams. A 15-minute daily <strong>online mcqs test</strong> can improve your memory retention by 60%."
            </div>

            <h2>Why Choose Online MCQs Tests Over Traditional Notes?</h2>
            <p>
                While traditional "Key Books" are helpful, they lack interactivity. Our <strong>online test preparation</strong> platform offers:
            </p>
            <ul>
                <li><strong>Interactive Feedback:</strong> Know why an answer is wrong immediately.</li>
                <li><strong>Randomized Questions:</strong> Every session is unique, preventing memory-based cheating.</li>
                <li><strong>Mobile Friendly:</strong> Prepare on the go, whether you are at home or traveling.</li>
            </ul>

            <h3>Top Tips for High-Score Exam Preparation</h3>
            <ol>
                <li><strong>Read the Textbook First:</strong> MCQs are often picked from "Do You Know?" boxes and summaries.</li>
                <li><strong>Analyze Past Papers:</strong> Identify the <strong>most repeated MCQs</strong> from the last 5 years.</li>
                <li><strong>Simulate Exam Conditions:</strong> Set a timer when taking our <strong>online mcqs test</strong> to build speed.</li>
            </ol>

            <div class="blog-cta-box">
                <h3>Start Your Free Online Test Now!</h3>
                <p>Don't wait until the last month. Select your Class and Subject from the menu above and begin your journey toward 100% marks in the objective section today!</p>
            </div>
        </section>
    </div>
</article>

    <!-- BOTTOM AD BANNER -->
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
let progressInterval;

function startLoaderProgress() {
    const progressBar = document.getElementById('loaderProgressBar');
    if (!progressBar) return;
    
    // Reset
    progressBar.style.width = '0%';
    clearInterval(progressInterval);
    
    let width = 0;
    progressInterval = setInterval(() => {
        if (width >= 90) {
            // Slow down significantly after 90%
            if (width < 95) width += 0.1;
        } else {
            // Fast initially, then slower
            const increment = Math.max(0.5, (90 - width) / 20);
            width += increment;
        }
        progressBar.style.width = width + '%';
    }, 100);
}

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
    
    // Create checkbox list for chapters
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

// Event listeners
classSel.addEventListener('change', loadBooks);
bookSel.addEventListener('change', loadChapters);

// Pre-fill form from URL parameters
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
      
      // If a specific chapter is provided, select it
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

// Reset button
resetBtn.addEventListener('click', () => {
  const form = document.getElementById('quizForm');
  form.reset();
  bookSel.innerHTML = '<option value="">Select a book</option>';
  bookSel.disabled = true;
  clearChapters();
});

// Show shared AI loader and redirect to SEO URL on form submit
document.getElementById('quizForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const classText = classSel.options[classSel.selectedIndex].text.trim().toLowerCase();
    const bookText = bookSel.options[bookSel.selectedIndex].text.trim().toLowerCase().replace(/\s+/g, '-');
    const mcqCount = document.getElementById('mcq_count').value;

    // Extract class number part (e.g. "9th Class" -> "9th")
    const classMatch = classText.match(/^(\d+(st|nd|rd|th))/i);
    const classSlug = classMatch ? classMatch[0] : classText.replace(/\s+/g, '-');

    // Handle chapters
    let chapterSlug = 'All-Chapter';
    if (selectedChapterIds.length > 0) {
        // We need to find the chapter numbers for selected IDs
        const selectedItems = chapterSelector.querySelectorAll('.chapter-item input:checked');
        const chapterNums = [];
        selectedItems.forEach(input => {
            const label = input.nextElementSibling.textContent.trim();
            // Assuming label starts with "Chapter X" or has the number
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

    // ── Submit the form FIRST — page starts loading immediately ─────────────
    this.submit();

    // ── Loader is purely cosmetic — steps animate, zero backend link ─
    if (typeof showAILoader === 'function') {
        showAILoader(
            [
                { label: 'Selecting questions', duration: 3500 },
                { label: 'Loading content', duration: 3500 },
                { label: 'Applying difficulty', duration: 3500 },
                { label: 'Arranging paper', duration: 3500 },
                { label: 'Starting quiz', duration: 3500 }
            ],
            'Preparing your personalized quiz session...',
            'Preparing Your Quiz',
            null // purely cosmetic — no callback
        );
    }
});
</script>
</body>
</html>

