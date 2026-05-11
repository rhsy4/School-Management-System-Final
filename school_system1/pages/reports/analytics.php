<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin','manager','director']);

$pageTitle = 'Нарийвчилсан Тайлан & Шинжилгээ';

// ── СУРАГЧИЙН ГҮЙЦЭТГЭЛИЙН ТРЕНД ──────────────────────────
$performanceTrend = dbQuery("
    SELECT 
        DATE(g.created_at) as date,
        COUNT(DISTINCT g.student_id) as students_graded,
        ROUND(AVG(g.grade_value), 1) as avg_grade,
        MAX(g.grade_value) as max_grade,
        MIN(g.grade_value) as min_grade
    FROM grades g
    WHERE g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(g.created_at)
    ORDER BY date DESC
");

// ── БАГШИЙН НӨЛӨӨЛӨЛ ҮНЭЛГЭЭ ────────────────────────────
$teacherEffectiveness = dbQuery("
    SELECT 
        CONCAT(t.last_name, ' ', t.first_name) as teacher_name,
        COUNT(g.grade_id) as grades_given,
        ROUND(AVG(g.grade_value), 1) as avg_student_grade,
        COUNT(DISTINCT g.student_id) as students_taught,
        COUNT(DISTINCT s.class_id) as classes_taught
    FROM teachers t
    LEFT JOIN grades g ON t.user_id = g.teacher_id AND g.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
    LEFT JOIN students s ON g.student_id = s.student_id
    GROUP BY t.user_id
    ORDER BY COUNT(g.grade_id) DESC
");

// ── АНГИЙН УЗ ҮЗҮҮЛЭЛТ ──────────────────────────────────
$classPerformance = dbQuery("
    SELECT 
        c.class_name,
        c.class_id,
        COUNT(DISTINCT s.student_id) as student_count,
        ROUND(AVG(g.grade_value), 1) as avg_grade,
        SUM(CASE WHEN a.status_id = 1 THEN 1 ELSE 0 END) as present_count,
        COUNT(DISTINCT a.attendance_id) as total_attendance_records
    FROM classes c
    LEFT JOIN students s ON c.class_id = s.class_id AND s.is_active = 1
    LEFT JOIN grades g ON s.student_id = g.student_id AND g.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    LEFT JOIN attendance a ON s.student_id = a.student_id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY c.class_id
    ORDER BY avg_grade DESC
");

// ── СУРАГЧИЙН ЯВЦЫН ХЯНАЛТ ──────────────────────────────
$studentProgress = dbQuery("
    SELECT 
        CONCAT(s.last_name, ' ', s.first_name) as student_name,
        s.student_id,
        c.class_name,
        COUNT(g.grade_id) as total_grades,
        ROUND(AVG(g.grade_value), 1) as avg_grade,
        MAX(g.grade_value) as best_grade,
        MIN(g.grade_value) as worst_grade,
        SUM(CASE WHEN a.status_id = 1 THEN 1 ELSE 0 END) as attendance_count,
        COUNT(DISTINCT a.attendance_id) as total_attendance
    FROM students s
    JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN grades g ON s.student_id = g.student_id AND g.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    LEFT JOIN attendance a ON s.student_id = a.student_id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE s.is_active = 1
    GROUP BY s.student_id
    ORDER BY avg_grade DESC
    LIMIT 100
");

// ── ТӨЛБӨРИЙН СТАТИСТИК ──────────────────────────────────
$paymentStats = dbQuery("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as payment_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid
    FROM tuition
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

// ── СИСТЕМИЙН ЭРҮҮЛ БАЙ ДҮЗҮҮЛЭЛТ ──────────────────────
$systemHealth = [
    'total_users' => dbOne("SELECT COUNT(*) as cnt FROM users WHERE is_active=1")['cnt'],
    'total_students' => dbOne("SELECT COUNT(*) as cnt FROM students WHERE is_active=1")['cnt'],
    'total_teachers' => dbOne("SELECT COUNT(*) as cnt FROM teachers")['cnt'],
    'total_classes' => dbOne("SELECT COUNT(*) as cnt FROM classes")['cnt'],
    'total_grades' => dbOne("SELECT COUNT(*) as cnt FROM grades")['cnt'],
    'today_attendance' => dbOne("SELECT COUNT(DISTINCT student_id) as cnt FROM attendance WHERE DATE(created_at) = CURDATE() AND status_id=1")['cnt'],
    'pending_payments' => dbOne("SELECT COUNT(*) as cnt FROM tuition WHERE status='unpaid'")['cnt'],
];

include __DIR__ . '/../../includes/header.php';
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['total_users'] ?></div><div class="stat-label">Нийт хэрэглэгч</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['total_students'] ?></div><div class="stat-label">Нийт сурагч</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['total_teachers'] ?></div><div class="stat-label">Багш</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-book"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['total_grades'] ?></div><div class="stat-label">Дүн</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['today_attendance'] ?></div><div class="stat-label">Өнөөдрийн ирц</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-money-bill"></i></div><div class="stat-info"><div class="stat-value"><?= $systemHealth['pending_payments'] ?></div><div class="stat-label">Төлөгдөөгүй төлбөр</div></div></div>
</div>

<!-- СУРАГЧИЙН ГҮЙЦЭТГЭЛ ТРЕНД -->
<div class="card" style="margin-bottom: 30px;">
  <div class="card-header">
    <h2><i class="fas fa-chart-line"></i> Сурагчийн гүйцэтгэлийн тренд (30 өдрийн төлөв)</h2>
  </div>
  <div class="card-body" style="padding: 0;">
    <table>
      <thead>
        <tr>
          <th>Өдөр</th>
          <th>Дүн оруулсан сурагч</th>
          <th>Дундаж дүн</th>
          <th>Хамгийн сайн</th>
          <th>Хамгийн муу</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($performanceTrend as $trend): ?>
        <tr>
          <td><?= mnDate($trend['date']) ?></td>
          <td><?= $trend['students_graded'] ?></td>
          <td>
            <span style="color:<?= $trend['avg_grade']>=80?'var(--success)':($trend['avg_grade']>=60?'var(--warning)':'var(--danger)') ?>">
              <strong><?= $trend['avg_grade'] ?></strong>
            </span>
          </td>
          <td><span style="color: var(--success)"><?= $trend['max_grade'] ?></span></td>
          <td><span style="color: var(--danger)"><?= $trend['min_grade'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- БАГШИЙН НӨЛӨӨЛӨЛ ҮНЭЛГЭЭ -->
<div class="card" style="margin-bottom: 30px;">
  <div class="card-header">
    <h2><i class="fas fa-star"></i> Багшийн үр дүн (Гүйцэтгэл)</h2>
  </div>
  <div class="card-body" style="padding: 0;">
    <table>
      <thead>
        <tr>
          <th>Багшийн нэр</th>
          <th>Өгөгдсөн дүн</th>
          <th>Сурагчдын дундаж</th>
          <th>Багаслуулсан сурагч</th>
          <th>Анги</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($teacherEffectiveness as $teacher): ?>
        <tr>
          <td><?= h($teacher['teacher_name']) ?></td>
          <td><?= $teacher['grades_given'] ?></td>
          <td><strong style="color: <?= $teacher['avg_student_grade']>=80?'var(--success)':($teacher['avg_student_grade']>=60?'var(--warning)':'var(--danger)') ?>"><?= $teacher['avg_student_grade'] ?></strong></td>
          <td><?= $teacher['students_taught'] ?></td>
          <td><?= $teacher['classes_taught'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- АНГИЙН УЗ ҮЗҮҮЛЭЛТ -->
<div class="card" style="margin-bottom: 30px;">
  <div class="card-header">
    <h2><i class="fas fa-chalkboard"></i> Ангийн гүйцэтгэлийн харьцуулалт</h2>
  </div>
  <div class="card-body" style="padding: 0;">
    <table>
      <thead>
        <tr>
          <th>Ангийн нэр</th>
          <th>Сурагч</th>
          <th>Дундаж дүн</th>
          <th>Ирц (30 өдөр)</th>
          <th>Ирцийн хувь</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($classPerformance as $class): ?>
        <tr>
          <td><strong><?= h($class['class_name']) ?></strong></td>
          <td><?= $class['student_count'] ?></td>
          <td><span style="color: <?= $class['avg_grade']>=80?'var(--success)':($class['avg_grade']>=60?'var(--warning)':'var(--danger)') ?>"><?= $class['avg_grade'] ?? '-' ?></span></td>
          <td><?= $class['present_count'] ?? 0 ?></td>
          <td>
            <?php 
              $attendance_pct = ($class['total_attendance_records'] > 0) 
                ? round(($class['present_count'] / $class['total_attendance_records']) * 100, 1)
                : 0;
              $color = $attendance_pct >= 90 ? 'var(--success)' : ($attendance_pct >= 75 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <span style="color: <?= $color ?>"><strong><?= $attendance_pct ?>%</strong></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ТӨЛБӨРИЙН СТАТИСТИК -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-chart-bar"></i> Төлбөрийн статистик (12 сар)</h2>
  </div>
  <div class="card-body" style="padding: 0;">
    <table>
      <thead>
        <tr>
          <th>Сар</th>
          <th>Нийт төлбөр</th>
          <th>Төлөгдсөн</th>
          <th>Төлөгдөөгүй</th>
          <th>Орлого</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($paymentStats as $stat): ?>
        <tr>
          <td><strong><?= $stat['month'] ?></strong></td>
          <td><?= $stat['payment_count'] ?></td>
          <td style="color: var(--success)"><?= $stat['paid_count'] ?></td>
          <td style="color: var(--danger)"><?= $stat['unpaid_count'] ?></td>
          <td><strong><?= mnMoney($stat['total_paid'] ?? 0) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
