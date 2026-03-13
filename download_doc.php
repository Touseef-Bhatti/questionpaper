<?php

// Accepts POSTed HTML and returns a Word-compatible .doc download

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$rawHtml      = isset($_POST['html'])         ? $_POST['html']         : '';
$extraStyles  = isset($_POST['extra_styles']) ? $_POST['extra_styles'] : '';
$fileName     = isset($_POST['file_name'])    ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $_POST['file_name']) : 'Document';
if ($fileName === '') { $fileName = 'Document'; }

// Allowed tags — keep <style> so inlined header class styles survive
$allowedTags = '<html><head><meta><style><body><div><section><article><header><footer>'
             . '<h1><h2><h3><h4><h5><h6><p><br><span><strong><em><b><i><u>'
             . '<ol><ul><li><table><tr><td><th>';
$cleanHtml = strip_tags($rawHtml, $allowedTags);

// Sanitize extra styles (strip any script injection)
$safeExtraStyles = strip_tags($extraStyles);

$docHtml = '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
         . ' xmlns:w="urn:schemas-microsoft-com:office:word"'
         . ' xmlns="http://www.w3.org/TR/REC-html40">'
         . '<head>'
         . '<meta charset="utf-8">'
         . '<title>' . htmlspecialchars($fileName) . '</title>'
         . '<style>'

         // ── Base document defaults ──────────────────────────────────────
         . 'body { font-family: "Times New Roman", Times, serif; font-size: 12pt; margin: 20px; }'
         . 'h1,h2,h3,h4,h5,h6 { font-family: inherit; }'

         // ── Question sections – no borders ──────────────────────────────
         . '.section { margin-top: 20px; }'
         . '.question-list { margin-top: 8px; }'
         . '.question-item { margin-bottom: 10px; }'
         . '.question-content { display: inline; }'
         . '.marks-container { float: right; font-weight: bold; }'

         // ── MCQ layout ───────────────────────────────────────────────────
         . '.mcq-question { font-weight: bold; margin-bottom: 4px; }'
         . '.mcq-options div { margin: 2px 0; }'
         . '.option-row { margin-bottom: 2px; }'

         // ── All tables default: no border (headers add their own) ────────
         . 'table { border-collapse: collapse; width: 100%; }'
         . 'td, th { padding: 4px 6px; vertical-align: top; }'

         // ── Embed ALL page styles (header CSS, etc.) so class rules work ─
         . $safeExtraStyles

         . '</style>'
         . '</head>'
         . '<body>'
         . $cleanHtml
         . '</body>'
         . '</html>';

header('Content-Description: File Transfer');
header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . $fileName . '.doc"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

echo $docHtml;
exit;
?>
