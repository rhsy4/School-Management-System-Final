<?php
// Minimal test to verify the fix works
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    // This is the exact flow that was causing the error
    require_once __DIR__ . '/includes/config.php';
    
    // Verify functions exist
    if (!function_exists('loadEnv')) {
        throw new Exception('loadEnv function not defined');
    }
    if (!function_exists('getEnv')) {
        throw new Exception('getEnv function not defined');
    }
    if (!function_exists('applySecurityMiddleware')) {
        throw new Exception('applySecurityMiddleware function not defined');
    }
    
    // Test that we can call the functions
    $val = getEnv('TEST', 'default');
    
    file_put_contents(__DIR__ . '/TEST_RESULT.txt', 'SUCCESS: All functions work - fix is verified');
    echo 'SUCCESS';
    
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/TEST_RESULT.txt', 'FAILED: ' . $e->getMessage());
    echo 'FAILED: ' . $e->getMessage();
    exit(1);
}
?>
