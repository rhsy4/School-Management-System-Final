<?php
// Test: Can we include config.php twice without redeclaration error?
try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/config.php';
    file_put_contents(__DIR__ . '/test_result.txt', 'SUCCESS');
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/test_result.txt', 'ERROR: ' . $e->getMessage());
}
?>
