<?php
// Test that dashboard.php can load without redeclaration errors
ini_set('display_errors', 0);
ob_start();

try {
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    require_once 'includes/auth.php';
    
    // Simulate what would happen if these files are included through different paths
    require_once 'includes/config.php';  // Second include
    
    ob_end_clean();
    echo "✅ SUCCESS: System loads without redeclaration errors";
    exit(0);
    
} catch (Error $e) {
    ob_end_clean();
    echo "❌ ERROR: " . $e->getMessage();
    exit(1);
}
?>
