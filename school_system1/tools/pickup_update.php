<?php
require_once __DIR__ . '/includes/config.php';
try {
    $db = getDB();
    // Update pickup_requests table to include more details
    $db->exec("ALTER TABLE pickup_requests ADD COLUMN picker_name VARCHAR(100) DEFAULT 'Parent';");
    $db->exec("ALTER TABLE pickup_requests ADD COLUMN picker_relation VARCHAR(50) DEFAULT 'Legal Guardian';");
    echo "OK";
} catch(Exception $e) { echo "ALREADY UPDATED"; }
?>
