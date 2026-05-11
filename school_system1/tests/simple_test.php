<?php
// Direct test - no complexity
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    require_once 'includes/config.php';
    echo "✅ First load OK\n";
    
    require_once 'includes/config.php';
    echo "✅ Second load OK - NO REDECLARATION ERROR\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
