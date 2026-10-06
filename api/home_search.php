<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/home_search.php';
require_once __DIR__ . '/../includes/home_search_security.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function homeSearchJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    homeSearchJson(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

// This is deliberately checked before loading the database connection. A
// public search endpoint must reject abusive traffic as cheaply as possible.
$rateLimit = homeSearchRateLimit('public', 60, 60);
if (!$rateLimit['available'] || !$rateLimit['allowed']) {
    homeSearchRateLimitResponse($rateLimit);
}

if ($method === 'GET') {
    $query = homeSearchRequestQuery($_GET['q'] ?? null);
    if ($query === '' || homeSearchTextLength($query) < 2 || homeSearchNormalize($query) === '') {
        homeSearchJson(['ok' => false, 'message' => 'Enter at least two valid characters.'], 422);
    }

    require_once __DIR__ . '/../db_connect.php';
    header('Cache-Control: public, max-age=15, stale-while-revalidate=30');
    $items = homeSearchItems($conn);
    $settings = homeSearchSettings($conn);
    $contentMatches = array_merge(
        homeSearchContentResults($conn, $query),
        homeSearchNoteResults($conn, $query),
        homeSearchUploadedNoteResults($conn, $query)
    );
    $staticMatches = homeSearchRank($items, $query);
    $exactMatch = $query !== '' && (!empty($contentMatches) || !empty($staticMatches));
    if (!empty($contentMatches)) {
        $publicMatches = array_slice(array_merge(
            array_map('homeSearchPublicResult', $contentMatches),
            array_map('homeSearchPublicItem', $staticMatches)
        ), 0, 12);
    } else {
        $matches = $staticMatches;
        if (!$exactMatch) {
            $matches = array_slice(array_values(array_filter($items, static fn(array $item): bool => !empty($item['is_fallback']))), 0, 6);
        }
        $publicMatches = array_map('homeSearchPublicItem', $matches);
    }
    homeSearchJson([
        'ok' => true,
        'matched' => $exactMatch,
        'items' => $publicMatches,
        'message' => $exactMatch ? '' : $settings['no_results_message'],
    ]);
}

if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 4096) {
    homeSearchJson(['ok' => false, 'message' => 'Request is too large.'], 413);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_contains($contentType, 'application/json')) {
    homeSearchJson(['ok' => false, 'message' => 'JSON requests are required.'], 415);
}

session_start();
$providedToken = (string) ($_SERVER['HTTP_X_HOME_SEARCH_TOKEN'] ?? '');
if (empty($_SESSION['home_search_csrf']) || !hash_equals((string) $_SESSION['home_search_csrf'], $providedToken)) {
    homeSearchJson(['ok' => false, 'message' => 'Invalid request token.'], 403);
}

$rawBody = file_get_contents('php://input', false, null, 0, 4097);
if (strlen((string) $rawBody) > 4096) {
    homeSearchJson(['ok' => false, 'message' => 'Request is too large.'], 413);
}
$input = json_decode($rawBody ?: '{}', true);
if (!is_array($input)) {
    homeSearchJson(['ok' => false, 'message' => 'Invalid request.'], 400);
}

$query = homeSearchRequestQuery($input['query'] ?? null);
if ($query === '' || homeSearchTextLength($query) < 2) {
    homeSearchJson(['ok' => false, 'message' => 'Enter at least two characters.'], 422);
}

// Lightweight session limiter prevents public analytics spam without blocking normal use.
$now = time();
$events = array_values(array_filter($_SESSION['home_search_events'] ?? [], static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - 60));
if (count($events) >= 20) {
    homeSearchJson(['ok' => false, 'message' => 'Please wait before searching again.'], 429);
}
$events[] = $now;
$_SESSION['home_search_events'] = $events;

require_once __DIR__ . '/../db_connect.php';

$matchedItemId = filter_var($input['matched_item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$matchType = in_array(($input['match_type'] ?? ''), ['result', 'quick_link', 'fallback', 'no_match'], true) ? $input['match_type'] : 'result';
$resultsCount = max(0, min(20, (int) ($input['results_count'] ?? 0)));
$normalized = homeSearchNormalize($query);
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$sessionHash = hash('sha256', session_id());
$ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|home-search');
$userAgent = homeSearchTextSlice((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255);

try {
    $stmt = $conn->prepare('INSERT INTO home_search_logs (query_text, normalized_query, matched_item_id, match_type, results_count, user_id, session_hash, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('ssisiisss', $query, $normalized, $matchedItemId, $matchType, $resultsCount, $userId, $sessionHash, $ipHash, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
} catch (Throwable $exception) {
    error_log('Home search analytics insert failed: ' . $exception->getMessage());
}

homeSearchJson(['ok' => true]);
