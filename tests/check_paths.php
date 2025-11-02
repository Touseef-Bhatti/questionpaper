<?php
/**
 * File Path Checker
 * 
 * This script checks for any hardcoded file paths that might need to be updated
 * for server deployment. It helps identify potential issues with file paths
 * that could cause problems when moving from local development to production.
 */

// Start output buffering
ob_start();
echo "=== File Path Checker ===\n\n";

// Load environment configuration
require_once __DIR__ . '/config/env.php';

// Get the application root directory
$appRoot = __DIR__;
$appUrl = EnvLoader::get('APP_URL', 'https://paper.bhattichemicalsindustry.com.pk');

echo "Application Root: $appRoot\n";
echo "Application URL: $appUrl\n\n";

// Function to scan directory recursively
function scanDirectory($dir, $baseDir, &$results = []) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        $relativePath = str_replace($baseDir . '/', '', $path);
        
        if (is_dir($path)) {
            scanDirectory($path, $baseDir, $results);
        } else {
            // Only check PHP files
            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                checkFileForPaths($path, $relativePath, $results);
            }
        }
    }
    
    return $results;
}

// Function to check file for hardcoded paths
function checkFileForPaths($filePath, $relativePath, &$results) {
    $content = file_get_contents($filePath);
    
    // Patterns to check
    $patterns = [
        'localhost' => '/localhost/',
        'xampp' => '/xampp/',
        'absolute_windows_path' => '/[C-Z]:\\\\/',
        'hardcoded_url' => '/http(s)?:\/\/[^\/]+\/questionpaper/',
    ];
    
    $fileResults = [];
    
    foreach ($patterns as $type => $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                // Get line number
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                
                // Get context (the line containing the match)
                $lines = explode("\n", $content);
                $context = trim($lines[$line - 1]);
                
                // Skip if in a comment
                if (strpos($context, '//') === 0 || strpos($context, '/*') === 0 || strpos($context, '*') === 0) {
                    continue;
                }
                
                $fileResults[] = [
                    'type' => $type,
                    'match' => $match[0],
                    'line' => $line,
                    'context' => $context
                ];
            }
        }
    }
    
    if (!empty($fileResults)) {
        $results[$relativePath] = $fileResults;
    }
}

// Scan the application directory
echo "Scanning for potential path issues...\n\n";
$results = scanDirectory($appRoot, $appRoot);

// Display results
if (empty($results)) {
    echo "✅ No potential path issues found.\n";
} else {
    echo "⚠️ Potential path issues found in " . count($results) . " files:\n\n";
    
    foreach ($results as $file => $issues) {
        echo "File: $file\n";
        echo str_repeat('-', strlen($file) + 6) . "\n";
        
        foreach ($issues as $issue) {
            echo "  Line {$issue['line']}: {$issue['type']} - \"{$issue['match']}\"\n";
            echo "  Context: " . $issue['context'] . "\n\n";
        }
    }
    
    echo "Please review these issues and update paths as needed for server deployment.\n";
}

// Final recommendations
echo "\n=== Deployment Recommendations ===\n\n";
echo "1. Use relative paths instead of absolute paths when possible\n";
echo "2. Use __DIR__ for file system paths instead of hardcoded paths\n";
echo "3. Use APP_URL from environment for URLs instead of hardcoded URLs\n";
echo "4. Test thoroughly after deployment to ensure all paths work correctly\n\n";

echo "Run the server_deploy.php script to complete the deployment process.\n";

// Output the buffer
$output = ob_get_clean();
echo $output;

// Also save the output to a log file
file_put_contents(__DIR__ . '/path_check_log.txt', $output);
?>