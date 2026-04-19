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
    public static function prepareForGemini(string $localPath, string $ext): array
    {
        $ext = strtolower($ext);
        $mime = self::allowedMimeByExtension()[$ext] ?? 'application/octet-stream';

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
