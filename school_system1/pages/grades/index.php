<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Дүн';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEditGrades()) {
        if (($_POST['action'] ?? '') === 'update_ajax') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Дүн оруулах эрхгүй байна.']);
            exit;
        } else {
            http_response_code(403);
            die('Дүн оруулах эрхгүй байна.');
        }
    }
}

// Дүн нэмэх
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();
    $studentId  = (int)$_POST['student_id'];
    $subjectId  = (int)$_POST['subject_id'];
    $gradeType  = trim($_POST['grade_type'] ?? '');
    $gradeValue = (float)$_POST['grade_value'];
    if (!$studentId || !$subjectId || !$gradeType) { setFlash('error','Бүх талбарыг бөглөнө үү!'); }
    elseif ($gradeValue < 0 || $gradeValue > 100) { setFlash('error','Дүн 0-100 байх ёстой!'); }
    else {
        // Багш зөвхөн өөрийн заадаг хичээлд дүн оруулах эрхтэй
        if (isTeacher() && !isManager() && !isAdmin()) {
            $isMySub = dbOne("SELECT subject_id FROM subjects WHERE subject_id=? AND teacher_id=?", [$subjectId, $_SESSION['user_id']]);
            if (!$isMySub) {
                setFlash('error','Та зөвхөн өөрийн заадаг хичээлд дүн оруулах эрхтэй.');
                header('Location: /school_system1/pages/grades/index.php'); 
                exit;
            }
        }
        $gid = dbExec("INSERT INTO grades (student_id,subject_id,grade_type,grade_value,recorded_by) VALUES (?,?,?,?,?)",
            [$studentId, $subjectId, $gradeType, $gradeValue, $_SESSION['user_id']]);
        
        // Smart Alert илгээх
        $stuInfo = dbOne("SELECT s.*, sub.subject_name FROM students s, subjects sub WHERE s.student_id=? AND sub.subject_id=?", [$studentId, $subjectId]);
        if ($stuInfo) {
            $msg = "Шинэ дүн орлоо: " . $stuInfo['subject_name'] . " хичээл дээр " . $gradeValue . " оноо авлаа.";
            sendSmartAlert($stuInfo['user_id'], $msg); // Сурагчид
            if ($stuInfo['parent_id']) {
                sendSmartAlert($stuInfo['parent_id'], "Таны хүүхэд " . $stuInfo['first_name'] . "-ийн " . $stuInfo['subject_name'] . " хичээлийн дүн орлоо: " . $gradeValue); // Эцэг эхэд
            }
        }

        auditLog('grade_added', $gid, "Дүн оруулагдлаа: $gradeValue");
        setFlash('success','Дүн хадгалагдлаа! Сурагч болон эцэг эхэд мэдэгдэл илгээгдлээ.');
    }
    header('Location: /school_system1/pages/grades/index.php'); exit;
}

// Дүн засах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verifyCsrf();
    $gradeId    = (int)$_POST['grade_id'];
    $gradeValue = (float)$_POST['grade_value'];
    $gradeType  = trim($_POST['grade_type'] ?? '');
    
    if ($gradeValue < 0 || $gradeValue > 100) {
        setFlash('error', 'Дүн 0-100 хооронд байх ёстой!');
    } else {
        // Ownership check for teachers
        if (isManager() || isAdmin()) {
            dbUpdate("UPDATE grades SET grade_value=?,grade_type=? WHERE grade_id=?", [$gradeValue, $gradeType, $gradeId]);
        } else {
            dbUpdate("UPDATE grades SET grade_value=?,grade_type=? WHERE grade_id=? AND recorded_by=?", [$gradeValue, $gradeType, $gradeId, $_SESSION['user_id']]);
        }
        
        auditLog('grade_updated', $gradeId);
        setFlash('success','Дүн шинэчлэгдлээ!');
    }
    header('Location: /school_system1/pages/grades/index.php'); exit;
}

// Дүн засах (AJAX / Inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ajax') {
    verifyCsrf();
    $gradeId    = (int)$_POST['grade_id'];
    $gradeValue = (float)$_POST['grade_value'];
    
    $gradeType  = $_POST['grade_type'] ?? null;
    
    // Server-side 0-100 хязгаар шалгах (JS-д bypass хийж болох учир)
    if ($gradeValue < 0 || $gradeValue > 100) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Дүн 0-100 хооронд байх ёстой!']);
        exit;
    }
    
    if ($gradeType !== null) {
        if (isManager() || isAdmin()) {
            dbUpdate("UPDATE grades SET grade_value=?, grade_type=? WHERE grade_id=?", [$gradeValue, trim($gradeType), $gradeId]);
        } else {
            dbUpdate("UPDATE grades SET grade_value=?, grade_type=? WHERE grade_id=? AND recorded_by=?", [$gradeValue, trim($gradeType), $gradeId, $_SESSION['user_id']]);
        }
    } else {
        if (isManager() || isAdmin()) {
            dbUpdate("UPDATE grades SET grade_value=? WHERE grade_id=?", [$gradeValue, $gradeId]);
        } else {
            dbUpdate("UPDATE grades SET grade_value=? WHERE grade_id=? AND recorded_by=?", [$gradeValue, $gradeId, $_SESSION['user_id']]);
        }
    }
    
    auditLog('grade_updated_ajax', $gradeId);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Дүн устгах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $gid = (int)$_POST['grade_id'];
    if (isManager() || isAdmin()) {
        dbUpdate("DELETE FROM grades WHERE grade_id=?", [$gid]);
        auditLog('grade_deleted', $gid);
        setFlash('success','Дүн устгагдлаа!');
    } else {
        setFlash('error','Устгах эрх зөвхөн удирдлагад бий!');
    }
    header('Location: /school_system1/pages/grades/index.php'); exit;
}

// Filter
$classFilter   = (int)($_GET['class_id'] ?? 0);
$subjectFilter = (int)($_GET['subject_id'] ?? 0);

$isStudent = isStudent();
$isParent  = isParent();
$myStudentId = null;
$myChildIds = [];

if ($isStudent) {
    $me = dbOne("SELECT student_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    $myStudentId = $me['student_id'] ?? null;
}
if ($isParent) {
    $myChildIds = isset($_SESSION['active_child_id']) ? [$_SESSION['active_child_id']] : [];
}

// ── Дүнгийн хандлага (График) ──
$chartData = [];
$targetStudentForChart = $isStudent ? $myStudentId : ($_SESSION['active_child_id'] ?? null);
if ($targetStudentForChart) {
    $chartData = dbQuery("SELECT grade_value, created_at, sub.subject_name 
                          FROM grades g
                          JOIN subjects sub ON g.subject_id = sub.subject_id
                          WHERE g.student_id = ? 
                          ORDER BY g.created_at ASC", [$targetStudentForChart]);
}

$params = [];
$sql = "SELECT g.*, CONCAT(s.last_name,' ',s.first_name) AS student_name,
               sub.subject_name, c.class_name
        FROM grades g
        JOIN students s ON g.student_id=s.student_id
        JOIN classes c ON s.class_id=c.class_id
        JOIN subjects sub ON g.subject_id=sub.subject_id
        WHERE 1=1";

$baseCountSql = "SELECT COUNT(*) as cnt FROM grades g JOIN students s ON g.student_id=s.student_id JOIN classes c ON s.class_id=c.class_id JOIN subjects sub ON g.subject_id=sub.subject_id WHERE 1=1";
$countParams = [];

if ($isStudent) {
    if (!$myStudentId) { $sql .= " AND 1=0"; $baseCountSql .= " AND 1=0"; }
    else {
        $sql .= " AND g.student_id=?";
        $baseCountSql .= " AND g.student_id=?";
        $params[] = $myStudentId;
        $countParams[] = $myStudentId;
    }
} elseif ($isParent) {
    if (!$myChildIds) { $sql .= " AND 1=0"; $baseCountSql .= " AND 1=0"; }
    else {
        $inPlaceholders = str_repeat('?,', count($myChildIds) - 1) . '?';
        $sql .= " AND g.student_id IN ($inPlaceholders)";
        $baseCountSql .= " AND g.student_id IN ($inPlaceholders)";
        $params = array_merge($params, $myChildIds);
        $countParams = array_merge($countParams, $myChildIds);
    }
} else {
    if ($classFilter)   { 
        $sql .= " AND s.class_id=?"; $params[] = $classFilter; 
        $baseCountSql .= " AND s.class_id=?"; $countParams[] = $classFilter; 
    }
}

if ($subjectFilter) { 
    $sql .= " AND g.subject_id=?"; $params[] = $subjectFilter; 
    $baseCountSql .= " AND g.subject_id=?"; $countParams[] = $subjectFilter; 
}
$perPage = 50;
$page = max(1,(int)($_GET['page'] ?? 1));
$offset = ($page-1)*$perPage;
$totalCount = dbOne($baseCountSql, $countParams)['cnt'] ?? 0;
$sql .= " ORDER BY g.created_at DESC LIMIT $perPage OFFSET $offset";
$grades   = dbQuery($sql, $params);

// Export Logic
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=grades_export.csv');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for proper Excel viewing
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Сурагч', 'Анги', 'Хичээл', 'Төрөл', 'Оноо', 'Огноо']);
    $gradeTypes = ['homework' => 'Гэрийн даалгавар', 'exam' => 'Шалгалт', 'midterm' => 'Явцын дүн', 'project' => 'Төсөл', 'quiz' => 'Богино шалгалт'];
    foreach($grades as $g) {
        fputcsv($output, [$g['student_name'], $g['class_name'], $g['subject_name'], $gradeTypes[$g['grade_type']] ?? $g['grade_type'], $g['grade_value'], $g['created_at']]);
    }
    fclose($output);
    exit;
}
$classes  = dbQuery("SELECT * FROM classes ORDER BY class_name");
$subjects = dbQuery("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON s.class_id=c.class_id ORDER BY c.class_name, s.subject_name");
$students = dbQuery("SELECT s.student_id, CONCAT(s.last_name,' ',s.first_name) AS full_name FROM students s WHERE s.is_active=1 ORDER BY s.last_name");

$gradeTypes = ['homework' => 'Гэрийн даалгавар', 'exam' => 'Шалгалт', 'midterm' => 'Явцын дүн', 'project' => 'Төсөл', 'quiz' => 'Богино шалгалт'];

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:24px;">
    <?php 
    // Quick Stats
    $statsSql = "SELECT AVG(grade_value) as avg, MAX(grade_value) as high, MIN(grade_value) as low FROM grades WHERE 1=1";
    $statsParams = [];
    if ($classFilter) { $statsSql .= " AND student_id IN (SELECT student_id FROM students WHERE class_id=?)"; $statsParams[] = $classFilter; }
    if ($subjectFilter) { $statsSql .= " AND subject_id=?"; $statsParams[] = $subjectFilter; }
    $stats = dbOne($statsSql, $statsParams);
    ?>
    <div class="card" style="border-left: 5px solid var(--primary); background: rgba(79, 70, 229, 0.05);">
        <div class="card-body">
            <div style="font-size:12px; color:var(--muted); font-weight:600; text-transform:uppercase;">Дундаж дүн</div>
            <div style="font-size:24px; font-weight:800; color:var(--primary);"><?= round($stats['avg'] ?? 0, 1) ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 5px solid #10b981; background: rgba(16, 185, 129, 0.05);">
        <div class="card-body">
            <div style="font-size:12px; color:var(--muted); font-weight:600; text-transform:uppercase;">Хамгийн өндөр</div>
            <div style="font-size:24px; font-weight:800; color:#10b981;"><?= $stats['high'] ?? 0 ?></div>
        </div>
    </div>
    <div class="card" style="border-left: 5px solid #ef4444; background: rgba(239, 68, 68, 0.05);">
        <div class="card-body">
            <div style="font-size:12px; color:var(--muted); font-weight:600; text-transform:uppercase;">Хамгийн бага</div>
            <div style="font-size:24px; font-weight:800; color:#ef4444;"><?= $stats['low'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="card">
  <div class="card-header" style="background:var(--card-bg); border-bottom:1px solid var(--border); padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
      <h2 style="font-size:18px; font-weight:700;"><i class="fas fa-star" style="color:#f59e0b;"></i> Дүнгийн бүртгэл</h2>
      <div style="display:flex; gap:10px;" class="no-print">
        <?php if ($chartData): ?>
          <button class="btn btn-secondary" onclick="toggleChart()"><i class="fas fa-chart-line"></i> График</button>
        <?php endif; ?>
        <button class="btn btn-success" onclick="exportExcel()"><i class="fas fa-file-excel"></i> Excel</button>
        <button class="btn btn-danger" onclick="window.print()"><i class="fas fa-file-pdf"></i> PDF хэвлэх</button>
        <?php if(isTeacher() || isManager()): ?>
          <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Дүн нэмэх</button>
        <?php endif; ?>
      </div>
    </div>
    <script>
    function exportExcel() {
        let qs = new URLSearchParams(window.location.search);
        qs.set('export', 'excel');
        window.location.href = '?' + qs.toString();
    }
    document.head.insertAdjacentHTML('beforeend', '<style>@media print { .no-print, .sidebar, .header, .filter-bar, .modal, .flash { display:none !important; } .main-content { margin-left:0 !important; padding:0 !important; } body { background:white; } table { width:100%; border-collapse:collapse; } th, td { border:1px solid #ddd; padding:8px; } }</style>');
    </script>
  </div>
  <div class="card-body">
    
    <?php if ($chartData): ?>
    <div id="gradeChartContainer" style="display:none; margin-bottom:30px; background:var(--bg); padding:25px; border-radius:16px; border:1px solid var(--border); box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);">
        <h3 style="margin-bottom:20px; font-size:14px; text-align:center; color:var(--primary); font-weight:700;">📈 Сурлагын ахиц дэвшлийн хандлага</h3>
        <div style="height:350px;">
            <canvas id="gradeTrendChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar" style="background:var(--bg); border-radius:12px; padding:15px; margin-bottom:25px;">
      <?php if(!$isStudent && !$isParent): ?>
      <div class="form-group" style="margin:0;">
          <select name="class_id" class="form-control" onchange="this.form.submit()" style="min-width:140px; border-radius:8px;">
            <option value="">-- Бүх анги --</option>
            <?php foreach($classes as $c): ?><option value="<?= $c['class_id'] ?>" <?= $classFilter==$c['class_id']?'selected':'' ?>><?= h($c['class_name']) ?></option><?php endforeach; ?>
          </select>
      </div>
      <?php endif; ?>
      <div class="form-group" style="margin:0; flex:1;">
          <select name="subject_id" class="form-control" onchange="this.form.submit()" style="border-radius:8px;">
            <option value="">-- Бүх хичээл --</option>
            <?php foreach($subjects as $s): ?><option value="<?= $s['subject_id'] ?>" <?= $subjectFilter==$s['subject_id']?'selected':'' ?>><?= h($s['class_name'].' — '.$s['subject_name']) ?></option><?php endforeach; ?>
          </select>
      </div>
      <a href="index.php" class="btn btn-secondary" style="border-radius:8px;">Цэвэрлэх</a>
    </form>

    <div class="table-wrap" style="border:none;">
      <table style="width:100%; border-collapse: separate; border-spacing: 0 8px;">
        <thead>
            <tr style="background:none;">
                <th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase;">Сурагч</th>
                <th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase;">Хичээл</th>
                <th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase;">Төрөл</th>
                <th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase; text-align:center;">Оноо</th>
                <th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase;">Огноо</th>
                <?php if(isTeacher() || isManager()): ?><th style="background:none; border:none; color:var(--muted); font-size:11px; text-transform:uppercase;">Үйлдэл</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($grades as $i => $g): ?>
        <tr style="background:var(--card-bg); box-shadow:0 1px 3px rgba(0,0,0,0.05); border-radius:10px;">
          <td style="padding:15px; border-radius:10px 0 0 10px; border-top:1px solid var(--border); border-bottom:1px solid var(--border); border-left:1px solid var(--border);">
            <div style="font-weight:700; color:var(--primary);"><?= h($g['student_name']) ?></div>
            <div style="font-size:11px; color:var(--muted);"><?= h($g['class_name']) ?></div>
          </td>
          <td style="padding:15px; border-top:1px solid var(--border); border-bottom:1px solid var(--border);"><?= h($g['subject_name']) ?></td>
          <td style="padding:15px; border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
            <span style="font-size:11px; padding:3px 8px; background:var(--bg); border-radius:5px;"><?= h($gradeTypes[$g['grade_type']] ?? $g['grade_type']) ?></span>
          </td>
          <td style="padding:15px; text-align:center; border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
            <div <?= (isManager() || isAdmin() || isTeacher()) ? 'onclick="makeEditable(this, '.$g['grade_id'].', '.json_encode($g['grade_type']).')"' : '' ?> 
                 style="font-size:20px; font-weight:800; color:<?= $g['grade_value']>=80?'#10b981':($g['grade_value']>=60?'#f59e0b':'#ef4444') ?>; <?= (isManager() || isAdmin() || isTeacher()) ? 'cursor:pointer; border-bottom:1px dashed var(--muted); display:inline-block;' : '' ?>" 
                 title="<?= (isManager() || isAdmin() || isTeacher()) ? 'Дүн засах (Дарж засна)' : '' ?>">
              <?= h($g['grade_value']) ?>
            </div>
          </td>
          <td style="padding:15px; font-size:11px; color:var(--muted); border-top:1px solid var(--border); border-bottom:1px solid var(--border);"><?= mnDateTime($g['created_at']) ?></td>
          <?php if(isManager() || isAdmin()): ?>
          <td style="padding:15px; border-radius:0 10px 10px 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border); border-right:1px solid var(--border);">
            <div style="display:flex; gap:5px;">
                <button class="btn btn-sm btn-secondary" onclick='editGrade(<?= json_encode($g, JSON_UNESCAPED_UNICODE) ?>)' style="padding:5px 10px;"><i class="fas fa-edit"></i></button>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="grade_id" value="<?= $g['grade_id'] ?>">
                  <button class="btn btn-sm btn-danger" data-confirm="Устгах уу?" style="padding:5px 10px;"><i class="fas fa-trash"></i></button>
                </form>
            </div>
          </td>
          <?php elseif(isTeacher()): ?>
          <td style="padding:15px; border-radius:0 10px 10px 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border); border-right:1px solid var(--border);">
            <div style="display:flex; gap:5px;">
                <button class="btn btn-sm btn-secondary" onclick='editGrade(<?= json_encode($g, JSON_UNESCAPED_UNICODE) ?>)' style="padding:5px 10px;"><i class="fas fa-edit"></i> Засах</button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if(!$grades): ?><tr><td colspan="<?= (isTeacher()||isManager())?6:5 ?>" style="text-align:center; padding:40px; color:var(--muted);"><i class="fas fa-ghost" style="font-size:30px; opacity:0.2; display:block; margin-bottom:10px;"></i> Жагсаалт хоосон байна</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- НЭМЭХ MODAL -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-plus"></i> Дүн оруулах</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Сурагч *</label>
            <select name="student_id" class="form-control" required>
              <option value="">Сонгох...</option>
              <?php foreach($students as $s): ?><option value="<?= $s['student_id'] ?>"><?= h($s['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Хичээл *</label>
            <select name="subject_id" class="form-control" required>
              <option value="">Сонгох...</option>
              <?php foreach($subjects as $s): ?><option value="<?= $s['subject_id'] ?>"><?= h($s['class_name'].' — '.$s['subject_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Дүнгийн төрөл *</label>
            <select name="grade_type" class="form-control" required>
              <?php foreach($gradeTypes as $k=>$v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Дүн (0-100) *</label>
            <input type="number" name="grade_value" class="form-control" min="0" max="100" step="0.5" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- ЗАСАХ MODAL -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header"><h3>Дүн засах</h3><button class="modal-close" onclick="closeModal('modalEdit')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="grade_id" id="editGradeId">
        <div class="form-row">
          <div class="form-group"><label>Дүнгийн төрөл</label>
            <select name="grade_type" id="editGradeType" class="form-control">
              <?php foreach($gradeTypes as $k=>$v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Дүн (0-100) *</label>
            <input type="number" name="grade_value" id="editGradeValue" class="form-control" min="0" max="100" step="0.5" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function makeEditable(element, gradeId, gradeType) {
    if (element.querySelector('input')) return;
    let oldVal = element.innerText.trim();
    element.innerHTML = `<input type="number" step="0.5" min="0" max="100" value="${oldVal}" style="width:70px; text-align:center; font-size:18px; font-weight:800; border:2px solid var(--primary); border-radius:6px; outline:none;" onblur="saveGrade(this, ${gradeId}, ${oldVal}, ${JSON.stringify(gradeType)})" onkeydown="if(event.key==='Enter') this.blur();">`;
    element.querySelector('input').focus();
    element.querySelector('input').select();
}

function saveGrade(input, gradeId, oldVal, gradeType) {
    let newVal = parseFloat(input.value);
    let parent = input.parentElement;
    if (isNaN(newVal) || newVal < 0 || newVal > 100) newVal = oldVal;
    
    if (newVal === oldVal) {
        parent.innerHTML = newVal;
        return;
    }
    
    let formData = new FormData();
    formData.append('csrf', '<?= csrfToken() ?>');
    formData.append('action', 'update_ajax');
    formData.append('grade_id', gradeId);
    formData.append('grade_value', newVal);
    if (gradeType) formData.append('grade_type', gradeType);
    
    input.disabled = true;
    input.style.opacity = '0.5';
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if (data.success) {
            parent.innerHTML = newVal;
            parent.style.color = newVal >= 80 ? '#10b981' : (newVal >= 60 ? '#f59e0b' : '#ef4444');
        } else {
            alert(data.error || 'Алдаа гарлаа');
            parent.innerHTML = oldVal;
        }
    }).catch(() => {
        parent.innerHTML = oldVal;
    });
}

function editGrade(g) {
    document.getElementById('editGradeId').value    = g.grade_id;
    document.getElementById('editGradeValue').value = g.grade_value;
    document.getElementById('editGradeType').value  = g.grade_type;
    openModal('modalEdit');
}

function toggleChart() {
    const container = document.getElementById('gradeChartContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

<?php if ($chartData): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('gradeTrendChart').getContext('2d');
    const data = <?= json_encode($chartData) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => new Date(d.created_at).toLocaleDateString()),
            datasets: [{
                label: 'Дүнгийн оноо',
                data: data.map(d => d.grade_value),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.3,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { min: 0, max: 100 }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const point = data[context.dataIndex];
                            return point.subject_name + ': ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>
<?php
// Pagination
$totalPages = max(1, ceil($totalCount / $perPage));
if ($totalPages > 1):
    $qp = $_GET; unset($qp['page']);
    $base = '?' . http_build_query($qp);
?>
<div style="display:flex;justify-content:center;gap:6px;padding:20px">
  <?php if($page>1): ?><a href="<?= $base ?>&page=<?= $page-1 ?>" class="btn btn-sm btn-secondary"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
  <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
    <a href="<?= $base ?>&page=<?= $i ?>" class="btn btn-sm <?= $i==$page?'btn-primary':'btn-secondary' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if($page<$totalPages): ?><a href="<?= $base ?>&page=<?= $page+1 ?>" class="btn btn-sm btn-secondary"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
