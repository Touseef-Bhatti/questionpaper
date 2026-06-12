<?php
session_start();
include 'db_connect.php';
require_once 'middleware/SubscriptionCheck.php';

// Ensure class_id is provided and is valid integer
if (!isset($_GET['class_id']) || empty($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    header('Location: select_class.php');
    exit;
}

$classId = intval($_GET['class_id']);

function getClassOrdinal(int $classId): string
{
    $lastTwoDigits = $classId % 100;

    if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
        return $classId . 'th';
    }

    return $classId . match ($classId % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
}

$classOrdinal = getClassOrdinal($classId);

// Select all books for this class using prepared statement (OPTIMIZED)
$bookQuery = "SELECT book_id, book_name FROM book WHERE class_id = ? ORDER BY book_id ASC";
$stmt = $conn->prepare($bookQuery);

if (!$stmt) {
    die("<h2 style='color:red;'>Database error: " . htmlspecialchars($conn->error) . "</h2>");
}

$stmt->bind_param('i', $classId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("<h2 style='color:red;'>Query error: " . htmlspecialchars($conn->error) . "</h2>");
}

$booksData = [];
while ($row = $result->fetch_assoc()) {
    $booksData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once __DIR__ . '/includes/google_analytics.php'; ?>
    <?php include_once __DIR__ . '/includes/monetag_ads.php'; ?>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_book.css">
    <link rel="stylesheet" href="css/buttons.css">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />



<meta name="description" content="Generate Class <?= htmlspecialchars($classId) ?> question papers online according to Punjab Board pattern. Select Physics, Chemistry, Biology, Computer Science, Math, English, Urdu, Islamiat or Pak Studies and create printable papers with MCQs, short and long questions.">

<meta name="keywords" content="<?= htmlspecialchars($classId) ?>th class paper generator, <?= htmlspecialchars($classId) ?> class subjects Punjab Board, online MCQs test <?= htmlspecialchars($classId) ?>, BISE Punjab subject-wise question papers, test generator Pakistan">


<title><?= htmlspecialchars($classOrdinal) ?> Class Question Paper Generator | Punjab Board</title>

    <!-- Schema.org Markup for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "<?= htmlspecialchars($classId) ?>th Class Online Paper Generator",
      "operatingSystem": "Web",
      "applicationCategory": "EducationalApplication",
      "description": "Professional online question paper generator for <?= htmlspecialchars($classId) ?>th class subjects including Physics, Chemistry, Biology, and Math for Punjab Boards.",
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "ratingCount": "2100"
      }
    }
    </script>
  
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- SIDE SKYSCRAPER ADS (Auto-responsive) -->

    
    <div class="main-container">

    <header class="book-page-intro">
        <h1>Class <?= htmlspecialchars($classId) ?> Online Question Paper Generator for Punjab Board</h1>
        <p>
            Select a subject to create chapter-wise question papers, MCQs tests and board-pattern exams for
            <?= htmlspecialchars($classOrdinal) ?> Class students across all Punjab BISE boards in Pakistan.
        </p>
    </header>

  
    <div class="classes-container" id="book-box-container">
        <div class="book-selection-header">
            <h2>Choose a Subject for <?= htmlspecialchars($classOrdinal) ?> Class</h2>
            <p>Select your desired book to start generating <strong>Online Question Papers</strong> and <strong>MCQs Tests</strong> according to the <strong>Punjab Board Exam Pattern</strong>. We provide comprehensive coverage for all major subjects including Science and Arts groups.</p>
        </div>

        <div class="classes-grid" id="books-grid">
        <?php if (!empty($booksData)): ?>
            <?php foreach ($booksData as $row): ?>
                <?php
                    // Example: Make some  "coming soon" book

                    $isComingSoon = in_array($row['book_id'], []);

                ?>
                <div 
                    class="class-box <?= $isComingSoon ? 'coming-soon' : '' ?>" 
                    data-book-id="<?= htmlspecialchars($row['book_id']) ?>" 
                    data-book-name="<?= htmlspecialchars($row['book_name']) ?>"
                    onclick="<?= $isComingSoon ? 'showComingSoon()' : 'navigateToChapters(this)' ?>"
                >
                    <?= htmlspecialchars($row['book_name']) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <h3 style="color:red;">No books found for this class.</h3>
        <?php endif; ?>
    </div>
    <div class="book-features-seo">
        <h2 class="features-title">Professional <?= htmlspecialchars($classOrdinal) ?> Class Online Question Paper Generator</h2>
        <p class="features-intro">
            Select your subject to generate <strong>chapter-wise question papers</strong> and <strong>online MCQs tests</strong> for <?= htmlspecialchars($classId) ?>th class. Our platform supports all subjects including Physics, Chemistry, Biology, and Mathematics according to <strong>Punjab Board (BISE)</strong> standards.
        </p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-text">
                    <strong>Board Pattern Exams</strong>
                    <p>Generate full-length <strong>Punjab Board Question Papers</strong> for any <?= htmlspecialchars($classId) ?>th class subject instantly.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-text">
                    <strong>Chapter-Wise Selection</strong>
                    <p>Create <strong>Custom Online Tests</strong> by selecting specific chapters for focused classroom assessments.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-text">
                    <strong>Fast MCQs Generator</strong>
                    <p>Build <strong>MCQs papers for <?= htmlspecialchars($classId) ?> class</strong> with automatic answer key generation in seconds.</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-text">
                    <strong>Editable Downloads</strong>
                    <p>Download your generated <strong>exam papers</strong> in editable Word (DOCX) or professional PDF formats.</p>
                </div>
            </div>
        </div>

        <!-- SEO Content Section for Punjab Boards -->
        <div class="bise-boards-section">
            <h3>Supported Punjab Education Boards</h3>
            <p>
                Our <?= htmlspecialchars($classId) ?>th class paper generator is fully compatible with the syllabus and paper patterns of all BISE boards in Punjab, including:
            </p>
            <div class="boards-list">
                <span>BISE Lahore</span><span>BISE Multan</span><span>BISE Faisalabad</span><span>BISE Gujranwala</span><span>BISE Rawalpindi</span><span>BISE Sahiwal</span><span>BISE Sargodha</span><span>BISE Bahawalpur</span><span>BISE DG Khan</span>
            </div>
        </div>
    </div>
    </div>

<?php if ($classId === 9): ?>
<article class="class-nine-seo-article" aria-labelledby="class-nine-guide-title">
    <header class="seo-article-header">
        <p class="seo-eyebrow">Punjab Board Class 9 Paper Preparation</p>
        <h2 id="class-nine-guide-title">Class 9 Question Paper Generator for All Subjects</h2>
        <p class="seo-article-lead">
            Ahmad Learning Hub provides a practical <strong>class 9 question paper generator</strong> for teachers,
            school administrators, academy tutors, students and parents in Pakistan. The tool helps users select a
            subject, choose chapters and prepare a printable assessment containing MCQs, short questions and long
            questions. Whether you call it a <strong>9th class question paper generator</strong>, a paper maker or an
            online test builder, the purpose is the same: to reduce manual paper-setting work while keeping the teacher
            in control of syllabus coverage, marks and difficulty.
        </p>

        <nav class="seo-article-nav" aria-label="Class 9 paper generator guide">
            <strong>Explore this guide:</strong>
            <a href="#class-nine-paper-types">Paper types</a>
            <a href="#class-nine-book-selection">Select a book</a>
            <a href="#class-nine-science-subjects">Science subjects</a>
            <a href="#class-nine-other-subjects">Other subjects</a>
            <a href="#class-nine-how-to">How it works</a>
            <a href="#class-nine-faq">FAQs</a>
        </nav>
    </header>

    <section id="class-nine-paper-types">
        <h3>Online Class 9 Paper Generator According to Punjab Board Pattern</h3>
        <p>
            A reliable <strong>class 9 paper generator</strong> should do more than place random questions on a page.
            Teachers need appropriate question types, useful chapter coverage and a paper structure that suits the
            available examination time. Ahmad Learning Hub works as a <strong>9th class paper generator</strong> that
            begins with the selected subject and continues through chapter and question selection. This gives the user
            a clear workflow instead of forcing them to type and format an entire examination from an empty document.
        </p>
        <p>
            The platform can be used as a <strong>class 9 online paper generator</strong> on a desktop computer, laptop,
            tablet or mobile phone. A teacher can prepare a classroom test at school, while a tutor can build revision
            material from home. Students searching for a <strong>9th class online paper generator</strong> can also use
            generated papers for timed practice, self-assessment and preparation before school or board examinations.
            Every final paper should still be checked by a teacher for wording, marks and suitability.
        </p>
        <p>
            Punjab Board preparation requires attention to objective and subjective sections. The
            <strong>class 9 board pattern paper generator</strong> supports paper planning around MCQs, short questions
            and long questions. In the same way, a <strong>9th class board pattern paper generator</strong> helps users
            create material that resembles the familiar structure of school and matric assessments. The exact official
            pattern may vary by subject or examination year, so current BISE instructions should be consulted before a
            formal annual examination.
        </p>
        <p>
            Users often want a <strong>class 9 paper according to board pattern</strong> because balanced question
            selection makes practice more meaningful. A <strong>9th class paper according to board pattern</strong>
            should not focus entirely on one easy chapter or repeat one question style. Instead, it should combine
            objective knowledge, concise written responses and detailed reasoning according to the subject. Ahmad
            Learning Hub provides the selection tools, while the teacher makes the final academic decisions.
        </p>
        <p>
            This page is designed for users seeking a <strong>class 9 Punjab board paper generator</strong> or a
            <strong>9th class Punjab board paper generator</strong>. It can support learners associated with BISE Lahore,
            Gujranwala, Multan, Faisalabad, Rawalpindi, Sargodha, Sahiwal, Bahawalpur and DG Khan. Punjab boards commonly
            follow related curriculum directions, but users should confirm any board-specific notification, revised
            syllabus or examination instruction before printing a final paper.
        </p>

        <div class="seo-info-grid">
            <div>
                <h4>Chapter-Wise Papers</h4>
                <p>
                    Use the <strong>class 9 chapter wise paper generator</strong> for a recently completed unit or combine
                    chapters for monthly assessment. The <strong>9th class chapter wise paper generator</strong> is also
                    useful for targeted revision after identifying weak areas.
                </p>
            </div>
            <div>
                <h4>Half-Syllabus Papers</h4>
                <p>
                    A <strong>class 9 half syllabus paper generator</strong> can support midterm and send-up preparation.
                    Teachers can select the taught portion and build a balanced test. The same workflow serves as a
                    <strong>9th class half syllabus paper generator</strong>.
                </p>
            </div>
            <div>
                <h4>Full-Syllabus Papers</h4>
                <p>
                    Use the <strong>class 9 full syllabus paper generator</strong> for broad revision after the course has
                    been completed. A <strong>9th class full syllabus paper generator</strong> helps students practise
                    switching between topics under timed conditions.
                </p>
            </div>
            <div>
                <h4>Printable and PDF Papers</h4>
                <p>
                    Create material with the <strong>class 9 printable paper generator</strong>, then review and download
                    the available format. Users looking for a <strong>9th class printable question paper</strong>,
                    <strong>class 9 paper generator PDF</strong> or <strong>9th class paper generator PDF</strong> can
                    prepare papers for printing and classroom use.
                </p>
            </div>
        </div>

        <p>
            Teachers may also describe the service as a <strong>class 9 question paper maker</strong>,
            <strong>9th class question paper maker</strong>, <strong>class 9 test paper generator</strong> or
            <strong>9th class test paper generator</strong>. For longer assessments, it works as a
            <strong>class 9 exam paper generator</strong> and <strong>9th class exam paper generator</strong>. These terms
            represent different assessment lengths, but the core process remains subject selection, chapter selection,
            question selection and final review.
        </p>
        <p>
            The generated assessment can include a <strong>class 9 paper with MCQs short and long questions</strong>.
            This mixture checks quick recall, concise understanding and extended response skills. A
            <strong>9th class paper with MCQs short and long questions</strong> is particularly valuable for practice
            because students must manage their time across sections instead of preparing for only one type of question.
        </p>
    </section>

    <section id="class-nine-book-selection">
        <h3>Select a Book or Subject for the Class 9 Question Paper</h3>
        <p>
            This is the book-selection stage of the generator. To <strong>select book for class 9 question paper</strong>,
            use the subject cards above and open the book required for your test. The phrase
            <strong>class 9 select book for paper</strong> describes this first important choice: questions must come
            from the correct textbook, subject and syllabus before chapters can be selected.
        </p>
        <p>
            A student may search for <strong>9th class select subject for paper</strong>, while a teacher may need a
            <strong>class 9 book wise paper generator</strong>. Both users arrive at the same workflow. The
            <strong>9th class book wise paper generator</strong> keeps subject content separate, making it easier to
            avoid mixing questions from unrelated books or groups.
        </p>
        <p>
            Subject-based organization makes the platform a <strong>class 9 subject wise paper generator</strong> and a
            <strong>9th class subject wise paper generator</strong>. Science-group teachers can use it as a
            <strong>class 9 science subjects paper generator</strong> or <strong>9th class science subjects paper
            generator</strong> for Physics, Chemistry, Biology, Computer Science and Mathematics. Available humanities
            and compulsory books also make it useful as a <strong>class 9 arts subjects paper generator</strong> and
            <strong>9th class arts subjects paper generator</strong>.
        </p>
        <p>
            Users who want to <strong>generate class 9 paper by subject</strong> should choose one card above and then
            select the required chapters. The process is identical for those who want to
            <strong>generate 9th class paper by subject</strong>. The subject choice narrows the question bank so the
            next screen can present relevant chapter options and paper controls.
        </p>
        <p>
            In simple terms, use this page when you need to <strong>class 9 choose subject for question paper</strong> or
            <strong>9th class choose book for question paper</strong>. The wording may differ in search, but the action
            is straightforward: choose the book, choose chapters and build the assessment. Because major subjects are
            available from one location, the page works as a <strong>class 9 all subjects paper generator</strong>,
            <strong>9th class all subjects paper generator</strong>, <strong>class 9 paper generator for all books</strong>
            and <strong>9th class paper generator for all books</strong>.
        </p>

        <div class="seo-callout">
            <h4>Choose the correct book before continuing</h4>
            <p>
                Check the class, subject name, study group and textbook used by your students. Correct book selection
                improves chapter accuracy and prevents questions from another syllabus or subject from entering the
                assessment.
            </p>
        </div>
    </section>

    <section id="class-nine-science-subjects">
        <h3>Class 9 Subject-Wise Question Paper Generators</h3>

        <div class="subject-seo-card" id="class-nine-physics">
            <h4>Class 9 Physics Question Paper Generator</h4>
            <p>
                The <a href="9th-class-physics-question-paper-generator"><strong>class 9 physics question paper
                generator</strong></a> helps teachers prepare assessments from selected Physics chapters. It is also a
                <strong>9th class physics question paper generator</strong> for students who need repeated practice with
                definitions, concepts, formulas, numerical problems and explanatory questions. Use the
                <strong>class 9 physics paper generator</strong> or <strong>9th class physics paper generator</strong>
                for chapter tests, monthly papers and broader revision.
            </p>
            <p>
                A <strong>class 9 physics board pattern paper</strong> should balance conceptual knowledge and
                problem-solving. The same applies to a <strong>9th class physics board pattern paper</strong>. Teachers
                using the <strong>class 9 physics Punjab board paper generator</strong> or
                <strong>9th physics paper generator Punjab board</strong> should check formulas, units, diagrams and
                numerical values before final use.
            </p>
            <p>
                Build a <strong>class 9 physics MCQs short long questions</strong> assessment, a
                <strong>9th class physics chapter wise paper</strong> or a <strong>class 9 physics chapter wise test
                paper</strong>. After review, the result can serve as a <strong>class 9 physics printable question
                paper</strong> or meet the need for a <strong>9th physics paper generator PDF</strong>.
            </p>
            <a class="subject-generator-link" href="9th-class-physics-question-paper-generator">Open Class 9 Physics Paper Generator</a>
        </div>

        <div class="subject-seo-card" id="class-nine-chemistry">
            <h4>Class 9 Chemistry Question Paper Generator</h4>
            <p>
                The <a href="9th-class-chemistry-question-paper-generator"><strong>class 9 chemistry question paper
                generator</strong></a> supports tests covering chemical concepts, terminology, equations and numerical
                work. A <strong>9th class chemistry question paper generator</strong> gives teachers a faster starting
                point than typing every item manually. The <strong>class 9 chemistry paper generator</strong> and
                <strong>9th class chemistry paper generator</strong> can be used for short tests or wider assessments.
            </p>
            <p>
                Use the tool to prepare a <strong>class 9 chemistry board pattern paper</strong> or
                <strong>9th class chemistry board pattern paper</strong>. People searching for a
                <strong>class 9 chemistry Punjab board paper generator</strong> or
                <strong>9th chemistry paper generator Punjab board</strong> should review equations, symbols, spellings
                and marks carefully because small technical errors can change the meaning of a Chemistry question.
            </p>
            <p>
                Select chapters for a <strong>class 9 chemistry MCQs short long questions</strong> paper,
                <strong>9th class chemistry chapter wise paper</strong> or <strong>class 9 chemistry chapter wise test
                paper</strong>. The completed assessment can be used as a <strong>class 9 chemistry printable question
                paper</strong>, with downloadable options supporting users searching for a
                <strong>9th chemistry paper generator PDF</strong>.
            </p>
            <a class="subject-generator-link" href="9th-class-chemistry-question-paper-generator">Open Class 9 Chemistry Paper Generator</a>
        </div>

        <div class="subject-seo-card" id="class-nine-biology">
            <h4>Class 9 Biology Question Paper Generator</h4>
            <p>
                The <a href="9th-class-biology-question-paper-generator"><strong>class 9 biology question paper
                generator</strong></a> helps users create assessments for biological terminology, processes,
                comparisons, diagrams and explanations. A <strong>9th class biology question paper generator</strong>
                can provide varied practice across chapters. Use the <strong>class 9 biology paper generator</strong> or
                <strong>9th class biology paper generator</strong> according to the syllabus portion already taught.
            </p>
            <p>
                A <strong>class 9 biology board pattern paper</strong> and <strong>9th class biology board pattern
                paper</strong> should test both factual knowledge and understanding. When using the
                <strong>class 9 biology Punjab board paper generator</strong> or
                <strong>9th biology paper generator Punjab board</strong>, check scientific names, labels, diagrams and
                answer scope before giving the paper to students.
            </p>
            <p>
                Teachers can prepare a <strong>class 9 biology MCQs short long questions</strong> assessment,
                <strong>9th class biology chapter wise paper</strong> or <strong>class 9 biology chapter wise test
                paper</strong>. The reviewed result becomes a <strong>class 9 biology printable question paper</strong>
                and supports the common search for a <strong>9th biology paper generator PDF</strong>.
            </p>
            <a class="subject-generator-link" href="9th-class-biology-question-paper-generator">Open Class 9 Biology Paper Generator</a>
        </div>

        <div class="subject-seo-card" id="class-nine-computer">
            <h4>Class 9 Computer Science Question Paper Generator</h4>
            <p>
                The <a href="9th-class-computer-question-paper-generator"><strong>class 9 computer science
                question paper generator</strong></a> supports assessments covering computer concepts, terminology,
                logic and subject-specific applications. A <strong>9th class computer science question paper
                generator</strong>, <strong>class 9 computer paper generator</strong> or
                <strong>9th class computer paper generator</strong> can help teachers create regular practice material.
            </p>
            <p>
                Use it to plan a <strong>class 9 computer science board pattern paper</strong> or
                <strong>9th class computer board pattern paper</strong>. The
                <strong>class 9 computer science Punjab board paper generator</strong> and
                <strong>9th computer science paper generator Punjab board</strong> should be used with the current
                textbook and syllabus, especially where terminology or course content has been updated.
            </p>
            <p>
                Build a <strong>class 9 computer MCQs short long questions</strong> paper,
                <strong>9th class computer chapter wise paper</strong> or <strong>class 9 computer science chapter wise
                test paper</strong>. The final version can serve as a <strong>class 9 computer science printable
                test paper</strong>. The final version can serve as a <strong>class 9 computer science printable
                question paper</strong> or a downloadable <strong>9th computer science paper generator PDF</strong>.
            </p>
            <a class="subject-generator-link" href="9th-class-computer-question-paper-generator">Open Class 9 Computer Science Paper Generator</a>
        </div>

        <div class="subject-seo-card" id="class-nine-mathematics">
            <h4>Class 9 Mathematics Question Paper Generator</h4>
            <p>
                The <a href="9th-class-math-question-paper-generator"><strong>class 9 math question paper generator</strong></a>
                is designed for selecting questions from Mathematics chapters. It can also be
                found through searches such as <strong>9th class math question paper generator</strong>,
                <strong>class 9 maths paper generator</strong>, <strong>9th class maths paper generator</strong>,
                <strong>class 9 mathematics question paper generator</strong> and
                <strong>9th class mathematics paper generator</strong>.
            </p>
            <p>
                A useful <strong>class 9 math board pattern paper</strong> or <strong>9th class math board pattern
                paper</strong> should include an appropriate range of exercises and skills. Users of the
                <strong>class 9 math Punjab board paper generator</strong> or
                <strong>9th math paper generator Punjab board</strong> should verify mathematical notation, values,
                diagrams and marks before printing.
            </p>
            <p>
                Select a focused portion for a <strong>class 9 math chapter wise paper</strong>, then prepare a
                <strong>class 9 math printable question paper</strong> for practice or assessment. Download options also
                support users looking for a <strong>9th maths paper generator PDF</strong>. Students should show complete
                working when attempting numerical and proof-based questions.
            </p>
            <a class="subject-generator-link" href="9th-class-math-question-paper-generator">Open Class 9 Mathematics Paper Generator</a>
        </div>
    </section>

    <section id="class-nine-other-subjects">
        <h3>English, Urdu, Islamiat, Pakistan Studies and Other Class 9 Books</h3>
        <p>
            Class 9 preparation is not limited to science subjects. The subject cards above may include English, Urdu,
            Islamiat, Pakistan Studies and other books available for the selected study group. Language papers may
            require grammar, comprehension, translation, vocabulary and written composition, while compulsory and
            theory subjects may require MCQs, short responses and detailed explanations.
        </p>
        <p>
            Choose the exact book used by your school, then select chapters according to the taught syllabus. Review
            quotations, translations, spellings, dates and names carefully. A generated question is a starting point
            for assessment, not a replacement for teacher judgment. The final paper should match the students'
            language level, available examination time and learning outcomes.
        </p>
    </section>

    <section id="class-nine-how-to">
        <h3>How to Generate a Class 9 Paper by Subject</h3>
        <ol class="seo-process-list">
            <li>
                <strong>Choose a subject above.</strong> Select Physics, Chemistry, Biology, Computer Science,
                Mathematics or another available Class 9 book.
            </li>
            <li>
                <strong>Select chapters.</strong> Choose one chapter for a focused test, several chapters for a monthly
                assessment, half the syllabus for a midterm paper or the full syllabus for revision.
            </li>
            <li>
                <strong>Choose question types.</strong> Set the required MCQs, short questions and long questions
                according to marks, time and the current paper pattern.
            </li>
            <li>
                <strong>Generate the paper.</strong> Let the system organize the selected questions into an assessment
                that can be reviewed before use.
            </li>
            <li>
                <strong>Check academic accuracy.</strong> Review wording, duplication, answers, formulas, diagrams,
                internal choice, total marks and chapter balance.
            </li>
            <li>
                <strong>Download or print.</strong> Use the available document or PDF options to prepare copies for
                classroom assessment, homework, academy tests or timed revision.
            </li>
        </ol>
        <p>
            This process saves preparation time, but quality still depends on review. A strong paper is clear,
            balanced and appropriate for what students have actually studied. Teachers should remove repeated
            questions, correct any ambiguity and adjust difficulty when necessary. Students using a generated paper
            independently should attempt it under realistic time conditions and revise mistakes afterward.
        </p>
    </section>

    <section id="class-nine-faq" class="seo-faq-section">
        <h3>Frequently Asked Questions</h3>

        <details>
            <summary>How do I select a Class 9 book for a question paper?</summary>
            <p>
                Use the subject cards at the top of this page. After choosing a book, select the chapters and question
                types required for your assessment.
            </p>
        </details>

        <details>
            <summary>Can I generate Punjab Board pattern papers for all Class 9 subjects?</summary>
            <p>
                You can create papers for the Class 9 books available in the database. Always compare a formal paper
                with the latest syllabus and instructions issued by your school or relevant Punjab BISE board.
            </p>
        </details>

        <details>
            <summary>Can the paper include MCQs, short questions and long questions?</summary>
            <p>
                Yes. The generator supports objective and subjective question selection according to the options
                available for the chosen subject and chapters.
            </p>
        </details>

        <details>
            <summary>Can I create chapter-wise, half-syllabus and full-syllabus papers?</summary>
            <p>
                Yes. Select the chapters that represent your intended syllabus portion. A single chapter can be used
                for a focused test, while multiple chapters can create half-book or full-course revision material.
            </p>
        </details>

        <details>
            <summary>Can I print or download the generated Class 9 paper?</summary>
            <p>
                The platform provides supported download and print workflows, including editable document or PDF
                options where available. Review the final layout before distributing copies.
            </p>
        </details>

        <details>
            <summary>Does a generated paper predict the annual board examination?</summary>
            <p>
                No. Generated papers are intended for assessment and practice. They do not guarantee that specific
                questions will appear in an official board examination.
            </p>
        </details>
    </section>

    <footer class="seo-article-footer">
        <h3>Choose a Class 9 Subject and Start Your Paper</h3>
        <p>
            Return to the subject cards above, select the required Class 9 book and continue to chapter selection.
            Build the paper, review every section and prepare a clean printable assessment for your students.
        </p>
        <a href="#book-box-container">Select a Class 9 Subject</a>
    </footer>
</article>
<?php endif; ?>

    <a href="select_class.php" class="go-back-btn page-back-link">Go Back</a>


<?php if ($classId !== 9): ?>
<p class="seo-subject-info">
    Fast Question paper generator for <?= htmlspecialchars($classId) ?> class for  Punjab Board  and School Exams.Fast MCQs Paper generator for <?= htmlspecialchars($classId) ?> class Exams. Create custom
    question papers, MCQs tests, and chapter-wise for <?= htmlspecialchars($classId) ?> exam papers in seconds.
</p>
<?php endif; ?>
<?php include 'footer.php'; ?>

    <script>
        const classId = '<?= urlencode($classId) ?>';
        
        function getOrdinalSuffix(i) {
            var j = i % 10, k = i % 100;
            if (j == 1 && k != 11) return i + "st";
            if (j == 2 && k != 12) return i + "nd";
            if (j == 3 && k != 13) return i + "rd";
            return i + "th";
        }

        function navigateToChapters(el) {
            const bookName = el.getAttribute('data-book-name');
            if (!bookName) {
                alert('Please select a valid book.');
                return;
            }
            
            // Generate SEO-friendly slug with subject aliases normalized
            const slugBase = bookName.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            const bookSlug = (slugBase === 'mathematics' || slugBase === 'maths') ? 'math'
                : (slugBase === 'computer-science' || slugBase === 'computer-science-engineering' || slugBase === 'computer science') ? 'computer'
                : (slugBase === 'computer' ? 'computer' : slugBase);
            const classOrdinal = getOrdinalSuffix(parseInt(classId));
            
            // Construct the SEO-optimized URL
            window.location.href = `${classOrdinal}-class-${bookSlug}-question-paper-generator`;
        }
        
        function showComingSoon() {
            alert('coming soon!');
        }
    </script>

    
    
</body>
</html>
