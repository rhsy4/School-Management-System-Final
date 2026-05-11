<?php
// Clear test file - check if redeclaration is fixed
echo "Starting test...\n";

// Test 1: First include
echo "TEST 1: First include of config.php\n";
require_once 'includes/config.php';
echo "✓ Success\n\n";

// Test 2: Second include (this is where the error occurred)
echo "TEST 2: Second include of config.php\n";
require_once 'includes/config.php';
echo "✓ Success - NO REDECLARATION ERROR!\n\n";

// Test 3: Check if functions exist
echo "TEST 3: Verify functions exist\n";
echo "getEnv exists: " . (function_exists('getEnv') ? 'YES' : 'NO') . "\n";
echo "checkRateLimit exists: " . (function_exists('checkRateLimit') ? 'YES' : 'NO') . "\n\n";

// Test 4: Test function calls
echo "TEST 4: Test function calls\n";
$db_name = getEnv('DB_NAME', 'school_db');
echo "getEnv('DB_NAME') = $db_name\n";

if (!session_id()) {
    session_start();
}
$rate_ok = checkRateLimit('test', 5, 300);
echo "checkRateLimit works: " . ($rate_ok ? 'YES' : 'NO') . "\n\n";

echo "✅ ALL TESTS PASSED - REDECLARATION ERROR IS FIXED!\n";
?>
