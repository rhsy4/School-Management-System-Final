<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!isTeacher() && !isAdmin() && !isManager()) {
    header('Location: /school_system1/dashboard.php');
    exit;
}

$pageTitle = 'Даалгавар удирдах';
$user_id = $_SESSION['user_id'];
$tRow = dbOne("SELECT teacher_id FROM teachers WHERE user_id=?", [$user_id]);
$myTeacherId = $tRow ? $tRow['teacher_id'] : null;

// ── Шинэ даалгавар үүсгэх ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();
    $subjectId   = (int)$_POST['subject_id'];
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $dueDate     = $_POST['due_date'];
    $attachmentUrl = null;

    if ($subjectId && $title && $dueDate) {
        // Хэрэв файл хавсаргасан бол
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($_FILES['attachment']['tmp_name']);
            $allowedMimes = [
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg', 'image/png'
            ];
            
            if (in_array($realMime, $allowedMimes)) {
                $extMap = [
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];
                $ext = $extMap[$realMime] ?? 'bin';
                $fileName = 'assign_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $uploadDir = __DIR__ . '/../../uploads/assignments/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                    $attachmentUrl = '/school_system1/uploads/assignments/' . $fileName;
                }
            }
        }

        dbExec("INSERT INTO assignments (subject_id, title, description, due_date, created_by, attachment_url) VALUES (?,?,?,?,?,?)",
               [$subjectId, $title, $description, $dueDate, $user_id, $attachmentUrl]);
        setFlash('success', 'Даалгавар амжилттай үүсгэгдлээ.');
    } else {
        setFlash('error', 'Мэдээлэл дутуу байна.');
    }
    header('Location: /school_system1/pages/assignments/manage.php');
    exit;
}

// ── Дүн тавих ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grade') {
    verifyCsrf();
    $submissionId = (int)$_POST['submission_id'];
    $grade        = (float)$_POST['grade'];
    
    // Ownership check: Менежер биш бол зөвхөн өөрийн үүсгэсэн даалгаварт дүн тавина
    if (isManager() || isAdmin()) {
        dbUpdate("UPDATE assignment_submissions SET grade=? WHERE submission_id=?", [$grade, $submissionId]);
    } else {
        dbUpdate("UPDATE assignment_submissions SET grade=? 
                  WHERE submission_id=? 
                  AND assignment_id IN (SELECT assignment_id FROM assignments WHERE created_by=?)", 
                  [$grade, $submissionId, $user_id]);
    }
    setFlash('success', 'Дүн амжилттай хадгалагдлаа.');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// ── Өгөгдөл татах ──────────────────────────────────────────────
// Заадаг хичээлүүд (Менежер бол бүгдийг харна)
if (isManager()) {
    $subjects = dbQuery("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON s.class_id=c.class_id ORDER BY c.class_name, s.subject_name");
} else {
    $subjects = dbQuery("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON s.class_id=c.class_id WHERE s.teacher_id=? ORDER BY s.subject_name", [$myTeacherId]);
}

// Үүсгэсэн даалгаврууд
$myAssignments = dbQuery("SELECT a.*, sub.subject_name, c.class_name,
                          (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id=a.assignment_id) AS sub_count,
                          (SELECT COUNT(*) FROM students st WHERE st.class_id=c.class_id) AS total_students
                          FROM assignments a
                          JOIN subjects sub ON a.subject_id=sub.subject_id
                          JOIN classes c ON sub.class_id=c.class_id
                          WHERE a.created_by=?
                          ORDER BY a.created_at DESC", [$user_id]);

// Хэрэв тодорхой даалгаврын илгээлтийг харж байгаа бол
$viewId = (int)($_GET['view'] ?? 0);
$submissions = [];
if ($viewId) {
    $submissions = dbQuery("SELECT s.*, st.first_name, st.last_name, c.class_name
                            FROM students st
                            JOIN classes c ON st.class_id=c.class_id
                            JOIN assignments a ON a.assignment_id = ?
                            JOIN subjects sub ON a.subject_id = sub.subject_id AND sub.class_id = c.class_id
                            LEFT JOIN assignment_submissions s ON s.assignment_id = a.assignment_id AND s.student_id = st.student_id
                            WHERE st.is_active=1
                            ORDER BY st.last_name, st.first_name", [$viewId]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px;">
    
    <!-- ЗҮҮН: Шинэ даалгавар -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-plus-circle"></i> Шинэ даалгавар</h2>
        </div>
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Хичээл / Анги</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Сонгох --</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>"><?= h($s['class_name'] . ' | ' . $s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Гарчиг</label>
                    <input type="text" name="title" class="form-control" placeholder="Жишээ: Бие даалт #1" required>
                </div>
                
                <div class="form-group">
                    <label>Дуусах хугацаа</label>
                    <input type="datetime-local" name="due_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Хавсаргах файл (зааварчилгаа, материал)</label>
                    <input type="file" name="attachment" class="form-control" style="background:var(--bg); border:1px solid var(--border); padding:6px; height:auto;">
                </div>
                
                <div class="form-group">
                    <label>Тайлбар</label>
                    <textarea name="description" class="form-control" style="height:80px;"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Үүсгэх
                </button>
            </form>
        </div>
    </div>

    <!-- БАРУУН: Даалгаврын жагсаалт -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list-alt"></i> Илгээсэн даалгаврууд</h2>
        </div>
        <div class="card-body">
            <?php if(empty($myAssignments)): ?>
                <p style="text-align:center; color:var(--muted); padding:20px;">Танд одоогоор үүсгэсэн даалгавар байхгүй байна.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Анги / Хичээл</th>
                                <th>Хугацаа</th>
                                <th>Илгээсэн</th>
                                <th>Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($myAssignments as $a): ?>
                            <tr <?= $viewId == $a['assignment_id'] ? 'style="background:var(--bg); border:1px solid var(--primary);"' : '' ?>>
                                <td>
                                    <strong><?= h($a['class_name'] . ' | ' . $a['subject_name']) ?></strong>
                                    <div style="font-size:12px;"><?= h($a['title']) ?></div>
                                </td>
                                <td style="font-size:12px;"><?= mnDateTime($a['due_date']) ?></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-success"><?= $a['sub_count'] ?> / <?= $a['total_students'] ?></span>
                                </td>
                                <td>
                                    <a href="?view=<?= $a['assignment_id'] ?>" class="btn btn-sm" title="Илгээсэн файлыг харах">
                                        <i class="fas fa-eye"></i> Харах
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($viewId): ?>
<!-- ДООР: СУРАГЧИЙН ИЛГЭЭЛТҮҮД -->
<div class="card" style="margin-top:20px;" id="submissions-list">
    <div class="card-header">
        <h2><i class="fas fa-users"></i> Сурагчдын ирүүлсэн даалгаварууд</h2>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Сурагч</th>
                        <th>Илгээсэн цаг</th>
                        <th>Файл</th>
                        <th>Дүн</th>
                        <th>Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($submissions as $s): 
                        $isSubmitted = !empty($s['submitted_at']);
                    ?>
                    <tr>
                        <td><?= h($s['last_name'] . ' ' . $s['first_name']) ?></td>
                        <td><?= $isSubmitted ? mnDateTime($s['submitted_at']) : '<span style="color:var(--muted)">Илгээгээгүй</span>' ?></td>
                        <td>
                            <?php if ($isSubmitted): ?>
                                <a href="<?= h($s['file_url']) ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-download"></i> Татаж авах
                                </a>
                            <?php else: ?> - <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isSubmitted): ?>
                            <form action="" method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="grade">
                                <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                                <input type="number" name="grade" value="<?= (float)$s['grade'] ?>" class="form-control" style="width:70px; height:32px;" min="0" max="100">
                                <button type="submit" class="btn btn-sm btn-success" title="Хадгалах">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php else: ?> - <?php endif; ?>
                        </td>
                        <td>-</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
