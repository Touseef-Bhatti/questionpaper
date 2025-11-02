<?php
/**
 * Subscription Access Control Middleware
 * Checks user subscription status and enforces limits
 */

require_once __DIR__ . '/../services/SubscriptionService.php';

class SubscriptionCheck 
{
    private static $subscriptionService;
    
    public static function init() 
    {
        if (!self::$subscriptionService) {
            self::$subscriptionService = new SubscriptionService();
        }
    }
    
    /**
     * Check if user can generate papers
     */
    public static function canGeneratePaper($userId) 
    {
        self::init();
        return self::$subscriptionService->canPerformAction($userId, 'generate_paper');
    }
    
    /**
     * Check if user can use unlimited chapters
     */
    public static function canUseUnlimitedChapters($userId) 
    {
        self::init();
        return self::$subscriptionService->canPerformAction($userId, 'unlimited_chapters');
    }
    
    /**
     * Check if user can use unlimited questions
     */
    public static function canUseUnlimitedQuestions($userId) 
    {
        self::init();
        return self::$subscriptionService->canPerformAction($userId, 'unlimited_questions');
    }
    
    /**
     * Check if user can export to DOCX
     */
    public static function canExportToDOCX($userId) 
    {
        self::init();
        return self::$subscriptionService->canPerformAction($userId, 'export_docx');
    }
    
    /**
     * Get user's limits
     */
    public static function getUserLimits($userId) 
    {
        self::init();
        return self::$subscriptionService->getUserLimits($userId);
    }
    
    /**
     * Enforce paper generation limit
     */
    public static function enforcePaperLimit($userId, $redirectUrl = null) 
    {
        if (!self::canGeneratePaper($userId)) {
            $limits = self::getUserLimits($userId);
            $message = "You have reached your monthly limit of {$limits['max_papers_per_month']} papers. Please upgrade your subscription to continue.";
            
            if ($redirectUrl) {
                header("Location: $redirectUrl?error=" . urlencode($message));
                exit;
            } else {
                return ['error' => $message, 'upgrade_required' => true];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Track paper generation
     */
    public static function trackPaperGeneration($userId, $paperId = null) 
    {
        self::init();
        self::$subscriptionService->trackUsage($userId, 'paper_generated', 'paper', $paperId);
    }
    
    /**
     * Get subscription status for display
     */
    public static function getSubscriptionStatus($userId) 
    {
        self::init();
        $subscription = self::$subscriptionService->getCurrentSubscription($userId);
        $limits = self::$subscriptionService->getUserLimits($userId);
        
        if (!$subscription || !$limits) {
            return null;
        }
        
        $status = [
            'plan_name' => $subscription['display_name'],
            'plan_type' => $subscription['plan_name'],
            'is_premium' => !in_array($subscription['plan_name'], ['free']),
            'expires_at' => $limits['expires_at'],
            'papers_used' => $limits['papers_used_this_month'],
            'papers_limit' => $limits['max_papers_per_month'],
            'papers_remaining' => $limits['papers_remaining'],
            'chapters_limit' => $limits['max_chapters_per_paper'],
            'questions_limit' => $limits['max_questions_per_paper'],
            'can_export_docx' => self::canExportToDOCX($userId),
            'features' => $limits['features'],
            'is_expired' => self::$subscriptionService->isSubscriptionExpired($userId)
        ];
        
        // Calculate status text
        if ($status['is_expired']) {
            $status['status_text'] = 'Expired';
            $status['status_class'] = 'expired';
        } elseif ($status['plan_type'] === 'free') {
            $status['status_text'] = 'Free';
            $status['status_class'] = 'free';
        } else {
            $status['status_text'] = 'Active';
            $status['status_class'] = 'active';
        }
        
        return $status;
    }
    
    /**
     * Check and enforce chapter limits
     */
    public static function enforceChapterLimit($userId, $chapterCount) 
    {
        $limits = self::getUserLimits($userId);
        
        if ($limits['max_chapters_per_paper'] !== -1 && $chapterCount > $limits['max_chapters_per_paper']) {
            return [
                'error' => "Your plan allows maximum {$limits['max_chapters_per_paper']} chapters per paper. Please upgrade for unlimited chapters.",
                'limit' => $limits['max_chapters_per_paper'],
                'upgrade_required' => true
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check and enforce question limits
     */
    public static function enforceQuestionLimit($userId, $questionCount) 
    {
        $limits = self::getUserLimits($userId);
        
        if ($limits['max_questions_per_paper'] !== -1 && $questionCount > $limits['max_questions_per_paper']) {
            return [
                'error' => "Your plan allows maximum {$limits['max_questions_per_paper']} questions per paper. Please upgrade for unlimited questions.",
                'limit' => $limits['max_questions_per_paper'],
                'upgrade_required' => true
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate upgrade prompt HTML
     */
    public static function getUpgradePrompt($message = null) 
    {
        $message = $message ?: "Upgrade your subscription to access this feature.";
        
        return "
        <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;'>
            <h3 style='color: #856404; margin: 0 0 10px 0;'>🎯 Upgrade Required</h3>
            <p style='color: #856404; margin: 0 0 15px 0;'>{$message}</p>
            <a href='subscription.php' style='background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;'>
                View Plans
            </a>
        </div>";
    }
    
    /**
     * Get plan comparison data for upselling
     */
    public static function getPlanComparison($userId) 
    {
        self::init();
        $currentSubscription = self::$subscriptionService->getCurrentSubscription($userId);
        $availablePlans = self::$subscriptionService->getAvailablePlans();
        
        $comparison = [];
        foreach ($availablePlans as $plan) {
            $isCurrent = ($currentSubscription['plan_name'] === $plan['name']);
            $comparison[] = [
                'id' => $plan['id'],
                'name' => $plan['display_name'],
                'price' => $plan['price'],
                'is_current' => $isCurrent,
                'features' => $plan['features'],
                'recommended' => !$isCurrent && in_array($plan['name'], ['premium', 'yearly_premium'])
            ];
        }
        
        return $comparison;
    }
}

// Helper function to check subscription in views
function checkSubscription($action, $userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return false;
    
    switch ($action) {
        case 'can_generate':
            return SubscriptionCheck::canGeneratePaper($userId);
        case 'can_export':
            return SubscriptionCheck::canExportToDOCX($userId);
        case 'unlimited_chapters':
            return SubscriptionCheck::canUseUnlimitedChapters($userId);
        case 'unlimited_questions':
            return SubscriptionCheck::canUseUnlimitedQuestions($userId);
        default:
            return false;
    }
}

// Helper function to get subscription status
function getSubscriptionInfo($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return null;
    
    return SubscriptionCheck::getSubscriptionStatus($userId);
}
?>
