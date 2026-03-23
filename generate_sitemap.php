<?php
require_once 'db_connect.php';
require_once __DIR__ . '/config/env.php';

header('Content-Type: application/xml; charset=UTF-8');

// Base URL from environment (APP_URL or SITE_URL), fallback to primary domain
$baseUrl = rtrim(EnvLoader::get('APP_URL', EnvLoader::get('SITE_URL', 'https://ahmadlearninghub.com.pk')), '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
$staticPages = [
    ['url' => '', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/index.php', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/about.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['url' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['url' => '/privacy-policy.php', 'priority' => '0.6', 'changefreq' => 'yearly'],
    ['url' => '/terms-and-conditions.php', 'priority' => '0.6', 'changefreq' => 'yearly'],
    ['url' => '/subscription.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/login.php', 'priority' => '0.4', 'changefreq' => 'monthly'],
    ['url' => '/auth/register.php', 'priority' => '0.4', 'changefreq' => 'monthly'],
    ['url' => '/auth/forgot_password.php', 'priority' => '0.3', 'changefreq' => 'monthly'],
    ['url' => '/select_class.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/select_book.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/select_chapters.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/select_topics.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/select_topics_by_book.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/select_question.php', 'priority' => '0.6', 'changefreq' => 'weekly'],
    ['url' => '/generate_question_paper.php', 'priority' => '0.6', 'changefreq' => 'weekly'],
    ['url' => '/download_doc.php', 'priority' => '0.6', 'changefreq' => 'weekly'],
    ['url' => '/download_docx.php', 'priority' => '0.6', 'changefreq' => 'weekly'],
    ['url' => '/newsletter-signup.php', 'priority' => '0.3', 'changefreq' => 'monthly'],
    ['url' => '/profile.php', 'priority' => '0.5', 'changefreq' => 'weekly'],
    ['url' => '/profile_questions.php', 'priority' => '0.5', 'changefreq' => 'weekly']
];

foreach ($staticPages as $page) {
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}{$page['url']}</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}

// Add any dynamic pages here if needed in the future

echo '</urlset>';
?>
