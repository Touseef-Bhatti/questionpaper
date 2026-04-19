<?php
/**
 * Extract a JSON object from Gemini (or other LLM) text: markdown fences, prose, truncated edges.
 */
class GeminiJsonExtractor
{
    /**
     * @return array|null Decoded associative array or null
     */
    public static function parseObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip UTF-8 BOM
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $candidates = [];

        // 1) Full string after stripping outer markdown fence once
        $stripped = self::stripMarkdownFences($raw);
        $candidates[] = $stripped;
        $candidates[] = $raw;

        // 2) Balanced { ... } from each candidate
        foreach ($candidates as $text) {
            $slice = self::extractBalancedObject($text);
            if ($slice !== null) {
                $decoded = self::tryJsonDecode($slice);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // 3) Last resort: first {...} regex (greedy) — can fail on nested strings; balanced already tried
        if (preg_match('/\{[\s\S]*\}/s', $stripped, $m)) {
            $decoded = self::tryJsonDecode($m[0]);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function stripMarkdownFences(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^```(?:json|JSON)?\s*\R?/m', '', $s);
        $s = preg_replace('/\R?```\s*$/m', '', $s);
        return trim($s);
    }

    /**
     * Extract first top-level JSON object using brace counting (respects strings).
     */
    private static function extractBalancedObject(string $s): ?string
    {
        $start = strpos($s, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $c = $s[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($inString) {
                if ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
                continue;
            }
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }
        return null;
    }

    private static function tryJsonDecode(string $json): ?array
    {
        $json = trim($json);
        $flags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;
        $decoded = json_decode($json, true, 512, $flags);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Common LLM issues: trailing commas before } or ]
        $fixed = preg_replace('/,\s*([\}\]])/', '$1', $json);
        if ($fixed !== $json) {
            $decoded = json_decode($fixed, true, 512, $flags);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Smart quotes → ASCII (UTF-8 bytes)
        $normalized = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"],
            ['"', '"', "'", "'"],
            $json
        );
        if ($normalized !== $json) {
            $decoded = json_decode($normalized, true, 512, $flags);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            $utf8 = @mb_convert_encoding($json, 'UTF-8', 'UTF-8');
            if ($utf8 !== false && $utf8 !== $json) {
                $decoded = json_decode($utf8, true, 512, $flags);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }
}
