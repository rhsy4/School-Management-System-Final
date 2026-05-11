<?php
// Simple verification - no output buffering issues
session_start();
require_once 'includes/config.php';
require_once 'includes/config.php';  // Load again
echo "OK";
?>
