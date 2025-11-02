<?php
require_once 'db_connect.php';

header('Content-Type: application/xml; charset=UTF-8');

// Your domain URL - update this to your actual domain
$baseUrl = 'https://yourdomain.com/questionpaper';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
$staticPages = [
    ['url' => '', 'priority' => '1.0', 'changefreq' => 'daily'], // Home page
    ['url' => '/select_class.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/notes.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/about.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['url' => '/contact.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['url' => '/subscription.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/login.php', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ['url' => '/register.php', 'priority' => '0.5', 'changefreq' => 'monthly']
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
