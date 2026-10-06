<?php
/**
 * Local font loader — include once per page (usually via header.php).
 *
 * Replaces ALL external Google Fonts <link> / @import tags and all
 * Font Awesome CDN <link> tags with self-hosted equivalents.
 *
 * Usage:  <?php include_once __DIR__ . '/includes/local_fonts.php'; ?>
 *
 * Files served from:
 *   /css/local-fonts.css  — @font-face rules for Inter, Poppins, Outfit,
 *                           Plus Jakarta Sans, Fredoka
 *   /fonts/fontawesome/   — Font Awesome 6.4.0 (css + webfonts)
 */

// Determine the base path relative to the web root.
// Works whether this file is included from the project root or a subdirectory.
$localFontsBasePath = '';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRoot = str_replace('\\', '/', dirname(__DIR__)); // one level up from /includes

if ($docRoot !== '' && str_starts_with($scriptDir, $docRoot)) {
    $relativeScript = substr($scriptDir, strlen($docRoot));
    $relativeProject = substr($projectRoot, strlen($docRoot));
    // How many dirs deep the calling script is relative to the project root
    $fromScript = trim($relativeScript, '/');
    $fromProject = trim($relativeProject, '/');

    if ($fromScript === $fromProject) {
        $localFontsBasePath = '';
    } else {
        // Calculate relative path from script to project root
        $scriptParts = $fromScript !== '' ? explode('/', $fromScript) : [];
        $projectParts = $fromProject !== '' ? explode('/', $fromProject) : [];
        // Find common prefix length
        $common = 0;
        $maxCommon = min(count($scriptParts), count($projectParts));
        for ($i = 0; $i < $maxCommon; $i++) {
            if ($scriptParts[$i] === $projectParts[$i]) {
                $common++;
            } else {
                break;
            }
        }
        $ups = count($scriptParts) - $common;
        $downs = array_slice($projectParts, $common);
        $localFontsBasePath = str_repeat('../', $ups) . implode('/', $downs);
        if ($localFontsBasePath !== '') {
            $localFontsBasePath = rtrim($localFontsBasePath, '/') . '/';
        }
    }
}
?>
<!-- Self-hosted Google Fonts (Inter, Poppins, Outfit, Plus Jakarta Sans, Fredoka) -->
<link rel="stylesheet" href="<?= htmlspecialchars($localFontsBasePath, ENT_QUOTES, 'UTF-8') ?>css/local-fonts.css">
<!-- Self-hosted Font Awesome 6.4.0 -->
<link rel="stylesheet" href="<?= htmlspecialchars($localFontsBasePath, ENT_QUOTES, 'UTF-8') ?>fonts/fontawesome/css/all.min.css">
