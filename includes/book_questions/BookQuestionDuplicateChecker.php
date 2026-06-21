<?php
/**
 * Duplicate detection for book-generated questions.
 */
class BookQuestionDuplicateChecker
{
    public const SIMILARITY_THRESHOLD = 82.0;

    public static function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
        $text = str_replace(
            ["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", '`'],
            ["'", "'", '"', '"', "'"],
            $text
        );
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    public static function stripQuestionPrefix(string $normalized): string
    {
        return trim((string) preg_replace(
            '/^(what is|what are|define|explain|describe|discuss|what do you mean by|how does|how do|name|list|state|give|write)\s+/u',
            '',
            $normalized
        ));
    }

    public static function areSimilar(string $a, string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        if ($na === $nb) {
            return true;
        }

        $sa = self::stripQuestionPrefix($na);
        $sb = self::stripQuestionPrefix($nb);
        if ($sa !== '' && $sb !== '' && $sa === $sb) {
            return true;
        }

        similar_text($na, $nb, $pct);
        if ($pct >= self::SIMILARITY_THRESHOLD) {
            return true;
        }

        if ($sa !== '' && $sb !== '') {
            similar_text($sa, $sb, $pct2);
            if ($pct2 >= 85.0) {
                return true;
            }
        }

        return false;
    }

    public static function isDuplicate(string $candidate, array $existingTexts): bool
    {
        foreach ($existingTexts as $existing) {
            if (!is_string($existing) || $existing === '') {
                continue;
            }
            if (self::areSimilar($candidate, $existing)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public static function loadExistingQuestionTexts(mysqli $conn, int $chapterId): array
    {
        $texts = [];

        $stmt = $conn->prepare('SELECT question FROM mcqs_from_book WHERE chapter_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $texts[] = (string) ($row['question'] ?? '');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("SELECT question_text FROM questions_from_book WHERE chapter_id = ? AND question_type IN ('short','long')");
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $texts[] = (string) ($row['question_text'] ?? '');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare('SELECT question FROM mcqs WHERE chapter_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $texts[] = (string) ($row['question'] ?? '');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("SELECT question_text FROM questions WHERE chapter_id = ? AND question_type IN ('short','long')");
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $texts[] = (string) ($row['question_text'] ?? '');
            }
            $stmt->close();
        }

        return array_values(array_filter($texts, static function ($t) {
            return trim((string) $t) !== '';
        }));
    }
}
