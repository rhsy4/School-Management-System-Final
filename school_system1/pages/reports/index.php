<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin','manager','director']);
$pageTitle = 'Тайлан';

$classes = dbQuery("SELECT * FROM classes ORDER BY class_name");
$classFilter = (int)($_GET['class_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');

// Дундаж дүнгийн тайлан
$gradeParams = [];
$gradeWhere = "WHERE 1=1";
if ($classFilter) {
    $gradeWhere .= " AND s.class_id = ?";
    $gradeParams[] = $classFilter;
}

$gradeReport = dbQuery(
    "SELECT CONCAT(s.last_name,' ',s.first_name) as student_name, c.class_name, sub.subject_name,
            ROUND(AVG(g.grade_value),1) as avg_grade,
            MAX(g.grade_value) as max_grade,
            MIN(g.grade_value) as min_grade,
            COUNT(g.grade_id) as total_records
     FROM grades g
     JOIN students s ON g.student_id=s.student_id
     JOIN classes c ON s.class_id=c.class_id
     JOIN subjects sub ON g.subject_id=sub.subject_id
     $gradeWhere
     GROUP BY s.student_id, sub.subject_id
     ORDER BY c.class_name, student_name",
    $gradeParams
);

// Ирцийн тайлан
$attParams = [];
$attWhere = ""; // WHERE logic already has handled month
if ($classFilter) {
    $attWhere .= " AND s.class_id = ?";
    $attParams[] = $classFilter;
}

$attReport = dbQuery(
    "SELECT CONCAT(s.last_name,' ',s.first_name) as student_name, c.class_name,
            SUM(CASE WHEN a.status_id=1 THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status_id=2 THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status_id=3 THEN 1 ELSE 0 END) as sick,
            COUNT(a.attendance_id) as total,
            ROUND(SUM(CASE WHEN a.status_id=1 THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(a.attendance_id),0),1) as pct
     FROM attendance a
     JOIN students s ON a.student_id=s.student_id
     JOIN classes c ON s.class_id=c.class_id
     WHERE DATE_FORMAT(a.date, '%Y-%m')=?
     $attWhere
     GROUP BY s.student_id
     ORDER BY c.class_name, student_name",
    array_merge([$month], $attParams)
);

// Санхүүгийн тайлан
$finParams = [];
$finWhere = "WHERE 1=1";
if ($classFilter) {
    $finWhere .= " AND c.class_id = ?";
    $finParams[] = $classFilter;
}

$finReport = dbQuery(
    "SELECT c.class_name,
            SUM(CASE WHEN t.status='paid' THEN t.amount ELSE 0 END) as paid,
            SUM(CASE WHEN t.status!='paid' THEN t.amount ELSE 0 END) as unpaid,
            COUNT(t.tuition_id) as total_records
     FROM tuition t
     JOIN students s ON t.student_id=s.student_id
     JOIN classes c ON s.class_id=c.class_id
     $finWhere
     GROUP BY c.class_id
     ORDER BY c.class_name",
    $finParams
);

$consParams = [];
$consWhere = "WHERE 1=1";
if ($classFilter) {
    $consWhere .= " AND s.class_id = ?";
    $consParams[] = $classFilter;
}
$consolidatedReport = dbQuery(
    "SELECT s.student_id, CONCAT(s.last_name,' ',s.first_name) as student_name, c.class_name,
            (SELECT ROUND(AVG(grade_value),1) FROM grades WHERE student_id=s.student_id) as avg_grade,
            (SELECT SUM(CASE WHEN status_id=1 THEN 1 ELSE 0 END) FROM attendance WHERE student_id=s.student_id AND DATE_FORMAT(date, '%Y-%m')=?) as present_days,
            (SELECT SUM(CASE WHEN status_id=2 THEN 1 ELSE 0 END) FROM attendance WHERE student_id=s.student_id AND DATE_FORMAT(date, '%Y-%m')=?) as absent_days,
            (SELECT SUM(amount) FROM tuition WHERE student_id=s.student_id AND status='paid') as total_paid,
            (SELECT SUM(amount) FROM tuition WHERE student_id=s.student_id AND status!='paid') as total_unpaid
     FROM students s
     JOIN classes c ON s.class_id=c.class_id
     $consWhere
     ORDER BY c.class_name, student_name",
    array_merge([$month, $month], $consParams)
);

include __DIR__ . '/../../includes/header.php';
?>

<!-- FILTER -->
<div class="card">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="class_id" class="form-control">
        <option value="">Бүх анги</option>
        <?php foreach($classes as $c): ?><option value="<?= $c['class_id'] ?>" <?= $classFilter==$c['class_id']?'selected':'' ?>><?= h($c['class_name']) ?></option><?php endforeach; ?>
      </select>
      <input type="month" name="month" class="form-control" value="<?= h($month) ?>">
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Шүүх</button>
      <a href="index.php" class="btn btn-secondary">Арилгах</a>
    </form>
  </div>
</div>

<!-- TABS -->
<div style="display:flex;gap:8px;margin-bottom:16px">
  <button class="btn btn-primary tab-btn active" data-tab="tab-grades" onclick="showTab('tab-grades')"><i class="fas fa-star"></i> Дүн</button>
  <button class="btn btn-secondary tab-btn" data-tab="tab-att" onclick="showTab('tab-att')"><i class="fas fa-clipboard-check"></i> Ирц</button>
  <button class="btn btn-secondary tab-btn" data-tab="tab-fin" onclick="showTab('tab-fin')"><i class="fas fa-money-bill-wave"></i> Санхүү</button>
  <button class="btn btn-warning tab-btn" data-tab="tab-cons" onclick="showTab('tab-cons')"><i class="fas fa-layer-group"></i> Нэгтгэл</button>
</div>

<!-- ДҮН -->
<div id="tab-grades" class="tab-pane active">
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-star"></i> Дүнгийн тайлан</h2>
      <button class="btn btn-sm btn-success" onclick="exportTableToExcel('gradesTable', 'Dungiin_Tailan')"><i class="fas fa-file-excel"></i> Excel татах</button>
      <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Хэвлэх</button>
    </div>
    <div class="card-body" style="padding:0">
      <table id="gradesTable">
        <thead><tr><th>Сурагч</th><th>Анги</th><th>Хичээл</th><th>Дундаж</th><th>Хамгийн өндөр</th><th>Хамгийн бага</th><th>Тооцоолол</th></tr></thead>
        <tbody>
        <?php foreach($gradeReport as $g): ?>
        <tr>
          <td><?= h($g['student_name']) ?></td>
          <td><?= h($g['class_name']) ?></td>
          <td><?= h($g['subject_name']) ?></td>
          <td><strong style="font-size:16px;color:<?= $g['avg_grade']>=80?'var(--success)':($g['avg_grade']>=60?'var(--warning)':'var(--danger)') ?>"><?= $g['avg_grade'] ?></strong></td>
          <td><?= $g['max_grade'] ?></td>
          <td><?= $g['min_grade'] ?></td>
          <td><?= $g['total_records'] ?> бүртгэл</td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$gradeReport): ?><tr><td colspan="7" style="text-align:center;color:var(--muted)">Мэдээлэл байхгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ИРЦИЙН ТАЙЛАН -->
<div id="tab-att" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-clipboard-check"></i> Ирцийн тайлан — <?= h($month) ?></h2>
      <button class="btn btn-sm btn-success" onclick="exportTableToExcel('attTable', 'Irtsiin_Tailan_<?= $month ?>')"><i class="fas fa-file-excel"></i> Excel татах</button>
    </div>
    <div class="card-body" style="padding:0">
      <table id="attTable">
        <thead><tr><th>Сурагч</th><th>Анги</th><th>Ирсэн</th><th>Тасалсан</th><th>Өвчтэй</th><th>Нийт</th><th>%</th></tr></thead>
        <tbody>
        <?php foreach($attReport as $a): ?>
        <tr>
          <td><?= h($a['student_name']) ?></td>
          <td><?= h($a['class_name']) ?></td>
          <td><span class="badge badge-success"><?= $a['present'] ?></span></td>
          <td><span class="badge badge-danger"><?= $a['absent'] ?></span></td>
          <td><span class="badge badge-warning"><?= $a['sick'] ?></span></td>
          <td><?= $a['total'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:80px;height:8px;background:#eee;border-radius:4px">
                <div style="width:<?= $a['pct'] ?>%;height:100%;background:<?= $a['pct']>=80?'var(--success)':'var(--danger)' ?>;border-radius:4px"></div>
              </div>
              <strong style="color:<?= $a['pct']>=80?'var(--success)':'var(--danger)' ?>"><?= $a['pct'] ?>%</strong>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$attReport): ?><tr><td colspan="7" style="text-align:center;color:var(--muted)">Мэдээлэл байхгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- САНХҮҮГИЙН ТАЙЛАН -->
<div id="tab-fin" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-money-bill-wave"></i> Санхүүгийн тайлан</h2>
      <button class="btn btn-sm btn-success" onclick="exportTableToExcel('finTable', 'Sanhuu_Tailan')"><i class="fas fa-file-excel"></i> Excel татах</button>
    </div>
    <div class="card-body" style="padding:0">
      <table id="finTable">
        <thead><tr><th>Анги</th><th>Орлого</th><th>Хүлээгдэж буй</th><th>Нийт бүртгэл</th><th>Гүйцэтгэлийн хувь</th></tr></thead>
        <tbody>
        <?php foreach($finReport as $f): $total=$f['paid']+$f['unpaid']; $pct=$total>0?round($f['paid']/$total*100,1):0; ?>
        <tr>
          <td><strong><?= h($f['class_name']) ?></strong></td>
          <td style="color:var(--success);font-weight:600"><?= mnMoney($f['paid']) ?></td>
          <td style="color:var(--danger)"><?= mnMoney($f['unpaid']) ?></td>
          <td><?= $f['total_records'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:80px;height:8px;background:#eee;border-radius:4px">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=80?'var(--success)':'var(--warning)' ?>;border-radius:4px"></div>
              </div>
              <strong><?= $pct ?>%</strong>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$finReport): ?><tr><td colspan="5" style="text-align:center;color:var(--muted)">Мэдээлэл байхгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- НЭГТГЭСЭН ТАЙЛАН -->
<div id="tab-cons" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-layer-group"></i> Нэгтгэсэн тайлан (<?= h($month) ?>)</h2>
      <div>
        <button class="btn btn-sm btn-success" onclick="exportTableToExcel('consTable', 'Negtgesen_Tailan_<?= $month ?>')"><i class="fas fa-file-excel"></i> Excel татах</button>
        <button class="btn btn-sm btn-danger" onclick="downloadPdf('tab-cons', 'Negtgel_<?= h($month) ?>.pdf')"><i class="fas fa-file-pdf"></i> PDF татах</button>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <table id="consTable">
        <thead><tr><th>Сурагч</th><th>Анги</th><th>Дундаж дүн</th><th>Ирсэн</th><th>Тасалсан</th><th>Төлсөн (₮)</th><th>Төлөөгүй (₮)</th></tr></thead>
        <tbody>
        <?php foreach($consolidatedReport as $r): ?>
        <tr>
          <td><strong><?= h($r['student_name']) ?></strong></td>
          <td><?= h($r['class_name']) ?></td>
          <td><strong style="color:<?= $r['avg_grade']>=80?'var(--success)':($r['avg_grade']? 'var(--warning)' : 'var(--muted)') ?>"><?= $r['avg_grade'] ?: '-' ?></strong></td>
          <td><?= (int)$r['present_days'] ?></td>
          <td><span style="color:<?= $r['absent_days']>0?'var(--danger)':'inherit' ?>"><?= (int)$r['absent_days'] ?></span></td>
          <td style="color:var(--success)"><?= mnMoney($r['total_paid'] ?: 0) ?></td>
          <td style="color:var(--danger)"><?= mnMoney($r['total_unpaid'] ?: 0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$consolidatedReport): ?><tr><td colspan="7" style="text-align:center;color:var(--muted)">Мэдээлэл байхгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>.tab-pane{display:none}.tab-pane.active{display:block}</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPdf(elementId, filename) {
  const element = document.getElementById(elementId);
  const buttons = element.querySelectorAll('button');
  buttons.forEach(b => b.style.display = 'none'); // PDF-д товчууд хэрэггүй
  
  const opt = {
    margin:       0.5,
    filename:     filename,
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2 },
    jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
  };
  
  html2pdf().set(opt).from(element).save().then(() => {
    buttons.forEach(b => b.style.display = 'inline-block');
  });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

