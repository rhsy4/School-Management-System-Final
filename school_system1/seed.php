<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

echo "Өгөгдлийн бааз руу туршилтын өгөгдөл хуулж эхэллээ...\n";

function insertUser($username, $password, $roleName, $fullName, $email, $phone) {
    global $pdo;
    $role = dbOne("SELECT role_id FROM user_roles WHERE role_name=?", [$roleName]);
    if (!$role) {
        dbExec("INSERT INTO user_roles (role_name) VALUES (?)", [$roleName]);
        $roleId = $pdo->lastInsertId();
    } else {
        $roleId = $role['role_id'];
    }
    
    $existing = dbOne("SELECT user_id FROM users WHERE username=?", [$username]);
    if ($existing) return $existing['user_id'];

    return dbExec("INSERT INTO users (username, password_hash, role_id, full_name, email, phone) VALUES (?, ?, ?, ?, ?, ?)",
                  [$username, hashPassword($password), $roleId, $fullName, $email, $phone]);
}

$teacher1 = insertUser('t_bold', 'password123', 'teacher', 'Болд Дорж', 'bold@school.mn', '99112233');
$teacher2 = insertUser('t_sar', 'password123', 'teacher', 'Саранчимэг Бат', 'sar@school.mn', '99223344');

dbExec("INSERT IGNORE INTO teachers (user_id, last_name, first_name, register_no) VALUES (?, ?, ?, ?)", [$teacher1, 'Дорж', 'Болд', 'УБ88010112']);
dbExec("INSERT IGNORE INTO teachers (user_id, last_name, first_name, register_no) VALUES (?, ?, ?, ?)", [$teacher2, 'Бат', 'Саранчимэг', 'УБ89010112']);

dbExec("INSERT IGNORE INTO classes (class_name, academic_year, teacher_id) VALUES ('10A', '2025-2026', ?)", [$teacher1]);
dbExec("INSERT IGNORE INTO classes (class_name, academic_year, teacher_id) VALUES ('11B', '2025-2026', ?)", [$teacher2]);

$class10A = dbOne("SELECT class_id FROM classes WHERE class_name='10A'")['class_id'];
$class11B = dbOne("SELECT class_id FROM classes WHERE class_name='11B'")['class_id'];

dbExec("INSERT IGNORE INTO subjects (subject_name, class_id) VALUES ('Математик', ?)", [$class10A]);
dbExec("INSERT IGNORE INTO subjects (subject_name, class_id) VALUES ('Монгол хэл', ?)", [$class10A]);
dbExec("INSERT IGNORE INTO subjects (subject_name, class_id) VALUES ('Англи хэл', ?)", [$class10A]);
dbExec("INSERT IGNORE INTO subjects (subject_name, class_id) VALUES ('Физик', ?)", [$class11B]);
dbExec("INSERT IGNORE INTO subjects (subject_name, class_id) VALUES ('Хими', ?)", [$class11B]);

$studentNames = [
    ['s_tuguldur', 'Бат Төгөлдөр', 'Бат', 'Төгөлдөр', $class10A],
    ['s_khulan', 'Тэмүүжин Хулан', 'Тэмүүжин', 'Хулан', $class10A],
    ['s_bilguun', 'Дорж Билгүүн', 'Дорж', 'Билгүүн', $class10A],
    ['s_anand', 'Сүхбат Ананд', 'Сүхбат', 'Ананд', $class10A],
    ['s_nomi', 'Эрдэнэ Номин', 'Эрдэнэ', 'Номин', $class11B],
    ['s_munkh', 'Ганболд Мөнх-Оргил', 'Ганболд', 'Мөнх-Оргил', $class11B],
    ['s_zaya', 'Болд Заяа', 'Болд', 'Заяа', $class11B],
    ['s_tengis', 'Алтан Тэнгис', 'Алтан', 'Тэнгис', $class11B]
];

foreach ($studentNames as $i => $sData) {
    $uid = insertUser($sData[0], 'password123', 'student', $sData[1], $sData[0].'@student.mn', '88'.rand(100000,999999));
    dbExec("INSERT IGNORE INTO students (user_id, last_name, first_name, class_id, is_active) VALUES (?, ?, ?, ?, 1)", [$uid, $sData[2], $sData[3], $sData[4]]);
}

$students = dbQuery("SELECT student_id, class_id FROM students");
$subjects10A = dbQuery("SELECT subject_id FROM subjects WHERE class_id=?", [$class10A]);
$subjects11B = dbQuery("SELECT subject_id FROM subjects WHERE class_id=?", [$class11B]);

foreach ($students as $stu) {
    $subs = $stu['class_id'] == $class10A ? $subjects10A : $subjects11B;
    foreach ($subs as $sub) {
        dbExec("INSERT IGNORE INTO grades (student_id, subject_id, grade_value, grade_type, recorded_by) VALUES (?, ?, ?, 1, ?)", 
               [$stu['student_id'], $sub['subject_id'], rand(70, 100), $teacher1]);
        
        dbExec("INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by) VALUES (?, ?, ?, ?, ?)",
               [$stu['student_id'], $sub['subject_id'], date('Y-m-d'), rand(1, 10) > 8 ? 2 : 1, $teacher1]);
    }
}

echo "Амжилттай өгөгдлүүд орлоо!\n";
