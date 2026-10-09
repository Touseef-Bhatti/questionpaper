<?php
/**
 * Duplicate detection for book-generated questions.
 */
class BookQuestionDuplicateChecker
{
    /**
     * A question is considered a near duplicate only when at least 75% of its
     * meaningful words are shared. Character-level similarity is deliberately
     * not used: question templates such as "What is the function of..." can
     * make different topics look similar even when their key terms differ.
     */
    public const SIMILARITY_THRESHOLD = 0.75;
    private const MIN_SHARED_CONTENT_TOKENS = 2;

    /**
     * Words that carry question grammar rather than the topic being tested.
     * Keep domain words such as "cause", "effect", "function", and "process"
     * so questions about different concepts are not collapsed together.
     *
     * @var string[]
     */
    private const QUESTION_STOP_WORDS = [
        'a', 'an', 'and', 'are', 'at', 'be', 'been', 'being', 'by', 'do',
        'does', 'for', 'from', 'how', 'in', 'is', 'it', 'of', 'on', 'or',
        'the', 'to', 'was', 'were', 'what', 'when', 'where', 'which', 'who',
        'why', 'with',
    ];

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
            '/^(which of the following(?: is| are)?|what do you mean by|what is meant by|what is the definition of|differentiate between|what is|what are|what was|what were|how does|how do|how did|why does|why do|why did|define|explain|describe|discuss|name|list|state|give|write|identify|mention|outline|tell)\s+/u',
            '',
            $normalized
        ));
    }

    /**
     * Return unique topic-bearing tokens in a normalized question.
     *
     * @return string[]
     */
    private static function contentTokens(string $normalized): array
    {
        $stripped = self::stripQuestionPrefix($normalized);
        preg_match_all('/[\p{L}\p{N}]+/u', $stripped, $matches);
        $stopWords = array_flip(self::QUESTION_STOP_WORDS);
        $tokens = [];

        foreach ($matches[0] ?? [] as $token) {
            if (!isset($stopWords[$token])) {
                $tokens[$token] = true;
            }
        }

        return array_keys($tokens);
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

        $tokensA = self::contentTokens($na);
        $tokensB = self::contentTokens($nb);
        if (count($tokensA) < self::MIN_SHARED_CONTENT_TOKENS
            || count($tokensB) < self::MIN_SHARED_CONTENT_TOKENS) {
            return false;
        }

        $shared = count(array_intersect($tokensA, $tokensB));
        if ($shared < self::MIN_SHARED_CONTENT_TOKENS) {
            return false;
        }

        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        if ($union === 0) {
            return false;
        }

        return ($shared / $union) >= self::SIMILARITY_THRESHOLD;
    }

    public static function isDuplicate(string $candidate, array $existingTexts): bool
    {
        return self::findDuplicateMatch($candidate, $existingTexts) !== null;
    }

    /**
     * Return the stored question that caused a candidate to be rejected.
     *
     * @param string[] $existingTexts
     */
    public static function findDuplicateMatch(string $candidate, array $existingTexts): ?string
    {
        foreach ($existingTexts as $existing) {
            if (!is_string($existing) || $existing === '') {
                continue;
            }
            if (self::areSimilar($candidate, $existing)) {
                return $existing;
            }
        }
        return null;
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

        // Pending drafts belong to the same question bank even before an
        // administrator approves them. Include them so repeated generation
        // jobs cannot recreate questions that are already awaiting review.
        $stmt = $conn->prepare("SELECT question_text FROM book_mcq_drafts WHERE chapter_id = ? AND status IN ('pending','approved')");
        if ($stmt) {
            $stmt->bind_param('i', $chapterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $texts[] = (string) ($row['question_text'] ?? '');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("SELECT question_text FROM book_question_drafts WHERE chapter_id = ? AND status IN ('pending','approved')");
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
