<?php
/**
 * Shared SEO and generative-search metadata for Ahmad Learning Hub.
 *
 * Keep this helper dependency-light so it can be used by public pages before
 * the navigation shell is rendered. It intentionally does not generate FAQ
 * or HowTo markup; visible, useful answers are preferable to schema markup
 * that is not eligible for Google's rich results.
 */

if (!function_exists('alh_seo_site_url')) {
    function alh_seo_site_url(): string
    {
        // Keep local canonical/OG URLs local during development. The request
        // host is used so ports such as localhost:8001 are preserved, while
        // production continues to use the configured public domain below.
        $requestHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost !== '' && preg_match('/^(?:localhost|127\.0\.0\.1|::1)(?::\d+)?$/i', $requestHost)) {
            $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            return ($isHttps ? 'https://' : 'http://') . $requestHost;
        }

        $configured = '';
        if (class_exists('EnvLoader')) {
            $configured = trim((string) EnvLoader::get('PUBLIC_SITE_URL', ''));
            if ($configured === '') {
                $configured = trim((string) EnvLoader::get('BASE_URL', ''));
            }
        }

        if ($configured === '' || preg_match('/localhost|127\.0\.0\.1|\.local(?:$|\/)/i', $configured)) {
            $configured = 'https://ahmadlearninghub.com.pk';
        }

        return rtrim($configured, '/');
    }
}

if (!function_exists('alh_seo_absolute_url')) {
    function alh_seo_absolute_url(string $path = ''): string
    {
        if ($path === '') {
            return alh_seo_site_url();
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return alh_seo_site_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('alh_seo_current_path')) {
    function alh_seo_current_path(?string $fallback = null): string
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = is_string($requestPath) && $requestPath !== '' ? $requestPath : ($fallback ?? '/');
        $path = '/' . ltrim($path, '/');

        $legacyRoutes = [
            '/index.php' => '/',
            '/index' => '/',
            '/home' => '/online-question-paper-generator',
            '/home.php' => '/online-question-paper-generator',
            '/reviews.php' => '/reviews',
            '/select_class' => '/class-9th-and-10th-online-question-paper-generator',
            '/select_class.php' => '/class-9th-and-10th-online-question-paper-generator',
            '/select_class_11-12.php' => '/class-11-and-12-online-question-paper-generator',
            '/quiz/quiz_setup.php' => '/class-9-and-10-online-mcqs-prepation-test',
            '/quiz/quiz_setup_inter.php' => '/class-11-and-12-online-mcqs-prepation-test',
            '/quiz/quiz-host-index.php' => '/online-quiz-hosting',
            '/quiz/mcqs_topic.php' => '/topic-wise-mcqs-test',
            '/notes/note.php' => '/study-material-for-board-exam-preparations',
            '/mcqs.php' => '/class-9-10-11-12-mcqs-for-board-exams',
            '/notes/mcqs.php' => '/class-9-10-11-12-mcqs-for-board-exams',
            '/examPreparation/select_class_for_test.php' => '/class-9-10-11-12-test-series-for-board-exams',
        ];

        return $legacyRoutes[$path] ?? rtrim($path, '/') ?: '/';
    }
}

if (!function_exists('alh_seo_json')) {
    function alh_seo_json(array $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('alh_seo_graph')) {
    function alh_seo_graph(array $options = []): array
    {
        $siteUrl = alh_seo_site_url();
        $title = trim((string) ($options['title'] ?? ($GLOBALS['pageTitle'] ?? 'Ahmad Learning Hub')));
        $description = trim((string) ($options['description'] ?? ($GLOBALS['metaDescription'] ?? 'Online question paper generation, MCQs practice, study notes, exam preparation and live quiz hosting for students and teachers.')));
        $canonical = (string) ($options['canonical'] ?? alh_seo_absolute_url(alh_seo_current_path()));
        $pageType = (string) ($options['page_type'] ?? 'WebPage');
        $image = (string) ($options['image'] ?? alh_seo_absolute_url('/favicon/web-app-manifest-512x512.png'));
        $audienceType = trim((string) ($options['audience_type'] ?? 'students, teachers, tutors and schools in Pakistan'));

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'EducationalOrganization',
                    '@id' => $siteUrl . '#organization',
                    'name' => 'Ahmad Learning Hub',
                    'url' => $siteUrl,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $image,
                    ],
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'Pakistan',
                    ],
                    'knowsAbout' => [
                        'online question paper generator',
                        'Class 9, 10, 11 and 12 exam preparation',
                        'chapter-wise MCQs practice',
                        'Punjab Board study materials',
                        'live online quiz hosting',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $siteUrl . '#website',
                    'url' => $siteUrl,
                    'name' => 'Ahmad Learning Hub',
                    'publisher' => ['@id' => $siteUrl . '#organization'],
                    'inLanguage' => 'en-PK',
                ],
                [
                    '@type' => $pageType,
                    '@id' => $canonical . '#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description,
                    'isPartOf' => ['@id' => $siteUrl . '#website'],
                    'about' => ['@id' => $siteUrl . '#organization'],
                    'inLanguage' => 'en-PK',
                    'audience' => [
                        '@type' => 'EducationalAudience',
                        'educationalRole' => 'student',
                         'audienceType' => $audienceType,
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('alh_render_seo_head')) {
    function alh_render_seo_head(array $options = []): void
    {
        $siteUrl = alh_seo_site_url();
        $title = trim((string) ($options['title'] ?? ($GLOBALS['pageTitle'] ?? 'Ahmad Learning Hub')));
        $description = trim((string) ($options['description'] ?? ($GLOBALS['metaDescription'] ?? 'Online question paper generation, MCQs practice, study notes, exam preparation and live quiz hosting for students and teachers.')));
        $keywords = trim((string) ($options['keywords'] ?? ($GLOBALS['metaKeywords'] ?? '')));
        $canonical = (string) ($options['canonical'] ?? alh_seo_absolute_url(alh_seo_current_path()));
        $image = (string) ($options['image'] ?? alh_seo_absolute_url('/favicon/web-app-manifest-512x512.png'));
        $type = (string) ($options['type'] ?? 'website');
        $robots = (string) ($options['robots'] ?? 'index, follow, max-image-preview:large');
        $includeTitle = (bool) ($options['include_title'] ?? true);
        $includeDescription = (bool) ($options['include_description'] ?? true);
        $includeKeywords = (bool) ($options['include_keywords'] ?? true);
        $includeRobots = (bool) ($options['include_robots'] ?? true);
        $includeAuthor = (bool) ($options['include_author'] ?? true);
        $contentLanguage = trim((string) ($options['content_language'] ?? 'en-PK'));

        if ($includeTitle) {
            echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
        }
        if ($includeDescription) {
            echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($includeKeywords && $keywords !== '') {
            echo '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($includeRobots) {
            echo '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($includeAuthor) {
            echo '<meta name="author" content="Ahmad Learning Hub">' . "\n";
        }
        echo '<meta name="content-language" content="' . htmlspecialchars($contentLanguage, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<link rel="alternate" type="text/plain" href="' . htmlspecialchars($siteUrl . '/llms.txt', ENT_QUOTES, 'UTF-8') . '" title="Ahmad Learning Hub AI-readable site guide">' . "\n";
        echo '<meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:site_name" content="Ahmad Learning Hub">' . "\n";
        echo '<meta property="og:locale" content="en_PK">' . "\n";
        echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<script type="application/ld+json">' . alh_seo_json(alh_seo_graph([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'page_type' => $options['page_type'] ?? 'WebPage',
            'audience_type' => $audienceType,
        ])) . '</script>' . "\n";
    }
}
