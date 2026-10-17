<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config/env.php';

const ALH_NOTES_SITEMAP_BATCH_SIZE = 25000; // two URLs per note; stay below Google's 50,000 URL limit.
const ALH_NOTES_SITEMAP_ROUTE = '/sitemap-notes.xml';

function alhNotesSitemapEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function alhNotesSitemapBaseUrl(): string
{
    $configured = trim((string) EnvLoader::get('PUBLIC_SITE_URL', EnvLoader::get('BASE_URL', '')));
    if ($configured === '' || preg_match('/localhost|127\.0\.0\.1|\.local(?:$|\/)/i', $configured)) {
        $configured = 'https://ahmadlearninghub.com.pk';
    }

    return rtrim($configured, '/');
}

function alhNotesSitemapSlug(string $value, string $fallback): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : $fallback;
}

function alhNotesSitemapNotePaths(array $note): array
{
    $class = (string) ($note['class'] ?? '9');
    $subject = alhNotesSitemapSlug((string) ($note['subject'] ?? ''), 'general');
    $title = alhNotesSitemapSlug((string) ($note['title'] ?? ''), 'study-notes');
    $id = (int) ($note['id'] ?? 0);

    $notePath = '/class-' . rawurlencode($class) . '-' . $subject . '-' . $title . '-' . $id;
    $downloadParts = ['class-' . alhNotesSitemapSlug($class, '9'), $subject];
    $chapter = trim((string) ($note['chapter'] ?? ''));
    if ($chapter !== '') {
        $downloadParts[] = alhNotesSitemapSlug($chapter, 'chapter');
    }
    $downloadParts[] = $title;
    $downloadPath = '/download-notes/' . implode('-', $downloadParts) . '-' . $id;

    return [$notePath, $downloadPath];
}

function alhNotesSitemapSendUrlset(mysqli $conn, string $baseUrl, int $part, int $parts): never
{
    if ($part < 1 || $part > $parts) {
        http_response_code(404);
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />' . "\n";
        exit;
    }

    $offset = ($part - 1) * ALH_NOTES_SITEMAP_BATCH_SIZE;
    $limit = ALH_NOTES_SITEMAP_BATCH_SIZE;
    $stmt = $conn->prepare("SELECT id, title, class, subject, chapter, COALESCE(approved_at, created_at) AS last_modified
        FROM class_notes
        WHERE status = 'approved' AND drive_status = 'available'
        ORDER BY id ASC
        LIMIT ? OFFSET ?");
    if (!$stmt) {
        error_log('Notes sitemap query could not be prepared: ' . $conn->error);
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Notes sitemap is temporarily unavailable.');
    }

    $stmt->bind_param('ii', $limit, $offset);
    if (!$stmt->execute()) {
        error_log('Notes sitemap query failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Notes sitemap is temporarily unavailable.');
    }

    $result = $stmt->get_result();
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    while ($note = $result->fetch_assoc()) {
        $lastModified = strtotime((string) ($note['last_modified'] ?? ''));
        $lastMod = $lastModified ? date('Y-m-d', $lastModified) : date('Y-m-d');
        foreach (alhNotesSitemapNotePaths($note) as $path) {
            echo "  <url>\n";
            echo '    <loc>' . alhNotesSitemapEscape($baseUrl . $path) . "</loc>\n";
            echo '    <lastmod>' . alhNotesSitemapEscape($lastMod) . "</lastmod>\n";
            echo "  </url>\n";
        }
    }
    $stmt->close();
    echo "</urlset>\n";
    exit;
}

$availableNotes = $conn->query("SELECT COUNT(*) AS total_notes,
    MAX(COALESCE(approved_at, created_at)) AS last_modified
    FROM class_notes
    WHERE status = 'approved' AND drive_status = 'available'");
if (!$availableNotes) {
    error_log('Notes sitemap count query failed: ' . $conn->error);
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Notes sitemap is temporarily unavailable.');
}

$stats = $availableNotes->fetch_assoc() ?: ['total_notes' => 0, 'last_modified' => null];
$noteCount = max(0, (int) ($stats['total_notes'] ?? 0));
$parts = max(1, (int) ceil($noteCount / ALH_NOTES_SITEMAP_BATCH_SIZE));
$baseUrl = alhNotesSitemapBaseUrl();
$requestedPart = filter_input(INPUT_GET, 'part', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($requestedPart !== null && $requestedPart !== false) {
    alhNotesSitemapSendUrlset($conn, $baseUrl, (int) $requestedPart, $parts);
}

// Show the URLs directly when they fit in one sitemap. Use an index only when
// the note collection needs multiple files to stay within the protocol limit.
if ($parts === 1) {
    alhNotesSitemapSendUrlset($conn, $baseUrl, 1, 1);
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
for ($part = 1; $part <= $parts; $part++) {
    $partUrl = $baseUrl . ALH_NOTES_SITEMAP_ROUTE . '?part=' . $part;
    echo "  <sitemap>\n";
    echo '    <loc>' . alhNotesSitemapEscape($partUrl) . "</loc>\n";
    if (!empty($stats['last_modified'])) {
        $lastModified = strtotime((string) $stats['last_modified']);
        if ($lastModified) {
            echo '    <lastmod>' . date('Y-m-d', $lastModified) . "</lastmod>\n";
        }
    }
    echo "  </sitemap>\n";
}
echo "</sitemapindex>\n";
