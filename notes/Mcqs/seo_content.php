<?php

function alh_mcqs_normalize_key(string $value): string
{
    $key = strtolower(trim($value));
    $key = preg_replace('/[^a-z0-9]+/', ' ', $key);
    return trim((string) preg_replace('/\s+/', ' ', (string) $key));
}

function alh_mcqs_class_profile(string $className): array
{
    if (preg_match('/\b12\b|second\s*year|part\s*2/i', $className)) {
        return [
            'stage' => 'Intermediate Part 2',
            'purpose' => 'consolidating advanced concepts while balancing board revision, college assessments and admission preparation',
            'routine' => 'Use mixed recall and application questions, then revisit the chapters where formulas, processes or closely related terms are confused.',
            'check' => 'Pay special attention to links between earlier intermediate concepts and the more advanced applications introduced in the final year.',
        ];
    }

    if (preg_match('/\b11\b|first\s*year|part\s*1/i', $className)) {
        return [
            'stage' => 'Intermediate Part 1',
            'purpose' => 'adjusting from matric recall to deeper definitions, multi-step reasoning and subject-specific terminology',
            'routine' => 'Start with one chapter at a time and explain the reason for each answer before increasing the size of the practice set.',
            'check' => 'Check whether an error came from unfamiliar terminology, an incomplete concept or applying a familiar rule in the wrong situation.',
        ];
    }

    if (preg_match('/\b10\b|matric\s*part\s*2/i', $className)) {
        return [
            'stage' => 'Matric Part 2',
            'purpose' => 'strengthening final-year matric concepts and preparing for cumulative school and board assessments',
            'routine' => 'Alternate chapter revision with short mixed tests so that older material remains active while new chapters are completed.',
            'check' => 'Review textbook exceptions, diagrams, units and similar-looking statements because these often cause avoidable objective-question errors.',
        ];
    }

    if (preg_match('/\b9\b|matric\s*part\s*1/i', $className)) {
        return [
            'stage' => 'Matric Part 1',
            'purpose' => 'building the definitions, symbols, rules and study habits needed for later matric work',
            'routine' => 'Read a small textbook section, answer a focused set of MCQs and correct the underlying idea before starting the next section.',
            'check' => 'Separate new terms that look similar and connect every formula, rule or definition with at least one textbook example.',
        ];
    }

    return [
        'stage' => $className,
        'purpose' => 'developing reliable recall and understanding through focused objective-question practice',
        'routine' => 'Work through one manageable topic at a time and review the reason for every incorrect answer.',
        'check' => 'Compare uncertain answers with the current textbook or guidance provided by the relevant teacher.',
    ];
}

function alh_mcqs_subject_profile(string $bookName): array
{
    $key = alh_mcqs_normalize_key($bookName);
    $profiles = [
        'physics' => [
            'label' => 'Physics',
            'question_sources' => 'definitions, physical quantities, SI units, laws, graphs, diagrams, formulas and the interpretation of numerical situations',
            'method' => 'write the known quantities and units before choosing a formula, and distinguish a law from the example used to demonstrate it',
            'common_errors' => 'mixing scalar and vector quantities, overlooking units, reversing cause and effect, or choosing a familiar formula without checking its conditions',
            'review' => 'definitions, symbols, unit conversions, graph shapes, diagram labels and the meaning of each term in a formula',
        ],
        'chemistry' => [
            'label' => 'Chemistry',
            'question_sources' => 'chemical symbols, atomic structure, periodic trends, bonding, equations, reaction conditions, laboratory observations and calculations',
            'method' => 'connect each chemical fact with a particle-level explanation and check symbols, charges, valencies and equation balance carefully',
            'common_errors' => 'confusing related trends, using an incorrect chemical symbol, missing a reaction condition or treating similar compounds as identical',
            'review' => 'definitions, equations, periodic relationships, structures, reaction conditions, examples and exceptions stated in the textbook',
        ],
        'biology' => [
            'label' => 'Biology',
            'question_sources' => 'biological terminology, structures, functions, process sequences, classifications, examples, comparisons and labelled diagrams',
            'method' => 'link each structure with its function and arrange multi-stage processes in the correct biological sequence',
            'common_errors' => 'interchanging similar terms, skipping a process step, assigning a function to the wrong structure or relying on a diagram without reading its labels',
            'review' => 'key terms, process stages, structure-function relationships, classifications, examples, differences and diagram labels',
        ],
        'mathematics' => [
            'label' => 'Mathematics',
            'question_sources' => 'definitions, formulas, identities, properties, signs, graphs, theorem statements and short calculations',
            'method' => 'identify the rule being tested, perform the essential working separately and check signs, restrictions and units before selecting an option',
            'common_errors' => 'sign mistakes, using a formula outside its conditions, confusing similar properties or selecting an answer from mental arithmetic without verification',
            'review' => 'formulas, identities, definitions, graph behavior, theorem conditions and representative solved examples',
        ],
        'computer science' => [
            'label' => 'Computer Science',
            'question_sources' => 'hardware and software concepts, data representation, networks, algorithms, programming syntax, logic, databases and digital responsibility',
            'method' => 'trace instructions in order, distinguish related technical terms and test small code or logic examples step by step',
            'common_errors' => 'confusing hardware with software roles, ignoring syntax, skipping an algorithm step or predicting output without tracing variable changes',
            'review' => 'technical definitions, comparisons, diagrams, algorithm stages, code traces, database terms and practical examples',
        ],
        'english' => [
            'label' => 'English',
            'question_sources' => 'vocabulary, grammar, sentence structure, comprehension, literary terms, textbook details and the meaning of words in context',
            'method' => 'read the complete sentence or passage before deciding, because context can change the correct meaning or grammatical form',
            'common_errors' => 'choosing a familiar word without checking context, overlooking tense agreement, confusing parts of speech or recalling a text detail imprecisely',
            'review' => 'vocabulary in context, grammar rules, sentence correction, lesson themes, characters, references and comprehension evidence',
        ],
        'urdu' => [
            'label' => 'Urdu',
            'question_sources' => 'prose and poetry details, vocabulary, grammar, central ideas, authors, references, meanings and literary forms',
            'method' => 'connect each answer with the relevant passage, verse, grammar rule or textbook context instead of memorising isolated options',
            'common_errors' => 'mixing authors or lessons, selecting a near-synonym with the wrong context, overlooking grammar details or confusing a central idea with a minor detail',
            'review' => 'lesson summaries, poetic references, vocabulary, grammar, authors, important lines and central themes',
        ],
        'islamiat' => [
            'label' => 'Islamiat',
            'question_sources' => 'Quranic teachings, Hadith, beliefs, worship, Seerah, Islamic history, ethics, personalities and important events',
            'method' => 'place teachings and events in their correct context and verify references, names and sequences carefully',
            'common_errors' => 'mixing historical events, confusing personalities, recalling an incomplete teaching or selecting a statement that lacks the required context',
            'review' => 'key teachings, translations, references, events, personalities, ethical applications and differences between related concepts',
        ],
        'pakistan studies' => [
            'label' => 'Pakistan Studies',
            'question_sources' => 'historical events, dates, personalities, constitutional developments, geography, resources, culture and national institutions',
            'method' => 'build timelines for history and connect geographical facts with maps, locations, resources and their effects',
            'common_errors' => 'confusing dates or personalities, placing events in the wrong sequence, mixing constitutional milestones or assigning a resource to the wrong region',
            'review' => 'timelines, maps, key personalities, constitutional milestones, definitions, locations and cause-and-effect relationships',
        ],
    ];

    $aliases = [
        'math' => 'mathematics',
        'maths' => 'mathematics',
        'computer' => 'computer science',
        'computer studies' => 'computer science',
        'pak studies' => 'pakistan studies',
        'islamic studies' => 'islamiat',
        'islamiyat' => 'islamiat',
    ];
    $key = $aliases[$key] ?? $key;

    return $profiles[$key] ?? [
        'label' => trim($bookName) !== '' ? trim($bookName) : 'the selected subject',
        'question_sources' => 'definitions, terminology, examples, comparisons, textbook details and applications from the available chapters',
        'method' => 'identify the exact concept being tested and connect the selected option with evidence from the relevant lesson',
        'common_errors' => 'confusing related terms, overlooking an exception, recalling only part of a definition or answering without checking the question wording',
        'review' => 'chapter summaries, key terms, examples, diagrams, comparisons and mistakes from previous practice',
    ];
}

function alh_mcqs_chapter_profile(string $chapterName, array $subject): array
{
    if ($chapterName === '') {
        return [
            'heading' => "Planning chapter-wise {$subject['label']} revision",
            'focus' => "The chapter list lets learners divide {$subject['label']} into smaller units instead of mixing the entire book in one session.",
            'activity' => "Choose a chapter that has already been studied, complete its available questions, and record which parts of {$subject['review']} need another reading.",
        ];
    }

    $key = alh_mcqs_normalize_key($chapterName);
    $rules = [
        '/motion|force|dynamics|kinematics|work|energy|power/' => [
            'focus' => 'relationships between physical quantities, direction, units, laws, graphs and the conditions under which an equation applies',
            'activity' => 'Sketch a simple situation or graph for difficult questions and check whether the selected option agrees with both the formula and the physical meaning.',
        ],
        '/wave|sound|light|optic|electro|current|magnet/' => [
            'focus' => 'definitions, diagrams, measurable quantities, component behavior and relationships between changing physical conditions',
            'activity' => 'Label the relevant diagram or circuit and explain what changes, what remains constant and why before selecting an answer.',
        ],
        '/atom|bond|periodic|reaction|acid|base|organic|hydrocarbon/' => [
            'focus' => 'particles, structures, symbols, trends, equations, reaction conditions and the reasons substances behave differently',
            'activity' => 'Write the relevant symbol, structure or balanced equation and compare each option with the rule demonstrated by it.',
        ],
        '/cell|tissue|enzyme|nutrition|transport|respiration|reproduction|genetic|ecology/' => [
            'focus' => 'biological structures, their functions, process order, terminology, examples and cause-and-effect relationships',
            'activity' => 'Create a short sequence or structure-function table and use it to eliminate options that belong to a different stage or organ.',
        ],
        '/set|number|algebra|equation|matrix|quadratic|geometry|trigon|probability|statistic/' => [
            'focus' => 'definitions, notation, properties, formulas, restrictions and the logical steps needed to reach a valid result',
            'activity' => 'Complete the smallest useful calculation on paper and test whether the answer satisfies the original condition.',
        ],
        '/program|algorithm|data|database|network|computer|logic|software|hardware/' => [
            'focus' => 'technical vocabulary, system roles, ordered procedures, data handling, logic and the output of instructions',
            'activity' => 'Trace the process one step at a time and write how the input, stored value or output changes at each stage.',
        ],
        '/grammar|sentence|tense|voice|narration|comprehension|poem|poetry|prose/' => [
            'focus' => 'meaning in context, grammar rules, textual evidence, vocabulary and the distinction between closely related language choices',
            'activity' => 'Read the complete sentence or passage, identify the rule or evidence, and then compare the effect of each possible answer.',
        ],
    ];

    foreach ($rules as $pattern => $profile) {
        if (preg_match($pattern, $key)) {
            return [
                'heading' => "{$chapterName}: what to focus on",
                'focus' => ucfirst($profile['focus']) . '.',
                'activity' => $profile['activity'],
            ];
        }
    }

    return [
        'heading' => "{$chapterName}: focused MCQ revision",
        'focus' => "This chapter should be reviewed through its main definitions, examples, diagrams, comparisons and links with earlier {$subject['label']} concepts.",
        'activity' => 'Write a one-sentence reason for each corrected answer and note the textbook heading where the concept is explained.',
    ];
}

function alh_mcqs_seo_content(string $className = 'Class 9, 10, 11 and 12', string $bookName = 'all subjects', string $chapterName = ''): void
{
    $class = alh_mcqs_class_profile($className);
    $subject = alh_mcqs_subject_profile($bookName);
    $chapter = alh_mcqs_chapter_profile($chapterName, $subject);
    $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $classText = $e($className);
    $subjectText = $e($subject['label']);
    $chapterText = $e($chapterName);
    ?>
    <article class="alh-mcq-section alh-seo-content">
        <h2><?= $classText ?> <?= $subjectText ?> MCQ Preparation<?= $chapterName !== '' ? ': ' . $chapterText : '' ?></h2>
        <p>
            This page supports <strong><?= $e($class['stage']) ?></strong> practice in <strong><?= $subjectText ?></strong>.
            At this stage, the main purpose is <?= $e($class['purpose']) ?>. The questions available here should be
            used with the current textbook and the instructions issued by the learner's school or examination board.
        </p>

        <h3>What <?= $subjectText ?> MCQs can test</h3>
        <p>
            In <?= $subjectText ?>, objective questions commonly draw on <?= $e($subject['question_sources']) ?>.
            A useful answer is based on the exact wording and concept, not simply on recognising a familiar option.
            For this subject, learners should <?= $e($subject['method']) ?>.
        </p>

        <h3><?= $e($chapter['heading']) ?></h3>
        <p><?= $e($chapter['focus']) ?></p>
        <p><?= $e($chapter['activity']) ?></p>

        <h3>Common mistakes for <?= $classText ?> learners</h3>
        <p>
            In this subject, frequent errors include <?= $e($subject['common_errors']) ?>.
            <?= $e($class['check']) ?> When the page marks an answer as incorrect, the next step should be to identify
            the mistaken idea and verify it, rather than memorising the displayed answer letter.
        </p>

        <h3>A practical revision routine</h3>
        <p>
            <?= $e($class['routine']) ?> Before attempting the MCQs, review <?= $e($subject['review']) ?>.
            Complete a manageable set without notes, check the result, and divide errors into missing knowledge,
            misunderstood concepts and careless reading. Revise the appropriate section before repeating the chapter.
        </p>

        <?php if ($chapterName !== ''): ?>
            <h3>How to review <?= $chapterText ?> after the quiz</h3>
            <p>
                List the questions you missed from <strong><?= $chapterText ?></strong> and write the textbook heading
                connected with each one. Explain the correct idea in your own words, then return later and answer a
                fresh set. This gives the chapter page a clear purpose: finding specific weaknesses in
                <?= $subjectText ?> rather than only collecting a score.
            </p>
        <?php else: ?>
            <h3>Choosing the next <?= $subjectText ?> chapter</h3>
            <p>
                Start with a chapter recently completed in class or one that caused difficulty in homework. The
                chapter cards show the material currently available in the database. Work through the chapters in
                the order that matches your course instead of assuming that every listed unit belongs to the latest
                syllabus for every board.
            </p>
        <?php endif; ?>

        <h3>Accuracy and responsible use</h3>
        <p>
            Explanations are provided where they exist in the question bank, but educational databases can contain
            incomplete or mistaken material. Confirm disputed answers with an authoritative textbook or teacher.
            Ahmad Learning Hub provides a practice resource and does not claim that a question will appear in an
            examination or that every available item represents an official board question.
        </p>
    </article>
    <?php
}
?>
