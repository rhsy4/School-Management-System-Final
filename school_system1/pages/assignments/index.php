<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Даалгавар';
$user_id = $_SESSION['user_id'];
$isTeacher = isTeacher() || isAdmin() || isManager();
$isStudent = isStudent();
$isParent  = isParent();

// Teacher -> Redirect to manage page
if ($isTeacher) {
    header('Location: /school_system1/pages/assignments/manage.php');
    exit;
}

$targetStudentId = null;
if ($isStudent) {
    $me = dbOne("SELECT student_id, class_id FROM students WHERE user_id=?", [$user_id]);
    $targetStudentId = $me['student_id'] ?? null;
    $targetClassId   = $me['class_id'] ?? null;
} elseif ($isParent) {
    $targetStudentId = (int)($_SESSION['active_child_id'] ?? 0);
    $s = dbOne("SELECT class_id FROM students WHERE student_id=? AND parent_id=?", [$targetStudentId, $user_id]);
    if (!$s) die('Хүүхдийн мэдээлэл олдсонгүй эсвэл хандах эрхгүй байна.');
    $targetClassId   = $s['class_id'] ?? null;
}

// ── Даалгавар илгээх ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['submission'])) {
    verifyCsrf();
    $assignmentId = (int)$_POST['assignment_id'];
    
    if ($assignmentId && $_FILES['submission']['error'] === 0) {
        
        // Даалгавар сурагчийн ангид хамааралтай эсэхийг шалгах
        $assignCheck = dbOne("SELECT a.assignment_id FROM assignments a 
                              JOIN subjects sub ON a.subject_id = sub.subject_id 
                              WHERE a.assignment_id=? AND sub.class_id=?", 
                              [$assignmentId, $targetClassId]);
        if (!$assignCheck) {
            setFlash('error', 'Энэ даалгавар танай ангид хамааралгүй байна.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['submission']['name'], PATHINFO_EXTENSION));
        
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','gif','zip','rar','txt'];
        if (!in_array($ext, $allowed)) {
            setFlash('error', 'Зөвшөөрөгдөөгүй файлын төрөл (.' . h($ext) . '). Зөвшөөрөгдөх: ' . implode(', ', $allowed));
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($_FILES['submission']['tmp_name']);
        
        // Хамгийн багадаа аюултай MIME байх ёсгүй (php, exe, sh гэх мэт)
        if (strpos($realMime, 'php') !== false || strpos($realMime, 'executable') !== false || strpos($realMime, 'x-sh') !== false) {
            setFlash('error', 'Аюултай файл илэрлээ (MIME: ' . h($realMime) . '). Дахин оролдоно уу.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Файлын хэмжээ ≤ 10MB
        if ($_FILES['submission']['size'] > 10 * 1024 * 1024) {
            setFlash('error', 'Файлын хэмжээ 10МБ-аас хэтэрч байна.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $fileName = 'sub_' . $assignmentId . '_' . $targetStudentId . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../uploads/submissions/';
        
        if (move_uploaded_file($_FILES['submission']['tmp_name'], $uploadDir . $fileName)) {
            $fileUrl = '/school_system1/uploads/submissions/' . $fileName;
            
            dbExec("INSERT INTO assignment_submissions (assignment_id, student_id, file_url) 
                    VALUES (?,?,?) 
                    ON DUPLICATE KEY UPDATE file_url=VALUES(file_url), submitted_at=NOW()",
                   [$assignmentId, $targetStudentId, $fileUrl]);
            
            setFlash('success', 'Даалгавар амжилттай илгээгдлээ.');
        } else {
            setFlash('error', 'Файл хуулж чадсангүй.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ── Өгөгдөл татах ──────────────────────────────────────────────
$assignments = [];
if ($targetClassId) {
    $assignments = dbQuery("SELECT a.*, sub.subject_name, t.first_name AS teacher_name, t.last_name AS teacher_lname,
                             s.submitted_at, s.grade, s.file_url 
                            FROM assignments a
                            JOIN subjects sub ON a.subject_id = sub.subject_id
                            JOIN teachers t ON a.created_by = t.user_id
                            LEFT JOIN assignment_submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
                            WHERE sub.class_id = ?
                            ORDER BY a.due_date DESC", [$targetStudentId, $targetClassId]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-tasks"></i> Миний даалгаварууд</h2>
    </div>
    <div class="card-body">
        
        <?php if (empty($assignments)): ?>
            <p style="text-align:center; color:var(--muted); padding:30px;">Танд одоогоор өгөгдсөн даалгавар байхгүй байна.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Хичээл / Гарчиг</th>
                            <th>Дуусах хугацаа</th>
                            <th>Багш</th>
                            <th>Төлөв</th>
                            <th>Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): 
                            $isSubmitted = !empty($a['submitted_at']);
                            $isGraded    = !empty($a['grade']);
                            $isLate      = strtotime($a['due_date']) < time() && !$isSubmitted;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= h($a['subject_name']) ?></div>
                                <div style="font-size:12px; color:var(--muted);"><?= h($a['title']) ?></div>
                                <div style="font-size:11px; margin-top:4px;"><?= nl2br(h($a['description'])) ?></div>
                            </td>
                            <td>
                                <span style="color: <?= $isLate ? 'var(--danger)' : 'inherit' ?>;">
                                    <?= mnDateTime($a['due_date']) ?>
                                </span>
                            </td>
                            <td><?= h($a['teacher_lname'] . ' ' . $a['teacher_name']) ?>
                                <?php if (!empty($a['attachment_url'])): ?>
                                    <div style="margin-top:5px;">
                                        <a href="<?= h($a['attachment_url']) ?>" target="_blank" class="btn btn-sm btn-info" style="font-size:10px; padding:2px 6px;">
                                            <i class="fas fa-paperclip"></i> Файл
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isGraded): ?>
                                    <span class="badge badge-success">Дүн: <?= (int)$a['grade'] ?></span>
                                <?php elseif ($isSubmitted): ?>
                                    <span class="badge" style="background:#3498db; color:#fff;">Илгээсэн</span>
                                <?php elseif ($isLate): ?>
                                    <span class="badge badge-danger">Хугацаа хэтэрсэн</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--bg); color:var(--text); border:1px solid var(--border);">Хүлээгдэж буй</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$isGraded && !$isParent): ?>
                                <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                                    <label class="btn btn-sm" style="background:var(--bg); border:1px solid var(--border); cursor:pointer;">
                                        <i class="fas fa-upload"></i> <?= $isSubmitted ? 'Дахин илгээх' : 'Илгээх' ?>
                                        <input type="file" name="submission" style="display:none;" onchange="this.form.submit()">
                                    </label>
                                </form>
                                <?php elseif($isSubmitted): ?>
                                    <a href="<?= h($a['file_url']) ?>" target="_blank" class="btn btn-sm" title="Файл харах"><i class="fas fa-file-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
