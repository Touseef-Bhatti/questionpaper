<?php
/**
 * Admin-only diagnostics for the textbook upload prerequisites.
 * This page deliberately does not expose environment values or credentials.
 */
require_once __DIR__ . '/../security.php';
requireAdminAuth();
require_once __DIR__ . '/../../includes/book_questions/BookChapterExtractor.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function diagnosticIniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }

    return (int) $number;
}

function diagnosticSize(string $value): string
{
    $bytes = diagnosticIniBytes($value);
    return $bytes > 0 ? number_format($bytes / 1024 / 1024, 2) . ' MB' : ($value !== '' ? $value : 'Unlimited / not set');
}

function diagnosticEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function diagnosticCheck(string $name, bool $ok, string $detail): array
{
    return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

$disabledFunctions = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
$shellExecAvailable = function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions, true);
$pdfInfoPath = '';
$pdfInfoVersion = '';

if ($shellExecAvailable) {
    $lookupCommand = PHP_OS_FAMILY === 'Windows' ? 'where pdfinfo 2>NUL' : 'command -v pdfinfo 2>/dev/null';
    $pdfInfoPath = trim((string) @shell_exec($lookupCommand));
    if ($pdfInfoPath !== '') {
        $pdfInfoVersion = trim((string) @shell_exec('pdfinfo -v 2>&1'));
    }
}

$tempDir = sys_get_temp_dir();
$tempWriteable = false;
$tempProbe = @tempnam($tempDir, 'ahl_diag_');
if ($tempProbe !== false) {
    $tempWriteable = @file_put_contents($tempProbe, 'diagnostic') === 9;
    @unlink($tempProbe);
}

$checks = [
    diagnosticCheck(
        'PHP version',
        version_compare(PHP_VERSION, '8.0.0', '>='),
        PHP_VERSION . ' (' . PHP_SAPI . ')'
    ),
    diagnosticCheck(
        'fileinfo extension',
        class_exists('finfo'),
        class_exists('finfo') ? 'Available' : 'Missing; PDF MIME validation cannot run.'
    ),
    diagnosticCheck(
        'cURL extension',
        function_exists('curl_init'),
        function_exists('curl_init') ? 'Available' : 'Missing; Google Drive upload cannot run.'
    ),
    diagnosticCheck(
        'shell_exec()',
        $shellExecAvailable,
        $shellExecAvailable ? 'Available' : 'Disabled or unavailable; book page counting can fail before Drive upload.'
    ),
    diagnosticCheck(
        'Poppler pdfinfo',
        $pdfInfoPath !== '',
        $pdfInfoPath !== '' ? $pdfInfoPath : 'Not found; install Poppler/pdfinfo on the server.'
    ),
    diagnosticCheck(
        'PHP temporary directory',
        is_dir($tempDir) && is_readable($tempDir) && is_writable($tempDir) && $tempWriteable,
        $tempDir . ' — ' . ($tempWriteable ? 'readable and writable' : 'not writable')
    ),
];

$uploadResult = null;
$csrfToken = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $uploadResult = ['ok' => false, 'message' => 'Security token invalid. Reload the page and try again.'];
    } elseif (!isset($_FILES['diagnostic_pdf']) || !is_array($_FILES['diagnostic_pdf'])) {
        $uploadResult = ['ok' => false, 'message' => 'No PDF was submitted.'];
    } else {
        $file = $_FILES['diagnostic_pdf'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The PDF exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE => 'The PDF exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'The PDF upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'No PDF was submitted.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP has no temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'PHP could not write the uploaded PDF to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        ];

        if ($uploadError !== UPLOAD_ERR_OK) {
            $uploadResult = ['ok' => false, 'message' => $errorMessages[$uploadError] ?? ('Upload error code ' . $uploadError . '.')];
        } else {
            $path = (string) ($file['tmp_name'] ?? '');
            $name = basename((string) ($file['name'] ?? ''));
            $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo && $path !== '' ? (string) $finfo->file($path) : '';

            if ($path === '' || !is_uploaded_file($path)) {
                $uploadResult = ['ok' => false, 'message' => 'PHP did not provide a valid uploaded temporary file.'];
            } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || stripos($mime, 'pdf') === false) {
                $uploadResult = ['ok' => false, 'message' => 'The submitted file is not detected as a PDF. Detected MIME: ' . ($mime !== '' ? $mime : 'unknown') . '.'];
            } elseif (!$shellExecAvailable || $pdfInfoPath === '') {
                $uploadResult = ['ok' => false, 'message' => 'The upload reached PHP, but page counting was not attempted because shell_exec/pdfinfo is unavailable. Fix the failed prerequisite above.'];
            } else {
                try {
                    $pageInfo = (new BookChapterExtractor())->getPdfPageCount($path);
                    $uploadResult = $pageInfo['ok']
                        ? ['ok' => true, 'message' => 'The PDF reached the same page-count step used by book uploads. Pages detected: ' . (int) $pageInfo['page_count'] . '.']
                        : ['ok' => false, 'message' => (string) ($pageInfo['error'] ?? 'The PDF page-count check failed.')];
                } catch (Throwable $e) {
                    error_log('Book upload diagnostic failed: ' . $e->getMessage());
                    $uploadResult = ['ok' => false, 'message' => 'The page-count check raised an error. Check the server error log for the exact exception.'];
                }
            }
        }
    }
}

$uploadMax = (string) ini_get('upload_max_filesize');
$postMax = (string) ini_get('post_max_size');
$effectiveMax = min(diagnosticIniBytes($uploadMax), diagnosticIniBytes($postMax));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Upload Server Diagnostic</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; }
        body { margin: 0; background: #f3f6fa; color: #172033; }
        main { max-width: 980px; margin: 32px auto; padding: 0 16px 48px; }
        .card { background: #fff; border: 1px solid #dbe3ed; border-radius: 12px; padding: 20px; margin: 16px 0; box-shadow: 0 4px 16px rgba(16, 36, 64, .06); }
        h1 { margin: 0 0 8px; font-size: 28px; }
        h2 { margin: 0 0 14px; font-size: 19px; }
        .muted { color: #59677a; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
        .metric { background: #f7f9fc; border-radius: 8px; padding: 12px; }
        .metric strong { display: block; font-size: 19px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 8px; border-bottom: 1px solid #e5eaf0; text-align: left; vertical-align: top; }
        th { width: 30%; }
        .ok { color: #087443; font-weight: 700; }
        .bad { color: #b42318; font-weight: 700; }
        code { word-break: break-word; }
        input[type=file] { display: block; margin: 10px 0 14px; }
        button { border: 0; border-radius: 7px; padding: 10px 16px; background: #1463d8; color: #fff; cursor: pointer; }
        .result { border-radius: 8px; padding: 12px; margin-bottom: 14px; }
        .result.ok { background: #eaf8f0; }
        .result.bad { background: #fff0ef; }
        .warning { background: #fff8e5; border-left: 4px solid #c58a00; padding: 12px; }
        .range-fields { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .range-fields label { display: grid; gap: 5px; font-weight: 700; }
        .range-fields input { width: 100px; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; }
        pre { white-space: pre-wrap; max-height: 360px; overflow: auto; background: #f7f9fc; padding: 12px; border-radius: 8px; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>Book Upload Server Diagnostic</h1>
        <p class="muted">Admin-only checks for the textbook upload prerequisites. This page does not upload anything to Google Drive.</p>
        <div class="warning">After deploying this page, open it on production and submit a small PDF below. Send the results and the exact server-log entry if a check fails.</div>
    </div>

    <div class="card">
        <h2>Runtime limits</h2>
        <div class="summary">
            <div class="metric">upload_max_filesize<strong><?= diagnosticEscape(diagnosticSize($uploadMax)) ?></strong></div>
            <div class="metric">post_max_size<strong><?= diagnosticEscape(diagnosticSize($postMax)) ?></strong></div>
            <div class="metric">Effective PDF limit<strong><?= $effectiveMax > 0 ? diagnosticEscape(number_format($effectiveMax / 1024 / 1024, 2) . ' MB') : 'Not limited' ?></strong></div>
            <div class="metric">memory_limit<strong><?= diagnosticEscape(diagnosticSize((string) ini_get('memory_limit'))) ?></strong></div>
            <div class="metric">max_execution_time<strong><?= diagnosticEscape((string) ini_get('max_execution_time')) ?> sec</strong></div>
        </div>
    </div>

    <div class="card">
        <h2>Book-upload prerequisites</h2>
        <table>
            <thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <th><?= diagnosticEscape($check['name']) ?></th>
                    <td class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'PASS' : 'FAIL' ?></td>
                    <td><code><?= diagnosticEscape($check['detail']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($pdfInfoVersion !== ''): ?><p class="muted"><code><?= diagnosticEscape($pdfInfoVersion) ?></code></p><?php endif; ?>
    </div>

    <div class="card">
        <h2>Test the PDF page-count step</h2>
        <?php if ($uploadResult !== null): ?>
            <div class="result <?= $uploadResult['ok'] ? 'ok' : 'bad' ?>"><?= diagnosticEscape($uploadResult['message']) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= diagnosticEscape($csrfToken) ?>">
            <input type="file" name="diagnostic_pdf" accept="application/pdf,.pdf" required>
            <button type="submit">Check PDF page count</button>
        </form>
        <p class="muted">Use a small PDF first. The uploaded temporary file is only inspected for MIME type and page count, then PHP removes it automatically.</p>
    </div>

    <div class="card">
        <h2>Browser PDF.js alternative test</h2>
        <p class="muted">This test stays entirely in your browser. The PDF is not sent to PHP, Google Drive, or Gemini. It checks whether the browser can count pages and extract selectable text without <code>shell_exec()</code>, <code>pdfinfo</code>, or <code>pdftotext</code>.</p>
        <input type="file" id="browserPdf" accept="application/pdf,.pdf">
        <div class="range-fields">
            <label>Start page <input type="number" id="browserStart" min="1" value="1" disabled></label>
            <label>End page <input type="number" id="browserEnd" min="1" value="1" disabled></label>
            <button type="button" id="browserExtract" disabled>Extract selected pages</button>
        </div>
        <div id="browserResult" class="result" hidden></div>
        <pre id="browserText" hidden></pre>
    </div>
</main>
<script type="module">
    import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.3.289/build/pdf.mjs';

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.3.289/build/pdf.worker.mjs';

    const fileInput = document.getElementById('browserPdf');
    const startInput = document.getElementById('browserStart');
    const endInput = document.getElementById('browserEnd');
    const extractButton = document.getElementById('browserExtract');
    const resultBox = document.getElementById('browserResult');
    const textBox = document.getElementById('browserText');
    let loadedPdf = null;

    function showBrowserResult(message, ok) {
        resultBox.hidden = false;
        resultBox.className = 'result ' + (ok ? 'ok' : 'bad');
        resultBox.textContent = message;
    }

    fileInput.addEventListener('change', async () => {
        loadedPdf = null;
        extractButton.disabled = true;
        startInput.disabled = true;
        endInput.disabled = true;
        textBox.hidden = true;
        textBox.textContent = '';
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        showBrowserResult('Loading PDF in the browser...', true);
        try {
            const data = new Uint8Array(await file.arrayBuffer());
            loadedPdf = await pdfjsLib.getDocument({ data }).promise;
            startInput.max = loadedPdf.numPages;
            endInput.max = loadedPdf.numPages;
            startInput.value = '1';
            endInput.value = String(Math.min(3, loadedPdf.numPages));
            startInput.disabled = false;
            endInput.disabled = false;
            extractButton.disabled = false;
            showBrowserResult(
                'Browser PDF.js loaded the PDF successfully. Page count: ' + loadedPdf.numPages + '.',
                true
            );
        } catch (error) {
            showBrowserResult('Browser PDF.js could not load this PDF: ' + error.message, false);
        }
    });

    extractButton.addEventListener('click', async () => {
        if (!loadedPdf) return;
        const start = Number.parseInt(startInput.value, 10);
        const end = Number.parseInt(endInput.value, 10);
        if (!Number.isInteger(start) || !Number.isInteger(end) || start < 1 || end < start || end > loadedPdf.numPages) {
            showBrowserResult('Enter a valid page range.', false);
            return;
        }

        extractButton.disabled = true;
        textBox.hidden = true;
        textBox.textContent = '';
        let extracted = '';
        try {
            for (let pageNumber = start; pageNumber <= end; pageNumber++) {
                showBrowserResult('Extracting page ' + pageNumber + ' of ' + end + '...', true);
                const page = await loadedPdf.getPage(pageNumber);
                const content = await page.getTextContent();
                const pageText = content.items
                    .map(item => typeof item.str === 'string' ? item.str : '')
                    .join(' ')
                    .replace(/\s+/g, ' ')
                    .trim();
                extracted += '\n\n--- Page ' + pageNumber + ' ---\n' + pageText;
            }

            const cleanText = extracted.trim();
            textBox.textContent = cleanText || '[No selectable text found. This may be a scanned/image-only PDF.]';
            textBox.hidden = false;
            showBrowserResult(
                cleanText
                    ? 'Browser extraction succeeded. Extracted ' + cleanText.length.toLocaleString() + ' characters from pages ' + start + '-' + end + '.'
                    : 'PDF pages loaded, but no selectable text was found.',
                Boolean(cleanText)
            );
        } catch (error) {
            showBrowserResult('Browser text extraction failed: ' + error.message, false);
        } finally {
            extractButton.disabled = false;
        }
    });
</script>
</body>
</html>
