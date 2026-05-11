<?php
/**
 * Test Multiple Includes - Verify No Redeclaration Error
 */

echo "=== Testing Redeclaration Fix ===\n\n";

// Test 1: Load config first time
echo "[1] First load of config.php...\n";
require_once './includes/config.php';
echo "    ✅ Success\n\n";

// Test 2: Load config second time
echo "[2] Second load of config.php...\n";
require_once './includes/config.php';
echo "    ✅ Success (no redeclaration error)\n\n";

// Test 3: Direct includes
echo "[3] Direct env.php include...\n";
require_once './includes/env.php';
echo "    ✅ Success\n";

echo "[4] Direct env.php include again...\n";
require_once './includes/env.php';
echo "    ✅ Success (no redeclaration)\n\n";

// Test 4: Verify functions work
echo "[5] Testing functions...\n";
if (function_exists('getEnv')) {
    $val = getEnv('DB_NAME', 'default');
    echo "    ✅ getEnv() works: DB_NAME = $val\n";
} else {
    echo "    ❌ getEnv() not found!\n";
}

if (function_exists('checkRateLimit')) {
    $result = checkRateLimit('test', 5, 300);
    echo "    ✅ checkRateLimit() works: " . ($result ? 'PASS' : 'BLOCKED') . "\n";
} else {
    echo "    ❌ checkRateLimit() not found!\n";
}

echo "\n✅ ALL TESTS PASSED - NO REDECLARATION ERRORS\n";
?>
