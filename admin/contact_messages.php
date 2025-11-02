<?php
require_once '../includes/admin_auth.php';
require_once '../db_connect.php';

// Handle message status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $messageId = intval($_POST['message_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($messageId > 0) {
        if ($action === 'mark_read') {
            $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'mark_replied') {
            $stmt = $conn->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->bind_param('i', $messageId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Redirect to prevent form resubmission
        header('Location: contact_messages.php');
        exit;
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = '';
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $whereClause = " WHERE status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM contact_messages" . $whereClause;
$stmt = $conn->prepare($countQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalCount / $limit);

// Get messages
$query = "SELECT * FROM contact_messages" . $whereClause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts
$statusCounts = [
    'all' => 0,
    'unread' => 0,
    'read' => 0,
    'replied' => 0
];

$result = $conn->query("SELECT status, COUNT(*) as count FROM contact_messages GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
    $statusCounts['all'] += $row['count'];
}

include 'header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>💌 Contact Messages</h1>
        <p>Manage and respond to user messages</p>
    </div>

    <!-- Status Filter Tabs -->
    <div class="filter-tabs">
        <a href="?status=all" class="tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
            All (<?= $statusCounts['all'] ?>)
        </a>
        <a href="?status=unread" class="tab <?= $statusFilter === 'unread' ? 'active' : '' ?>">
            Unread (<?= $statusCounts['unread'] ?>)
        </a>
        <a href="?status=read" class="tab <?= $statusFilter === 'read' ? 'active' : '' ?>">
            Read (<?= $statusCounts['read'] ?>)
        </a>
        <a href="?status=replied" class="tab <?= $statusFilter === 'replied' ? 'active' : '' ?>">
            Replied (<?= $statusCounts['replied'] ?>)
        </a>
    </div>

    <?php if (empty($messages)): ?>
        <div class="no-data">
            <p>📭 No messages found.</p>
        </div>
    <?php else: ?>
        <!-- Messages List -->
        <div class="messages-container">
            <?php foreach ($messages as $message): ?>
                <div class="message-card <?= $message['status'] ?>">
                    <div class="message-header">
                        <div class="message-info">
                            <h3><?= htmlspecialchars($message['name']) ?></h3>
                            <span class="email"><?= htmlspecialchars($message['email']) ?></span>
                            <span class="status-badge status-<?= $message['status'] ?>"><?= ucfirst($message['status']) ?></span>
                        </div>
                        <div class="message-meta">
                            <span class="date"><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></span>
                            <div class="message-actions">
                                <?php if ($message['status'] === 'unread'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Mark as Read">
                                            👁️ Mark Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($message['status'] !== 'replied'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <input type="hidden" name="action" value="mark_replied">
                                        <button type="submit" class="btn btn-sm btn-success" title="Mark as Replied">
                                            ✅ Mark Replied
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="mailto:<?= htmlspecialchars($message['email']) ?>?subject=Re: Your message to QPaperGen" 
                                   class="btn btn-sm btn-primary" title="Reply via Email">
                                    📧 Reply
                                </a>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Message">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <p><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                    </div>
                    
                    <?php if ($message['ip_address']): ?>
                        <div class="message-footer">
                            <small class="text-muted">
                                IP: <?= htmlspecialchars($message['ip_address']) ?>
                                <?php if ($message['user_agent']): ?>
                                    | <?= htmlspecialchars(substr($message['user_agent'], 0, 100)) ?>...
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?status=<?= $statusFilter ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">← Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?status=<?= $statusFilter ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-decoration: none;
    color: #666;
    background: #f8f9fa;
    transition: all 0.2s;
}

.tab:hover {
    background: #e9ecef;
    text-decoration: none;
}

.tab.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.messages-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.message-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;
}

.message-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.message-card.unread {
    border-left: 4px solid #ffc107;
    background: #fffbf0;
}

.message-card.read {
    border-left: 4px solid #6c757d;
}

.message-card.replied {
    border-left: 4px solid #28a745;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 15px;
}

.message-info h3 {
    margin: 0 0 5px 0;
    font-size: 1.2em;
    color: #333;
}

.email {
    color: #666;
    font-size: 0.9em;
    margin-right: 10px;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    text-transform: uppercase;
}

.status-unread {
    background: #fff3cd;
    color: #856404;
}

.status-read {
    background: #d1ecf1;
    color: #0c5460;
}

.status-replied {
    background: #d4edda;
    color: #155724;
}

.message-meta {
    text-align: right;
    flex-shrink: 0;
}

.date {
    display: block;
    color: #666;
    font-size: 0.9em;
    margin-bottom: 10px;
}

.message-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.message-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.message-content p {
    margin: 0;
    line-height: 1.6;
    color: #333;
}

.message-footer {
    border-top: 1px solid #eee;
    padding-top: 10px;
    margin-top: 10px;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 30px;
}

.page-info {
    color: #666;
    font-weight: 500;
}

@media (max-width: 768px) {
    .message-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .message-meta {
        text-align: left;
        width: 100%;
    }
    
    .message-actions {
        justify-content: flex-start;
        margin-top: 10px;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
    
    .tab {
        text-align: center;
    }
}
</style>

<?php include '../footer.php'; ?>
