<?php
/**
 * Pure PHP extraction for DOCX/PPTX. PDF/images/legacy Office use Gemini multimodal/File API.
 */
class DocumentContentExtractor
{
    public const MIN_TEXT_CHARS = 80;
    public const MAX_TEXT_FOR_PROMPT = 120000;

    /** extension (lowercase, no dot) => canonical MIME for Gemini */
    public static function allowedMimeByExtension(): array
    {
        return [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt'  => 'text/plain',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];
    }

    public static function isAllowedExtension(string $ext): bool
    {
        return isset(self::allowedMimeByExtension()[strtolower($ext)]);
    }

    /**
     * Validate finfo MIME against extension (subset match).
     */
    public static function mimeMatchesExtension(string $detectedMime, string $ext): bool
    {
        $ext = strtolower($ext);
        $canonical = self::allowedMimeByExtension()[$ext] ?? '';
        if ($canonical === '') {
            return false;
        }
        $detectedMime = strtolower(trim(explode(';', $detectedMime)[0]));
        $aliases = [
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'txt'  => ['text/plain', 'application/octet-stream'],
        ];
        if ($detectedMime === $canonical) {
            return true;
        }
        if (isset($aliases[$ext]) && in_array($detectedMime, $aliases[$ext], true)) {
            return true;
        }
        // PDF / images / legacy office often report correctly
        if (in_array($ext, ['pdf', 'png', 'gif', 'webp'], true) && $detectedMime === $canonical) {
            return true;
        }
        if ($ext === 'doc' && in_array($detectedMime, ['application/msword', 'application/x-cfb'], true)) {
            return true;
        }
        if ($ext === 'ppt' && in_array($detectedMime, ['application/vnd.ms-powerpoint', 'application/x-cfb'], true)) {
            return true;
        }
        return false;
    }

    public static function extractDocxText(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            return '';
        }
        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text));
    }

    public static function extractPptxText(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !preg_match('#^ppt/slides/slide\d+\.xml$#i', $name)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') {
                continue;
            }
            if (preg_match_all('/<a:t[^>]*>([^<]*)<\/a:t>/u', $xml, $m)) {
                foreach ($m[1] as $chunk) {
                    $chunk = html_entity_decode($chunk, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($chunk !== '') {
                        $parts[] = $chunk;
                    }
                }
            }
        }
        $zip->close();
        $text = implode("\n", $parts);
        return trim(preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text));
    }

    /**
     * @return array{mode:string,text?:string,mime?:string,path?:string,ext?:string}
     */
    /**
     * Read plain text from a .txt file.
     */
    public static function extractTxtText(string $path): string
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            return '';
        }
        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }
        // Strip control characters except newline/tab
        $text = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text);
        return trim($text);
    }

    private static function commandExists(string $command): bool
    {
        $command = trim($command);
        if ($command === '') {
            return false;
        }
        $check = (stripos(PHP_OS_FAMILY, 'Windows') === 0)
            ? ('where ' . escapeshellarg($command) . ' 2>NUL')
            : ('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        $out = @shell_exec($check);
        return is_string($out) && trim($out) !== '';
    }

    private static function stderrToNull(): string
    {
        return (stripos(PHP_OS_FAMILY, 'Windows') === 0) ? ' 2>NUL' : ' 2>/dev/null';
    }

    /**
     * @return array{ok:bool,error?:string,page_count?:int}
     */
    public static function getPdfPageCount(string $path): array
    {
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'PDF file is not readable.'];
        }
        if (!self::commandExists('pdfinfo')) {
            return ['ok' => false, 'error' => 'Local PDF tool pdfinfo is not installed on the server. Install poppler-utils, then try again.'];
        }

        $cmd = 'pdfinfo ' . escapeshellarg($path) . ' 2>&1';
        $out = @shell_exec($cmd);
        if (!is_string($out) || trim($out) === '') {
            return ['ok' => false, 'error' => 'Could not read PDF page count with pdfinfo.'];
        }
        if (preg_match('/^Pages:\s*(\d+)\s*$/mi', $out, $m)) {
            $pages = (int) $m[1];
            if ($pages > 0) {
                return ['ok' => true, 'page_count' => $pages];
            }
        }

        return ['ok' => false, 'error' => 'Could not parse PDF page count from pdfinfo output.'];
    }

    /**
     * Extract text from a PDF, optionally limited to a 1-based page range.
     */
    public static function extractPdfText(string $path, ?int $startPage = null, ?int $endPage = null): string
    {
        if (!is_readable($path)) {
            return '';
        }

        if (self::commandExists('pdftotext')) {
            $parts = ['pdftotext', '-layout', '-enc', 'UTF-8'];
            if ($startPage !== null && $endPage !== null) {
                $parts[] = '-f';
                $parts[] = (string) max(1, $startPage);
                $parts[] = '-l';
                $parts[] = (string) max(max(1, $startPage), $endPage);
            }
            $parts[] = $path;
            $parts[] = '-';

            $escaped = array_map(static function (string $part): string {
                return escapeshellarg($part);
            }, $parts);
            $cmd = implode(' ', $escaped) . self::stderrToNull();
            $text = @shell_exec($cmd);
            if (is_string($text)) {
                $text = str_replace("\r\n", "\n", $text);
                $text = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text);
                $text = preg_replace("/[ \t]+\n/", "\n", $text);
                $text = preg_replace("/\n{4,}/", "\n\n\n", $text);
                $text = trim((string) $text);
                if (mb_strlen($text) >= self::MIN_TEXT_CHARS) {
                    return $text;
                }
            }
        }

        return self::ocrPdfText($path, $startPage, $endPage);
    }

    /**
     * OCR image-based PDF pages locally with Poppler + Tesseract.
     */
    private static function ocrPdfText(string $path, ?int $startPage = null, ?int $endPage = null): string
    {
        if (!self::commandExists('pdftoppm') || !self::commandExists('tesseract')) {
            return '';
        }

        $start = max(1, (int) ($startPage ?? 1));
        $end = max($start, (int) ($endPage ?? $start));
        $tmpBase = sys_get_temp_dir() . '/pdfocr_' . bin2hex(random_bytes(6));
        $prefix = $tmpBase . '/page';
        if (!@mkdir($tmpBase, 0700, true)) {
            return '';
        }

        $cmdParts = [
            'pdftoppm',
            '-f',
            (string) $start,
            '-l',
            (string) $end,
            '-r',
            '180',
            '-png',
            $path,
            $prefix,
        ];
        $cmd = implode(' ', array_map(static function (string $part): string {
            return escapeshellarg($part);
        }, $cmdParts)) . self::stderrToNull();
        @shell_exec($cmd);

        $images = glob($prefix . '-*.png');
        if (!is_array($images) || $images === []) {
            @rmdir($tmpBase);
            return '';
        }
        sort($images, SORT_NATURAL);

        $chunks = [];
        foreach ($images as $image) {
            $ocrCmd = 'tesseract ' . escapeshellarg($image) . ' stdout -l eng --psm 6' . self::stderrToNull();
            $chunk = @shell_exec($ocrCmd);
            if (is_string($chunk) && trim($chunk) !== '') {
                $chunks[] = trim($chunk);
            }
            @unlink($image);
        }
        @rmdir($tmpBase);

        $text = implode("\n\n", $chunks);
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", (string) $text);
        $text = preg_replace("/\n{4,}/", "\n\n\n", (string) $text);
        return trim((string) $text);
    }

    /**
     * @return array{mode:string,text?:string,mime?:string,path?:string,ext?:string}
     */
    public static function prepareForGemini(string $localPath, string $ext): array
    {
        $ext = strtolower($ext);
        $mime = self::allowedMimeByExtension()[$ext] ?? 'application/octet-stream';

        // TXT — direct text read
        if ($ext === 'txt') {
            $text = self::extractTxtText($localPath);
            if (mb_strlen($text) < self::MIN_TEXT_CHARS) {
                return ['mode' => 'binary', 'mime' => $mime, 'path' => $localPath, 'ext' => $ext];
            }
            if (mb_strlen($text) > self::MAX_TEXT_FOR_PROMPT) {
                $text = mb_substr($text, 0, self::MAX_TEXT_FOR_PROMPT) . "\n\n[... content truncated ...]";
            }
            return ['mode' => 'text', 'text' => $text, 'ext' => $ext];
        }

        if ($ext === 'pdf') {
            $text = self::extractPdfText($localPath);
            if (mb_strlen($text) < self::MIN_TEXT_CHARS) {
                return ['mode' => 'binary', 'mime' => $mime, 'path' => $localPath, 'ext' => $ext];
            }
            if (mb_strlen($text) > self::MAX_TEXT_FOR_PROMPT) {
                $text = mb_substr($text, 0, self::MAX_TEXT_FOR_PROMPT) . "\n\n[... content truncated ...]";
            }
            return ['mode' => 'text', 'text' => $text, 'ext' => $ext];
        }

        if ($ext === 'docx') {
            $text = self::extractDocxText($localPath);
            if (mb_strlen($text) < self::MIN_TEXT_CHARS) {
                return ['mode' => 'binary', 'mime' => $mime, 'path' => $localPath, 'ext' => $ext];
            }
            if (mb_strlen($text) > self::MAX_TEXT_FOR_PROMPT) {
                $text = mb_substr($text, 0, self::MAX_TEXT_FOR_PROMPT) . "\n\n[... content truncated ...]";
            }
            return ['mode' => 'text', 'text' => $text, 'ext' => $ext];
        }

        if ($ext === 'pptx') {
            $text = self::extractPptxText($localPath);
            if (mb_strlen($text) < self::MIN_TEXT_CHARS) {
                return ['mode' => 'binary', 'mime' => $mime, 'path' => $localPath, 'ext' => $ext];
            }
            if (mb_strlen($text) > self::MAX_TEXT_FOR_PROMPT) {
                $text = mb_substr($text, 0, self::MAX_TEXT_FOR_PROMPT) . "\n\n[... content truncated ...]";
            }
            return ['mode' => 'text', 'text' => $text, 'ext' => $ext];
        }

        return ['mode' => 'binary', 'mime' => $mime, 'path' => $localPath, 'ext' => $ext];
    }
}
