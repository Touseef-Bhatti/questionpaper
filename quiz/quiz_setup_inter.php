<?php
session_start();
// quiz_setup_inter.php - Public quiz setup page for Class 11 & 12
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

        <form id="quizForm" method="POST" action="mcqs-quiz.php">
            <input type="hidden" id="class_id" name="class_id" value="">
            <input type="hidden" id="book_id" name="book_id" value="">
            <input type="hidden" id="book_name_hidden" name="book_name" value="">

            <!-- Step 1: Class Selection Cards -->
            <div class="section-title">
                <span class="icon" aria-hidden="true"></span> Select Your Class
            </div>
            <div class="class-cards" id="classCards">
                <?php
                $icons = [
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4H20v16H5.5A2.5 2.5 0 0 1 3 17.5v-11z"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l7 4-7 4-7-4 7-4z"/><path d="M5 10v7a2 2 0 0 0 2 2h10"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-2-2h-4l-2-2H9L7 6H5a2 2 0 0 0-2 2v8"/><path d="M7 13h10"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a7 7 0 0 0 0-6"/><path d="M4.6 9a7 7 0 0 0 0 6"/></svg>'
                ];
                $subs = ['Matric Part 1', 'Matric Part 2', 'FSc Part 1', 'FSc Part 2'];
                $cls = $conn->query("SELECT class_id, class_name FROM class WHERE class_id IN (11, 12) ORDER BY class_id ASC");
                $i = 0;
                if ($cls && $cls->num_rows > 0) {
                    while ($row = $cls->fetch_assoc()) {
                        $cid = (int)$row['class_id'];
                        $cname = htmlspecialchars($row['class_name']);
                        $icon = $icons[$i % 4];
                        $sub = ($i < count($subs)) ? $subs[$i % count($subs)] : '';
                        echo '<div class="class-card" tabindex="0" data-class-id="' . $cid . '" data-class-name="' . $cname . '">';
                        echo '  <div class="class-card-icon">' . $icon . '</div>';
                        echo '  <div class="class-card-info">';
                        echo '    <div class="class-card-name">' . $cname . '</div>';
                        echo '    <div class="class-card-sub">' . $sub . '</div>';
                        echo '  </div>';
                        echo '  <div class="class-card-check"></div>';
                        echo '</div>';
                        $i++;
                    }
                } else {
                    // Fallback for inter
                    $fallback = [
                        ['11', '11th Class', 'FSc Part 1', ''],
                        ['12', '12th Class', 'FSc Part 2', ''],
                    ];
                    foreach ($fallback as $fb) {
                        $fi = $i % 4;
                        $iconHtml = $icons[$fi];
                        echo '<div class="class-card" tabindex="0" data-class-id="' . $fb[0] . '" data-class-name="' . $fb[1] . '">';
                        echo '  <div class="class-card-icon">' . $iconHtml . '</div>';
                        echo '  <div class="class-card-info">';
                        echo '    <div class="class-card-name">' . $fb[1] . '</div>';
                        echo '    <div class="class-card-sub">' . $fb[2] . '</div>';
                        echo '  </div>';
                        echo '  <div class="class-card-check"></div>';
                        echo '</div>';
                        $i++;
                    }
                }
                ?>
            </div>

            <a href="topic-wise-mcqs-test" class="topic-link after-class">
                Or try Topic-Wise MCQs <span class="arrow">&rarr;</span>
            </a>

            <div class="setup-divider"></div>

            <!-- Step 2: Book Selection Cards -->
            <div class="book-section" id="bookSection">
                <div class="section-title">
                    <span class="icon" aria-hidden="true"></span> Select Your Book
                </div>
                <div class="book-cards-wrapper">
                    <div class="book-cards" id="bookCards">
                        <div class="book-empty-state">
                            <span class="empty-icon" aria-hidden="true"></span>
                            Pick a class above to see available books
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn secondary" id="resetBtn">Reset</button>
                <button type="submit" class="btn primary" id="startBtn" disabled>
                    Start Quiz <span>&rarr;</span>
                </button>
            </div>

            <!-- MIDDLE AD BANNER -->
        </form>
    </div>

    <?php include_once __DIR__ . '/../includes/quiz_ad_gate.php'; ?>

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
// ─── DOM References ───
const classInput = document.getElementById('class_id');
const bookInput = document.getElementById('book_id');
const bookNameInput = document.getElementById('book_name_hidden');
const classCards = document.querySelectorAll('.class-card');
const bookCardsContainer = document.getElementById('bookCards');
const bookSection = document.getElementById('bookSection');
const startBtn = document.getElementById('startBtn');
const resetBtn = document.getElementById('resetBtn');

// Step indicators (add if page has them)
const stepDot1 = document.getElementById('stepDot1');
const stepDot2 = document.getElementById('stepDot2');
const stepLine1 = document.getElementById('stepLine1');
const stepLabel1 = document.getElementById('stepLabel1');
const stepLabel2 = document.getElementById('stepLabel2');

// Choose an appropriate SVG icon and color based on book name
function chooseBookIcon(name) {
    const n = (name || '').toLowerCase();
    const icons = {
        science: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5"/><path d="M8 21V11"/><path d="M16 21V11"/><path d="M12 3v8"/><path d="M8 7h8"/></svg>`, color: '#0ea5a4' },
        chemistry: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M8 6h8l-2 6a4 4 0 1 1-8 0L8 6z"/></svg>`, color: '#f97316' },
        math: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14"/><path d="M16 5v14"/><path d="M3 12h18"/></svg>`, color: '#7c3aed' },
        computer: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8"/></svg>`, color: '#2563eb' },
        geography: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M2 12h4"/><path d="M18 12h4"/></svg>`, color: '#16a34a' },
        default: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4H20v16H5.5A2.5 2.5 0 0 1 3 17.5v-11z"/></svg>`, color: getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#4f46e5' }
    };

    if (n.match(/physics|science|biology|chemistry/)) return icons.science;
    if (n.match(/chemistry|organic|inorganic/)) return icons.chemistry;
    if (n.match(/math|mathematics|calculus|algebra/)) return icons.math;
    if (n.match(/computer|computer science|cs|programming|c\+\+|python/)) return icons.computer;
    if (n.match(/geograph|geography|earth|world/)) return icons.geography;
    return icons.default;
}

let progressInterval;
function startLoaderProgress() {
    const progressBar = document.getElementById('loaderProgressBar');
    if (!progressBar) return;
    progressBar.style.width = '0%';
    clearInterval(progressInterval);
    let width = 0;
    progressInterval = setInterval(() => {
        if (width >= 90) {
            if (width < 95) width += 0.1;
        } else {
            const increment = Math.max(0.5, (90 - width) / 20);
            width += increment;
        }
        progressBar.style.width = width + '%';
    }, 100);
}

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

// ─── Step Updates ───
function updateSteps(step) {
    if (step >= 1) {
        if (stepDot1) stepDot1.classList.add('active');
        if (stepLabel1) stepLabel1.classList.add('active');
    }
    if (step >= 2) {
        if (stepDot1) { stepDot1.classList.remove('active'); stepDot1.classList.add('completed'); stepDot1.textContent = ''; }
        if (stepLabel1) { stepLabel1.classList.remove('active'); stepLabel1.classList.add('completed'); }
        if (stepLine1) stepLine1.classList.add('active');
        if (stepDot2) stepDot2.classList.add('active');
        if (stepLabel2) stepLabel2.classList.add('active');
        if (bookSection) bookSection.classList.add('active');
    } else {
        if (stepDot1) { stepDot1.classList.remove('completed'); stepDot1.classList.add('active'); stepDot1.textContent = '1'; }
        if (stepLabel1) { stepLabel1.classList.remove('completed'); stepLabel1.classList.add('active'); }
        if (stepLine1) stepLine1.classList.remove('active');
        if (stepDot2) stepDot2.classList.remove('active');
        if (stepLabel2) stepLabel2.classList.remove('active');
        if (bookSection) bookSection.classList.remove('active');
    }
}

// ─── Class Card Click ───
classCards.forEach(card => {
    const activateClassCard = async () => {
        // Deselect all
        classCards.forEach(c => c.classList.remove('selected'));
        // Select this one
        card.classList.add('selected');
        const cid = card.getAttribute('data-class-id');
        classInput.value = cid;

        // Reset book selection
        bookInput.value = '';
        bookNameInput.value = '';
        startBtn.disabled = true;

        // Move to step 2
        updateSteps(2);

        // Load books
        await loadBooks(cid);
    };

    card.addEventListener('click', activateClassCard);
    card.addEventListener('keydown', async (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            await activateClassCard();
        }
    });
    
});

// ─── Load Books ───
async function loadBooks(classId) {
    // Show skeleton
    bookCardsContainer.innerHTML = `
        <div class="book-skeleton">
            <div class="book-skeleton-item"></div>
            <div class="book-skeleton-item"></div>
            <div class="book-skeleton-item"></div>
        </div>
    `;

    try {
        const res = await fetch('quiz_data.php?' + toQuery({ type: 'books', class_id: classId }));
        const data = await res.json();

        if (!data || data.length === 0) {
            bookCardsContainer.innerHTML = `
                <div class="book-empty-state">
                    <span class="empty-icon" aria-hidden="true"></span>
                    No books found for this class
                </div>
            `;
            return;
        }

        bookCardsContainer.innerHTML = data.map((b, idx) => `
            <div class="book-card" tabindex="0" data-book-id="${b.book_id}" data-book-name="${b.book_name}">
                <div class="book-card-emoji"></div>
                <div class="book-card-name">${b.book_name}</div>
                <div class="book-card-radio"></div>
            </div>
        `).join('');

        // Inject per-book icons and attach click handlers to book cards
        document.querySelectorAll('.book-card').forEach(bcard => {
            const bname = bcard.getAttribute('data-book-name') || '';
            const icon = chooseBookIcon(bname);
            const emojiEl = bcard.querySelector('.book-card-emoji');
            if (emojiEl) {
                emojiEl.innerHTML = icon.svg;
                emojiEl.style.color = icon.color;
            }
            const selectBookCard = () => {
                document.querySelectorAll('.book-card').forEach(bc => bc.classList.remove('selected'));
                bcard.classList.add('selected');
                bookInput.value = bcard.getAttribute('data-book-id');
                bookNameInput.value = bcard.getAttribute('data-book-name');
                startBtn.disabled = false;
            };

            bcard.addEventListener('click', selectBookCard);
            bcard.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    selectBookCard();
                }
            });
        });

    } catch (error) {
        bookCardsContainer.innerHTML = `
            <div class="book-empty-state">
                <span class="empty-icon" aria-hidden="true"></span>
                Error loading books. Please try again.
            </div>
        `;
        console.error('Error loading books:', error);
    }
}

// ─── Pre-fill from URL ───
(async function() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlClassId = urlParams.get('class_id');
    const urlBookId = urlParams.get('book_id');

    if (urlClassId) {
        const matchCard = document.querySelector(`.class-card[data-class-id="${urlClassId}"]`);
        if (matchCard) {
            matchCard.classList.add('selected');
            classInput.value = urlClassId;
            updateSteps(2);
            await loadBooks(urlClassId);

            if (urlBookId) {
                const matchBook = document.querySelector(`.book-card[data-book-id="${urlBookId}"]`);
                if (matchBook) {
                    matchBook.classList.add('selected');
                    bookInput.value = urlBookId;
                    bookNameInput.value = matchBook.getAttribute('data-book-name');
                    startBtn.disabled = false;
                }
            }
        }
    }
})();

// ─── Reset ───
resetBtn.addEventListener('click', () => {
    classCards.forEach(c => c.classList.remove('selected'));
    classInput.value = '';
    bookInput.value = '';
    bookNameInput.value = '';
    startBtn.disabled = true;
    updateSteps(1);
    bookCardsContainer.innerHTML = `
        <div class="book-empty-state">
            <span class="empty-icon" aria-hidden="true"></span>
            Pick a class above to see available books
        </div>
    `;
});

// ─── Form Submit ───
function createSlug(string) {
    let slug = string.toLowerCase();
    slug = slug.replace(/[\s_]+/g, '-');
    slug = slug.replace(/[^a-z0-9-]/g, '');
    return slug.replace(/^-+|-+$/g, '');
}

document.getElementById('quizForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const selectedClass = classInput.value;
    const bookName = bookNameInput.value;
    if (!selectedClass || !bookName) return;
    const bookSlug = createSlug(bookName);
    const seoUrl = `/class-${selectedClass}-${bookSlug}-mcqs`;
    window.location.href = seoUrl;
});
</script>
</body>
</html>
