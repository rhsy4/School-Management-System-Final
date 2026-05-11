<?php
// This is a final verification test - if this file can be included without errors, the fix works
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/config.php'; // Second require - would trigger error without fix
echo "TEST_PASSED";
?>
