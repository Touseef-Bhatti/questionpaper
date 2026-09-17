<?php
require_once __DIR__ . '/BookQuestionDuplicateChecker.php';
require_once __DIR__ . '/BookChapterExtractor.php';
require_once __DIR__ . '/../../questionPaperFromTopic/GeminiClient.php';
require_once __DIR__ . '/../../questionPaperFromTopic/GeminiJsonExtractor.php';

class BookQuestionGenerator
{
    public const MAX_MCQ_BATCH = 10;
    public const MAX_SHORT_BATCH = 5;
    public const MAX_LONG_BATCH = 3;
    public const MAX_LONG_BATCH_SMALL = 2;
    public const LARGE_TEXT_THRESHOLD = 40000;
    public const MAX_REPLACEMENT_ATTEMPTS = 8;
    public const MAX_GEMINI_RETRIES = 3;
    public const MAX_MCQ_TOTAL = 200;
    public const MAX_SHORT_TOTAL = 100;
    public const MAX_LONG_TOTAL = 50;

    private mysqli $conn;
    private string $projectRoot;
    private string $uploadDir;
    private string $stateDir;
    private string $textDir;
    private array $tableColumnsCache = [];
    private BookChapterExtractor $extractor;

    public function __construct(mysqli $conn, ?string $projectRoot = null)
    {
        $this->conn = $conn;
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 2);
        $this->uploadDir = $this->projectRoot . '/storage/book_uploads';
        $this->stateDir = $this->projectRoot . '/storage/book_generation';
        $this->textDir = $this->projectRoot . '/storage/book_generation/text';
        $this->extractor = new BookChapterExtractor($this->projectRoot);

        foreach ([$this->uploadDir, $this->stateDir, $this->textDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
    }

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function validateClassBook(int $classId, int $bookId): array
    {
        $stmt = $this->conn->prepare('SELECT b.book_id, b.book_name, c.class_name FROM book b INNER JOIN class c ON c.class_id = b.class_id WHERE b.book_id = ? AND b.class_id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error while validating book.'];
        }
        $stmt->bind_param('ii', $bookId, $classId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'error' => 'Selected book does not belong to the selected class.'];
        }
        return ['ok' => true, 'book_name' => (string) $row['book_name'], 'class_name' => (string) $row['class_name']];
    }

    /**
     * @param array<int,array<string,mixed>> $chaptersInput
     * @return array{ok:bool,error?:string,chapters?:array<int,array<string,mixed>>}
     */
    public function normalizeChaptersInput(array $chaptersInput): array
    {
        $normalized = [];
        foreach ($chaptersInput as $idx => $chapter) {
            $chapterNo = intval($chapter['chapter_no'] ?? 0);
            $chapterName = trim((string) ($chapter['chapter_name'] ?? ''));
            $startPage = intval($chapter['start_page'] ?? 0);
            $endPage = intval($chapter['end_page'] ?? 0);
            $mcqCount = intval($chapter['mcq_count'] ?? 0);
            $shortCount = intval($chapter['short_count'] ?? 0);
            $longCount = intval($chapter['long_count'] ?? 0);

            if ($chapterNo <= 0) {
                return ['ok' => false, 'error' => 'Chapter number is required for row ' . ($idx + 1) . '.'];
            }
            if ($chapterName === '') {
                return ['ok' => false, 'error' => 'Chapter name is required for row ' . ($idx + 1) . '.'];
            }
            if ($startPage <= 0 || $endPage <= 0 || $endPage < $startPage) {
                return ['ok' => false, 'error' => 'Invalid page range for chapter ' . $chapterNo . '.'];
            }
            if ($mcqCount <= 0 && $shortCount <= 0 && $longCount <= 0) {
                return ['ok' => false, 'error' => 'Enter at least one question count for chapter ' . $chapterNo . '.'];
            }
            if ($mcqCount > self::MAX_MCQ_TOTAL || $shortCount > self::MAX_SHORT_TOTAL || $longCount > self::MAX_LONG_TOTAL) {
                return ['ok' => false, 'error' => 'Question counts exceed allowed limits for chapter ' . $chapterNo . '.'];
            }

            $normalized[] = [
                'chapter_no' => $chapterNo,
                'chapter_name' => $chapterName,
                'printed_start' => $startPage,
                'printed_end' => $endPage,
                'targets' => [
                    'mcq' => max(0, $mcqCount),
                    'short' => max(0, $shortCount),
                    'long' => max(0, $longCount),
                ],
            ];
        }

        if ($normalized === []) {
            return ['ok' => false, 'error' => 'Add at least one chapter row.'];
        }

        return ['ok' => true, 'chapters' => $normalized];
    }

    /**
     * @return array{ok:bool,error?:string,job_id?:string,state?:array<string,mixed>}
     */
    public function createJob(int $classId, int $bookId, int $pageOffset, string $storedPdfPath, string $originalName, int $pdfPageCount, array $chapters, string $mode, ?int $uploadId = null, bool $reviewMode = false): array
    {
        $validation = $this->validateClassBook($classId, $bookId);
        if (!$validation['ok']) {
            return $validation;
        }

        $preparedChapters = [];
        foreach ($chapters as $chapter) {
            $range = $this->extractor->validatePrintedPageRange(
                (int) $chapter['printed_start'],
                (int) $chapter['printed_end'],
                $pageOffset,
                $pdfPageCount
            );
            if (!$range['ok']) {
                return $range;
            }

            $preparedChapters[] = [
                'chapter_no' => (int) $chapter['chapter_no'],
                'chapter_name' => (string) $chapter['chapter_name'],
                'printed_start' => (int) $chapter['printed_start'],
                'printed_end' => (int) $chapter['printed_end'],
                'pdf_start' => (int) $range['pdf_start'],
                'pdf_end' => (int) $range['pdf_end'],
                'targets' => $chapter['targets'],
                'saved' => ['mcq' => 0, 'short' => 0, 'long' => 0],
                'skipped' => ['duplicates' => 0, 'invalid' => 0],
                'replacement_attempts' => 0,
                'completed_batches' => [],
                'failed_batches' => [],
                'chapter_id' => 0,
                'extracted_text_file' => '',
                'status' => 'pending',
                'current_type' => 'mcq',
                'current_batch' => 0,
            ];
        }

        $jobId = bin2hex(random_bytes(16));
        $state = [
            'job_id' => $jobId,
            'created_at' => date('c'),
            'admin_id' => (int) ($_SESSION['admin_id'] ?? 0),
            'class_id' => $classId,
            'book_id' => $bookId,
            'upload_id' => $uploadId,
            'class_name' => $validation['class_name'],
            'book_name' => $validation['book_name'],
            'page_offset' => $pageOffset,
            'pdf_page_count' => $pdfPageCount,
            'original_filename' => $originalName,
            'stored_pdf' => basename($storedPdfPath),
            'mode' => $mode === 'all' ? 'all' : 'one',
            'review_mode' => $reviewMode,
            'status' => 'ready',
            'cancelled' => false,
            'current_chapter_index' => 0,
            'chapters' => $preparedChapters,
            'logs' => [],
        ];
        $this->addLog($state, 'Uploaded PDF saved as ' . basename($storedPdfPath) . '.');
        $this->addLog($state, 'Generation job prepared for ' . count($preparedChapters) . ' chapter row(s).');

        if (!$this->saveState($jobId, $state)) {
            return ['ok' => false, 'error' => 'Could not save generation state.'];
        }

        $_SESSION['book_question_job_id'] = $jobId;

        return ['ok' => true, 'job_id' => $jobId, 'state' => $state];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadState(string $jobId): ?array
    {
        $path = $this->statePath($jobId);
        if (!is_readable($path)) {
            return null;
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $state = json_decode($json, true);
        return is_array($state) ? $state : null;
    }

    /**
     * @param array<string,mixed> $state
     */
    public function saveState(string $jobId, array $state): bool
    {
        $path = $this->statePath($jobId);
        $tmp = $path . '.tmp';
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return false;
        }

        $fp = @fopen($tmp, 'cb');
        if (!$fp) {
            return false;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        ftruncate($fp, 0);
        fwrite($fp, $encoded);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return @rename($tmp, $path);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function addLog(array &$state, string $message, string $level = 'info'): void
    {
        if (!isset($state['logs']) || !is_array($state['logs'])) {
            $state['logs'] = [];
        }
        $state['logs'][] = [
            'time' => date('H:i:s'),
            'level' => in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info',
            'message' => $message,
        ];
        if (count($state['logs']) > 120) {
            $state['logs'] = array_slice($state['logs'], -120);
        }
    }

    private function statePath(string $jobId): string
    {
        $safe = preg_replace('/[^a-f0-9]/', '', strtolower($jobId));
        return $this->stateDir . '/' . $safe . '.json';
    }

    public function pdfPathFromState(array $state): string
    {
        return $this->uploadDir . '/' . basename((string) ($state['stored_pdf'] ?? ''));
    }

    /**
     * @return array{ok:bool,error?:string,chapter_id?:int}
     */
    public function resolveOrCreateChapter(int $classId, int $bookId, string $bookName, int $chapterNo, string $chapterName): array
    {
        $stmt = $this->conn->prepare('SELECT chapter_id FROM chapter WHERE class_id = ? AND book_id = ? AND chapter_no = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error while checking chapter.'];
        }
        $stmt->bind_param('iii', $classId, $bookId, $chapterNo);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            return ['ok' => true, 'chapter_id' => (int) $row['chapter_id']];
        }

        $stmt = $this->conn->prepare('INSERT INTO chapter (chapter_name, chapter_no, class_id, book_id, book_name) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error while creating chapter.'];
        }
        $stmt->bind_param('siiis', $chapterName, $chapterNo, $classId, $bookId, $bookName);
        if (!$stmt->execute()) {
            error_log('Chapter insert failed: ' . $stmt->error);
            $stmt->close();
            return ['ok' => false, 'error' => 'Could not create chapter record.'];
        }
        $chapterId = (int) $stmt->insert_id;
        $stmt->close();

        return ['ok' => true, 'chapter_id' => $chapterId];
    }

    /**
     * @return array{ok:bool,error?:string,done?:bool,progress?:array<string,mixed>,state?:array<string,mixed>}
     */
    public function processNextBatch(string $jobId): array
    {
        $state = $this->loadState($jobId);
        if (!$state) {
            return ['ok' => false, 'error' => 'Generation job not found.'];
        }
        if (!empty($state['cancelled'])) {
            return ['ok' => false, 'error' => 'Generation was cancelled.', 'state' => $state];
        }

        $apiKey = trim((string) EnvLoader::get('GEMINIAPIKEYFORBOOKQUESTIONS', ''));
        if ($apiKey === '') {
            $this->addLog($state, 'Gemini API key is not configured.', 'error');
            $this->saveState($jobId, $state);
            return ['ok' => false, 'error' => 'Gemini API key is not configured. Contact the administrator.'];
        }
        $model = trim((string) EnvLoader::get('GEMINIMODELFORBOOKQUESTIONS', EnvLoader::get('GEMINIMODEL', 'gemini-2.5-flash')));

        $chapterIndex = (int) ($state['current_chapter_index'] ?? 0);
        if (!isset($state['chapters'][$chapterIndex])) {
            $state['status'] = 'completed';
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => true, 'progress' => $this->buildProgress($state), 'state' => $state];
        }

        $chapter = &$state['chapters'][$chapterIndex];
        if (($chapter['status'] ?? '') === 'done') {
            $state['current_chapter_index'] = $chapterIndex + 1;
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state];
        }

        if (($chapter['status'] ?? 'pending') === 'pending') {
            $this->addLog($state, 'Preparing chapter ' . (int) $chapter['chapter_no'] . ': ' . (string) $chapter['chapter_name'] . '.');
            $chapterResolved = $this->resolveOrCreateChapter(
                (int) $state['class_id'],
                (int) $state['book_id'],
                (string) $state['book_name'],
                (int) $chapter['chapter_no'],
                (string) $chapter['chapter_name']
            );
            if (!$chapterResolved['ok']) {
                $chapter['status'] = 'failed';
                $chapter['error'] = $chapterResolved['error'] ?? 'Chapter error';
                $this->addLog($state, $chapter['error'], 'error');
                $this->saveState($jobId, $state);
                return ['ok' => false, 'error' => $chapter['error'], 'state' => $state];
            }
            $chapter['chapter_id'] = (int) $chapterResolved['chapter_id'];
            $this->addLog($state, 'Chapter database record ready. ID: ' . (int) $chapter['chapter_id'] . '.');

            $workDir = $this->textDir . '/' . preg_replace('/[^a-f0-9]/', '', $jobId) . '_ch' . (int) $chapter['chapter_no'];
            $this->addLog($state, 'Extracting chapter text from PDF pages ' . (int) $chapter['pdf_start'] . '-' . (int) $chapter['pdf_end'] . '.');
            $extract = $this->extractor->extractChapterMarkdown(
                $this->pdfPathFromState($state),
                (int) $chapter['pdf_start'],
                (int) $chapter['pdf_end'],
                $workDir
            );
            if (!$extract['ok']) {
                $chapter['status'] = 'failed';
                $chapter['error'] = $extract['error'] ?? 'Extraction failed';
                $this->addLog($state, $chapter['error'], 'error');
                $this->saveState($jobId, $state);
                return ['ok' => false, 'error' => $chapter['error'], 'state' => $state];
            }

            $textFile = $workDir . '/extracted.md';
            @file_put_contents($textFile, (string) $extract['text']);
            $chapter['extracted_text_file'] = $textFile;
            $chapter['status'] = 'generating';
            $chapter['current_type'] = $this->nextPendingType($chapter);
            $chapter['current_batch'] = 0;
            $this->addLog($state, 'Extracted ' . mb_strlen((string) $extract['text']) . ' characters for chapter ' . (int) $chapter['chapter_no'] . '.');
            $this->saveState($jobId, $state);
        }

        $type = (string) ($chapter['current_type'] ?? 'mcq');
        if ($type === '') {
            $chapter['status'] = 'done';
            $state['current_chapter_index'] = $chapterIndex + 1;
            $this->addLog($state, 'Chapter ' . (int) $chapter['chapter_no'] . ' has no pending question types.');
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state];
        }

        $remaining = $this->remainingForType($chapter, $type);
        if ($remaining <= 0) {
            $nextType = $this->nextPendingType($chapter);
            if ($nextType === '') {
                $chapter['status'] = 'done';
                if (($state['mode'] ?? 'one') === 'one') {
                    $state['status'] = 'completed';
                    $this->addLog($state, 'Generation completed.');
                    $this->saveState($jobId, $state);
                    return ['ok' => true, 'done' => true, 'progress' => $this->buildProgress($state), 'state' => $state];
                }
                $state['current_chapter_index'] = $chapterIndex + 1;
                $this->addLog($state, 'Chapter ' . (int) $chapter['chapter_no'] . ' completed.');
                $this->saveState($jobId, $state);
                return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state];
            }
            $chapter['current_type'] = $nextType;
            $chapter['current_batch'] = 0;
            $this->addLog($state, 'Moving to ' . $nextType . ' questions for chapter ' . (int) $chapter['chapter_no'] . '.');
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state];
        }

        $batchSize = $this->batchSizeForType($type, (string) @file_get_contents((string) ($chapter['extracted_text_file'] ?? '')));
        $batchSize = min($batchSize, $remaining);
        $batchNum = (int) ($chapter['current_batch'] ?? 0);
        $batchKey = $type . ':' . $batchNum;
        
        // Stop after 5 batches per type to prevent infinite loops
        if ($batchNum >= 5) {
            $nextType = $this->nextPendingType($chapter);
            if ($nextType === '') {
                $chapter['status'] = 'done';
                if (($state['mode'] ?? 'one') === 'one') {
                    $state['status'] = 'completed';
                    $this->addLog($state, 'Generation completed after reaching batch safety limit.', 'warn');
                } else {
                    $state['current_chapter_index'] = $chapterIndex + 1;
                    $this->addLog($state, 'Chapter ' . (int) $chapter['chapter_no'] . ' moved on after reaching batch safety limit.', 'warn');
                }
            } else {
                $chapter['current_type'] = $nextType;
                $chapter['current_batch'] = 0;
                $this->addLog($state, 'Moving to ' . $nextType . ' questions after batch safety limit.', 'warn');
            }
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state];
        }

        if (in_array($batchKey, $chapter['completed_batches'] ?? [], true)) {
            $chapter['current_batch'] = $batchNum + 1;
            $this->addLog($state, 'Skipped already completed ' . $type . ' batch ' . ($batchNum + 1) . '.');
            $this->saveState($jobId, $state);
            return ['ok' => true, 'done' => false, 'progress' => $this->buildProgress($state), 'state' => $state, 'skipped_duplicate_batch' => true];
        }

        $chapterText = (string) @file_get_contents((string) ($chapter['extracted_text_file'] ?? ''));
        $existingTexts = BookQuestionDuplicateChecker::loadExistingQuestionTexts($this->conn, (int) $chapter['chapter_id']);
        $sessionTexts = $chapter['generated_texts'] ?? [];
        if (is_array($sessionTexts)) {
            $existingTexts = array_merge($existingTexts, $sessionTexts);
        }

        $this->addLog($state, 'Requesting ' . $batchSize . ' ' . $type . ' question(s), batch ' . ($batchNum + 1) . '.');
        $batchResult = $this->generateBatch(
            $apiKey,
            $model,
            $state,
            $chapter,
            $type,
            $batchSize,
            $chapterText,
            $existingTexts
        );

        if (!$batchResult['ok']) {
            $chapter['failed_batches'][$batchKey] = $batchResult['error'] ?? 'Batch failed';
            $chapter['status'] = 'failed';
            $chapter['error'] = $batchResult['error'] ?? 'Batch generation failed.';
            $this->addLog($state, $chapter['error'], 'error');
            $this->saveState($jobId, $state);
            return ['ok' => false, 'error' => $batchResult['error'] ?? 'Batch generation failed.', 'state' => $state];
        }

        $savedCount = $this->saveBatchItems($state, $chapter, $type, $batchResult['items'], $existingTexts);
        if (!empty($savedCount['error'])) {
            $chapter['failed_batches'][$batchKey] = $savedCount['error'];
            $chapter['status'] = 'failed';
            $chapter['error'] = $savedCount['error'];
            $this->addLog($state, $savedCount['error'], 'error');
            $this->saveState($jobId, $state);
            return ['ok' => false, 'error' => $savedCount['error'], 'state' => $state];
        }
        $chapter['completed_batches'][] = $batchKey;
        unset($chapter['failed_batches'][$batchKey]);
        $chapter['current_batch'] = $batchNum + 1;
        $this->addLog(
            $state,
            'Saved ' . $savedCount['saved'] . ' ' . $type . ' question(s). Duplicates skipped: ' . $savedCount['duplicates'] . ', invalid skipped: ' . $savedCount['invalid'] . '.'
        );

        if ($savedCount['saved'] === 0 && $remaining > 0) {
            $chapter['replacement_attempts'] = (int) ($chapter['replacement_attempts'] ?? 0) + 1;
            if ($chapter['replacement_attempts'] >= self::MAX_REPLACEMENT_ATTEMPTS) {
                $chapter['status'] = 'done';
                $chapter['warning'] = 'Stopped after replacement limit. Saved ' . $this->savedSummary($chapter) . '.';
                $this->addLog($state, $chapter['warning'], 'warn');
                if (($state['mode'] ?? 'one') === 'one') {
                    $state['status'] = 'completed';
                } else {
                    $state['current_chapter_index'] = $chapterIndex + 1;
                }
            }
        }

        if ($this->remainingForType($chapter, $type) <= 0) {
            $nextType = $this->nextPendingType($chapter);
            $chapter['current_type'] = $nextType;
            $chapter['current_batch'] = 0;
            if ($nextType === '') {
                $chapter['status'] = 'done';
                if (($state['mode'] ?? 'one') === 'one') {
                    $state['status'] = 'completed';
                    $this->addLog($state, 'Generation completed.');
                } else {
                    $state['current_chapter_index'] = $chapterIndex + 1;
                    $this->addLog($state, 'Chapter ' . (int) $chapter['chapter_no'] . ' completed.');
                }
            }
        }

        $this->saveState($jobId, $state);

        return [
            'ok' => true,
            'done' => ($state['status'] ?? '') === 'completed' || $state['current_chapter_index'] >= count($state['chapters']),
            'progress' => $this->buildProgress($state),
            'state' => $state,
            'batch' => [
                'type' => $type,
                'requested' => $batchSize,
                'saved' => $savedCount['saved'],
                'duplicates' => $savedCount['duplicates'],
                'invalid' => $savedCount['invalid'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $chapter
     */
    private function remainingForType(array $chapter, string $type): int
    {
        $targets = $chapter['targets'] ?? [];
        $saved = $chapter['saved'] ?? [];
        $target = (int) ($targets[$type] ?? 0);
        $have = (int) ($saved[$type] ?? 0);
        return max(0, $target - $have);
    }

    /**
     * @param array<string,mixed> $chapter
     */
    private function nextPendingType(array $chapter): string
    {
        foreach (['mcq', 'short', 'long'] as $type) {
            if ($this->remainingForType($chapter, $type) > 0) {
                return $type;
            }
        }
        return '';
    }

    private function batchSizeForType(string $type, string $chapterText): int
    {
        if ($type === 'mcq') {
            return self::MAX_MCQ_BATCH;
        }
        if ($type === 'short') {
            return self::MAX_SHORT_BATCH;
        }
        $large = mb_strlen($chapterText) >= self::LARGE_TEXT_THRESHOLD;
        return $large ? self::MAX_LONG_BATCH_SMALL : self::MAX_LONG_BATCH;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param string[] $existingTexts
     * @return array{ok:bool,error?:string,items?:array<int,array<string,mixed>>}
     */
    private function generateBatch(
        string $apiKey,
        string $model,
        array $state,
        array $chapter,
        string $type,
        int $batchSize,
        string $chapterText,
        array $existingTexts
    ): array {
        $prompt = $this->buildPrompt($state, $chapter, $type, $batchSize, $chapterText, $existingTexts);
        $parts = [['text' => $prompt]];

        $maxTokens = $type === 'long' ? 8192 : ($type === 'short' ? 4096 : 6144);
        $attempt = 0;
        $lastError = 'AI request failed.';

        while ($attempt < self::MAX_GEMINI_RETRIES) {
            $attempt++;
            $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, 180, true);
            if (empty($gen['ok']) && $this->shouldRetryWithoutJsonMode($gen)) {
                $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, 180, false);
            }
            if (empty($gen['ok'])) {
                $lastError = $this->publicGeminiError($gen['error'] ?? 'AI request failed.');
                if ($this->isRetryableGeminiError($gen)) {
                    usleep(500000);
                    continue;
                }
                break;
            }

            $parsed = GeminiJsonExtractor::parseObject((string) ($gen['text'] ?? ''));
            if (!is_array($parsed)) {
                $lastError = 'AI response could not be parsed as JSON.';
                continue;
            }

            $items = $this->extractItemsFromParsed($parsed, $type);
            if ($items === []) {
                $lastError = 'AI returned no valid questions for this batch.';
                continue;
            }

            return ['ok' => true, 'items' => $items];
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param string[] $existingTexts
     */
    private function buildPrompt(array $state, array $chapter, string $type, int $batchSize, string $chapterText, array $existingTexts): string
    {
        $typeLabel = $type === 'mcq' ? 'MCQ' : ($type === 'short' ? 'short question' : 'long question');
        $topicName = json_encode((string) ($chapter['chapter_name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines = [];
        $lines[] = 'You are generating exam questions for a textbook chapter.';
        $lines[] = 'Class: ' . ($state['class_name'] ?? '');
        $lines[] = 'Book: ' . ($state['book_name'] ?? '');
        $lines[] = 'Chapter number: ' . ($chapter['chapter_no'] ?? '');
        $lines[] = 'Chapter name: ' . ($chapter['chapter_name'] ?? '');
        $lines[] = 'Question type: ' . $typeLabel;
        $lines[] = 'Number of questions required in this batch: ' . $batchSize;
        $lines[] = '';
        $lines[] = 'IMPORTANT RULES:';
        $lines[] = '1. FIRST, extract ALL existing labeled questions (MCQs, short questions, long questions) from the chapter that are labeled as such, even if there are more than requested.';
        $lines[] = '2. THEN, generate NEW original questions from the chapter content until you reach at least the total requested number.';
        $lines[] = '3. Use only the supplied chapter content - NO outside knowledge.';
        $lines[] = '4. Do not invent facts, definitions, examples, names, figures, or terminology.';
        $lines[] = '5. Every answer must be directly supported by the supplied text.';
        $lines[] = '6. Do not repeat or closely rephrase any previously generated question.';
        $lines[] = '7. Do not generate questions from another chapter, table of contents, preface, glossary, index, or answer key.';
        $lines[] = '8. Use clear and student-friendly wording suitable for the selected class.';
        $lines[] = '9. Return valid JSON only - no markdown code fences.';
        $lines[] = '';
        $lines[] = '=== CHAPTER CONTENT START ===';
        $lines[] = $chapterText;
        $lines[] = '=== CHAPTER CONTENT END ===';

        if (!empty($existingTexts)) {
            $sample = array_slice(array_values(array_unique($existingTexts)), 0, 80);
            $lines[] = '';
            $lines[] = 'Previously generated questions that must NOT be repeated or closely rephrased:';
            foreach ($sample as $q) {
                $lines[] = '- ' . $q;
            }
        }

        if ($type === 'mcq') {
            $lines[] = '';
            $lines[] = 'Return JSON exactly like: {"questions":[{"topic":' . $topicName . ',"question":"...","option_a":"...","option_b":"...","option_c":"...","option_d":"...","correct_option":"A|B|C|D","difficulty_level":"Easy|Medium|Hard"}]}';
        } elseif ($type === 'short') {
            $lines[] = '';
            $lines[] = 'Return JSON exactly like: {"questions":[{"topic":' . $topicName . ',"question":"..."}]}';
        } else {
            $lines[] = '';
            $lines[] = 'Return JSON exactly like: {"questions":[{"topic":' . $topicName . ',"question":"..."}]}';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $parsed
     * @return array<int,array<string,mixed>>
     */
    private function extractItemsFromParsed(array $parsed, string $type): array
    {
        $block = $parsed['questions'] ?? ($parsed[$type . 's'] ?? ($parsed[$type] ?? null));
        if (!is_array($block)) {
            return [];
        }
        if (isset($block['question']) || isset($block['question_text'])) {
            $block = [$block];
        }

        $items = [];
        foreach ($block as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['question']) && isset($row['question_text'])) {
                $row['question'] = $row['question_text'];
            }
            $items[] = $row;
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param array<int,array<string,mixed>> $items
     * @param string[] $existingTexts
     * @return array{saved:int,duplicates:int,invalid:int}
     */
    private function saveBatchItems(array $state, array &$chapter, string $type, array $items, array $existingTexts): array
    {
        $saved = 0;
        $duplicates = 0;
        $invalid = 0;

        $this->conn->begin_transaction();
        try {
            foreach ($items as $item) {
                $questionText = trim((string) ($item['question'] ?? ''));
                if ($questionText === '') {
                    $invalid++;
                    $chapter['skipped']['invalid'] = (int) ($chapter['skipped']['invalid'] ?? 0) + 1;
                    continue;
                }

                if (BookQuestionDuplicateChecker::isDuplicate($questionText, $existingTexts)) {
                    $duplicates++;
                    $chapter['skipped']['duplicates'] = (int) ($chapter['skipped']['duplicates'] ?? 0) + 1;
                    continue;
                }

                if ($type === 'mcq') {
                    $valid = $this->validateMcqItem($item);
                    if (!$valid['ok']) {
                        $invalid++;
                        $chapter['skipped']['invalid'] = (int) ($chapter['skipped']['invalid'] ?? 0) + 1;
                        continue;
                    }
                    if (!$this->insertMcq($state, $chapter, $valid['item'])) {
                        throw new RuntimeException('MCQ insert failed');
                    }
                } else {
                    if (!$this->insertQuestion($state, $chapter, $type, $questionText)) {
                        throw new RuntimeException('Question insert failed');
                    }
                }

                $existingTexts[] = $questionText;
                if (!isset($chapter['generated_texts']) || !is_array($chapter['generated_texts'])) {
                    $chapter['generated_texts'] = [];
                }
                $chapter['generated_texts'][] = $questionText;
                $chapter['saved'][$type] = (int) ($chapter['saved'][$type] ?? 0) + 1;
                $saved++;
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('BookQuestionGenerator batch save failed: ' . $e->getMessage());
            return ['saved' => $saved, 'duplicates' => $duplicates, 'invalid' => $invalid, 'error' => 'Database error while saving generated questions.'];
        }

        return ['saved' => $saved, 'duplicates' => $duplicates, 'invalid' => $invalid];
    }

    /**
     * @param array<string,mixed> $item
     * @return array{ok:bool,item?:array<string,mixed>}
     */
    private function validateMcqItem(array $item): array
    {
        $question = trim((string) ($item['question'] ?? ''));
        $a = trim((string) ($item['option_a'] ?? ''));
        $b = trim((string) ($item['option_b'] ?? ''));
        $c = trim((string) ($item['option_c'] ?? ''));
        $d = trim((string) ($item['option_d'] ?? ''));
        $letter = strtoupper(trim((string) ($item['correct_option'] ?? '')));
        if ($letter === 'OPTION_A') {
            $letter = 'A';
        } elseif ($letter === 'OPTION_B') {
            $letter = 'B';
        } elseif ($letter === 'OPTION_C') {
            $letter = 'C';
        } elseif ($letter === 'OPTION_D') {
            $letter = 'D';
        }

        if ($question === '' || $a === '' || $b === '' || $c === '' || $d === '') {
            return ['ok' => false];
        }

        $options = [$a, $b, $c, $d];
        $normalizedOptions = array_map([BookQuestionDuplicateChecker::class, 'normalize'], $options);
        if (count(array_unique($normalizedOptions)) < 4) {
            return ['ok' => false];
        }

        if (!in_array($letter, ['A', 'B', 'C', 'D'], true)) {
            return ['ok' => false];
        }

        $correctText = $a;
        if ($letter === 'B') {
            $correctText = $b;
        } elseif ($letter === 'C') {
            $correctText = $c;
        } elseif ($letter === 'D') {
            $correctText = $d;
        }

        $qNorm = BookQuestionDuplicateChecker::normalize($question);
        $ansNorm = BookQuestionDuplicateChecker::normalize($correctText);
        if ($ansNorm !== '' && ($qNorm === $ansNorm || strpos($qNorm, $ansNorm) !== false)) {
            return ['ok' => false];
        }

        $difficulty = trim((string) ($item['difficulty_level'] ?? 'Medium'));
        if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) {
            $difficulty = 'Medium';
        }

        return [
            'ok' => true,
            'item' => [
                'question' => $question,
                'option_a' => $a,
                'option_b' => $b,
                'option_c' => $c,
                'option_d' => $d,
                'correct_option' => $letter,
                'difficulty_level' => $difficulty,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param array<string,mixed> $item
     */
    private function insertMcq(array $state, array $chapter, array $item): bool
    {
        if (!empty($state['review_mode'])) {
            return $this->insertDraftQuestion($state, $chapter, 'mcq', (string) $item['question'], $item);
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO mcqs_from_book (class_id, book_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_option, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $classId = (int) $state['class_id'];
        $bookId = (int) $state['book_id'];
        $chapterId = (int) $chapter['chapter_id'];
        $topic = (string) $chapter['chapter_name'];
        $stmt->bind_param(
            'iiissssssss',
            $classId,
            $bookId,
            $chapterId,
            $topic,
            $item['question'],
            $item['option_a'],
            $item['option_b'],
            $item['option_c'],
            $item['option_d'],
            $item['correct_option'],
            $item['difficulty_level']
        );
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        $this->insertMainMcq($state, $chapter, $item);
        return true;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     */
    private function insertQuestion(array $state, array $chapter, string $type, string $questionText): bool
    {
        if (!empty($state['review_mode'])) {
            return $this->insertDraftQuestion($state, $chapter, $type, $questionText, []);
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO questions_from_book (class_id, book_id, chapter_id, question_type, question_text, topic, book_name) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $classId = (int) $state['class_id'];
        $bookId = (int) $state['book_id'];
        $chapterId = (int) $chapter['chapter_id'];
        $topic = (string) $chapter['chapter_name'];
        $bookName = (string) $state['book_name'];
        $stmt->bind_param('iiissss', $classId, $bookId, $chapterId, $type, $questionText, $topic, $bookName);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        $this->insertMainQuestion($state, $chapter, $type, $questionText);
        return true;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param array<string,mixed> $item
     */
    private function insertDraftQuestion(array $state, array $chapter, string $type, string $questionText, array $item): bool
    {
        if ($type === 'mcq') {
            $stmt = $this->conn->prepare(
                "INSERT INTO book_mcq_drafts
                    (job_id, upload_id, class_id, book_id, chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty_level, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO book_question_drafts
                    (job_id, upload_id, class_id, book_id, chapter_id, question_kind, question_text, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
        }
        if (!$stmt) {
            error_log('BookQuestionGenerator draft prepare failed: ' . $this->conn->error);
            return false;
        }

        $jobId = (string) ($state['job_id'] ?? '');
        $uploadId = (int) ($state['upload_id'] ?? 0);
        $classId = (int) $state['class_id'];
        $bookId = (int) $state['book_id'];
        $chapterId = (int) $chapter['chapter_id'];
        $optionA = (string) ($item['option_a'] ?? '');
        $optionB = (string) ($item['option_b'] ?? '');
        $optionC = (string) ($item['option_c'] ?? '');
        $optionD = (string) ($item['option_d'] ?? '');
        $correctOption = (string) ($item['correct_option'] ?? '');
        $difficulty = (string) ($item['difficulty_level'] ?? 'Medium');

        if ($type === 'mcq') {
            $stmt->bind_param(
                'siiiisssssss',
                $jobId,
                $uploadId,
                $classId,
                $bookId,
                $chapterId,
                $questionText,
                $optionA,
                $optionB,
                $optionC,
                $optionD,
                $correctOption,
                $difficulty
            );
        } else {
            $stmt->bind_param(
                'siiiiss',
                $jobId,
                $uploadId,
                $classId,
                $bookId,
                $chapterId,
                $type,
                $questionText
            );
        }
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('BookQuestionGenerator draft insert failed: ' . $stmt->error);
        }
        $stmt->close();

        return $ok;
    }

    /**
     * @return array<string,bool>
     */
    private function tableColumns(string $table): array
    {
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($safeTable === '') {
            return [];
        }
        if (isset($this->tableColumnsCache[$safeTable])) {
            return $this->tableColumnsCache[$safeTable];
        }

        $columns = [];
        $res = $this->conn->query("SHOW COLUMNS FROM `$safeTable`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $columns[$row['Field']] = true;
                }
            }
        }

        $this->tableColumnsCache[$safeTable] = $columns;
        return $columns;
    }

    private function hasColumn(array $columns, string $name): bool
    {
        return isset($columns[$name]);
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     * @param array<string,mixed> $item
     */
    private function insertMainMcq(array $state, array $chapter, array $item): bool
    {
        $columns = $this->tableColumns('mcqs');
        foreach (['class_id', 'book_id', 'chapter_id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'] as $required) {
            if (!$this->hasColumn($columns, $required)) {
                return true;
            }
        }

        $chapterId = (int) $chapter['chapter_id'];
        $question = (string) $item['question'];

        $dup = $this->conn->prepare('SELECT mcq_id FROM mcqs WHERE chapter_id = ? AND question = ? LIMIT 1');
        if ($dup) {
            $dup->bind_param('is', $chapterId, $question);
            $dup->execute();
            $res = $dup->get_result();
            $exists = $res && $res->num_rows > 0;
            $dup->close();
            if ($exists) {
                return true;
            }
        }

        $topic = (string) ($chapter['chapter_name'] ?? '');
        $data = [
            'class_id' => (int) $state['class_id'],
            'book_id' => (int) $state['book_id'],
            'chapter_id' => $chapterId,
            'topic' => $topic,
            'question' => $question,
            'option_a' => (string) $item['option_a'],
            'option_b' => (string) $item['option_b'],
            'option_c' => (string) $item['option_c'],
            'option_d' => (string) $item['option_d'],
            'correct_option' => (string) $item['correct_option'],
            'difficulty_level' => (string) ($item['difficulty_level'] ?? 'Medium'),
            'explanation' => '',
        ];
        $typesByColumn = [
            'class_id' => 'i',
            'book_id' => 'i',
            'chapter_id' => 'i',
            'topic' => 's',
            'question' => 's',
            'option_a' => 's',
            'option_b' => 's',
            'option_c' => 's',
            'option_d' => 's',
            'correct_option' => 's',
            'difficulty_level' => 's',
            'explanation' => 's',
        ];

        return $this->insertAssociativeRow('mcqs', $columns, $data, $typesByColumn);
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $chapter
     */
    private function insertMainQuestion(array $state, array $chapter, string $type, string $questionText): bool
    {
        $columns = $this->tableColumns('questions');
        $typeColumn = $this->hasColumn($columns, 'question_type') ? 'question_type' : ($this->hasColumn($columns, 'type') ? 'type' : '');
        $textColumn = $this->hasColumn($columns, 'question_text') ? 'question_text' : ($this->hasColumn($columns, 'text') ? 'text' : '');

        foreach (['class_id', 'chapter_id'] as $required) {
            if (!$this->hasColumn($columns, $required)) {
                return true;
            }
        }
        if ($typeColumn === '' || $textColumn === '') {
            return true;
        }

        $chapterId = (int) $chapter['chapter_id'];
        $dupSql = "SELECT id FROM questions WHERE chapter_id = ? AND `$typeColumn` = ? AND `$textColumn` = ? LIMIT 1";
        $dup = $this->conn->prepare($dupSql);
        if ($dup) {
            $dup->bind_param('iss', $chapterId, $type, $questionText);
            $dup->execute();
            $res = $dup->get_result();
            $exists = $res && $res->num_rows > 0;
            $dup->close();
            if ($exists) {
                return true;
            }
        }

        $topic = (string) ($chapter['chapter_name'] ?? '');
        $data = [
            'class_id' => (int) $state['class_id'],
            'book_id' => (int) $state['book_id'],
            'book_name' => (string) $state['book_name'],
            'chapter_id' => $chapterId,
            $typeColumn => $type,
            $textColumn => $questionText,
            'topic' => $topic,
            'marks' => $type === 'long' ? 5 : 2,
            'typical_answer' => '',
        ];
        $typesByColumn = [
            'class_id' => 'i',
            'book_id' => 'i',
            'book_name' => 's',
            'chapter_id' => 'i',
            $typeColumn => 's',
            $textColumn => 's',
            'topic' => 's',
            'marks' => 'i',
            'typical_answer' => 's',
        ];

        return $this->insertAssociativeRow('questions', $columns, $data, $typesByColumn);
    }

    /**
     * @param array<string,bool> $tableColumns
     * @param array<string,mixed> $data
     * @param array<string,string> $typesByColumn
     */
    private function insertAssociativeRow(string $table, array $tableColumns, array $data, array $typesByColumn): bool
    {
        $insertColumns = [];
        $values = [];
        $types = '';

        foreach ($data as $column => $value) {
            if (!$this->hasColumn($tableColumns, (string) $column)) {
                continue;
            }
            $insertColumns[] = (string) $column;
            $values[] = $value;
            $types .= $typesByColumn[$column] ?? 's';
        }

        if (empty($insertColumns)) {
            return true;
        }

        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $quotedColumns = array_map(static function ($column) {
            return '`' . str_replace('`', '', $column) . '`';
        }, $insertColumns);
        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $sql = "INSERT INTO `$safeTable` (" . implode(',', $quotedColumns) . ") VALUES ($placeholders)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('BookQuestionGenerator main table sync prepare failed for ' . $safeTable . ': ' . $this->conn->error);
            return false;
        }

        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('BookQuestionGenerator main table sync insert failed for ' . $safeTable . ': ' . $stmt->error);
        }
        $stmt->close();

        return $ok;
    }

    /**
     * Sync already generated book-table rows into the normal site tables.
     *
     * @return array<string,int>
     */
    public function syncBookTablesToMainTables(int $limit = 0): array
    {
        $stats = [
            'mcqs_processed' => 0,
            'mcqs_failed' => 0,
            'questions_processed' => 0,
            'questions_failed' => 0,
        ];

        $limitClause = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

        $mcqSql = "SELECT m.*, COALESCE(b.book_name, c.book_name, '') AS resolved_book_name, COALESCE(c.chapter_name, '') AS chapter_name
                   FROM mcqs_from_book m
                   LEFT JOIN book b ON b.book_id = m.book_id
                   LEFT JOIN chapter c ON c.chapter_id = m.chapter_id
                   ORDER BY m.mcq_id ASC" . $limitClause;
        $res = $this->conn->query($mcqSql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $state = [
                    'class_id' => (int) $row['class_id'],
                    'book_id' => (int) $row['book_id'],
                    'book_name' => (string) ($row['resolved_book_name'] ?? ''),
                ];
                $chapter = [
                    'chapter_id' => (int) $row['chapter_id'],
                    'chapter_name' => (string) ($row['chapter_name'] ?? ''),
                ];
                $item = [
                    'question' => (string) $row['question'],
                    'option_a' => (string) $row['option_a'],
                    'option_b' => (string) $row['option_b'],
                    'option_c' => (string) $row['option_c'],
                    'option_d' => (string) $row['option_d'],
                    'correct_option' => (string) $row['correct_option'],
                    'difficulty_level' => (string) ($row['difficulty_level'] ?? 'Medium'),
                ];

                $stats['mcqs_processed']++;
                if (!$this->insertMainMcq($state, $chapter, $item)) {
                    $stats['mcqs_failed']++;
                }
            }
        }

        $questionSql = "SELECT q.*, COALESCE(q.book_name, b.book_name, c.book_name, '') AS resolved_book_name, COALESCE(c.chapter_name, q.topic, '') AS chapter_name
                        FROM questions_from_book q
                        LEFT JOIN book b ON b.book_id = q.book_id
                        LEFT JOIN chapter c ON c.chapter_id = q.chapter_id
                        ORDER BY q.id ASC" . $limitClause;
        $res = $this->conn->query($questionSql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $state = [
                    'class_id' => (int) $row['class_id'],
                    'book_id' => (int) $row['book_id'],
                    'book_name' => (string) ($row['resolved_book_name'] ?? ''),
                ];
                $chapter = [
                    'chapter_id' => (int) $row['chapter_id'],
                    'chapter_name' => (string) ($row['chapter_name'] ?? ''),
                ];

                $stats['questions_processed']++;
                if (!$this->insertMainQuestion($state, $chapter, (string) $row['question_type'], (string) $row['question_text'])) {
                    $stats['questions_failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public function buildProgress(array $state): array
    {
        $chapterIndex = (int) ($state['current_chapter_index'] ?? 0);
        $chapter = $state['chapters'][$chapterIndex] ?? null;
        if (!$chapter && !empty($state['chapters'])) {
            $chapter = $state['chapters'][count($state['chapters']) - 1];
        }

        return [
            'job_status' => $state['status'] ?? 'ready',
            'cancelled' => !empty($state['cancelled']),
            'current_chapter_index' => $chapterIndex,
            'total_chapters' => count($state['chapters'] ?? []),
            'stored_pdf' => $state['stored_pdf'] ?? '',
            'logs' => array_slice(is_array($state['logs'] ?? null) ? $state['logs'] : [], -80),
            'chapter' => $chapter ? [
                'chapter_no' => $chapter['chapter_no'] ?? 0,
                'chapter_name' => $chapter['chapter_name'] ?? '',
                'printed_start' => $chapter['printed_start'] ?? 0,
                'printed_end' => $chapter['printed_end'] ?? 0,
                'pdf_start' => $chapter['pdf_start'] ?? 0,
                'pdf_end' => $chapter['pdf_end'] ?? 0,
                'targets' => $chapter['targets'] ?? [],
                'saved' => $chapter['saved'] ?? [],
                'skipped' => $chapter['skipped'] ?? [],
                'replacement_attempts' => $chapter['replacement_attempts'] ?? 0,
                'status' => $chapter['status'] ?? 'pending',
                'current_type' => $chapter['current_type'] ?? '',
                'current_batch' => $chapter['current_batch'] ?? 0,
                'error' => $chapter['error'] ?? '',
                'warning' => $chapter['warning'] ?? '',
            ] : null,
        ];
    }

    /**
     * @param array<string,mixed> $chapter
     */
    private function savedSummary(array $chapter): string
    {
        $saved = $chapter['saved'] ?? [];
        return (int) ($saved['mcq'] ?? 0) . ' MCQs, ' . (int) ($saved['short'] ?? 0) . ' short, ' . (int) ($saved['long'] ?? 0) . ' long';
    }

    public function cancelJob(string $jobId): array
    {
        $state = $this->loadState($jobId);
        if (!$state) {
            return ['ok' => false, 'error' => 'Generation job not found.'];
        }
        $state['cancelled'] = true;
        $state['status'] = 'cancelled';
        $this->saveState($jobId, $state);
        return ['ok' => true, 'state' => $state];
    }

    /**
     * @return array{ok:bool,error?:string,text?:string}
     */
    public function getExtractedText(string $jobId, int $chapterIndex): array
    {
        $state = $this->loadState($jobId);
        if (!$state || !isset($state['chapters'][$chapterIndex])) {
            return ['ok' => false, 'error' => 'Chapter not found.'];
        }
        $file = (string) ($state['chapters'][$chapterIndex]['extracted_text_file'] ?? '');
        if ($file === '' || !is_readable($file)) {
            return ['ok' => false, 'error' => 'Extracted chapter text is not available yet.'];
        }
        $text = (string) file_get_contents($file);
        return ['ok' => true, 'text' => mb_substr($text, 0, 20000)];
    }

    /**
     * @return array{ok:bool,error?:string,items?:array<int,array<string,mixed>>}
     */
    public function getSavedQuestions(string $jobId, int $chapterIndex): array
    {
        $state = $this->loadState($jobId);
        if (!$state || !isset($state['chapters'][$chapterIndex])) {
            return ['ok' => false, 'error' => 'Chapter not found.'];
        }
        $chapterId = (int) ($state['chapters'][$chapterIndex]['chapter_id'] ?? 0);
        if ($chapterId <= 0) {
            return ['ok' => false, 'error' => 'Chapter has not been created yet.'];
        }

        $items = [];

        if (!empty($state['review_mode'])) {
            $jobIdSafe = (string) ($state['job_id'] ?? $jobId);
            $stmt = $this->conn->prepare(
                "SELECT id, 'mcq' AS type, question_text AS question, option_a, option_b, option_c, option_d, correct_option, difficulty_level
                 FROM book_mcq_drafts
                 WHERE job_id = ? AND chapter_id = ? AND status = 'pending'
                 ORDER BY id ASC"
            );
            if ($stmt) {
                $stmt->bind_param('si', $jobIdSafe, $chapterId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $items[] = $row;
                }
                $stmt->close();
            }
            $stmt = $this->conn->prepare(
                "SELECT id, question_kind AS type, question_text AS question, '' AS option_a, '' AS option_b, '' AS option_c, '' AS option_d, '' AS correct_option, '' AS difficulty_level
                 FROM book_question_drafts
                 WHERE job_id = ? AND chapter_id = ? AND status = 'pending'
                 ORDER BY id ASC"
            );
            if ($stmt) {
                $stmt->bind_param('si', $jobIdSafe, $chapterId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $items[] = $row;
                }
                $stmt->close();
            }
            return ['ok' => true, 'items' => $items];
        }

        $stmt = $this->conn->prepare('SELECT mcq_id AS id, question, option_a, option_b, option_c, option_d, correct_option, difficulty_level FROM mcqs_from_book WHERE chapter_id = ? ORDER BY mcq_id ASC');
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['type'] = 'mcq';
                $items[] = $row;
            }
            $stmt->close();
        }

        $stmt = $this->conn->prepare("SELECT id, question_type AS type, question_text AS question FROM questions_from_book WHERE chapter_id = ? ORDER BY id ASC");
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt->close();
        }

        return ['ok' => true, 'items' => $items];
    }

    private function shouldRetryWithoutJsonMode(array $gen): bool
    {
        if (($gen['http'] ?? 0) !== 400) {
            return false;
        }
        $m = strtolower((string) ($gen['error'] ?? ''));
        return strpos($m, 'json') !== false
            || strpos($m, 'mimetype') !== false
            || strpos($m, 'mime type') !== false
            || strpos($m, 'responsemimetype') !== false
            || strpos($m, 'invalid argument') !== false;
    }

    private function isRetryableGeminiError(array $gen): bool
    {
        $http = (int) ($gen['http'] ?? 0);
        if (in_array($http, [429, 500, 502, 503, 504], true)) {
            return true;
        }
        $m = strtolower((string) ($gen['error'] ?? ''));
        return strpos($m, 'rate') !== false || strpos($m, 'timeout') !== false || strpos($m, 'timed out') !== false;
    }

    private function publicGeminiError(string $message): string
    {
        $m = strtolower($message);
        if (strpos($m, 'api key') !== false || strpos($m, 'permission') !== false) {
            return 'Gemini API key is invalid or not authorized.';
        }
        if (strpos($m, 'rate') !== false || strpos($m, 'quota') !== false) {
            return 'Gemini rate limit reached. Please wait and retry.';
        }
        if (strpos($m, 'timeout') !== false || strpos($m, 'timed out') !== false) {
            return 'Gemini request timed out. Please retry.';
        }
        return 'AI generation failed. Please retry this batch.';
    }
}
