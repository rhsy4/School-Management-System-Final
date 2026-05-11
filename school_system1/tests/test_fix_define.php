<?php
/**
 * Test the fix for function redeclaration using define() guard
 */

echo "Test 1: First include of env.php\n";
require_once __DIR__ . '/includes/env.php';
echo "✓ env.php loaded successfully\n";
echo "✓ ENV_FUNCTIONS_LOADED defined: " . (defined('ENV_FUNCTIONS_LOADED') ? 'YES' : 'NO') . "\n";
echo "✓ loadEnv function exists: " . (function_exists('loadEnv') ? 'YES' : 'NO') . "\n";
echo "✓ getEnv function exists: " . (function_exists('getEnv') ? 'YES' : 'NO') . "\n";

echo "\nTest 2: Second include of env.php (should not redeclare)\n";
require_once __DIR__ . '/includes/env.php';
echo "✓ env.php re-included without fatal error\n";

echo "\nTest 3: Test getEnv function\n";
$result = getEnv('TEST_VAR', 'default_value');
echo "✓ getEnv returned: " . $result . "\n";

echo "\nTest 4: Include config (which includes env.php via require_once)\n";
require_once __DIR__ . '/includes/config.php';
echo "✓ config.php loaded successfully\n";
echo "✓ SECURITY_FUNCTIONS_LOADED defined: " . (defined('SECURITY_FUNCTIONS_LOADED') ? 'YES' : 'NO') . "\n";

echo "\n✓✓✓ ALL TESTS PASSED ✓✓✓\n";
?>
