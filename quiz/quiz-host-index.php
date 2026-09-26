<?php
// Public SEO landing page for live quiz hosting.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/db_connect.php';

$pageTitle = 'Online Quiz Maker & Hosting Platform | Pakistan';
$metaDescription = 'Create timed online MCQ quizzes for schools, teachers and academies in Pakistan. Share a room code, monitor participation and review results.';
$metaKeywords = 'online quiz maker Pakistan, classroom quiz maker, live MCQ quiz, online test maker, quiz hosting platform, teacher quiz tool, online exam platform';
$siteBaseUrl = rtrim(EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', 'https://ahmadlearninghub.com.pk')), '/');
$canonicalUrl = $siteBaseUrl . '/online-quiz-hosting';

$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl . '#webpage',
            'url' => $canonicalUrl,
            'name' => $pageTitle,
            'description' => $metaDescription,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Ahmad Learning Hub', 'url' => $siteBaseUrl],
            'about' => ['@type' => 'Thing', 'name' => 'Live online quiz hosting'],
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Ahmad Learning Hub Live Quiz Hosting',
            'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'Web browser',
            'url' => $canonicalUrl,
            'description' => $metaDescription,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PKR'],
            'provider' => ['@type' => 'Organization', 'name' => 'Ahmad Learning Hub', 'url' => $siteBaseUrl],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static function (array $faq): array {
                return [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ];
            }, [
                ['question' => 'Can Ahmad Learning Hub detect tab switching during an online exam?', 'answer' => 'The quiz-taking page monitors browser visibility changes and window focus loss, then records tab-switch and window-blur events for the live quiz room.'],
                ['question' => 'Does the online quiz prevent copy and paste?', 'answer' => 'During the quiz, text selection, copy, cut, paste, right-click and common inspection shortcuts are restricted where the browser allows it, and relevant attempts can be logged.'],
                ['question' => 'Is Ahmad Learning Hub suitable for online exams?', 'answer' => 'It is designed for classroom quizzes, academy assessments, revision tests and supervised online exams with timed questions, participant identity, live rooms, monitoring events and results.'],
                ['question' => 'Do students need an account to take a hosted quiz?', 'answer' => 'Students join with the room code, name and roll number. The host must sign in to create and manage the quiz room.'],
            ]),
        ],
    ],
];

$integrityFaqs = [
    ['question' => 'Can Ahmad Learning Hub detect tab switching during an online exam?', 'answer' => 'Yes. The quiz page monitors browser visibility changes and window focus loss, then records tab-switch and window-blur events for the host to review.'],
    ['question' => 'Does the online quiz prevent copy and paste?', 'answer' => 'During the quiz, text selection, copy, cut, paste, right-click and common inspection shortcuts are restricted where the browser allows it. Attempts can also be recorded as activity events.'],
    ['question' => 'Is this an anti-cheating system?', 'answer' => 'It provides browser-based exam-integrity controls and activity monitoring. No browser-only system can guarantee that every form of cheating is impossible, so institutions should also use clear instructions and appropriate supervision.'],
    ['question' => 'What makes this a strong online exam platform?', 'answer' => 'Hosts can create timed MCQ rooms, use textbook or custom questions, share a room code, monitor participation and review scores and activity events from one workflow.'],
    ['question' => 'Can I use it for school, academy and remote exams?', 'answer' => 'Yes. It supports classroom quizzes, academy tests, board-exam revision, formative assessments and supervised remote learning sessions.'],
    ['question' => 'Do students need an account to take a hosted quiz?', 'answer' => 'Students join with the room code, name and roll number. The host must sign in to create and manage the quiz room.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once dirname(__DIR__) . '/includes/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Ahmad Learning Hub</title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="author" content="Ahmad Learning Hub">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:site_name" content="Ahmad Learning Hub">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <link rel="stylesheet" href="<?= ($assetBase ?? '') ?>css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .host-index { background: linear-gradient(145deg, #f8fbff 0%, #eef2ff 52%, #f8fafc 100%); color: #172033; padding: 3.5rem 1rem 5rem; }
        .host-index__wrap { max-width: 1120px; margin: 0 auto; }
        .host-index__hero { display: grid; grid-template-columns: 1.15fr .85fr; gap: 2.5rem; align-items: center; }
        .host-index__eyebrow { color: #4f46e5; font-size: .8rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .host-index h1 { margin: .7rem 0 1rem; color: #111827; font-size: clamp(2.25rem, 5vw, 4.4rem); line-height: 1.05; }
        .host-index h2 { color: #111827; font-size: clamp(1.55rem, 3vw, 2.25rem); }
        .host-index p { color: #526078; font-size: 1.05rem; line-height: 1.75; }
        .host-index__actions { display: flex; flex-wrap: wrap; gap: .85rem; margin: 1.6rem 0; }
        .host-index__button { display: inline-flex; align-items: center; justify-content: center; gap: .55rem; min-height: 48px; padding: .8rem 1.25rem; border-radius: 12px; font-weight: 800; text-decoration: none; }
        .host-index__button--primary { background: #4f46e5; color: #fff; box-shadow: 0 10px 24px rgba(79,70,229,.22); }
        .host-index__button--secondary { background: #fff; color: #3730a3; border: 1px solid #c7d2fe; }
        .host-index__note { font-size: .92rem; color: #64748b; }
        .host-index__card { background: rgba(255,255,255,.88); border: 1px solid #dbe4f0; border-radius: 24px; padding: 1.5rem; box-shadow: 0 18px 50px rgba(30,41,59,.1); }
        .host-index__preview { display: grid; gap: .8rem; }
        .host-index__preview-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1rem; background: #f8fafc; border-radius: 12px; }
        .host-index__preview-row strong { color: #1e293b; }
        .host-index__preview-row span { color: #4f46e5; font-weight: 800; }
        .host-index__section { padding-top: 5rem; }
        .host-index__section > h2, .host-index__section > p { text-align: center; max-width: 760px; margin-left: auto; margin-right: auto; }
        .host-index__integrity { background: #111827; border-radius: 20px; padding: 2rem; color: #fff; }
        .host-index__integrity h2, .host-index__integrity h3, .host-index__integrity p { color: #fff; }
        .host-index__integrity .host-index__grid { margin-top: 1.25rem; }
        .host-index__integrity .host-index__feature { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15); }
        .host-index__integrity .host-index__feature h3 { color: #fff; }
        .host-index__integrity .host-index__feature p { color: #dbeafe; }
        .host-index__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 2rem; }
        .host-index__feature, .host-index__step, .host-index__faq { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; }
        .host-index__feature i { color: #4f46e5; font-size: 1.5rem; }
        .host-index__feature h3, .host-index__step h3, .host-index__faq h3 { margin: .7rem 0 .35rem; color: #1e293b; }
        .host-index__feature p, .host-index__step p, .host-index__faq p { margin: 0; font-size: .96rem; }
        .host-index__step-number { display: inline-grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #eef2ff; color: #4338ca; font-weight: 800; }
        .host-index__faq-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 2rem; }
        .host-index__cta { margin-top: 4rem; text-align: center; background: #1e1b4b; border-radius: 24px; padding: 2.5rem 1.25rem; }
        .host-index__cta h2, .host-index__cta p { color: #fff; }
        @media (max-width: 760px) { .host-index__hero, .host-index__grid, .host-index__faq-grid { grid-template-columns: 1fr; } .host-index { padding-top: 2rem; } }
    </style>
</head>
<body>
<?php $skip_shell = true; ?>
<?php include_once dirname(__DIR__) . '/header.php'; ?>

<main class="host-index">
    <div class="host-index__wrap">
        <section class="host-index__hero" aria-labelledby="host-index-title">
            <div>
                <span class="host-index__eyebrow">For teachers, tutors and academies</span>
                <h1 id="host-index-title">A smarter online exam and live quiz platform</h1>
                <p>Host secure, engaging online MCQ exams for schools, academies and remote learners. Choose questions, create a timed room, share one code with your students and review participation and results from Ahmad Learning Hub.</p>
                <div class="host-index__actions">
                    <a class="host-index__button host-index__button--primary" href="online_quiz_host_new.php"><i class="fas fa-rocket" aria-hidden="true"></i> Start hosting a quiz</a>
                    <a class="host-index__button host-index__button--secondary" href="online_quiz_join.php"><i class="fas fa-users" aria-hidden="true"></i> Join a quiz room</a>
                </div>
                <div class="host-index__note"><i class="fas fa-lock" aria-hidden="true"></i> Sign in is required to create and manage a quiz room.</div>
            </div>
            <div class="host-index__card" aria-label="Live quiz room preview">
                <div class="host-index__preview">
                    <div class="host-index__preview-row"><strong>Live MCQ room</strong><span><i class="fas fa-circle" aria-hidden="true"></i> Ready</span></div>
                    <div class="host-index__preview-row"><strong>Participants</strong><span>15 online</span></div>
                    <div class="host-index__preview-row"><strong>Room code</strong><span>ABC 123</span></div>
                    <div class="host-index__preview-row"><strong>Integrity events</strong><span>Monitored</span></div>
                </div>
            </div>
        </section>

        <section class="host-index__section" aria-labelledby="teaching-capabilities-title">
            <h2 id="teaching-capabilities-title">Built for Live Teaching and Assessment</h2>
            <p>Create questions faster, manage a live room and review participant performance from one workflow.</p>
            <div class="host-index__grid">
                <article class="host-index__feature">
                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                    <h3>Live Performance</h3>
                    <p>Monitor participation, answers and scores from the host dashboard.</p>
                </article>
                <article class="host-index__feature">
                    <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                    <h3>Flexible Question Creation</h3>
                    <p>Use textbook MCQs, topic search, file generation or your own saved questions.</p>
                </article>
                <article class="host-index__feature">
                    <i class="fas fa-ranking-star" aria-hidden="true"></i>
                    <h3>Engaging Live Sessions</h3>
                    <p>Share a room code and use the leaderboard to keep learners involved.</p>
                </article>
            </div>
        </section>

        <section class="host-index__section" aria-labelledby="host-benefits-title">
            <h2 id="host-benefits-title">A complete platform for online exams and live quizzes</h2>
            <p>Build a quiz around your lesson, give students a simple way to join, and use live participation and results to guide feedback.</p>
            <div class="host-index__grid">
                <article class="host-index__feature"><i class="fas fa-book-open" aria-hidden="true"></i><h3>Flexible question sources</h3><p>Use textbook questions, topics, saved MCQs, custom questions or supported uploaded learning material.</p></article>
                <article class="host-index__feature"><i class="fas fa-share-alt" aria-hidden="true"></i><h3>Simple room sharing</h3><p>Create a room code and join link that students can open on a phone, tablet or computer.</p></article>
                <article class="host-index__feature"><i class="fas fa-chart-line" aria-hidden="true"></i><h3>Live participation and results</h3><p>Manage the session, follow participation and review scores after the room finishes.</p></article>
            </div>
        </section>

        <section class="host-index__section host-index__integrity" aria-labelledby="host-integrity-title">
            <h2 id="host-integrity-title">Built-in online exam integrity controls</h2>
            <p>Our browser-based quiz monitoring helps hosts identify suspicious activity during a live exam. Events are associated with the participant and quiz room for review.</p>
            <div class="host-index__grid">
                <article class="host-index__feature"><i class="fas fa-eye" aria-hidden="true"></i><h3>Tab and focus monitoring</h3><p>Tab visibility changes, minimizing and switching to another window can be detected and recorded as activity events.</p></article>
                <article class="host-index__feature"><i class="fas fa-ban" aria-hidden="true"></i><h3>Copy and paste restrictions</h3><p>Text selection, copy, cut and paste actions are restricted during the quiz where the browser supports those controls.</p></article>
                <article class="host-index__feature"><i class="fas fa-shield-alt" aria-hidden="true"></i><h3>Exam activity review</h3><p>Hosts can use logged activity alongside answers, timing and participation when reviewing an assessment.</p></article>
            </div>
            <p class="host-index__note">Browser controls are a deterrent and monitoring aid, not a replacement for institutional exam rules or human supervision.</p>
        </section>

        <section class="host-index__section" aria-labelledby="host-steps-title">
            <h2 id="host-steps-title">How to host an online quiz</h2>
            <div class="host-index__grid">
                <article class="host-index__step"><span class="host-index__step-number">1</span><h3>Choose your content</h3><p>Select a class, book, chapters or topic, or add questions of your own.</p></article>
                <article class="host-index__step"><span class="host-index__step-number">2</span><h3>Configure the room</h3><p>Set the question count and duration, then review the quiz before publishing it.</p></article>
                <article class="host-index__step"><span class="host-index__step-number">3</span><h3>Invite students</h3><p>Share the generated room code or join link with your class or learning group.</p></article>
            </div>
        </section>

        <section class="host-index__section" aria-labelledby="host-faq-title">
            <h2 id="host-faq-title">Online exam and quiz hosting FAQs</h2>
            <div class="host-index__faq-grid">
                <?php foreach ($integrityFaqs as $faq): ?>
                    <article class="host-index__faq"><h3><?= htmlspecialchars($faq['question']) ?></h3><p><?= htmlspecialchars($faq['answer']) ?></p></article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="host-index__cta" aria-labelledby="host-cta-title">
            <h2 id="host-cta-title">Ready to create your live quiz room?</h2>
            <p>Sign in, prepare your questions and share a room code with your learners.</p>
            <a class="host-index__button host-index__button--primary" href="online_quiz_host_new.php"><i class="fas fa-plus-circle" aria-hidden="true"></i> Create a live quiz room</a>
        </section>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/footer.php'; ?>
</body>
</html>
