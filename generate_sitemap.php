<?php
require_once 'db_connect.php';
require_once __DIR__ . '/config/env.php';

header('Content-Type: application/xml; charset=UTF-8');
$baseUrl = rtrim(EnvLoader::get('PUBLIC_SITE_URL', EnvLoader::get('BASE_URL', 'https://ahmadlearninghub.com.pk')), '/');
if ($baseUrl === '' || preg_match('/localhost|127\.0\.0\.1|\.local(?:$|\/)/i', $baseUrl)) {
    $baseUrl = 'https://ahmadlearninghub.com.pk';
}
$today = date('Y-m-d');
$urls = [];

function toSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim((string) $slug, '-');
}

function toOrdinal($number) {
    $number = (int)$number;
    $mod100 = $number % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
        return $number . 'th';
    }
    $suffix = 'th';
    switch ($number % 10) {
        case 1:
            $suffix = 'st';
            break;
        case 2:
            $suffix = 'nd';
            break;
        case 3:
            $suffix = 'rd';
            break;
    }
    return $number . $suffix;
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function resolveLastmod(string $relativePath, string $today): string
{
    $routeSources = [
        '' => 'index.php',
        '/' => 'index.php',
        '/about' => 'about.php',
        '/contact' => 'contact.php',
        '/reviews' => 'reviews.php',
        '/privacy-policy' => 'privacy-policy.php',
        '/terms-and-conditions' => 'terms-and-conditions.php',
        '/class-9th-and-10th-online-question-paper-generator' => 'select_class.php',
        '/class-11-and-12-online-question-paper-generator' => 'select_class_11-12.php',
        '/online-question-paper-generator' => 'questionPaperFromTopic/home.php',
        '/class-9-and-10-online-mcqs-prepation-test' => 'quiz/quiz_setup.php',
        '/class-11-and-12-online-mcqs-prepation-test' => 'quiz/quiz_setup_inter.php',
        '/study-material-for-board-exam-preparations' => 'notes/note.php',
        '/class-notes' => 'uploadingNotesForClasses/index.php',
        '/textbooks' => 'notes/textbooks.php',
        '/topic-wise-mcqs-test' => 'quiz/mcqs_topic.php',
        '/online-quiz-hosting' => 'quiz/quiz-host-index.php',
        '/class-9-10-11-12-mcqs-for-board-exams' => 'notes/Mcqs/index.php',
        '/class-9-10-pastpaper-and-test-papers' => 'examPreparation/select_class_for_test.php',
        '/class-11-12-pastpaper-and-test-papers' => 'examPreparation/select_class_for_test.php',
        '/university-pastpaper-and-test-papers' => 'examPreparation/select_class_for_test.php',
        '/class-9-10-11-12-test-series-for-board-exams' => 'examPreparation/select_class_for_test.php',
    ];

    $sourcePath = $routeSources[$relativePath] ?? null;
    if ($sourcePath === null) {
        $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        $sourcePath = $normalizedPath;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $sourcePath;
    return is_file($fullPath) ? date('Y-m-d', (int) filemtime($fullPath)) : $today;
}

function addUrl(array &$urls, string $baseUrl, string $relativePath, string $changefreq, string $priority, string $today): void
{
    $relativePath = trim($relativePath);
    $relativePath = $relativePath === '/' ? '' : $relativePath;
    if ($relativePath !== '' && str_starts_with($relativePath, '/')) {
        $relativePath = '/' . ltrim($relativePath, '/');
    }

    $loc = $relativePath === '' ? $baseUrl : $baseUrl . $relativePath;
    if (isset($urls[$loc])) {
        return;
    }

    $urls[$loc] = [
        'loc' => $loc,
        'lastmod' => resolveLastmod($relativePath, $today),
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
}

$staticPages = [
    ['', 'daily', '1.0'],
    ['/about', 'monthly', '0.8'],
    ['/contact', 'monthly', '0.8'],
    ['/reviews', 'weekly', '0.8'],
    ['/privacy-policy', 'yearly', '0.5'],
    ['/terms-and-conditions', 'yearly', '0.5'],
];

foreach ($staticPages as [$path, $changefreq, $priority]) {
    addUrl($urls, $baseUrl, $path, $changefreq, $priority, $today);
}

addUrl($urls, $baseUrl, '/class-9th-and-10th-online-question-paper-generator', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/class-11-and-12-online-question-paper-generator', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/online-question-paper-generator', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-9-and-10-online-mcqs-prepation-test', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-11-and-12-online-mcqs-prepation-test', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/study-material-for-board-exam-preparations', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/class-notes', 'weekly', '0.9', $today);

// Publish clean, indexable landing URLs for every available class/subject and
// chapter combination. Search and file-type filters stay out of the sitemap.
$classNotesQuery = $conn->query("SELECT DISTINCT n.class, n.subject, n.chapter, ch.chapter_no
    FROM class_notes n
    LEFT JOIN chapter ch
      ON ch.class_id = n.class AND ch.book_name = n.subject AND ch.chapter_name = n.chapter
    WHERE n.status = 'approved' AND n.class IS NOT NULL AND n.class <> ''
      AND n.subject IS NOT NULL AND n.subject <> ''
    ORDER BY n.class ASC, n.subject ASC, ch.chapter_no ASC, n.chapter ASC");
if ($classNotesQuery) {
    while ($classNoteRoute = $classNotesQuery->fetch_assoc()) {
        $noteClass = trim((string) ($classNoteRoute['class'] ?? ''));
        $noteSubject = toSlug((string) ($classNoteRoute['subject'] ?? ''));
        $noteChapter = toSlug((string) ($classNoteRoute['chapter'] ?? ''));
        $noteChapterNumber = (int) ($classNoteRoute['chapter_no'] ?? 0);
        if ($noteClass === '' || $noteSubject === '') {
            continue;
        }
        $subjectPath = '/class-' . rawurlencode($noteClass) . '-' . $noteSubject . '-notes';
        addUrl($urls, $baseUrl, $subjectPath, 'weekly', '0.8', $today);
        if ($noteChapter !== '' || $noteChapterNumber > 0) {
            $chapterPath = $noteChapterNumber > 0 ? 'chapter-' . $noteChapterNumber : $noteChapter;
            addUrl($urls, $baseUrl, $subjectPath . '/' . $chapterPath, 'weekly', '0.7', $today);
        }
    }
}
addUrl($urls, $baseUrl, '/textbooks', 'monthly', '0.7', $today);
addUrl($urls, $baseUrl, '/topic-wise-mcqs-test', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/online-quiz-hosting', 'monthly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-9-10-11-12-mcqs-for-board-exams', 'weekly', '0.9', $today);
// Exam Preparation Entry Points
addUrl($urls, $baseUrl, '/class-9-10-pastpaper-and-test-papers', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/class-11-12-pastpaper-and-test-papers', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/university-pastpaper-and-test-papers', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-9-10-11-12-test-series-for-board-exams', 'weekly', '0.9', $today);

$classRows = [];
$classQuery = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
if ($classQuery) {
    while ($classRow = $classQuery->fetch_assoc()) {
        $classRows[] = $classRow;
    }
}

$bookRows = [];
$bookQuery = $conn->query('SELECT b.class_id, b.book_id, b.book_name,
    EXISTS (
        SELECT 1 FROM mcqs m
        WHERE m.class_id = b.class_id AND m.book_id = b.book_id
          AND m.correct_option IS NOT NULL AND m.correct_option <> ""
    ) AS has_quiz_questions
    FROM book b
    ORDER BY b.class_id ASC, b.book_name ASC');
if ($bookQuery) {
    while ($bookRow = $bookQuery->fetch_assoc()) {
        $bookRows[] = $bookRow;
    }
}

foreach ($classRows as $classRow) {
    $classId = (int) ($classRow['class_id'] ?? 0);
    $className = trim((string) ($classRow['class_name'] ?? ''));
    if ($classId <= 0) {
        continue;
    }
    
    addUrl($urls, $baseUrl, '/class-' . $classId . '-online-question-paper-generator', 'weekly', '0.8', $today);
    
    addUrl($urls, $baseUrl, '/class-' . $classId . '-all-subjects-test-series-with-solutions', 'weekly', '0.8', $today);
    if (in_array($classId, [9, 10, 11, 12], true)) {
        addUrl($urls, $baseUrl, '/class-' . $classId . '-all-subjects-mcqs-with-explanations', 'weekly', '0.8', $today);
    }
}

foreach ($bookRows as $bookRow) {
    $classId = (int) ($bookRow['class_id'] ?? 0);
    $bookName = trim((string) ($bookRow['book_name'] ?? ''));
    $hasQuizQuestions = (int) ($bookRow['has_quiz_questions'] ?? 0) === 1;
    if ($classId <= 0 || $bookName === '') {
        continue;
    }
    $bookSlug = toSlug($bookName);
    if ($bookSlug === '') {
        continue;
    }

    $ordinalClass = toOrdinal($classId);
    addUrl($urls, $baseUrl, '/' . $ordinalClass . '-class-' . $bookSlug . '-question-paper-generator', 'weekly', '0.8', $today);
    addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookSlug . '-chapterwise-test-series-with-solutions', 'weekly', '0.8', $today);
    if (in_array($classId, [9, 10, 11, 12], true)) {
        addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookSlug . '-chapter-wise-mcqs-with-explanations', 'weekly', '0.8', $today);
        if ($hasQuizQuestions) {
            addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookSlug . '-mcqs-test-2026', 'weekly', '0.8', $today);
        }
    }
}

$chapterRows = [];
$chapterQuery = $conn->query('SELECT ch.class_id, ch.chapter_no, ch.chapter_name, b.book_name
    FROM chapter ch
    JOIN book b ON b.book_id = ch.book_id
    WHERE EXISTS (
        SELECT 1 FROM mcqs m
        WHERE m.chapter_id = ch.chapter_id AND m.class_id = ch.class_id AND m.book_id = ch.book_id
    )
    ORDER BY ch.class_id ASC, b.book_id ASC, ch.chapter_no ASC');
if ($chapterQuery) {
    while ($chapterRow = $chapterQuery->fetch_assoc()) {
        $classId = (int) ($chapterRow['class_id'] ?? 0);
        $bookName = trim((string) ($chapterRow['book_name'] ?? ''));
        $chapterName = trim((string) ($chapterRow['chapter_name'] ?? ''));
        $chapterNo = (int) ($chapterRow['chapter_no'] ?? 0);
        if (!in_array($classId, [9, 10, 11, 12], true) || $bookName === '' || $chapterName === '') {
            continue;
        }
        // Clean chapterName to avoid redundancy in slug
        $cleanedChapterName = $chapterName;
        // Remove "Chapter X" or "Xth Chapter" patterns
        $cleanedChapterName = preg_replace('/(?:Chapter\s*)?' . preg_quote((string)$chapterNo, '/') . '(?:st|nd|rd|th)?\s*[-–]?\s*/i', '', $cleanedChapterName, 1);
        $cleanedChapterName = trim($cleanedChapterName, ' -');
        $chapterPart = $chapterNo > 0 ? 'chapter-' . $chapterNo . '-' . $cleanedChapterName : $cleanedChapterName;
        addUrl($urls, $baseUrl, '/class-' . $classId . '-' . toSlug($bookName) . '-' . toSlug($chapterPart) . '-mcqs-with-explanations', 'weekly', '0.7', $today);
    }
}

ksort($urls, SORT_NATURAL | SORT_FLAG_CASE);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . xmlEscape($url['loc']) . "</loc>\n";
    echo '    <lastmod>' . xmlEscape($url['lastmod']) . "</lastmod>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
?>
