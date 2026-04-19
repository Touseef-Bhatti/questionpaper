<?php
/**
 * Background verification for file-upload generation: uses GEMINIAPIKEYFORRECHECK
 * (or GEMINIAPIKEY) with the stored document to add MCQ explanations and refine
 * short/long typical answers against the source file.
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/DocumentContentExtractor.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/GeminiJsonExtractor.php';

/**
 * @param mysqli $conn
 * @param int    $uploadId AIDocumentUploads.id
 */
function runAiUploadRecheck($conn, int $uploadId): void
{
    if ($uploadId <= 0 || !($conn instanceof mysqli)) {
        return;
    }

    $row = aiUploadFetchRow($conn, $uploadId);
    if (!$row) {
        return;
    }

    if (($row['recheck_status'] ?? '') === 'done') {
        return;
    }

    $recheckKey = trim((string) EnvLoader::get('GEMINIAPIKEYFORRECHECK', ''));
    if ($recheckKey === '') {
        $recheckKey = trim((string) EnvLoader::get('GEMINIAPIKEY', ''));
    }
    if ($recheckKey === '') {
        if (in_array((string) ($row['recheck_status'] ?? ''), ['pending', 'processing', 'failed'], true)) {
            aiUploadMarkFailed($conn, $uploadId, 'No Gemini key for recheck (set GEMINIAPIKEYFORRECHECK or GEMINIAPIKEY).');
        }
        return;
    }

    $model = trim((string) EnvLoader::get('GEMINIRECHECKMODEL', ''));
    if ($model === '') {
        $model = trim((string) EnvLoader::get('GEMINIMODEL', 'gemini-2.5-flash'));
    }

    $projectRoot = dirname(__DIR__);
    $rel = str_replace('\\', '/', (string) ($row['relative_path'] ?? ''));
    $absPath = $projectRoot . '/' . ltrim($rel, '/');
    if (!is_readable($absPath)) {
        aiUploadMarkFailed($conn, $uploadId, 'Stored file missing or unreadable.');
        return;
    }

    $ext = strtolower((string) ($row['ext'] ?? pathinfo($absPath, PATHINFO_EXTENSION)));
    if ($ext === '' || !DocumentContentExtractor::isAllowedExtension($ext)) {
        aiUploadMarkFailed($conn, $uploadId, 'Invalid extension for recheck.');
        return;
    }

    $mcqIds = aiUploadDecodeIds($row['mcq_ids_json'] ?? null);
    $shortIds = aiUploadDecodeIds($row['short_ids_json'] ?? null);
    $longIds = aiUploadDecodeIds($row['long_ids_json'] ?? null);

    if ($mcqIds === [] && $shortIds === [] && $longIds === []) {
        aiUploadMarkDone($conn, $uploadId);
        return;
    }

    $st = $conn->prepare('UPDATE AIDocumentUploads SET recheck_status = ?, recheck_started_at = NOW(), recheck_error = NULL WHERE id = ? AND recheck_status IN (\'pending\',\'processing\',\'failed\',\'skipped\')');
    if ($st) {
        $proc = 'processing';
        $st->bind_param('si', $proc, $uploadId);
        $st->execute();
        $st->close();
    }

    $prepared = DocumentContentExtractor::prepareForGemini($absPath, $ext);

    try {
        $mcqRows = $mcqIds !== [] ? aiUploadLoadMcqs($conn, $mcqIds) : [];
        $shortRows = $shortIds !== [] ? aiUploadLoadShort($conn, $shortIds) : [];
        $longRows = $longIds !== [] ? aiUploadLoadLong($conn, $longIds) : [];

        $batchSize = 8;
        for ($i = 0; $i < count($mcqRows); $i += $batchSize) {
            $chunk = array_slice($mcqRows, $i, $batchSize);
            aiUploadRecheckMcqBatch($recheckKey, $model, $conn, $prepared, $absPath, $ext, $chunk);
        }

        if ($shortRows !== [] || $longRows !== []) {
            aiUploadRecheckShortLong($recheckKey, $model, $conn, $prepared, $absPath, $ext, $shortRows, $longRows);
        }

        aiUploadMarkDone($conn, $uploadId);
    } catch (Throwable $e) {
        error_log('runAiUploadRecheck upload_id=' . $uploadId . ' ' . $e->getMessage());
        aiUploadMarkFailed($conn, $uploadId, $e->getMessage());
    }
}

/**
 * @return array<string,mixed>|null
 */
function aiUploadFetchRow(mysqli $conn, int $uploadId): ?array
{
    $st = $conn->prepare('SELECT id, relative_path, ext, prepare_mode, recheck_status, mcq_ids_json, short_ids_json, long_ids_json FROM AIDocumentUploads WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $uploadId);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

/**
 * @return int[]
 */
function aiUploadDecodeIds($json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $d = json_decode((string) $json, true);
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
 * @return array<int,array<string,mixed>>
 */
function aiUploadLoadMcqs(mysqli $conn, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM AIGeneratedMCQs WHERE id IN ($placeholders)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $r = $st->get_result();
    $rows = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $st->close();
    return $rows;
}

/**
 * @param int[] $ids
 * @return array<int,array<string,mixed>>
 */
function aiUploadLoadShort(mysqli $conn, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, question_text, typical_answer FROM AIGeneratedShortQuestions WHERE id IN ($placeholders)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $r = $st->get_result();
    $rows = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $st->close();
    return $rows;
}

/**
 * @param int[] $ids
 * @return array<int,array<string,mixed>>
 */
function aiUploadLoadLong(mysqli $conn, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, question_text, typical_answer FROM AIGeneratedLongQuestions WHERE id IN ($placeholders)";
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$ids);
    $st->execute();
    $r = $st->get_result();
    $rows = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $st->close();
    return $rows;
}

function aiUploadMarkDone(mysqli $conn, int $uploadId): void
{
    $st = $conn->prepare('UPDATE AIDocumentUploads SET recheck_status = ?, recheck_finished_at = NOW(), recheck_error = NULL WHERE id = ?');
    if ($st) {
        $done = 'done';
        $st->bind_param('si', $done, $uploadId);
        $st->execute();
        $st->close();
    }
}

function aiUploadMarkFailed(mysqli $conn, int $uploadId, string $msg): void
{
    $st = $conn->prepare('UPDATE AIDocumentUploads SET recheck_status = ?, recheck_finished_at = NOW(), recheck_error = ? WHERE id = ?');
    if ($st) {
        $failed = 'failed';
        $st->bind_param('ssi', $failed, $msg, $uploadId);
        $st->execute();
        $st->close();
    }
}

/**
 * @param array<string,mixed> $prepared
 * @param array<int,array<string,mixed>> $mcqs
 */
function aiUploadRecheckMcqBatch(
    string $apiKey,
    string $model,
    mysqli $conn,
    array $prepared,
    string $absPath,
    string $ext,
    array $mcqs
): void {
    if ($mcqs === []) {
        return;
    }

    $lines = [];
    foreach ($mcqs as $m) {
        $id = (int) ($m['id'] ?? 0);
        $q = (string) ($m['question_text'] ?? '');
        $lines[] = "ID {$id}: Q: {$q} | A: {$m['option_a']} | B: {$m['option_b']} | C: {$m['option_c']} | D: {$m['option_d']} | Marked correct (letter or text): {$m['correct_option']}";
    }

    $instr = "You are an expert examiner. The ONLY source of truth is the attached document (or extracted text below).\n"
        . "For each MCQ listed, verify which option (A, B, C, or D) is correct according to the document.\n"
        . "Return JSON only with this shape (no markdown):\n"
        . '{"mcqs":[{"id":<int>,"correct_option":"A"|"B"|"C"|"D","explanation":"<2-4 sentences: why that option matches the document>","status":"verified"|"corrected"}]}\n'
        . "Use status \"verified\" if the marked answer was already correct; \"corrected\" if you change the correct letter.\n"
        . "Explanation must be grounded in the document only.\n\n"
        . "MCQs to verify:\n" . implode("\n", $lines);

    $gen = aiUploadGeminiJsonCall($apiKey, $model, $prepared, $absPath, $ext, $instr, 8192, 240);
    if (empty($gen['ok'])) {
        throw new RuntimeException($gen['error'] ?? 'Gemini recheck (MCQ) failed');
    }
    $parsed = GeminiJsonExtractor::parseObject((string) ($gen['text'] ?? ''));
    if (!is_array($parsed) || empty($parsed['mcqs']) || !is_array($parsed['mcqs'])) {
        throw new RuntimeException('Recheck response missing mcqs JSON');
    }

    $upd = $conn->prepare('UPDATE AIGeneratedMCQs SET correct_option = ?, explanation = ? WHERE id = ?');
    if (!$upd) {
        return;
    }

    foreach ($parsed['mcqs'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = intval($item['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $letter = strtoupper(trim((string) ($item['correct_option'] ?? '')));
        if (preg_match('/^[ABCD]$/', $letter) !== 1) {
            continue;
        }
        $expl = trim((string) ($item['explanation'] ?? ''));
        if ($expl === '') {
            continue;
        }
        $upd->bind_param('ssi', $letter, $expl, $id);
        $upd->execute();
    }
    $upd->close();
}

/**
 * @param array<string,mixed> $prepared
 * @param array<int,array<string,mixed>> $shortRows
 * @param array<int,array<string,mixed>> $longRows
 */
function aiUploadRecheckShortLong(
    string $apiKey,
    string $model,
    mysqli $conn,
    array $prepared,
    string $absPath,
    string $ext,
    array $shortRows,
    array $longRows
): void {
    $parts = [];
    if ($shortRows !== []) {
        $s = [];
        foreach ($shortRows as $r) {
            $s[] = 'SHORT id ' . intval($r['id']) . ': Q: ' . ($r['question_text'] ?? '') . ' | Draft answer: ' . ($r['typical_answer'] ?? '');
        }
        $parts[] = "SHORT QUESTIONS (refine typical_answer using the document only):\n" . implode("\n", $s);
    }
    if ($longRows !== []) {
        $s = [];
        foreach ($longRows as $r) {
            $s[] = 'LONG id ' . intval($r['id']) . ': Q: ' . ($r['question_text'] ?? '') . ' | Draft answer: ' . ($r['typical_answer'] ?? '');
        }
        $parts[] = "LONG QUESTIONS (produce thorough typical_answer aligned with the document):\n" . implode("\n", $s);
    }

    $instr = "You are an expert examiner. The ONLY authority is the attached document (or text below).\n"
        . "Refine the draft answers so they are accurate and complete relative to the document.\n"
        . "Return JSON only (no markdown):\n"
        . '{"short":[{"id":<int>,"typical_answer":"..."}],"long":[{"id":<int>,"typical_answer":"..."}]}\n'
        . "Include only keys for types you were given; use empty arrays if none.\n\n"
        . implode("\n\n", $parts);

    $gen = aiUploadGeminiJsonCall($apiKey, $model, $prepared, $absPath, $ext, $instr, 12288, 300);
    if (empty($gen['ok'])) {
        throw new RuntimeException($gen['error'] ?? 'Gemini recheck (short/long) failed');
    }
    $parsed = GeminiJsonExtractor::parseObject((string) ($gen['text'] ?? ''));
    if (!is_array($parsed)) {
        throw new RuntimeException('Recheck short/long JSON parse failed');
    }

    if (!empty($parsed['short']) && is_array($parsed['short'])) {
        $u = $conn->prepare('UPDATE AIGeneratedShortQuestions SET typical_answer = ? WHERE id = ?');
        if ($u) {
            foreach ($parsed['short'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = intval($row['id'] ?? 0);
                $ta = trim((string) ($row['typical_answer'] ?? ''));
                if ($id <= 0 || $ta === '') {
                    continue;
                }
                $u->bind_param('si', $ta, $id);
                $u->execute();
            }
            $u->close();
        }
    }

    if (!empty($parsed['long']) && is_array($parsed['long'])) {
        $u = $conn->prepare('UPDATE AIGeneratedLongQuestions SET typical_answer = ? WHERE id = ?');
        if ($u) {
            foreach ($parsed['long'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = intval($row['id'] ?? 0);
                $ta = trim((string) ($row['typical_answer'] ?? ''));
                if ($id <= 0 || $ta === '') {
                    continue;
                }
                $u->bind_param('si', $ta, $id);
                $u->execute();
            }
            $u->close();
        }
    }
}

/**
 * @param array<string,mixed> $prepared
 * @return array{ok?:bool,error?:string,text?:string}
 */
function aiUploadGeminiJsonCall(
    string $apiKey,
    string $model,
    array $prepared,
    string $absPath,
    string $ext,
    string $instructionText,
    int $maxTokens,
    int $timeoutSeconds
): array {
    if (($prepared['mode'] ?? '') === 'text') {
        $userText = (string) ($prepared['text'] ?? '');
        $full = $instructionText . "\n\n=== SOURCE TEXT (from uploaded file) ===\n" . $userText . "\n=== END ===\n";
        $parts = [['text' => $full]];
        $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, $timeoutSeconds, true);
        if (empty($gen['ok']) && aiUploadGeminiShouldRetryNoJson($gen)) {
            $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, $timeoutSeconds, false);
        }
        return $gen;
    }

    $mime = (string) ($prepared['mime'] ?? 'application/octet-stream');
    $built = GeminiClient::buildMultimodalParts($apiKey, $instructionText, $absPath, $mime);
    if (!empty($built['error'])) {
        return ['ok' => false, 'error' => $built['error']];
    }
    $parts = $built['parts'];
    $cleanup = $built['fileNameForCleanup'] ?? null;
    $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, $timeoutSeconds, true);
    if (empty($gen['ok']) && aiUploadGeminiShouldRetryNoJson($gen)) {
        $gen = GeminiClient::callGenerateContent($apiKey, $model, $parts, $maxTokens, $timeoutSeconds, false);
    }
    if (!empty($cleanup)) {
        GeminiClient::deleteFile($apiKey, $cleanup);
    }
    return $gen;
}

/**
 * @param array<string,mixed> $gen
 */
function aiUploadGeminiShouldRetryNoJson(array $gen): bool
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

if (PHP_SAPI === 'cli' && isset($argv[1]) && (int) $argv[1] > 0) {
    require_once __DIR__ . '/../db_connect.php';
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        runAiUploadRecheck($conn, (int) $argv[1]);
    }
}
