<?php
/**
 * Extract chapter pages from PDF locally, then send extracted text to AI.
 */
require_once __DIR__ . '/../../questionPaperFromTopic/DocumentContentExtractor.php';

class BookChapterExtractor
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 2);
    }

    /**
     * Get PDF page count locally.
     * @return array{ok:bool,error?:string,page_count?:int}
     */
    public function getPdfPageCount(string $pdfPath): array
    {
        if (!is_readable($pdfPath)) {
            return ['ok' => false, 'error' => 'Uploaded PDF is not readable.'];
        }

        return DocumentContentExtractor::getPdfPageCount($pdfPath);
    }

    /**
     * Validate printed page range.
     * @return array{ok:bool,error?:string,pdf_start?:int,pdf_end?:int}
     */
    public function validatePrintedPageRange(int $printedStart, int $printedEnd, int $pageOffset, int $pdfPageCount): array
    {
        if ($printedStart <= 0 || $printedEnd <= 0) {
            return ['ok' => false, 'error' => 'Page numbers must be positive.'];
        }
        if ($printedEnd < $printedStart) {
            return ['ok' => false, 'error' => 'End page cannot be smaller than start page.'];
        }

        $pdfStart = $printedStart + $pageOffset;
        $pdfEnd = $printedEnd + $pageOffset;

        if ($pdfStart < 1 || $pdfEnd < $pdfStart) {
            return ['ok' => false, 'error' => 'Calculated PDF page range is invalid. Check page offset.'];
        }
        if ($pdfPageCount > 0 && ($pdfStart > $pdfPageCount || $pdfEnd > $pdfPageCount)) {
            return ['ok' => false, 'error' => 'Requested pages exceed PDF length (PDF has ' . $pdfPageCount . ' pages).'];
        }

        return ['ok' => true, 'pdf_start' => $pdfStart, 'pdf_end' => $pdfEnd];
    }

    /**
     * Extract chapter text from PDF locally.
     * @return array{ok:bool,error?:string,text?:string,temp_files?:string[]}
     */
    public function extractChapterMarkdown(string $sourcePdf, int $pdfStart, int $pdfEnd, string $workDir): array
    {
        if (!is_dir($workDir)) {
            @mkdir($workDir, 0750, true);
        }

        if (!is_readable($sourcePdf)) {
            return ['ok' => false, 'error' => 'Source PDF is not readable.'];
        }

        if ($pdfStart < 1 || $pdfEnd < $pdfStart) {
            return ['ok' => false, 'error' => 'Invalid PDF page range.'];
        }

        $cacheFile = $workDir . '/extracted.md';
        if (is_readable($cacheFile)) {
            $cached = (string) @file_get_contents($cacheFile);
            if (mb_strlen($cached) >= 80) {
                return ['ok' => true, 'text' => $cached, 'temp_files' => []];
            }
        }

        $text = DocumentContentExtractor::extractPdfText($sourcePdf, $pdfStart, $pdfEnd);

        // Validate extracted text
        if (mb_strlen($text) < 80) {
            return [
                'ok' => false,
                'error' => 'Could not extract enough text locally from these PDF pages. Make sure poppler-utils/pdftotext is installed and the PDF contains selectable text, not only scanned images.',
            ];
        }

        // Truncate if too large
        if (mb_strlen($text) > 120000) {
            $text = mb_substr($text, 0, 120000) . "\n\n[... content truncated ...]";
        }
        @file_put_contents($cacheFile, $text);

        return ['ok' => true, 'text' => $text, 'temp_files' => []];
    }

    /**
     * Cleanup files (legacy method, no-op for local extraction).
     * @param string[] $files
     */
    public function cleanupFiles(array $files): void
    {
        // Kept for backward compatibility.
    }
}
