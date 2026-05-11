<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Админ самбар';

// Статистик үзүүлэлтүүд
$stats = [
    'users'    => dbOne("SELECT COUNT(*) AS c FROM users")['c'],
    'students' => dbOne("SELECT COUNT(*) AS c FROM students WHERE is_active=1")['c'],
    'teachers' => dbOne("SELECT COUNT(*) AS c FROM teachers")['c'],
    'classes'  => dbOne("SELECT COUNT(*) AS c FROM classes")['c'],
    'grades'   => dbOne("SELECT COUNT(*) AS c FROM grades")['c'],
    'logs'     => dbOne("SELECT COUNT(*) AS c FROM audit_log")['c'],
];
$roles = dbQuery("SELECT r.*, COUNT(u.user_id) AS user_count FROM user_roles r LEFT JOIN users u ON r.role_id=u.role_id GROUP BY r.role_id ORDER BY r.role_id");
$recentLogs = dbQuery("SELECT al.*, u.full_name, u.username FROM audit_log al LEFT JOIN users u ON al.user_id=u.user_id ORDER BY al.created_at DESC LIMIT 20");

include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['users'] ?></div><div class="stat-label">Нийт хэрэглэгч</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['students'] ?></div><div class="stat-label">Сурагч</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['teachers'] ?></div><div class="stat-label">Багш</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-chalkboard"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['classes'] ?></div><div class="stat-label">Анги</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-star"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['grades'] ?></div><div class="stat-label">Дүн</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-history"></i></div><div class="stat-info"><div class="stat-value"><?= $stats['logs'] ?></div><div class="stat-label">Audit лог</div></div></div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px">

<!-- Эрхийн төрөл -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-shield-alt"></i> Эрхийн төрөл</h2></div>
  <div class="card-body">
    <?php foreach($roles as $r): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
      <span class="badge badge-<?= h($r['role_name']) ?>" style="font-size:13px"><?= h($r['role_name']) ?></span>
      <strong><?= $r['user_count'] ?> хэрэглэгч</strong>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:16px">
      <h4 style="margin-bottom:8px;font-size:13px;color:var(--muted)">Шуурхай холбоос</h4>
      <a href="/school_system1/pages/users/index.php" class="btn btn-secondary" style="width:100%;justify-content:flex-start;margin-bottom:6px"><i class="fas fa-users-cog"></i> Хэрэглэгчид удирдах</a>
      <a href="/school_system1/admin/logs.php" class="btn btn-secondary" style="width:100%;justify-content:flex-start;margin-bottom:6px"><i class="fas fa-history"></i> Audit лог</a>
      <a href="/school_system1/pages/reports/index.php" class="btn btn-secondary" style="width:100%;justify-content:flex-start"><i class="fas fa-chart-bar"></i> Тайлан</a>
    </div>
  </div>
</div>

<!-- СҮҮЛИЙН ACTIVITY -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-history"></i> Audit лог (сүүлийн 20)</h2>
    <a href="/school_system1/admin/logs.php" class="btn btn-sm btn-secondary">Бүгдийг харах</a>
  </div>
  <div class="card-body" style="padding:0">
    <table>
      <thead><tr><th>Хэрэглэгч</th><th>Үйлдэл</th><th>IP</th><th>Цаг</th></tr></thead>
      <tbody>
      <?php foreach($recentLogs as $log): ?>
      <tr>
        <td>
          <div><?= h($log['full_name'] ?? 'Систем') ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= h($log['username'] ?? '') ?></div>
        </td>
        <td>
          <span class="badge badge-info"><?= h($log['action']) ?></span>
          <?php if($log['detail']): ?><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= h($log['detail']) ?></div><?php endif; ?>
        </td>
        <td style="font-size:11px;color:var(--muted)"><?= h($log['ip_address'] ?? '-') ?></td>
        <td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= mnDateTime($log['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
