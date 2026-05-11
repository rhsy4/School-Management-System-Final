<?php
// Simulate the actual error scenario: env.php being required directly AND through config.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$test_passed = false;
$error_msg = '';

try {
    // This simulates what happens when different includes happen
    require_once 'includes/env.php';      // Direct include
    require_once 'includes/config.php';   // Includes env.php via require_once
    require_once 'includes/env.php';      // Direct include again
    
    // If we get here without error, the fix worked
    $test_passed = true;
    
} catch (Error $e) {
    $error_msg = $e->getMessage();
    $test_passed = false;
}

// Output result
if ($test_passed) {
    echo "TEST RESULT: PASS - No redeclaration error\n";
    exit(0);
} else {
    echo "TEST RESULT: FAIL - " . htmlspecialchars($error_msg) . "\n";
    exit(1);
}
?>
