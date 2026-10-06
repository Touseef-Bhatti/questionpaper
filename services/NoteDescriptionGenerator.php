<?php
/**
 * Generate search-friendly class-note descriptions with the configured Gemini model.
 * The API key is read from the server environment and is never sent to the browser.
 */
require_once __DIR__ . '/../questionPaperFromTopic/GeminiClient.php';

class NoteDescriptionGenerator
{
    /**
     * @param array{title?:string,class?:string,subject?:string,chapter?:string} $context
     * @return array{ok:bool,description?:string,error?:string}
     */
    public static function generate(array $context): array
    {
        $apiKey = trim((string) EnvLoader::get('GEMINIAPIKEYFORBOOKQUESTIONS', ''));
        $model = trim((string) EnvLoader::get('GEMINIMODELFORBOOKQUESTIONS', 'gemini-3.1-flash-lite'));

        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'The Gemini description service is not configured.'];
        }

        $title = self::limit((string) ($context['title'] ?? ''), 255);
        $class = self::limit((string) ($context['class'] ?? ''), 20);
        $subject = self::limit((string) ($context['subject'] ?? ''), 100);
        $chapter = self::limit((string) ($context['chapter'] ?? ''), 255);
        $chapterLabel = $chapter !== '' ? $chapter : 'all chapters or general notes';

        $prompt = <<<PROMPT
Write a useful on-page SEO and GEO description for an educational study-note page.

Note details:
- Title: {$title}
- Class: {$class}
- Book/subject: {$subject}
- Chapter or scope: {$chapterLabel}

Requirements:
1. Return exactly 5 or 6 short, natural-language lines separated by newline characters. The result must read like helpful educational copy, not a keyword list.
2. Follow this information order in the writing: Class, book/subject, chapter or scope, note title, then the solution or learning benefit.
3. Mention the class, book/subject, chapter/scope, and title clearly, but vary sentence structure so the description sounds natural and useful to a student.
4. For Mathematics or Math notes, naturally explain that the page provides the solution for the named title or exercise. Follow the path Class > book/subject > chapter > title > solution in the prose, without printing arrows or a list. Include the actual title in phrases such as "[title] solved exercise" and "[title] solution", along with "[chapter] notes" and "Class [class] [subject] notes" where they fit grammatically. Replace every bracketed placeholder with the real value; never output the brackets. Do not repeat a phrase just to add keywords.
5. For non-mathematics notes, describe the concepts, revision help, examples, or questions only when supported by the title and note context; do not claim that a solution is included unless the title indicates one.
6. Make the text useful for Google Search and answer engines: clearly explain who the notes help, what the student can learn or revise, and what the page covers.
7. Do not invent syllabus claims, authors, exam dates, marks, downloads, completeness, or features not provided above.
8. Do not use a heading, bullets, numbering, markdown, quotation marks, emojis, or the phrase "AI-generated".
PROMPT;

        try {
            $result = GeminiClient::callGenerateContent(
                $apiKey,
                $model,
                [['text' => $prompt]],
                512,
                60,
                false
            );
        } catch (Throwable $e) {
            error_log('Note description generation failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Gemini could not generate the description right now. Please try again.'];
        }

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => 'Gemini could not generate the description right now. Please try again.'];
        }

        $description = self::clean((string) ($result['text'] ?? ''));
        if ($description === '') {
            return ['ok' => false, 'error' => 'Gemini returned an empty description. Please try again.'];
        }

        return ['ok' => true, 'description' => self::limit($description, 2500)];
    }

    private static function clean(string $text): string
    {
        $text = preg_replace('/```(?:text|plain)?/i', '', $text) ?? $text;
        $text = str_replace('```', '', $text);
        $text = preg_replace('/^\s*(?:[-*\x{2022}]|\d+[.)])\s*/mu', '', $text) ?? $text;
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn ($line) => $line !== ''));

        return trim(implode("\n", $lines));
    }

    private static function limit(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return substr($value, 0, $maxLength);
    }
}
