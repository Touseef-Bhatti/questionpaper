<?php
/**
 * Extract PDF content using Gemini API (replaces Python PyMuPDF + MarkItDown).
 * Uses Gemini's File API and multimodal capabilities to handle PDF extraction without Python.
 */
class GeminiPdfExtractor
{
    private string $apiKey;
    private string $model;
    private string $projectRoot;
    private string $cacheDir;

    public function __construct(string $apiKey, string $model = 'gemini-2.5-flash', ?string $projectRoot = null)
    {
        if ($apiKey === '') {
            throw new InvalidArgumentException('Gemini API key is required for PDF extraction');
        }
        $this->apiKey = $apiKey;
        $this->model = $model ?: 'gemini-2.5-flash';
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 2);
        $this->cacheDir = $this->projectRoot . '/storage/pdf_extraction_cache';
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
    }

    /**
     * Get PDF page count via Gemini API.
     * @return array{ok:bool,error?:string,page_count?:int}
     */
    public function getPdfPageCount(string $pdfPath): array
    {
        if (!is_readable($pdfPath)) {
            return ['ok' => false, 'error' => 'PDF file is not readable'];
        }

        $prompt = 'How many pages does this PDF document have? Reply with ONLY a number.';
        $built = GeminiClient::buildMultimodalParts($this->apiKey, $prompt, $pdfPath, 'application/pdf');
        if (!empty($built['error'])) {
            return ['ok' => false, 'error' => $built['error']];
        }

        $fileName = $built['fileNameForCleanup'] ?? null;
        try {
            $result = GeminiClient::callGenerateContent(
                $this->apiKey,
                $this->model,
                $built['parts'],
                256,
                120
            );

            if (!$result['ok']) {
                return ['ok' => false, 'error' => $result['error'] ?? 'Failed to get page count'];
            }

            $text = trim((string) ($result['text'] ?? ''));
            $matches = [];
            if (preg_match('/\b(\d+)\b/', $text, $matches)) {
                $pageCount = intval($matches[1]);
                if ($pageCount > 0) {
                    return ['ok' => true, 'page_count' => $pageCount];
                }
            }

            return ['ok' => false, 'error' => 'Could not parse page count from AI response'];
        } finally {
            if (!empty($fileName)) {
                GeminiClient::deleteFile($this->apiKey, $fileName);
            }
        }
    }

    /**
     * Extract chapter text from PDF using Gemini.
     * @return array{ok:bool,error?:string,text?:string}
     */
    public function extractChapterMarkdown(
        string $sourcePdf,
        int $pdfStart,
        int $pdfEnd,
        string $workDir
    ): array {
        if (!is_readable($sourcePdf)) {
            return ['ok' => false, 'error' => 'Source PDF is not readable'];
        }

        if ($pdfStart < 1 || $pdfEnd < $pdfStart) {
            return ['ok' => false, 'error' => 'Invalid page range'];
        }

        // Create work directory
        if (!is_dir($workDir)) {
            @mkdir($workDir, 0750, true);
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($sourcePdf, $pdfStart, $pdfEnd);
        $cachedText = $this->readCache($cacheKey);
        if ($cachedText !== null) {
            return ['ok' => true, 'text' => $cachedText];
        }

        $prompt = $this->buildExtractionPrompt($pdfStart, $pdfEnd);
        $built = GeminiClient::buildMultimodalParts($this->apiKey, $prompt, $sourcePdf, 'application/pdf');
        if (!empty($built['error'])) {
            return ['ok' => false, 'error' => $built['error']];
        }

        $fileName = $built['fileNameForCleanup'] ?? null;
        try {
            $result = GeminiClient::callGenerateContent(
                $this->apiKey,
                $this->model,
                $built['parts'],
                8192,
                180
            );

            if (!$result['ok']) {
                return ['ok' => false, 'error' => $result['error'] ?? 'Text extraction failed'];
            }

            $text = (string) ($result['text'] ?? '');
            if (mb_strlen($text) < 50) {
                return ['ok' => false, 'error' => 'Extracted text is too short or empty'];
            }

            // Cache the result
            $this->writeCache($cacheKey, $text);

            return ['ok' => true, 'text' => $text];
        } finally {
            if (!empty($fileName)) {
                GeminiClient::deleteFile($this->apiKey, $fileName);
            }
        }
    }

    /**
     * Build extraction prompt for specific page range.
     */
    private function buildExtractionPrompt(int $pdfStart, int $pdfEnd): string
    {
        $lines = [];
        $lines[] = 'You are extracting text from pages ' . $pdfStart . ' to ' . $pdfEnd . ' of a PDF document.';
        $lines[] = '';
        $lines[] = 'INSTRUCTIONS:';
        $lines[] = '1. Extract ALL text content from ONLY the specified pages.';
        $lines[] = '2. Preserve the document structure and formatting where meaningful.';
        $lines[] = '3. Include all text, headings, subheadings, lists, tables, and captions.';
        $lines[] = '4. For tables: present data in a readable format (markdown tables or clear text).';
        $lines[] = '5. For images: describe them briefly if they contain important information.';
        $lines[] = '6. Do NOT include page numbers, headers, footers, or page breaks.';
        $lines[] = '7. Do NOT include content from other pages.';
        $lines[] = '8. Return ONLY the extracted text content, nothing else.';
        $lines[] = '';
        $lines[] = 'Extract pages ' . $pdfStart . ' to ' . $pdfEnd . ':';

        return implode("\n", $lines);
    }

    /**
     * Generate cache key for extracted text.
     */
    private function getCacheKey(string $pdfPath, int $pdfStart, int $pdfEnd): string
    {
        $fileHash = hash_file('sha256', $pdfPath, false);
        return $fileHash . '_' . $pdfStart . '_' . $pdfEnd . '.txt';
    }

    /**
     * Read from extraction cache.
     */
    private function readCache(string $cacheKey): ?string
    {
        $path = $this->cacheDir . '/' . preg_replace('/[^a-f0-9_.]/', '', $cacheKey);
        if (is_readable($path)) {
            $content = @file_get_contents($path);
            if ($content !== false && mb_strlen($content) > 0) {
                return $content;
            }
        }
        return null;
    }

    /**
     * Write to extraction cache.
     */
    private function writeCache(string $cacheKey, string $text): bool
    {
        $path = $this->cacheDir . '/' . preg_replace('/[^a-f0-9_.]/', '', $cacheKey);
        $tmp = $path . '.tmp';
        
        $result = @file_put_contents($tmp, $text, LOCK_EX);
        if ($result === false) {
            return false;
        }

        return @rename($tmp, $path);
    }

    /**
     * Validate page range (for consistency checking).
     * @return array{ok:bool,error?:string,pdf_start?:int,pdf_end?:int}
     */
    public function validatePrintedPageRange(
        int $printedStart,
        int $printedEnd,
        int $pageOffset,
        int $pdfPageCount
    ): array {
        if ($printedStart <= 0 || $printedEnd <= 0) {
            return ['ok' => false, 'error' => 'Page numbers must be positive.'];
        }
        if ($printedEnd < $printedStart) {
            return ['ok' => false, 'error' => 'End page cannot be smaller than start page.'];
        }

        $pdfStart = $printedStart + $pageOffset;
        $pdfEnd = $printedEnd + $pageOffset;

        if ($pdfStart < 1 || $pdfEnd < $pdfStart) {
            return [
                'ok' => false,
                'error' => 'Calculated PDF page range is invalid. Check page offset.',
            ];
        }
        if ($pdfStart > $pdfPageCount || $pdfEnd > $pdfPageCount) {
            return [
                'ok' => false,
                'error' => 'Requested pages exceed PDF length (PDF has ' . $pdfPageCount . ' pages).',
            ];
        }

        return ['ok' => true, 'pdf_start' => $pdfStart, 'pdf_end' => $pdfEnd];
    }
}
