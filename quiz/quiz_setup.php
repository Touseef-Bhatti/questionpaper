<?php
session_start();
// quiz_setup.php - Public quiz setup page
include_once '../db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take a Free Online Quiz - AI MCQ Generator - Ahmad Learning Hub</title>
    <!-- Enhanced SEO Meta Tags -->
    <meta name="description" content="Free AI-powered MCQ generator for board exams. Practice with thousands of questions according to the new 2026 syllabus. Perfect for Matric, FSc, MDCAT & ECAT prep. Generate custom tests by class, book, or AI topic search.">
    <meta name="keywords" content="AI MCQs, AI quiz generator, new syllabus MCQs 2026, board exam preparation, online MCQs practice, Matric MCQs, FSc MCQs, Biology MCQs, Chemistry MCQs, Physics MCQs, All board MCQs, Ahmad Learning Hub, automatic test generator">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://paper.bhattichemicalsindustry.com.pk/quiz/quiz_setup.php">
    <meta property="og:title" content="Free AI MCQ Generator for Board Exams | Ahmad Learning Hub">
    <meta property="og:description" content="Generate 100% accurate MCQs based on the latest 2026 syllabus using advanced AI. All subjects covered - science and arts.">
    <meta property="og:image" content="https://paper.bhattichemicalsindustry.com.pk/assets/images/quiz-og.jpg">

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
    <meta property="twitter:url" content="https://paper.bhattichemicalsindustry.com.pk/quiz/quiz_setup.php">
    <meta property="twitter:title" content="Take a Free Online Quiz - Ahmad Learning Hub">
    <meta property="twitter:description" content="Tailor your study sessions with our advanced MCQ generator. Practice by class, book, or specific chapters.">
    <meta property="twitter:image" content="https://paper.bhattichemicalsindustry.com.pk/assets/images/quiz-og.jpg">

    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/main.css">
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/quiz_setup.css">
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/ai_loader.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="<?= ($assetBase ?? '') ?>js/ai_loader.js" defer></script>
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS -->
<?= renderAd('skyscraper', 'Place Right Skyscraper Banner Here', 'right', 'margin-top: 10%;') ?>

<div class="main-content">
    <div class="quiz-setup-container">
        <!-- TOP AD BANNER MOVED HERE FROM HEADER -->
        <?= renderAd('banner', 'Place Top Banner Here', 'ad-placement-top') ?>

        <header class="setup-header">
            <h1>Master Your Exams with Custom Quizzes</h1>
            <p class="desc">Ahmad Learning Hub provides a personalized learning experience. Select your current academic level below to generate a focused MCQ practice session tailored to your syllabus.</p>
        </header>

        <form id="quizForm" method="POST" action="quiz.php">
            <!-- SELECTION TOP AD -->
            <?= renderAd('banner', 'Selection Top Banner') ?>
            <br>
            <div class="grid">
                <div>
                    <label for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id" required>
                        <option value="">Select a class</option>
                        <?php
                        // Load classes
                        $cls = $conn->query("SELECT class_id, class_name FROM class ORDER BY class_id ASC");
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
                        <button type="button" class="btn topic-btn" onclick="window.location.href='mcqs_topic'">Topic</button>
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
            <?= renderAd('banner', 'Place Middle Banner Here') ?>
        </form>
    </div>

    <!-- SEO Article Section -->
    <article class="seo-article-section">
        <div class="seo-grid">
            <div class="seo-card">
                <div class="seo-icon">🤖</div>
                <h3 class="seo-card-title">AI-Powered MCQs</h3>
                <p class="seo-card-text">Using advanced AI, we generate high-quality questions for any topic. Our AI matches the difficulty and style of modern board exams, ensuring you're ready for anything.</p>
            </div>
            
            <div class="seo-card">
                <div class="seo-icon">🆕</div>
                <h3 class="seo-card-title">New 2026 Syllabus</h3>
                <p class="seo-card-text">Our database is updated daily to follow the latest Board Exam New Syllabus and paper patterns. Practice with confidence knowing you're studying the right material.</p>
            </div>
            
            <div class="seo-card">
                <div class="seo-icon">🌍</div>
                <h3 class="seo-card-title">All Boards Coverage</h3>
                <p class="seo-card-text">From Punjab Board to Federal and Sindh Boards, we provide MCQs for all standard educational curricula, including MDCAT, ECAT, and GRE foundations.</p>
            </div>
            <div class="seo-card">
                <div class="seo-icon">📈</div>
                <h3 class="seo-card-title">Success Analytics</h3>
                <p class="seo-card-text">Simulate real exam environments and track your performance. Regular testing on our platform is proven to increase retention and boost exam confidence.</p>
            </div>
        </div>

        </div>
    </article>

    <!-- BOTTOM AD BANNER -->
    <br>
    <?= renderAd('banner', 'Place Bottom Banner Here') ?>
</div>

<!-- AI Loader Overlay -->
<div class="ai-loader-overlay" id="aiLoaderModal">
    <div class="ai-loader-card">
        <div class="ai-icon-container">
            <div class="ai-icon-glow"></div>
            <i class="fas fa-graduation-cap" style="color: white; z-index: 2; position: relative;"></i>
        </div>
        <h2 class="ai-loader-title">Preparing Your Quiz</h2>

        <div class="ai-steps-list">
            <div class="ai-step" id="step-1">
                <div class="ai-step-icon" id="icon-1"><i class="fas fa-circle-notch"></i></div>
                <div class="ai-step-text">Selecting questions</div>
            </div>
            <div class="ai-step" id="step-2">
                <div class="ai-step-icon" id="icon-2"><i class="fas fa-circle-notch"></i></div>
                <div class="ai-step-text">Loading content</div>
            </div>
            <div class="ai-step" id="step-3">
                <div class="ai-step-icon" id="icon-3"><i class="fas fa-circle-notch"></i></div>
                <div class="ai-step-text">Applying difficulty settings</div>
            </div>
            <div class="ai-step" id="step-4">
                <div class="ai-step-icon" id="icon-4"><i class="fas fa-circle-notch"></i></div>
                <div class="ai-step-text">Arranging paper</div>
            </div>
            <div class="ai-step" id="step-5">
                <div class="ai-step-icon" id="icon-5"><i class="fas fa-circle-notch"></i></div>
                <div class="ai-step-text">Starting quiz</div>
            </div>
        </div>

        <div class="ai-progress-container">
            <div class="ai-progress-bar" id="aiProgressBar"></div>
        </div>

        <div class="ai-loader-note">
            <i class="fas fa-info-circle"></i> Preparing your personalized quiz session...
        </div>
    </div>
</div>

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

// Show AI loader on form submit (timestamp-based, mobile-reliable)
document.getElementById('quizForm').addEventListener('submit', function() {
    const modal = document.getElementById('aiLoaderModal');
    const progressBar = document.getElementById('aiProgressBar');

    document.body.style.overflow = 'hidden';
    modal.style.display = 'flex';

    const steps = [
        { id: 1, duration: 2500 },
        { id: 2, duration: 2500 },
        { id: 3, duration: 2500 },
        { id: 4, duration: 2500 },
        { id: 5, duration: 2500 }
    ];

    const totalDuration = steps.reduce(function(acc, s) { return acc + s.duration; }, 0);

    steps.forEach(function(step) {
        const stepEl = document.getElementById('step-' + step.id);
        const iconEl = document.getElementById('icon-' + step.id);
        if (stepEl) {
            stepEl.classList.remove('active', 'completed');
            iconEl.innerHTML = '<i class="fas fa-circle-notch"></i>';
        }
    });

    const startTime = Date.now();
    let lastStepIndex = -1;

    const loaderInterval = setInterval(function() {
        const elapsed = Date.now() - startTime;

        let progress = Math.min((elapsed / totalDuration) * 100, 99);
        if (progressBar) progressBar.style.width = progress + '%';

        let cumulativeTime = 0;
        let activeStepIndex = steps.length - 1;
        for (let i = 0; i < steps.length; i++) {
            cumulativeTime += steps[i].duration;
            if (elapsed < cumulativeTime) {
                activeStepIndex = i;
                break;
            }
        }

        if (activeStepIndex !== lastStepIndex) {
            lastStepIndex = activeStepIndex;
            steps.forEach(function(step, idx) {
                const stepEl = document.getElementById('step-' + step.id);
                const iconEl = document.getElementById('icon-' + step.id);
                if (!stepEl) return;
                if (idx < activeStepIndex) {
                    stepEl.classList.add('completed');
                    stepEl.classList.remove('active');
                    iconEl.innerHTML = '<i class="fas fa-check"></i>';
                } else if (idx === activeStepIndex) {
                    stepEl.classList.add('active');
                    stepEl.classList.remove('completed');
                    iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                } else {
                    stepEl.classList.remove('active', 'completed');
                    iconEl.innerHTML = '<i class="fas fa-circle-notch"></i>';
                }
            });
        }

        if (elapsed >= totalDuration) {
            clearInterval(loaderInterval);
            steps.forEach(function(step) {
                const stepEl = document.getElementById('step-' + step.id);
                const iconEl = document.getElementById('icon-' + step.id);
                if (stepEl) {
                    stepEl.classList.add('completed');
                    stepEl.classList.remove('active');
                    iconEl.innerHTML = '<i class="fas fa-check"></i>';
                }
            });
            if (progressBar) progressBar.style.width = '99%';
        }
    }, 250);
});
</script>
</body>
</html>

