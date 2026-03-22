<?php
require_once __DIR__ . '/../db_connect.php';

// Handle Reset Action
$message = "";
if (isset($_POST['action']) && $_POST['action'] === 'reset_all') {
    $sql = "UPDATE ai_api_keys 
            SET used_today = 0, 
                status = 'active', 
                consecutive_failures = 0, 
                last_reset_at = NOW(),
                temporary_block_until = NULL
            WHERE status IN ('exhausted', 'temporarily_blocked', 'active')";
    
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        $message = "<div class='alert success'>Successfully reset $affected keys to active status!</div>";
    } else {
        $message = "<div class='alert error'>Error: " . $conn->error . "</div>";
    }
}

// Fetch Current Status
$statusQuery = "SELECT status, COUNT(*) as count FROM ai_api_keys GROUP BY status";
$statusResult = $conn->query($statusQuery);
$stats = [];
while ($row = $statusResult->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Key Reset Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #2c3e50; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-box { padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #eee; }
        .stat-count { display: block; font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 14px; color: #666; text-transform: uppercase; }
        .active { border-top: 4px solid #2ecc71; }
        .exhausted { border-top: 4px solid #e74c3c; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="card">
    <h1>AI Key Management</h1>
    
    <?php echo $message; ?>

    <div class="stats">
        <div class="stat-box active">
            <span class="stat-count"><?php echo $stats['active'] ?? 0; ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="stat-box exhausted">
            <span class="stat-count"><?php echo $stats['exhausted'] ?? 0; ?></span>
            <span class="stat-label">Exhausted</span>
        </div>
        <div class="stat-box">
            <span class="stat-count"><?php echo $stats['temporarily_blocked'] ?? 0; ?></span>
            <span class="stat-label">Blocked</span>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="reset_all">
        <button type="submit" class="btn btn-danger" onclick="return confirm('This will reset usage counters for ALL keys. Proceed?')">
            Reset All Keys to Active
        </button>
    </form>
    
    <p style="margin-top: 20px; font-size: 13px; color: #888;">
        Note: This tool resets <code>used_today</code> to 0 and sets status to <code>active</code> for all exhausted or blocked keys.
    </p>
</div>

</body>
</html>
