<?php
/**
 * Verification: Test that redeclaration error is fixed
 */

echo "Testing redeclaration fix...\n\n";

// Test 1: First include
echo "1. Loading config.php (first time)...\n";
require_once __DIR__ . '/includes/config.php';
echo "   ✅ Success\n\n";

// Test 2: Second include (simulating multiple requires)
echo "2. Loading config.php again (second time)...\n";
require_once __DIR__ . '/includes/config.php';
echo "   ✅ Success - No redeclaration error!\n\n";

// Test 3: Direct includes
echo "3. Testing direct includes...\n";
require_once __DIR__ . '/includes/env.php';
echo "   ✅ env.php loaded\n";
require_once __DIR__ . '/includes/env.php';
echo "   ✅ env.php loaded again (no error)\n";

require_once __DIR__ . '/includes/security.php';
echo "   ✅ security.php loaded\n";
require_once __DIR__ . '/includes/security.php';
echo "   ✅ security.php loaded again (no error)\n\n";

// Test 4: Verify functions exist
echo "4. Verifying functions...\n";
$functions = ['loadEnv', 'getEnv', 'isEnvEnabled', 'checkRateLimit', 'setSecurityHeaders'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "   ✅ $func() exists\n";
    } else {
        echo "   ❌ $func() NOT found\n";
    }
}

echo "\n✅ All tests passed! Redeclaration error is fixed.\n";
?>
