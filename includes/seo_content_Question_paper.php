<?php

/**
 * SEO and GEO content for class and subject question-paper pages.
 *
 * The generic fallback keeps this module usable when a new book is added
 * to the database without requiring a template change.
 */

function qpSeoNormalizeBook(string $bookName): string
{
    $key = strtolower(trim($bookName));
    $key = preg_replace('/[^a-z0-9]+/', ' ', $key);
    $key = trim(preg_replace('/\s+/', ' ', $key));

    $aliases = [
        'maths' => 'mathematics',
        'math' => 'mathematics',
        'computer' => 'computer science',
        'computer studies' => 'computer science',
        'pak studies' => 'pakistan studies',
        'pak study' => 'pakistan studies',
        'islamic studies' => 'islamiat',
        'islamiyat' => 'islamiat',
    ];

    return $aliases[$key] ?? $key;
}

function qpSeoClassOrdinal(int $classId): string
{
    if ($classId % 100 >= 11 && $classId % 100 <= 13) {
        return $classId . 'th';
    }

    return $classId . match ($classId % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
}

function qpSeoClassProfile(int $classId): array
{
    return match ($classId) {
        9 => [
            'stage' => 'Matric Part 1',
            'teaching_context' => 'the first year of matric study, where students are building new subject vocabulary, formal methods and reliable written-work habits',
            'paper_balance' => 'Give suitable space to definitions and basic applications, but include enough reasoning to show whether foundational ideas are understood.',
            'student_need' => 'Students at this stage benefit from clear wording, familiar textbook contexts and a gradual movement from direct recall to application.',
            'review_priority' => 'new terminology, core rules, symbols, basic diagrams and the steps used in standard examples',
        ],
        10 => [
            'stage' => 'Matric Part 2',
            'teaching_context' => 'the final matric year, where chapter mastery must be combined with cumulative revision and preparation for formal assessments',
            'paper_balance' => 'Combine chapter-specific questions with earlier skills that students must retain, while keeping the paper realistic for the announced syllabus and time.',
            'student_need' => 'Students need practice that reveals small factual errors as well as weaknesses in explanation, calculation and presentation.',
            'review_priority' => 'textbook exceptions, diagrams, formulas, comparisons and links between the current chapter and earlier matric concepts',
        ],
        11 => [
            'stage' => 'Intermediate Part 1',
            'teaching_context' => 'the transition from matric to intermediate study, where concepts become more detailed and answers require more precise subject language',
            'paper_balance' => 'Test essential recall alongside interpretation, multi-step reasoning and the ability to use new intermediate terminology correctly.',
            'student_need' => 'Students often know a general idea but need practice expressing it with the depth, method and vocabulary expected at college level.',
            'review_priority' => 'technical definitions, derivations or process stages, worked examples and distinctions between closely related concepts',
        ],
        12 => [
            'stage' => 'Intermediate Part 2',
            'teaching_context' => 'the concluding intermediate year, where advanced topics, board revision and preparation for further study often overlap',
            'paper_balance' => 'Include questions that connect earlier intermediate knowledge with final-year applications without allowing one difficult chapter to dominate the assessment.',
            'student_need' => 'Students benefit from timed practice, careful method checking and questions that reveal whether knowledge can be transferred to an unfamiliar example.',
            'review_priority' => 'advanced applications, linked concepts, formulas or evidence, common exceptions and complete long-answer organization',
        ],
        default => [
            'stage' => "Class {$classId}",
            'teaching_context' => 'the current stage of the selected course and the chapters that have actually been taught',
            'paper_balance' => 'Use a fair mix of recall, understanding and application that matches the available learning time.',
            'student_need' => 'Students need questions that are clearly worded and directly connected with the intended learning material.',
            'review_priority' => 'key terms, examples, applications and mistakes identified during earlier class work',
        ],
    };
}

function qpSeoClassSubjectFocus(int $classId, string $bookKey, string $subject): string
{
    $focus = [
        9 => [
            'mathematics' => 'For Class 9 Mathematics, a useful paper establishes whether learners can move from number and algebra rules into formal equations, geometry and introductory reasoning without losing essential working.',
            'physics' => 'For Class 9 Physics, the paper should establish a foundation in measurement, motion, forces and other introductory physical ideas while checking units, symbols and interpretation.',
            'chemistry' => 'For Class 9 Chemistry, the assessment should connect basic chemical language with atomic ideas, bonding, equations and observable properties rather than testing isolated facts only.',
            'biology' => 'For Class 9 Biology, the paper should check accurate terminology and the relationship between biological structures, functions and ordered life processes.',
            'computer science' => 'For Class 9 Computer Science, the assessment should distinguish basic system concepts and introduce logical problem solving through definitions, diagrams and short practical situations.',
        ],
        10 => [
            'mathematics' => 'For Class 10 Mathematics, the paper should combine fluent use of formulas with algebraic, geometric and trigonometric reasoning, including enough working to diagnose method errors.',
            'physics' => 'For Class 10 Physics, the assessment should connect laws and formulas with diagrams, circuits, waves or other physical situations and require consistent use of units.',
            'chemistry' => 'For Class 10 Chemistry, the paper should test how students connect reactions, chemical families, laboratory observations and applications with the underlying concepts.',
            'biology' => 'For Class 10 Biology, the assessment should check whether students can sequence life processes, compare systems and explain how structures support their biological functions.',
            'computer science' => 'For Class 10 Computer Science, the paper should combine technical definitions with algorithmic thinking, data handling and careful interpretation of code or system behavior.',
        ],
        11 => [
            'mathematics' => 'For Class 11 Mathematics, the paper should reflect the increased depth of intermediate algebra, functions, trigonometry and analytical methods while rewarding complete reasoning.',
            'physics' => 'For Class 11 Physics, the assessment should require precise definitions, vector or numerical reasoning, graph interpretation and clear links between physical laws and observations.',
            'chemistry' => 'For Class 11 Chemistry, the paper should connect atomic and molecular explanations with calculations, trends, bonding and the conditions that control chemical behavior.',
            'biology' => 'For Class 11 Biology, the assessment should use correct scientific vocabulary and test processes, classification, cellular organization and evidence-based comparisons.',
            'computer science' => 'For Class 11 Computer Science, the paper should test computing theory together with algorithms, data representation, logic and the ability to trace an ordered process.',
        ],
        12 => [
            'mathematics' => 'For Class 12 Mathematics, the paper should test whether students can select and combine advanced methods, justify steps and check that a final result satisfies the original problem.',
            'physics' => 'For Class 12 Physics, the assessment should combine advanced laws, numerical relationships, diagrams and applications while checking links with earlier intermediate concepts.',
            'chemistry' => 'For Class 12 Chemistry, the paper should balance detailed chemical knowledge with equations, structures, reaction reasoning, calculations and practical applications.',
            'biology' => 'For Class 12 Biology, the assessment should connect complex biological systems, regulation, inheritance or ecology with accurate terminology and organized explanation.',
            'computer science' => 'For Class 12 Computer Science, the paper should assess advanced system knowledge, program logic, databases or networks through both conceptual and applied questions.',
        ],
    ];

    return $focus[$classId][$bookKey]
        ?? "For Class {$classId} {$subject}, the paper should reflect the depth of the selected chapters and require students to use subject knowledge rather than depend on memorized answer patterns.";
}

function qpSeoSubjectProfiles(): array
{
    return [
        'mathematics' => [
            'label' => 'Mathematics',
            'focus' => 'algebra, geometry, trigonometry, formulas and step-based problem solving',
            'assessment' => 'concept checks, calculations and extended problems',
            'benefit' => 'balance routine practice with questions that test method, accuracy and reasoning',
            'concepts' => 'algebraic manipulation, equations, geometry, trigonometry, graphs, formulas and numerical reasoning',
            'skills' => 'selecting the correct method, showing complete working, applying formulas accurately and checking the final result',
            'mistakes' => 'sign errors, skipped working, incorrect formula substitution, weak diagram interpretation and answers written without units',
            'revision' => 'formula recall, worked examples, mixed exercises, timed calculations and careful review of each solution step',
        ],
        'physics' => [
            'label' => 'Physics',
            'focus' => 'physical laws, numerical problems, definitions, diagrams and scientific reasoning',
            'assessment' => 'conceptual MCQs, short explanations, numericals and detailed questions',
            'benefit' => 'combine theory and calculations from the selected chapters in one printable paper',
            'concepts' => 'measurements, motion, forces, energy, waves, electricity, physical laws, diagrams and numerical relationships',
            'skills' => 'connecting laws with situations, choosing equations, handling units, interpreting diagrams and explaining physical effects',
            'mistakes' => 'unit conversion errors, memorized definitions without understanding, incorrect equation choice and incomplete numerical working',
            'revision' => 'definitions, laws, formula practice, solved numericals, diagrams, unit conversions and comparisons between related concepts',
        ],
        'chemistry' => [
            'label' => 'Chemistry',
            'focus' => 'chemical concepts, equations, reactions, calculations and laboratory understanding',
            'assessment' => 'conceptual MCQs, equations, short responses and structured long questions',
            'benefit' => 'cover factual recall, chemical reasoning and equation-based practice',
            'concepts' => 'atomic structure, bonding, chemical reactions, equations, periodic trends, calculations, laboratory ideas and applications',
            'skills' => 'balancing equations, using chemical symbols, comparing substances, explaining trends and applying concepts to reactions',
            'mistakes' => 'unbalanced equations, incorrect symbols, confused valencies, missing reaction conditions and unsupported chemical explanations',
            'revision' => 'definitions, equations, periodic relationships, reaction conditions, numerical practice and links between structure and properties',
        ],
        'biology' => [
            'label' => 'Biology',
            'focus' => 'biological processes, terminology, diagrams, systems and applied concepts',
            'assessment' => 'terminology MCQs, short concepts, diagram-related prompts and descriptive questions',
            'benefit' => 'build a balanced test across definitions, processes and explanatory answers',
            'concepts' => 'cells, tissues, life processes, biological systems, classification, genetics, ecology, health and labelled diagrams',
            'skills' => 'using correct terminology, sequencing processes, comparing structures, interpreting diagrams and linking structure with function',
            'mistakes' => 'vague terminology, incomplete process steps, poorly labelled diagrams, mixed biological functions and overly general answers',
            'revision' => 'key terms, process sequences, labelled diagrams, comparisons, functions, examples and cause-and-effect relationships',
        ],
        'computer science' => [
            'label' => 'Computer Science',
            'focus' => 'computing concepts, problem solving, programming logic, data and digital systems',
            'assessment' => 'technical MCQs, definitions, logic questions and structured explanations',
            'benefit' => 'mix theory and practical computing concepts from the chosen chapters',
            'concepts' => 'computer systems, hardware, software, data, networks, algorithms, programming logic, databases and digital responsibility',
            'skills' => 'tracing logic, distinguishing technical terms, designing steps, interpreting code and explaining how computing systems operate',
            'mistakes' => 'confusing related terms, ignoring syntax, incomplete algorithms, weak output tracing and answers without technical examples',
            'revision' => 'definitions, diagrams, algorithm steps, code tracing, comparisons, practical examples and links between hardware and software',
        ],
        'english' => [
            'label' => 'English',
            'focus' => 'reading, grammar, vocabulary, writing and textbook comprehension',
            'assessment' => 'language MCQs, short responses, comprehension and extended writing',
            'benefit' => 'prepare a varied language paper from the available textbook chapters',
            'concepts' => 'prose, poetry, comprehension, grammar, vocabulary, sentence construction, translation and extended writing',
            'skills' => 'reading closely, identifying meaning, using accurate grammar, organizing ideas and supporting answers from the text',
            'mistakes' => 'copying without explanation, weak sentence structure, tense inconsistency, limited vocabulary and unplanned extended answers',
            'revision' => 'textbook themes, vocabulary, grammar rules, comprehension practice, paragraph structure and timed writing',
        ],
        'urdu' => [
            'label' => 'Urdu',
            'focus' => 'prose, poetry, grammar, vocabulary and written expression',
            'assessment' => 'textbook MCQs, short answers, explanation and descriptive writing',
            'benefit' => 'combine language knowledge and textbook understanding in one paper',
            'concepts' => 'prose, poetry, central ideas, explanation, grammar, vocabulary, comprehension and written expression',
            'skills' => 'interpreting passages, explaining verses, using appropriate vocabulary, applying grammar and organizing written responses',
            'mistakes' => 'incomplete explanation, weak textual reference, spelling errors, grammar mistakes and answers that miss the central idea',
            'revision' => 'lesson summaries, poetic explanation, vocabulary, grammar rules, important references and structured writing practice',
        ],
        'islamiat' => [
            'label' => 'Islamiat',
            'focus' => 'Quranic teachings, Hadith, beliefs, worship, ethics and Islamic history',
            'assessment' => 'knowledge MCQs, short explanations and detailed responses',
            'benefit' => 'assess key teachings and their understanding across selected chapters',
            'concepts' => 'Quranic guidance, Hadith, articles of faith, worship, Seerah, Islamic history, ethics and social responsibilities',
            'skills' => 'recalling authentic teachings, explaining their meaning, connecting principles with conduct and organizing evidence-based answers',
            'mistakes' => 'unsupported statements, mixed historical events, incomplete references, vague explanation and failure to connect teaching with practice',
            'revision' => 'key teachings, translations, references, important events, ethical applications and clear comparisons between related topics',
        ],
        'pakistan studies' => [
            'label' => 'Pakistan Studies',
            'focus' => 'history, geography, national development, citizenship and constitutional themes',
            'assessment' => 'fact-based MCQs, short concepts and analytical long questions',
            'benefit' => 'cover historical knowledge and broader national concepts in a structured paper',
            'concepts' => 'the Pakistan Movement, constitutional development, geography, resources, population, culture, citizenship and national institutions',
            'skills' => 'placing events in sequence, explaining causes and effects, reading maps, comparing developments and supporting historical arguments',
            'mistakes' => 'incorrect dates, confused personalities, weak chronology, unsupported opinions and incomplete geographical explanation',
            'revision' => 'timelines, key personalities, constitutional milestones, maps, resources, definitions and cause-and-effect relationships',
        ],
        'general science' => [
            'label' => 'General Science',
            'focus' => 'scientific concepts, everyday applications, observations and basic calculations',
            'assessment' => 'concept MCQs, short responses and explanatory questions',
            'benefit' => 'create broad science practice from the chapters selected by the teacher',
            'concepts' => 'matter, energy, living systems, environment, technology, observations, measurements and everyday scientific applications',
            'skills' => 'classifying information, interpreting evidence, applying concepts, using scientific vocabulary and explaining everyday phenomena',
            'mistakes' => 'memorized answers without application, confused terms, missing units, weak observation and unsupported conclusions',
            'revision' => 'definitions, examples, diagrams, simple calculations, experiments, applications and comparisons between scientific ideas',
        ],
    ];
}

function getQuestionPaperSeoContent(int $classId, string $bookName): array
{
    $bookKey = qpSeoNormalizeBook($bookName);
    $profiles = qpSeoSubjectProfiles();
    $displayBook = trim($bookName);
    $profile = $profiles[$bookKey] ?? [
        'label' => $displayBook,
        'focus' => 'the key concepts, terminology and learning outcomes in the selected textbook chapters',
        'assessment' => 'MCQs, short questions and detailed questions',
        'benefit' => 'create a focused assessment from the chapters and question types you choose',
        'concepts' => 'the main concepts, terminology, examples, applications and learning outcomes presented in the textbook',
        'skills' => 'recalling accurate information, explaining ideas clearly, applying knowledge and organizing complete written answers',
        'mistakes' => 'incomplete responses, confused terminology, weak examples and answers that do not address the exact question',
        'revision' => 'chapter summaries, key terms, examples, practice questions and review of mistakes from earlier assessments',
    ];

    $subject = $profile['label'];
    $ordinal = qpSeoClassOrdinal($classId);
    $level = in_array($classId, [9, 10], true) ? 'Matric' : 'Intermediate';
    $classProfile = qpSeoClassProfile($classId);
    $classSubjectFocus = qpSeoClassSubjectFocus($classId, $bookKey, $subject);
    $paperTypes = 'MCQs, short questions and long questions';

    $faqs = [
        [
            'question' => "How do I generate a {$ordinal} Class {$subject} question paper?",
            'answer' => "Select the required {$subject} chapters, review the automatically distributed question counts, and choose Next. You can also switch to manual selection before generating the paper.",
        ],
        [
            'question' => "Does this {$subject} paper generator follow Punjab Board requirements?",
            'answer' => "The generator is designed for {$ordinal} Class {$level} preparation and organizes {$paperTypes} in a Punjab Board and BISE-friendly format. Teachers should still confirm the final paper against their current board scheme.",
        ],
        [
            'question' => "Can I make a chapter-wise {$subject} test?",
            'answer' => "Yes. You can select one chapter, several chapters or all chapters currently available in the question bank and distribute each question type across that selection.",
        ],
        [
            'question' => "What should a Class {$classId} {$subject} paper emphasize?",
            'answer' => "{$classProfile['paper_balance']} Teachers should adapt the final selection to the topics taught and the time available.",
        ],
        [
            'question' => 'Can the generated question paper be printed?',
            'answer' => 'Yes. After reviewing the selected questions, the completed paper can be prepared for printing or exported using the download options available in the generator.',
        ],
    ];

    $keywords = [
        "{$ordinal} class {$subject} question paper generator",
        "class {$classId} {$subject} paper generator Pakistan",
        "online {$subject} paper generator",
        "Punjab Board {$subject} question paper",
        "BISE {$subject} paper generator",
        "chapter wise {$subject} test",
        "{$subject} MCQs short long questions generator",
        "printable {$subject} question paper",
        "{$level} question paper generator Pakistan",
    ];

    return [
        'subject' => $subject,
        'ordinal' => $ordinal,
        'level' => $level,
        'title' => "{$ordinal} Class {$subject} Question Paper Generator | Punjab Board",
        'description' => "Generate a chapter-wise {$ordinal} Class {$subject} question paper for Punjab Board preparation. Create printable MCQs, short and long questions online.",
        'keywords' => implode(', ', $keywords),
        'heading' => "{$ordinal} Class {$subject} Question Paper Generator",
        'intro' => "Create a chapter-wise {$subject} paper for {$ordinal} Class {$classProfile['stage']} preparation. Select the required chapters and generate a structured assessment with {$paperTypes} for classroom tests, homework, revision or exam practice.",
        'profile' => $profile,
        'class_profile' => $classProfile,
        'class_subject_focus' => $classSubjectFocus,
        'long_form_sections' => qpSeoLongFormSections($classId, $subject, $ordinal, $level, $profile, $classProfile, $classSubjectFocus),
        'faqs' => $faqs,
    ];
}

function qpSeoLongFormSections(
    int $classId,
    string $subject,
    string $ordinal,
    string $level,
    array $profile,
    array $classProfile,
    string $classSubjectFocus
): array
{
    return [
        [
            'heading' => "{$subject} assessment at the {$classProfile['stage']} stage",
            'paragraphs' => [
                "Class {$classId} is {$classProfile['teaching_context']}. A useful {$subject} paper should therefore do more than collect random questions. It should represent the chapters actually taught, use language appropriate to this stage and give students a fair opportunity to demonstrate both knowledge and method.",
                "{$classSubjectFocus} The generator supports {$profile['assessment']}. Automatic selection provides a starting point, while manual controls let the paper setter change chapter weight and question quantities after reviewing what is available.",
            ],
        ],
        [
            'heading' => "A Class {$classId} paper balance for {$subject}",
            'paragraphs' => [
                "{$classProfile['paper_balance']} This makes the paper more diagnostic: a teacher can see whether a student needs to revise factual knowledge, practise a method or improve the organization of a longer response.",
                "{$classProfile['student_need']} When reviewing an automatically prepared selection, check that the wording, depth and expected response are appropriate for {$classProfile['stage']} rather than borrowing expectations from a lower or higher class.",
            ],
        ],
        [
            'heading' => "What a strong {$subject} assessment should measure",
            'paragraphs' => [
                "In {$subject}, effective assessment should cover more than memorization. Important areas include {$profile['concepts']}. A balanced paper checks whether students can recognize correct information, explain an idea in their own words, connect related concepts and complete longer responses with an appropriate method. The exact balance depends on the chapters selected and the purpose of the test. A short classroom quiz may emphasize key terms and basic understanding, while a terminal or pre-board paper should sample a wider range of content and cognitive difficulty.",
                "Question variety also gives a clearer picture of student learning. MCQs can quickly identify misconceptions and test coverage across several topics. Short questions can examine definitions, comparisons, reasons, steps or small applications. Long questions can require organized explanation, calculations, diagrams, evidence or multi-stage reasoning. By distributing these formats across selected chapters, the generator helps avoid a paper that is accidentally dominated by one narrow topic. Teachers can still review every selection and adjust quantities before finalizing the assessment.",
            ],
        ],
        [
            'heading' => "Chapter-wise selection and syllabus control",
            'paragraphs' => [
                "Chapter-wise paper creation is valuable because teaching schedules are rarely identical in every school. One class may have completed the first three chapters, another may be revising a difficult unit, and an academy may want a test that combines topics from different parts of the book. On this page, users can choose only the chapters relevant to their current plan. This keeps the generated {$subject} paper aligned with classroom progress and prevents students from being assessed on material that has not yet been covered.",
                "The chapter controls also support targeted intervention. If students are struggling with a specific area, a teacher can build a focused test around that chapter and include enough short or long questions to reveal where the difficulty lies. For broader revision, several chapters can be combined to test retention and connections across the syllabus. Full-syllabus selection is useful near examinations, but smaller chapter groups often work better during teaching because feedback can be acted on immediately. This flexible approach is more useful than relying on a fixed paper that cannot reflect the actual learning sequence.",
            ],
        ],
        [
            'heading' => "Using MCQs, short questions and long questions effectively",
            'paragraphs' => [
                "Each question type serves a different purpose in a {$ordinal} Class {$subject} paper. MCQs are useful for broad coverage, quick checking and identifying common misconceptions. Good MCQs should not depend only on obvious recall; they can also ask students to distinguish similar ideas, choose a correct application or identify an error. Short questions should require concise but meaningful responses. Depending on the subject, they may test a definition, a reason, a comparison, a formula, a process, an example or a brief interpretation.",
                "Long questions provide space for deeper assessment. They can test whether students organize information logically, show complete working, use correct terminology and connect several parts of a topic. A well-designed paper does not simply maximize the number of questions. It uses an achievable quantity that matches the available time and the expected depth of response. The generator displays the distribution so the teacher can check totals before moving forward. This makes it easier to maintain a purposeful mix instead of adding questions until the paper becomes unnecessarily long or repetitive.",
            ],
        ],
        [
            'heading' => "Class {$classId} preparation priorities for {$subject}",
            'paragraphs' => [
                "Students preparing for Class {$classId} {$subject} should review {$classProfile['review_priority']} and give attention to {$profile['skills']}. These skills develop through active practice rather than reading alone. A generated paper can be used under timed conditions, as an open-book learning activity or as a guided classroom exercise.",
                "Common problems in {$subject} include {$profile['mistakes']}. A useful practice paper should expose these weaknesses early enough for students to correct them. For example, a learner may know the topic but lose marks because the response is incomplete, poorly organized or technically inaccurate. Repeated chapter-wise testing makes those patterns visible. The objective is not to produce more tests for their own sake; it is to create focused evidence that helps the teacher decide what should be retaught, practised or explained differently.",
            ],
        ],
        [
            'heading' => "Punjab Board and BISE-oriented paper practice",
            'paragraphs' => [
                "Students in Punjab often prepare for assessment formats that include objective and subjective sections. A Punjab Board-oriented {$subject} paper therefore benefits from clear question grouping, sensible marks distribution and coverage of textbook concepts. This generator organizes MCQs, short questions and long questions in a familiar way, making it useful for BISE-related classroom preparation. However, official pairing schemes, marks, syllabus notices and examination policies can change. Teachers should compare the final paper with the current instructions issued by their relevant board or institution.",
                "The tool is intended as a flexible paper-making resource, not as a claim that every generated selection is an official board paper. Its value lies in helping educators create structured practice from the available question bank while retaining control over chapter choice and quantity. Schools in Lahore, Gujranwala, Faisalabad, Multan, Rawalpindi, Sargodha, Sahiwal, Bahawalpur and other Punjab regions can adapt the result to local schedules. The same workflow can also support institutions elsewhere in Pakistan when the available book and question content match their teaching requirements.",
            ],
        ],
        [
            'heading' => "How teachers and academies can use generated papers",
            'paragraphs' => [
                "Teachers can use the {$subject} paper generator at several points in the learning cycle. A short diagnostic test at the start of a unit can reveal prior knowledge. A chapter test can check recent teaching. A mixed paper can measure whether students remember older topics while learning new ones. Near examinations, a broader paper can provide timed practice and help students manage their response order. Academy tutors can create parallel tests for different groups without rebuilding the layout each time, while school coordinators can use a common chapter plan to support consistent assessment.",
                "Before printing, the paper setter should review the selected questions for duplication, difficulty, wording and chapter balance. It is also worth checking whether students have been taught the exact terminology or method expected by a question. Automatic selection saves time, but professional judgment remains important. A teacher may replace a question, alter the quantity from a chapter or choose manual selection when a specific learning outcome requires attention. The generator works best as an efficient assistant inside a thoughtful assessment process.",
            ],
        ],
        [
            'heading' => "A revision workflow for students",
            'paragraphs' => [
                "Students can use generated papers as part of a repeatable revision routine. Begin with {$profile['revision']}. Then attempt a chapter-wise paper without looking at notes. Mark uncertain questions during the attempt, but continue working so the final result reflects both knowledge and time management. After finishing, review every error and classify it: missing knowledge, misunderstood concept, careless mistake, weak presentation or lack of time. This classification is more useful than recording only a total score because it identifies the action needed before the next attempt.",
                "A second paper should not be attempted immediately with the same mistakes still fresh but unresolved. First revise the weak areas, redo difficult examples and explain the topic aloud or in writing. Then generate another paper from the same chapters or combine them with earlier units. Over several attempts, students should see fewer repeated errors and more complete answers. This process supports retrieval practice, spaced revision and exam confidence. It also gives parents and tutors clearer evidence of progress than passive reading or highlighting alone.",
            ],
        ],
        [
            'heading' => "Quality checks before finalizing a question paper",
            'paragraphs' => [
                "A final review improves both fairness and usability. Confirm that the selected chapters match the announced syllabus and that the total number of questions is realistic for the available time. Check that objective questions have one defensible answer, short questions are precise, and long questions clearly indicate what students must explain or calculate. The paper should include a suitable range of easy, moderate and challenging items. If every question tests the same small skill, the result will not provide a reliable picture of overall learning.",
                "Presentation also matters. Instructions should be clear, question numbering should be consistent and any required parts should be visible. Technical terms, names, formulas and spellings need checking, particularly in subjects where a small error changes meaning. When the paper is intended for formal school use, the teacher may add institution details, time, marks and specific attempt instructions during the final preparation stage. A careful review takes only a few minutes but can prevent confusion during the test and reduce disputes during marking.",
            ],
        ],
        [
            'heading' => "Why online paper generation saves preparation time",
            'paragraphs' => [
                "Creating papers manually involves searching through books or files, copying questions, balancing chapters, formatting sections and checking totals. That work is repeated every time a new test is needed. An online {$subject} question paper generator reduces the repetitive part by bringing chapter selection and question distribution into one workflow. Teachers can start with automatic selection, inspect the result and make manual changes where necessary. The saved time can be used for lesson preparation, feedback, marking criteria or support for students who need additional explanation.",
                "Efficiency should not mean generic assessment. Because the generator responds to the selected class, book and chapters, the output remains connected to the intended course. The long-form guidance on this page also explains how to use the tool responsibly rather than presenting it as a one-click replacement for a teacher. For {$ordinal} Class {$subject}, the strongest results come from combining a structured question bank with knowledge of the students, the current syllabus and the purpose of the test. That balance supports useful, printable and repeatable assessment practice.",
            ],
        ],
    ];
}

function renderQuestionPaperSeoContent(array $content): void
{
    $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $subject = $e($content['subject']);
    $ordinal = $e($content['ordinal']);
    $profile = $content['profile'];
    ?>
    <section class="book-features-seo" aria-labelledby="question-paper-seo-title">
        <h2 id="question-paper-seo-title" class="features-title"><?= $e($content['heading']) ?></h2>
        <p class="features-intro"><?= $e($content['intro']) ?></p>

        <div class="features-grid">
            <article class="feature-card">
                <div class="feature-text">
                    <strong>Subject-focused assessment</strong>
                    <p>This <?= $subject ?> generator covers <?= $e($profile['focus']) ?>.</p>
                </div>
            </article>
            <article class="feature-card">
                <div class="feature-text">
                    <strong>Chapter-wise paper creation</strong>
                    <p>Choose individual chapters or combine multiple chapters to <?= $e($profile['benefit']) ?>.</p>
                </div>
            </article>
            <article class="feature-card">
                <div class="feature-text">
                    <strong>Flexible question formats</strong>
                    <p>Build a paper using <?= $e($profile['assessment']) ?> according to the available question bank.</p>
                </div>
            </article>
            <article class="feature-card">
                <div class="feature-text">
                    <strong>Pakistan and Punjab Board use</strong>
                    <p>Prepare printable <?= $ordinal ?> Class <?= $subject ?> tests for schools, academies, teachers and students across Punjab BISE boards.</p>
                </div>
            </article>
        </div>

        <div class="seo-answer-section">
            <h3>How this <?= $subject ?> paper generator works</h3>
            <p>Select the chapters, confirm the number of MCQs, short questions and long questions, then continue to review the chosen questions. Automatic selection provides a quick starting point, while manual controls let teachers adjust the chapter distribution before creating the final paper.</p>
        </div>

        <div class="seo-long-form">
            <?php foreach ($content['long_form_sections'] as $section): ?>
                <article class="seo-content-section">
                    <h3><?= $e($section['heading']) ?></h3>
                    <?php foreach ($section['paragraphs'] as $paragraph): ?>
                        <p><?= $e($paragraph) ?></p>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="seo-faq-section">
            <h3><?= $ordinal ?> Class <?= $subject ?> Paper Generator FAQs</h3>
            <div class="faq-grid">
                <?php foreach ($content['faqs'] as $faq): ?>
                    <article class="faq-item">
                        <h4><?= $e($faq['question']) ?></h4>
                        <p><?= $e($faq['answer']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ], $content['faqs']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>
    <?php
}
