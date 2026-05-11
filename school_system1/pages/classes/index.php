<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Ангиуд';

$isManager = isManager() || isAdmin();
$isTeacher = isTeacher();
$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    verifyCsrf();
    
    if (!$isManager && !$isTeacher) {
        setFlash('error', 'Эрх хүрэхгүй байна!');
        header('Location: /school_system1/pages/classes/index.php'); exit;
    }

    $action    = $_POST['action'];
    $classId   = (int)($_POST['class_id'] ?? 0);
    $className = trim($_POST['class_name'] ?? '');
    $year      = trim($_POST['academic_year'] ?? '');
    $teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;
    $room      = trim($_POST['room'] ?? '');

    // Allow teacher to only self-assign or must assign themselves if creating?
    if ($isTeacher && !$isManager && $action === 'create') {
        if (!$teacherId || $teacherId != $userId) {
            $teacherId = $userId; // Default to themselves
        }
    }

    if (!$className || !$year) { setFlash('error','Ангийн нэр, хичээлийн жил заавал шаардлагатай!'); }
    elseif ($action === 'create') {
        $cid = dbExec("INSERT INTO classes (class_name,academic_year,teacher_id,room) VALUES (?,?,?,?)", [$className,$year,$teacherId,$room]);
        auditLog('class_created', $cid, "$className анги үүсгэлээ");
        setFlash('success', 'Анги амжилттай үүсгэгдлээ!');
    } else {
        // For update, check if teacher owns it
        if ($isTeacher && !$isManager) {
            $existing = dbOne("SELECT teacher_id FROM classes WHERE class_id=?", [$classId]);
            if (!$existing || $existing['teacher_id'] != $userId) {
                setFlash('error', 'Эрх хүрэхгүй байна!');
                header('Location: /school_system1/pages/classes/index.php'); exit;
            }
        }
        dbUpdate("UPDATE classes SET class_name=?,academic_year=?,teacher_id=?,room=? WHERE class_id=?", [$className,$year,$teacherId,$room,$classId]);
        auditLog('class_updated', $classId);
        setFlash('success', 'Анги амжилттай шинэчлэгдлээ!');
    }
    header('Location: /school_system1/pages/classes/index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $cid = (int)$_POST['class_id'];
    if ($isManager) {
        dbUpdate("DELETE FROM classes WHERE class_id=?", [$cid]);
        setFlash('success','Анги амжилттай устгагдлаа');
    } else {
        setFlash('error', 'Эрх хүрэхгүй байна!');
    }
    header('Location: /school_system1/pages/classes/index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim') {
    verifyCsrf();
    $cid = (int)$_POST['class_id'];
    if ($isTeacher) {
        dbUpdate("UPDATE classes SET teacher_id=? WHERE class_id=? AND teacher_id IS NULL", [$userId, $cid]);
        setFlash('success','Ангийг амжилттай дааж авлаа');
    }
    header('Location: /school_system1/pages/classes/index.php'); exit;
}

$classes  = dbQuery("SELECT c.*, CONCAT(t.last_name,' ',t.first_name) AS teacher_name,
    COUNT(s.student_id) AS student_count
    FROM classes c
    LEFT JOIN teachers t ON c.teacher_id=t.user_id
    LEFT JOIN students s ON c.class_id=s.class_id AND s.is_active=1
    GROUP BY c.class_id ORDER BY c.academic_year DESC, c.class_name");
$teachers = dbQuery("SELECT t.*, CONCAT(t.last_name,' ',t.first_name) AS full_name FROM teachers t JOIN users u ON t.user_id=u.user_id WHERE u.is_active=1 ORDER BY t.last_name");

$editClass = null;
if (isset($_GET['edit'])) $editClass = dbOne("SELECT * FROM classes WHERE class_id=?", [(int)$_GET['edit']]);

$primaryClasses = [];
$middleClasses = [];
$highClasses = [];
$otherClasses = [];

foreach($classes as $c) {
    if (preg_match('/^(\d+)/', trim($c['class_name']), $m)) {
        $grade = (int)$m[1];
        if ($grade >= 1 && $grade <= 5) {
            $primaryClasses[] = $c;
        } elseif ($grade >= 6 && $grade <= 9) {
            $middleClasses[] = $c;
        } elseif ($grade >= 10) {
            $highClasses[] = $c;
        } else {
            $otherClasses[] = $c;
        }
    } else {
        $otherClasses[] = $c;
    }
}

$categories = [
    'Бага анги (I-V анги)' => $primaryClasses,
    'Дунд анги (VI-IX анги)' => $middleClasses,
    'Ахлах анги (X-XII анги)' => $highClasses,
    'Бусад' => $otherClasses
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-chalkboard"></i> Ангиудын удирдлага</h2>
    <?php if(isManager() || isTeacher()): ?>
    <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Анги нэмэх</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php foreach($categories as $catName => $catClasses): ?>
    <?php if(empty($catClasses) && $catName === 'Бусад') continue; ?>
    <h3 style="margin-top:<?= $catName === 'Бага анги (I-V анги)' ? '0' : '30px' ?>; margin-bottom:15px; color:var(--primary); font-size:16px; border-bottom:2px solid var(--border); padding-bottom:10px;">
        <i class="fas fa-layer-group"></i> <?= h($catName) ?>
    </h3>
    <div class="table-wrap" style="margin-bottom:0;">
      <table>
        <thead><tr><th>#</th><th>Ангийн нэр</th><th>Хичээлийн жил</th><th>Ангийн багш</th><th>Өрөө</th><th>Сурагчид</th><th>Үйлдэл</th></tr></thead>
        <tbody>
        <?php foreach($catClasses as $i => $c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong style="font-size:16px"><?= h($c['class_name']) ?></strong></td>
          <td><?= h($c['academic_year']) ?></td>
          <td><?= h($c['teacher_name'] ?? '-') ?></td>
          <td><?= h($c['room'] ?: '-') ?></td>
          <td><span class="badge badge-info"><?= $c['student_count'] ?> сурагч</span></td>
          <td style="white-space:nowrap">
            <?php if (isManager() || (isTeacher() && $c['teacher_id'] == $_SESSION['user_id'])): ?>
            <a href="?edit=<?= $c['class_id'] ?>" class="btn btn-sm btn-secondary" title="Засах"><i class="fas fa-edit"></i></a>
            <?php endif; ?>
            <?php if (isManager()): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="class_id" value="<?= $c['class_id'] ?>">
              <button class="btn btn-sm btn-danger" data-confirm="Ангийг устгах уу?" title="Устгах"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif; ?>
            <?php if(isTeacher() && empty($c['teacher_id'])): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="claim">
              <input type="hidden" name="class_id" value="<?= $c['class_id'] ?>">
              <button class="btn btn-sm btn-success" data-confirm="Энэ ангийг дааж авах уу?" title="Дааж авах"><i class="fas fa-hand-holding-heart"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$catClasses): ?><tr><td colspan="7" style="text-align:center;color:var(--muted)">Энэ ангилалд анги байхгүй байна</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ????? -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-plus"></i> Шинэ анги нэмэх</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST" data-confirm-form="Шинэ анги үүсгэх үү?">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Ангийн нэр *</label><input type="text" name="class_name" class="form-control" placeholder="10А" required></div>
          <div class="form-group"><label>Хичээлийн жил *</label><input type="text" name="academic_year" class="form-control" placeholder="2025-2026" value="2025-2026" required></div>
          <div class="form-group"><label>Ангийн багш</label>
            <select name="teacher_id" class="form-control">
              <option value="">Сонгох...</option>
              <?php foreach($teachers as $t): ?><option value="<?= $t['user_id'] ?>"><?= h($t['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Өрөө</label><input type="text" name="room" class="form-control" placeholder="201"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- ????? -->
<?php if($editClass): ?>
<div class="modal-overlay open" id="modalEdit">
<?php else: ?>
<div class="modal-overlay" id="modalEdit">
<?php endif; ?>
  <div class="modal">
    <div class="modal-header"><h3>Анги засах</h3><button class="modal-close" onclick="closeModal('modalEdit')">×</button></div>
    <form method="POST" data-confirm-form="Өөрчлөлтийг хадгалах уу?">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="class_id" value="<?= $editClass['class_id'] ?? '' ?>">
        <div class="form-row">
          <div class="form-group"><label>Ангийн нэр *</label><input type="text" name="class_name" class="form-control" value="<?= h($editClass['class_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Хичээлийн жил *</label><input type="text" name="academic_year" class="form-control" value="<?= h($editClass['academic_year'] ?? '') ?>" required></div>
          <div class="form-group"><label>Ангийн багш</label>
            <select name="teacher_id" class="form-control">
              <option value="">Сонгох...</option>
              <?php foreach($teachers as $t): ?><option value="<?= $t['user_id'] ?>" <?= ($editClass['teacher_id']??'')==$t['user_id']?'selected':'' ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Өрөө</label><input type="text" name="room" class="form-control" value="<?= h($editClass['room'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="index.php" class="btn btn-secondary">Болих</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

