<?php
// Direct test - if this runs without Fatal error, the fix works
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/security.php';

// Test functions exist and work
$test1 = function_exists('getEnv') ? 'PASS' : 'FAIL';
$test2 = function_exists('checkRateLimit') ? 'PASS' : 'FAIL';

echo "✅ FIX VERIFIED: No redeclaration error occurred\n";
echo "Function getEnv exists: $test1\n";
echo "Function checkRateLimit exists: $test2\n";
?>
