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
    ['/quiz/online_quiz_host_new.php', 'weekly', '0.6'],
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
addUrl($urls, $baseUrl, '/online-question-paper-generator', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/online-mcqs-test-for-9th-and-10th-board-exams', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/topic-wise-mcqs-test', 'weekly', '0.8', $today);
addUrl($urls, $baseUrl, '/online-mcqs-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-short-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-long-question-paper-generator', 'weekly', '0.7', $today);
addUrl($urls, $baseUrl, '/online-mcqs-short-and-long-question-paper-generator', 'weekly', '0.7', $today);

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
    if ($classId <= 0) {
        continue;
    }
    addUrl($urls, $baseUrl, '/class-' . $classId . '-online-question-paper-generator', 'weekly', '0.8', $today);
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
    addUrl($urls, $baseUrl, '/mcqs/' . $ordinalClass . '-class/' . $bookSlug, 'weekly', '0.7', $today);
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
