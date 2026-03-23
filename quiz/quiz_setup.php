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
                    <div id="class_dropdown_container"></div>
                    <select class="select" id="class_id" name="class_id" style="display:none;" required>
                        <option value="">Select a class</option>
                        <?php
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
                        <div id="book_dropdown_container" style="flex:1;"></div>
                        <select id="book_id" name="book_id" style="display:none;" required disabled>
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
let selectedChapterIds = [];

// ─── Pro-Selection Engine ─────────────────────────────────────────

const SelectionSystem = {
    cache: {
        books: {},    // {class_id: [data]}
        chapters: {}  // {book_id: [data]}
    },
    
    dropdowns: {}, // Instances

    init() {
        this.dropdowns.class = this.createCustomDropdown('class_dropdown_container', 'class_id', 'Select a class');
        this.dropdowns.book = this.createCustomDropdown('book_dropdown_container', 'book_id', 'Select a book', true);
        
        this.setupListeners();
        this.populateInitialClasses();
        this.handleUrlParams();
    },

    createCustomDropdown(containerId, originalSelectId, placeholder, disabled = false) {
        const container = document.getElementById(containerId);
        const originalSelect = document.getElementById(originalSelectId);
        
        container.innerHTML = `
            <div class="custom-dropdown ${disabled ? 'disabled' : ''}" id="custom_${originalSelectId}">
                <div class="dropdown-overlay"></div>
                <div class="dropdown-trigger">
                    <span class="trigger-label">${placeholder}</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu">
                    <div class="dropdown-search">
                        <input type="text" placeholder="Search...">
                    </div>
                    <div class="dropdown-options"></div>
                </div>
            </div>
        `;

        const dropdown = container.querySelector('.custom-dropdown');
        const trigger = dropdown.querySelector('.dropdown-trigger');
        const searchInput = dropdown.querySelector('.dropdown-search input');
        
        trigger.onclick = (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('disabled')) return;
            
            // Close other dropdowns
            Object.values(this.dropdowns).forEach(d => {
                if (d.element !== dropdown) d.element.classList.remove('active');
            });
            
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) searchInput.focus();
        };

        searchInput.onclick = (e) => e.stopPropagation();
        searchInput.oninput = (e) => this.filterOptions(dropdown, e.target.value);

        // Global click to close
        document.addEventListener('click', () => dropdown.classList.remove('active'));

        return {
            element: dropdown,
            originalSelect,
            placeholder,
            options: []
        };
    },

    populateInitialClasses() {
        const classDropdown = this.dropdowns.class;
        const options = Array.from(classDropdown.originalSelect.options)
            .filter(o => o.value !== '')
            .map(o => ({ id: o.value, name: o.textContent }));
        
        this.updateDropdownOptions('class', options);
    },

    updateDropdownOptions(key, options) {
        const dd = this.dropdowns[key];
        dd.options = options;
        const list = dd.element.querySelector('.dropdown-options');
        
        if (options.length === 0) {
            list.innerHTML = '<div class="dropdown-option no-results">No matches found</div>';
        } else {
            list.innerHTML = options.map(opt => `
                <div class="dropdown-option" data-id="${opt.id}" onclick="SelectionSystem.selectOption('${key}', '${opt.id}', '${opt.name}')">
                    ${opt.name}
                </div>
            `).join('');
        }
    },

    selectOption(key, id, name) {
        const dd = this.dropdowns[key];
        dd.element.querySelector('.trigger-label').textContent = name;
        dd.originalSelect.value = id;
        dd.element.classList.remove('active');
        
        // Trigger original change event
        dd.originalSelect.dispatchEvent(new Event('change'));
        
        // Style selected option
        const allOpts = dd.element.querySelectorAll('.dropdown-option');
        allOpts.forEach(o => {
            o.classList.toggle('selected', o.getAttribute('data-id') === id);
        });
    },

    filterOptions(dropdown, query) {
        const list = dropdown.querySelector('.dropdown-options');
        const options = Array.from(list.querySelectorAll('.dropdown-option:not(.no-results)'));
        let visibleCount = 0;

        options.forEach(opt => {
            const match = opt.textContent.toLowerCase().includes(query.toLowerCase());
            opt.style.display = match ? 'flex' : 'none';
            if (match) visibleCount++;
        });

        // Toggle no-results
        let noResults = list.querySelector('.no-results');
        if (visibleCount === 0) {
            if (!noResults) {
                const div = document.createElement('div');
                div.className = 'dropdown-option no-results';
                div.textContent = 'No matches found';
                list.appendChild(div);
            }
        } else if (noResults) {
            noResults.remove();
        }
    },

    showSkeleton(key) {
        const list = this.dropdowns[key].element.querySelector('.dropdown-options');
        list.innerHTML = `
            <div class="dropdown-skeleton">
                <div class="skeleton-item"></div>
                <div class="skeleton-item" style="width: 80%"></div>
                <div class="skeleton-item" style="width: 90%"></div>
            </div>
        `;
    },

    setupListeners() {
        this.dropdowns.class.originalSelect.addEventListener('change', async () => {
            const cid = this.dropdowns.class.originalSelect.value;
            this.resetBook();
            clearChapters();
            
            if (!cid) return;
            
            this.dropdowns.book.element.classList.remove('disabled');
            this.showSkeleton('book');
            
            const books = await this.getBooks(cid);
            this.updateDropdownOptions('book', books);
        });

        this.dropdowns.book.originalSelect.addEventListener('change', async () => {
            loadChapters();
        });
    },

    resetBook() {
        const dd = this.dropdowns.book;
        dd.element.querySelector('.trigger-label').textContent = dd.placeholder;
        dd.originalSelect.value = '';
        dd.element.classList.add('disabled');
        dd.element.classList.remove('active');
        this.updateDropdownOptions('book', []);
    },

    async getBooks(classId) {
        if (this.cache.books[classId]) return this.cache.books[classId];
        
        try {
            const res = await fetch(`quiz_data.php?type=books&class_id=${classId}`);
            const data = await res.json();
            const formatted = data.map(b => ({ id: b.book_id, name: b.book_name }));
            this.cache.books[classId] = formatted;
            return formatted;
        } catch (e) {
            console.error(e);
            return [];
        }
    },

    async handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const urlClassId = urlParams.get('class_id');
        const urlBookId = urlParams.get('book_id');
        const urlChapterId = urlParams.get('chapter_id');
        
        if (urlClassId) {
            const classMatches = this.dropdowns.class.options.find(o => o.id == urlClassId);
            if (classMatches) {
                this.selectOption('class', classMatches.id, classMatches.name);
                
                if (urlBookId) {
                    const checkBooks = setInterval(async () => {
                        const books = this.cache.books[urlClassId];
                        if (books) {
                            clearInterval(checkBooks);
                            const bookMatch = books.find(b => b.id == urlBookId);
                            if (bookMatch) {
                                this.selectOption('book', bookMatch.id, bookMatch.name);
                                
                                if (urlChapterId) {
                                    // Wait for chapters
                                    const checkChapters = setInterval(() => {
                                        const checkbox = document.getElementById(`ch_${urlChapterId}`);
                                        if (checkbox) {
                                            clearInterval(checkChapters);
                                            checkbox.checked = true;
                                            handleChapterChange(parseInt(urlChapterId));
                                        }
                                    }, 100);
                                    // Safety timeout
                                    setTimeout(() => clearInterval(checkChapters), 5000);
                                }
                            }
                        }
                    }, 100);
                    setTimeout(() => clearInterval(checkBooks), 5000);
                }
            }
        }
    }
};

// Original Load Chapters (Keeping but optimizing)
async function loadChapters() {
  clearChapters();
  chapterSelector.innerHTML = '<div class="dropdown-skeleton" style="padding:40px"><div class="skeleton-item"></div><div class="skeleton-item" style="width:80%"></div></div>';
  
  const cid = document.getElementById('class_id').value; 
  const bid = document.getElementById('book_id').value;
  
  if (!cid || !bid) {
    chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
    return;
  }
  
  try {
    const res = await fetch(`quiz_data.php?type=chapters&class_id=${cid}&book_id=${bid}`);
    const data = await res.json();
    
    if (data.length === 0) {
      chapterSelector.innerHTML = '<div class="selector-hint">No chapters found for this book</div>';
      return;
    }
    
    // Optimized fragment-based rendering for zero-lag
    const fragment = document.createDocumentFragment();
    data.forEach(chapter => {
        const item = document.createElement('div');
        item.className = 'chapter-item';
        item.innerHTML = `
            <input type="checkbox" id="ch_${chapter.chapter_id}" value="${chapter.chapter_id}">
            <label for="ch_${chapter.chapter_id}">${chapter.chapter_name}</label>
        `;
        item.onclick = (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return;
            const cb = item.querySelector('input');
            cb.checked = !cb.checked;
            handleChapterChange(chapter.chapter_id);
        };
        item.querySelector('input').onclick = (e) => {
            e.stopPropagation();
            handleChapterChange(chapter.chapter_id);
        };
        item.querySelector('label').onclick = (e) => e.stopPropagation();
        fragment.appendChild(item);
    });
    
    chapterSelector.innerHTML = '';
    chapterSelector.appendChild(fragment);
  } catch (error) {
    chapterSelector.innerHTML = '<div class="selector-hint">Error loading chapters</div>';
  }
}

function handleChapterChange(chapterId) {
    const cb = document.getElementById(`ch_${chapterId}`);
    if (cb.checked) {
        if (!selectedChapterIds.includes(chapterId)) selectedChapterIds.push(chapterId);
    } else {
        selectedChapterIds = selectedChapterIds.filter(id => id !== chapterId);
    }
    updateChapterInput();
}

function updateChapterInput() {
    document.getElementById('chapter_ids').value = selectedChapterIds.join(',');
}

function clearChapters() {
    selectedChapterIds = [];
    document.getElementById('chapter_ids').value = '';
    chapterSelector.innerHTML = '<div class="selector-hint">Select a book first to see available chapters</div>';
}

// Reset button enhancement - fully reset everything
resetBtn.addEventListener('click', () => {
    // 1. Reset Selects
    document.getElementById('class_id').value = '';
    document.getElementById('book_id').value = '';
    document.getElementById('book_id').disabled = true;
    
    // 2. Clear Chapters
    clearChapters();
    
    // 3. Reset Dropdown UI
    const classDD = SelectionSystem.dropdowns.class;
    classDD.element.querySelector('.trigger-label').textContent = classDD.placeholder;
    classDD.element.querySelectorAll('.dropdown-option').forEach(o => o.classList.remove('selected'));
    
    SelectionSystem.resetBook();
});

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    SelectionSystem.init();
});

// AI Loader Logic (Maintained from original)
document.getElementById('quizForm').addEventListener('submit', function() {
    const modal = document.getElementById('aiLoaderModal');
    const progressBar = document.getElementById('aiProgressBar');

    document.body.style.overflow = 'hidden';
    modal.style.display = 'flex';

    const steps = [
        { id: 1, duration: 2000 },
        { id: 2, duration: 2000 },
        { id: 3, duration: 2000 },
        { id: 4, duration: 2000 },
        { id: 5, duration: 1500 }
    ];

    const totalDuration = steps.reduce((acc, s) => acc + s.duration, 0);
    const startTime = Date.now();

    const loaderInterval = setInterval(() => {
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

        steps.forEach((step, idx) => {
            const stepEl = document.getElementById('step-' + step.id);
            const iconEl = document.getElementById('icon-' + step.id);
            if (!stepEl) return;
            if (idx < activeStepIndex) {
                stepEl.classList.add('completed');
                iconEl.innerHTML = '<i class="fas fa-check"></i>';
            } else if (idx === activeStepIndex) {
                stepEl.classList.add('active');
                iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
        });

        if (elapsed >= totalDuration) clearInterval(loaderInterval);
    }, 100);
});
</script>
</body>
</html>

