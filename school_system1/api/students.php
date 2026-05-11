<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!isTeacher() && !isManager() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) { echo json_encode([]); exit; }
$students = dbQuery("SELECT student_id, CONCAT(last_name,' ',first_name) AS full_name FROM students WHERE class_id=? AND is_active=1 ORDER BY last_name,first_name", [$classId]);
echo json_encode($students, JSON_UNESCAPED_UNICODE);

