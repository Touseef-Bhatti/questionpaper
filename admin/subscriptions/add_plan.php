<?php
/**
 * admin/subscriptions/add_plan.php - Create New Subscription Plan
 */

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../security.php';

// Check admin access (MUST BE BEFORE ANY OUTPUT)
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = strtolower(trim($_POST['name'] ?? ''));
        $display_name = trim($_POST['display_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'PKR');
        $duration_days = intval($_POST['duration_days'] ?? 30);
        $questionPaperPerDay = intval($_POST['questionPaperPerDay'] ?? -1);
        $TopicsForOnlineMCQs = intval($_POST['TopicsForOnlineMCQs'] ?? -1);
        $CustomPaperTemplate = isset($_POST['CustomPaperTemplate']) ? 1 : 0;
        $Ads = isset($_POST['Ads']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        $features = $_POST['features'] ?? [];
        $features = array_filter(array_map('trim', $features), function($val) {
            return $val !== '';
        });

        if ($name !== '' && $display_name !== '') {
            // Check if name already exists
            $check = $conn->prepare("SELECT id FROM subscription_plans WHERE name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'A plan with this identifier already exists.';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO subscription_plans (
                        name, display_name, price, currency, duration_days, 
                        questionPaperPerDay, TopicsForOnlineMCQs, CustomPaperTemplate, 
                        Ads, is_active, sort_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bind_param("ssdsiiiiiii", 
                        $name, $display_name, $price, $currency, $duration_days, 
                        $questionPaperPerDay, $TopicsForOnlineMCQs, $CustomPaperTemplate, $Ads,
                        $is_active, $sort_order);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Error creating plan: ' . $stmt->error);
                    }
                    $planId = $conn->insert_id;
                    $stmt->close();

                    // Insert features
                    if (!empty($features)) {
                        $stmt = $conn->prepare("INSERT INTO subscription_plan_features (plan_id, feature_text, sort_order) VALUES (?, ?, ?)");
                        foreach (array_values($features) as $index => $featureText) {
                            $stmt->bind_param("isi", $planId, $featureText, $index);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    $conn->commit();
                    header("Location: index.php?success=New plan created successfully");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
            $check->close();
        } else {
            $error = 'Identifier and Display name are required.';
        }
    }
}

require_once __DIR__ . '/../header.php';
?>

<link rel="stylesheet" href="<?= $adminUrl ?>subscriptions/SUB_css/subscriptions.css">

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Subscription Plans</a></li>
                    <li class="breadcrumb-item active">Create New Plan</li>
                </ol>
            </nav>

            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-header bg-white py-4 border-bottom">
                    <h4 class="mb-0 fw-bold text-success">Create New Plan</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Plan Identifier (Internal)</label>
                                <input type="text" name="name" class="form-control py-2" placeholder="e.g. enterprise_pro" required pattern="[a-z0-9_]+">
                                <small class="text-muted">Only lowercase letters, numbers, and underscores</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Display Name</label>
                                <input type="text" name="display_name" class="form-control py-2" placeholder="e.g. Enterprise Pro" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Price</label>
                                <input type="number" step="0.01" name="price" class="form-control py-2" value="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Currency</label>
                                <input type="text" name="currency" class="form-control py-2" value="PKR" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Duration (Days)</label>
                                <input type="number" name="duration_days" class="form-control py-2" value="30" required>
                            </div>
                        </div>

                        <hr class="my-4 opacity-50">
                        <h5 class="fw-bold mb-4"><i class="fas fa-chart-line me-2 text-success"></i>Usage Limits <span class="badge bg-light text-muted fw-normal small">-1 for Unlimited</span></h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Question Paper Per Day</label>
                                <input type="number" name="questionPaperPerDay" class="form-control py-2" value="-1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Topics For Online MCQs</label>
                                <input type="number" name="TopicsForOnlineMCQs" class="form-control py-2" value="-1" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="CustomPaperTemplate" id="CustomPaperTemplate">
                                    <label class="form-check-label fw-bold small text-muted" for="CustomPaperTemplate">Custom Paper Template</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="Ads" id="Ads" checked>
                                    <label class="form-check-label fw-bold small text-muted" for="Ads">Show Ads</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 opacity-50">
                        <h5 class="fw-bold mb-4"><i class="fas fa-list-check me-2 text-success"></i>Features List</h5>
                        <div id="features-container">
                            <div class="input-group mb-3 feature-row">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-check text-success"></i></span>
                                <input type="text" name="features[]" class="form-control border-start-0 py-2" placeholder="Describe a plan feature...">
                                <button type="button" class="btn btn-outline-danger remove-feature">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success px-3 mt-2" id="add-feature">
                            <i class="fas fa-plus me-1"></i> Add Feature Item
                        </button>

                        <hr class="my-4 opacity-50">
                        <div class="row align-items-center mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control py-2" value="0" required>
                            </div>
                            <div class="col-md-4 pt-4">
                                <div class="form-check form-switch custom-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                    <label class="form-check-label fw-bold" for="is_active">Plan is Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-5">
                            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold shadow-sm">
                                <i class="fas fa-plus-circle me-2"></i> Create Subscription Plan
                            </button>
                            <a href="index.php" class="btn btn-link text-muted">Cancel and go back</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('add-feature').addEventListener('click', function() {
    const container = document.getElementById('features-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-3 feature-row';
    div.innerHTML = `
        <span class="input-group-text bg-white border-end-0"><i class="fas fa-check text-success"></i></span>
        <input type="text" name="features[]" class="form-control border-start-0 py-2" placeholder="Describe a plan feature...">
        <button type="button" class="btn btn-outline-danger remove-feature">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
});

document.addEventListener('click', function(e) {
    if (e.target && (e.target.classList.contains('remove-feature') || e.target.parentElement.classList.contains('remove-feature'))) {
        const row = e.target.closest('.feature-row');
        if (row) row.remove();
    }
});
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
