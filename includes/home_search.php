<?php

/**
 * Homepage smart-search helpers. The ranking functions are deliberately kept
 * independent from HTTP handling so they can be reused and tested.
 */

function homeSearchDefaults(): array
{
    return [
        ['id' => 0, 'title' => 'Question Paper Generator', 'description' => 'Create printable papers for school, college and university.', 'url' => 'class-9th-and-10th-online-question-paper-generator', 'icon' => 'fas fa-file-alt', 'keywords' => 'question paper, paper generator, exam maker, create test, board paper', 'synonyms' => 'paper builder, test maker, exam creator', 'is_quick_link' => 1, 'is_fallback' => 1, 'sort_order' => 10],
        ['id' => 0, 'title' => 'Online MCQs Test', 'description' => 'Practise multiple-choice questions with instant results.', 'url' => 'online-mcqs-test-for-9th-and-10th-board-exams', 'icon' => 'fas fa-check-circle', 'keywords' => 'mcqs, quiz, online test, objective questions, practice', 'synonyms' => 'multiple choice questions, objective test, mock quiz', 'is_quick_link' => 1, 'is_fallback' => 1, 'sort_order' => 20],
        ['id' => 0, 'title' => 'Math & Class Notes', 'description' => 'Browse class notes and study material by subject.', 'url' => 'class-notes', 'icon' => 'fas fa-square-root-alt', 'keywords' => 'math notes, maths notes, study notes, class notes, physics notes, chemistry notes', 'synonyms' => 'mathematics notes, study material, revision notes', 'is_quick_link' => 1, 'is_fallback' => 1, 'sort_order' => 30],
        ['id' => 0, 'title' => 'Board Exam Test Series', 'description' => 'Prepare with chapter-wise tests and past-paper practice.', 'url' => 'class-9-10-11-12-test-series-for-board-exams', 'icon' => 'fas fa-clipboard-list', 'keywords' => 'past papers, test series, board exam, exam preparation', 'synonyms' => 'old papers, mock exams, practice papers', 'is_quick_link' => 1, 'is_fallback' => 1, 'sort_order' => 40],
        ['id' => 0, 'title' => 'Host a Live Quiz', 'description' => 'Create an interactive quiz room for your class.', 'url' => 'online-quiz-hosting', 'icon' => 'fas fa-broadcast-tower', 'keywords' => 'host quiz, live quiz, classroom game, quiz room', 'synonyms' => 'create live test, teacher quiz', 'is_quick_link' => 1, 'is_fallback' => 0, 'sort_order' => 50],
        ['id' => 0, 'title' => 'Study Materials', 'description' => 'Explore learning resources for board exam preparation.', 'url' => 'study-material-for-board-exam-preparations', 'icon' => 'fas fa-book-open', 'keywords' => 'study material, books, notes, learning resources', 'synonyms' => 'revision resources, educational material', 'is_quick_link' => 1, 'is_fallback' => 0, 'sort_order' => 60],
    ];
}

function homeSearchTextLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function homeSearchTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function homeSearchTextSlice(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}

function homeSearchSettings(mysqli $conn): array
{
    $settings = [
        'placeholder' => 'Search question papers, MCQs, notes and more...',
        'no_results_title' => 'We could not find an exact match',
        'no_results_message' => 'Try a shorter phrase, or choose one of these popular destinations.',
    ];
    try {
        $result = $conn->query("SELECT setting_key, setting_value FROM home_search_settings");
    } catch (Throwable $exception) {
        $result = false;
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
    }
    return $settings;
}

function homeSearchItems(mysqli $conn, bool $activeOnly = true): array
{
    $where = $activeOnly ? ' WHERE is_active = 1' : '';
    try {
        $result = $conn->query("SELECT id, title, description, url, icon, keywords, synonyms, is_quick_link, is_fallback, is_active, sort_order, created_at, updated_at FROM home_search_items{$where} ORDER BY sort_order ASC, title ASC");
    } catch (Throwable $exception) {
        $result = false;
    }
    if (!$result) {
        return $activeOnly ? homeSearchDefaults() : [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function homeSearchNormalize(string $value): string
{
    $value = homeSearchTextLower(trim($value));
    $value = str_replace(['&', '_', '-'], [' and ', ' ', ' '], $value);
    $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

function homeSearchLikePattern(string $value): string
{
    // Treat user-entered LIKE metacharacters as literal text. Prepared
    // statements stop SQL injection, but unescaped wildcards can still turn a
    // normal search into an unnecessarily broad and expensive scan.
    return '%' . addcslashes($value, "\\%_") . '%';
}

function homeSearchCanonicalToken(string $token): string
{
    $groups = [
        'paper' => ['paper', 'papers', 'exam', 'exams', 'assessment', 'worksheet'],
        'create' => ['create', 'make', 'maker', 'build', 'builder', 'generate', 'generator'],
        'mcq' => ['mcq', 'mcqs', 'quiz', 'quizzes', 'objective', 'choice'],
        'notes' => ['note', 'notes', 'material', 'materials', 'resource', 'resources', 'revision'],
        'math' => ['math', 'maths', 'mathematics', 'algebra', 'geometry'],
        'test' => ['test', 'tests', 'practice', 'practise', 'mock', 'preparation', 'prepare'],
        'live' => ['live', 'host', 'hosting', 'room', 'classroom'],
        'past' => ['past', 'old', 'previous', 'model'],
    ];
    foreach ($groups as $canonical => $members) {
        if (in_array($token, $members, true)) {
            return $canonical;
        }
    }
    return $token;
}

function homeSearchTokens(string $value): array
{
    $stopWords = ['a', 'an', 'and', 'for', 'in', 'of', 'on', 'the', 'to', 'with', 'i', 'want', 'need', 'find', 'show', 'me'];
    $tokens = preg_split('/\s+/u', homeSearchNormalize($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $tokens = array_values(array_filter($tokens, static fn(string $token): bool => !in_array($token, $stopWords, true)));
    return array_values(array_unique($tokens));
}

function homeSearchRank(array $items, string $query, int $limit = 6): array
{
    $normalizedQuery = homeSearchNormalize($query);
    if ($normalizedQuery === '') {
        return array_slice(array_values(array_filter($items, static fn(array $item): bool => !empty($item['is_quick_link']))), 0, $limit);
    }

    $queryTokens = homeSearchTokens($normalizedQuery);
    $ranked = [];
    foreach ($items as $item) {
        $title = homeSearchNormalize((string) ($item['title'] ?? ''));
        $keywords = homeSearchNormalize((string) ($item['keywords'] ?? ''));
        $synonyms = homeSearchNormalize((string) ($item['synonyms'] ?? ''));
        $document = trim($title . ' ' . $keywords . ' ' . $synonyms);
        $documentTokens = homeSearchTokens($document);
        $canonicalDocument = array_map('homeSearchCanonicalToken', $documentTokens);
        $score = 0;

        if ($normalizedQuery === $title) $score += 140;
        if (str_contains($title, $normalizedQuery)) $score += 85;
        elseif (str_contains($document, $normalizedQuery)) $score += 55;

        foreach ($queryTokens as $queryToken) {
            $canonical = homeSearchCanonicalToken($queryToken);
            if (in_array($queryToken, $documentTokens, true)) {
                $score += 24;
                continue;
            }
            if (in_array($canonical, $canonicalDocument, true)) {
                $score += 18;
                continue;
            }
            foreach ($documentTokens as $documentToken) {
                if (homeSearchTextLength($queryToken) >= 3 && (str_starts_with($documentToken, $queryToken) || str_starts_with($queryToken, $documentToken))) {
                    $score += 10;
                    break;
                }
                if (homeSearchTextLength($queryToken) >= 4 && levenshtein($queryToken, $documentToken) <= (homeSearchTextLength($queryToken) >= 7 ? 2 : 1)) {
                    $score += 7;
                    break;
                }
            }
        }

        $coverage = count($queryTokens) > 0 ? $score / count($queryTokens) : 0;
        if ($score >= 12 && $coverage >= 7) {
            $item['_score'] = $score;
            $ranked[] = $item;
        }
    }

    usort($ranked, static function (array $a, array $b): int {
        return ($b['_score'] <=> $a['_score']) ?: ((int) $a['sort_order'] <=> (int) $b['sort_order']);
    });
    return array_slice($ranked, 0, $limit);
}

function homeSearchPublicItem(array $item): array
{
    $url = (string) ($item['url'] ?? '');
    if ($url === '' || str_starts_with($url, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || !preg_match('~^/?[A-Za-z0-9][A-Za-z0-9_./?=&%+#-]*$~', $url)) {
        $url = '#';
    }
    return [
        'id' => (int) ($item['id'] ?? 0),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'url' => $url,
        'icon' => preg_match('/^[a-z0-9 -]+$/i', (string) ($item['icon'] ?? '')) ? (string) $item['icon'] : 'fas fa-search',
        'is_quick_link' => (int) ($item['is_quick_link'] ?? 0),
        'is_fallback' => (int) ($item['is_fallback'] ?? 0),
    ];
}

function homeSearchSlug(string $value): string
{
    $slug = homeSearchTextLower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    return trim(preg_replace('/-+/', '-', $slug) ?? '', '-');
}

function homeSearchClassLabel(array $row): string
{
    $className = trim((string) ($row['class_name'] ?? ''));
    if ($className === '') {
        return 'Class ' . (int) ($row['class_id'] ?? 0);
    }
    return preg_match('/class/i', $className) ? $className : 'Class ' . $className;
}

function homeSearchAction(string $label, string $url, string $icon): array
{
    return ['label' => $label, 'url' => $url, 'icon' => $icon];
}

function homeSearchContentResults(mysqli $conn, string $query, int $limit = 8): array
{
    $query = homeSearchTextSlice(trim($query), 120);
    if ($query === '' || homeSearchTextLength($query) < 2) {
        return [];
    }

    $like = homeSearchLikePattern($query);
    $results = [];

    try {
        $bookStmt = $conn->prepare(
            'SELECT b.book_id, b.class_id, b.book_name, c.class_name
             FROM book b
             LEFT JOIN class c ON c.class_id = b.class_id
             WHERE b.book_name LIKE ?
             ORDER BY b.book_name ASC
             LIMIT ?'
        );
        if ($bookStmt) {
            $bookStmt->bind_param('si', $like, $limit);
            $bookStmt->execute();
            $bookRows = $bookStmt->get_result();
            while ($book = $bookRows->fetch_assoc()) {
                $classId = (int) $book['class_id'];
                $bookName = trim((string) $book['book_name']);
                $bookSlug = homeSearchSlug($bookName);
                if ($classId <= 0 || $bookSlug === '') {
                    continue;
                }

                $classLabel = homeSearchClassLabel($book);
                $classQuery = http_build_query(['class' => $classId]);
                $paperQuery = http_build_query(['class_id' => $classId, 'book_name' => $bookName]);
                $subjectQuery = http_build_query(['class' => $classId, 'subject' => $bookName]);
                $results[] = [
                    'id' => 'book-' . (int) $book['book_id'],
                    'type' => 'book',
                    'title' => $bookName,
                    'description' => $classLabel . ' · Book',
                    'meta' => $classLabel . ' → ' . $bookName,
                    'icon' => 'fas fa-book',
                    'actions' => [
                        homeSearchAction('Generate Question Paper', 'select_chapters.php?' . $paperQuery, 'fas fa-file-alt'),
                        homeSearchAction('MCQs Test', 'class-' . $classId . '-' . $bookSlug . '-mcqs-test-2026', 'fas fa-check-circle'),
                        homeSearchAction('Notes', 'class-notes?' . $subjectQuery, 'fas fa-sticky-note'),
                        homeSearchAction('TextBook', 'notes/textbooks.php?' . $classQuery, 'fas fa-book-open'),
                    ],
                ];
            }
            $bookStmt->close();
        }

        $chapterStmt = $conn->prepare(
            'SELECT ch.chapter_id, ch.chapter_no, ch.chapter_name, ch.class_id,
                    ch.book_id, b.book_name, c.class_name
             FROM chapter ch
             INNER JOIN book b ON b.book_id = ch.book_id AND b.class_id = ch.class_id
             LEFT JOIN class c ON c.class_id = ch.class_id
             WHERE ch.chapter_name LIKE ?
             ORDER BY ch.chapter_name ASC, ch.chapter_id ASC
             LIMIT ?'
        );
        if ($chapterStmt) {
            $chapterStmt->bind_param('si', $like, $limit);
            $chapterStmt->execute();
            $chapterRows = $chapterStmt->get_result();
            while ($chapter = $chapterRows->fetch_assoc()) {
                $classId = (int) $chapter['class_id'];
                $bookName = trim((string) $chapter['book_name']);
                $chapterName = trim((string) $chapter['chapter_name']);
                $bookSlug = homeSearchSlug($bookName);
                $chapterSlug = homeSearchSlug($chapterName);
                if ($classId <= 0 || $bookSlug === '' || $chapterSlug === '' || $chapterName === '') {
                    continue;
                }

                $classLabel = homeSearchClassLabel($chapter);
                $chapterNo = (int) ($chapter['chapter_no'] ?? 0);
                $chapterMcqsPath = 'class-' . $classId . '-' . $bookSlug . '-' . ($chapterNo > 0 ? 'chapter-' . $chapterNo . '-' : '') . $chapterSlug . '-mcqs-with-explanations';
                $paperQuery = [
                    'class_id' => $classId,
                    'book_name' => $bookName,
                    'chapters_from_url' => $chapterNo > 0 ? (string) $chapterNo : $chapterName,
                ];
                $notesQuery = http_build_query([
                    'class' => $classId,
                    'subject' => $bookName,
                    'chapter' => $chapterName,
                ]);
                $testQuery = http_build_query(['chapter_ids' => (int) $chapter['chapter_id']]);
                $results[] = [
                    'id' => 'chapter-' . (int) $chapter['chapter_id'],
                    'type' => 'chapter',
                    'title' => $chapterName,
                    'description' => $classLabel . ' · ' . $bookName,
                    'meta' => $classLabel . ' → ' . $bookName . ' → ' . $chapterName,
                    'icon' => 'fas fa-layer-group',
                    'actions' => [
                        homeSearchAction('Generate Question Paper', 'select_question.php?' . http_build_query($paperQuery), 'fas fa-file-alt'),
                        homeSearchAction('MCQs Test', 'class-' . $classId . '-' . $bookSlug . '-mcqs-test-2026?' . $testQuery, 'fas fa-check-circle'),
                        homeSearchAction('MCQs Practice', $chapterMcqsPath, 'fas fa-brain'),
                        homeSearchAction('Notes', 'class-notes?' . $notesQuery, 'fas fa-sticky-note'),
                    ],
                ];
            }
            $chapterStmt->close();
        }
    } catch (Throwable $exception) {
        error_log('Home content search failed: ' . $exception->getMessage());
    }

    return array_slice($results, 0, $limit);
}

function homeSearchNoteResults(mysqli $conn, string $query, int $limit = 8): array
{
    $query = homeSearchTextSlice(trim($query), 120);
    if ($query === '' || homeSearchTextLength($query) < 2) {
        return [];
    }

    $results = [];
    $like = homeSearchLikePattern($query);
    try {
        $stmt = $conn->prepare(
            'SELECT id, title, subject, class, chapter
             FROM class_notes
             WHERE status = \'approved\' AND title LIKE ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?'
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $like, $limit);
        $stmt->execute();
        $noteRows = $stmt->get_result();
        while ($note = $noteRows->fetch_assoc()) {
            $noteId = (int) ($note['id'] ?? 0);
            $title = trim((string) ($note['title'] ?? ''));
            $class = trim((string) ($note['class'] ?? ''));
            $subject = trim((string) ($note['subject'] ?? ''));
            $chapter = trim((string) ($note['chapter'] ?? ''));
            if ($noteId <= 0 || $title === '' || $class === '') {
                continue;
            }

            $subjectSlug = homeSearchSlug($subject !== '' ? $subject : 'general');
            $titleSlug = homeSearchSlug($title);
            if ($subjectSlug === '') {
                $subjectSlug = 'general';
            }
            if ($titleSlug === '') {
                $titleSlug = 'study-notes';
            }
            $noteUrl = 'class-notes/class-' . rawurlencode($class)
                . '-' . $subjectSlug
                . '-' . $titleSlug
                . '-' . $noteId;
            $metaParts = ['Class ' . $class];
            if ($subject !== '') {
                $metaParts[] = $subject;
            }
            if ($chapter !== '') {
                $metaParts[] = $chapter;
            }

            $results[] = [
                'id' => 'note-' . $noteId,
                'type' => 'note',
                'title' => $title,
                'description' => 'Study Note · ' . implode(' · ', $metaParts),
                'meta' => implode(' → ', $metaParts),
                'url' => $noteUrl,
                'icon' => 'fas fa-sticky-note',
                'actions' => [],
            ];
        }
        $stmt->close();
    } catch (Throwable $exception) {
        error_log('Home note search failed: ' . $exception->getMessage());
    }

    return $results;
}

function homeSearchUploadedNoteResults(mysqli $conn, string $query, int $limit = 8): array
{
    $query = homeSearchTextSlice(trim($query), 120);
    if ($query === '' || homeSearchTextLength($query) < 2) {
        return [];
    }

    $results = [];
    $like = homeSearchLikePattern($query);
    try {
        $stmt = $conn->prepare(
            'SELECT n.note_id, n.title, n.class_id, n.book_id, n.chapter_id,
                    c.class_name, b.book_name, ch.chapter_name
             FROM uploaded_notes n
             LEFT JOIN class c ON c.class_id = n.class_id
             LEFT JOIN book b ON b.book_id = n.book_id
             LEFT JOIN chapter ch ON ch.chapter_id = n.chapter_id
             WHERE n.is_deleted = 0 AND n.drive_status = \'available\' AND n.title LIKE ?
             ORDER BY n.created_at DESC, n.note_id DESC
             LIMIT ?'
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $like, $limit);
        $stmt->execute();
        $noteRows = $stmt->get_result();
        while ($note = $noteRows->fetch_assoc()) {
            $noteId = (int) ($note['note_id'] ?? 0);
            $title = trim((string) ($note['title'] ?? ''));
            $classId = (int) ($note['class_id'] ?? 0);
            $bookId = (int) ($note['book_id'] ?? 0);
            $chapterId = (int) ($note['chapter_id'] ?? 0);
            if ($noteId <= 0 || $title === '' || $classId <= 0) {
                continue;
            }

            $metaParts = ['Class ' . ($note['class_name'] ?? $classId)];
            if (!empty($note['book_name'])) {
                $metaParts[] = (string) $note['book_name'];
            }
            if (!empty($note['chapter_name'])) {
                $metaParts[] = (string) $note['chapter_name'];
            }
            $noteQuery = http_build_query([
                'class_id' => $classId,
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'search' => $title,
            ]);
            $results[] = [
                'id' => 'uploaded-note-' . $noteId,
                'type' => 'note',
                'title' => $title,
                'description' => 'Study Material · ' . implode(' · ', $metaParts),
                'meta' => implode(' → ', $metaParts),
                'url' => 'notes/uploaded_notes.php?' . $noteQuery,
                'icon' => 'fas fa-file-alt',
                'actions' => [],
            ];
        }
        $stmt->close();
    } catch (Throwable $exception) {
        error_log('Uploaded note search failed: ' . $exception->getMessage());
    }

    return $results;
}

function homeSearchPublicResult(array $item): array
{
    $result = [
        'id' => (string) ($item['id'] ?? ''),
        'type' => in_array(($item['type'] ?? ''), ['book', 'chapter', 'note'], true) ? $item['type'] : 'result',
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'meta' => (string) ($item['meta'] ?? ''),
        'icon' => preg_match('/^[a-z0-9 -]+$/i', (string) ($item['icon'] ?? '')) ? (string) $item['icon'] : 'fas fa-search',
    ];
    if ($result['type'] === 'note') {
        $result['url'] = homeSearchPublicItem(['url' => $item['url'] ?? '#'])['url'];
    }
    $result['actions'] = [];
    foreach (($item['actions'] ?? []) as $action) {
        $publicAction = homeSearchPublicItem([
            'id' => 0,
            'title' => $action['label'] ?? '',
            'description' => '',
            'url' => $action['url'] ?? '#',
            'icon' => $action['icon'] ?? 'fas fa-arrow-right',
        ]);
        $result['actions'][] = [
            'label' => $publicAction['title'],
            'url' => $publicAction['url'],
            'icon' => $publicAction['icon'],
        ];
    }
    return $result;
}
