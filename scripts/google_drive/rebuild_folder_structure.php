<?php
/**
 * Rebuild/verify the stable Google Drive folder structure.
 *
 * Run from the project root with:
 *   php scripts/google_drive/rebuild_folder_structure.php
 *
 * Dynamic book, chapter, and Admin/User folders are created lazily when a
 * matching file is uploaded.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../../services/GoogleDriveService.php';

try {
    $drive = new GoogleDriveService();
    $layout = $drive->ensureStandardFolderStructure();
    echo json_encode([
        'success' => true,
        'message' => 'Google Drive folder structure is ready.',
        'layout' => $layout,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
