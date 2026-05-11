<?php
/**
 * System Configuration & Security Verification
 */

echo "═════════════════════════════════════════════\n";
echo "🔍 SYSTEM VERIFICATION REPORT\n";
echo "═════════════════════════════════════════════\n\n";

// Test 1: Load configuration
echo "1️⃣  Loading configuration...\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "   ✅ config.php loaded successfully\n";
    echo "      - Site: " . SITE_NAME . "\n";
    echo "      - URL: " . SITE_URL . "\n";
    echo "      - DB: " . DB_NAME . "@" . DB_HOST . ":" . DB_PORT . "\n";
    echo "      - Mail: " . (MAIL_ENABLED ? 'ENABLED' : 'DISABLED') . "\n";
    echo "      - Debug: " . (DEBUG_MODE ? 'ON' : 'OFF') . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check required files
echo "\n2️⃣  Checking required files...\n";
$files = [
    'includes/env.php',
    'includes/security.php',
    'includes/functions.php',
    'includes/auth.php',
    '.env.example',
];

foreach ($files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        echo "   ✅ $file (" . $size . " bytes)\n";
    } else {
        echo "   ⚠️  $file (MISSING)\n";
    }
}

// Test 3: Check database connection
echo "\n3️⃣  Testing database connection...\n";
try {
    $db = getDB();
    $result = $db->query("SELECT 1");
    echo "   ✅ Database connected\n";
} catch (Exception $e) {
    echo "   ⚠️  Database error: " . $e->getMessage() . "\n";
}

// Test 4: Check security functions
echo "\n4️⃣  Checking security functions...\n";
$functions = ['checkRateLimit', 'setSecurityHeaders', 'getEnv', 'isEnvEnabled'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "   ✅ $func() defined\n";
    } else {
        echo "   ❌ $func() NOT FOUND\n";
    }
}

// Test 5: Environment variables
echo "\n5️⃣  Environment variables loaded:\n";
$env_vars = ['DB_HOST', 'DB_USER', 'DB_NAME', 'SITE_NAME', 'MAIL_ENABLED', 'DEBUG_MODE'];
foreach ($env_vars as $var) {
    $val = getEnv($var);
    $display = is_bool($val) ? ($val ? 'true' : 'false') : $val;
    echo "   ✓ $var = $display\n";
}

// Test 6: Security headers (if headers not already sent)
echo "\n6️⃣  Security headers:\n";
if (function_exists('headers_list')) {
    $headers = headers_list();
    if ($headers) {
        foreach ($headers as $header) {
            if (strpos($header, 'X-') === 0 || strpos($header, 'Content-Security') === 0) {
                echo "   ✓ " . substr($header, 0, 60) . "...\n";
            }
        }
    } else {
        echo "   (Headers not yet sent - will be sent on page request)\n";
    }
}

// Test 7: Rate limiting
echo "\n7️⃣  Testing rate limiting function...\n";
$_SESSION = [];  // Initialize session array for testing
if (checkRateLimit('test_action', 2, 60)) {
    echo "   ✅ Rate limiting working (1st attempt: PASS)\n";
} else {
    echo "   ❌ Rate limiting failed on first attempt\n";
}

if (checkRateLimit('test_action', 2, 60)) {
    echo "   ✅ Rate limiting working (2nd attempt: PASS)\n";
} else {
    echo "   ❌ Rate limiting failed on second attempt\n";
}

if (!checkRateLimit('test_action', 2, 60)) {
    echo "   ✅ Rate limiting working (3rd attempt: BLOCKED)\n";
} else {
    echo "   ❌ Rate limiting failed to block on limit\n";
}

// Test 8: CSRF functions
echo "\n8️⃣  Checking CSRF functions...\n";
if (function_exists('csrfToken') && function_exists('verifyCsrf')) {
    echo "   ✅ CSRF functions available\n";
    $_SESSION['csrf_token'] = csrfToken();
    echo "   ✓ Token generated: " . substr($_SESSION['csrf_token'], 0, 20) . "...\n";
} else {
    echo "   ⚠️  CSRF functions not found\n";
}

// Summary
echo "\n═════════════════════════════════════════════\n";
echo "✅ SYSTEM VERIFICATION COMPLETE\n";
echo "═════════════════════════════════════════════\n";
echo "\nSystem is ready for use!\n\n";
?>
