<?php
require_once __DIR__ . '/db_connect.php';

echo "<h2>Starting Database Migration</h2>";

// 1. Add missing columns to subscription_plans
$checkCol = $conn->query("SHOW COLUMNS FROM subscription_plans LIKE 'max_topics_per_quiz'");
if ($checkCol->num_rows == 0) {
    echo "<p>Adding max_topics_per_quiz column...</p>";
    $conn->query("ALTER TABLE subscription_plans ADD COLUMN max_topics_per_quiz INT DEFAULT 3 AFTER max_questions_per_paper");
    echo "<p style='color: green;'>✅ Column added.</p>";
} else {
    echo "<p style='color: blue;'>ℹ️ max_topics_per_quiz column already exists.</p>";
}

// 2. Create the new features table
$sql = "CREATE TABLE IF NOT EXISTS subscription_plan_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    feature_text VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✅ subscription_plan_features table created or already exists.</p>";
} else {
    die("<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>");
}

// 2. Migrate data
$result = $conn->query("SELECT id, features FROM subscription_plans");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $planId = $row['id'];
        $features = json_decode($row['features'], true);
        
        if (is_array($features)) {
            echo "<p>Migrating " . count($features) . " features for Plan ID: $planId...</p>";
            foreach ($features as $index => $featureText) {
                if (trim($featureText) === '') continue;
                
                $stmt = $conn->prepare("INSERT INTO subscription_plan_features (plan_id, feature_text, sort_order) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $planId, $featureText, $index);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    echo "<p style='color: green;'>✅ Data migration complete.</p>";
} else {
    echo "<p style='color: red;'>❌ Error fetching plans: " . $conn->error . "</p>";
}

echo "<h3>Migration Finished</h3>";
?>