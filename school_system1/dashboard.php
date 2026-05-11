<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Хяналтын самбар';
$role = $_SESSION['role'];
$isAdminOrManager = isAdmin() || isManager() || isDirector();
$isStudent = isStudent();
$isParent  = ($role === 'parent');

// ── Өөрийн student/parent ID олох ────────────────────────────
$myStudentId  = null;
$myChildIds   = [];

if ($isStudent) {
    $me = dbOne("SELECT student_id, merit_points FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    $myStudentId = $me['student_id'] ?? null;
    $myMerit = $me['merit_points'] ?? 0;
}
if ($isParent) {
    $myChildIds = isset($_SESSION['active_child_id']) ? [$_SESSION['active_child_id']] : [];
}

// ── KPI өгөгдөл (Initialize) ──────────────────────────────────
$stats = [
    'students' => 0, 'teachers' => 0, 'classes' => 0, 
    'paid' => 0, 'unpaid' => 0
];
$attPct = 0;
$gradeAvg = 0;

if ($isAdminOrManager) {
    // 3 тусдаа COUNT query-г 1 query-д нэгтгэсэн
    $kpi = dbOne("SELECT 
        (SELECT COUNT(*) FROM students WHERE is_active=1) AS students,
        (SELECT COUNT(*) FROM teachers) AS teachers,
        (SELECT COUNT(*) FROM classes) AS classes,
        (SELECT COUNT(*) FROM tuition WHERE status='paid') AS paid,
        (SELECT COUNT(*) FROM tuition WHERE status='unpaid') AS unpaid
    ");
    $stats = [
        'students' => $kpi['students'] ?? 0,
        'teachers' => $kpi['teachers'] ?? 0,
        'classes'  => $kpi['classes'] ?? 0,
        'paid'     => $kpi['paid'] ?? 0,
        'unpaid'   => $kpi['unpaid'] ?? 0,
    ];
    $att = dbOne("SELECT ROUND(SUM(CASE WHEN status_id=1 THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(*),0),1) AS pct FROM attendance");
    $attPct   = $att['pct'] ?? 0;
    $grade    = dbOne("SELECT ROUND(AVG(grade_value),1) AS avg FROM grades");
    $gradeAvg = $grade['avg'] ?? 0;
} elseif ($isStudent && $myStudentId) {
    $tuition = dbOne("SELECT COUNT(CASE WHEN status='paid' THEN 1 END) AS paid, COUNT(CASE WHEN status='unpaid' THEN 1 END) AS unpaid FROM tuition WHERE student_id=?", [$myStudentId]);
    $stats = [
        'paid'   => $tuition['paid'] ?? 0,
        'unpaid' => $tuition['unpaid'] ?? 0,
    ];
    $att = dbOne("SELECT ROUND(SUM(CASE WHEN status_id=1 THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(*),0),1) AS pct FROM attendance WHERE student_id=?", [$myStudentId]);
    $attPct   = $att['pct'] ?? 0;
    $grade    = dbOne("SELECT ROUND(AVG(grade_value),1) AS avg FROM grades WHERE student_id=?", [$myStudentId]);
    $gradeAvg = $grade['avg'] ?? 0;
} elseif ($isParent && !empty($myChildIds)) {
    $inPlaceholders = str_repeat('?,', count($myChildIds) - 1) . '?';
    $tuition = dbOne("SELECT COUNT(CASE WHEN status='paid' THEN 1 END) AS paid, COUNT(CASE WHEN status='unpaid' THEN 1 END) AS unpaid FROM tuition WHERE student_id IN ($inPlaceholders)", $myChildIds);
    $stats = [
        'paid'   => $tuition['paid'] ?? 0,
        'unpaid' => $tuition['unpaid'] ?? 0,
    ];
} elseif (isTeacher()) {
    $myClassResult = dbOne("SELECT class_id FROM classes WHERE teacher_id=?", [$_SESSION['user_id']]);
    $myClassId = $myClassResult['class_id'] ?? 0;
    
    if ($myClassId) {
        $stats = [
            'students' => (($r = dbOne("SELECT COUNT(*) AS cnt FROM students WHERE class_id=? AND is_active=1", [$myClassId])) ? $r['cnt'] : 0),
            'paid'     => (($r = dbOne("SELECT COUNT(*) AS cnt FROM tuition t JOIN students s ON t.student_id=s.student_id WHERE s.class_id=? AND t.status='paid'", [$myClassId])) ? $r['cnt'] : 0),
            'unpaid'   => (($r = dbOne("SELECT COUNT(*) AS cnt FROM tuition t JOIN students s ON t.student_id=s.student_id WHERE s.class_id=? AND t.status='unpaid'", [$myClassId])) ? $r['cnt'] : 0),
        ];
        
        // Date range logic removed as we show overall class averages now

        $att = dbOne("SELECT ROUND(SUM(CASE WHEN status_id=1 THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(*),0),1) AS pct FROM attendance WHERE student_id IN (SELECT student_id FROM students WHERE class_id=?)", [$myClassId]);
        $attPct = $att['pct'] ?? 0;
        
        $grade = dbOne("SELECT ROUND(AVG(grade_value),1) AS avg FROM grades WHERE student_id IN (SELECT student_id FROM students WHERE class_id=?)", [$myClassId]);
        $gradeAvg = $grade['avg'] ?? 0;
    }
}

// ── Ирцийн 7 хоногийн тренд ──────────────────────────────────
$attTrendLabels = [];
$attTrendData = [];
$gradeDistLabels = [];
$gradeDistData = [];

if ($isAdminOrManager) {
    $trend = dbQuery("SELECT date as d, ROUND(SUM(CASE WHEN status_id=1 THEN 1 ELSE 0 END)*100.0/COUNT(*),1) as pct FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY date ORDER BY d ASC");
    foreach($trend as $t) {
        $attTrendLabels[] = $t['d'];
        $attTrendData[] = $t['pct'];
    }

    // Дүнгийн тархалт (Grade Distribution)
    $gDist = dbQuery("SELECT 
        CASE 
            WHEN grade_value >= 90 THEN 'A (90-100)'
            WHEN grade_value >= 80 THEN 'B (80-89)'
            WHEN grade_value >= 70 THEN 'C (70-79)'
            WHEN grade_value >= 60 THEN 'D (60-69)'
            ELSE 'F (<60)'
        END as label,
        COUNT(*) as count
        FROM grades GROUP BY label ORDER BY label ASC");
    foreach($gDist as $g) {
        $gradeDistLabels[] = $g['label'];
        $gradeDistData[] = $g['count'];
    }
}

// ── Сүүлийн 3 зарлал ──────────────────────────────────────────
$recentAnnouncements = dbQuery("SELECT * FROM announcements ORDER BY pinned DESC, created_at DESC LIMIT 3");

// ── Багшийн удирддаг анги ────────────────────────────────────
$myClass = null;
if ($_SESSION['role'] === 'teacher') {
    $myClass = dbOne("SELECT c.*, COUNT(s.student_id) as student_count FROM classes c LEFT JOIN students s ON c.class_id=s.class_id AND s.is_active=1 WHERE c.teacher_id=? GROUP BY c.class_id", [$_SESSION['user_id']]);
}

// ── Сурагчийн дүнгийн график дата ────────────────────────────
$chartLabels = [];
$chartData = [];
$classAvgData = [];

if (($isStudent && $myStudentId) || ($isParent && current($myChildIds))) {
    $targetStudentId = $isStudent ? $myStudentId : current($myChildIds); // First child for chart if parent
    
    // My average
    $myGrades = dbQuery("SELECT sub.subject_id, sub.subject_name, ROUND(AVG(g.grade_value),1) as avg_grade FROM grades g JOIN subjects sub ON g.subject_id=sub.subject_id WHERE g.student_id=? GROUP BY sub.subject_id", [$targetStudentId]);
    
    // Find my class
    $me = dbOne("SELECT class_id FROM students WHERE student_id=?", [$targetStudentId]);
    $myClassId = $me['class_id'] ?? null;
    
    $classAverages = [];
    if ($myClassId) {
        $cAvgs = dbQuery("SELECT g.subject_id, ROUND(AVG(g.grade_value),1) as avg_grade FROM grades g JOIN students s ON g.student_id=s.student_id WHERE s.class_id=? GROUP BY g.subject_id", [$myClassId]);
        foreach ($cAvgs as $ca) {
            $classAverages[$ca['subject_id']] = $ca['avg_grade'];
        }
    }

    foreach ($myGrades as $mg) {
        $chartLabels[] = $mg['subject_name'];
        $chartData[] = $mg['avg_grade'];
        $classAvgData[] = $classAverages[$mg['subject_id']] ?? 0;
    }
}

// ── Сурагч/Эцэг эхийн ангийн багшийн мэдээлэл ────────────────
$myClassDetails = null;
if (($isStudent || $isParent) && isset($myClassId) && $myClassId) {
    $myClassDetails = dbOne("
        SELECT c.*, t.phone, u.email, CONCAT(t.last_name, ' ', t.first_name) as teacher_name 
        FROM classes c 
        LEFT JOIN teachers t ON c.teacher_id=t.user_id 
        LEFT JOIN users u ON t.user_id=u.user_id 
        WHERE c.class_id=?
    ", [$myClassId]);
}

// ── Сүүлийн activity ─────────────────────────────────────────
$recentLogs = $isAdminOrManager
    ? dbQuery("SELECT al.*, u.full_name FROM audit_log al LEFT JOIN users u ON al.user_id=u.user_id ORDER BY al.created_at DESC LIMIT 10")
    : dbQuery("SELECT al.*, u.full_name FROM audit_log al LEFT JOIN users u ON al.user_id=u.user_id WHERE al.user_id=? ORDER BY al.created_at DESC LIMIT 10", [$_SESSION['user_id']]);

// ── Сүүлийн сурагчид (зөвхөн admin/manager харна) ────────────
$recentStudents = $isAdminOrManager
    ? dbQuery("SELECT s.*, c.class_name, CONCAT(s.last_name,' ',s.first_name) AS full_name FROM students s JOIN classes c ON s.class_id=c.class_id WHERE s.is_active=1 ORDER BY s.student_id DESC LIMIT 5")
    : [];

// ── Төлөгдөөгүй төлбөрүүд (role-based) ──────────────────────
if ($isAdminOrManager) {
    $unpaidList = dbQuery("SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
        FROM tuition t JOIN students s ON t.student_id=s.student_id JOIN classes c ON s.class_id=c.class_id
        WHERE t.status='unpaid' ORDER BY t.due_date ASC LIMIT 5");
} elseif ($isStudent && $myStudentId) {
    $unpaidList = dbQuery("SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
        FROM tuition t JOIN students s ON t.student_id=s.student_id JOIN classes c ON s.class_id=c.class_id
        WHERE t.status='unpaid' AND t.student_id=?
        ORDER BY t.due_date ASC LIMIT 5", [$myStudentId]);
} elseif ($isParent && $myChildIds) {
    $inPlaceholders = str_repeat('?,', count($myChildIds) - 1) . '?';
    $unpaidList = dbQuery("SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
        FROM tuition t JOIN students s ON t.student_id=s.student_id JOIN classes c ON s.class_id=c.class_id
        WHERE t.status='unpaid' AND t.student_id IN ($inPlaceholders)
        ORDER BY t.due_date ASC LIMIT 5", $myChildIds);
} else {
    $unpaidList = [];
}

// ── Эрсдэлтэй сурагчид (Smart Risk Analytics - Ухаалаг Анхааруулга) ──
$riskStudents = [];
if ($isAdminOrManager) {
    $riskStudents = dbQuery("SELECT s.student_id, CONCAT(s.last_name, ' ', s.first_name) AS full_name, c.class_name,
               ROUND(AVG(g.grade_value),1) as avg_grade,
               ROUND(SUM(CASE WHEN a.status_id=1 THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(DISTINCT a.attendance_id),0),1) as att_pct
        FROM students s
        JOIN classes c ON s.class_id=c.class_id
        LEFT JOIN grades g ON g.student_id=s.student_id
        LEFT JOIN attendance a ON a.student_id=s.student_id
        WHERE s.is_active=1
        GROUP BY s.student_id, s.last_name, s.first_name, c.class_name
        HAVING avg_grade < 70 OR att_pct < 80
        ORDER BY avg_grade ASC LIMIT 5");
}

include __DIR__ . '/includes/header.php';
?>

<!-- STAT CARDS -->
<div class="stats-grid">
<?php if ($isAdminOrManager): ?>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['students'] ?></div>
      <div class="stat-label">Идэвхтэй сурагч</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['teachers'] ?></div>
      <div class="stat-label">Багш</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fas fa-chalkboard"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['classes'] ?></div>
      <div class="stat-label">Анги</div>
    </div>
  </div>
<?php endif; ?>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-clipboard-check"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $attPct ?>%</div>
      <div class="stat-label">Ирцийн хувь</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-star"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $gradeAvg ?></div>
      <div class="stat-label">Дундаж дүн</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['unpaid'] ?></div>
      <div class="stat-label">Төлөгдөөгүй төлбөр</div>
    </div>
  </div>
</div>

<!-- GAMIFICATION (Зан төлөвийн урамшуулал) -->
<?php if ($isStudent && isset($myMerit)): ?>
<div class="card" style="margin-top:20px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;">
  <div class="card-body" style="display:flex; align-items:center; gap:20px;">
    <div style="font-size: 40px; color: #fbbf24;"><i class="fas fa-medal"></i></div>
    <div>
      <h3 style="margin-bottom: 5px;">Ангийн шилдэгт өрсөлдөж байна!</h3>
      <p style="opacity:0.9;">Таны цуглуулсан урамшууллын оноо: <strong><?= $myMerit ?> оноо</strong></p>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ДЭЛГЭЦ ХУВААХ -->
<div class="responsive-grid">

  <!-- Сүүлийн идэвхүүд -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-history"></i> Сүүлийн үйлдлүүд</h2>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead><tr><th>Хэрэглэгч</th><th>Үйлдэл</th><th>Цаг</th></tr></thead>
        <tbody>
        <?php foreach($recentLogs as $log): ?>
        <tr>
          <td><?= h($log['full_name'] ?? 'Систем') ?></td>
          <td><span class="badge badge-info"><?= h($log['action']) ?></span></td>
          <td style="font-size:11px;color:var(--muted)"><?= mnDateTime($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$recentLogs): ?><tr><td colspan="3" style="text-align:center;color:var(--muted)">Үйлдэл байхгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Төлөгдөөгүй төлбөрүүд -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i>
        <?= ($isStudent || $isParent) ? 'Миний төлөгдөөгүй төлбөрүүд' : 'Төлөгдөөгүй төлбөрүүд' ?>
      </h2>
      <a href="/school_system1/pages/payments/index.php" class="btn btn-sm btn-secondary">Бүгдийг харах</a>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead>
          <tr>
            <?php if ($isAdminOrManager): ?>
            <th>Сурагч</th><th>Анги</th>
            <?php endif; ?>
            <th>Дүн</th><th>Хугацаа</th>
            <?php if ($isStudent || $isParent): ?><th>Төлөх</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach($unpaidList as $u): ?>
        <tr>
          <?php if ($isAdminOrManager): ?>
          <td><?= h($u['student_name']) ?></td>
          <td><?= h($u['class_name']) ?></td>
          <?php endif; ?>
          <td><strong><?= mnMoney($u['amount']) ?></strong></td>
          <td style="color:var(--danger);font-size:11px"><?= h($u['due_date']) ?></td>
          <?php if ($isStudent || $isParent): ?>
          <td>
            <a href="/school_system1/pages/payments/pay.php?id=<?= $u['tuition_id'] ?>"
               class="btn btn-sm btn-primary">
              <i class="fas fa-credit-card"></i> Төлөх
            </a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if(!$unpaidList): ?>
        <tr>
          <td colspan="<?= $isAdminOrManager ? 4 : ($isStudent||$isParent ? 3 : 2) ?>"
              style="text-align:center;color:var(--success)">
            <i class="fas fa-check-circle"></i> Бүх төлбөр төлөгдсөн!
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- БАГШИЙН УДИРДДАГ АНГИ -->
<?php if ($myClass): ?>
<div class="card" style="margin-top:20px; border-top:4px solid var(--primary);">
  <div class="card-header teacher-class-card" style="display:flex; justify-content:space-between; align-items:center;">
    <h2 style="color:var(--teacher-card-text); margin:0;"><i class="fas fa-users"></i> Миний удирддаг анги: <?= h($myClass['class_name']) ?></h2>
    <div style="background:rgba(255,255,255,0.2); padding:5px 15px; border-radius:20px; font-weight:700; font-size:12px; color:#fff;">
      <i class="fas fa-calendar-alt"></i> <?= getQuarter() ?>-р улирал
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
        <table style="width:100%;">
          <tr><th>Хичээлийн жил:</th><td><?= h($myClass['academic_year']) ?></td></tr>
          <tr><th>Сурагчдын тоо:</th><td><span class="badge badge-success"><?= $myClass['student_count'] ?></span></td></tr>
          <tr><th>Уулзалтын өрөө:</th><td><?= h($myClass['room'] ?: '-') ?></td></tr>
        </table>
        <div style="background:var(--bg); padding:15px; border-radius:12px; border:1px solid var(--border); display:flex; flex-direction:column; justify-content:center; align-items:center;">
            <div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700;">Ангийн дундаж ирц</div>
            <div style="font-size:28px; font-weight:800; color:var(--primary);"><?= $attPct ?>%</div>
        </div>
        <div style="background:var(--bg); padding:15px; border-radius:12px; border:1px solid var(--border); display:flex; flex-direction:column; justify-content:center; align-items:center;">
            <div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700;">Ангийн дундаж дүн</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= $gradeAvg ?></div>
        </div>
    </div>
    <div style="margin-top:20px; display:flex; gap:10px;">
      <a href="/school_system1/pages/students/index.php" class="btn btn-sm btn-primary"><i class="fas fa-list"></i> Сурагчид харах</a>
      <a href="/school_system1/pages/attendance/bulk.php" class="btn btn-sm btn-secondary"><i class="fas fa-calendar-check"></i> Ирцийн журнал</a>
      <a href="/school_system1/pages/grades/index.php" class="btn btn-sm btn-secondary"><i class="fas fa-star"></i> Дүнгийн журнал</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- СУРАГЧ/ЭЦЭГ ЭХИЙН АНГИЙН БАГШ -->
<?php if ($myClassDetails): ?>
<div class="card" style="margin-top:20px;">
  <div class="card-header" style="background:var(--hint-bg)">
    <h2><i class="fas fa-info-circle"></i> Ангийн мэдээлэл: <?= h($myClassDetails['class_name']) ?></h2>
  </div>
  <div class="card-body">
    <table style="width:auto; min-width:50%">
      <tr><th>Ангийн багш:</th><td><strong><?= h($myClassDetails['teacher_name'] ?? 'Томилогдоогүй') ?></strong></td></tr>
      <tr><th>Утас:</th><td><?= h($myClassDetails['phone'] ?? '-') ?></td></tr>
      <tr><th>Имэйл:</th><td><?= h($myClassDetails['email'] ?? '-') ?></td></tr>
      <tr><th>Өрөө:</th><td><?= h($myClassDetails['room'] ?: '-') ?></td></tr>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- СУРАГЧИЙН ГРАФИК -->
<?php if (!empty($chartLabels)): ?>
<div class="card" style="margin-top:20px;">
  <div class="card-header">
    <h2><i class="fas fa-chart-bar"></i> Дүнгийн дундаж үзүүлэлт (Ангитай харьцуулах)</h2>
  </div>
  <div class="card-body" style="height:350px">
    <canvas id="gradeChart"></canvas>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('gradeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Миний / Хүүхдийн дундаж',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Ангийн дундаж',
                data: <?= json_encode($classAvgData) ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
});
</script>
<?php endif; ?>

<!-- АДМИН ГРАФИК (Шинэчилсэн Дизайн) -->
<?php if ($isAdminOrManager && isset($stats['paid'])): ?>
<div class="card" style="margin-top:30px; border: 1px solid var(--border); background: var(--card-bg); box-shadow: 0 10px 40px rgba(0,0,0,0.08); border-radius: 24px; overflow: hidden;">
  <div class="card-header" style="background: transparent; border-bottom: 1px solid var(--border); padding: 25px 30px;">
    <h2 style="font-size: 20px; font-weight: 800; color: var(--text); margin: 0; display: flex; align-items: center; gap: 15px;">
      <div style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); padding: 12px; border-radius: 14px; color: white; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);">
        <i class="fas fa-chart-pie" style="font-size: 20px;"></i>
      </div>
      Системийн ерөнхий үзүүлэлт
    </h2>
  </div>
  
  <div class="card-body" style="padding: 30px;">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:30px;">
      
      <!-- Бүрэлдэхүүн -->
      <div style="background: var(--bg); border-radius: 20px; padding: 30px; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; position: relative; overflow: hidden; transition: transform 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%); border-radius: 50%;"></div>
        
        <h3 style="font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 25px; width: 100%; text-align: left; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-users" style="color:#3b82f6; opacity:0.8;"></i> Нийт бүрэлдэхүүн
        </h3>
        <div style="position: relative; width: 200px; height: 200px;">
          <canvas id="adminPie1"></canvas>
          <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; pointer-events: none;">
            <span style="font-size: 36px; font-weight: 800; color: var(--text); line-height: 1;"><?= $stats['teachers'] + $stats['students'] ?></span>
            <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 6px;">Нийт</span>
          </div>
        </div>
        
        <div style="width: 100%; margin-top: 30px; display: flex; justify-content: space-between; gap: 15px;">
           <div style="flex: 1; background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 16px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.2);">
             <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Багш</div>
             <div style="font-size: 22px; font-weight: 800; color: #10b981;"><?= $stats['teachers'] ?></div>
           </div>
           <div style="flex: 1; background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 16px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.2);">
             <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Сурагч</div>
             <div style="font-size: 22px; font-weight: 800; color: #3b82f6;"><?= $stats['students'] ?></div>
           </div>
        </div>
      </div>

      <!-- Төлбөрийн байдал -->
      <div style="background: var(--bg); border-radius: 20px; padding: 30px; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; position: relative; overflow: hidden; transition: transform 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: radial-gradient(circle, rgba(239, 68, 68, 0.15) 0%, rgba(255,255,255,0) 70%); border-radius: 50%;"></div>
        
        <h3 style="font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 25px; width: 100%; text-align: left; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-file-invoice-dollar" style="color:#ef4444; opacity:0.8;"></i> Төлбөрийн байдал
        </h3>
        <div style="position: relative; width: 200px; height: 200px;">
          <canvas id="adminPie2"></canvas>
          <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; pointer-events: none;">
            <span style="font-size: 36px; font-weight: 800; color: var(--text); line-height: 1;"><?= $stats['paid'] + $stats['unpaid'] ?></span>
            <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 6px;">Нийт</span>
          </div>
        </div>
        
        <div style="width: 100%; margin-top: 30px; display: flex; justify-content: space-between; gap: 15px;">
           <div style="flex: 1; background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 16px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.2);">
             <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Төлсөн</div>
             <div style="font-size: 22px; font-weight: 800; color: #10b981;"><?= $stats['paid'] ?></div>
           </div>
           <div style="flex: 1; background: rgba(239, 68, 68, 0.1); padding: 15px; border-radius: 16px; text-align: center; border: 1px solid rgba(239, 68, 68, 0.2);">
             <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Төлөөгүй</div>
             <div style="font-size: 22px; font-weight: 800; color: #ef4444;"><?= $stats['unpaid'] ?></div>
           </div>
        </div>
      </div>
      
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    
    // Common Chart Options for a beautiful doughnut
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '78%', // makes the ring thinner, more elegant
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleFont: { size: 13, weight: '600' },
                bodyFont: { size: 15, weight: 'bold' },
                padding: 14,
                cornerRadius: 12,
                displayColors: true,
                boxWidth: 10,
                boxHeight: 10,
                usePointStyle: true,
                callbacks: {
                    label: function(context) {
                        return '  ' + context.label + ': ' + context.formattedValue;
                    }
                }
            }
        },
        layout: { padding: 10 }
    };

    // Chart 1: Users
    new Chart(document.getElementById('adminPie1').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Багш', 'Сурагч'],
            datasets: [{
                data: [<?= $stats['teachers'] ?>, <?= $stats['students'] ?>],
                backgroundColor: ['#10b981', '#3b82f6'],
                hoverBackgroundColor: ['#059669', '#2563eb'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: commonOptions
    });

    // Chart 2: Payments
    new Chart(document.getElementById('adminPie2').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Төлсөн', 'Төлөөгүй'],
            datasets: [{
                data: [<?= $stats['paid'] ?>, <?= $stats['unpaid'] ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                hoverBackgroundColor: ['#059669', '#dc2626'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: commonOptions
    });
});
</script>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px; margin-top:20px;">
  <!-- Attendance Trend -->
  <div class="card" style="margin:0;">
    <div class="card-header">
      <h2><i class="fas fa-chart-line"></i> Ирцийн хандлага (Сүүлийн 7 хоног)</h2>
    </div>
    <div class="card-body" style="height:300px; display:flex; align-items:center; justify-content:center;">
      <?php if (!empty($attTrendLabels)): ?>
        <canvas id="attTrendChart"></canvas>
      <?php else: ?>
        <div style="color:var(--muted); font-size:14px;"><i class="fas fa-info-circle"></i> Сүүлийн 7 хоногт ирцийн мэдээлэл бүртгэгдээгүй байна.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Grade Distribution -->
  <div class="card" style="margin:0;">
    <div class="card-header">
      <h2><i class="fas fa-chart-bar"></i> Дүнгийн тархалт (Нийт)</h2>
    </div>
    <div class="card-body" style="height:300px; display:flex; align-items:center; justify-content:center;">
      <?php if (!empty($gradeDistLabels)): ?>
        <canvas id="gradeDistChart"></canvas>
      <?php else: ?>
        <div style="color:var(--muted); font-size:14px;"><i class="fas fa-info-circle"></i> Дүнгийн мэдээлэл бүртгэгдээгүй байна.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Attendance Trend
    <?php if (!empty($attTrendLabels)): ?>
    new Chart(document.getElementById('attTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($attTrendLabels) ?>,
            datasets: [{
                label: 'Ирцийн хувь (%)',
                data: <?= json_encode($attTrendData) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.2)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
    <?php endif; ?>

    // Grade Distribution
    <?php if (!empty($gradeDistLabels)): ?>
    new Chart(document.getElementById('gradeDistChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($gradeDistLabels) ?>,
            datasets: [{
                label: 'Сурагчдын тоо',
                data: <?= json_encode($gradeDistData) ?>,
                backgroundColor: '#f59e0b',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<!-- SMART RISK ANALYTICS AI (Эрсдэлтэй сурагчид) -->
<?php if ($isAdminOrManager && $riskStudents): ?>
<div class="card" style="margin-top:20px; border-left: 4px solid var(--danger);">
  <div class="card-header">
    <h2 style="color:var(--danger)"><i class="fas fa-exclamation-circle"></i> AI Эрсдэлийн мэдээлэл (Хурц Анхааруулга)</h2>
  </div>
  <div class="card-body" style="padding:0">
    <table>
      <thead><tr><th>Сурагч</th><th>Анги</th><th>Дүн (Дундаж)</th><th>Ирц</th><th>Төлөв</th></tr></thead>
      <tbody>
      <?php foreach($riskStudents as $r): ?>
      <tr>
        <td><strong><?= h($r['full_name']) ?></strong></td>
        <td><?= h($r['class_name']) ?></td>
        <td style="color: <?= $r['avg_grade']<70?'var(--danger)':'inherit' ?>; font-weight:bold;"><?= $r['avg_grade'] ?: '-' ?></td>
        <td style="color: <?= $r['att_pct']<80?'var(--warning)':'inherit' ?>; font-weight:bold;"><?= $r['att_pct'] ? ($r['att_pct'].'%') : '-' ?></td>
        <td>
           <span class="badge badge-danger" style="animation: pulse 2s infinite;"><i class="fas fa-radiation"></i> Хоцрогдох эрсдэлтэй</span>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- СҮҮЛИЙН СУРАГЧИД — зөвхөн admin/manager -->
<?php if ($isAdminOrManager && $recentStudents): ?>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-user-graduate"></i> Сурагчдын жагсаалт (сүүлийн 5)</h2>
    <a href="/school_system1/pages/students/index.php" class="btn btn-sm btn-primary">
      <i class="fas fa-list"></i> Бүгдийг харах
    </a>
  </div>
  <div class="card-body" style="padding:0">
    <table>
      <thead><tr><th>#</th><th>Нэр</th><th>Анги</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach($recentStudents as $i => $s): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= h($s['full_name']) ?></td>
        <td><?= h($s['class_name']) ?></td>
        <td><a href="/school_system1/pages/students/view.php?id=<?= $s['student_id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($recentAnnouncements): ?>
<div class="card" style="margin-top:20px;">
  <div class="card-header">
    <h2><i class="fas fa-bullhorn"></i> Сүүлийн үеийн зарлал & мэдэгдэл</h2>
    <a href="/school_system1/pages/announcements/index.php" class="btn btn-sm btn-primary">
      Бүгдийг харах
    </a>
  </div>
  <div class="card-body">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
      <?php foreach($recentAnnouncements as $ann): ?>
      <div style="background:var(--bg); border:1px solid var(--border); border-radius:12px; overflow:hidden; display:flex; flex-direction:column;">
        <?php if (!empty($ann['image_url'])): ?>
          <div style="height:150px; overflow:hidden;">
            <img src="<?= h($ann['image_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
          </div>
        <?php endif; ?>
        <div style="padding:15px; flex:1; display:flex; flex-direction:column;">
          <h3 style="font-size:15px; font-weight:600; margin-bottom:10px;">
            <?php if($ann['pinned']): ?><i class="fas fa-thumbtack" style="color:var(--primary); font-size:12px;"></i> <?php endif; ?>
            <?= h($ann['title']) ?>
          </h3>
          <p style="font-size:13px; color:var(--text); opacity:0.8; line-height:1.5; margin-bottom:15px; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
            <?= h($ann['body']) ?>
          </p>
          <div style="margin-top:auto; font-size:11px; color:var(--muted); display:flex; justify-content:space-between;">
            <span><i class="fas fa-clock"></i> <?= mnDate($ann['created_at']) ?></span>
            <a href="/school_system1/pages/announcements/index.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Унших <i class="fas fa-chevron-right"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

