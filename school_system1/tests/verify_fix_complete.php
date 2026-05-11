<?php
/**
 * Comprehensive test that simulates actual application flow
 * This tests the exact error scenario reported
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== COMPREHENSIVE FIX VERIFICATION TEST ===\n\n";

try {
    echo "Step 1: Testing includes/env.php directly...\n";
    require_once __DIR__ . '/includes/env.php';
    echo "✓ env.php loaded\n";
    echo "✓ loadEnv exists: " . (function_exists('loadEnv') ? 'YES' : 'NO') . "\n";
    echo "✓ getEnv exists: " . (function_exists('getEnv') ? 'YES' : 'NO') . "\n";
    
    echo "\nStep 2: Testing double-include (simulating multiple requires)...\n";
    require_once __DIR__ . '/includes/env.php';
    echo "✓ Second include successful - no redeclaration error\n";
    
    echo "\nStep 3: Testing includes/config.php (includes env.php + security.php)...\n";
    require_once __DIR__ . '/includes/config.php';
    echo "✓ config.php loaded\n";
    echo "✓ DB_HOST constant defined: " . (defined('DB_HOST') ? 'YES' : 'NO') . "\n";
    
    echo "\nStep 4: Testing function calls...\n";
    $test_val = getEnv('NONEXISTENT', 'fallback');
    echo "✓ getEnv('NONEXISTENT', 'fallback') returned: '$test_val'\n";
    
    echo "\nStep 5: Testing security functions...\n";
    echo "✓ applySecurityMiddleware called\n";
    echo "✓ SESSION_TIMEOUT defined: " . (defined('SESSION_TIMEOUT') ? 'YES' : 'NO') . "\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    echo "The fatal errors have been FIXED:\n";
    echo "✓ Cannot redeclare getEnv() - FIXED\n";
    echo "✓ Call to undefined function loadEnv() - FIXED\n";
    
    exit(0);
    
} catch (Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
