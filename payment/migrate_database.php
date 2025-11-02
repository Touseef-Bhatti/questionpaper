<?php
/**
 * Database Migration Runner
 * Applies enhanced payment schema to database
 * Usage: php migrate_database.php
 */

require_once 'db_connect.php';

echo "🗄️ Starting database migration...\n";

try {
    // Read and execute enhanced schema
    $schemaFile = 'database/enhanced_payment_schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || preg_match('/^--/', $statement)) {
            $skipped++;
            continue;
        }
        
        try {
            $conn->query($statement);
            $executed++;
            echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            $errors++;
            echo "⚠️ Warning: " . $e->getMessage() . "\n";
            echo "   Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n📊 Migration Summary:\n";
    echo "  Executed: $executed statements\n";
    echo "  Skipped: $skipped statements\n";
    echo "  Errors: $errors statements\n";
    
    // Verify critical tables exist
    $criticalTables = [
        'payment_refunds',
        'payment_logs', 
        'rate_limits',
        'payment_alerts',
        'daily_reports'
    ];
    
    echo "\n🔍 Verifying tables:\n";
    foreach ($criticalTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "  ✅ $table exists\n";
        } else {
            echo "  ❌ $table missing\n";
        }
    }
    
    // Verify views exist
    $views = ['payment_summary', 'revenue_trends', 'popular_plans'];
    echo "\n👁️ Verifying views:\n";
    foreach ($views as $view) {
        $result = $conn->query("SHOW TABLES LIKE '$view'");
        if ($result->num_rows > 0) {
            echo "  ✅ $view exists\n";
        } else {
            echo "  ❌ $view missing\n";
        }
    }
    
    echo "\n✅ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
