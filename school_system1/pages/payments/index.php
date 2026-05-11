<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// ── Нэвтрэлт шалгах ───────────────────────────────────────────
requireLogin();
$isAdminOrManager = isAdmin() || isManager();
$isStudent  = isStudent();
$isParent   = ($_SESSION['role'] === 'parent');
$isStudentOrParent = $isStudent || $isParent;

// Хандах эрх шалгах
if (!$isAdminOrManager && !$isStudent && !$isParent) {
    header('Location: /school_system1/dashboard.php'); exit;
}

// Сурагч: өөрийн student_id
$myStudentId  = null;
// Эцэг эх: хүүхдийн student_id жагсаалт
$myChildIds   = [];

if ($isStudent) {
    $me = dbOne("SELECT student_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    if (!$me) { header('Location: /school_system1/dashboard.php'); exit; }
    $myStudentId = (int)$me['student_id'];
}
if ($isParent) {
    if (isset($_SESSION['active_child_id'])) {
        $myChildIds = [$_SESSION['active_child_id']];
    }
    if (empty($myChildIds)) { header('Location: /school_system1/dashboard.php'); exit; }
}

$pageTitle = 'Төлбөрийн мэдээлэл';

// ── POST: Төлбөр бүртгэх (зөвхөн admin/manager) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!$isAdminOrManager) { http_response_code(403); die('Хандах эрхгүй'); }
    verifyCsrf();
    $studentId = (int)$_POST['student_id'];
    $amount    = (float)$_POST['amount'];
    $method    = trim($_POST['payment_method'] ?? '');
    $dueDate   = $_POST['due_date'] ?? '';
    $status    = $_POST['status'] ?? 'paid';
    if (!$studentId || !$amount || !$dueDate) {
        setFlash('error','Бүх шаардлагатай талбаруудыг бөглөнө үү!');
    } else {
        $receipt = 'RCP-' . date('Y') . '-' . strtoupper(substr(uniqid(),5,6));
        $paid    = $status === 'paid' ? date('Y-m-d') : null;
        $tid = dbExec(
            "INSERT INTO tuition (student_id,amount,payment_method,paid_date,due_date,status,receipt_no,recorded_by) VALUES (?,?,?,?,?,?,?,?)",
            [$studentId, $amount, $method ?: null, $paid, $dueDate, $status, $receipt, $_SESSION['user_id']]
        );
        auditLog('payment_recorded', $tid, "₮$amount төлбөр бүртгэгдлээ");
        setFlash('success', "Төлбөр бүртгэгдлээ! Баримт: $receipt");
    }
    header('Location: /school_system1/pages/payments/index.php'); exit;
}

// ── POST: Төлөв өөрчлөх (зөвхөн admin/manager) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!$isAdminOrManager) { http_response_code(403); die('Хандах эрхгүй'); }
    verifyCsrf();
    $tid    = (int)$_POST['tuition_id'];
    $status = $_POST['status'] ?? 'paid';
    $paid   = $status === 'paid' ? date('Y-m-d') : null;
    dbUpdate("UPDATE tuition SET status=?,paid_date=? WHERE tuition_id=?", [$status, $paid, $tid]);
    auditLog('payment_status_changed', $tid, "Төлвийг $status болгов");
    setFlash('success','Төлбөрийн төлөв шинэчлэгдлээ!');
    header('Location: /school_system1/pages/payments/index.php'); exit;
}

// ── Нэгтгэл ───────────────────────────────────────────────────
if ($isAdminOrManager) {
    $summary = dbOne("SELECT
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS collected,
        SUM(CASE WHEN status='unpaid' OR status='overdue' THEN amount ELSE 0 END) AS pending,
        COUNT(CASE WHEN status='paid' THEN 1 END) AS paid_cnt,
        COUNT(CASE WHEN status IN ('unpaid', 'overdue') THEN 1 END) AS pending_cnt,
        COUNT(*) AS total FROM tuition");
} elseif ($isStudent) {
    $summary = dbOne("SELECT
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS collected,
        SUM(CASE WHEN status='unpaid' OR status='overdue' THEN amount ELSE 0 END) AS pending,
        COUNT(CASE WHEN status='paid' THEN 1 END) AS paid_cnt,
        COUNT(CASE WHEN status IN ('unpaid', 'overdue') THEN 1 END) AS pending_cnt,
        COUNT(*) AS total FROM tuition WHERE student_id=?", [$myStudentId]);
} else {
    // Эцэг эх — хүүхдийнх
    $inList = implode(',', $myChildIds);
    $summary = dbOne("SELECT
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS collected,
        SUM(CASE WHEN status='unpaid' OR status='overdue' THEN amount ELSE 0 END) AS pending,
        COUNT(CASE WHEN status='paid' THEN 1 END) AS paid_cnt,
        COUNT(CASE WHEN status IN ('unpaid', 'overdue') THEN 1 END) AS pending_cnt,
        COUNT(*) AS total FROM tuition WHERE student_id IN ($inList)");
}

// ── Жагсаалт ─────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$classFilter  = (int)($_GET['class_id'] ?? 0);
$search       = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
        FROM tuition t
        JOIN students s ON t.student_id=s.student_id
        JOIN classes c ON s.class_id=c.class_id
        WHERE 1=1";

// ★ Role-based шүүлт
if ($isStudent) {
    $sql .= " AND t.student_id=?";
    $params[] = $myStudentId;
} elseif ($isParent) {
    $inList = implode(',', $myChildIds);
    $sql .= " AND t.student_id IN ($inList)";
}
if ($statusFilter) { $sql .= " AND t.status=?"; $params[] = $statusFilter; }
if ($classFilter && $isAdminOrManager) { $sql .= " AND s.class_id=?"; $params[] = $classFilter; }
if ($search) {
    $sql .= " AND (s.last_name LIKE ? OR s.first_name LIKE ? OR c.class_name LIKE ? OR t.receipt_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY t.created_at DESC";

// ── EXCEL EXPORT (бүх бичлэг) ───────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $payments = dbQuery($sql, $params);
    $filename = "payments_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['#', 'Сурагч', 'Анги', 'Дүн', 'Арга', 'Баримт №', 'Хугацаа', 'Огноо', 'Төлөв']);
    foreach ($payments as $i => $p) {
        fputcsv($output, [
            $i+1,
            $p['student_name'],
            $p['class_name'],
            $p['amount'],
            $p['payment_method'] ?? '-',
            $p['receipt_no'] ?? '-',
            $p['due_date'],
            $p['paid_date'] ?? '-',
            $p['status']
        ]);
    }
    fclose($output);
    exit;
}

// ── Pagination ───────────────────────────────────────────────
$countSql = preg_replace('/SELECT .+ FROM/', 'SELECT COUNT(*) AS cnt FROM', $sql, 1);
$countSql = preg_replace('/ORDER BY .+$/', '', $countSql);
$totalCount = (int)(dbOne($countSql, $params)['cnt'] ?? 0);
$pag = paginate($totalCount, (int)($_GET['page'] ?? 1), 20);
$sql .= " LIMIT {$pag['offset']}, {$pag['perPage']}";
$payments = dbQuery($sql, $params);



$students = $isAdminOrManager
    ? dbQuery("SELECT s.student_id, s.class_id, c.class_name, CONCAT(s.last_name,' ',s.first_name) AS full_name,
               (SELECT COALESCE(SUM(amount), 0) FROM tuition WHERE student_id = s.student_id AND status IN ('unpaid', 'overdue')) as pending_balance
               FROM students s 
               JOIN classes c ON s.class_id = c.class_id
               WHERE s.is_active=1 ORDER BY s.last_name")
    : [];
$classes = $isAdminOrManager
    ? dbQuery("SELECT * FROM classes ORDER BY class_name")
    : [];

include __DIR__ . '/../../includes/header.php';
?>

<!-- НЭГТГЭЛ -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= mnMoney($summary['collected'] ?? 0) ?></div>
      <div class="stat-label">Төлөгдсөн (<?= $summary['paid_cnt'] ?? 0 ?> төлбөр)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= mnMoney($summary['pending'] ?? 0) ?></div>
      <div class="stat-label">Хүлээгдэж буй (<?= $summary['pending_cnt'] ?? 0 ?> төлбөр)</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-money-bill-wave"></i>
      <?php
        if ($isStudent) echo 'Миний төлбөрийн мэдээлэл';
        elseif ($isParent) echo 'Хүүхдийн төлбөрийн мэдээлэл';
        else echo 'Төлбөрийн бүртгэл';
      ?>
    </h2>
    <div style="display:flex; gap:8px;">
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'excel'])) ?>" class="btn btn-sm btn-secondary" style="background:#27ae60; color:white; border:none;"><i class="fas fa-file-excel"></i> Excel татах</a>
        <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> PDF/Хэвлэх</button>
        <?php if ($isAdminOrManager): ?>
        <button class="btn btn-primary" onclick="openModal('modalCreate')">
          <i class="fas fa-plus"></i> Төлбөр бүртгэх
        </button>
        <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <!-- Filter bar — зөвхөн admin/manager -->
    <?php if ($isAdminOrManager): ?>
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="Сурагч, анги, баримт хайх..." value="<?= h($search) ?>" style="width:250px">
      <select name="class_id" class="form-control">
        <option value="">Бүх анги</option>
        <?php foreach($classes as $c): ?>
        <option value="<?= $c['class_id'] ?>" <?= $classFilter==$c['class_id']?'selected':'' ?>>
          <?= h($c['class_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control">
        <option value="">Бүх төлөв</option>
        <option value="paid"    <?= $statusFilter==='paid'   ?'selected':'' ?>>Төлөгдсөн</option>
        <option value="unpaid"  <?= $statusFilter==='unpaid' ?'selected':'' ?>>Хүлээгдэж буй</option>
        <option value="overdue" <?= $statusFilter==='overdue'?'selected':'' ?>>Хоцорсон</option>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
      <a href="index.php" class="btn btn-secondary">Арилгах</a>
    </form>
    <?php else: ?>
    <!-- Сурагч: зөвхөн төлөв шүүлт -->
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="Баримт № хайх..." value="<?= h($search) ?>" style="width:200px">
      <select name="status" class="form-control">
        <option value="">Бүх төлөв</option>
        <option value="paid"    <?= $statusFilter==='paid'   ?'selected':'' ?>>Төлөгдсөн</option>
        <option value="unpaid"  <?= $statusFilter==='unpaid' ?'selected':'' ?>>Хүлээгдэж буй</option>
        <option value="overdue" <?= $statusFilter==='overdue'?'selected':'' ?>>Хоцорсон</option>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
      <a href="index.php" class="btn btn-secondary">Арилгах</a>
    </form>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <?php if ($isAdminOrManager || $isParent): ?>
            <th>Сурагч</th>
            <th>Анги</th>
            <?php endif; ?>
            <th>Дүн</th>
            <th>Арга</th>
            <th>Баримт №</th>
            <th>Хугацаа</th>
            <th>Төлөгдсөн огноо</th>
            <th>Төлөв</th>
            <?php if ($isAdminOrManager): ?><th>Үйлдэл</th>
            <?php elseif ($isStudentOrParent): ?><th>Төлөх</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach($payments as $i => $p): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <?php if ($isAdminOrManager || $isParent): ?>
          <td><strong><?= h($p['student_name']) ?></strong></td>
          <td><?= h($p['class_name']) ?></td>
          <?php endif; ?>
          <td><strong><?= mnMoney($p['amount']) ?></strong></td>
          <td><?= h($p['payment_method'] ?? '-') ?></td>
          <td style="font-size:11px"><?= h($p['receipt_no'] ?? '-') ?></td>
          <td style="font-size:12px"><?= h($p['due_date']) ?></td>
          <td style="font-size:12px"><?= $p['paid_date'] ? h($p['paid_date']) : '-' ?></td>
          <td>
            <?php if($p['status']==='paid'): ?>
              <span class="badge badge-success">Төлөгдсөн</span>
            <?php elseif($p['status']==='overdue'): ?>
              <span class="badge badge-danger">Хоцорсон</span>
            <?php else: ?>
              <span class="badge badge-warning">Хүлээгдэж буй</span>
            <?php endif; ?>
          </td>
          <?php if ($isAdminOrManager): ?>
          <td>
            <?php if($p['status'] !== 'paid'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="tuition_id" value="<?= $p['tuition_id'] ?>">
              <input type="hidden" name="status" value="paid">
              <button class="btn btn-sm btn-success" data-confirm="Төлөгдсөн гэж тэмдэглэх үү?">
                <i class="fas fa-check"></i> Төлөгдсөн
              </button>
            </form>
            <?php endif; ?>
          </td>
          <?php elseif ($isStudentOrParent): ?>
          <td>
            <?php if($p['status'] !== 'paid'): ?>
            <a href="/school_system1/pages/payments/pay.php?id=<?= $p['tuition_id'] ?>"
               class="btn btn-sm btn-primary">
              <i class="fas fa-credit-card"></i> Төлөх
            </a>
            <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:5px;">
                <span style="color:var(--success);font-size:12px"><i class="fas fa-check-circle"></i> Төлөгдсөн</span>
                <a href="/school_system1/pages/payments/receipt.php?id=<?= $p['tuition_id'] ?>" target="_blank" class="btn btn-sm" style="background:var(--bg); border:1px solid var(--border);">
                    <i class="fas fa-print"></i> Баримт
                </a>
            </div>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if(!$payments): ?>
        <tr>
          <td colspan="<?= $isAdminOrManager ? 10 : 7 ?>" style="text-align:center;color:var(--muted)">
            <?= $isStudent ? 'Таны төлбөрийн мэдээлэл байхгүй байна' : 'Мэдээлэл байхгүй' ?>
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php include __DIR__ . '/../../includes/pagination.php'; ?>

  </div>
</div>

<!-- НЭМЭХ MODAL — зөвхөн admin/manager -->
<?php if ($isAdminOrManager): ?>
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fas fa-plus"></i> Төлбөр бүртгэх</h3>
      <button class="modal-close" onclick="closeModal('modalCreate')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <style>
            .student-result-item { padding: 10px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.2s; }
            .student-result-item:hover { background: var(--hover-row); }
            .student-result-item:last-child { border-bottom: none; }
          </style>
          <div class="form-group" style="position:relative;"><label>Сурагчийн нэр эсвэл ангиар хайх *</label>
            <input type="text" id="smart_student_search" class="form-control" placeholder="Жишээ: 10а Бат..." onkeyup="smartFilterStudents()" autocomplete="off">
            <input type="hidden" name="student_id" id="modal_student_id_hidden" required>
            <div id="student_results_dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; z-index:1000; max-height:200px; overflow-y:auto; box-shadow:var(--shadow); backdrop-filter:blur(15px); -webkit-backdrop-filter:blur(15px);">
            </div>
            <div id="selected_student_display" style="margin-top:5px; font-weight:600; color:var(--primary); display:none;">
            </div>
          </div>
          <div class="form-group"><label>Дүн (₮) *</label>
            <input type="number" name="amount" id="modal_payment_amount" class="form-control" min="0" step="1000" placeholder="250000" required>
          </div>
          <div class="form-group"><label>Төлбөрийн арга</label>
            <select name="payment_method" class="form-control">
              <option value="">Сонгох</option>
              <option value="cash">Бэлэн мөнгө</option>
              <option value="bank">Банкны шилжүүлэг</option>
              <option value="qr">QR код</option>
              <option value="card">Карт</option>
            </select>
          </div>
          <div class="form-group"><label>Хугацаа *</label>
            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group"><label>Төлөв</label>
            <select name="status" class="form-control">
              <option value="paid">Төлөгдсөн</option>
              <option value="unpaid">Хүлээгдэж буй</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Бүртгэх</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const ALL_STUDENTS = <?= json_encode($students) ?>;

function smartFilterStudents() {
    const search = document.getElementById('smart_student_search').value.toLowerCase();
    const dropdown = document.getElementById('student_results_dropdown');
    
    if (search.length < 1) {
        dropdown.style.display = 'none';
        return;
    }
    
    const filtered = ALL_STUDENTS.filter(s => {
        const fullName = s.full_name.toLowerCase();
        const className = s.class_name.toLowerCase();
        return fullName.includes(search) || className.includes(search);
    }).slice(0, 15); // Хамгийн ихдээ 15 үр дүн харуулах
    
    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding:10px; color:var(--muted); text-align:center;">Сурагч олдсонгүй</div>';
        dropdown.style.display = 'block';
        return;
    }
    
    dropdown.innerHTML = filtered.map(s => `
        <div class="student-result-item" onclick="selectSmartStudent(${s.student_id}, '${s.full_name.replace(/'/g, "\\'")}', '${s.class_name}')">
            <div style="font-weight:600; color:var(--text);">${s.full_name}</div>
            <div style="font-size:11px; color:var(--muted);">${s.class_name}</div>
        </div>
    `).join('');
    
    dropdown.style.display = 'block';
}

function selectSmartStudent(id, name, className) {
    document.getElementById('modal_student_id_hidden').value = id;
    document.getElementById('smart_student_search').value = "";
    document.getElementById('student_results_dropdown').style.display = 'none';
    
    // Find mapping student to get balance
    const student = ALL_STUDENTS.find(s => s.student_id == id);
    if (student) {
        document.getElementById('modal_payment_amount').value = student.pending_balance;
    }
    
    const display = document.getElementById('selected_student_display');
    display.innerHTML = `<i class="fas fa-user-check"></i> Сонгогдсон: ${name} (${className})<br><small style="color:var(--muted)">Үлдэгдэл төлбөр: ₮${Number(student.pending_balance).toLocaleString()}</small>`;
    display.style.display = 'block';
}

// Click outside to close dropdown
document.addEventListener('click', function(e) {
    if (!e.target.closest('#smart_student_search')) {
        document.getElementById('student_results_dropdown').style.display = 'none';
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

