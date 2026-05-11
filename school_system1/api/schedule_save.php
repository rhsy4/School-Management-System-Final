<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification.php';

header('Content-Type: application/json');

if (!isManager() && !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $classId   = (int)($_POST['class_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $day       = (int)($_POST['day'] ?? 0);
    $start     = $_POST['start_time'] ?? '';
    // Auto-calculate end time (+45 or +60 mins)
    $startTimeStamp = strtotime("1970-01-01 $start");
    if ($startTimeStamp === false) {
        echo json_encode(['success' => false, 'error' => 'Цагийн формат буруу']);
        exit;
    }
    $end = date('H:i', $startTimeStamp + 3600); // Default 1 hour
    $room = $_POST['room'] ?? '';

    if (!$classId || !$subjectId || !$teacherId || !$day || !$start) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    // 1-5-р ангийн багш зөвхөн өөрөө орох логик
    $classInfo = dbOne("SELECT class_name, teacher_id FROM classes WHERE class_id=?", [$classId]);
    if ($classInfo) {
        $gradeNum = (int)$classInfo['class_name'];
        if ($gradeNum >= 1 && $gradeNum <= 5) {
            if ($classInfo['teacher_id'] != $teacherId) {
                echo json_encode(['success' => false, 'error' => "1-5-р ангид зөвхөн тухайн ангийн багш нь хичээл заах боломжтой!"]);
                exit;
            }
        }
    }

    // 🔍 Багш + Анги + Өрөө давхцал шалгах (нэгдсэн функц)
    $conflict = checkScheduleConflict($teacherId, $classId, $room, $day, $start, $end);
    if ($conflict) {
        echo json_encode(['success' => false, 'error' => $conflict['message']]);
        exit;
    }

    $sid = dbExec("INSERT INTO schedule (class_id,subject_id,teacher_id,day_of_week,start_time,end_time,room) VALUES (?,?,?,?,?,?,?)",
        [$classId, $subjectId, $teacherId, $day, $start, $end, $room]);
    
    echo json_encode(['success' => true, 'id' => $sid]);
    exit;
}
?>

