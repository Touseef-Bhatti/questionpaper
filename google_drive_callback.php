<?php
require_once __DIR__ . '/admin/security.php';
require_once __DIR__ . '/services/GoogleDriveService.php';

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'], true)) {
    header('Location: admin/login.php');
    exit;
}

$message = '';
$error = '';
$details = [];

try {
    $drive = new GoogleDriveService();

    if (($_GET['action'] ?? '') === 'connect') {
        header('Location: ' . $drive->getAuthorizationUrl());
        exit;
    }

    if (!empty($_GET['error'])) {
        $error = 'Google Drive authorization was cancelled or denied: ' . (string) $_GET['error'];
    } elseif (!empty($_GET['code'])) {
        $state = (string)($_GET['state'] ?? '');
        $expectedState = (string)($_SESSION['google_drive_oauth_state'] ?? '');
        unset($_SESSION['google_drive_oauth_state']);

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new Exception('OAuth state check failed. Please start the connection again from this page.');
        }

        $tokenData = $drive->exchangeAuthorizationCode((string) $_GET['code']);
        $message = 'Google Drive OAuth is connected. Class notes uploads will now use this Google account.';
        $details[] = 'Refresh token saved securely in storage/gdrive_oauth_token.json.';
        $details[] = 'Access token expires in about ' . (int)($tokenData['expires_in'] ?? 3600) . ' seconds and will refresh automatically.';
    }

    $diagnostics = $drive->diagnoseConnection();
} catch (Throwable $e) {
    $diagnostics = null;
    $error = $e->getMessage();
}

$isConnected = is_array($diagnostics) && !empty($diagnostics['oauth_connected']);
$clientConfigured = is_array($diagnostics) && !empty($diagnostics['oauth_client_configured']);
$redirectUri = is_array($diagnostics) ? ($diagnostics['oauth_redirect_uri'] ?? 'https://ahmadlearninghub.com.pk/google_drive_callback.php') : 'https://ahmadlearninghub.com.pk/google_drive_callback.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/includes/favicons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Google Drive OAuth Setup | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/main.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f6f8fb; color: #172033; }
        .oauth-shell { max-width: 860px; margin: 0 auto; padding: 32px 20px 56px; }
        .oauth-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .oauth-title h1 { margin: 0; font-size: 1.65rem; font-weight: 800; color: #0f172a; }
        .oauth-title p { margin: 6px 0 0; color: #64748b; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); margin-bottom: 16px; }
        .status-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .status { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; }
        .status strong { display: block; font-size: .82rem; color: #475569; margin-bottom: 4px; }
        .status span { font-weight: 800; color: #0f172a; }
        .alert { border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; font-weight: 650; }
        .alert.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
        .alert.err { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 16px; border-radius: 8px; text-decoration: none; font-weight: 750; border: 1px solid transparent; cursor: pointer; }
        .btn.primary { color: #fff; background: #2563eb; }
        .btn.secondary { color: #334155; background: #fff; border-color: #cbd5e1; }
        code { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 6px; color: #0f172a; word-break: break-all; }
        ul { margin: 10px 0 0; padding-left: 20px; color: #475569; line-height: 1.7; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        @media (max-width: 720px) {
            .oauth-header, .status-row { grid-template-columns: 1fr; display: grid; }
            .oauth-header .actions { justify-content: start; margin-top: 0; }
        }
    </style>
</head>
<body>
    <?php $only_navbar = true; include __DIR__ . '/header.php'; unset($only_navbar); ?>

    <main class="oauth-shell">
        <div class="oauth-header">
            <div class="oauth-title">
                <h1><i class="fab fa-google-drive" style="color:#4285F4"></i> Google Drive OAuth</h1>
                <p>Connect the Google account that should own class-note uploads.</p>
            </div>
            <div class="actions">
                <a class="btn secondary" href="admin/uploadingNotesForClasses/index.php"><i class="fas fa-arrow-left"></i> Notes Admin</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert err"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="panel">
            <div class="status-row">
                <div class="status">
                    <strong>OAuth Client</strong>
                    <span><?= $clientConfigured ? 'Configured' : 'Missing' ?></span>
                </div>
                <div class="status">
                    <strong>Drive Account</strong>
                    <span><?= $isConnected ? 'Connected' : 'Not Connected' ?></span>
                </div>
                <div class="status">
                    <strong>Active Auth</strong>
                    <span><?= htmlspecialchars($diagnostics['auth_mode'] ?? 'Not ready') ?></span>
                </div>
            </div>

            <div class="actions">
                <?php if ($clientConfigured): ?>
                    <a class="btn primary" href="?action=connect"><i class="fab fa-google"></i> Connect Google Drive</a>
                <?php endif; ?>
                <a class="btn secondary" href="?"><i class="fas fa-rotate"></i> Refresh Status</a>
            </div>
        </section>

        <section class="panel">
            <h2 style="margin:0 0 10px;font-size:1.05rem;">Required Google Cloud Settings</h2>
            <ul>
                <li>Authorized redirect URI must be <code><?= htmlspecialchars($redirectUri) ?></code></li>
                <li>Set <code>GOOGLE_DRIVE_AUTH_MODE=oauth</code> in <code>config/.env</code> when you are ready to force OAuth.</li>
                <li>Set <code>GOOGLE_DRIVE_CLIENT_ID</code> and <code>GOOGLE_DRIVE_CLIENT_SECRET</code> in <code>config/.env</code>.</li>
                <li>Keep <code>GOOGLE_DRIVE_FOLDER_ID</code> as the Drive folder ID or create a folder named <code>AhmadLearningHub</code>.</li>
            </ul>
        </section>

        <?php if ($details): ?>
            <section class="panel">
                <h2 style="margin:0 0 10px;font-size:1.05rem;">Connection Details</h2>
                <ul>
                    <?php foreach ($details as $detail): ?>
                        <li><?= htmlspecialchars($detail) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (is_array($diagnostics) && !empty($diagnostics['errors'])): ?>
            <section class="panel">
                <h2 style="margin:0 0 10px;font-size:1.05rem;">Diagnostics</h2>
                <ul>
                    <?php foreach ($diagnostics['errors'] as $diagError): ?>
                        <li><?= htmlspecialchars($diagError) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
