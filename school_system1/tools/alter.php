<?php
require_once __DIR__ . '/includes/config.php';
try {
    $db = getDB();
    // Ignore error if column exists
    $db->exec("ALTER TABLE students ADD COLUMN merit_points INT DEFAULT 0;");
    // Seed some random data between 0 and 150
    $db->exec("UPDATE students SET merit_points = FLOOR(RAND()*150);");
    echo "SUCCESS";
} catch(Exception $e) { echo "OK"; } // Already exists
