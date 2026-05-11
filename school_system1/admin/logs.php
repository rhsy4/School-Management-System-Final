<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Audit лог';

$search  = $_GET['search'] ?? '';
$action  = $_GET['action_f'] ?? '';
$params  = [];
$sql = "SELECT al.*, u.full_name, u.username FROM audit_log al LEFT JOIN users u ON al.user_id=u.user_id WHERE 1=1";
if ($search) { $sql .= " AND (u.full_name LIKE ? OR al.detail LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($action) { $sql .= " AND al.action=?"; $params[] = $action; }
$sql .= " ORDER BY al.created_at DESC LIMIT 200";
$logs = dbQuery($sql, $params);
$actions = dbQuery("SELECT DISTINCT action FROM audit_log ORDER BY action");

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-history"></i> Системийн Audit лог</h2>
    <span class="badge badge-info"><?= count($logs) ?> бичлэг</span>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="search" class="form-control" placeholder="Хайх..." value="<?= h($search) ?>">
      <select name="action_f" class="form-control">
        <option value="">Бүх үйлдэл</option>
        <?php foreach($actions as $a): ?><option value="<?= h($a['action']) ?>" <?= $action===$a['action']?'selected':'' ?>><?= h($a['action']) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
      <a href="logs.php" class="btn btn-secondary">Арилгах</a>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Үйлдэл</th><th>Дэлгэрэнгүй</th><th>IP хаяг</th><th>Цаг</th></tr></thead>
        <tbody>
        <?php foreach($logs as $i => $log): ?>
        <tr>
          <td><?= $log['log_id'] ?></td>
          <td>
            <div style="font-weight:600"><?= h($log['full_name'] ?? 'Систем') ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= h($log['username'] ?? '') ?></div>
          </td>
          <td><span class="badge badge-info"><?= h($log['action']) ?></span></td>
          <td style="font-size:12px"><?= h($log['detail'] ?? '-') ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= h($log['ip_address'] ?? '-') ?></td>
          <td style="font-size:11px;white-space:nowrap;color:var(--muted)"><?= mnDateTime($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$logs): ?><tr><td colspan="6" style="text-align:center;color:var(--muted)">Илэрц олдсонгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

