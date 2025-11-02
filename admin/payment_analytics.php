<?php
/**
 * Payment Analytics Dashboard
 * Advanced analytics and reporting for payment data
 */

require_once '../includes/admin_auth.php';
require_once '../services/PaymentService.php';

// Require admin access
$user = adminPageHeader('Payment Analytics', 'admin');
adminNavigation();

$paymentService = new PaymentService();

// Get date range from parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Start of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$period = $_GET['period'] ?? '30'; // Default 30 days

// Get analytics data
$analytics = $paymentService->getAdvancedAnalytics($dateFrom, $dateTo);
$trends = $paymentService->getRevenueTrends(12);
$healthStatus = $paymentService->getHealthStatus();
$stats = $paymentService->getPaymentStatistics($period);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Analytics - Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filters input, .filters select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .filters button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .health-status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .health-healthy { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .health-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .health-critical { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filters input, .filters select, .filters button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>💰 Payment Analytics</h1>
            <p>Comprehensive payment system insights and monitoring</p>
            
            <div class="filters">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" required>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" required>
                    <select name="period">
                        <option value="7" <?= $period == 7 ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $period == 30 ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period == 90 ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="365" <?= $period == 365 ? 'selected' : '' ?>>Last year</option>
                    </select>
                    <button type="submit">Update Analytics</button>
                </form>
            </div>
        </div>
        
        <!-- Health Status -->
        <div class="health-status health-<?= $healthStatus['status'] ?>">
            <h3>🏥 System Health: <?= ucfirst($healthStatus['status']) ?></h3>
            <?php if (!empty($healthStatus['issues'])): ?>
                <ul style="margin-top: 10px;">
                    <?php foreach ($healthStatus['issues'] as $issue): ?>
                        <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>All systems operational</p>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-grid">
            <!-- Key Metrics -->
            <div class="card">
                <h3>📊 Key Metrics (<?= $period ?> days)</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= number_format($stats['total_payments'] ?? 0) ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= number_format($stats['successful_payments'] ?? 0) ?></div>
                        <div class="stat-label">Successful</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">PKR <?= number_format($stats['total_revenue'] ?? 0) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">
                            <?= $stats['total_payments'] > 0 ? number_format(($stats['successful_payments'] / $stats['total_payments']) * 100, 1) : 0 ?>%
                        </div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Trends -->
            <div class="card">
                <h3>📈 Revenue Trends (12 months)</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Payments</th>
                                <th>Revenue</th>
                                <th>Conversion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($trends, 0, 6) as $trend): ?>
                            <tr>
                                <td><?= htmlspecialchars($trend['month']) ?></td>
                                <td><?= number_format($trend['successful_payments']) ?></td>
                                <td>PKR <?= number_format($trend['revenue']) ?></td>
                                <td><?= number_format($trend['conversion_rate'] ?? 0, 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Plan Performance -->
            <div class="card">
                <h3>🎯 Plan Performance</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Purchases</th>
                                <th>Success Rate</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['plan_stats'] as $plan): ?>
                            <tr>
                                <td><?= htmlspecialchars($plan['display_name']) ?></td>
                                <td><?= number_format($plan['total_purchases']) ?></td>
                                <td>
                                    <?= $plan['total_purchases'] > 0 ? number_format(($plan['successful_purchases'] / $plan['total_purchases']) * 100, 1) : 0 ?>%
                                </td>
                                <td>PKR <?= number_format($plan['plan_revenue']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Conversion Analytics -->
            <div class="card">
                <h3>🔄 Conversion Analytics</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value trend-up"><?= number_format($analytics['conversion_stats']['conversion_rate'] ?? 0, 1) ?>%</div>
                        <div class="stat-label">Conversion Rate</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value trend-down"><?= number_format($analytics['conversion_stats']['failure_rate'] ?? 0, 1) ?>%</div>
                        <div class="stat-label">Failure Rate</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value trend-neutral"><?= number_format($analytics['conversion_stats']['cancellation_rate'] ?? 0, 1) ?>%</div>
                        <div class="stat-label">Cancellation Rate</div>
                    </div>
                </div>
            </div>
            
            <!-- Daily Performance -->
            <div class="card">
                <h3>📅 Daily Performance</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Payments</th>
                                <th>Success</th>
                                <th>Revenue</th>
                                <th>Avg Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($analytics['daily_stats'], 0, 10) as $daily): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($daily['date'])) ?></td>
                                <td><?= number_format($daily['total_payments']) ?></td>
                                <td><?= number_format($daily['successful_payments']) ?></td>
                                <td>PKR <?= number_format($daily['daily_revenue']) ?></td>
                                <td>PKR <?= number_format($daily['avg_transaction_value'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="verify_payment.php" style="background: #007bff; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">
                        🔍 Manual Payment Verification
                    </a>
                    <a href="payment_refunds.php" style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">
                        💸 Process Refunds
                    </a>
                    <a href="payment_reports.php" style="background: #28a745; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">
                        📑 Generate Reports
                    </a>
                    <button onclick="exportData()" style="background: #17a2b8; color: white; padding: 15px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                        📥 Export Data
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Health Check Details -->
        <?php if (!empty($healthStatus['issues'])): ?>
        <div class="card" style="margin-top: 20px;">
            <h3>⚠️ System Issues</h3>
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <h4>Issues requiring attention:</h4>
                <ul style="margin-top: 10px; padding-left: 20px;">
                    <?php foreach ($healthStatus['issues'] as $issue): ?>
                        <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function exportData() {
            const dateFrom = '<?= $dateFrom ?>';
            const dateTo = '<?= $dateTo ?>';
            window.open(`payment_export.php?date_from=${dateFrom}&date_to=${dateTo}&format=csv`, '_blank');
        }
        
        // Auto-refresh health status every 5 minutes
        setInterval(function() {
            fetch('payment_health.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== '<?= $healthStatus['status'] ?>') {
                    location.reload();
                }
            })
            .catch(error => console.error('Health check failed:', error));
        }, 300000); // 5 minutes
    </script>
</body>
</html>
