<?php
require_once 'db_connect.php';
require_once __DIR__ . '/config/env.php';

header('Content-Type: application/xml; charset=UTF-8');
$baseUrl = rtrim(EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', 'https://ahmadlearninghub.com.pk')), '/');
$today = date('Y-m-d');
$urls = [];

function toSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim((string) $slug, '-');
}

function toOrdinal(int $number): string
{
    $mod100 = $number % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
        return $number . 'th';
    }
    $suffix = match ($number % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th'
    };
    return $number . $suffix;
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function resolveLastmod(string $relativePath, string $today): string
{
    if ($relativePath === '' || $relativePath === '/') {
        $indexFile = __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
        return is_file($indexFile) ? date('Y-m-d', (int) filemtime($indexFile)) : $today;
    }

    $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $normalizedPath;
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
    ['/index.php', 'daily', '0.9'],
    ['/about.php', 'monthly', '0.8'],
    ['/contact.php', 'monthly', '0.8'],
    ['/reviews.php', 'weekly', '0.8'],
    ['/privacy-policy.php', 'yearly', '0.5'],
    ['/terms-and-conditions.php', 'yearly', '0.5'],
    ['/subscription.php', 'weekly', '0.7'],
    ['/select_class.php', 'weekly', '0.9'],
    ['/select_topics.php', 'weekly', '0.7'],
    ['/select_topics_by_book.php', 'weekly', '0.7'],
    ['/generate_question_paper.php', 'weekly', '0.7'],
    ['/questionPaperFromTopic/home.php', 'weekly', '0.8'],
    ['/questionPaperFromTopic/generate_ai_paper.php', 'weekly', '0.7'],
    ['/quiz/quiz_setup.php', 'weekly', '0.8'],
    ['/quiz/mcqs_topic.php', 'weekly', '0.8'],
    ['/quiz/quiz.php', 'weekly', '0.7'],
    ['/online_quiz_host_new', 'weekly', '0.8'],
    ['/quiz/online_quiz_dashboard.php', 'weekly', '0.6'],
    ['/quiz/online_quiz_join.php', 'weekly', '0.6'],
    ['/quiz/online_quiz_lobby.php', 'weekly', '0.6'],
    ['/quiz/online_quiz_take.php', 'weekly', '0.6'],
    ['/notes/textbooks.php', 'weekly', '0.8'],
    ['/notes/mcqs.php', 'weekly', '0.8'],
];

foreach ($staticPages as [$path, $changefreq, $priority]) {
    addUrl($urls, $baseUrl, $path, $changefreq, $priority, $today);
}

addUrl($urls, $baseUrl, '/class-9th-and-10th-online-question-paper-generator', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/Class-11-and-12-Online-Question-Paper-generator', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/online-question-paper-generator', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/online-mcqs-test-for-9th-and-10th-board-exams', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/study-material-for-board-exam-preparations', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/topic-wise-mcqs-test', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-9-10-11-12-mcqs-for-board-exams', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/online-mcqs-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-short-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-long-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-mcqs-short-and-long-question-paper-generator', 'weekly', '0.7', $today);

// Exam Preparation Entry Points
addUrl($urls, $baseUrl, '/Class-9-10-pastPaper-&-Test-Papers', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/Class-11-12-pastPaper-&-Test-Papers', 'weekly', '0.9', $today);
addUrl($urls, $baseUrl, '/University-pastPaper-&-Test-Papers', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/class-9-10-11-12-test-series-for-board-exams', 'weekly', '0.9', $today);

$classRows = [];
$classQuery = $conn->query('SELECT class_id, class_name FROM class ORDER BY class_id ASC');
if ($classQuery) {
    while ($classRow = $classQuery->fetch_assoc()) {
        $classRows[] = $classRow;
    }
}

$bookRows = [];
$bookQuery = $conn->query('SELECT class_id, book_name FROM book ORDER BY class_id ASC, book_name ASC');
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
    if ($classId <= 0 || $bookName === '') {
        continue;
    }
    $bookSlug = toSlug($bookName);
    if ($bookSlug === '') {
        continue;
    }

    $ordinalClass = toOrdinal($classId);
    addUrl($urls, $baseUrl, '/' . $ordinalClass . '-class-' . $bookSlug . '-question-paper-generator', 'weekly', '0.8', $today);
    $bookNameUrl = urlencode(str_replace(' ', '-', $bookName));
    addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookNameUrl . '-chapterWise-test-series-with-solutions', 'weekly', '0.8', $today);
    if (in_array($classId, [9, 10, 11, 12], true)) {
        addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookSlug . '-chapter-wise-mcqs-with-explanations', 'weekly', '0.8', $today);
        addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookSlug . '-mcqs-test-2026', 'weekly', '0.8', $today);
    }
}

$chapterRows = [];
$chapterQuery = $conn->query('SELECT ch.class_id, ch.chapter_no, ch.chapter_name, b.book_name FROM chapter ch JOIN book b ON b.book_id = ch.book_id ORDER BY ch.class_id ASC, b.book_id ASC, ch.chapter_no ASC');
if ($chapterQuery) {
    while ($chapterRow = $chapterQuery->fetch_assoc()) {
        $classId = (int) ($chapterRow['class_id'] ?? 0);
        $bookName = trim((string) ($chapterRow['book_name'] ?? ''));
        $chapterName = trim((string) ($chapterRow['chapter_name'] ?? ''));
        $chapterNo = (int) ($chapterRow['chapter_no'] ?? 0);
        if (!in_array($classId, [9, 10, 11, 12], true) || $bookName === '' || $chapterName === '') {
            continue;
        }
        $chapterPart = $chapterNo > 0 ? 'chapter-' . $chapterNo . '-' . $chapterName : $chapterName;
        addUrl($urls, $baseUrl, '/class-' . $classId . '-' . toSlug($bookName) . '-' . toSlug($chapterPart) . '-mcqs-with-explanations', 'weekly', '0.7', $today);
    }
}

$examQuery = $conn->query('SELECT e.id, e.class_id, e.title, b.book_name FROM exam_preparations e JOIN book b ON e.book_id = b.book_id ORDER BY e.id ASC');
if ($examQuery) {
    while ($examRow = $examQuery->fetch_assoc()) {
        $classId = (int) ($examRow['class_id'] ?? 0);
        $bookName = trim((string) ($examRow['book_name'] ?? ''));
        $examTitle = trim((string) ($examRow['title'] ?? ''));
        $examId = (int) ($examRow['id'] ?? 0);
        
        if ($classId <= 0 || $bookName === '' || $examTitle === '' || $examId <= 0) {
            continue;
        }
        
        $bookNameUrl = urlencode(str_replace(' ', '-', $bookName));
        $examTitleSlug = toSlug($examTitle);
        if ($examTitleSlug !== '') {
            addUrl($urls, $baseUrl, '/class-' . $classId . '-' . $bookNameUrl . '-' . $examTitleSlug . '-with-solutions', 'weekly', '0.7', $today);
        }
    }
}

ksort($urls, SORT_NATURAL | SORT_FLAG_CASE);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . xmlEscape($url['loc']) . "</loc>\n";
    echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $url['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
?>
