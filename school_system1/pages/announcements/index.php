<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Зарлал & Мэдэгдэл';

// Student болон Parent зарлал зөвхөн ХАРАХ эрхтэй (нийтлэх эрхгүй)
// Filter logic (доорх) target_audience-аар зөвхөн зохимжтой зарлалуудыг харуулна

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if (!canPostAnn()) {
    header('Location: /school_system1/dashboard.php');
    exit;
  }
  verifyCsrf();
  $title = trim($_POST['title'] ?? '');
  $body = trim($_POST['body'] ?? '');
  $pinned = isset($_POST['pinned']) ? 1 : 0;
  $audience = $_POST['target_audience'] ?? 'all';
  if (!$title || !$body) {
    setFlash('error', 'Гарчиг болон агуулга хоосон байж болохгүй!');
  } else {
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
      $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      $imgAllowed = ['jpg','jpeg','png','gif','webp'];
      if (in_array($ext, $imgAllowed) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
        $fileName = 'ann_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $uploadDir = __DIR__ . '/../../uploads/announcements/';
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
          $imageUrl = '/school_system1/uploads/announcements/' . $fileName;
        }
      }
    }

    dbExec(
      "INSERT INTO announcements (title, body, pinned, created_by, image_url, target_audience) VALUES (?,?,?,?,?,?)",
      [$title, $body, $pinned, $_SESSION['user_id'], $imageUrl, $audience]
    );
    auditLog('announcement_created', null, $title);
    setFlash('success', 'Зарлал нийтлэгдлээ!');
  }
  header('Location: /school_system1/pages/announcements/index.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!isManager() && !isAdmin()) {
    // Allow creators to delete their own
    $annId = (int) $_POST['ann_id'];
    $check = dbOne("SELECT created_by FROM announcements WHERE ann_id=?", [$annId]);
    if (!$check || $check['created_by'] != $_SESSION['user_id']) {
      header('Location: /school_system1/dashboard.php');
      exit;
    }
  }
  verifyCsrf();
  dbUpdate("DELETE FROM announcements WHERE ann_id=?", [(int) $_POST['ann_id']]);
  setFlash('success', 'Зарлал устгагдлаа.');
  header('Location: /school_system1/pages/announcements/index.php');
  exit;
}

// Filter logic
$search = trim($_GET['q'] ?? '');
$where = " (target_audience = 'all' ";
if (isTeacher()) {
  $where .= " OR target_audience = 'teachers' ";
} elseif (isStudent() || isParent()) {
  $where .= " OR target_audience = 'students_parents' ";
} else {
  $where = " (1=1 "; // Admin/Manager
}
$where .= " OR created_by = " . (int) $_SESSION['user_id'] . ") ";

$params = [];
if ($search) {
    $where = "($where) AND (a.title LIKE ? OR a.body LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$list = dbQuery("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.created_by=u.user_id WHERE $where ORDER BY a.pinned DESC, a.created_at DESC", $params);
include __DIR__ . '/../../includes/header.php';
?>
<?php if (canPostAnn()): ?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <h2><i class="fas fa-plus"></i> Шинэ зарлал</h2>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group"><label>Гарчиг *</label><input type="text" name="title" class="form-control" required>
        </div>
        <div class="form-group"><label>Агуулга *</label><textarea name="body" class="form-control" rows="4"
            style="resize:vertical" required></textarea></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
          <div class="form-group" style="display:flex;align-items:center;gap:8px; margin-bottom:0;">
            <input type="checkbox" name="pinned" id="pinned" style="width:16px;height:16px">
            <label for="pinned" style="margin:0;cursor:pointer"><i class="fas fa-thumbtack"></i> Дээр бэхлэх</label>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label>Хэнд харагдуулах</label>
            <select name="target_audience" class="form-control" style="padding: 4px 10px; height: 35px;">
              <option value="all">Бүгдэд (Нээлттэй)</option>
              <option value="teachers">Зөвхөн Багш нарт</option>
              <option value="students_parents">Сурагч & Эцэг эхийн хэсэгт</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Зураг хавсаргах</label>
          <input type="file" name="image" class="form-control" accept="image/*"
            style="background:var(--bg); border:1px solid var(--border); padding:6px; height:auto;">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-bullhorn"></i> Нийтлэх</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<form method="GET" class="filter-bar" style="margin-bottom: 20px;">
  <input type="text" name="q" class="form-control" placeholder="Зарлал хайх (гарчиг, агуулга)..." value="<?= h($search) ?>" style="width:300px">
  <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Хайх</button>
  <?php if($search): ?><a href="index.php" class="btn btn-secondary">Арилгах</a><?php endif; ?>
</form>

<div style="display:flex;flex-direction:column;gap:14px">
  <?php if (!$list): ?>
    <div class="card">
      <div class="card-body" style="text-align:center;color:var(--muted);padding:40px">
        <i class="fas fa-bullhorn" style="font-size:32px;margin-bottom:10px;display:block"></i>Зарлал байхгүй.
      </div>
    </div>
  <?php endif; ?>
  <?php foreach ($list as $a): ?>
    <div class="card" style="<?= $a['pinned'] ? 'border-left:4px solid var(--primary)' : '' ?>">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <?php if ($a['pinned']): ?><span style="color:var(--primary)"><i
                    class="fas fa-thumbtack"></i></span><?php endif; ?>
              <h3 style="font-size:16px;font-weight:600;margin:0"><?= h($a['title']) ?></h3>
            </div>
            <div style="color:var(--muted);font-size:12px;margin-bottom:10px">
              <i class="fas fa-user"></i> <?= h($a['full_name'] ?? 'Систем') ?> &nbsp;·&nbsp; <i class="fas fa-clock"></i>
              <?= mnDateTime($a['created_at']) ?>
            </div>
            <p style="font-size:14px;line-height:1.7;white-space:pre-wrap;margin:0"><?= h($a['body']) ?></p>
            <?php if (!empty($a['image_url'])): ?>
              <div style="margin-top:15px;">
                <img src="<?= h($a['image_url']) ?>"
                  style="max-width:100%; border-radius:8px; border:1px solid var(--border); max-height:400px; object-fit:contain;">
              </div>
            <?php endif; ?>
          </div>
          <?php if (isManager() || isAdmin() || $a['created_by'] == $_SESSION['user_id']): ?>
            <form method="POST">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ann_id" value="<?= $a['ann_id'] ?>">
              <button class="btn btn-sm btn-danger" data-confirm="Устгах уу?"><i class="fas fa-trash"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
