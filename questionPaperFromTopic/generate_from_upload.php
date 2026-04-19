<?php
/**
 * Accepts a single uploaded document; uses Gemini to generate MCQs / short / long
 * from file content only. Persists to AIGeneratedMCQs, AIGeneratedShortQuestions, AIGeneratedLongQuestions.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../quiz/mcq_generator.php';
require_once __DIR__ . '/DocumentContentExtractor.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/GeminiJsonExtractor.php';

/**
 * Retry without JSON mode if the API rejects responseMimeType (older models / edge cases).
 */
function geminiShouldRetryWithoutJsonMode(array $gen): bool
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

/**
 * Normalize LLM JSON: single object instead of array for mcqs/short/long.
 *
 * @param mixed $block
 * @return array<int, array<string, mixed>>
 */
function normalizeQuestionList($block): array
{
    if ($block === null || $block === []) {
        return [];
    }
    if (!is_array($block)) {
        return [];
    }
    if (isset($block['question']) || isset($block['question_text'])) {
        $out = [$block];
    } else {
        $out = [];
        foreach ($block as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
    }
    foreach ($out as &$row) {
        if (!is_array($row)) {
            continue;
        }
        if (!isset($row['question']) && isset($row['question_text'])) {
            $row['question'] = $row['question_text'];
        }
        if (!isset($row['typical_answer']) && isset($row['answer'])) {
            $row['typical_answer'] = $row['answer'];
        }
    }
    unset($row);
    return $out;
}

/**
 * @return int[]
 */
function decodeIdJsonList(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return [];
    }
    $out = [];
    foreach ($d as $v) {
        $n = intval($v);
        if ($n > 0) {
            $out[] = $n;
        }
    }
    return array_values(array_unique($out));
}

/**
 * @param int[] $ids
 * @return array<int, array<string, mixed>>
 */
function fetchStoredMcqsByIds(mysqli $conn, array $ids, int $limit): array
{
    if ($ids === [] || $limit <= 0) {
        return [];
    }
    $ids = array_slice($ids, 0, max(1, $limit));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, topic, question_text AS question, option_a, option_b, option_c, option_d, correct_option, explanation FROM AIGeneratedMCQs WHERE id IN ($ph)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $st->close();
    return $rows;
}

/**
 * @param int[] $ids
 * @return array<int, array<string, mixed>>
 */
function fetchStoredShortByIds(mysqli $conn, array $ids, int $limit): array
{
    if ($ids === [] || $limit <= 0) {
        return [];
    }
    $ids = array_slice($ids, 0, max(1, $limit));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, question_text AS question, typical_answer FROM AIGeneratedShortQuestions WHERE id IN ($ph)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $st->close();
    return $rows;
}

/**
 * @param int[] $ids
 * @return array<int, array<string, mixed>>
 */
function fetchStoredLongByIds(mysqli $conn, array $ids, int $limit): array
{
    if ($ids === [] || $limit <= 0) {
        return [];
    }
    $ids = array_slice($ids, 0, max(1, $limit));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, question_text AS question, typical_answer FROM AIGeneratedLongQuestions WHERE id IN ($ph)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $st->close();
    return $rows;
}

/**
 * @param array<string,mixed> $req
 * @return array<string,mixed>|null
 */
function findReusableUploadByHash(mysqli $conn, string $sha, array $req): ?array
{
    if ($sha === '') {
        return null;
    }
    $st = $conn->prepare(
        "SELECT id, stored_filename, relative_path, mime_type, ext, prepare_mode, topic_id, detected_topic, mcq_ids_json, short_ids_json, long_ids_json, recheck_status, recheck_finished_at
         FROM AIDocumentUploads
         WHERE file_sha256 = ?
         ORDER BY id DESC
         LIMIT 25"
    );
    if (!$st) {
        return null;
    }
    $st->bind_param('s', $sha);
    $st->execute();
    $res = $st->get_result();
    if (!$res) {
        $st->close();
        return null;
    }

    while ($row = $res->fetch_assoc()) {
        $mcqIds = decodeIdJsonList($row['mcq_ids_json'] ?? null);
        $shortIds = decodeIdJsonList($row['short_ids_json'] ?? null);
        $longIds = decodeIdJsonList($row['long_ids_json'] ?? null);

        $okMcq = empty($req['need_mcqs']) || (count($mcqIds) > 0);
        $okShort = empty($req['need_short']) || (count($shortIds) > 0);
        $okLong = empty($req['need_long']) || (count($longIds) > 0);

        if ($okMcq && $okShort && $okLong) {
            $row['mcq_ids'] = $mcqIds;
            $row['short_ids'] = $shortIds;
            $row['long_ids'] = $longIds;
            $st->close();
            return $row;
        }
    }
    $st->close();
    return null;
}

/**
 * @param int[] $mcqIds
 * @param int[] $shortIds
 * @param int[] $longIds
 */
function insertUploadRecord(
    mysqli $conn,
    int $uidForUpload,
    string $origName,
    string $storedName,
    string $relativeStoragePath,
    string $mimeType,
    int $size,
    string $fileSha256,
    string $ext,
    string $prepareModeStr,
    int $topicId,
    string $detectedTopic,
    array $mcqIds,
    array $shortIds,
    array $longIds,
    string $recheckStatusInsert,
    ?string $recheckFinishedInsert
): int {
    $mcqJson = json_encode(array_values(array_unique(array_map('intval', $mcqIds))));
    $shortJson = json_encode(array_values(array_unique(array_map('intval', $shortIds))));
    $longJson = json_encode(array_values(array_unique(array_map('intval', $longIds))));
    if ($mcqJson === false) {
        $mcqJson = '[]';
    }
    if ($shortJson === false) {
        $shortJson = '[]';
    }
    if ($longJson === false) {
        $longJson = '[]';
    }

    $uploadRecordId = 0;
    $ins = $conn->prepare(
        'INSERT INTO AIDocumentUploads (user_id, original_filename, stored_filename, relative_path, mime_type, file_size, file_sha256, ext, prepare_mode, topic_id, detected_topic, mcq_ids_json, short_ids_json, long_ids_json, recheck_status, recheck_finished_at) VALUES (NULLIF(?, 0), ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($ins) {
        $bindTypes = 'i' . str_repeat('s', 4) . 'i' . str_repeat('s', 3) . 'i' . str_repeat('s', 6);
        $ins->bind_param(
            $bindTypes,
            $uidForUpload,
            $origName,
            $storedName,
            $relativeStoragePath,
            $mimeType,
            $size,
            $fileSha256,
            $ext,
            $prepareModeStr,
            $topicId,
            $detectedTopic,
            $mcqJson,
            $shortJson,
            $longJson,
            $recheckStatusInsert,
            $recheckFinishedInsert
        );
        if ($ins->execute()) {
            $uploadRecordId = (int) $ins->insert_id;
        }
        $ins->close();
    }
    return $uploadRecordId;
}

/**
 * Keep uploaded source files for 48 hours, then delete physical files.
 */
function purgeExpiredUploadFiles(mysqli $conn, string $projectRoot, int $maxRows = 200): void
{
    $sql = "SELECT id, relative_path
            FROM AIDocumentUploads
            WHERE created_at < (NOW() - INTERVAL 48 HOUR)
              AND relative_path IS NOT NULL
              AND relative_path <> ''
            ORDER BY id ASC
            LIMIT ?";
    $st = $conn->prepare($sql);
    if (!$st) {
        return;
    }
    $st->bind_param('i', $maxRows);
    $st->execute();
    $res = $st->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rid = intval($row['id'] ?? 0);
            $rel = str_replace('\\', '/', (string) ($row['relative_path'] ?? ''));
            if ($rel === '') {
                continue;
            }
            $abs = rtrim($projectRoot, '/\\') . '/' . ltrim($rel, '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
            if ($rid > 0) {
                $up = $conn->prepare("UPDATE AIDocumentUploads SET relative_path = '' WHERE id = ?");
                if ($up) {
                    $up->bind_param('i', $rid);
                    $up->execute();
                    $up->close();
                }
            }
        }
    }
    $st->close();
}

function purgeExpiredContextCacheFiles(string $cacheDir): void
{
    if (!is_dir($cacheDir)) {
        return;
    }
    $cutoff = time() - (48 * 3600);
    $list = @glob(rtrim($cacheDir, '/\\') . '/*.txt');
    if (!is_array($list)) {
        return;
    }
    foreach ($list as $p) {
        $mtime = @filemtime($p);
        if ($mtime !== false && $mtime < $cutoff) {
            @unlink($p);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$maxBytes = 10 * 1024 * 1024;
$uploadBase = __DIR__ . '/../storage/ai_uploads';
if (!is_dir($uploadBase)) {
    @mkdir($uploadBase, 0755, true);
}
purgeExpiredUploadFiles($conn, dirname(__DIR__));
$globalContextCacheDir = __DIR__ . '/../storage/ai_upload_context';
purgeExpiredContextCacheFiles($globalContextCacheDir);

$file = $_FILES['document'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'Please choose a file to upload.']);
    exit;
}

if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid upload.']);
    exit;
}

if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload failed (error code ' . intval($file['error']) . ').']);
    exit;
}

$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    echo json_encode(['success' => false, 'error' => 'File must be between 1 byte and 10 MB.']);
    exit;
}

$origName = (string) ($file['name'] ?? 'upload');
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext === '' || !DocumentContentExtractor::isAllowedExtension($ext)) {
    echo json_encode([
        'success' => false,
        'error' => 'Allowed types: PDF, DOC, DOCX, PPT, PPTX, PNG, JPG, JPEG, WEBP, GIF.',
    ]);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);
if (!DocumentContentExtractor::mimeMatchesExtension((string) $detectedMime, $ext)) {
    echo json_encode(['success' => false, 'error' => 'File type does not match extension.']);
    exit;
}

$questionTypes = $_POST['question_types'] ?? [];
if (!is_array($questionTypes)) {
    $questionTypes = [$questionTypes];
}
$countMcqs  = intval($_POST['count_mcqs'] ?? 5);
$countShort = intval($_POST['count_short'] ?? 3);
$countLong  = intval($_POST['count_long'] ?? 2);

if (empty($questionTypes)) {
    echo json_encode(['success' => false, 'error' => 'Please select at least one question type.']);
    exit;
}

$countMcqs  = max(1, min($countMcqs, 30));
$countShort = max(1, min($countShort, 20));
$countLong  = max(1, min($countLong, 10));

$typesRequested = [];
if (in_array('mcqs', $questionTypes, true)) {
    $typesRequested[] = 'mcqs';
}
if (in_array('short', $questionTypes, true)) {
    $typesRequested[] = 'short';
}
if (in_array('long', $questionTypes, true)) {
    $typesRequested[] = 'long';
}
if ($typesRequested === []) {
    echo json_encode(['success' => false, 'error' => 'Please select at least one valid question type.']);
    exit;
}

$uidForUpload = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$tmpSha256 = @hash_file('sha256', $file['tmp_name']) ?: '';
$requestedCountMcqs = $countMcqs;
$requestedCountShort = $countShort;
$requestedCountLong = $countLong;
$existingMcqs = [];
$existingShort = [];
$existingLong = [];
$reuseTopicHint = '';
$reuseTopicIdHint = 0;

$reuseCandidate = findReusableUploadByHash($conn, $tmpSha256, [
    'need_mcqs'  => in_array('mcqs', $typesRequested, true),
    'need_short' => in_array('short', $typesRequested, true),
    'need_long'  => in_array('long', $typesRequested, true),
]);
if ($reuseCandidate) {
    $reuseTopic = trim((string) ($reuseCandidate['detected_topic'] ?? 'AI Generated from Upload'));
    if ($reuseTopic === '') {
        $reuseTopic = 'AI Generated from Upload';
    }
    $reuseTopicId = intval($reuseCandidate['topic_id'] ?? 0);
    if ($reuseTopicId <= 0) {
        $reuseTopicId = getOrCreateTopicId($conn, $reuseTopic);
    }

    $reuseResult = ['success' => true, 'detected_topic' => $reuseTopic];
    $reuseMcqIds = decodeIdJsonList($reuseCandidate['mcq_ids_json'] ?? null);
    $reuseShortIds = decodeIdJsonList($reuseCandidate['short_ids_json'] ?? null);
    $reuseLongIds = decodeIdJsonList($reuseCandidate['long_ids_json'] ?? null);

    if (in_array('mcqs', $typesRequested, true)) {
        $reuseResult['mcqs'] = fetchStoredMcqsByIds($conn, $reuseMcqIds, $requestedCountMcqs);
    }
    if (in_array('short', $typesRequested, true)) {
        $reuseResult['short'] = fetchStoredShortByIds($conn, $reuseShortIds, $requestedCountShort);
    }
    if (in_array('long', $typesRequested, true)) {
        $reuseResult['long'] = fetchStoredLongByIds($conn, $reuseLongIds, $requestedCountLong);
    }

    $ready = true;
    if (in_array('mcqs', $typesRequested, true) && count($reuseResult['mcqs'] ?? []) < $requestedCountMcqs) {
        $ready = false;
    }
    if (in_array('short', $typesRequested, true) && count($reuseResult['short'] ?? []) < $requestedCountShort) {
        $ready = false;
    }
    if (in_array('long', $typesRequested, true) && count($reuseResult['long'] ?? []) < $requestedCountLong) {
        $ready = false;
    }

    if ($ready) {
        $reuseStoredName = (string) ($reuseCandidate['stored_filename'] ?? '');
        $reuseRelPath = (string) ($reuseCandidate['relative_path'] ?? '');
        $reuseMime = (string) ($reuseCandidate['mime_type'] ?? $detectedMime);
        $reuseExt = (string) ($reuseCandidate['ext'] ?? $ext);
        $reusePrepare = (string) ($reuseCandidate['prepare_mode'] ?? 'file');
        $reuseStatus = (string) ($reuseCandidate['recheck_status'] ?? 'done');
        if ($reuseStatus === '') {
            $reuseStatus = 'done';
        }
        $reuseFinished = (string) ($reuseCandidate['recheck_finished_at'] ?? '');
        $reuseFinishedAt = ($reuseFinished === '') ? date('Y-m-d H:i:s') : $reuseFinished;

        $uploadRecordId = insertUploadRecord(
            $conn,
            $uidForUpload,
            $origName,
            $reuseStoredName !== '' ? $reuseStoredName : basename($reuseRelPath),
            $reuseRelPath,
            $reuseMime,
            $size,
            $tmpSha256,
            $reuseExt !== '' ? $reuseExt : $ext,
            $reusePrepare,
            $reuseTopicId,
            $reuseTopic,
            array_map(static function ($r) { return intval($r['id'] ?? 0); }, $reuseResult['mcqs'] ?? []),
            array_map(static function ($r) { return intval($r['id'] ?? 0); }, $reuseResult['short'] ?? []),
            array_map(static function ($r) { return intval($r['id'] ?? 0); }, $reuseResult['long'] ?? []),
            $reuseStatus,
            $reuseFinishedAt
        );

        $reuseResult['ai_upload_id'] = $uploadRecordId;
        $reuseResult['recheck_status'] = $reuseStatus;
        $reuseResult['reused_existing'] = true;
        echo json_encode($reuseResult);
        exit;
    }

    // Partial top-up path: reuse what exists, generate only remaining count.
    $existingMcqs = $reuseResult['mcqs'] ?? [];
    $existingShort = $reuseResult['short'] ?? [];
    $existingLong = $reuseResult['long'] ?? [];
    $countMcqs = in_array('mcqs', $typesRequested, true) ? max(0, $requestedCountMcqs - count($existingMcqs)) : 0;
    $countShort = in_array('short', $typesRequested, true) ? max(0, $requestedCountShort - count($existingShort)) : 0;
    $countLong = in_array('long', $typesRequested, true) ? max(0, $requestedCountLong - count($existingLong)) : 0;
    $reuseTopicHint = $reuseTopic;
    $reuseTopicIdHint = $reuseTopicId;
}

$apiKey = EnvLoader::get('GEMINIAPIKEY', '');
$model  = EnvLoader::get('GEMINIMODEL', 'gemini-2.5-flash');
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'AI service is not configured. Please contact administrator.']);
    exit;
}

$safeExt = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'bin';
$storedName = uniqid('up_', true) . '.' . $safeExt;
$destPath = $uploadBase . '/' . $storedName;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Could not store upload.']);
    exit;
}

$fileCleanupName = null;
$contextCacheDir = __DIR__ . '/../storage/ai_upload_context';
if (!is_dir($contextCacheDir)) {
    @mkdir($contextCacheDir, 0755, true);
}
$contextCachePath = ($tmpSha256 !== '') ? ($contextCacheDir . '/' . $tmpSha256 . '.txt') : '';

$prepared = null;
if ($contextCachePath !== '' && is_readable($contextCachePath)) {
    $cachedText = (string) @file_get_contents($contextCachePath);
    if ($cachedText !== '' && mb_strlen($cachedText) >= DocumentContentExtractor::MIN_TEXT_CHARS) {
        $prepared = ['mode' => 'text', 'text' => $cachedText, 'mime' => 'text/plain'];
    }
}
if (!is_array($prepared)) {
    $prepared = DocumentContentExtractor::prepareForGemini($destPath, $ext);
    if (($prepared['mode'] ?? '') === 'text') {
        $pt = (string) ($prepared['text'] ?? '');
        if ($contextCachePath !== '' && $pt !== '' && mb_strlen($pt) >= DocumentContentExtractor::MIN_TEXT_CHARS) {
            @file_put_contents($contextCachePath, $pt);
        }
    }
}

$promptParts = [];
$promptParts[] = 'You are an expert educational content creator and examiner.';
if (($prepared['mode'] ?? '') === 'text') {
    $userText = (string) ($prepared['text'] ?? '');
    if (mb_strlen($userText) < DocumentContentExtractor::MIN_TEXT_CHARS) {
        @unlink($destPath);
        echo json_encode([
            'success' => false,
            'error' => 'Could not extract enough text from this file. Try PDF or a different export.',
        ]);
        exit;
    }
    $promptParts[] = 'Analyze the following text (extracted from the user document) and generate high-quality exam questions from it.';
    $promptParts[] = "\n=== TEXT MATERIAL ===\n{$userText}\n=== END TEXT ===\n";
    $promptParts[] = "INSTRUCTIONS: Generate questions ONLY from information present in or directly inferable from the text above. Do not invent facts.\n";
} else {
    $promptParts[] = 'The user attached a file that contains the only source material you may use.';
    $promptParts[] = "INSTRUCTIONS: Read the attached file carefully. Generate questions ONLY from information present in or directly inferable from that file. Do not use outside knowledge or invent facts.\n";
}

$promptParts[] = 'First, identify the main topic/subject of this material in 2-5 words (e.g., \'Photosynthesis\', \'Newton Laws of Motion\'). Include this as "detected_topic" in your response.\n';
if ($reuseTopicHint !== '') {
    $promptParts[] = 'Previously detected topic for this same file hash: "' . $reuseTopicHint . '". Keep topic naming consistent unless clearly wrong.';
}
$promptParts[] = 'QUESTION WRITING STYLE (STRICT): Write direct standalone exam questions only.';
$promptParts[] = 'FORBIDDEN PHRASES: Do not use wording like "according to the text", "according to this passage", "according to this exercise", "as stated in", "from the page", "from the document", "from the file", "refer to", "based on the above text", or similar reference phrases.';
$promptParts[] = 'Do NOT mention text/page/exercise/document/file in the question wording unless an unavoidable unique label is part of the source itself.';
$promptParts[] = 'Rewrite naturally as regular exam questions while still grounded in the uploaded material facts.';

$jsonStructure = "{\n  \"detected_topic\": \"<main topic>\",\n";

if (in_array('mcqs', $typesRequested, true) && $countMcqs > 0) {
    $promptParts[] = "GENERATE {$countMcqs} MCQs: Each with a clear question stem, four options (option_a, option_b, option_c, option_d), and correct_option as just A, B, C, or D. Use plausible distractors. Cover different parts of the material.";
    $promptParts[] = 'MCQ STEM STYLE: Write each stem as a direct examination question (standalone). Do NOT use prefixes like "Based on Exercise", "According to Exercise", "From Exercise", "In Exercise", "As stated in Exercise", "Referring to Section", "According to the passage", or "From the document" unless that reference is strictly required to tell two facts apart. Prefer: "What is ...?", "Which of the following ...?", etc., using only the substantive content.';
    $jsonStructure .= '  "mcqs": [{"question": "...", "option_a": "...", "option_b": "...", "option_c": "...", "option_d": "...", "correct_option": "A|B|C|D"}],' . "\n";
}

if (in_array('short', $typesRequested, true) && $countShort > 0) {
    $promptParts[] = "GENERATE {$countShort} SHORT QUESTIONS: Each with a focused question answerable in 2-4 sentences and a concise typical_answer.";
    $jsonStructure .= '  "short": [{"question": "...", "typical_answer": "..."}],' . "\n";
}

if (in_array('long', $typesRequested, true) && $countLong > 0) {
    $promptParts[] = "GENERATE {$countLong} LONG QUESTIONS: Each with a comprehensive question requiring detailed explanation and a thorough typical_answer (full paragraph).";
    $jsonStructure .= '  "long": [{"question": "...", "typical_answer": "..."}],' . "\n";
}

$jsonStructure = rtrim($jsonStructure, ",\n") . "\n}";

$promptParts[] = 'For short and long questions, use the same direct wording: no unnecessary exercise/section/file references.';
$existingStems = [];
foreach ($existingMcqs as $r) {
    $s = trim((string) ($r['question'] ?? ''));
    if ($s !== '') {
        $existingStems[] = $s;
    }
}
foreach ($existingShort as $r) {
    $s = trim((string) ($r['question'] ?? ''));
    if ($s !== '') {
        $existingStems[] = $s;
    }
}
foreach ($existingLong as $r) {
    $s = trim((string) ($r['question'] ?? ''));
    if ($s !== '') {
        $existingStems[] = $s;
    }
}
if (!empty($existingStems)) {
    $existingStems = array_values(array_unique($existingStems));
    $existingStems = array_slice($existingStems, 0, 120);
    $promptParts[] = "IMPORTANT: The following question stems already exist for this same file. Do NOT repeat, paraphrase, or minimally reword them. Generate only new distinct questions:\n- " . implode("\n- ", $existingStems);
}
$promptParts[] = "\nRESPONSE: Return ONLY valid JSON, no markdown fences, no explanation:\n{$jsonStructure}";
$promptParts[] = 'RULES: Unique questions covering different aspects. Proper grammar. Academic language. MCQ distractors must be plausible. Short answers: 2-4 sentences. Long answers: full paragraphs.';

$fullPrompt = implode("\n", $promptParts);

$estimatedTokens = 1500;
if (in_array('mcqs', $typesRequested, true)) {
    $estimatedTokens += $countMcqs * 320;
}
if (in_array('short', $typesRequested, true)) {
    $estimatedTokens += $countShort * 280;
}
if (in_array('long', $typesRequested, true)) {
    $estimatedTokens += $countLong * 520;
}
// Never shrink below 8192 — low caps truncate JSON and cause parse errors.
$maxTokens = (int) min(16384, max(8192, $estimatedTokens));

$partsForApi = [];
$fileCleanupName = null;
if (($prepared['mode'] ?? '') === 'text') {
    $partsForApi = [['text' => $fullPrompt]];
    $gen = GeminiClient::callGenerateContent($apiKey, $model, $partsForApi, $maxTokens, 240, true);
    if (empty($gen['ok']) && geminiShouldRetryWithoutJsonMode($gen)) {
        $gen = GeminiClient::callGenerateContent($apiKey, $model, $partsForApi, $maxTokens, 240, false);
    }
} else {
    $mime = (string) ($prepared['mime'] ?? 'application/octet-stream');
    $built = GeminiClient::buildMultimodalParts($apiKey, $fullPrompt, $destPath, $mime);
    if (!empty($built['error'])) {
        @unlink($destPath);
        echo json_encode(['success' => false, 'error' => $built['error']]);
        exit;
    }
    $fileCleanupName = $built['fileNameForCleanup'] ?? null;
    $gen = GeminiClient::callGenerateContent($apiKey, $model, $built['parts'], $maxTokens, 300, true);
    if (empty($gen['ok']) && geminiShouldRetryWithoutJsonMode($gen)) {
        $gen = GeminiClient::callGenerateContent($apiKey, $model, $built['parts'], $maxTokens, 300, false);
    }
}

if (!empty($fileCleanupName)) {
    GeminiClient::deleteFile($apiKey, $fileCleanupName);
    $fileCleanupName = null;
}

if (empty($gen['ok'])) {
    @unlink($destPath);
    error_log('Gemini generate_from_upload: ' . ($gen['error'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => $gen['error'] ?? 'AI request failed.']);
    exit;
}

$content = (string) ($gen['text'] ?? '');
if ($content === '') {
    @unlink($destPath);
    echo json_encode(['success' => false, 'error' => 'AI returned empty response. Try another file or format.']);
    exit;
}

$parsed = GeminiJsonExtractor::parseObject($content);

if (!is_array($parsed)) {
    $snippet = mb_substr($content, 0, 1200);
    error_log('Gemini JSON parse failed. Length=' . strlen($content) . ' preview=' . $snippet);
    $hint = !empty($gen['truncated'])
        ? ' The AI hit its output limit — reduce the number of questions or shorten the file.'
        : '';
    @unlink($destPath);
    echo json_encode([
        'success' => false,
        'error' => 'AI response could not be parsed as JSON. Try again, use fewer questions, or a smaller file.' . $hint,
    ]);
    exit;
}

if (isset($parsed['mcqs'])) {
    $parsed['mcqs'] = normalizeQuestionList($parsed['mcqs']);
}
if (isset($parsed['short'])) {
    $parsed['short'] = normalizeQuestionList($parsed['short']);
}
if (isset($parsed['long'])) {
    $parsed['long'] = normalizeQuestionList($parsed['long']);
}

$detectedTopic = trim((string) ($parsed['detected_topic'] ?? 'AI Generated from Upload'));
if ($detectedTopic === '') {
    $detectedTopic = 'AI Generated from Upload';
}

$now = date('Y-m-d H:i:s');
$topicId = getOrCreateTopicId($conn, $detectedTopic);
if ($reuseTopicIdHint > 0) {
    $topicId = $reuseTopicIdHint;
}

$result = ['success' => true, 'detected_topic' => $detectedTopic];
if (in_array('mcqs', $typesRequested, true) && !empty($existingMcqs)) {
    $result['mcqs'] = $existingMcqs;
}
if (in_array('short', $typesRequested, true) && !empty($existingShort)) {
    $result['short'] = $existingShort;
}
if (in_array('long', $typesRequested, true) && !empty($existingLong)) {
    $result['long'] = $existingLong;
}

if (in_array('mcqs', $typesRequested, true) && !empty($parsed['mcqs'])) {
    $mcqs = $parsed['mcqs'];
    $mcqsForSave = [];
    foreach ($mcqs as &$q) {
        $q['topic'] = $detectedTopic;
        $mcqsForSave[] = $q;
    }
    unset($q);

    $savedMcqs = saveGeneratedMcqs($conn, $mcqsForSave, $detectedTopic, null, null, true);

    if (!empty($savedMcqs)) {
        $countStmt = $conn->prepare('INSERT INTO TopicQuestionCounts (topic_name, question_count) VALUES (?, ?) ON DUPLICATE KEY UPDATE question_count = question_count + ?');
        if ($countStmt) {
            $savedCount = count($savedMcqs);
            $countStmt->bind_param('sii', $detectedTopic, $savedCount, $savedCount);
            $countStmt->execute();
            $countStmt->close();
        }
    }

    $generatedMcqRows = !empty($savedMcqs) ? $savedMcqs : $mcqs;
    $result['mcqs'] = array_values(array_merge($result['mcqs'] ?? [], $generatedMcqRows));
}

if (in_array('short', $typesRequested, true) && !empty($parsed['short'])) {
    $shorts = $parsed['short'];
    $savedShorts = [];

    $stmt = $conn->prepare('INSERT INTO AIGeneratedShortQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        foreach ($shorts as $q) {
            $question = trim((string) ($q['question'] ?? ''));
            $answer = trim((string) ($q['typical_answer'] ?? ''));
            if ($question === '') {
                continue;
            }

            $stmt->bind_param('isss', $topicId, $question, $answer, $now);
            if ($stmt->execute()) {
                $savedShorts[] = [
                    'id'             => $stmt->insert_id,
                    'question'       => $question,
                    'typical_answer' => $answer,
                ];
            }
        }
        $stmt->close();
    }

    if (!empty($savedShorts)) {
        $countStmt = $conn->prepare('INSERT INTO TopicShortQuestionCounts (topic_name, question_count) VALUES (?, ?) ON DUPLICATE KEY UPDATE question_count = question_count + ?');
        if ($countStmt) {
            $savedCount = count($savedShorts);
            $countStmt->bind_param('sii', $detectedTopic, $savedCount, $savedCount);
            $countStmt->execute();
            $countStmt->close();
        }
    }

    $generatedShortRows = !empty($savedShorts) ? $savedShorts : $shorts;
    $result['short'] = array_values(array_merge($result['short'] ?? [], $generatedShortRows));
}

if (in_array('long', $typesRequested, true) && !empty($parsed['long'])) {
    $longs = $parsed['long'];
    $savedLongs = [];

    $stmt = $conn->prepare('INSERT INTO AIGeneratedLongQuestions (topic_id, question_text, typical_answer, generated_at) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        foreach ($longs as $q) {
            $question = trim((string) ($q['question'] ?? ''));
            $answer = trim((string) ($q['typical_answer'] ?? ''));
            if ($question === '') {
                continue;
            }

            $stmt->bind_param('isss', $topicId, $question, $answer, $now);
            if ($stmt->execute()) {
                $savedLongs[] = [
                    'id'             => $stmt->insert_id,
                    'question'       => $question,
                    'typical_answer' => $answer,
                ];
            }
        }
        $stmt->close();
    }

    if (!empty($savedLongs)) {
        $countStmt = $conn->prepare('INSERT INTO TopicLongQuestionCounts (topic_name, question_count) VALUES (?, ?) ON DUPLICATE KEY UPDATE question_count = question_count + ?');
        if ($countStmt) {
            $savedCount = count($savedLongs);
            $countStmt->bind_param('sii', $detectedTopic, $savedCount, $savedCount);
            $countStmt->execute();
            $countStmt->close();
        }
    }

    $generatedLongRows = !empty($savedLongs) ? $savedLongs : $longs;
    $result['long'] = array_values(array_merge($result['long'] ?? [], $generatedLongRows));
}

if (in_array('mcqs', $typesRequested, true) && !empty($result['mcqs'])) {
    $result['mcqs'] = array_slice(array_values($result['mcqs']), 0, $requestedCountMcqs);
}
if (in_array('short', $typesRequested, true) && !empty($result['short'])) {
    $result['short'] = array_slice(array_values($result['short']), 0, $requestedCountShort);
}
if (in_array('long', $typesRequested, true) && !empty($result['long'])) {
    $result['long'] = array_slice(array_values($result['long']), 0, $requestedCountLong);
}

try {
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $typesStr = implode(',', $typesRequested);
    $totalGenerated = count($result['mcqs'] ?? []) + count($result['short'] ?? []) + count($result['long'] ?? []);
    error_log("UploadToQuestions: user={$userId} topic='{$detectedTopic}' fileExt={$ext} types={$typesStr} generated={$totalGenerated}");
} catch (Exception $e) {
}

$generationOk = true;
if (in_array('mcqs', $typesRequested, true) && empty($result['mcqs'] ?? [])) {
    $generationOk = false;
}
if (in_array('short', $typesRequested, true) && empty($result['short'] ?? [])) {
    $generationOk = false;
}
if (in_array('long', $typesRequested, true) && empty($result['long'] ?? [])) {
    $generationOk = false;
}
if (!$generationOk) {
    @unlink($destPath);
    echo json_encode([
        'success' => false,
        'error' => 'AI did not return usable questions for your selections. Try a clearer file or different format.',
    ]);
    exit;
}

$fileSha256 = $tmpSha256 !== '' ? $tmpSha256 : (@hash_file('sha256', $destPath) ?: '');
$relativeStoragePath = 'storage/ai_uploads/' . $storedName;
$prepareModeStr = (string) ($prepared['mode'] ?? 'file');
$mcqIdList = [];
if (!empty($result['mcqs']) && is_array($result['mcqs'])) {
    foreach ($result['mcqs'] as $row) {
        if (!empty($row['id'])) {
            $mcqIdList[] = (int) $row['id'];
        }
    }
}
$shortIdList = [];
if (!empty($result['short']) && is_array($result['short'])) {
    foreach ($result['short'] as $row) {
        if (!empty($row['id'])) {
            $shortIdList[] = (int) $row['id'];
        }
    }
}
$longIdList = [];
if (!empty($result['long']) && is_array($result['long'])) {
    foreach ($result['long'] as $row) {
        if (!empty($row['id'])) {
            $longIdList[] = (int) $row['id'];
        }
    }
}

$recheckKeyForBg = trim((string) EnvLoader::get('GEMINIAPIKEYFORRECHECK', ''));
$recheckStatusInsert = ($recheckKeyForBg !== '') ? 'pending' : 'skipped';
$recheckFinishedInsert = ($recheckKeyForBg !== '') ? null : date('Y-m-d H:i:s');
$uploadRecordId = insertUploadRecord(
    $conn,
    $uidForUpload,
    $origName,
    $storedName,
    $relativeStoragePath,
    (string) $detectedMime,
    $size,
    $fileSha256,
    $ext,
    $prepareModeStr,
    $topicId,
    $detectedTopic,
    $mcqIdList,
    $shortIdList,
    $longIdList,
    $recheckStatusInsert,
    $recheckFinishedInsert
);

$result['ai_upload_id'] = $uploadRecordId;
$result['recheck_status'] = $recheckStatusInsert;

echo json_encode($result);
exit;

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level()) {
        @ob_end_flush();
    }
    @flush();
}

if ($uploadRecordId > 0 && $recheckKeyForBg !== '') {
    // Detach background worker so user flow (quiz/paper start) never waits.
    $phpBin = defined('PHP_BINARY') ? (string) PHP_BINARY : 'php';
    $script = __DIR__ . '/ai_upload_recheck_run.php';
    $idArg = (string) intval($uploadRecordId);

    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows/XAMPP
        $cmd = 'start /B "" ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($idArg) . ' >NUL 2>&1';
        @pclose(@popen($cmd, 'r'));
    } else {
        // Linux shared hosting
        $cmd = 'nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($idArg) . ' >/dev/null 2>&1 &';
        @pclose(@popen($cmd, 'r'));
    }
}
exit;
