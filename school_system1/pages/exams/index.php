<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Онлайн Шалгалт';

// For Teachers/Managers
if (!isStudent() && !isParent()):
    $where = (isManager()) ? "1=1" : "e.created_by = " . (int)$_SESSION['user_id'];
    $exams = dbQuery("
        SELECT e.*, s.subject_name, c.class_name, 
               (SELECT COUNT(*) FROM exam_questions WHERE exam_id=e.exam_id) as q_count,
               (SELECT COUNT(*) FROM exam_results WHERE exam_id=e.exam_id) as taker_count
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN classes c ON e.class_id = c.class_id
        WHERE $where
        ORDER BY e.created_at DESC
    ");
else:
// For Students
    $student = dbOne("SELECT class_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    $classId = $student['class_id'] ?? 0;
    
    $where = "e.is_active = 1 AND (e.class_id IS NULL OR e.class_id = ?)";
    $exams = dbQuery("
        SELECT e.*, s.subject_name,
               (SELECT COUNT(*) FROM exam_questions WHERE exam_id=e.exam_id) as q_count,
               r.result_id, r.score as total_score, r.finished_at as submitted_at
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN exam_results r ON e.exam_id = r.exam_id 
             AND r.student_id = (SELECT student_id FROM students WHERE user_id=". (int)$_SESSION['user_id'] .")
        WHERE $where
        ORDER BY e.created_at DESC
    ", [$classId]);
endif;

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h2><i class="fas fa-laptop-code"></i> Онлайн Шалгалтууд</h2>
    <?php if (isManager() || isTeacher()): ?>
      <a href="/school_system1/pages/exams/manage.php" class="btn btn-primary"><i class="fas fa-plus"></i> Шалгалт үүсгэх</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($flash = getFlash()): ?>
      <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>
    
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Гарчиг</th>
            <th>Хичээл</th>
            <?php if (!isStudent() && !isParent()): ?><th>Анги</th><?php endif; ?>
            <th>Асуултын тоо</th>
            <th>Хугацаа</th>
            <th>Төлөв</th>
            <th>Үйлдэл</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($exams as $e): ?>
            <tr>
              <td><strong><?= h($e['title']) ?></strong></td>
              <td><span class="badge badge-info"><?= h($e['subject_name']) ?></span></td>
              <?php if (!isStudent() && !isParent()): ?>
                <td><?= $e['class_name'] ? h($e['class_name']) : 'Бүгд' ?></td>
              <?php endif; ?>
              <td><?= $e['q_count'] ?></td>
              <td><?= $e['duration_minutes'] ?> минут</td>
              <td>
                  <?php if (!isStudent() && !isParent()): ?>
                      <?php if ($e['is_active']): ?>
                          <span class="badge badge-success">Идэвхтэй</span>
                      <?php else: ?>
                          <span class="badge badge-danger">Хаалттай</span>
                      <?php endif; ?>
                  <?php else: ?>
                      <?php if ($e['submitted_at']): ?>
                          <span class="badge badge-success">Өгсөн (Оноо: <?= $e['total_score'] ?>)</span>
                      <?php else: ?>
                          <span class="badge badge-warning">Өгөөгүй</span>
                      <?php endif; ?>
                  <?php endif; ?>
              </td>
              <td style="white-space:nowrap;">
                  <?php if (!isStudent() && !isParent()): ?>
                      <a href="/school_system1/pages/exams/manage.php?id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-secondary" title="Засах"><i class="fas fa-edit"></i></a>
                      <a href="/school_system1/pages/exams/results.php?id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-info" title="Үр дүн"><i class="fas fa-chart-bar"></i> (<?= $e['taker_count'] ?>)</a>
                  <?php else: ?>
                      <?php if ($e['submitted_at']): ?>
                          <a href="/school_system1/pages/exams/results.php?id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Дүн харах</a>
                      <?php else: ?>
                          <a href="/school_system1/pages/exams/take.php?id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-play"></i> Эхлэх</a>
                      <?php endif; ?>
                  <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$exams): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:20px;">Шалгалт олдсонгүй.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
