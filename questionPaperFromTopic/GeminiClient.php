<?php
/**
 * Minimal Gemini REST client: generateContent, optional File API for large binaries.
 */
class GeminiClient
{
    /** ~3 MiB — stay under typical inline limits */
    public const INLINE_MAX_BYTES = 3145728;

    private static function sslVerifyPeer(): bool
    {
        if (class_exists('EnvLoader')) {
            $v = strtolower((string) EnvLoader::get('GEMINI_SSL_VERIFY', 'true'));
            return !in_array($v, ['0', 'false', 'no', 'off'], true);
        }
        return true;
    }

    public static function callGenerateContent(
        string $apiKey,
        string $model,
        array $parts,
        int $maxOutputTokens = 8192,
        int $timeoutSeconds = 180,
        bool $jsonMode = false
    ): array {
        $model = trim($model);
        if ($model === '') {
            return ['ok' => false, 'http' => 0, 'error' => 'Model not configured', 'raw' => null];
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        $generationConfig = [
            'temperature'     => 0.5,
            'topP'            => 0.9,
            'topK'            => 40,
            'maxOutputTokens' => $maxOutputTokens,
        ];
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $body = json_encode([
            'contents' => [['parts' => $parts]],
            'generationConfig' => $generationConfig,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['ok' => false, 'http' => $http, 'error' => 'Network error: ' . $curlErr, 'raw' => $raw];
        }
        if ($http !== 200) {
            $decoded = json_decode((string) $raw, true);
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $http);
            return ['ok' => false, 'http' => $http, 'error' => $msg, 'raw' => $raw];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'http' => $http, 'error' => 'Invalid API response body', 'raw' => $raw];
        }

        if (empty($decoded['candidates']) || !is_array($decoded['candidates'])) {
            $blockReason = $decoded['promptFeedback']['blockReason'] ?? '';
            $msg = $blockReason !== ''
                ? ('Request blocked: ' . $blockReason)
                : 'No response from AI (empty candidates). Try different content or try again.';
            return ['ok' => false, 'http' => $http, 'error' => $msg, 'raw' => $raw];
        }

        $candidate = $decoded['candidates'][0];
        $finishReason = $candidate['finishReason'] ?? '';

        $text = '';
        $partList = $candidate['content']['parts'] ?? [];
        if (is_array($partList)) {
            foreach ($partList as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        if ($text === '') {
            $fr = $finishReason !== '' ? $finishReason : 'UNKNOWN';
            return ['ok' => false, 'http' => $http, 'error' => 'AI returned no text (finish: ' . $fr . ')', 'raw' => $raw];
        }

        if ($finishReason === 'MAX_TOKENS') {
            // Allow caller to try parsing; JSON may be incomplete.
            return [
                'ok' => true,
                'http' => $http,
                'text' => $text,
                'finishReason' => $finishReason,
                'truncated' => true,
                'raw' => $raw,
            ];
        }

        if ($finishReason !== '' && $finishReason !== 'STOP') {
            // SAFETY, RECITATION, etc. — still try to use text if present
            if (in_array($finishReason, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
                return [
                    'ok' => false,
                    'http' => $http,
                    'error' => 'Generation stopped (' . $finishReason . '). Adjust the file or try again.',
                    'raw' => $raw,
                ];
            }
        }

        return [
            'ok' => true,
            'http' => $http,
            'text' => $text,
            'finishReason' => $finishReason,
            'raw' => $raw,
        ];
    }

    /**
     * Upload file (multipart), wait until ACTIVE, return file URI for file_data.
     *
     * @return array{ok:bool,error?:string,fileUri?:string,fileName?:string}
     */
    public static function uploadFileAndGetUri(string $apiKey, string $localPath, string $mime, int $timeoutSeconds = 300): array
    {
        if (!is_readable($localPath)) {
            return ['ok' => false, 'error' => 'Upload path not readable'];
        }
        $displayName = 'doc_' . bin2hex(random_bytes(6));
        $url = 'https://generativelanguage.googleapis.com/upload/v1beta/files?key=' . rawurlencode($apiKey);

        $meta = json_encode(['file' => ['displayName' => $displayName]]);
        $boundary = 'BOUNDARY_' . bin2hex(random_bytes(12));
        $fileContents = file_get_contents($localPath);
        if ($fileContents === false) {
            return ['ok' => false, 'error' => 'Could not read file'];
        }

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $meta . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mime}\r\n\r\n"
            . $fileContents . "\r\n"
            . "--{$boundary}--\r\n";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'X-Goog-Upload-Protocol: multipart',
                'Content-Type: multipart/related; boundary=' . $boundary,
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200) {
            $d = json_decode((string) $raw, true);
            $msg = $d['error']['message'] ?? ('File upload failed HTTP ' . $http);
            return ['ok' => false, 'error' => $msg];
        }
        $d = json_decode((string) $raw, true);
        $file = $d['file'] ?? null;
        if (!is_array($file)) {
            return ['ok' => false, 'error' => 'Invalid upload response'];
        }
        $name = $file['name'] ?? '';
        $uri = $file['uri'] ?? '';
        if ($name === '' || $uri === '') {
            return ['ok' => false, 'error' => 'Missing file reference from API'];
        }

        $deadline = time() + 120;
        while (time() < $deadline) {
            $st = self::getFileStatus($apiKey, $name);
            if (!$st['ok']) {
                return ['ok' => false, 'error' => $st['error'] ?? 'File status check failed'];
            }
            $state = $st['state'] ?? '';
            if ($state === 'ACTIVE') {
                return ['ok' => true, 'fileUri' => $uri, 'fileName' => $name];
            }
            if ($state === 'FAILED') {
                return ['ok' => false, 'error' => 'File processing failed on AI service'];
            }
            usleep(400000);
        }
        return ['ok' => false, 'error' => 'File did not become ready in time'];
    }

    /**
     * @return array{ok:bool,state?:string,error?:string}
     */
    public static function getFileStatus(string $apiKey, string $fileResourceName): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/' . $fileResourceName . '?key=' . rawurlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http !== 200) {
            $d = json_decode((string) $raw, true);
            return ['ok' => false, 'error' => $d['error']['message'] ?? ('HTTP ' . $http)];
        }
        $d = json_decode((string) $raw, true);
        return ['ok' => true, 'state' => $d['state'] ?? ''];
    }

    public static function deleteFile(string $apiKey, string $fileResourceName): void
    {
        if ($fileResourceName === '') {
            return;
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/' . $fileResourceName . '?key=' . rawurlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Build parts: instruction text + inline or file_data.
     *
     * @return array{parts:array,fileNameForCleanup:?string,error?:string}
     */
    public static function buildMultimodalParts(string $apiKey, string $instructionText, string $localPath, string $mime): array
    {
        $size = @filesize($localPath);
        if ($size === false) {
            return ['parts' => [], 'fileNameForCleanup' => null, 'error' => 'Could not read file size'];
        }

        if ($size <= self::INLINE_MAX_BYTES) {
            $b64 = base64_encode((string) file_get_contents($localPath));
            if ($b64 === '') {
                return ['parts' => [], 'fileNameForCleanup' => null, 'error' => 'Empty file'];
            }
            return [
                'parts' => [
                    ['text' => $instructionText],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
                ],
                'fileNameForCleanup' => null,
            ];
        }

        $up = self::uploadFileAndGetUri($apiKey, $localPath, $mime);
        if (!$up['ok']) {
            return ['parts' => [], 'fileNameForCleanup' => null, 'error' => $up['error'] ?? 'Upload failed'];
        }
        return [
            'parts' => [
                ['text' => $instructionText],
                ['file_data' => ['mime_type' => $mime, 'file_uri' => $up['fileUri']]],
            ],
            'fileNameForCleanup' => $up['fileName'] ?? null,
        ];
    }
}
