<?php
$output = "TEST STARTED\n";

try {
    $output .= "Step 1: Requiring env.php\n";
    require_once 'includes/env.php';
    $output .= "Step 1: Success\n";
    
    $output .= "Step 2: Requiring config.php\n";
    require_once 'includes/config.php';
    $output .= "Step 2: Success\n";
    
    $output .= "Step 3: Requiring env.php again\n";
    require_once 'includes/env.php';
    $output .= "Step 3: Success\n";
    
    $output .= "\n✅ TEST PASSED - NO REDECLARATION ERROR\n";
    
} catch (Throwable $e) {
    $output .= "\n❌ TEST FAILED\n";
    $output .= "Error: " . $e->getMessage() . "\n";
    $output .= "File: " . $e->getFile() . "\n";
    $output .= "Line: " . $e->getLine() . "\n";
}

file_put_contents('test_output.log', $output);
echo $output;
?>
