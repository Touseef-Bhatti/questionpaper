<?php
require_once __DIR__ . '/../db_connect.php';

class SubscriptionService 
{
    private $conn;
    
    public function __construct($connection = null) 
    {
        global $conn;
        $this->conn = $connection ?: $conn;
    }
    
    /**
     * Get user's current active subscription
     */
    public function getCurrentSubscription($userId) 
    {
        $userId = intval($userId);
        
        // Auto-cleanup expired subscriptions for this user
        $this->cleanupExpiredSubscriptions($userId);

        $sql = "SELECT us.*, sp.name as plan_name, sp.display_name, sp.features, 
                       sp.questionPaperPerDay, sp.TopicsForOnlineMCQs, sp.CustomPaperTemplate, sp.Ads,
                       sp.price, sp.currency
                FROM user_subscriptions us 
                JOIN subscription_plans sp ON us.plan_id = sp.id 
                WHERE us.user_id = ? AND us.status = 'active' 
                AND (us.expires_at IS NULL OR us.expires_at > NOW())
                ORDER BY us.id DESC LIMIT 1";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $subscription = $result->fetch_assoc();
            
            // Handle missing max_topics_per_quiz column gracefully
            if (!isset($subscription['max_topics_per_quiz'])) {
                $subscription['max_topics_per_quiz'] = ($subscription['plan_name'] === 'free') ? 3 : -1;
            }
            
            // Fetch features from the new table
            $planId = intval($subscription['plan_id']);
            $features = [];
            try {
                $featSql = "SELECT feature_text FROM subscription_plan_features WHERE plan_id = ? ORDER BY sort_order ASC";
                $featStmt = $this->conn->prepare($featSql);
                $featStmt->bind_param("i", $planId);
                $featStmt->execute();
                $featRes = $featStmt->get_result();
                while($fRow = $featRes->fetch_assoc()) {
                    $features[] = $fRow['feature_text'];
                }
                $featStmt->close();
            } catch (Exception $e) {
                // subscription_plan_features table may not exist; use empty features
                error_log('Features table error: ' . $e->getMessage());
                $features = [];
            }
            
            $subscription['features_list'] = $features;
            return $subscription;
        }
        
        // Return free plan if no active subscription
        return $this->getFreeSubscription($userId);
    }
    
    /**
     * Cleanup and reset expired subscriptions for a specific user
     */
    public function cleanupExpiredSubscriptions($userId) 
    {
        $userId = intval($userId);
        
        // Find if there's an active but expired subscription
        $sql = "SELECT us.id, us.plan_id FROM user_subscriptions us 
                WHERE us.user_id = ? AND us.status = 'active' 
                AND us.expires_at IS NOT NULL AND us.expires_at <= NOW()";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            // Found expired subscriptions, mark them as 'expired'
            $updateSql = "UPDATE user_subscriptions SET status = 'expired' 
                         WHERE user_id = ? AND status = 'active' 
                         AND expires_at IS NOT NULL AND expires_at <= NOW()";
            $upStmt = $this->conn->prepare($updateSql);
            $upStmt->bind_param("i", $userId);
            $upStmt->execute();
            
            // Now check if they have any *other* active subscription (e.g. they bought another one before expiry)
            $activeCheck = "SELECT id FROM user_subscriptions 
                           WHERE user_id = ? AND status = 'active' 
                           AND (expires_at IS NULL OR expires_at > NOW())";
            $acStmt = $this->conn->prepare($activeCheck);
            $acStmt->bind_param("i", $userId);
            $acStmt->execute();
            if ($acStmt->get_result()->num_rows === 0) {
                // No other active subscription, reset user status to 'free'
                $this->updateUserSubscriptionStatus($userId, 'free', null);
            }
        }
    }

    /**
     * Get free plan details
     */
    public function getFreeSubscription($userId) 
    {
        $sql = "SELECT * FROM subscription_plans WHERE name = 'free' LIMIT 1";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows > 0) {
            $plan = $result->fetch_assoc();
            
            // Fetch features from the new table
            $planId = intval($plan['id']);
            $features = [];
            try {
                $featSql = "SELECT feature_text FROM subscription_plan_features WHERE plan_id = ? ORDER BY sort_order ASC";
                $featStmt = $this->conn->prepare($featSql);
                $featStmt->bind_param("i", $planId);
                $featStmt->execute();
                $featRes = $featStmt->get_result();
                while($fRow = $featRes->fetch_assoc()) {
                    $features[] = $fRow['feature_text'];
                }
                $featStmt->close();
            } catch (Exception $e) {
                // subscription_plan_features table may not exist; use empty features
                error_log('Features table error: ' . $e->getMessage());
                $features = [];
            }
            
            $plan['features'] = $features;
            $plan['user_id'] = $userId;
            $plan['status'] = 'active';
            $plan['papers_used_this_month'] = $this->getUsageCount($userId, 'paper_generated');
            $plan['plan_name'] = $plan['name'];
            return $plan;
        }
        
        return null;
    }
    
    /**
     * Get all available subscription plans
     */
    public function getAvailablePlans() 
    {
        $sql = "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC";
        $result = $this->conn->query($sql);
        $plans = [];
        
        while ($row = $result->fetch_assoc()) {
            $planId = intval($row['id']);
            $features = [];
            try {
                $featSql = "SELECT feature_text FROM subscription_plan_features WHERE plan_id = ? ORDER BY sort_order ASC";
                $featStmt = $this->conn->prepare($featSql);
                $featStmt->bind_param("i", $planId);
                $featStmt->execute();
                $featRes = $featStmt->get_result();
                while($fRow = $featRes->fetch_assoc()) {
                    $features[] = $fRow['feature_text'];
                }
                $featStmt->close();
            } catch (Exception $e) {
                // subscription_plan_features table may not exist; use empty features
                error_log('Features table error: ' . $e->getMessage());
                $features = [];
            }
            
            $row['features'] = $features;
            $plans[] = $row;
        }
        
        return $plans;
    }
    
    /**
     * Check if user can perform action based on their subscription
     */
    public function canPerformAction($userId, $action) 
    {
        $subscription = $this->getCurrentSubscription($userId);
        if (!$subscription) return false;
        
        switch ($action) {
            case 'generate_paper':
                if ($subscription['questionPaperPerDay'] == -1) return true;
                $dailyUsage = $this->getDailyUsageCount($userId, 'paper_generated');
                return $dailyUsage < $subscription['questionPaperPerDay'];
                
            case 'custom_template':
                return (bool)$subscription['CustomPaperTemplate'];
                
            case 'no_ads':
                return !(bool)$subscription['Ads'];
                
            case 'export_docx':
                return in_array($subscription['plan_name'], ['premium', 'pro', 'yearly_premium', 'yearly_pro']);
                
            default:
                return true;
        }
    }
    
    /**
     * Get usage count for today
     */
    public function getDailyUsageCount($userId, $action) 
    {
        $userId = intval($userId);
        $sql = "SELECT COUNT(*) as count FROM usage_tracking 
                WHERE user_id = ? AND action = ? 
                AND DATE(created_at) = CURDATE()";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $userId, $action);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Get usage count for current month
     */
    public function getUsageCount($userId, $action) 
    {
        $userId = intval($userId);
        $sql = "SELECT COUNT(*) as count FROM usage_tracking 
                WHERE user_id = ? AND action = ? 
                AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $userId, $action);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Track user action/usage
     */
    public function trackUsage($userId, $action, $resourceType = null, $resourceId = null, $metadata = null) 
    {
        $subscription = $this->getCurrentSubscription($userId);
        $subscriptionId = $subscription['id'] ?? null;
        
        $sql = "INSERT INTO usage_tracking (user_id, subscription_id, action, resource_type, resource_id, metadata) 
                VALUES (?, ?, ?, ?, ?, ?)";
                
        $metadataJson = $metadata ? json_encode($metadata) : null;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iissis", $userId, $subscriptionId, $action, $resourceType, $resourceId, $metadataJson);
        $stmt->execute();
        
        // Update papers_used_this_month if tracking paper generation
        if ($action === 'paper_generated' && $subscriptionId) {
            $this->updateMonthlyUsage($subscriptionId, $userId);
        }
    }
    
    /**
     * Update monthly usage count
     */
    private function updateMonthlyUsage($subscriptionId, $userId) 
    {
        $currentMonth = date('Y-m-01');
        $sql = "UPDATE user_subscriptions 
                SET papers_used_this_month = (
                    SELECT COUNT(*) FROM usage_tracking 
                    WHERE user_id = ? AND action = 'paper_generated' 
                    AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                ),
                last_usage_reset = ?
                WHERE id = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $userId, $currentMonth, $subscriptionId);
        $stmt->execute();
    }
    
    /**
     * Create new subscription
     */
    public function createSubscription($userId, $planId, $paymentId = null) 
    {
        $userId = intval($userId);
        $planId = intval($planId);
        
        // Get plan details
        $plan = $this->getPlanById($planId);
        if (!$plan) return false;
        
        // Calculate expiry date
        $expiresAt = null;
        if ($plan['duration_days'] > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        }
        
        // Deactivate existing subscriptions
        $this->deactivateUserSubscriptions($userId);
        
        // Create new subscription
        $sql = "INSERT INTO user_subscriptions (user_id, plan_id, status, expires_at, auto_renew) 
                VALUES (?, ?, 'active', ?, 1)";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iis", $userId, $planId, $expiresAt);
        
        if ($stmt->execute()) {
            $subscriptionId = $this->conn->insert_id;
            
            // Update user's subscription status
            $this->updateUserSubscriptionStatus($userId, $plan['name'], $expiresAt);
            
            // Track subscription creation
            $this->trackUsage($userId, 'subscription_created', 'subscription', $subscriptionId, [
                'plan_name' => $plan['name'],
                'payment_id' => $paymentId
            ]);
            
            return $subscriptionId;
        }
        
        return false;
    }
    
    /**
     * Get plan by ID
     */
    public function getPlanById($planId) 
    {
        $planId = intval($planId);
        $sql = "SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $planId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $plan = $result->fetch_assoc();
            $plan['features'] = json_decode($plan['features'], true);
            return $plan;
        }
        
        return null;
    }
    
    /**
     * Deactivate user's existing subscriptions
     */
    private function deactivateUserSubscriptions($userId) 
    {
        $sql = "UPDATE user_subscriptions SET status = 'inactive' WHERE user_id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    /**
     * Update user subscription status
     */
    private function updateUserSubscriptionStatus($userId, $planName, $expiresAt) 
    {
        // Map plan names to user subscription status
        $statusMap = [
            'free' => 'free',
            'premium' => 'premium',
            'pro' => 'pro',
            'yearly_premium' => 'premium',
            'yearly_pro' => 'pro'
        ];
        
        $status = $statusMap[$planName] ?? 'free';
        
        $sql = "UPDATE users SET subscription_status = ?, subscription_expires_at = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $expiresAt, $userId);
        $stmt->execute();
    }
    
    /**
     * Get user's subscription limits and usage
     */
    public function getUserLimits($userId) 
    {
        $subscription = $this->getCurrentSubscription($userId);
        if (!$subscription) return null;
        
        return [
            'plan_name' => $subscription['plan_name'],
            'display_name' => $subscription['display_name'],
            'questionPaperPerDay' => $subscription['questionPaperPerDay'],
            'TopicsForOnlineMCQs' => $subscription['TopicsForOnlineMCQs'],
            'CustomPaperTemplate' => $subscription['CustomPaperTemplate'],
            'Ads' => $subscription['Ads'],
            'papers_used_today' => $this->getDailyUsageCount($userId, 'paper_generated'),
            'papers_remaining_today' => $subscription['questionPaperPerDay'] == -1 ? -1 : 
                                max(0, $subscription['questionPaperPerDay'] - $this->getDailyUsageCount($userId, 'paper_generated')),
            'expires_at' => $subscription['expires_at'] ?? null,
            'features' => $subscription['features']
        ];
    }
    
    /**
     * Check if subscription is expired
     */
    public function isSubscriptionExpired($userId) 
    {
        $subscription = $this->getCurrentSubscription($userId);
        if (!$subscription || !isset($subscription['expires_at']) || !$subscription['expires_at']) return false;
        
        return strtotime($subscription['expires_at']) < time();
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription($userId) 
    {
        $sql = "UPDATE user_subscriptions SET status = 'cancelled', auto_renew = 0 
                WHERE user_id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $this->trackUsage($userId, 'subscription_cancelled');
            return true;
        }
        
        return false;
    }
}
?>
