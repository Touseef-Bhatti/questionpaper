<?php

// Accepts POSTed HTML and returns a Word-compatible .doc download

// Simple CSRF safety: only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$rawHtml = isset($_POST['html']) ? $_POST['html'] : '';
$fileName = isset($_POST['file_name']) ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $_POST['file_name']) : 'Document';
if ($fileName === '') { $fileName = 'Document'; }

// Basic sanitization: allow only a subset of tags used in the paper content
// Note: Word can open HTML wrapped in a minimal Word HTML header
$allowedTags = '<html><head><meta><style><body><div><section><article><header><footer><h1><h2><h3><h4><h5><h6><p><br><span><strong><em><b><i><u><ol><ul><li><table><tr><td><th>'; 
$cleanHtml = strip_tags($rawHtml, $allowedTags);

// Minimal Word HTML header for better compatibility
$docHtml = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
    . '<head><meta charset="utf-8"><title>' . htmlspecialchars($fileName) . '</title>'
    . '<style>
        body{font-family:Segoe UI,Arial,sans-serif;}
        table{border-collapse:collapse;width:100%;}
        td,th{border:1px solid #888;padding:6px;vertical-align:top;}
        ol,ul{margin-left:20px;}
        .marks-input{float:right;}
        .mcq-question{font-weight:bold;margin-bottom:5px;}
        .mcq-options div{margin:2px 0;}
        .mcq-docx-layout table{width:100%;border:1px solid #ddd;}
        .mcq-docx-layout td{width:50%;padding:8px;border:1px solid #ddd;vertical-align:top;}
      </style>'
    . '</head><body>' . $cleanHtml . '</body></html>';

// Output as a .doc download. Use application/msword for best mobile handling
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


