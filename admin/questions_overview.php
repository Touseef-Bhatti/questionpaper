<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
requireAdminAuth();

function overviewEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetchOverviewRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Questions overview prepare failed: ' . $conn->error);
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log('Questions overview query failed: ' . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

$selectedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: 0;
$selectedBookId = filter_input(INPUT_GET, 'book_id', FILTER_VALIDATE_INT) ?: 0;
$selectedChapterId = filter_input(INPUT_GET, 'chapter_id', FILTER_VALIDATE_INT) ?: 0;
$hasBookSelection = $selectedClassId > 0 && $selectedBookId > 0;

$classes = fetchOverviewRows($conn, 'SELECT class_id, class_name FROM class ORDER BY class_id ASC');
$books = $selectedClassId > 0
    ? fetchOverviewRows($conn, 'SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_name ASC', 'i', [$selectedClassId])
    : [];
$chapters = ($selectedClassId > 0 && $selectedBookId > 0)
    ? fetchOverviewRows($conn, 'SELECT chapter_id, chapter_no, chapter_name FROM chapter WHERE class_id = ? AND book_id = ? ORDER BY chapter_no ASC, chapter_id ASC', 'ii', [$selectedClassId, $selectedBookId])
    : [];

$where = [];
$params = [];
$types = '';
if ($selectedClassId > 0) { $where[] = 'c.class_id = ?'; $types .= 'i'; $params[] = $selectedClassId; }
if ($selectedBookId > 0) { $where[] = 'c.book_id = ?'; $types .= 'i'; $params[] = $selectedBookId; }
if ($selectedChapterId > 0) { $where[] = 'c.chapter_id = ?'; $types .= 'i'; $params[] = $selectedChapterId; }

$overviewSql = "SELECT
        c.chapter_id, c.chapter_no, c.chapter_name, c.class_id, c.book_id,
        cl.class_name, b.book_name,
        /* Book-generated rows are synchronized into the legacy tables after approval.
           Count the book row, or a legacy row only when no matching book row exists. */
        ((SELECT COUNT(*)
            FROM mcqs_from_book m
           WHERE m.class_id = c.class_id
             AND m.book_id = c.book_id
             AND m.chapter_id = c.chapter_id)
          + (SELECT COUNT(*)
               FROM mcqs m
              WHERE m.class_id = c.class_id
                AND m.book_id = c.book_id
                AND m.chapter_id = c.chapter_id
                AND NOT EXISTS (
                    SELECT 1
                      FROM mcqs_from_book mb
                     WHERE mb.class_id = m.class_id
                       AND mb.book_id = m.book_id
                       AND mb.chapter_id = m.chapter_id
                       AND BINARY mb.question = BINARY m.question
                ))) AS mcq_count,
        ((SELECT COUNT(*)
            FROM questions_from_book q
           WHERE q.class_id = c.class_id
             AND q.book_id = c.book_id
             AND q.chapter_id = c.chapter_id
             AND BINARY q.question_type = BINARY 'short')
          + (SELECT COUNT(*)
               FROM questions q
              WHERE q.class_id = c.class_id
                AND q.book_id = c.book_id
                AND q.chapter_id = c.chapter_id
                AND BINARY q.question_type = BINARY 'short'
                AND NOT EXISTS (
                    SELECT 1
                      FROM questions_from_book qb
                     WHERE qb.class_id = q.class_id
                       AND qb.book_id = q.book_id
                       AND qb.chapter_id = q.chapter_id
                       AND BINARY qb.question_type = BINARY q.question_type
                       AND BINARY qb.question_text = BINARY q.question_text
                ))) AS short_count,
        ((SELECT COUNT(*)
            FROM questions_from_book q
           WHERE q.class_id = c.class_id
             AND q.book_id = c.book_id
             AND q.chapter_id = c.chapter_id
             AND BINARY q.question_type = BINARY 'long')
          + (SELECT COUNT(*)
               FROM questions q
              WHERE q.class_id = c.class_id
                AND q.book_id = c.book_id
                AND q.chapter_id = c.chapter_id
                AND BINARY q.question_type = BINARY 'long'
                AND NOT EXISTS (
                    SELECT 1
                      FROM questions_from_book qb
                     WHERE qb.class_id = q.class_id
                       AND qb.book_id = q.book_id
                       AND qb.chapter_id = q.chapter_id
                       AND BINARY qb.question_type = BINARY q.question_type
                       AND BINARY qb.question_text = BINARY q.question_text
                ))) AS long_count
    FROM chapter c
    INNER JOIN class cl ON cl.class_id = c.class_id
    INNER JOIN book b ON b.book_id = c.book_id AND b.class_id = c.class_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY cl.class_id ASC, b.book_name ASC, c.chapter_no ASC, c.chapter_id ASC";

$overviewRows = $hasBookSelection ? fetchOverviewRows($conn, $overviewSql, $types, $params) : [];
$totals = ['mcq' => 0, 'short' => 0, 'long' => 0];
foreach ($overviewRows as &$row) {
    $row['mcq_count'] = (int) $row['mcq_count'];
    $row['short_count'] = (int) $row['short_count'];
    $row['long_count'] = (int) $row['long_count'];
    $row['total_count'] = $row['mcq_count'] + $row['short_count'] + $row['long_count'];
    $totals['mcq'] += $row['mcq_count'];
    $totals['short'] += $row['short_count'];
    $totals['long'] += $row['long_count'];
}
unset($row);
$totalQuestions = $totals['mcq'] + $totals['short'] + $totals['long'];
$filterLabel = $hasBookSelection ? 'Filtered inventory' : 'Select class and book';
$emptyTitle = $hasBookSelection ? 'No questions found yet' : ($selectedClassId > 0 ? 'Select a book to view its inventory' : 'Select a class and book');
$emptyCopy = $hasBookSelection
    ? 'This selection has chapters, but no questions in the legacy or book-generated question tables.'
    : 'Choose a class first, then select one of its books. Chapter-wise totals will appear here.';
?>
<?php include __DIR__ . '/header.php'; ?>

<main class="admin-container book-overview-page">
    <section class="overview-hero">
        <div>
            <p class="eyebrow">Content intelligence / Book questions</p>
            <h1>Question inventory</h1>
            <p class="hero-copy">See exactly how much book-generated content is ready in every chapter, from MCQs to written questions.</p>
        </div>
        <button type="button" class="print-button" onclick="window.print()" aria-label="Print question inventory"><span aria-hidden="true">↗</span> Print report</button>
    </section>

    <section class="filter-panel" aria-labelledby="filter-heading">
        <div class="filter-heading">
            <div><p class="eyebrow">Narrow the report</p><h2 id="filter-heading">Browse by curriculum</h2></div>
            <?php if ($selectedClassId || $selectedBookId || $selectedChapterId): ?><a class="clear-filter" href="questions_overview.php">Reset filters</a><?php endif; ?>
        </div>
        <form class="filter-grid" method="get" action="questions_overview.php">
            <label><span>Class</span>
                <select name="class_id" id="class_id" onchange="this.form.submit()">
                    <option value="0">All classes</option>
                    <?php foreach ($classes as $class): ?><option value="<?= (int) $class['class_id'] ?>" <?= $selectedClassId === (int) $class['class_id'] ? 'selected' : '' ?>><?= overviewEscape($class['class_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><span>Book</span>
                <select name="book_id" id="book_id" <?= $selectedClassId > 0 ? '' : 'disabled' ?> onchange="this.form.submit()">
                    <option value="0"><?= $selectedClassId > 0 ? 'All books in this class' : 'Select a class first' ?></option>
                    <?php foreach ($books as $book): ?><option value="<?= (int) $book['book_id'] ?>" <?= $selectedBookId === (int) $book['book_id'] ? 'selected' : '' ?>><?= overviewEscape($book['book_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><span>Chapter</span>
                <select name="chapter_id" id="chapter_id" <?= $selectedBookId > 0 ? '' : 'disabled' ?> onchange="this.form.submit()">
                    <option value="0"><?= $selectedBookId > 0 ? 'All chapters in this book' : 'Select a book first' ?></option>
                    <?php foreach ($chapters as $chapter): ?><option value="<?= (int) $chapter['chapter_id'] ?>" <?= $selectedChapterId === (int) $chapter['chapter_id'] ? 'selected' : '' ?>><?= (int) $chapter['chapter_no'] ?> · <?= overviewEscape($chapter['chapter_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
        </form>
    </section>

    <section class="summary-strip" aria-label="Question totals">
        <div class="summary-intro"><span class="summary-kicker"><?= overviewEscape($filterLabel) ?></span><strong><?= number_format($totalQuestions) ?></strong><span class="summary-muted">combined questions</span></div>
        <div class="summary-stat mcq-stat"><span>MCQs</span><strong><?= number_format($totals['mcq']) ?></strong></div>
        <div class="summary-stat short-stat"><span>Short</span><strong><?= number_format($totals['short']) ?></strong></div>
        <div class="summary-stat long-stat"><span>Long</span><strong><?= number_format($totals['long']) ?></strong></div>
        <div class="summary-stat neutral-stat"><span>Chapters</span><strong><?= number_format(count($overviewRows)) ?></strong></div>
    </section>

    <section class="report-card" aria-labelledby="report-heading">
        <div class="report-card-header"><div><p class="eyebrow">Chapter breakdown</p><h2 id="report-heading">Book question coverage</h2></div><span class="result-count"><?= number_format(count($overviewRows)) ?> chapter<?= count($overviewRows) === 1 ? '' : 's' ?></span></div>
        <?php if ($overviewRows): ?>
            <div class="table-wrap"><table class="inventory-table">
                <thead><tr><th scope="col">Chapter</th><th scope="col">Book</th><th scope="col" class="numeric">MCQs</th><th scope="col" class="numeric">Short</th><th scope="col" class="numeric">Long</th><th scope="col" class="numeric">Total</th></tr></thead>
                <tbody>
                <?php foreach ($overviewRows as $row): ?><tr>
                    <td><span class="chapter-number">CH <?= str_pad((string) $row['chapter_no'], 2, '0', STR_PAD_LEFT) ?></span><strong><?= overviewEscape($row['chapter_name']) ?></strong></td>
                    <td><span class="book-name"><?= overviewEscape($row['book_name']) ?></span><small><?= overviewEscape($row['class_name']) ?></small></td>
                    <td class="numeric"><span class="count-pill mcq-pill"><?= number_format($row['mcq_count']) ?></span></td>
                    <td class="numeric"><span class="count-pill short-pill"><?= number_format($row['short_count']) ?></span></td>
                    <td class="numeric"><span class="count-pill long-pill"><?= number_format($row['long_count']) ?></span></td>
                    <td class="numeric total-cell"><?= number_format($row['total_count']) ?></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?><div class="empty-state"><div class="empty-icon" aria-hidden="true">⌁</div><h3><?= overviewEscape($emptyTitle) ?></h3><p><?= overviewEscape($emptyCopy) ?></p></div><?php endif; ?>
    </section>
</main>

<style>
.book-overview-page{--ink:#132238;--muted:#68768a;--line:#e5e9ef;--paper:#fbfcfe;--navy:#173b63;--teal:#168c83;--orange:#d87945;--violet:#7051a5;max-width:1280px;padding-top:18px;padding-bottom:56px;color:var(--ink)}
.overview-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:34px 38px 38px;border-radius:20px;color:#fff;background:radial-gradient(circle at 91% 18%,rgba(62,174,157,.34),transparent 27%),linear-gradient(120deg,#112b47 0%,#1d4367 56%,#24615f 100%);box-shadow:0 18px 40px rgba(18,46,75,.18);position:relative;overflow:hidden}.overview-hero:after{content:'';position:absolute;right:-42px;bottom:-96px;width:250px;height:250px;border:1px solid rgba(255,255,255,.2);border-radius:50%;box-shadow:0 0 0 24px rgba(255,255,255,.04),0 0 0 48px rgba(255,255,255,.03)}
.eyebrow{margin:0 0 8px;color:#6d859e;font:700 .72rem/1.1 'Trebuchet MS',sans-serif;letter-spacing:.14em;text-transform:uppercase}.overview-hero .eyebrow{color:#9bd4c7}.overview-hero h1,.filter-heading h2,.report-card-header h2{margin:0;font-family:Georgia,'Times New Roman',serif;letter-spacing:-.025em}.overview-hero h1{font-size:clamp(2rem,4vw,3.25rem);line-height:1}.hero-copy{max-width:580px;margin:16px 0 0;color:#cbd8e5;font-size:1rem}.print-button{position:relative;z-index:1;display:inline-flex;align-items:center;gap:9px;padding:11px 15px;border:1px solid rgba(255,255,255,.27);border-radius:10px;color:#fff;background:rgba(255,255,255,.09);font:700 .86rem 'Trebuchet MS',sans-serif;cursor:pointer}.print-button:hover{background:rgba(255,255,255,.18);transform:translateY(-1px)}
.filter-panel,.report-card{margin-top:22px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 8px 22px rgba(24,41,63,.045)}.filter-panel{padding:24px 26px 26px}.filter-heading,.report-card-header{display:flex;align-items:center;justify-content:space-between;gap:16px}.filter-heading h2,.report-card-header h2{font-size:1.55rem}.clear-filter{color:var(--navy);font-size:.86rem;font-weight:700;text-decoration:none}.filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px;margin-top:22px}.filter-grid label{display:grid;gap:8px;color:#536277;font-size:.82rem;font-weight:700}.filter-grid select{width:100%;min-height:46px;padding:0 13px;border:1px solid #d8dfe8;border-radius:9px;color:var(--ink);background:var(--paper);font:600 .95rem 'Trebuchet MS',sans-serif}.filter-grid select:focus{outline:3px solid rgba(22,140,131,.2);border-color:var(--teal)}.filter-grid select:disabled{color:#9aa7b6;cursor:not-allowed}
.summary-strip{display:grid;grid-template-columns:minmax(1.4fr,2fr) repeat(4,minmax(110px,1fr));gap:1px;margin-top:22px;overflow:hidden;border:1px solid var(--line);border-radius:16px;background:var(--line)}.summary-strip>div{min-height:100px;padding:21px 20px;background:#fff}.summary-intro{display:grid;align-content:center;gap:2px}.summary-kicker{color:var(--teal);font:700 .72rem 'Trebuchet MS',sans-serif;letter-spacing:.08em;text-transform:uppercase}.summary-intro strong{color:var(--ink);font:700 2rem/1.1 Georgia,serif}.summary-muted,.summary-stat span{color:var(--muted);font-size:.78rem}.summary-stat{display:grid;align-content:center;gap:6px;border-left:3px solid transparent}.summary-stat strong{font:700 1.6rem/1 Georgia,serif}.mcq-stat{border-left-color:var(--teal)!important}.mcq-stat strong{color:var(--teal)}.short-stat{border-left-color:var(--orange)!important}.short-stat strong{color:var(--orange)}.long-stat{border-left-color:var(--violet)!important}.long-stat strong{color:var(--violet)}.neutral-stat{border-left-color:#9aa7b6!important}.neutral-stat strong{color:var(--navy)}
.report-card{overflow:hidden}.report-card-header{padding:25px 26px 20px}.result-count{padding:7px 10px;border-radius:20px;color:#536277;background:#f0f3f7;font-size:.78rem;font-weight:700}.table-wrap{overflow-x:auto}.inventory-table{width:100%;min-width:760px;border-collapse:collapse}.inventory-table th{padding:13px 26px;border-top:1px solid var(--line);border-bottom:1px solid var(--line);color:#8190a1;background:#f9fafc;font:700 .7rem 'Trebuchet MS',sans-serif;letter-spacing:.1em;text-align:left;text-transform:uppercase}.inventory-table td{padding:17px 26px;border-bottom:1px solid #edf0f4;vertical-align:middle;color:var(--ink)}.inventory-table tbody tr:last-child td{border-bottom:0}.inventory-table tbody tr:hover{background:#fbfdfd}.inventory-table td:first-child{display:grid;gap:5px}.inventory-table td strong{font:700 1rem Georgia,serif}.chapter-number{color:var(--teal);font:700 .68rem 'Trebuchet MS',sans-serif;letter-spacing:.11em}.book-name{display:block;font-weight:700}.inventory-table small{display:block;margin-top:4px;color:var(--muted);font-size:.76rem}.numeric{text-align:right!important}.count-pill{display:inline-grid;min-width:38px;height:28px;padding:0 8px;place-items:center;border-radius:7px;font-weight:800;font-size:.8rem}.mcq-pill{color:#08766e;background:#def4f0}.short-pill{color:#a6501d;background:#fff0e7}.long-pill{color:#5e428e;background:#eee8fb}.total-cell{color:var(--navy)!important;font-weight:800}.empty-state{padding:60px 24px 66px;text-align:center;background:linear-gradient(180deg,#fff,#fbfcfe)}.empty-icon{display:grid;width:48px;height:48px;margin:0 auto 14px;place-items:center;border-radius:50%;color:var(--teal);background:#e1f5f1;font-size:1.8rem}.empty-state h3{margin:0;color:var(--ink);font:700 1.35rem Georgia,serif}.empty-state p{max-width:470px;margin:9px auto 0;color:var(--muted)}
@media(max-width:800px){.overview-hero{align-items:flex-start;flex-direction:column;padding:28px 24px 30px}.summary-strip{grid-template-columns:repeat(2,1fr)}.summary-intro{grid-column:span 2}.filter-grid{grid-template-columns:1fr}.filter-panel,.report-card-header{padding-left:20px;padding-right:20px}.inventory-table th,.inventory-table td{padding-left:17px;padding-right:17px}}@media print{.admin-navbar,.filter-panel,.print-button{display:none!important}.book-overview-page{max-width:100%;margin:0;padding:0}.overview-hero{color:#000;background:#fff;box-shadow:none;border-bottom:2px solid #000;border-radius:0;padding:0 0 18px}.overview-hero:after{display:none}.overview-hero .eyebrow,.hero-copy{color:#555}.summary-strip,.report-card{box-shadow:none}}
</style>
