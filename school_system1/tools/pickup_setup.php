<?php
require_once __DIR__ . '/includes/config.php';
try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS pickup_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        parent_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'waiting',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "OK";
} catch(Exception $e) { echo $e->getMessage(); }
