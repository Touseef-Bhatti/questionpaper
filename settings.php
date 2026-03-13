<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Ahmad Learning Hub</title>
    <link rel="stylesheet" href="css/profile.css">
    <style>
        .settings-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }
        html.dark-mode .settings-section {
            background: #1e293b;
            border-color: #334155;
        }
        .settings-section h3 {
            margin-top: 0;
            color: #1e293b;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        html.dark-mode .settings-section h3 {
            color: #f8fafc !important;
            border-bottom-color: #334155;
        }
        .quota-info-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        html.dark-mode .quota-info-card {
            background: #0f172a;
            border-color: #334155;
        }
        .quota-icon {
            font-size: 1.5rem;
            color: #4f46e5;
            background: #eef2ff;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        html.dark-mode .quota-icon {
            background: rgba(79, 70, 229, 0.1);
        }
        .quota-details h4 {
            margin: 0;
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .quota-details .quota-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        html.dark-mode .quota-details .quota-value {
            color: #f8fafc;
        }
        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        .setting-item label {
            font-weight: 500;
            color: #334155;
            cursor: pointer;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #4f46e5;
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #4f46e5;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .mode-buttons {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            gap: 4px;
        }
        html.dark-mode .mode-buttons {
            background: #0f172a;
        }
        .btn-mode {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-mode:hover {
            color: #334155;
        }
        html.dark-mode .btn-mode {
            color: #94a3b8;
        }
        html.dark-mode .btn-mode:hover {
            color: #cbd5e1;
        }
        
        .btn-mode.active {
            background: white;
            color: #4f46e5;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        html.dark-mode .btn-mode.active {
            background: #1e293b;
            color: #818cf8;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
  <div class="profile-content">
    <h2>Settings</h2>

    <div class="settings-section">
      <h3>Subscription & Usage</h3>
      <?php
      if (file_exists(__DIR__ . '/middleware/SubscriptionCheck.php')) {
          require_once __DIR__ . '/middleware/SubscriptionCheck.php';
          $subInfo = getSubscriptionInfo($_SESSION['user_id']);
          if ($subInfo): ?>
            <div class="quota-info-card">
              <div class="quota-icon">
                <i class="fas fa-file-invoice"></i>
              </div>
              <div class="quota-details">
                <h4>Daily Paper Quota</h4>
                <div class="quota-value">
                  <?= $subInfo['papers_remaining'] === -1 ? 'Unlimited' : $subInfo['papers_remaining'] . ' Left Today' ?>
                </div>
                <p class="small text-muted mb-0">Used: <?= $subInfo['papers_used_today'] ?> / <?= $subInfo['papers_limit'] == -1 ? '∞' : $subInfo['papers_limit'] ?></p>
              </div>
            </div>
            
            <div class="quota-info-card">
              <div class="quota-icon">
                <i class="fas fa-crown"></i>
              </div>
              <div class="quota-details">
                <h4>Current Plan</h4>
                <div class="quota-value"><?= htmlspecialchars($subInfo['plan_name']) ?></div>
                <p class="small text-muted mb-0"><?= $subInfo['expires_at'] ? 'Expires: ' . date('M d, Y', strtotime($subInfo['expires_at'])) : 'Lifetime Access' ?></p>
              </div>
            </div>
          <?php endif;
      } ?>
      <div class="mt-3">
        <a href="subscription.php" class="btn btn-sm btn-outline-primary">Upgrade My Plan</a>
      </div>
    </div>

    <div class="settings-section">
      <h3>General Setup</h3>
      
      <div class="setting-item">
          <label>Interface Mode</label>
          <div class="mode-buttons">
              <button id="btnSchoolMode" class="btn-mode active"><i class="fas fa-school"></i> School Mode</button>
              <button id="btnAdvanceMode" class="btn-mode"><i class="fas fa-laptop-code"></i> Advance Mode</button>
          </div>
      </div>

      <div class="setting-item">
          <label for="notifications">Email Notifications</label>
          <label class="switch">
              <input type="checkbox" id="notifications">
              <span class="slider"></span>
          </label>
      </div>

      <div class="setting-item">
          <label for="darkMode">Dark Mode Theme</label>
          <label class="switch">
              <input type="checkbox" id="darkMode">
              <span class="slider"></span>
          </label>
      </div>
    </div>

    <div class="actions-row">
      <a href="index.php" class="back-home">← Back to Home</a>
      <a href="profile.php" class="quiz-dashboard-btn" style="background: #4f46e5;">View Profile</a>
    </div>

  </div>
</div>

<?php 
if (file_exists('footer.php')) {
    include 'footer.php'; 
}
?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Initialize toggles from localStorage
    
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.getElementById('darkMode').checked = true;
    }
});

const btnSchool = document.getElementById('btnSchoolMode');
const btnAdvance = document.getElementById('btnAdvanceMode');

if(btnSchool && btnAdvance) {
    btnSchool.addEventListener('click', function() {
        if (typeof selectUserType === 'function') {
            selectUserType('School');
        } else {
            localStorage.setItem('user_type_preference', 'School');
        }
        if(typeof updateModeUI === 'function') updateModeUI();
    });
    
    btnAdvance.addEventListener('click', function() {
        if (typeof selectUserType === 'function') {
            selectUserType('Other');
        } else {
            localStorage.setItem('user_type_preference', 'Other');
        }
        if(typeof updateModeUI === 'function') updateModeUI();
    });
}

document.getElementById('darkMode').addEventListener('change', function(e) {
    if (e.target.checked) {
        localStorage.setItem('darkMode', 'enabled');
        document.documentElement.classList.add('dark-mode');
    } else {
        localStorage.setItem('darkMode', 'disabled');
        document.documentElement.classList.remove('dark-mode');
    }
});

document.getElementById('notifications').addEventListener('change', function(e) {
    if (e.target.checked) {
        localStorage.setItem('notifications', 'enabled');
    } else {
        localStorage.setItem('notifications', 'disabled');
    }
});

// Initialize notifications checkbox
if(localStorage.getItem('notifications') === 'enabled') {
    document.getElementById('notifications').checked = true;
}
</script>

</body>
</html>
