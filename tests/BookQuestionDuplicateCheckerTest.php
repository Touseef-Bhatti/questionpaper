<?php

declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string
    {
        return strtolower($value);
    }
}

require_once __DIR__ . '/../includes/book_questions/BookQuestionDuplicateChecker.php';

/** @param bool $condition */
function expectDuplicateCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectDuplicateCheck(
    BookQuestionDuplicateChecker::areSimilar(
        'What is the function of the cell membrane?',
        'Explain the function of the cell membrane.'
    ),
    'Equivalent question wording must be detected as a duplicate.'
);

expectDuplicateCheck(
    BookQuestionDuplicateChecker::areSimilar(
        'Define osmosis.',
        'What is meant by osmosis?'
    ),
    'Definition-question prefixes must normalize to the same topic.'
);

expectDuplicateCheck(
    BookQuestionDuplicateChecker::areSimilar(
        'What are three functions of roots?',
        'List three functions of roots.'
    ),
    'Equivalent list-question wording must be detected as a duplicate.'
);

expectDuplicateCheck(
    BookQuestionDuplicateChecker::areSimilar(
        'What is the process of photosynthesis in green plants?',
        'Explain the process by which photosynthesis occurs in green plants.'
    ),
    'A close rephrasing with the same topic terms must be detected as a duplicate.'
);

expectDuplicateCheck(
    !BookQuestionDuplicateChecker::areSimilar(
        'What is the function of the cell membrane?',
        'What is the function of the cell wall?'
    ),
    'Questions about different structures must not be rejected because their template is similar.'
);

expectDuplicateCheck(
    !BookQuestionDuplicateChecker::areSimilar(
        'What are the causes of air pollution?',
        'What are the effects of air pollution?'
    ),
    'Cause and effect questions must remain distinct.'
);

echo "BookQuestionDuplicateChecker tests passed.\n";
