<?php
/**
 * admin/subscriptions/index.php - Subscription Plans Management
 */

require_once __DIR__ . '/../../db_connect.php';

// Check admin access (MUST BE BEFORE ANY OUTPUT)
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../header.php';

$plans = [];
$result = $conn->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $planId = intval($row['id']);
        $features = [];
        $featSql = "SELECT feature_text FROM subscription_plan_features WHERE plan_id = ? ORDER BY sort_order ASC";
        $featStmt = $conn->prepare($featSql);
        $featStmt->bind_param("i", $planId);
        $featStmt->execute();
        $featRes = $featStmt->get_result();
        while($fRow = $featRes->fetch_assoc()) {
            $features[] = $fRow['feature_text'];
        }
        $featStmt->close();
        
        $row['features_array'] = $features;
        $plans[] = $row;
    }
}

?>

<link rel="stylesheet" href="<?= $adminUrl ?>subscriptions/SUB_css/subscriptions.css">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2">💳 Subscription Plans</h1>
            <p class="text-muted">Manage Free, Pro, and Premium plan features and limits</p>
        </div>
        <a href="add_plan.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Plan
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0 <?= $plan['name'] === 'premium' ? 'border-primary border-2' : '' ?>">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($plan['display_name']) ?></h5>
                        <span class="badge bg-<?= $plan['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $plan['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="h3 fw-bold"><?= $plan['price'] > 0 ? number_format($plan['price']) . ' ' . $plan['currency'] : 'Free' ?></span>
                            <span class="text-muted">/ <?= $plan['duration_days'] == 365 ? 'year' : 'month' ?></span>
                        </div>
                        
                        <h6 class="fw-bold text-uppercase small text-muted mb-3">Usage Limits</h6>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2">
                                <i class="fas fa-file-alt text-primary me-2"></i>
                                <strong>Paper Daily:</strong> <?= $plan['questionPaperPerDay'] == -1 ? 'Unlimited' : $plan['questionPaperPerDay'] ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-tags text-primary me-2"></i>
                                <strong>MCQ Topics:</strong> <?= $plan['TopicsForOnlineMCQs'] == -1 ? 'Unlimited' : $plan['TopicsForOnlineMCQs'] ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-palette text-primary me-2"></i>
                                <strong>Custom Templates:</strong> <?= $plan['CustomPaperTemplate'] ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>' ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-ad text-primary me-2"></i>
                                <strong>Ads:</strong> <?= $plan['Ads'] ? '<span class="text-warning">Enabled</span>' : '<span class="text-success">No Ads</span>' ?>
                            </li>
                        </ul>

                        <h6 class="fw-bold text-uppercase small text-muted mb-3">Included Features</h6>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <?php foreach ($plan['features_array'] as $feature): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($feature) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-3 border-top-0">
                        <div class="d-grid gap-2">
                            <a href="edit_plan.php?id=<?= $plan['id'] ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Plan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../footer.php'; ?>
