<?php
// Minimal test - no output before potential errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simulate what happens when files are included multiple times
require_once 'includes/config.php';
require_once 'includes/config.php';
require_once 'includes/env.php';
require_once 'includes/security.php';

echo "OK";
?>
