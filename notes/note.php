<?php
include '../db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title>Free Study Materials for Class 9 & 10 Punjab Board – Notes, Textbooks, MCQs | Ahmad Learning Hub</title>
    <meta name="description" content="Access free study materials for Punjab Board class 9 and 10. Download notes, read digital textbooks, and practice chapter-wise MCQs for Physics, Chemistry, Biology, Math, and Computer Science. Aligned with PCTB curriculum 2024-2025.">
    <meta name="keywords" content="Punjab Board notes, class 9 notes, class 10 notes, 9th class study material, 10th class study material, PCTB notes, Punjab Board textbooks, free notes class 9, free notes class 10, MCQs class 9, MCQs class 10, Physics notes 9th, Chemistry notes 10th, Biology notes, Math notes Punjab Board, Computer Science notes, board exam preparation, Ahmad Learning Hub">
    <meta name="author" content="Ahmad Learning Hub">

    <!-- Open Graph -->
    <meta property="og:title" content="Free Study Materials – Class 9 & 10 Punjab Board | Ahmad Learning Hub">
    <meta property="og:description" content="Download free notes, read textbooks online, and practice MCQs for all Punjab Board class 9 and 10 subjects.">
    <meta property="og:type" content="website">

    <!-- Canonical -->
    <link rel="canonical" href="https://ahmadlearninghub.com/note">

    <link rel="stylesheet" href="<?= $assetBase ?>css/main.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/notes.css">
    <link rel="stylesheet" href="<?= $assetBase ?>css/buttons.css">

    <!-- FAQ Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "Where can I find free notes for class 9 Punjab Board?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Ahmad Learning Hub provides free, comprehensive notes for all class 9 Punjab Board subjects including Physics, Chemistry, Biology, Mathematics, and Computer Science. All notes follow the official PCTB curriculum."
                }
            },
            {
                "@type": "Question",
                "name": "Are Punjab Board class 10 textbooks available online?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. You can read official Punjab Board class 10 textbooks online for free on Ahmad Learning Hub. The digital textbook reader includes zoom, dark mode, and bookmarking features."
                }
            },
            {
                "@type": "Question",
                "name": "How can I practice MCQs for class 9 and 10 board exams?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Ahmad Learning Hub offers chapter-wise MCQs with instant scoring for all Punjab Board class 9 and 10 subjects. You can filter by subject, chapter, and difficulty level."
                }
            }
        ]
    }
    </script>
</head>
<body>
    <?php include '../header.php'; ?>

    <!-- Hero Section -->
    <section class="notes-hero">
        <div class="notes-hero-badge"><i class="fas fa-graduation-cap"></i> Punjab Board – Class 9 & 10</div>
        <h1>Your Complete <span class="gradient-text">Study Hub</span></h1>
        <p>Access free notes, digital textbooks, and chapter-wise MCQs for all Punjab Board class 9 and 10 subjects. Everything you need to ace your board exams.</p>
        <div class="notes-stats">
            <div class="notes-stat">
                <div class="notes-stat-value">10+</div>
                <div class="notes-stat-label">Subjects</div>
            </div>
            <div class="notes-stat">
                <div class="notes-stat-value">1000+</div>
                <div class="notes-stat-label">MCQs</div>
            </div>
            <div class="notes-stat">
                <div class="notes-stat-value">Free</div>
                <div class="notes-stat-label">Forever</div>
            </div>
        </div>
    </section>

    <div class="study-materials-container">

        <!-- Quick Class Links -->
        <h2 class="materials-section-title"><i class="fas fa-bolt"></i> Quick Access</h2>
        <div class="quick-links">
            <a href="<?= $assetBase ?>select_book.php?class_id=9" class="quick-link bypass-user-type"><i class="fas fa-book-open"></i> Class 9 – All Subjects</a>
            <a href="<?= $assetBase ?>select_book.php?class_id=10" class="quick-link bypass-user-type"><i class="fas fa-book-open"></i> Class 10 – All Subjects</a>
            <a href="<?= $assetBase ?>quiz_setup" class="quick-link bypass-user-type"><i class="fas fa-clipboard-check"></i> MCQs Practice – 9th & 10th</a>
            <a href="<?= $assetBase ?>topic-wise-mcqs-test" class="quick-link bypass-user-type"><i class="fas fa-brain"></i> Topic-Wise MCQs Test</a>
        </div>

        <!-- Materials Grid -->
        <h2 class="materials-section-title"><i class="fas fa-folder-open"></i> Study Resources</h2>
        <div class="materials-grid">

            <div class="material-card" onclick="navigateToMaterial('textbook')">
                <div class="material-icon textbook"><i class="fas fa-book"></i></div>
                <div class="material-body">
                    <div class="material-title">Digital Textbooks</div>
                    <div class="material-description">Read official Punjab Board textbooks online with zoom, dark mode, and bookmarking tools.</div>
                    <span class="material-tag free">Free Access</span>
                </div>
                <i class="fas fa-chevron-right material-arrow"></i>
            </div>

            <div class="material-card" onclick="navigateToMaterial('notes')">
                <div class="material-icon notes"><i class="fas fa-file-alt"></i></div>
                <div class="material-body">
                    <div class="material-title">Chapter-Wise Notes</div>
                    <div class="material-description">Download comprehensive notes and study guides for class 9 and 10 all subjects.</div>
                    <span class="material-tag popular">Popular</span>
                </div>
                <i class="fas fa-chevron-right material-arrow"></i>
            </div>

            <div class="material-card" onclick="navigateToMaterial('mcqs')">
                <div class="material-icon mcqs"><i class="fas fa-check-circle"></i></div>
                <div class="material-body">
                    <div class="material-title">MCQs Practice</div>
                    <div class="material-description">Practice chapter-wise multiple choice questions with instant scoring and answers.</div>
                    <span class="material-tag free">Free Access</span>
                </div>
                <i class="fas fa-chevron-right material-arrow"></i>
            </div>

        </div>

        <!-- SEO Content -->
        <div class="seo-section">
            <h2>Free Study Materials for Punjab Board Class 9 & 10 Students</h2>
            <p>
                Welcome to Ahmad Learning Hub's study materials hub – your one-stop destination for everything you need to prepare for Punjab Board exams.
                Whether you are in <strong>class 9</strong> or <strong>class 10</strong>, our platform provides free, high-quality resources aligned with the
                <strong>Punjab Curriculum and Textbook Board (PCTB)</strong> syllabus for the academic year 2024–2025.
            </p>

            <h3>Available Subjects</h3>
            <ul>
                <li><strong>Physics</strong> – Detailed notes, solved numericals, and MCQs for class 9 and 10</li>
                <li><strong>Chemistry</strong> – Chapter-wise notes with reactions, diagrams, and practice questions</li>
                <li><strong>Biology</strong> – Illustrated notes covering all PCTB Biology chapters</li>
                <li><strong>Mathematics</strong> – Step-by-step solved exercises, theorems, and formula sheets</li>
                <li><strong>Computer Science</strong> – Theory notes, programming concepts, and objective questions</li>
            </ul>

            <h3>Why Students Choose Ahmad Learning Hub</h3>
            <ul>
                <li>100% free – no sign-up required for notes and textbooks</li>
                <li>Aligned with the latest Punjab Board curriculum</li>
                <li>Chapter-wise MCQs with instant results</li>
                <li>Mobile-friendly – study on any device</li>
                <li>Updated regularly with new content</li>
            </ul>

            <!-- FAQ -->
            <div class="faq-section">
                <h3>Frequently Asked Questions</h3>
                <div class="faq-item">
                    <div class="faq-q">Where can I find free notes for class 9 Punjab Board?</div>
                    <div class="faq-a">Ahmad Learning Hub provides free, comprehensive notes for all class 9 Punjab Board subjects including Physics, Chemistry, Biology, Mathematics, and Computer Science. All notes follow the official PCTB curriculum.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-q">Are Punjab Board class 10 textbooks available online?</div>
                    <div class="faq-a">Yes. You can read official Punjab Board class 10 textbooks online for free on Ahmad Learning Hub. The digital textbook reader includes zoom, dark mode, and bookmarking features.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-q">How can I practice MCQs for class 9 and 10 board exams?</div>
                    <div class="faq-a">Ahmad Learning Hub offers chapter-wise MCQs with instant scoring for all Punjab Board class 9 and 10 subjects. You can filter by subject, chapter, and difficulty level.</div>
                </div>
            </div>
        </div>

        <div class="go-back-section">
            <a href="../index.php" class="go-back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        function navigateToMaterial(type) {
            const routes = {
                'textbook': 'textbooks.php',
                'notes': 'uploaded_notes.php',
                'mcqs': 'mcqs'
            };
            const url = routes[type] || '#';
            if (url !== '#') {
                window.location.href = url;
            }
        }
    </script>
</body>
</html>
