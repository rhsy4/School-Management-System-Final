<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Зөвхөн эцэг эхийг зөвшөөрөх
requireRole(['parent']);

$pageTitle = 'Эцэг эхийн портал';

// Сурагчийы ID авах (активтай)
$childId = isset($_SESSION['active_child_id']) ? (int)$_SESSION['active_child_id'] : null;

// Энэ эцэг эхэд холбогдсон сурагчдыг авах
$children = dbQuery(
    "SELECT student_id, CONCAT(last_name, ' ', first_name) as full_name, parent_id 
     FROM students 
     WHERE parent_id IN (SELECT parent_id FROM parents WHERE user_id=?) 
     AND is_active=1",
    [$_SESSION['user_id']]
);

if (empty($children)) {
    setFlash('warning', 'Таних сурагч олдсонгүй');
    header('Location: /school_system1/dashboard.php');
    exit;
}

// Хэрэв active_child_id сет болоогүй бол эхний хүүхлээ сонго
if (!$childId) {
    $childId = $children[0]['student_id'];
    $_SESSION['active_child_id'] = $childId;
}

// Сонгосон сурагчийн мэдээлэл
$child = dbOne(
    "SELECT s.*, c.class_name, c.teacher_id, u.email, u.phone 
     FROM students s 
     JOIN classes c ON s.class_id=c.class_id 
     JOIN users u ON s.user_id=u.user_id 
     WHERE s.student_id=?",
    [$childId]
);

if (!$child) {
    setFlash('error', 'Сурагч олдсонгүй');
    header('Location: /school_system1/dashboard.php');
    exit;
}

// ── СУРАГЧИЙН ДҮНГҮҮД ──────────────────────────────────
$grades = dbQuery(
    "SELECT g.*, sub.subject_name 
     FROM grades g 
     JOIN subjects sub ON g.subject_id=sub.subject_id 
     WHERE g.student_id=? 
     ORDER BY g.created_at DESC LIMIT 20",
    [$childId]
);

// ── ИРЦИЙН МЭДЭЭЛЭЛ ────────────────────────────────────
$attendance = dbQuery(
    "SELECT 
        sub.subject_name,
        SUM(CASE WHEN a.status_id=1 THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status_id=2 THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status_id=3 THEN 1 ELSE 0 END) as sick,
        COUNT(*) as total
     FROM attendance a
     JOIN subjects sub ON a.subject_id=sub.subject_id
     WHERE a.student_id=?
     GROUP BY sub.subject_id",
    [$childId]
);

// ── ТӨЛБӨРИЙН ТҮҮХ ────────────────────────────────────
$payments = dbQuery(
    "SELECT * FROM tuition WHERE student_id=? ORDER BY created_at DESC LIMIT 10",
    [$childId]
);

// ── СУРГУУЛИЙН САРЛАГУУД ──────────────────────────────
$announcements = dbQuery(
    "SELECT * FROM announcements WHERE is_active=1 ORDER BY pinned DESC, created_at DESC LIMIT 5"
);

include __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
  <div><strong>Сурагч сонгох:</strong></div>
  <?php foreach($children as $c): ?>
  <form method="GET" style="display: inline;">
    <button type="submit" name="child_id" value="<?= $c['student_id'] ?>" 
            style="padding: 8px 15px; background: <?= $c['student_id'] == $childId ? 'var(--primary)' : 'var(--border)' ?>; 
            color: <?= $c['student_id'] == $childId ? 'white' : 'inherit' ?>; 
            border: none; border-radius: 4px; cursor: pointer;">
      <?= h($c['full_name']) ?>
    </button>
  </form>
  <?php endforeach; ?>
</div>

<!-- СУРАГЧИЙН МЭДЭЭЛЭЛ CARD -->
<div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px; margin-bottom: 30px;">
  <div class="card">
    <div class="card-body" style="text-align: center;">
      <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 15px;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <h3><?= h($child['full_name']) ?></h3>
      <p style="color: var(--muted); margin: 5px 0;"><?= h($child['class_name']) ?></p>
      <hr style="margin: 15px 0;">
      <table style="width: 100%; font-size: 13px; text-align: left;">
        <tr><td style="color: var(--muted);">Төрсөн</td><td><?= mnDate($child['birth_date']) ?></td></tr>
        <tr><td style="color: var(--muted);">Имэйл</td><td><?= h($child['email'] ?? '-') ?></td></tr>
        <tr><td style="color: var(--muted);">Утас</td><td><?= h($child['phone'] ?? '-') ?></td></tr>
        <tr><td style="color: var(--muted);">Огноо</td><td><?= mnDate($child['created_at']) ?></td></tr>
      </table>
      <?php if ($child['teacher_id']): ?>
      <a href="/school_system1/pages/messages/index.php?usr=<?= $child['teacher_id'] ?>" class="btn btn-primary" style="margin-top: 15px; width: 100%;"><i class="fas fa-comment-dots"></i> Багштай холбогдох</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- TABS -->
  <div>
    <div style="display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
      <button class="btn btn-sm btn-primary tab-btn active" data-tab="tab-grades" onclick="showTab('tab-grades')">
        <i class="fas fa-star"></i> Дүн
      </button>
      <button class="btn btn-sm btn-secondary tab-btn" data-tab="tab-attendance" onclick="showTab('tab-attendance')">
        <i class="fas fa-calendar-check"></i> Ирц
      </button>
      <button class="btn btn-sm btn-secondary tab-btn" data-tab="tab-payments" onclick="showTab('tab-payments')">
        <i class="fas fa-money-bill"></i> Төлбөр
      </button>
    </div>

    <!-- ДҮНГИЙН TAB -->
    <div id="tab-grades" class="tab-pane active">
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-star"></i> Сүүлийн 20 дүн</h3>
        </div>
        <div class="card-body" style="padding: 0;">
          <table>
            <thead><tr><th>Хичээл</th><th>Төрөл</th><th>Дүн</th><th>Огноо</th></tr></thead>
            <tbody>
            <?php foreach($grades as $g): ?>
            <tr>
              <td><?= h($g['subject_name']) ?></td>
              <td><?= h($g['grade_type']) ?></td>
              <td>
                <strong style="color: <?= $g['grade_value']>=80?'var(--success)':($g['grade_value']>=60?'var(--warning)':'var(--danger)') ?>">
                  <?= $g['grade_value'] ?>
                </strong>
              </td>
              <td><?= mnDateTime($g['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ИРЦИЙН TAB -->
    <div id="tab-attendance" class="tab-pane" style="display: none;">
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-calendar-check"></i> Ирцийн нэгтгэл</h3>
        </div>
        <div class="card-body" style="padding: 0;">
          <table>
            <thead><tr><th>Хичээл</th><th>Ирсэн</th><th>Ирээгүй</th><th>Өвчтэй</th><th>Нийт</th><th>Хувь</th></tr></thead>
            <tbody>
            <?php foreach($attendance as $att): 
              $total = $att['present'] + $att['absent'] + $att['sick'];
              $pct = $total > 0 ? round(($att['present'] / $total) * 100, 1) : 0;
            ?>
            <tr>
              <td><?= h($att['subject_name']) ?></td>
              <td style="color: var(--success)"><?= $att['present'] ?></td>
              <td style="color: var(--danger)"><?= $att['absent'] ?></td>
              <td style="color: var(--warning)"><?= $att['sick'] ?></td>
              <td><?= $att['total'] ?></td>
              <td><strong style="color: <?= $pct>=90?'var(--success)':($pct>=75?'var(--warning)':'var(--danger)') ?>"><?= $pct ?>%</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ТӨЛБӨРИЙН TAB -->
    <div id="tab-payments" class="tab-pane" style="display: none;">
      <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
          <h3 style="margin:0;"><i class="fas fa-money-bill"></i> Төлбөрийн түүх</h3>
          <a href="/school_system1/pages/payments/index.php" class="btn btn-sm btn-success"><i class="fas fa-credit-card"></i> Төлбөр төлөх</a>
        </div>
        <div class="card-body" style="padding: 0;">
          <table>
            <thead><tr><th>Дүн</th><th>Статус</th><th>Төрөл</th><th>Огноо</th></tr></thead>
            <tbody>
            <?php foreach($payments as $p): ?>
            <tr>
              <td><strong><?= mnMoney($p['amount']) ?></strong></td>
              <td>
                <span class="badge badge-<?= $p['status']=='paid'?'success':'danger' ?>">
                  <?= $p['status']=='paid'?'Төлөгдсөн':'Төлөгдөөгүй' ?>
                </span>
              </td>
              <td>
                <?= h($p['description'] ?? '-') ?>
                <?php if ($p['status'] === 'paid'): ?>
                <div style="margin-top:4px;"><a href="#" style="font-size:11px; color:var(--primary); text-decoration:underline;"><i class="fas fa-download"></i> Баримт татах</a></div>
                <?php endif; ?>
              </td>
              <td><?= mnDateTime($p['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- СУРГУУЛИЙН САРЛАГУУД -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-bullhorn"></i> Сургуулийн сарлагууд</h2>
  </div>
  <div class="card-body">
    <?php foreach($announcements as $ann): ?>
    <div style="border: 1px solid var(--border); padding: 15px; border-radius: 8px; margin-bottom: 10px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 5px 0;"><?= h($ann['title']) ?></h4>
          <p style="margin: 5px 0; font-size: 13px; color: var(--muted);"><?= mnDateTime($ann['created_at']) ?></p>
        </div>
        <?php if($ann['pinned']): ?>
        <span class="badge badge-info" style="margin-left: 10px;"><i class="fas fa-thumbtack"></i> Сүүдэр</span>
        <?php endif; ?>
      </div>
      <p style="margin: 10px 0 0 0; line-height: 1.6;"><?= h($ann['content']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function showTab(tabId) {
  // Бүх tab-ыг нуух
  document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  
  // Сонгосон tab-ыг харуулах
  document.getElementById(tabId).style.display = 'block';
  event.target.closest('.tab-btn').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
