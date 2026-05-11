<?php
// Direct test of the actual error scenario
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing actual include chain from index.php...\n";
echo "CWD: " . getcwd() . "\n\n";

// Simulate exactly what index.php does
try {
    echo "1. require_once config.php\n";
    require_once __DIR__ . '/includes/config.php';
    echo "   ✓ Success\n";
    
    echo "2. require_once functions.php\n";
    require_once __DIR__ . '/includes/functions.php';
    echo "   ✓ Success\n";
    
    echo "3. require_once auth.php\n";
    require_once __DIR__ . '/includes/auth.php';
    echo "   ✓ Success\n";
    
    echo "\n✓ ALL INCLUDES SUCCESSFUL - APPLICATION WILL LOAD\n";
    
} catch (Error $e) {
    echo "\n❌ FATAL ERROR:\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Message: " . $e->getMessage() . "\n";
    exit(1);
}
?>
