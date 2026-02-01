<?php
/**
 * ============================================================================
 * Admin: API Keys Management Dashboard
 * ============================================================================
 * 
 * Comprehensive UI for managing AI API keys with features:
 * - View all keys and their status
 * - Monitor usage and quotas
 * - Enable/disable keys
 * - View account statistics
 * - System health overview
 * - Configuration verification
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/AIKeyConfigManager.php';
require_once __DIR__ . '/header.php';

// Check admin access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    header("Location: login.php");
    exit;
}

try {
    // Determine which environment file to use
    $envFile = __DIR__ . '/../config/.env.local';
    
    // Check if in production environment
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        $envFile = __DIR__ . '/../config/.env.production';
    } elseif (getenv('APP_ENV') === 'production') {
        $envFile = __DIR__ . '/../config/.env.production';
    }
    
    // Use .env.production if it exists and .env.local doesn't
    $productionFile = __DIR__ . '/../config/.env.production';
    $localFile = __DIR__ . '/../config/.env.local';
    
    if (!file_exists($localFile) && file_exists($productionFile)) {
        $envFile = $productionFile;
    }
    
    $configManager = new AIKeyConfigManager($envFile);
    $systemConfig = $configManager->getSystemConfig();
    $allAccounts = $configManager->getAllAccounts();
    $allKeys = $configManager->getAllKeys();
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1>🔑 AI API Keys Management</h1>
            <p class="text-muted">Manage and monitor your API keys for AI services</p>
            <hr>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- System Health Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3>System Health Overview</h3>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Keys</h5>
                    <p class="card-text display-4" style="color: #007bff;">
                        <?php echo $systemConfig['total_keys_loaded'] ?? 0; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Active Accounts</h5>
                    <p class="card-text display-4" style="color: #28a745;">
                        <?php echo $systemConfig['total_accounts'] ?? 0; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Encryption</h5>
                    <p class="card-text">
                        <?php if ($systemConfig['encryption_configured']): ?>
                            <span class="badge badge-success">✓ Configured</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⚠ Not Configured</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Default Model</h5>
                    <p class="card-text" style="font-size: 0.9rem;">
                        <?php echo htmlspecialchars($systemConfig['default_model']); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3>Accounts Overview</h3>
        </div>
    </div>

    <div class="row mb-4">
        <?php foreach ($allAccounts as $account): ?>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header" style="background-color: #f8f9fa;">
                    <strong><?php echo htmlspecialchars($account['name']); ?></strong>
                    <span class="badge badge-info float-right">Priority <?php echo $account['priority']; ?></span>
                </div>
                <div class="card-body">
                    <p><strong>Provider:</strong> <?php echo htmlspecialchars($account['provider']); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($account['status']); ?></span></p>
                    <p><strong>Keys:</strong> <?php echo $account['key_count']; ?> active</p>
                    
                    <?php
                    $stats = $configManager->getAccountStats($account['id']);
                    if ($stats):
                    ?>
                    <div class="mt-3">
                        <p><strong>Daily Quota:</strong> 
                            <br>
                            <?php echo number_format($stats['used_today']); ?> / 
                            <?php echo number_format($stats['daily_limit']); ?>
                            <br>
                            <small class="text-muted">Remaining: <?php echo number_format($stats['remaining_quota']); ?></small>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Keys Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3>All API Keys</h3>
            <div style="overflow-x: auto;">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Key Name</th>
                            <th>Provider</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Daily Limit</th>
                            <th>Used Today</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allKeys as $key): ?>
                        <tr>
                            <td>
                                <code><?php echo htmlspecialchars($key['name']); ?></code>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($key['provider']); ?>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($key['model']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $key['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($key['status']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($key['daily_limit']); ?></td>
                            <td>
                                <?php echo number_format($key['used_today']); ?>
                                <br>
                                <small class="text-muted">
                                    <?php 
                                    $percent = round(($key['used_today'] / $key['daily_limit']) * 100);
                                    echo $percent . '%';
                                    ?>
                                </small>
                            </td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($key['created_at'])); ?></small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" title="View Details" data-toggle="modal" data-target="#keyModal<?php echo $key['id']; ?>">
                                    📊 View
                                </button>
                            </td>
                        </tr>

                        <!-- Key Details Modal -->
                        <div class="modal fade" id="keyModal<?php echo $key['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Key Details: <?php echo htmlspecialchars($key['name']); ?></h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>Key Name:</strong> <?php echo htmlspecialchars($key['name']); ?></p>
                                        <p><strong>Provider:</strong> <?php echo htmlspecialchars($key['provider']); ?></p>
                                        <p><strong>Model:</strong> <?php echo htmlspecialchars($key['model']); ?></p>
                                        <p><strong>Status:</strong> <?php echo htmlspecialchars($key['status']); ?></p>
                                        <p><strong>Daily Limit:</strong> <?php echo number_format($key['daily_limit']); ?></p>
                                        <p><strong>Used Today:</strong> <?php echo number_format($key['used_today']); ?></p>
                                        <p><strong>Created:</strong> <?php echo htmlspecialchars($key['created_at']); ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- System Settings -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3>System Settings</h3>
            <table class="table">
                <tr>
                    <td><strong>Default Model:</strong></td>
                    <td><?php echo htmlspecialchars($systemConfig['default_model']); ?></td>
                </tr>
                <tr>
                    <td><strong>Fallback Model:</strong></td>
                    <td><?php echo htmlspecialchars($systemConfig['fallback_model']); ?></td>
                </tr>
                <tr>
                    <td><strong>Daily Quota Per Key:</strong></td>
                    <td><?php echo number_format($systemConfig['daily_quota_per_key']); ?></td>
                </tr>
                <tr>
                    <td><strong>Max Retries:</strong></td>
                    <td><?php echo $systemConfig['max_retries']; ?></td>
                </tr>
                <tr>
                    <td><strong>Retry Delay (ms):</strong></td>
                    <td><?php echo $systemConfig['retry_delay_ms']; ?></td>
                </tr>
                <tr>
                    <td><strong>Circuit Breaker Threshold:</strong></td>
                    <td><?php echo $systemConfig['circuit_breaker_threshold']; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Help Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <h5>💡 How to Add New Keys</h5>
                <ol>
                    <li>Edit <code>config/.env.local</code></li>
                    <li>Add your key: <code>KEY_N=your_api_key</code></li>
                    <li>Optionally set model: <code>KEY_N_MODEL=gpt-4-turbo</code></li>
                    <li>Optionally set provider: <code>KEY_N_PROVIDER=openai</code></li>
                    <li>Run <code>install.php</code> to import keys</li>
                    <li>Keys are automatically encrypted and stored in database</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: none;
    }
    
    .card-header {
        border-bottom: 1px solid #e9ecef;
    }
    
    code {
        background-color: #f5f5f5;
        padding: 2px 4px;
        border-radius: 3px;
    }
    
    .badge {
        padding: 4px 8px;
        font-size: 0.85rem;
    }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
