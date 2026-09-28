<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'cookie_samesite' => 'Lax',
]);
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../services/GoogleDriveContentSyncService.php';

requireAdminAuth();

function driveSyncJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    driveSyncJson(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verifyCSRFToken($csrfToken)) {
    driveSyncJson(['ok' => false, 'error' => 'Security token invalid. Reload the page and try again.'], 403);
}

if (trim((string) ($_POST['action'] ?? '')) !== 'sync_drive') {
    driveSyncJson(['ok' => false, 'error' => 'Invalid sync action.'], 400);
}

try {
    $adminId = (int) ($_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? 0));
    $sync = new GoogleDriveContentSyncService();
    $result = $sync->sync($conn, $adminId);
    logAdminAction('google_drive_content_synced', json_encode($result, JSON_UNESCAPED_UNICODE));
    driveSyncJson($result);
} catch (Throwable $e) {
    error_log('Google Drive content sync failed: ' . $e->getMessage());
    driveSyncJson([
        'ok' => false,
        'error' => 'Google Drive sync failed. Check the Drive connection and folder permissions.',
    ], 500);
}
