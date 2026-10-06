<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../includes/home_search.php';
requireAdminAuth();

$message = '';
$messageType = 'success';
$csrfToken = generateCSRFToken();

function homeSearchNormalizeAdminUrl(string $rawUrl): ?string
{
    $url = trim($rawUrl);
    if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F]/', $url)) return null;

    $parts = parse_url($url);
    if ($parts === false) return null;
    if (isset($parts['scheme']) || isset($parts['host'])) {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $inputHost = strtolower((string) ($parts['host'] ?? ''));
        $currentHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (!in_array($scheme, ['http', 'https'], true) || $inputHost === '' || $currentHost === '') return null;

        $inputHostWithPort = $inputHost . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
        if ($inputHostWithPort !== $currentHost) return null;

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $url = $path . $query;
    }

    if (str_starts_with($url, '//')) return null;
    $url = preg_replace('~/+~', '/', $url) ?? $url;
    $pathOnly = explode('?', $url, 2)[0];
    foreach (explode('/', trim($pathOnly, '/')) as $segment) {
        if ($segment === '.' || $segment === '..') return null;
    }
    if (!preg_match('~^/?[A-Za-z0-9][A-Za-z0-9_./?=&%+#-]*$~', $url)) return null;

    return '/' . ltrim($url, '/');
}

function homeSearchDateWindow(string $range, string $customFrom, string $customTo): array
{
    $now = new DateTimeImmutable('now');
    $today = $now->setTime(0, 0, 0);
    if (!in_array($range, ['today', '1', '7', '28', 'custom'], true)) $range = '28';

    if ($range === 'today') {
        $start = $today;
        $end = $now->modify('+1 second');
        $label = 'Today';
    } elseif ($range === 'custom') {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $customFrom ?: '') ?: $today->modify('-27 days');
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $customTo ?: '') ?: $today;
        if ($from > $to) [$from, $to] = [$to, $from];
        $start = $from->setTime(0, 0, 0);
        $end = $to->setTime(0, 0, 0)->modify('+1 day');
        $customFrom = $start->format('Y-m-d');
        $customTo = $to->format('Y-m-d');
        $label = $start->format('M j, Y') . ' – ' . $to->format('M j, Y');
    } else {
        $days = (int) $range;
        $start = $now->modify('-' . $days . ' days');
        $end = $now->modify('+1 second');
        $label = 'Last ' . $days . ' day' . ($days === 1 ? '' : 's');
    }

    return [$range, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $label, $customFrom, $customTo];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken((string) ($_POST['csrf_token'] ?? ''))) {
        $message = 'Your session token expired. Refresh and try again.';
        $messageType = 'error';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($action === 'save_item') {
            $title = homeSearchTextSlice(trim((string) ($_POST['title'] ?? '')), 120);
            $description = homeSearchTextSlice(trim((string) ($_POST['description'] ?? '')), 255);
            $url = homeSearchNormalizeAdminUrl((string) ($_POST['url'] ?? ''));
            $icon = homeSearchTextSlice(trim((string) ($_POST['icon'] ?? 'fas fa-search')), 80);
            $keywords = homeSearchTextSlice(trim((string) ($_POST['keywords'] ?? '')), 3000);
            $synonyms = homeSearchTextSlice(trim((string) ($_POST['synonyms'] ?? '')), 3000);
            $sortOrder = max(0, min(65535, (int) ($_POST['sort_order'] ?? 100)));
            $isQuick = isset($_POST['is_quick_link']) ? 1 : 0;
            $isFallback = isset($_POST['is_fallback']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($title === '' || $keywords === '') {
                $message = 'Title and keywords are required.'; $messageType = 'error';
            } elseif ($url === null) {
                $message = 'Paste a same-site URL or safe local path, such as /class-notes.'; $messageType = 'error';
            } elseif (!preg_match('/^[a-z0-9 -]+$/i', $icon)) {
                $message = 'Icon may contain only Font Awesome class names.'; $messageType = 'error';
            } else {
                if ($id > 0) {
                    $stmt = $conn->prepare('UPDATE home_search_items SET title=?, description=?, url=?, icon=?, keywords=?, synonyms=?, is_quick_link=?, is_fallback=?, is_active=?, sort_order=? WHERE id=?');
                    if ($stmt) $stmt->bind_param('ssssssiiiii', $title, $description, $url, $icon, $keywords, $synonyms, $isQuick, $isFallback, $isActive, $sortOrder, $id);
                } else {
                    $stmt = $conn->prepare('INSERT INTO home_search_items (title, description, url, icon, keywords, synonyms, is_quick_link, is_fallback, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($stmt) $stmt->bind_param('ssssssiiii', $title, $description, $url, $icon, $keywords, $synonyms, $isQuick, $isFallback, $isActive, $sortOrder);
                }
                if (!empty($stmt) && $stmt->execute()) {
                    $message = $id > 0 ? 'Search destination updated.' : 'Search destination added.';
                    logAdminAction($id > 0 ? 'home_search_item_updated' : 'home_search_item_created', 'Item ID ' . ($id ?: $stmt->insert_id));
                } else { $message = 'Could not save the destination.'; $messageType = 'error'; }
                if (!empty($stmt)) $stmt->close();
            }
        } elseif ($action === 'delete_item' && $id > 0) {
            $stmt = $conn->prepare('DELETE FROM home_search_items WHERE id = ?');
            if ($stmt) { $stmt->bind_param('i', $id); $ok = $stmt->execute(); $stmt->close(); }
            $message = !empty($ok) ? 'Search destination deleted.' : 'Could not delete the destination.';
            $messageType = !empty($ok) ? 'success' : 'error';
            if (!empty($ok)) logAdminAction('home_search_item_deleted', 'Item ID ' . $id);
        } elseif ($action === 'save_settings') {
            $rules = ['placeholder' => [20,150], 'no_results_title' => [5,150], 'no_results_message' => [10,500]];
            $valid = true; $settingValues = [];
            foreach ($rules as $key => [$min, $max]) {
                $value = trim((string) ($_POST[$key] ?? ''));
                if (homeSearchTextLength($value) < $min || homeSearchTextLength($value) > $max) { $valid = false; break; }
                $settingValues[$key] = $value;
            }
            if ($valid) foreach ($settingValues as $key => $value) {
                $stmt = $conn->prepare('INSERT INTO home_search_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                if ($stmt) { $stmt->bind_param('ss', $key, $value); $stmt->execute(); $stmt->close(); }
            }
            $message = $valid ? 'Search display settings saved.' : 'Check the setting text lengths.';
            $messageType = $valid ? 'success' : 'error';
            if ($valid) logAdminAction('home_search_settings_updated');
        }
    }
}

[$selectedRange, $dateStart, $dateEnd, $dateLabel, $customFrom, $customTo] = homeSearchDateWindow((string) ($_GET['range'] ?? '28'), (string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? ''));
$items = homeSearchItems($conn, false);
$settings = homeSearchSettings($conn);
$logs = $popularQueries = $mcqsSearchResults = $paperSearchResults = [];
$stats = ['total' => 0, 'no_match' => 0];
$logResult = false;

try {
    $logStmt = $conn->prepare("SELECT l.id,l.query_text,l.match_type,l.results_count,l.created_at,i.title matched_title,u.name user_name FROM home_search_logs l LEFT JOIN home_search_items i ON i.id=l.matched_item_id LEFT JOIN users u ON u.id=l.user_id WHERE l.created_at >= ? AND l.created_at < ? ORDER BY l.created_at DESC LIMIT 200");
    if ($logStmt) { $logStmt->bind_param('ss', $dateStart, $dateEnd); $logStmt->execute(); $logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC); $logStmt->close(); $logResult = true; }
    $statsStmt = $conn->prepare("SELECT COUNT(*) total,SUM(match_type IN ('fallback','no_match')) no_match FROM home_search_logs WHERE created_at >= ? AND created_at < ?");
    if ($statsStmt) { $statsStmt->bind_param('ss', $dateStart, $dateEnd); $statsStmt->execute(); $row = $statsStmt->get_result()->fetch_assoc(); $stats = ['total' => (int) ($row['total'] ?? 0), 'no_match' => (int) ($row['no_match'] ?? 0)]; $statsStmt->close(); }
    $popularStmt = $conn->prepare("SELECT normalized_query,COUNT(*) searches,MAX(created_at) last_searched FROM home_search_logs WHERE created_at >= ? AND created_at < ? GROUP BY normalized_query ORDER BY searches DESC,last_searched DESC LIMIT 10");
    if ($popularStmt) { $popularStmt->bind_param('ss', $dateStart, $dateEnd); $popularStmt->execute(); $popularQueries = $popularStmt->get_result()->fetch_all(MYSQLI_ASSOC); $popularStmt->close(); }
} catch (Throwable $exception) { $logResult = false; }

try {
    $legacySql = "SELECT h.query_text,h.created_at,u.name user_name FROM %s h LEFT JOIN users u ON u.id=h.user_id WHERE h.created_at >= ? AND h.created_at < ? ORDER BY h.created_at DESC LIMIT 100";
    foreach ([['mcqs_topic_search_history', 'mcqsSearchResults'], ['question_paper_topic_search_history', 'paperSearchResults']] as [$table, $target]) {
        $legacyStmt = $conn->prepare(sprintf($legacySql, $table));
        if ($legacyStmt) { $legacyStmt->bind_param('ss', $dateStart, $dateEnd); $legacyStmt->execute(); $$target = $legacyStmt->get_result()->fetch_all(MYSQLI_ASSOC); $legacyStmt->close(); }
    }
} catch (Throwable $exception) { /* Older installations may not have both tables. */ }

include __DIR__ . '/header.php';
?>
<main class="hs-admin">
    <header class="hs-page-head">
        <div><h1>Homepage Smart Search</h1><p>Manage destinations, search language and visitor activity from one place.</p></div>
        <div class="hs-page-head-actions"><button class="hs-btn" type="button" data-search-item-add><i class="fas fa-plus"></i> Add destination</button><a class="hs-dashboard-link" href="dashboard.php">← Dashboard</a></div>
    </header>

    <?php if ($message): ?><div class="hs-msg <?= htmlspecialchars($messageType) ?>" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (empty($items) && !$logResult): ?><div class="hs-msg error">Smart-search tables are not installed. Run install.php once.</div><?php endif; ?>

    <section class="hs-card hs-date-card" aria-labelledby="dateFilterTitle">
        <div class="hs-card-heading"><div><h2 id="dateFilterTitle">Search activity period</h2><p>Apply the same date window to homepage, MCQ and question-paper history.</p></div></div>
        <div class="hs-date-panel">
            <div><span class="hs-filter-label">Quick ranges</span><nav class="hs-range-tabs" aria-label="Search date ranges">
                <?php foreach (['today' => 'Today', '1' => 'Last 1 day', '7' => 'Last 7 days', '28' => 'Last 28 days'] as $key => $label): ?><a class="hs-range-tab <?= $selectedRange === $key ? 'active' : '' ?>" href="?range=<?= urlencode($key) ?>"><?= htmlspecialchars($label) ?></a><?php endforeach; ?>
            </nav></div>
            <form class="hs-custom-filter" method="get"><input type="hidden" name="range" value="custom"><div class="hs-field"><label for="searchFrom">From</label><input class="hs-input hs-date-input" id="searchFrom" type="date" name="from" value="<?= htmlspecialchars($customFrom) ?>"></div><div class="hs-field"><label for="searchTo">To</label><input class="hs-input hs-date-input" id="searchTo" type="date" name="to" value="<?= htmlspecialchars($customTo) ?>"></div><button class="hs-btn secondary" type="submit"><i class="fas fa-calendar-check"></i> Apply custom</button></form>
        </div>
        <div class="hs-current-range">Showing: <strong><?= htmlspecialchars($dateLabel) ?></strong></div>
    </section>

    <section class="hs-stats" aria-label="Search statistics"><div class="hs-stat"><strong><?= number_format($stats['total']) ?></strong><span>Searches in this period</span></div><div class="hs-stat"><strong><?= number_format($stats['no_match']) ?></strong><span>Fallback / no-match searches</span></div><div class="hs-stat"><strong><?= number_format(count($items)) ?></strong><span>Managed destinations</span></div></section>

    <section class="hs-card"><div class="hs-card-heading"><div><h2>Search wording & no-match message</h2><p>Control the prompt visitors see and the response for unfamiliar searches.</p></div></div><form method="post" class="hs-modal-grid"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="save_settings"><div class="hs-field wide"><label for="searchPlaceholder">Search placeholder</label><input class="hs-input" id="searchPlaceholder" name="placeholder" maxlength="150" required value="<?= htmlspecialchars($settings['placeholder']) ?>"></div><div class="hs-field"><label for="noResultTitle">No-match title</label><input class="hs-input" id="noResultTitle" name="no_results_title" maxlength="150" required value="<?= htmlspecialchars($settings['no_results_title']) ?>"></div><div class="hs-field"><label for="noResultMessage">No-match explanation</label><input class="hs-input" id="noResultMessage" name="no_results_message" maxlength="500" required value="<?= htmlspecialchars($settings['no_results_message']) ?>"></div><div><button class="hs-btn" type="submit">Save display settings</button></div></form></section>

    <section class="hs-card"><div class="hs-card-heading"><div><h2>Managed destinations</h2><p>Each destination can be searched by title, keywords and alternate meanings.</p></div><button class="hs-btn" type="button" data-search-item-add><i class="fas fa-plus"></i> Add</button></div><div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>Destination</th><th>Search language</th><th>Display rules</th><th>Order</th><th>Actions</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><div class="hs-destination-name"><i class="<?= htmlspecialchars($item['icon']) ?>"></i><span><?= htmlspecialchars($item['title']) ?><small class="hs-url"><?= htmlspecialchars($item['url']) ?></small></span></div></td><td class="hs-language"><strong>Keywords:</strong> <?= htmlspecialchars($item['keywords']) ?><br><strong>Synonyms:</strong> <?= htmlspecialchars($item['synonyms']) ?></td><td><span class="hs-badge <?= $item['is_active'] ? 'on' : '' ?>"><?= $item['is_active'] ? 'Active' : 'Hidden' ?></span><?php if ($item['is_quick_link']): ?><span class="hs-badge on">Quick link</span><?php endif; ?><?php if ($item['is_fallback']): ?><span class="hs-badge">Fallback</span><?php endif; ?></td><td><?= (int) $item['sort_order'] ?></td><td><div class="hs-actions"><button class="hs-btn secondary hs-icon-btn" type="button" title="Edit destination" aria-label="Edit <?= htmlspecialchars($item['title']) ?>" data-search-item-edit='<?= htmlspecialchars(json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>'><i class="fas fa-pen"></i></button><form method="post" data-search-delete-form><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="hs-btn danger hs-icon-btn" type="submit" title="Delete destination" aria-label="Delete <?= htmlspecialchars($item['title']) ?>"><i class="fas fa-trash"></i></button></form></div></td></tr><?php endforeach; ?><?php if (!$items): ?><tr><td colspan="5" class="hs-muted">No destinations configured yet.</td></tr><?php endif; ?></tbody></table></div></section>

    <div class="hs-two"><section class="hs-card"><div class="hs-card-heading"><div><h2>Popular searches</h2><p><?= htmlspecialchars($dateLabel) ?></p></div></div><table class="hs-mini-table"><thead><tr><th>Query</th><th>Count</th><th>Last searched</th></tr></thead><tbody><?php foreach ($popularQueries as $row): ?><tr><td><?= htmlspecialchars($row['normalized_query']) ?></td><td><?= (int) $row['searches'] ?></td><td><?= htmlspecialchars($row['last_searched']) ?></td></tr><?php endforeach; ?><?php if (!$popularQueries): ?><tr><td colspan="3" class="hs-muted">No search data for this period.</td></tr><?php endif; ?></tbody></table></section><section class="hs-card"><div class="hs-card-heading"><div><h2>How matching works</h2><p>Keep results relevant as your website grows.</p></div></div><div class="hs-help">Titles and phrases rank highest. Keywords add direct intent matches. Synonyms teach alternate wording. Built-in groups understand <strong>make / build / generate</strong>, <strong>quiz / MCQ / objective</strong>, and <strong>math / maths / mathematics</strong>. Small spelling mistakes are tolerated. Keep at least one active destination marked <strong>Fallback</strong>.</div></section></div>

    <section class="hs-card"><div class="hs-card-heading"><div><h2>Latest homepage searches</h2><p><?= htmlspecialchars($dateLabel) ?> · showing up to 200 searches</p></div></div><div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>Date</th><th>User</th><th>Search query</th><th>Outcome</th><th>Results</th></tr></thead><tbody><?php foreach ($logs as $row): ?><tr><td><?= htmlspecialchars($row['created_at']) ?></td><td><strong><?= htmlspecialchars($row['user_name'] ?: 'Guest') ?></strong></td><td><strong><?= htmlspecialchars($row['query_text']) ?></strong></td><td><span class="hs-badge <?= $row['match_type'] === 'result' ? 'on' : '' ?>"><?= htmlspecialchars($row['match_type']) ?></span><?= $row['matched_title'] ? ' ' . htmlspecialchars($row['matched_title']) : '' ?></td><td><?= (int) $row['results_count'] ?></td></tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="5" class="hs-muted">No homepage searches recorded for this period.</td></tr><?php endif; ?></tbody></table></div></section>

    <details class="hs-card hs-collapsible"><summary>Specialized MCQ & question-paper search history <span class="hs-muted"><?= htmlspecialchars($dateLabel) ?> · existing logs</span></summary><div class="hs-collapsible-body"><div class="hs-two"><div><h2>MCQ topic searches</h2><div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>Date</th><th>User</th><th>Query</th></tr></thead><tbody><?php foreach ($mcqsSearchResults as $row): ?><tr><td><?= htmlspecialchars($row['created_at']) ?></td><td><?= htmlspecialchars($row['user_name'] ?: 'Guest') ?></td><td><?= htmlspecialchars($row['query_text']) ?></td></tr><?php endforeach; ?><?php if (!$mcqsSearchResults): ?><tr><td colspan="3" class="hs-muted">No MCQ searches in this period.</td></tr><?php endif; ?></tbody></table></div></div><div><h2>Question-paper topic searches</h2><div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>Date</th><th>User</th><th>Query</th></tr></thead><tbody><?php foreach ($paperSearchResults as $row): ?><tr><td><?= htmlspecialchars($row['created_at']) ?></td><td><?= htmlspecialchars($row['user_name'] ?: 'Guest') ?></td><td><?= htmlspecialchars($row['query_text']) ?></td></tr><?php endforeach; ?><?php if (!$paperSearchResults): ?><tr><td colspan="3" class="hs-muted">No question-paper searches in this period.</td></tr><?php endif; ?></tbody></table></div></div></div></div></details>
<section class="hs-card hs-legacy-actions" aria-labelledby="legacyHistoryTitle">
    <div class="hs-card-heading"><div><h2 id="legacyHistoryTitle">Specialized MCQ &amp; question-paper search history</h2><p><?= htmlspecialchars($dateLabel) ?> · existing logs</p></div></div>
    <div class="hs-history-buttons">
        <button class="hs-history-button hs-history-mcq" type="button" data-history-popup="mcq"><i class="fas fa-list-check"></i><span><strong>MCQ searches</strong><small>View topic-search activity</small></span><i class="fas fa-arrow-right"></i></button>
        <button class="hs-history-button hs-history-paper" type="button" data-history-popup="paper"><i class="fas fa-file-lines"></i><span><strong>Question-paper searches</strong><small>View paper-topic activity</small></span><i class="fas fa-arrow-right"></i></button>
    </div>
</section>
</main>

<div class="hs-history-modal" id="historyPopup" hidden aria-hidden="true"><div class="hs-history-backdrop" data-history-close></div><section class="hs-history-dialog" role="dialog" aria-modal="true" aria-labelledby="historyPopupTitle"><header class="hs-history-dialog-head"><div><h2 id="historyPopupTitle">Search history</h2><p><?= htmlspecialchars($dateLabel) ?></p></div><button class="hs-btn secondary hs-icon-btn" type="button" data-history-close aria-label="Close search history"><i class="fas fa-times"></i></button></header><div class="hs-history-dialog-body" id="historyPopupBody"></div></section></div>

<div class="hs-modal" id="searchItemModal" hidden aria-hidden="true"><div class="hs-modal-backdrop" data-search-modal-close></div><section class="hs-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="searchModalTitle"><div class="hs-modal-head"><div><h2 id="searchModalTitle">Add search destination</h2><p>Give visitors a clear route and teach smart search how to find it.</p></div><button class="hs-btn hs-modal-close" type="button" data-search-modal-close aria-label="Close editor"><i class="fas fa-times"></i></button></div><form method="post" id="searchItemForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" id="searchItemId" value="0"><div class="hs-modal-grid"><div class="hs-field"><label for="itemTitle">Title *</label><input class="hs-input" id="itemTitle" name="title" maxlength="120" required></div><div class="hs-field"><label for="itemUrl">Local URL *</label><input class="hs-input" id="itemUrl" name="url" maxlength="500" placeholder="https://your-site/page or /page" required><small class="hs-field-help">Paste a full URL from this website or a local path. It is stored as a safe root-relative URL.</small></div><div class="hs-field"><label for="itemIcon">Font Awesome icon</label><input class="hs-input" id="itemIcon" name="icon" value="fas fa-search" maxlength="80"></div><div class="hs-field"><label for="itemOrder">Display order</label><input class="hs-input" id="itemOrder" type="number" name="sort_order" value="100" min="0" max="65535"></div><div class="hs-field wide"><label for="itemDescription">Description</label><input class="hs-input" id="itemDescription" name="description" maxlength="255"></div><div class="hs-field"><label for="itemKeywords">Keywords *</label><textarea class="hs-textarea" id="itemKeywords" name="keywords" placeholder="question paper, exam maker" required></textarea></div><div class="hs-field"><label for="itemSynonyms">Synonyms / same meanings</label><textarea class="hs-textarea" id="itemSynonyms" name="synonyms" placeholder="paper builder, test creator"></textarea></div><div class="hs-field wide hs-checks"><label><input type="checkbox" name="is_quick_link" id="itemQuick" checked> Quick link</label><label><input type="checkbox" name="is_fallback" id="itemFallback"> No-match fallback</label><label><input type="checkbox" name="is_active" id="itemActive" checked> Active</label></div></div><div class="hs-modal-actions"><button class="hs-btn secondary" type="button" data-search-modal-close>Cancel</button><button class="hs-btn" type="submit"><i class="fas fa-save"></i> Save destination</button></div></form></section></div>
<script src="<?= htmlspecialchars($baseUrl) ?>js/admin-search.js" defer></script>
<?php include __DIR__ . '/footer.php'; ?>
