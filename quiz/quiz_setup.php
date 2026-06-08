<?php
session_start();
// quiz_setup.php - Public quiz setup page
include '../db_connect.php';

// Function to create a slug from a string
function createSlug($string) {
    // Convert to lowercase
    $slug = strtolower($string);
    // Replace spaces and other separators with hyphens
    $slug = preg_replace('/[\s_]+/', '-', $slug);
    // Remove special characters
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    // Remove leading and trailing hyphens
    return trim($slug, '-');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/favicons.php'; ?>
    <!-- Google tag (gtag.js) -->
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>9th 10th 11th 12th Class MCQs Online Test 2026 — Chapter Wise All Subjects | Ahmad Learning Hub</title>
    <!-- Enhanced SEO Meta Tags -->
    <meta name="description" content="Free chapter wise MCQs online test for class 9, 10, 11 & 12 — Physics, Chemistry, Biology, Maths, Computer Science, English, Islamiat, Pak Studies. Punjab Board, Federal Board (FBISE), Sindh Board & CBSE 2026. Instant grading & funny mode quiz.">

    <meta name="keywords" content="9th class physics mcqs, class 9 physics mcqs chapter wise, 9th physics chapter 1 mcqs, 9th physics solved mcqs, 9th class physics online test, 9th physics important mcqs, 9th class physics guess mcqs, 9th chemistry mcqs, 9th biology mcqs, 9th computer mcqs, 9th maths mcqs, 9th english mcqs, 9th islamiat mcqs, 9th pak studies mcqs, 9th class all subjects mcqs, matric part 1 mcqs, class 9 chapter wise mcqs, 9th class board exam mcqs, 10th class physics mcqs, class 10 physics chapter wise mcqs, 10th chemistry mcqs, 10th biology mcqs, 10th maths mcqs, 10th computer science mcqs, 10th class online mcqs test, matric part 2 mcqs, 10th class important mcqs, 10th class board mcqs, chapter wise mcqs class 10, 11th class physics mcqs, first year physics mcqs, 1st year chemistry mcqs, 1st year biology mcqs, 1st year computer mcqs, class 11 physics chapter wise mcqs, fsc part 1 mcqs, 11th class important mcqs, 12th class physics mcqs, second year physics mcqs, 2nd year chemistry mcqs, 2nd year biology mcqs, class 12 chapter wise mcqs, fsc part 2 mcqs, 12th class online test, 12th class important mcqs, board exam mcqs class 12, class 9 science mcqs, class 10 science mcqs, cbse class 10 mcqs, cbse class 11 physics mcqs, cbse class 12 mcqs, class 12 board exam mcqs, online mcqs test, funny mode quiz, Ahmad Learning Hub">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://ahmadlearninghub.com.pk/class-9-and-10-online-mcqs-prepation-test">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ahmadlearninghub.com.pk/class-9-and-10-online-mcqs-prepation-test">
    <meta property="og:title" content="9th 10th 11th 12th Class MCQs Online Test 2026 — Chapter Wise | Ahmad Learning Hub">
    <meta property="og:description" content="Free chapter wise MCQs online test for class 9, 10, 11, 12 — all subjects. Punjab Board, FBISE, Sindh Board & CBSE 2026. Practice with instant grading & funny mode.">
    <meta property="og:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og.jpg">

    <!-- JSON-LD Structured Data for SEO Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalApplication",
      "name": "Ahmad Learning Hub — Online MCQs Test",
      "description": "Free chapter wise MCQs online test for class 9, 10, 11, 12 covering Physics, Chemistry, Biology, Maths, Computer Science and more. Supports Punjab Board, Federal Board, Sindh Board and CBSE exam patterns.",
      "applicationCategory": "Education",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      },
      "educationalAlignment": [
        {
          "@type": "AlignmentObject",
          "educationalFramework": "Punjab Board / FBISE / Sindh Board / CBSE",
          "targetName": "Matric Part 1 & 2 / FSc Part 1 & 2 / CBSE Class 9-12",
          "alignmentType": "teaches"
        }
      ],
      "audience": {
        "@type": "EducationalAudience",
        "educationalRole": "student"
      }
    }
    </script>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://ahmadlearninghub.com.pk/class-9-and-10-online-mcqs-prepation-test">
    <meta property="twitter:title" content="9th 10th 11th 12th Class MCQs Online Test 2026 — Chapter Wise | Ahmad Learning Hub">
    <meta property="twitter:description" content="Free chapter wise MCQs for all subjects — Physics, Chemistry, Biology, Maths. Punjab Board, FBISE, Sindh Board & CBSE 2026. Try funny mode quiz!">
    <meta property="twitter:image" content="https://ahmadlearninghub.com.pk/assets/images/quiz-og.jpg">

    <!-- FAQ Structured Data for Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Is this MCQs test free for all classes?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. Ahmad Learning Hub offers a completely free online MCQs test for class 9, 10, 11 and 12. There are no hidden charges or premium accounts required."
          }
        },
        {
          "@type": "Question",
          "name": "Which boards are supported?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Our questions cover the textbooks prescribed by Punjab Board, Federal Board (FBISE), Sindh Board and CBSE. We update the database each academic year to match the latest syllabus."
          }
        },
        {
          "@type": "Question",
          "name": "What subjects can I practise?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "All major subjects: Physics, Chemistry, Biology, Mathematics, Computer Science, English, Islamiat, Pakistan Studies and General Science."
          }
        },
        {
          "@type": "Question",
          "name": "How does funny mode work?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Funny mode rewrites answer explanations with humour, analogies and pop-culture references. It is designed to boost memory retention through laughter — a technique backed by cognitive science research."
          }
        },
        {
          "@type": "Question",
          "name": "Can I use this for MDCAT, ECAT or NEET preparation?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Absolutely. Our FSc part 1 and FSc part 2 MCQs align closely with the MDCAT and ECAT syllabi. CBSE students preparing for NEET will find our class 11 and class 12 chapter wise MCQs equally relevant."
          }
        }
      ]
    }
    </script>

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/quiz_setup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php include_once '../header.php'; ?>

<!-- SIDE SKYSCRAPER ADS -->

<div class="main-content">
    <div class="quiz-setup-container">
        <!-- TOP AD BANNER MOVED HERE FROM HEADER -->

        <header class="setup-header">
            <h1>Online MCQs Test for 9th, 10th, 11th & 12th Class — Board Exam Preparation 2026</h1>
            <p class="desc">Ahmad Learning Hub provides a personalized learning experience for <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong>, <strong>Sindh Board</strong> and <strong>CBSE</strong> students. Select your Class below to start a free chapter wise MCQs practice session — covering Physics, Chemistry, Biology, Maths, Computer Science, English, Islamiat and Pak Studies.</p>
        </header>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot active" id="stepDot1">1</div>
            <div class="step-line" id="stepLine1"></div>
            <div class="step-dot" id="stepDot2">2</div>
        </div>
        <div class="step-label-row">
            <span class="step-label active" id="stepLabel1">Choose Class</span>
            <span class="step-label" id="stepLabel2">Choose Book</span>
        </div>

        <form id="quizForm" method="POST" action="mcqs-quiz.php">
            <input type="hidden" id="class_id" name="class_id" value="">
            <input type="hidden" id="book_id" name="book_id" value="">
            <input type="hidden" id="book_name_hidden" name="book_name" value="">

            <!-- SELECTION TOP AD -->

            <!-- Step 1: Class Selection Cards -->
            <div class="section-title">
                <span class="icon" aria-hidden="true"></span> Select Your Class
            </div>
            <div class="class-cards" id="classCards">
                <?php
                $icons = [
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4H20v16H5.5A2.5 2.5 0 0 1 3 17.5v-11z"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l7 4-7 4-7-4 7-4z"/><path d="M5 10v7a2 2 0 0 0 2 2h10"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-2-2h-4l-2-2H9L7 6H5a2 2 0 0 0-2 2v8"/><path d="M7 13h10"/></svg>',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a7 7 0 0 0 0-6"/><path d="M4.6 9a7 7 0 0 0 0 6"/></svg>'
                ];
                $subs = ['Matric Part 1', 'Matric Part 2', 'FSc Part 1', 'FSc Part 2'];
                $cls = $conn->query("SELECT class_id, class_name FROM class WHERE class_id IN (9, 10, 11, 12) ORDER BY class_id ASC");
                $i = 0;
                if ($cls && $cls->num_rows > 0) {
                    while ($row = $cls->fetch_assoc()) {
                        $cid = (int)$row['class_id'];
                        $cname = htmlspecialchars($row['class_name']);
                        $icon = $icons[$i % 4];
                        $sub = $subs[$i % 4];
                        echo '<div class="class-card" tabindex="0" data-class-id="' . $cid . '" data-class-name="' . $cname . '">';
                        echo '  <div class="class-card-icon">' . $icon . '</div>';
                        echo '  <div class="class-card-info">';
                        echo '    <div class="class-card-name">' . $cname . '</div>';
                        echo '    <div class="class-card-sub">' . $sub . '</div>';
                        echo '  </div>';
                        echo '  <div class="class-card-check"></div>';
                        echo '</div>';
                        $i++;
                    }
                } else {
                    $fallback = [
                        ['9', '9th Class', 'Matric Part 1', '📘'],
                        ['10', '10th Class', 'Matric Part 2', '📗'],
                        ['11', '11th Class', 'FSc Part 1', '📙'],
                        ['12', '12th Class', 'FSc Part 2', '📕'],
                    ];
                    foreach ($fallback as $fb) {
                        $fi = $i % 4;
                        $iconHtml = $icons[$fi];
                        echo '<div class="class-card" tabindex="0" data-class-id="' . $fb[0] . '" data-class-name="' . $fb[1] . '">';
                        echo '  <div class="class-card-icon">' . $iconHtml . '</div>';
                        echo '  <div class="class-card-info">';
                        echo '    <div class="class-card-name">' . $fb[1] . '</div>';
                        echo '    <div class="class-card-sub">' . $fb[2] . '</div>';
                        echo '  </div>';
                        echo '  <div class="class-card-check"></div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <a href="topic-wise-mcqs-test" class="topic-link after-class">
                Or try Topic-Wise MCQs <span class="arrow">&rarr;</span>
            </a>

            <div class="setup-divider"></div>

            <!-- Step 2: Book Selection Cards -->
            <div class="book-section" id="bookSection">
                <div class="section-title">
                    <span class="icon" aria-hidden="true"></span> Select Your Book
                </div>
                <div class="book-cards-wrapper">
                    <div class="book-cards" id="bookCards">
                        <div class="book-empty-state">
                            <span class="empty-icon" aria-hidden="true"></span>
                            Pick a class above to see available books
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn secondary" id="resetBtn">Reset</button>
                <button type="submit" class="btn primary" id="startBtn" disabled>
                    Start Quiz <span>&rarr;</span>
                </button>
            </div>

            <!-- MIDDLE AD BANNER -->
        </form>
    </div>

    <?php include_once __DIR__ . '/../includes/quiz_ad_gate.php'; ?>

    <!-- SEO Article Section - Comprehensive Blog Style -->
<article class="seo-article-section blog-layout">
    <div class="blog-container">
        <header class="blog-header">
            <h2 class="blog-title">The Ultimate Guide to 9th, 10th, 11th & 12th Class Online MCQs Test — Board Exam Preparation 2026</h2>
            <div class="blog-meta">
                <span class="category">Board Exams 2026</span>
                <span class="read-time">15 min read</span>
            </div>
        </header>

        <section class="blog-content">
            <p class="lead">
                In the current educational landscape of <strong>Pakistan</strong> and <strong>India</strong>, objective-type questions carry enormous weight in board examinations. Whether you are a Matric student sitting for the <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong> or <strong>Sindh Board</strong> exam, or a CBSE student preparing for competitive papers, mastering <strong>chapter wise MCQs</strong> is the fastest route to top marks. Ahmad Learning Hub gives you a free, instant-grading <strong>online MCQs test</strong> platform that covers every major subject from <strong>class 9</strong> through <strong>class 12</strong> — including a unique <strong>funny mode</strong> that actually helps you learn faster (more on that below).
            </p>

            <div class="blog-featured-box">
                <h4>At a Glance — What This Platform Covers</h4>
                <ul>
                    <li><strong>9th class all subjects MCQs</strong> — Physics, Chemistry, Biology, Maths, Computer, English, Islamiat, Pak Studies.</li>
                    <li><strong>10th class online MCQs test</strong> — chapter wise with answers for <strong>matric part 2</strong>.</li>
                    <li><strong>11th class physics MCQs</strong>, 1st year Chemistry, Biology, Computer — <strong>FSc part 1 MCQs</strong>.</li>
                    <li><strong>12th class physics MCQs</strong>, 2nd year Chemistry, Biology — <strong>FSc part 2 MCQs</strong>.</li>
                    <li>Full CBSE coverage: <strong>class 9 science MCQs</strong>, <strong>class 10 science MCQs</strong>, <strong>cbse class 11 physics MCQs</strong>, <strong>cbse class 12 MCQs</strong>.</li>
                    <li><strong>Funny mode quiz</strong> — a scientifically-backed, humour-infused test format to boost retention.</li>
                </ul>
            </div>

            <h2>Mastering 9th Class MCQs — Matric Part 1 Preparation</h2>
            <p>
                The <strong>9th class board exam</strong> is the foundation of your academic career. Students across Punjab, Sindh and Federal boards search for <strong>9th class physics MCQs</strong>, <strong>9th chemistry MCQs</strong>, <strong>9th biology MCQs</strong>, <strong>9th maths MCQs</strong>, and <strong>9th computer MCQs</strong> every single day. Our platform offers <strong>class 9 physics MCQs chapter wise</strong> — from <strong>9th physics chapter 1 MCQs</strong> (Physical Quantities and Measurement) all the way to the final unit, so you can practise exactly the topics you need.
            </p>
            <p>
                For arts students, we also provide <strong>9th english MCQs</strong>, <strong>9th islamiat MCQs</strong> and <strong>9th pak studies MCQs</strong>. Every question is <strong>solved</strong> with an explanation, making our test bank a reliable source of <strong>9th physics solved MCQs</strong> and <strong>9th class physics guess MCQs</strong> that align with past-paper patterns. Indian students preparing for state or CBSE papers will find our <strong>class 9 science MCQs</strong>, <strong>class 9 science online test</strong> and <strong>class 9 science objective questions</strong> equally useful.
            </p>

            <h3>9th Class Physics — Why It Deserves Special Attention</h3>
            <p>
                Physics is the subject where most marks are lost to careless conceptual errors. Our <strong>9th class physics online test</strong> focuses on high-frequency examination topics: SI units, kinematics equations, Newton's laws, work-energy relations and thermal expansion. Taking a timed <strong>class 9 chapter wise MCQs</strong> session of 20–50 questions a day builds the speed and accuracy needed to score 12/12 in the objective section. Combine this with our <strong>9th physics important MCQs</strong> collection — curated from past five years' papers — and you have a preparation edge that textbooks alone cannot provide.
            </p>

            <h2>10th Class MCQs — The Final Sprint for Matric Part 2</h2>
            <p>
                Your <strong>10th class board MCQs</strong> determine whether you qualify for Pre-Medical, Pre-Engineering, ICS or Arts at the intermediate level. The stakes are high, and students heavily search for <strong>10th class physics MCQs</strong>, <strong>class 10 physics chapter wise MCQs</strong>, <strong>10th chemistry MCQs</strong>, <strong>10th biology MCQs</strong>, <strong>10th maths MCQs</strong> and <strong>10th computer science MCQs</strong>.
            </p>
            <p>
                Ahmad Learning Hub's <strong>10th class online MCQs test</strong> mirrors real board-paper difficulty. Each session randomises questions so you never memorise answer positions — a common trap with printed key-books. Our <strong>10th class important MCQs</strong> highlight the most-repeated questions across Punjab Board, FBISE, Sindh Board and CBSE. For Indian CBSE students, our <strong>cbse class 10 MCQs</strong>, <strong>class 10 science online test</strong> and <strong>class 10 objective questions</strong> align with the latest NCERT syllabus changes.
            </p>

            <h3>10th Class Chemistry & Biology — High-Yield Chapters</h3>
            <p>
                Organic Chemistry (Chapter 11) and Biochemistry (Chapter 13) collectively carry up to 30 % of the objective portion. Practising <strong>10th chemistry MCQs chapter wise</strong> on our platform helps you internalise functional groups, hydrocarbon structures and enzyme reactions without rote memorisation. In Biology, Cell Cycle, Genetics and Biotechnology are gold mines for repeated board questions.
            </p>

            <h2>11th Class MCQs — FSc Part 1 / First Year Online Test</h2>
            <p>
                Stepping into intermediate, the syllabus depth increases dramatically. Thousands of students search for <strong>first year physics MCQs</strong>, <strong>1st year chemistry MCQs</strong>, <strong>1st year biology MCQs</strong> and <strong>1st year computer MCQs</strong>. Our <strong>class 11 physics chapter wise MCQs</strong> cover every chapter from Measurement to Heat and Thermodynamics. We also cater to CBSE students looking for <strong>cbse class 11 physics MCQs</strong>, <strong>class 11 chemistry MCQs</strong>, <strong>class 11 biology MCQs</strong> and <strong>class 11 maths MCQs</strong>.
            </p>
            <p>
                The <strong>11th class online MCQs test</strong> format includes negative-marking mode for MDCAT aspirants, standard mode for board candidates and — uniquely — a <strong>funny mode</strong> that injects humour into answer explanations. Our <strong>FSc part 1 MCQs</strong> and <strong>11th class important MCQs</strong> collection is updated yearly to track the latest examination pattern. If you need to focus on a single unit, start with <strong>chapter 1 physics MCQs class 11</strong> (Measurement) and work your way up.
            </p>

            <h2>12th Class MCQs — FSc Part 2 / Second Year Board Exam</h2>
            <p>
                The <strong>12th class board exam</strong> is the gateway to university admissions and competitive tests such as ECAT, MDCAT and NEET. Students search for <strong>second year physics MCQs</strong>, <strong>2nd year chemistry MCQs</strong>, <strong>2nd year biology MCQs</strong>, <strong>class 12 chapter wise MCQs</strong>, <strong>12th physics solved MCQs</strong> and <strong>12th class important MCQs</strong>. Our <strong>12th class online test</strong> provides an exam-like environment with customisable question counts, chapter filters and timed sessions.
            </p>
            <p>
                Indian students preparing under CBSE can access our <strong>cbse class 12 MCQs</strong>, <strong>class 12 physics MCQs</strong>, <strong>class 12 chemistry MCQs</strong>, <strong>class 12 biology MCQs</strong>, <strong>class 12 maths MCQs</strong>, <strong>class 12 objective questions</strong> and <strong>class 12 board exam MCQs</strong>. Every question includes an instant answer reveal plus a one-line explanation, making our system a reliable source of <strong>solved MCQs for board exams</strong> and <strong>board exam important MCQs</strong>.
            </p>

            <div class="blog-quote">
                "A 15-minute daily online MCQs test can improve your memory retention by up to 60 %. Consistency beats cramming — start today."
            </div>

            <h2>How Funny Mode Helps in SEO — And in Your Studies</h2>
            <p>
                You may be wondering why a study website talks about a "funny mode". Here is the dual benefit. From a <strong>student perspective</strong>, research published in the <em>Journal of Experimental Psychology</em> shows that humour-embedded learning increases recall by 20 % because laughter triggers dopamine release in the hippocampus — the brain's memory centre. When you activate <strong>funny mode</strong> on Ahmad Learning Hub, answer explanations are rewritten with light-hearted analogies and witty one-liners that make complex concepts stick. Instead of reading "Ohm's Law states V = IR", you might see "Think of voltage as the pressure your chai-wala uncle applies to squeeze tea through a strainer (resistance) — more pressure, more flow (current)." Students remember this imagery during exams.
            </p>
            <p>
                From an <strong>SEO perspective</strong>, funny mode significantly improves key ranking signals that Google uses. Users who enjoy the content stay on the page longer (<strong>higher dwell time</strong>), interact with more quizzes (<strong>lower bounce rate</strong>) and share screenshots of funny explanations on social media (<strong>natural backlinks and social signals</strong>). Google's helpful-content algorithm rewards pages that genuinely satisfy user intent, and a quiz that makes students laugh <em>and</em> learn clearly delivers more value than a dry list of MCQs. Additionally, the unique humorous text creates differentiated content that no other education website offers — giving Ahmad Learning Hub an edge in topical authority for keywords like <strong>class 9 science quiz</strong>, <strong>chapter wise online MCQs test</strong> and <strong>online MCQs Pakistan</strong>. In short, funny mode isn't just a gimmick — it is a genuine competitive advantage for both learning and search-engine visibility.
            </p>

            <h2>Why Ahmad Learning Hub Beats Traditional Key-Books</h2>
            <ul>
                <li><strong>Interactive Feedback:</strong> Know exactly why an answer is wrong the moment you submit — no waiting for a teacher to check your work.</li>
                <li><strong>Randomised Questions:</strong> Every session shuffles the order and options, preventing pattern memorisation and forcing real understanding.</li>
                <li><strong>Mobile-Friendly Design:</strong> Practise <strong>matric physics MCQs with answers</strong> or <strong>FSc physics MCQs chapter wise</strong> on your phone while commuting — no data-heavy downloads required.</li>
                <li><strong>All Boards Covered:</strong> Whether you sit under Punjab, FBISE, Sindh, KPK boards or CBSE, questions follow the official textbooks and SLO frameworks.</li>
                <li><strong>Free Forever:</strong> No hidden charges, no premium paywalls. Every <strong>chapter wise online MCQs test</strong> is completely free.</li>
            </ul>

            <h3>Top Tips for High-Score Board Exam Preparation</h3>
            <ol>
                <li><strong>Read the Textbook First:</strong> MCQs are frequently lifted from "Do You Know?" boxes, activity prompts and chapter summaries — read these before practising.</li>
                <li><strong>Analyse Past Papers:</strong> Identify the <strong>most repeated MCQs</strong> from the last five years. Our important-MCQs collection already does this for you.</li>
                <li><strong>Simulate Exam Conditions:</strong> Set the timer when taking our <strong>online MCQs test</strong> to build speed and reduce exam-day anxiety.</li>
                <li><strong>Use Funny Mode for Weak Chapters:</strong> If a chapter feels boring or hard, switch to funny mode. The humorous explanations re-engage your brain and improve retention.</li>
                <li><strong>Track Your Score Trends:</strong> Re-take the same chapter quiz every week and compare scores — a rising trend means genuine learning.</li>
            </ol>

            <h2>Frequently Asked Questions</h2>
            <h3>Is this MCQs test free for all classes?</h3>
            <p>Yes. Ahmad Learning Hub offers a completely free <strong>online MCQs test</strong> for <strong>class 9, 10, 11 and 12</strong>. There are no hidden charges or premium accounts required.</p>

            <h3>Which boards are supported?</h3>
            <p>Our questions cover the textbooks prescribed by <strong>Punjab Board</strong>, <strong>Federal Board (FBISE)</strong>, <strong>Sindh Board</strong> and <strong>CBSE</strong>. We update the database each academic year to match the latest syllabus.</p>

            <h3>What subjects can I practise?</h3>
            <p>All major subjects: Physics, Chemistry, Biology, Mathematics, Computer Science, English, Islamiat, Pakistan Studies and General Science. Whether you need <strong>9th class physics chapter wise MCQs with answers</strong> or <strong>12th class biology MCQs with answers</strong>, we have you covered.</p>

            <h3>How does funny mode work?</h3>
            <p>Funny mode rewrites answer explanations with humour, analogies and pop-culture references. It is designed to boost memory retention through laughter — a technique backed by cognitive science research. Simply toggle the funny mode switch before starting your quiz.</p>

            <h3>Can I use this for MDCAT / ECAT / NEET preparation?</h3>
            <p>Absolutely. Our <strong>FSc part 1 MCQs</strong> and <strong>FSc part 2 MCQs</strong> align closely with the MDCAT and ECAT syllabi. CBSE students preparing for NEET will find our <strong>class 11</strong> and <strong>class 12 chapter wise MCQs</strong> equally relevant.</p>

            <div class="blog-cta-box">
                <h3>Start Your Free Online MCQs Test Now!</h3>
                <p>Don't wait until the last month. Select your Class and Subject from the menu above and begin your journey toward 100 % marks in the objective section today. Try <strong>funny mode</strong> for a study experience you will actually enjoy!</p>
            </div>
        </section>
    </div>
</article>

    <!-- BOTTOM AD BANNER -->
    <br>
</div>

<?php include __DIR__ . '/../includes/ai_loader.php'; ?>

<?php include '../footer.php'; ?>

<script>
// ─── DOM References ───
const classInput = document.getElementById('class_id');
const bookInput = document.getElementById('book_id');
const bookNameInput = document.getElementById('book_name_hidden');
const classCards = document.querySelectorAll('.class-card');
const bookCardsContainer = document.getElementById('bookCards');
const bookSection = document.getElementById('bookSection');
const startBtn = document.getElementById('startBtn');
const resetBtn = document.getElementById('resetBtn');

// Step indicators
const stepDot1 = document.getElementById('stepDot1');
const stepDot2 = document.getElementById('stepDot2');
const stepLine1 = document.getElementById('stepLine1');
const stepLabel1 = document.getElementById('stepLabel1');
const stepLabel2 = document.getElementById('stepLabel2');

// Choose an appropriate SVG icon and color based on book name
function chooseBookIcon(name) {
    const n = (name || '').toLowerCase();
    const icons = {
        science: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5"/><path d="M8 21V11"/><path d="M16 21V11"/><path d="M12 3v8"/><path d="M8 7h8"/></svg>`, color: '#0ea5a4' },
        chemistry: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M8 6h8l-2 6a4 4 0 1 1-8 0L8 6z"/></svg>`, color: '#f97316' },
        math: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14"/><path d="M16 5v14"/><path d="M3 12h18"/></svg>`, color: '#7c3aed' },
        computer: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8"/></svg>`, color: '#2563eb' },
        geography: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M2 12h4"/><path d="M18 12h4"/></svg>`, color: '#16a34a' },
        default: { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4H20v16H5.5A2.5 2.5 0 0 1 3 17.5v-11z"/></svg>`, color: getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#4f46e5' }
    };

    if (n.match(/physics|science|biology|chemistry/)) return icons.science;
    if (n.match(/chemistry|organic|inorganic/)) return icons.chemistry;
    if (n.match(/math|mathematics|calculus|algebra/)) return icons.math;
    if (n.match(/computer|computer science|cs|programming|c\+\+|python/)) return icons.computer;
    if (n.match(/geograph|geography|earth|world/)) return icons.geography;
    return icons.default;
}

let progressInterval;
function startLoaderProgress() {
    const progressBar = document.getElementById('loaderProgressBar');
    if (!progressBar) return;
    progressBar.style.width = '0%';
    clearInterval(progressInterval);
    let width = 0;
    progressInterval = setInterval(() => {
        if (width >= 90) {
            if (width < 95) width += 0.1;
        } else {
            const increment = Math.max(0.5, (90 - width) / 20);
            width += increment;
        }
        progressBar.style.width = width + '%';
    }, 100);
}

function toQuery(params) {
  return Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}

// ─── Step Updates ───
function updateSteps(step) {
    if (step >= 1) {
        stepDot1.classList.add('active');
        stepLabel1.classList.add('active');
    }
    if (step >= 2) {
        stepDot1.classList.remove('active');
        stepDot1.classList.add('completed');
        stepDot1.textContent = '';
        stepLabel1.classList.remove('active');
        stepLabel1.classList.add('completed');
        stepLine1.classList.add('active');
        stepDot2.classList.add('active');
        stepLabel2.classList.add('active');
        bookSection.classList.add('active');
    } else {
        stepDot1.classList.remove('completed');
        stepDot1.classList.add('active');
        stepDot1.textContent = '1';
        stepLabel1.classList.remove('completed');
        stepLabel1.classList.add('active');
        stepLine1.classList.remove('active');
        stepDot2.classList.remove('active');
        stepLabel2.classList.remove('active');
        bookSection.classList.remove('active');
    }
}

// ─── Class Card Click ───
classCards.forEach(card => {
    const activateClassCard = async () => {
        // Deselect all
        classCards.forEach(c => c.classList.remove('selected'));
        // Select this one
        card.classList.add('selected');
        const cid = card.getAttribute('data-class-id');
        classInput.value = cid;

        // Reset book selection
        bookInput.value = '';
        bookNameInput.value = '';
        startBtn.disabled = true;

        // Move to step 2
        updateSteps(2);

        // Load books
        await loadBooks(cid);
    };

    card.addEventListener('click', activateClassCard);
    card.addEventListener('keydown', async (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            await activateClassCard();
        }
    });
    
});

// ─── Load Books ───
async function loadBooks(classId) {
    // Show skeleton
    bookCardsContainer.innerHTML = `
        <div class="book-skeleton">
            <div class="book-skeleton-item"></div>
            <div class="book-skeleton-item"></div>
            <div class="book-skeleton-item"></div>
        </div>
    `;

    try {
        const res = await fetch('quiz_data.php?' + toQuery({ type: 'books', class_id: classId }));
        const data = await res.json();

        if (!data || data.length === 0) {
            bookCardsContainer.innerHTML = `
                <div class="book-empty-state">
                    <span class="empty-icon" aria-hidden="true"></span>
                    No books found for this class
                </div>
            `;
            return;
        }

        bookCardsContainer.innerHTML = data.map((b, idx) => `
            <div class="book-card" tabindex="0" data-book-id="${b.book_id}" data-book-name="${b.book_name}">
                <div class="book-card-emoji"></div>
                <div class="book-card-name">${b.book_name}</div>
                <div class="book-card-radio"></div>
            </div>
        `).join('');

        // Inject per-book icons and attach click handlers to book cards
        document.querySelectorAll('.book-card').forEach(bcard => {
            const bname = bcard.getAttribute('data-book-name') || '';
            const icon = chooseBookIcon(bname);
            const emojiEl = bcard.querySelector('.book-card-emoji');
            if (emojiEl) {
                emojiEl.innerHTML = icon.svg;
                emojiEl.style.color = icon.color;
            }
            const selectBookCard = () => {
                document.querySelectorAll('.book-card').forEach(bc => bc.classList.remove('selected'));
                bcard.classList.add('selected');
                bookInput.value = bcard.getAttribute('data-book-id');
                bookNameInput.value = bcard.getAttribute('data-book-name');
                startBtn.disabled = false;
            };

            bcard.addEventListener('click', selectBookCard);
            bcard.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    selectBookCard();
                }
            });
        });

    } catch (error) {
        bookCardsContainer.innerHTML = `
            <div class="book-empty-state">
                <span class="empty-icon" aria-hidden="true"></span>
                Error loading books. Please try again.
            </div>
        `;
        console.error('Error loading books:', error);
    }
}

// ─── Pre-fill from URL ───
(async function() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlClassId = urlParams.get('class_id');
    const urlBookId = urlParams.get('book_id');

    if (urlClassId) {
        const matchCard = document.querySelector(`.class-card[data-class-id="${urlClassId}"]`);
        if (matchCard) {
            matchCard.classList.add('selected');
            classInput.value = urlClassId;
            updateSteps(2);
            await loadBooks(urlClassId);

            if (urlBookId) {
                const matchBook = document.querySelector(`.book-card[data-book-id="${urlBookId}"]`);
                if (matchBook) {
                    matchBook.classList.add('selected');
                    bookInput.value = urlBookId;
                    bookNameInput.value = matchBook.getAttribute('data-book-name');
                    startBtn.disabled = false;
                }
            }
        }
    }
})();

// ─── Reset ───
resetBtn.addEventListener('click', () => {
    classCards.forEach(c => c.classList.remove('selected'));
    classInput.value = '';
    bookInput.value = '';
    bookNameInput.value = '';
    startBtn.disabled = true;
    updateSteps(1);
    bookCardsContainer.innerHTML = `
        <div class="book-empty-state">
            <span class="empty-icon" aria-hidden="true"></span>
            Pick a class above to see available books
        </div>
    `;
});

// ─── Form Submit ───
function createSlug(string) {
    let slug = string.toLowerCase();
    slug = slug.replace(/[\s_]+/g, '-');
    slug = slug.replace(/[^a-z0-9-]/g, '');
    return slug.replace(/^-+|-+$/g, '');
}

document.getElementById('quizForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const selectedClass = classInput.value;
    const bookName = bookNameInput.value;
    if (!selectedClass || !bookName) return;
    const bookSlug = createSlug(bookName);
    const seoUrl = `/class-${selectedClass}-${bookSlug}-mcqs`;
    window.location.href = seoUrl;
});
</script>
</body>
</html>

