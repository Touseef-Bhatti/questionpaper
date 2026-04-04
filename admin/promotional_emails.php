<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../email/phpmailer_mailer.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
requireAdminAuth();

$successMessage = '';
$errorMessage = '';

// Handle flash messages from session
if (isset($_SESSION['promo_success'])) {
    $successMessage = $_SESSION['promo_success'];
    unset($_SESSION['promo_success']);
}
if (isset($_SESSION['promo_error'])) {
    $errorMessage = $_SESSION['promo_error'];
    unset($_SESSION['promo_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $target = $_POST['target'] ?? 'all';
    $button1_label = trim($_POST['button1_label'] ?? '');
    $button1_url = trim($_POST['button1_url'] ?? '');
    $button2_label = trim($_POST['button2_label'] ?? '');
    $button2_url = trim($_POST['button2_url'] ?? '');
    $template_style = $_POST['template_style'] ?? 'standard';

    if ($subject === '' || $body === '') {
        $errorMessage = 'Subject and message body are required.';
    } else {
        // Determine recipients
        $users = [];

        // Allow full user selection via checkbox list in custom mode
        $selectedUserIds = $target === 'custom' && !empty($_POST['selected_user_ids']) ? array_map('intval', (array)$_POST['selected_user_ids']) : [];

        if ($target === 'custom' && empty($selectedUserIds)) {
            $errorMessage = 'Please select at least one user for the custom recipient group.';
        } else {

        if ($target === 'all') {
            $stmt = $conn->prepare("SELECT id,email,name FROM users WHERE email IS NOT NULL AND email != '' ORDER BY id DESC LIMIT 2000");
        } elseif ($target === 'active') {
            $stmt = $conn->prepare("SELECT id,email,name FROM users WHERE email IS NOT NULL AND email != '' AND verified = 1 ORDER BY id DESC LIMIT 2000");
        } elseif ($target === 'custom' && !empty($selectedUserIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedUserIds), '?'));
            $stmt = $conn->prepare("SELECT id,email,name FROM users WHERE id IN ($placeholders) AND email IS NOT NULL AND email != '' ORDER BY id DESC");
            if ($stmt) {
                $types = str_repeat('i', count($selectedUserIds));
                $stmt->bind_param($types, ...$selectedUserIds);
            }
        } else {
            $stmt = null;
        }

        if ($stmt) {
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        if (empty($users)) {
            $errorMessage = 'No users found for the selected recipient group.';
        } else {
            $sentCount = 0;
            $failedEmails = [];

            foreach ($users as $user) {
                $toEmail = $user['email'];
                if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) continue;

                // Setup PHPMailer using shared SMTP configuration helpers
                $mail = new PHPMailer(true);
                try {
                    configureMailerSmtp($mail);
                    $mail->setFrom(getMailerFromAddress(), getMailerFromName());
                    $mail->addAddress($toEmail, $user['name'] ?? '');
                    $mail->isHTML(true);
                    $mail->Subject = $subject;

                    $actionButtons = '';
                    if (!empty($button1_label) && filter_var($button1_url, FILTER_VALIDATE_URL)) {
                        $actionButtons .= "<a href='" . htmlspecialchars($button1_url, ENT_QUOTES) . "' style='display:inline-block;margin:5px 5px 5px 0;padding:12px 18px;background:#2f6dcf;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;'>" . htmlspecialchars($button1_label) . "</a>";
                    }
                    if (!empty($button2_label) && filter_var($button2_url, FILTER_VALIDATE_URL)) {
                        $actionButtons .= "<a href='" . htmlspecialchars($button2_url, ENT_QUOTES) . "' style='display:inline-block;margin:5px 5px 5px 0;padding:12px 18px;background:#6c757d;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;'>" . htmlspecialchars($button2_label) . "</a>";
                    }

                    $bgColor = ($template_style === 'highlight') ? '#f0f9ff' : '#ffffff';
                    $accentColor = ($template_style === 'highlight') ? '#1d4ed8' : '#1f2937';

                    $mailBodyHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlspecialchars($subject) . '</title></head>';
                    $mailBodyHtml .= '<body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial, sans-serif;">';
                    $mailBodyHtml .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fb;padding:32px 0;">';
                    $mailBodyHtml .= '<tr><td align="center"><table width="600" cellpadding="0" cellspacing="0" border="0" style="background:' . $bgColor . ';border-radius:12px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">';
                    $mailBodyHtml .= '<tr><td><h1 style="color:' . $accentColor . ';font-size:24px;margin:0 0 16px 0;">' . htmlspecialchars($subject) . '</h1></td></tr>';
                    $mailBodyHtml .= '<tr><td style="color:#333;line-height:1.6;font-size:16px;padding-bottom:18px;">' . nl2br(htmlspecialchars($body)) . '</td></tr>';
                    if (!empty($actionButtons)) {
                        $mailBodyHtml .= '<tr><td style="padding: 5px 0 0;">' . $actionButtons . '</td></tr>';
                    }
                    $mailBodyHtml .= '<tr><td style="padding-top:20px;color:#555;font-size:13px;border-top:1px solid #eee;">You are receiving this email because you subscribed to Ahmad Learning Hub updates. <br><a href="' . htmlspecialchars($fromEmail, ENT_QUOTES) . '" style="color:#1d4ed8;text-decoration:none;">Unsubscribe</a></td></tr>';
                    $mailBodyHtml .= '</table></td></tr></table></body></html>';

                    $mail->Body = $mailBodyHtml;
                    $mail->AltBody = strip_tags($subject . "\n\n" . $body . "\n\n" . ($button1_label ? $button1_label . ": " . $button1_url . "\n" : "") . ($button2_label ? $button2_label . ": " . $button2_url . "\n" : ""));

                    $mail->send();
                    $sentCount++;
                } catch (Exception $e) {
                    $failedEmails[] = $toEmail;
                }
            }

            $recipients = implode(', ', array_slice(array_column($users, 'email'), 0, 1000));
            $stmt = $conn->prepare("INSERT INTO promotional_email_campaigns (subject, body, recipient_emails, sent_count) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssi', $subject, $body, $recipients, $sentCount);
                $stmt->execute();
                $stmt->close();
            }

            $successMessage = "Email campaign sent: {$sentCount} delivered";
            if (!empty($failedEmails)) {
                $errorMessage = 'Failed to send to: ' . implode(', ', array_slice($failedEmails, 0, 20));
            }

            // Store messages in session and redirect to prevent double submission
            $_SESSION['promo_success'] = $successMessage;
            if (!empty($errorMessage)) {
                $_SESSION['promo_error'] = $errorMessage;
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
}

// User list for custom recipient group
$allUsers = [];
$stmt = $conn->prepare("SELECT id, name, email, role, verified FROM users WHERE email IS NOT NULL AND email != '' ORDER BY id DESC LIMIT 1500");
if ($stmt) {
    $stmt->execute();
    $allUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

?>
<?php include __DIR__ . '/header.php'; ?>
<div class="admin-container">
    <div class="top">
        <h1>Send Promotional Emails</h1>
        <div>
            <a class="btn btn-secondary" href="search_queries.php">Search Activity</a>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4" style="background:#fff; border-radius:10px; border: 1px solid #ddd;">
        <div class="mb-3">
            <label for="target" class="form-label">Recipient Group</label>
            <select id="target" name="target" class="form-control" onchange="toggleCustomUsers(this.value)">
                <option value="all" <?= (($_POST['target'] ?? '') === 'all') ? 'selected' : '' ?>>All users (limit 2000)</option>
                <option value="active" <?= (($_POST['target'] ?? '') === 'active') ? 'selected' : '' ?>>Active users only (limit 2000)</option>
                <option value="custom" <?= (($_POST['target'] ?? '') === 'custom') ? 'selected' : '' ?>>Custom selected users</option>
            </select>
        </div>

        <div id="customUsersContainer" class="mb-3" style="display: <?= (($_POST['target'] ?? '') === 'custom') ? 'block' : 'none' ?>;">
            <label class="form-label">Choose specific users</label>
            <div style="margin-bottom:10px;" class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectCustomUsers(true)">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectCustomUsers(false)">Clear All</button>
            </div>
            <div style="max-height: 340px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px; background: #fafafa;">
                <?php foreach ($allUsers as $user): ?>
                    <div class="form-check" style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <input class="form-check-input" type="checkbox" name="selected_user_ids[]" value="<?= htmlspecialchars($user['id']) ?>" id="user_<?= htmlspecialchars($user['id']) ?>" <?= (in_array($user['id'], $_POST['selected_user_ids'] ?? [], true)) ? 'checked' : '' ?> >
                        <label class="form-check-label" for="user_<?= htmlspecialchars($user['id']) ?>">
                            <strong><?= htmlspecialchars($user['name'] ?: 'Unnamed') ?></strong> &lt;<?= htmlspecialchars($user['email']) ?>&gt; <?php if($user['role']) echo '(' . htmlspecialchars($user['role']) . ')'; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label">Email Subject</label>
            <input type="text" id="subject" name="subject" class="form-control" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="body" class="form-label">Email Body (HTML supported)</label>
            <textarea id="body" name="body" rows="8" class="form-control" required><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label for="button1_label" class="form-label">Primary button text</label>
            <input type="text" id="button1_label" name="button1_label" class="form-control" value="<?= htmlspecialchars($_POST['button1_label'] ?? '') ?>" placeholder="e.g. View Offer">
        </div>
        <div class="mb-3">
            <label for="button1_url" class="form-label">Primary button URL</label>
            <input type="url" id="button1_url" name="button1_url" class="form-control" value="<?= htmlspecialchars($_POST['button1_url'] ?? '') ?>" placeholder="https://example.com">
        </div>

        <div class="mb-3">
            <label for="button2_label" class="form-label">Secondary button text (optional)</label>
            <input type="text" id="button2_label" name="button2_label" class="form-control" value="<?= htmlspecialchars($_POST['button2_label'] ?? '') ?>" placeholder="e.g. Learn More">
        </div>
        <div class="mb-3">
            <label for="button2_url" class="form-label">Secondary button URL (optional)</label>
            <input type="url" id="button2_url" name="button2_url" class="form-control" value="<?= htmlspecialchars($_POST['button2_url'] ?? '') ?>" placeholder="https://example.com">
        </div>

        <button type="submit" class="btn btn-primary">Send Promotional Email</button>
    </form>

    <div class="overview-section mt-5">
        <h2>Recent Campaigns</h2>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Sent</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $campaigns = [];
                $stmt = $conn->prepare("SELECT id, subject, sent_count, created_at FROM promotional_email_campaigns ORDER BY created_at DESC LIMIT 20");
                if ($stmt) {
                    $stmt->execute();
                    $campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                foreach ($campaigns as $camp):
                ?>
                    <tr>
                        <td><?= htmlspecialchars($camp['id']) ?></td>
                        <td><?= htmlspecialchars($camp['subject']) ?></td>
                        <td><?= htmlspecialchars($camp['sent_count']) ?></td>
                        <td><?= htmlspecialchars($camp['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleCustomUsers(value) {
    const container = document.getElementById('customUsersContainer');
    if (!container) return;
    container.style.display = (value === 'custom') ? 'block' : 'none';
}

function selectCustomUsers(selectAll) {
    document.querySelectorAll('#customUsersContainer input[name="selected_user_ids[]"]').forEach(cb => {
        cb.checked = selectAll;
    });
}

// Persist UI state on page load
document.addEventListener('DOMContentLoaded', function() {
    const target = document.getElementById('target');
    if (target) {
        toggleCustomUsers(target.value);
        target.addEventListener('change', function() {
            toggleCustomUsers(this.value);
        });
    }
});
</script>
