<?php
/**
 * Daily Reports Generation Cron Job
 * Generates daily payment and revenue reports
 * Run daily at 2 AM: 0 2 * * * php daily_reports.php
 */

// Prevent direct browser access
if (isset($_SERVER['REQUEST_METHOD']) && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied. This script can only be run from command line.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../services/PaymentService.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting daily reports generation...\n";
    
    $paymentService = new PaymentService();
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Get yesterday's analytics
    $analytics = $paymentService->getAdvancedAnalytics($yesterday, $yesterday);
    $dailyStats = $analytics['daily_stats'][0] ?? null;
    
    if ($dailyStats) {
        echo "[" . date('Y-m-d H:i:s') . "] Daily stats for $yesterday:\n";
        echo "  Total payments: " . $dailyStats['total_payments'] . "\n";
        echo "  Successful payments: " . $dailyStats['successful_payments'] . "\n";
        echo "  Daily revenue: PKR " . number_format($dailyStats['daily_revenue']) . "\n";
        echo "  Average transaction: PKR " . number_format($dailyStats['avg_transaction_value'] ?? 0) . "\n";
        
        // Store daily report
        $reportData = [
            'date' => $yesterday,
            'total_payments' => $dailyStats['total_payments'],
            'successful_payments' => $dailyStats['successful_payments'],
            'daily_revenue' => $dailyStats['daily_revenue'],
            'avg_transaction_value' => $dailyStats['avg_transaction_value'] ?? 0,
            'plan_breakdown' => $analytics['plan_stats']
        ];
        
        storeDailyReport($reportData);
        
        // Check for anomalies
        $alerts = checkForAnomalies($dailyStats);
        foreach ($alerts as $alert) {
            createAlert($alert['type'], $alert['title'], $alert['message'], $alert['severity'], $alert['data']);
            echo "[" . date('Y-m-d H:i:s') . "] Alert: " . $alert['title'] . "\n";
        }
        
        // Generate email report for admin
        if (EnvLoader::getBool('SEND_PAYMENT_EMAILS', true)) {
            generateEmailReport($reportData);
        }
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No payment data found for $yesterday\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Daily reports generation completed\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error during report generation: " . $e->getMessage() . "\n";
    error_log("Daily reports generation error: " . $e->getMessage());
    exit(1);
}

function storeDailyReport($reportData) {
    global $conn;
    
    $sql = "INSERT INTO daily_reports (report_date, total_payments, successful_payments, daily_revenue, 
            avg_transaction_value, plan_breakdown, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            total_payments = VALUES(total_payments),
            successful_payments = VALUES(successful_payments),
            daily_revenue = VALUES(daily_revenue),
            avg_transaction_value = VALUES(avg_transaction_value),
            plan_breakdown = VALUES(plan_breakdown)";
    
    $stmt = $conn->prepare($sql);
    $planBreakdownJson = json_encode($reportData['plan_breakdown']);
    
    $stmt->bind_param("siidds", 
        $reportData['date'],
        $reportData['total_payments'],
        $reportData['successful_payments'],
        $reportData['daily_revenue'],
        $reportData['avg_transaction_value'],
        $planBreakdownJson
    );
    
    $stmt->execute();
}

function checkForAnomalies($stats) {
    global $conn;
    
    $alerts = [];
    
    // Get previous 7 days average for comparison
    $sql = "SELECT AVG(daily_revenue) as avg_revenue, AVG(total_payments) as avg_payments,
            AVG(successful_payments) as avg_successful
            FROM payment_summary 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 8 DAY) 
            AND date < DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    
    $result = $conn->query($sql);
    $baseline = $result->fetch_assoc();
    
    if ($baseline['avg_revenue'] > 0) {
        // Check for significant revenue drop (>50% decrease)
        $revenueDrop = (($baseline['avg_revenue'] - $stats['daily_revenue']) / $baseline['avg_revenue']) * 100;
        
        if ($revenueDrop > 50) {
            $alerts[] = [
                'type' => 'revenue_drop',
                'title' => 'Significant Revenue Drop Detected',
                'message' => "Daily revenue dropped by " . number_format($revenueDrop, 1) . "% compared to 7-day average",
                'severity' => 'warning',
                'data' => [
                    'current_revenue' => $stats['daily_revenue'],
                    'avg_revenue' => $baseline['avg_revenue'],
                    'drop_percentage' => $revenueDrop
                ]
            ];
        }
        
        // Check for high failure rate
        $totalPayments = $stats['total_payments'] ?? 0;
        if ($totalPayments > 0) {
            $failureRate = (($totalPayments - $stats['successful_payments']) / $totalPayments) * 100;
            
            if ($failureRate > 25) {
                $alerts[] = [
                    'type' => 'high_failure_rate',
                    'title' => 'High Payment Failure Rate',
                    'message' => "Payment failure rate is " . number_format($failureRate, 1) . "%",
                    'severity' => $failureRate > 50 ? 'critical' : 'warning',
                    'data' => [
                        'total_payments' => $totalPayments,
                        'successful_payments' => $stats['successful_payments'],
                        'failure_rate' => $failureRate
                    ]
                ];
            }
        }
    }
    
    return $alerts;
}

function createAlert($type, $title, $message, $severity, $data = null) {
    global $conn;
    
    // Check if similar alert exists in last 24 hours to avoid spam
    $sql = "SELECT COUNT(*) as count FROM payment_alerts 
            WHERE alert_type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $existingCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($existingCount > 0) {
        return; // Don't create duplicate alert
    }
    
    $sql = "INSERT INTO payment_alerts (alert_type, title, message, severity, data, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $dataJson = $data ? json_encode($data) : null;
    $stmt->bind_param("sssss", $type, $title, $message, $severity, $dataJson);
    $stmt->execute();
}

function generateEmailReport($reportData) {
    // Generate and send daily email report to admin
    $adminEmail = EnvLoader::get('ADMIN_EMAIL', 'admin@questionpaper.com');
    $subject = "QPaperGen Daily Payment Report - " . $reportData['date'];
    
    $message = "
    <html>
    <body>
    <h2>Daily Payment Report - " . $reportData['date'] . "</h2>
    
    <h3>Summary</h3>
    <ul>
        <li><strong>Total Payments:</strong> " . $reportData['total_payments'] . "</li>
        <li><strong>Successful Payments:</strong> " . $reportData['successful_payments'] . "</li>
        <li><strong>Daily Revenue:</strong> PKR " . number_format($reportData['daily_revenue']) . "</li>
        <li><strong>Average Transaction:</strong> PKR " . number_format($reportData['avg_transaction_value']) . "</li>
        <li><strong>Success Rate:</strong> " . ($reportData['total_payments'] > 0 ? number_format(($reportData['successful_payments'] / $reportData['total_payments']) * 100, 1) : 0) . "%</li>
    </ul>
    
    <h3>Plan Breakdown</h3>
    <table border='1' style='border-collapse: collapse; width: 100%;'>
        <tr>
            <th>Plan</th>
            <th>Purchases</th>
            <th>Revenue</th>
        </tr>";
    
    foreach ($reportData['plan_breakdown'] as $plan) {
        $message .= "
        <tr>
            <td>" . htmlspecialchars($plan['display_name']) . "</td>
            <td>" . $plan['successful_purchases'] . "</td>
            <td>PKR " . number_format($plan['plan_revenue']) . "</td>
        </tr>";
    }
    
    $message .= "
    </table>
    
    <p><em>This is an automated report from QPaperGen Payment System</em></p>
    </body>
    </html>";
    
    // Implement email sending here
    // mail($adminEmail, $subject, $message, $headers);
    
    echo "[" . date('Y-m-d H:i:s') . "] Email report prepared for: $adminEmail\n";
}
?>
