<?php
include '../db_connect.php';

// Get filter parameters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$bookId = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$chapterId = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$viewMcqs = isset($_GET['view']) && $_GET['view'] == '1'; // Only show MCQs when view=1
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch classes for filter
$classesQuery = "SELECT class_id, class_name FROM class ORDER BY class_id ASC";
$classesResult = $conn->query($classesQuery);
$classes = [];
while ($row = $classesResult->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch books for selected class
$books = [];
if ($classId > 0) {
    $booksQuery = "SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_name ASC";
    $stmt = $conn->prepare($booksQuery);
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $booksResult = $stmt->get_result();
    while ($row = $booksResult->fetch_assoc()) {
        $books[] = $row;
    }
    $stmt->close();
}

// Fetch chapters for selected class and book
$chapters = [];
if ($classId > 0 && $bookId > 0) {
    $chaptersQuery = "SELECT chapter_id, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_id ASC";
    $stmt = $conn->prepare($chaptersQuery);
    $stmt->bind_param('ii', $classId, $bookId);
    $stmt->execute();
    $chaptersResult = $stmt->get_result();
    while ($row = $chaptersResult->fetch_assoc()) {
        $chapters[] = $row;
    }
    $stmt->close();
}

// Build query for MCQs
$whereConditions = [];
$params = [];
$types = '';

if ($classId > 0) {
    $whereConditions[] = "m.class_id = ?";
    $params[] = $classId;
    $types .= 'i';
}

if ($bookId > 0) {
    $whereConditions[] = "m.book_id = ?";
    $params[] = $bookId;
    $types .= 'i';
}

if ($chapterId > 0) {
    $whereConditions[] = "m.chapter_id = ?";
    $params[] = $chapterId;
    $types .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = "(m.question LIKE ? OR m.topic LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count (only if we need to show MCQs)
$totalMcqs = 0;
$totalPages = 0;
$mcqs = [];

if ($viewMcqs) {
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM mcqs m $whereClause";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalMcqs = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalMcqs / $perPage);
    $countStmt->close();

    // Fetch MCQs with pagination
    if ($totalMcqs > 0) {
    $query = "SELECT m.mcq_id, m.class_id, m.book_id, m.chapter_id, m.topic, m.question, 
                     m.option_a, m.option_b, m.option_c, m.option_d, m.correct_option,
                     c.class_name, b.book_name, ch.chapter_name
              FROM mcqs m
              LEFT JOIN class c ON c.class_id = m.class_id
              LEFT JOIN book b ON b.book_id = m.book_id
              LEFT JOIN chapter ch ON ch.chapter_id = m.chapter_id
              $whereClause
              ORDER BY m.mcq_id DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
        while ($row = $result->fetch_assoc()) {
            $mcqs[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <?php
    // Dynamic SEO content based on filters
    $pageTitle = "9th & 10th Class All MCQs | Ahmad Learning Hub";
    $pageDescription = "Practice MCQs for 9th and 10th class Punjab Board exams. Access thousands of multiple choice questions for Physics, Chemistry, Biology, Mathematics, and Computer Science with instant answers.";
    $pageKeywords = "MCQs, multiple choice questions, 9th class MCQs, 10th class MCQs, Punjab Board MCQs, physics MCQs, chemistry MCQs, biology MCQs, online practice, exam preparation";
    
    // Customize based on selected filters
    if ($viewMcqs && $classId > 0) {
        $className = '';
        foreach ($classes as $class) {
            if ($class['class_id'] == $classId) {
                $className = $class['class_name'];
                break;
            }
        }
        
        $bookName = '';
        if ($bookId > 0) {
            foreach ($books as $book) {
                if ($book['book_id'] == $bookId) {
                    $bookName = $book['book_name'];
                    break;
                }
            }
        }
        
        $chapterName = '';
        if ($chapterId > 0) {
            foreach ($chapters as $chapter) {
                if ($chapter['chapter_id'] == $chapterId) {
                    $chapterName = $chapter['chapter_name'];
                    break;
                }
            }
        }
        
        // Build dynamic title and description
        if (!empty($className)) {
            $pageTitle = "MCQs for " . htmlspecialchars($className);
            $pageDescription = "Practice multiple choice questions for " . htmlspecialchars($className) . " Punjab Board";
            $pageKeywords = htmlspecialchars($className) . " MCQs, " . $pageKeywords;
            
            if (!empty($bookName)) {
                $pageTitle .= " - " . htmlspecialchars($bookName);
                $pageDescription .= " - " . htmlspecialchars($bookName);
                $pageKeywords = htmlspecialchars($bookName) . " MCQs, " . $pageKeywords;
                
                if (!empty($chapterName)) {
                    $pageTitle .= " - " . htmlspecialchars($chapterName);
                    $pageDescription .= " - " . htmlspecialchars($chapterName);
                    $pageKeywords = htmlspecialchars($chapterName) . " MCQs, " . $pageKeywords;
                }
            }
            
            $pageTitle .= " | Ahmad Learning Hub";
            $pageDescription .= ". Free online MCQs practice with answers for exam preparation.";
        }
    }
    
    // Get current URL for canonical and Open Graph
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $currentUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $canonicalUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    ?>
    
    <!-- Primary Meta Tags -->
    <title><?= $pageTitle ?></title>
    <meta name="title" content="<?= $pageTitle ?>">
    <meta name="description" content="<?= $pageDescription ?>">
    <meta name="keywords" content="<?= $pageKeywords ?>">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="rating" content="General">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:site_name" content="Ahmad Learning Hub">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= $pageDescription ?>">
    
    <!-- Additional SEO Meta Tags -->
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    
    <!-- Structured Data (JSON-LD) for Rich Snippets -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "Ahmad Learning Hub",
        "description": "<?= addslashes($pageDescription) ?>",
        "url": "<?= htmlspecialchars($currentUrl) ?>",
        "logo": "<?= $protocol ?>://<?= $_SERVER['HTTP_HOST'] ?>/images/logo.png",
        "sameAs": [],
        "educationalLevel": "Secondary Education",
        "audience": {
            "@type": "EducationalAudience",
            "educationalRole": "student"
        }
    }
    </script>
    
    <?php if ($viewMcqs && count($mcqs) > 0): ?>
    <!-- Quiz Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Quiz",
        "name": "<?= addslashes($pageTitle) ?>",
        "description": "<?= addslashes($pageDescription) ?>",
        "educationalLevel": "<?= !empty($className) ? addslashes($className) : 'Secondary Education' ?>",
        "numberOfQuestions": <?= $totalMcqs ?>,
        "assesses": "<?= !empty($bookName) ? addslashes($bookName) : 'General Knowledge' ?>",
        "educationalUse": "Practice and Assessment",
        "learningResourceType": "Multiple Choice Questions",
        "inLanguage": "en",
        "isAccessibleForFree": true,
        "provider": {
            "@type": "EducationalOrganization",
            "name": "Ahmad Learning Hub"
        }
    }
    </script>
    
    <!-- BreadcrumbList Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "<?= $protocol ?>://<?= $_SERVER['HTTP_HOST'] ?>/"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "MCQs Practice",
                "item": "<?= htmlspecialchars($canonicalUrl) ?>"
            }
            <?php if (!empty($className)): ?>
            ,{
                "@type": "ListItem",
                "position": 3,
                "name": "<?= addslashes($className) ?>",
                "item": "<?= htmlspecialchars($currentUrl) ?>"
            }
            <?php endif; ?>
        ]
    }
    </script>
    <?php endif; ?>
    
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/notes.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/notes-mcqs.css">
    <style>
       
    </style>
</head>
<body>
    <?php include '../header.php'; ?>

    <div class="main-content">
        <div class="mcqs-container">
            <div class="mcqs-header">
                <h1>📝 MCQs Learning</h1>
                <p>Learn and practice multiple choice questions for Punjab Board 9th & 10th class exams</p>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="" id="filterForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="class_id">Class</label>
                            <select name="class_id" id="class_id" onchange="updateBooks()">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_id'] ?>" <?= $classId == $class['class_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="book_id">Book</label>
                            <select name="book_id" id="book_id" onchange="updateChapters()">
                                <option value="0">All Books</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?= $book['book_id'] ?>" <?= $bookId == $book['book_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($book['book_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="chapter_id">Chapter</label>
                            <select name="chapter_id" id="chapter_id">
                                <option value="0">All Chapters</option>
                                <?php foreach ($chapters as $chapter): ?>
                                    <option value="<?= $chapter['chapter_id'] ?>" <?= $chapterId == $chapter['chapter_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($chapter['chapter_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" placeholder="Search questions or topics..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="button" class="btn-filter" onclick="viewMcqs()">📖 View MCQs</button>
                        <button type="button" class="btn-filter" style="background: #28a745;" onclick="takeQuiz()">🎯 Take Online Test/Quiz</button>
                        <button type="button" class="btn-filter btn-clear" onclick="clearFilters()">🔄 Clear All</button>
                    </div>
                </form>
            </div>

            <!-- Action Buttons Info -->
            <?php if (!$viewMcqs): ?>
                <div class="action-info" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h3 style="margin-top: 0; color: #1976d2;">📚 How to Use</h3>
                    <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: #333;">
                        <li><strong>View MCQs:</strong> Select your class, book, and chapter, then click "View MCQs" to see questions with answers for learning.</li>
                        <li><strong>Take Online Test/Quiz:</strong> Click "Take Online Test/Quiz" to practice with a timed quiz and get instant results.</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Results Info -->
            <?php if ($viewMcqs): ?>
            <div class="results-info">
                <div class="results-count">
                    <?php if ($totalMcqs > 0): ?>
                        Showing <?= count($mcqs) ?> of <?= $totalMcqs ?> MCQs
                    <?php else: ?>
                        No MCQs found
                    <?php endif; ?>
                </div>
                <div class="show-answers-toggle">
                    <label for="showAnswersToggle" style="font-weight: 600; color: #333; cursor: pointer;">
                        Show Correct Answers
                    </label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="showAnswersToggle" onchange="toggleAnswers()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <!-- MCQs List -->
            <?php if ($viewMcqs && count($mcqs) > 0): ?>
                <div class="mcqs-list" id="mcqsList">
                    <?php foreach ($mcqs as $index => $mcq): ?>
                        <div class="mcq-card" data-mcq-id="<?= $mcq['mcq_id'] ?>">
                            <div class="mcq-meta">
                                <?php if (!empty($mcq['class_name'])): ?>
                                    <span>Class: <?= htmlspecialchars($mcq['class_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($mcq['book_name'])): ?>
                                    <span>Book: <?= htmlspecialchars($mcq['book_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($mcq['chapter_name'])): ?>
                                    <span>Chapter: <?= htmlspecialchars($mcq['chapter_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($mcq['topic'])): ?>
                                    <span class="mcq-topic">Topic: <?= htmlspecialchars($mcq['topic']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mcq-question">
                                <?= ($offset + $index + 1) ?>. <?= htmlspecialchars($mcq['question']) ?>
                            </div>

                            <div class="mcq-options">
                                <?php
                                $options = [
                                    'A' => $mcq['option_a'],
                                    'B' => $mcq['option_b'],
                                    'C' => $mcq['option_c'],
                                    'D' => $mcq['option_d']
                                ];
                                // Determine correct option letter by comparing correct_option text with option texts
                                $correctOptionText = trim($mcq['correct_option'] ?? '');
                                $correctOptionLetter = '';
                                foreach ($options as $label => $text) {
                                    if (strcasecmp(trim($text), $correctOptionText) === 0) {
                                        $correctOptionLetter = $label;
                                        break;
                                    }
                                }
                                // Fallback: if correct_option is already a letter (A, B, C, D)
                                if (empty($correctOptionLetter) && in_array(strtoupper($correctOptionText), ['A', 'B', 'C', 'D'])) {
                                    $correctOptionLetter = strtoupper($correctOptionText);
                                }
                                ?>
                                <?php foreach ($options as $label => $text): ?>
                                    <?php $isCorrect = $label === $correctOptionLetter; ?>
                                    <div class="mcq-option <?= $isCorrect ? 'correct' : '' ?>" 
                                         data-option="<?= $label ?>">
                                        <span class="option-label"><?= $label ?>.</span>
                                        <span class="option-text"><?= htmlspecialchars($text) ?></span>
                                        <?php if ($isCorrect): ?>
                                            <span class="correct-indicator" style="margin-left: auto; color: #28a745; font-weight: 600;">✓ Correct</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($correctOptionLetter)): ?>
                                <div class="correct-answer" style="margin-top: 1rem;">
                                    <span class="correct-answer-badge">✓ Correct Answer: <?= $correctOptionLetter ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Individual Show Answer Button -->
                            <button class="mcq-answer-btn" onclick="toggleIndividualAnswer(this)" data-mcq-id="<?= $mcq['mcq_id'] ?>">
                                <span class="btn-icon">👁️</span>
                                <span class="btn-text">Show Answer</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">« Previous</a>
                        <?php else: ?>
                            <span class="disabled">« Previous</span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next »</a>
                        <?php else: ?>
                            <span class="disabled">Next »</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($viewMcqs && count($mcqs) == 0): ?>
                <div class="no-mcqs">
                    <div class="no-mcqs-icon">📭</div>
                    <h3>No MCQs Found</h3>
                    <p>Try adjusting your filters or search terms to find MCQs.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        function updateBooks() {
            const classId = document.getElementById('class_id').value;
            const bookSelect = document.getElementById('book_id');
            const chapterSelect = document.getElementById('chapter_id');
            
            bookSelect.innerHTML = '<option value="0">Loading...</option>';
            chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
            
            if (classId == 0) {
                bookSelect.innerHTML = '<option value="0">All Books</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=books&class_id=${classId}`)
                .then(response => response.json())
                .then(books => {
                    bookSelect.innerHTML = '<option value="0">All Books</option>';
                    books.forEach(book => {
                        const option = document.createElement('option');
                        option.value = book.book_id;
                        option.textContent = book.book_name;
                        bookSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading books:', error);
                    bookSelect.innerHTML = '<option value="0">Error loading books</option>';
                });
        }

        function updateChapters() {
            const classId = document.getElementById('class_id').value;
            const bookId = document.getElementById('book_id').value;
            const chapterSelect = document.getElementById('chapter_id');
            
            chapterSelect.innerHTML = '<option value="0">Loading...</option>';
            
            if (bookId == 0 || classId == 0) {
                chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                return;
            }
            
            fetch(`../quiz/quiz_data.php?type=chapters&class_id=${classId}&book_id=${bookId}`)
                .then(response => response.json())
                .then(chapters => {
                    chapterSelect.innerHTML = '<option value="0">All Chapters</option>';
                    chapters.forEach(chapter => {
                        const option = document.createElement('option');
                        option.value = chapter.chapter_id;
                        option.textContent = chapter.chapter_name;
                        chapterSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading chapters:', error);
                    chapterSelect.innerHTML = '<option value="0">Error loading chapters</option>';
                });
        }

        function clearFilters() {
            window.location.href = 'mcqs.php';
        }

        function viewMcqs() {
            const classId = document.getElementById('class_id').value;
            const bookId = document.getElementById('book_id').value;
            const chapterId = document.getElementById('chapter_id').value;
            const search = document.getElementById('search').value;
            
            if (!classId || classId == 0) {
                alert('Please select a class first.');
                return;
            }
            
            if (!bookId || bookId == 0) {
                alert('Please select a book first.');
                return;
            }
            
            // Build URL with filters and view=1
            const params = new URLSearchParams();
            params.append('class_id', classId);
            params.append('book_id', bookId);
            if (chapterId > 0) params.append('chapter_id', chapterId);
            if (search.trim()) params.append('search', search.trim());
            params.append('view', '1');
            
            window.location.href = 'mcqs.php?' + params.toString();
        }

        function takeQuiz() {
            const classId = document.getElementById('class_id').value;
            const bookId = document.getElementById('book_id').value;
            const chapterId = document.getElementById('chapter_id').value;
            
            if (!classId || classId == 0) {
                alert('Please select a class first.');
                return;
            }
            
            if (!bookId || bookId == 0) {
                alert('Please select a book first.');
                return;
            }
            
            // Build URL to quiz_setup.php with parameters
            const params = new URLSearchParams();
            params.append('class_id', classId);
            params.append('book_id', bookId);
            if (chapterId > 0) {
                params.append('chapter_id', chapterId);
            }
            
            window.location.href = '../quiz/quiz_setup.php?' + params.toString();
        }

        // Toggle correct answers visibility
        function toggleAnswers() {
            const isChecked = document.getElementById('showAnswersToggle').checked;
            const mcqOptions = document.querySelectorAll('.mcq-option.correct');
            const correctAnswers = document.querySelectorAll('.correct-answer');
            const mcqCards = document.querySelectorAll('.mcq-card');
            const answerButtons = document.querySelectorAll('.mcq-answer-btn');
            
            if (isChecked) {
                // Show all answers
                mcqOptions.forEach(option => {
                    option.classList.add('show-answers');
                });
                correctAnswers.forEach(answer => {
                    answer.classList.add('show-answers');
                });
                // Hide individual answer buttons when showing all
                answerButtons.forEach(btn => {
                    btn.style.display = 'none';
                });
            } else {
                // Hide all answers
                mcqOptions.forEach(option => {
                    option.classList.remove('show-answers');
                });
                correctAnswers.forEach(answer => {
                    answer.classList.remove('show-answers');
                });
                // Show individual answer buttons
                answerButtons.forEach(btn => {
                    btn.style.display = 'inline-flex';
                });
                // Reset all individual answer states
                mcqCards.forEach(card => {
                    card.classList.remove('show-individual-answer');
                    const btn = card.querySelector('.mcq-answer-btn');
                    if (btn) {
                        btn.classList.remove('hide-answer');
                        btn.querySelector('.btn-text').textContent = 'Show Answer';
                        btn.querySelector('.btn-icon').textContent = '👁️';
                    }
                });
            }
        }

        // Toggle individual MCQ answer
        function toggleIndividualAnswer(button) {
            const mcqCard = button.closest('.mcq-card');
            const isShowing = mcqCard.classList.contains('show-individual-answer');
            const btnText = button.querySelector('.btn-text');
            const btnIcon = button.querySelector('.btn-icon');
            
            if (isShowing) {
                // Hide answer
                mcqCard.classList.remove('show-individual-answer');
                button.classList.remove('hide-answer');
                btnText.textContent = 'Show Answer';
                btnIcon.textContent = '👁️';
            } else {
                // Show answer
                mcqCard.classList.add('show-individual-answer');
                button.classList.add('hide-answer');
                btnText.textContent = 'Hide Answer';
                btnIcon.textContent = '🙈';
            }
        }

    </script>
</body>
</html>

