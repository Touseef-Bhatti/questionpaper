<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $filename = $_POST['filename'] ?? 'document.doc';
    $content = $_POST['content'];

    // Clean up filename
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'doc') {
        $filename .= '.doc';
    }

    // Headers for Word Document
    header("Content-Type: application/vnd.ms-word");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("content-disposition: attachment;filename=\"$filename\"");

    echo "<html>";
    echo "<head>";
    echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
    echo "<style>
            body { font-family: 'Times New Roman', serif; font-size: 12pt; }
            .paper-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .institute-name { font-size: 24px; font-weight: bold; text-transform: uppercase; }
            .section-title { font-size: 14pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; background-color: #f0f0f0; padding: 5px; }
            .q-item { margin-bottom: 15px; }
            .q-text { font-weight: bold; display: block; margin-bottom: 5px; }
            .options-grid { margin-left: 20px; }
            .option { display: block; margin-bottom: 3px; }
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; padding: 5px; }
          </style>";
    echo "</head>";
    echo "<body>";
    echo $content;
    echo "</body>";
    echo "</html>";
    exit;
}
?>