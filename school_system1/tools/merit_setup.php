<?php
require_once __DIR__ . '/includes/config.php';
try {
    $db = getDB();
    // Merit logs table
    $db->exec("CREATE TABLE IF NOT EXISTS merit_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT NOT NULL,
        points INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "OK";
} catch(Exception $e) { echo $e->getMessage(); }
?>
