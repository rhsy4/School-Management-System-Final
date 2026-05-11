<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$student = dbOne("SELECT s.*, CONCAT(s.last_name,' ',s.first_name) AS full_name,
    c.class_name, u.username, u.email, u.phone AS user_phone, u.is_active
    FROM students s
    JOIN classes c ON s.class_id=c.class_id
    JOIN users u ON s.user_id=u.user_id
    WHERE s.student_id=?", [$id]);

if (!$student) { setFlash('error','Сурагч олдсонгүй'); header('Location: /school_system1/pages/students/index.php'); exit; }

$pageTitle = $student['full_name'];

// Дүн
$grades = dbQuery("SELECT g.*, sub.subject_name FROM grades g JOIN subjects sub ON g.subject_id=sub.subject_id WHERE g.student_id=? ORDER BY g.created_at DESC", [$id]);

// Ирц нэгтгэл
$attSummary = dbQuery("SELECT sub.subject_name,
    SUM(CASE WHEN a.status_id=1 THEN 1 ELSE 0 END) AS present,
    SUM(CASE WHEN a.status_id=2 THEN 1 ELSE 0 END) AS absent,
    SUM(CASE WHEN a.status_id=3 THEN 1 ELSE 0 END) AS sick,
    COUNT(*) AS total
    FROM attendance a
    JOIN subjects sub ON a.subject_id=sub.subject_id
    WHERE a.student_id=?
    GROUP BY sub.subject_id", [$id]);

// Төлбөр
$payments = dbQuery("SELECT * FROM tuition WHERE student_id=? ORDER BY created_at DESC", [$id]);

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;gap:10px;margin-bottom:16px">
  <a href="/school_system1/pages/students/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Буцах</a>
  <?php if(isManager()): ?>
  <a href="/school_system1/pages/students/index.php?edit=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Засах</a>
  <?php endif; ?>
</div>

<?php 
// Spider Chart Data
$gradesData = dbQuery("SELECT s.subject_name, g.grade_value as score 
                       FROM grades g JOIN subjects s ON g.subject_id=s.subject_id 
                       WHERE g.student_id=?", [$id]);

$cats = [
  'Математик' => ['Математик','Геометр','Тоо'],
  'Хэл' => ['Монгол хэл','Англи','Орос','Хэл','Уран зохиол'],
  'Байгалийн ухаан' => ['Физик','Хими','Биологи','Байгаль'],
  'Нийгмийн ухаан' => ['Түүх','Нийгэм','Газар'],
  'Урлаг & Спорт' => ['Биеийн тамир','Зураг','Дуу','Мэдээлэл']
];

$catScores = [];
foreach($cats as $catName => $subjects) {
    $sum = 0; $count = 0;
    foreach($gradesData as $g) {
        $matched = false;
        foreach($subjects as $s) {
            if(mb_stripos($g['subject_name'], $s) !== false) {
               $matched = true;
               break;
            }
        }
        if($matched) {
            $sum += $g['score'];
            $count++;
        }
    }
    $catScores[$catName] = $count > 0 ? round($sum / $count, 1) : 0;
}
?>

<div style="display:grid;grid-template-columns:350px 1fr;gap:20px">


<!-- МЭДЭЭЛЭЛ CARD -->
<div style="display:flex; flex-direction:column; gap:20px;">
  <div class="card" style="position:relative; overflow:hidden;">
    <div style="height:100px; background: linear-gradient(135deg, var(--primary), #9333ea); opacity:0.8;"></div>
    <div class="card-body" style="text-align:center; padding-top:0;">
      <div style="width:100px; height:100px; border-radius:50%; background:#fff; border:4px solid #fff; box-shadow:0 4px 15px rgba(0,0,0,0.1); display:flex; align-items:center; justify-content:center; font-size:40px; color:var(--primary); margin:-50px auto 15px; position:relative;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <h3 style="margin-bottom:5px;"><?= h($student['full_name']) ?></h3>
      <p style="color:var(--muted); font-size:14px; margin-bottom:15px;"><?= h($student['class_name']) ?></p>
      <div style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;">
          <span class="badge <?= $student['is_active'] ? 'badge-success' : 'badge-danger' ?>">
            <?= $student['is_active'] ? 'Идэвхтэй' : 'Идэвхгүй' ?>
          </span>
          <span class="badge" style="background:#f59e0b; color:#fff;">Merit: <?= $student['merit_points'] ?? 0 ?></span>
      </div>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border); padding:20px;">
      <table style="width:100%; font-size:13px;">
        <tr><td style="color:var(--muted); padding:8px 0;">Нэвтрэх нэр</td><td><strong><?= h($student['username']) ?></strong></td></tr>
        <tr><td style="color:var(--muted); padding:8px 0;">Утас</td><td><?= h($student['user_phone'] ?: '-') ?></td></tr>
        <tr><td style="color:var(--muted); padding:8px 0;">Төрсөн</td><td><?= mnDate($student['birth_date']) ?></td></tr>
        <tr><td style="color:var(--muted); padding:8px 0;">Хаяг</td><td style="line-height:1.3;"><?= h($student['address'] ?: '-') ?></td></tr>
      </table>
    </div>
  </div>

  <!-- SPIDER CHART -->
  <div class="card" style="border:none; background: #1e293b; color:#fff;">
    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);"><h2 style="color:#fff; font-size:14px;"><i class="fas fa-chart-pie"></i> Ур чадварын тор</h2></div>
    <div class="card-body" style="padding:20px;">
        <canvas id="skillRadar" style="max-height:280px;"></canvas>
    </div>
  </div>
</div>

<!-- TABS -->
<div>
  <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn btn-primary tab-btn active" data-tab="tab-grades" onclick="showTab('tab-grades')"><i class="fas fa-star"></i> Дүн</button>
    <button class="btn btn-secondary tab-btn" data-tab="tab-att" onclick="showTab('tab-att')"><i class="fas fa-clipboard-check"></i> Ирц</button>
    <button class="btn btn-secondary tab-btn" data-tab="tab-pay" onclick="showTab('tab-pay')"><i class="fas fa-money-bill-wave"></i> Төлбөр</button>
  </div>

  <!-- ДҮН TAB -->
  <div id="tab-grades" class="tab-pane active">
    <div class="card">
      <div class="card-header">
        <h2>Дүнгийн бүртгэл</h2>
        <?php if(isTeacher() || isManager()): ?>
        <a href="/school_system1/pages/grades/index.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Дүн оруулах</a>
        <?php endif; ?>
      </div>
      <div class="card-body" style="padding:0">
        <table>
          <thead><tr><th>Хичээл</th><th>Төрөл</th><th>Дүн</th><th>Огноо</th></tr></thead>
          <tbody>
          <?php foreach($grades as $g): ?>
          <tr>
            <td><?= h($g['subject_name']) ?></td>
            <td><?= h($g['grade_type']) ?></td>
            <td>
              <strong style="color:<?= $g['grade_value']>=80?'var(--success)':($g['grade_value']>=60?'var(--warning)':'var(--danger)') ?>">
                <?= h($g['grade_value']) ?>
              </strong>
            </td>
            <td style="font-size:11px;color:var(--muted)"><?= mnDateTime($g['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$grades): ?><tr><td colspan="4" style="text-align:center;color:var(--muted)">Дүн байхгүй</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ИРЦИЙН TAB -->
  <div id="tab-att" class="tab-pane">
    <div class="card">
      <div class="card-header"><h2>Ирцийн нэгтгэл</h2></div>
      <div class="card-body" style="padding:0">
        <table>
          <thead><tr><th>Хичээл</th><th>Ирсэн</th><th>Тасалсан</th><th>Өвчтэй</th><th>Нийт</th><th>%</th></tr></thead>
          <tbody>
          <?php foreach($attSummary as $a): $pct = $a['total']>0?round($a['present']/$a['total']*100,1):0; ?>
          <tr>
            <td><?= h($a['subject_name']) ?></td>
            <td><span class="badge badge-success"><?= $a['present'] ?></span></td>
            <td><span class="badge badge-danger"><?= $a['absent'] ?></span></td>
            <td><span class="badge badge-warning"><?= $a['sick'] ?></span></td>
            <td><?= $a['total'] ?></td>
            <td><strong style="color:<?= $pct>=80?'var(--success)':'var(--danger)' ?>"><?= $pct ?>%</strong></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$attSummary): ?><tr><td colspan="6" style="text-align:center;color:var(--muted)">Ирцийн бүртгэл байхгүй</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ТӨЛБӨР TAB -->
  <div id="tab-pay" class="tab-pane">
    <div class="card">
      <div class="card-header"><h2>Төлбөрийн түүх</h2></div>
      <div class="card-body" style="padding:0">
        <table>
          <thead><tr><th>Баримтын №</th><th>Дүн</th><th>Арга</th><th>Төлсөн огноо</th><th>Хугацаа</th><th>Төлөв</th></tr></thead>
          <tbody>
          <?php foreach($payments as $p): ?>
          <tr>
            <td><?= h($p['receipt_no'] ?? '-') ?></td>
            <td><?= mnMoney($p['amount']) ?></td>
            <td><?= h($p['payment_method'] ?? '-') ?></td>
            <td><?= h($p['paid_date'] ?? '-') ?></td>
            <td><?= h($p['due_date']) ?></td>
            <td>
              <?php if($p['status']==='paid'): ?><span class="badge badge-success">Төлөгдсөн</span>
              <?php elseif($p['status']==='overdue'): ?><span class="badge badge-danger">Хоцорсон</span>
              <?php else: ?><span class="badge badge-warning">Хүлээгдэж буй</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$payments): ?><tr><td colspan="6" style="text-align:center;color:var(--muted)">Төлбөрийн бүртгэл байхгүй</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctxRadar = document.getElementById('skillRadar').getContext('2d');
new Chart(ctxRadar, {
    type: 'radar',
    data: {
        labels: <?= json_encode(array_keys($catScores)) ?>,
        datasets: [{
            label: 'Түвшин',
            data: <?= json_encode(array_values($catScores)) ?>,
            backgroundColor: 'rgba(99, 102, 241, 0.4)',
            borderColor: 'rgba(129, 140, 248, 1)',
            borderWidth: 2,
            pointBackgroundColor: '#fff',
            pointRadius: 3
        }]
    },
    options: {
        scales: {
            r: {
                angleLines: { color: 'rgba(255,255,255,0.1)' },
                grid: { color: 'rgba(255,255,255,0.1)' },
                pointLabels: { color: '#fff', font: { size: 10 } },
                suggestedMin: 0,
                suggestedMax: 100,
                ticks: { display: false, stepSize: 20 }
            }
        },
        plugins: { legend: { display: false } }
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

