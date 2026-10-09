<?php

declare(strict_types=1);

// The CLI image used for repository tests does not load mysqli. The generator
// only needs the type to be declared for this policy-level test.
if (!class_exists('mysqli')) {
    class mysqli {}
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int
    {
        return strlen($value);
    }
}

require_once __DIR__ . '/../includes/book_questions/BookQuestionGenerator.php';

/** @param bool $condition */
function expectGenerationPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectGenerationPolicy(BookQuestionGenerator::MAX_MCQ_TOTAL === 100, 'MCQ limit must be 100.');
expectGenerationPolicy(BookQuestionGenerator::MAX_MCQ_BATCH === 20, 'Each MCQ request must contain at most 20 questions.');
expectGenerationPolicy(BookQuestionGenerator::MAX_SHORT_TOTAL === 100, 'Short-question limit must be 100.');
expectGenerationPolicy(BookQuestionGenerator::MAX_LONG_TOTAL === 100, 'Long-question limit must be 100.');

$reflection = new ReflectionClass(BookQuestionGenerator::class);
$method = $reflection->getMethod('maxBatchRequestsForType');
$generator = $reflection->newInstanceWithoutConstructor();

$requestBudget = $method->invoke($generator, ['targets' => ['mcq' => 100]], 'mcq', 10);
expectGenerationPolicy($requestBudget === 108, 'The request budget must allow 100 target items plus replacement attempts.');

$promptMethod = $reflection->getMethod('buildPrompt');
$storedAndGenerated = [];
for ($index = 1; $index <= 100; $index++) {
    $storedAndGenerated[] = 'Previously generated question ' . $index;
}
$prompt = $promptMethod->invoke(
    $generator,
    ['class_name' => 'Class 9', 'book_name' => 'Science'],
    ['chapter_no' => 1, 'chapter_name' => 'Cells'],
    'short',
    5,
    'Chapter content',
    $storedAndGenerated,
    false
);
expectGenerationPolicy(
    str_contains($prompt, 'Previously generated question 100'),
    'The AI prompt must receive the complete stored/generated exclusion list.'
);

$progress = $reflection->getMethod('buildProgress')->invoke($generator, [
    'status' => 'generating',
    'current_chapter_index' => 1,
    'chapters' => [
        [
            'chapter_no' => 1,
            'chapter_name' => 'First chapter',
            'duplicate_candidates' => [[
                'id' => 'first-candidate',
                'question_text' => 'First duplicate candidate',
            ]],
        ],
        [
            'chapter_no' => 2,
            'chapter_name' => 'Second chapter',
            'duplicate_candidates' => [[
                'id' => 'second-candidate',
                'question_text' => 'Second duplicate candidate',
            ]],
        ],
    ],
]);
expectGenerationPolicy(
    count($progress['duplicate_candidates'] ?? []) === 2,
    'Duplicate review must include candidates from every chapter in the job.'
);

echo "BookQuestionGenerationPolicy tests passed.\n";
