<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin','manager','director']);

// Зөвхөн admin болон director шинэ хэрэглэгч нэмж болно
$canCreateUser = isAdmin() || isDirector();

$pageTitle = 'Хэрэглэгчид';

// Устгах / Идэвхгүй болгох
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    if (!$canCreateUser) { die('Эрх хүрэхгүй байна'); }
    $id = (int)$_POST['user_id'];
    if ($id !== (int)$_SESSION['user_id']) {
        dbUpdate("UPDATE users SET is_active=0 WHERE user_id=?", [$id]);
        auditLog('user_deactivated', $id, 'Хэрэглэгч идэвхгүй болгов');
        setFlash('success', 'Хэрэглэгч идэвхгүй болгогдлоо');
    }
    header('Location: /school_system1/pages/users/index.php'); exit;
}

// Идэвхтэй болгох
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate') {
    verifyCsrf();
    if (!$canCreateUser) { die('Эрх хүрэхгүй байна'); }
    $id = (int)$_POST['user_id'];
    if ($id !== (int)$_SESSION['user_id']) {
        dbUpdate("UPDATE users SET is_active=1 WHERE user_id=?", [$id]);
        auditLog('user_activated', $id, 'Хэрэглэгч идэвхтэй болгов');
        setFlash('success', 'Хэрэглэгч амжилттай идэвхтэй болгогдлоо');
    }
    header('Location: /school_system1/pages/users/index.php'); exit;
}


// Нэмэх / засах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    verifyCsrf();
    $action   = $_POST['action'];
    $userId   = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $roleId   = (int)($_POST['role_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if (!$fullName || !$roleId || ($action === 'create' && !$username)) {
        setFlash('error', 'Шаардлагатай талбаруудыг бөглөнө үү!');
    } else {
        if ($action === 'create') {
            if (!$canCreateUser) { die('Эрх хүрэхгүй байна'); }
            if (!$password) { setFlash('error', 'Нууц үг оруулна уу!'); }
            else {
                $hash = hashPassword($password);
                $profileImage = null;
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $profileImage = uploadProfileImage($_FILES['profile_image']);
                }
                try {
                    $newId = dbExec("INSERT INTO users (username,password_hash,role_id,full_name,email,phone,profile_image) VALUES (?,?,?,?,?,?,?)",
                        [$username, $hash, $roleId, $fullName, $email, $phone, $profileImage]);
                    auditLog('user_created', $newId, "$fullName нэмэгдлээ");
                    setFlash('success', 'Хэрэглэгч амжилттай нэмэгдлээ!');
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        setFlash('error', 'Нэвтрэх нэр эсвэл имэйл бүртгэлтэй байна!');
                    } else {
                        setFlash('error', 'Алдаа гарлаа: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // Manager cannot edit admin or become admin
            if (!isAdmin()) {
                $targetUser = dbOne("SELECT role_name FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.user_id=?", [$userId]);
                if ($targetUser && $targetUser['role_name'] === 'admin') {
                    setFlash('error', 'Систем админы мэдээллийг засах эрхгүй!');
                    header('Location: /school_system1/pages/users/index.php'); exit;
                }
                $newRole = dbOne("SELECT role_name FROM user_roles WHERE role_id=?", [$roleId]);
                if ($newRole && $newRole['role_name'] === 'admin') {
                    setFlash('error', 'Эрхийг Админ болгож өсгөх боломжгүй!');
                    header('Location: /school_system1/pages/users/index.php'); exit;
                }
            }

            $pRow = dbOne("SELECT profile_image FROM users WHERE user_id=?", [$userId]);
            $profileImage = $pRow ? $pRow['profile_image'] : null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploaded = uploadProfileImage($_FILES['profile_image']);
                if ($uploaded) {
                    if ($profileImage && file_exists(__DIR__ . '/../../' . $profileImage)) {
                        unlink(__DIR__ . '/../../' . $profileImage);
                    }
                    $profileImage = $uploaded;
                }
            }

            $params = [$fullName, $email, $phone, $roleId, $profileImage, $userId];
            $sql = "UPDATE users SET full_name=?,email=?,phone=?,role_id=?,profile_image=? WHERE user_id=?";
            if ($password) {
                $sql = "UPDATE users SET full_name=?,email=?,phone=?,role_id=?,profile_image=?,password_hash=? WHERE user_id=?";
                $params = [$fullName, $email, $phone, $roleId, $profileImage, hashPassword($password), $userId];
            }
            try {
                dbUpdate($sql, $params);
                auditLog('user_updated', $userId, "$fullName шинэчлэгдлээ");
                setFlash('success', 'Хэрэглэгч шинэчлэгдлээ!');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Имэйл эсвэл дугаар давхардаж байна!');
                } else {
                    setFlash('error', 'Алдаа гарлаа: ' . $e->getMessage());
                }
            }
        }
    }
    header('Location: /school_system1/pages/users/index.php'); exit;
}

// Pagination and Жагсаалт
$roleFilter = $_GET['role'] ?? '';
$search     = $_GET['search'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;

$params = [];
$whereSql = "WHERE 1=1";
if ($roleFilter) { $whereSql .= " AND r.role_name=?"; $params[] = $roleFilter; }
if ($search)     { $whereSql .= " AND (u.full_name LIKE ? OR u.username LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$cntRow = dbOne("SELECT COUNT(u.user_id) as cnt FROM users u JOIN user_roles r ON u.role_id=r.role_id $whereSql", $params);
$totalRows = $cntRow ? $cntRow['cnt'] : 0;
$totalPages = max(1, ceil($totalRows / $perPage));
$offset = ($page - 1) * $perPage;

$sql = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id=r.role_id $whereSql ORDER BY u.user_id DESC LIMIT $perPage OFFSET $offset";
$users = dbQuery($sql, $params);
$roles = dbQuery("SELECT * FROM user_roles ORDER BY role_id");
$modalRoles = dbQuery("SELECT * FROM user_roles WHERE role_name NOT IN ('student', 'teacher') ORDER BY role_id");

// Засах хэрэглэгч
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = dbOne("SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.user_id=?", [(int)$_GET['edit']]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-users-cog"></i> Хэрэглэгчдийн удирдлага</h2>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if($canCreateUser): ?>
        <a href="/school_system1/pages/students/index.php" class="btn btn-secondary" style="background:#2ecc71; color:white; border:none;"><i class="fas fa-user-graduate"></i> Сурагч бүртгэх</a>
        <a href="/school_system1/pages/teachers/index.php" class="btn btn-secondary" style="background:#9b59b6; color:white; border:none;"><i class="fas fa-chalkboard-teacher"></i> Багш бүртгэх</a>
        <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Шинэ хэрэглэгч нэмэх</button>
        <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <!-- FILTER -->
    <form method="GET" class="filter-bar">
      <input type="text" name="search" class="form-control" placeholder="Хайх..." value="<?= h($search) ?>">
      <select name="role" class="form-control">
        <option value="">Бүх эрх</option>
        <?php foreach($roles as $r): ?>
        <option value="<?= h($r['role_name']) ?>" <?= $roleFilter===$r['role_name']?'selected':'' ?>><?= h($r['role_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Хайх</button>
      <a href="/school_system1/pages/users/index.php" class="btn btn-secondary">Арилгах</a>
    </form>

    <!-- TABLE -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Зураг</th><th>Нэвтрэх нэр</th><th>Бүтэн нэр</th><th>Эрх</th><th>Имэйл</th><th>Утас</th><th>Төлөв</th><th>Үйлдэл</th></tr>
        </thead>
        <tbody>
        <?php foreach($users as $i => $u): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><img src="<?= getUserAvatar($u['profile_image'], $u['full_name']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;"></td>
          <td><strong><?= h($u['username']) ?></strong></td>
          <td><?= h($u['full_name']) ?></td>
          <td><span class="badge badge-<?= h($u['role_name']) ?>"><?= h($u['role_name']) ?></span></td>
          <td><?= h($u['email']) ?></td>
          <td><?= h($u['phone']) ?></td>
          <td><?= $u['is_active'] ? '<span class="badge badge-success">Идэвхтэй</span>' : '<span class="badge badge-danger">Идэвхгүй</span>' ?></td>
          <td style="white-space:nowrap">
            <?php if($canCreateUser || isManager()): ?>
            <a href="?edit=<?= $u['user_id'] ?>" class="btn btn-sm btn-secondary" title="Засах"><i class="fas fa-edit"></i></a>
            <?php endif; ?>
            <?php if($u['user_id'] != $_SESSION['user_id'] && $canCreateUser): ?>
              <?php if($u['is_active']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Хэрэглэгчийг идэвхгүй болгох уу?')">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Идэвхгүй болгох"><i class="fas fa-ban"></i></button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Хэрэглэгчийг идэвхтэй болгох уу?')">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-success" title="Идэвхтэй болгох"><i class="fas fa-check-circle"></i></button>
              </form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$users): ?><tr><td colspan="9" style="text-align:center;color:var(--muted)">Хэрэглэгч олдсонгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginator -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex; justify-content:center; gap:5px; margin-top:20px;">
      <?php
      $qry = $_GET; unset($qry['page']);
      $baseLink = '?' . http_build_query($qry) . '&page=';
      if ($page > 1) { echo '<a href="'.$baseLink.($page-1).'" class="btn btn-sm btn-secondary">Өмнөх</a>'; }
      for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++) {
          $active = ($p == $page) ? 'btn-primary' : 'btn-secondary';
          echo '<a href="'.$baseLink.$p.'" class="btn btn-sm '.$active.'">'.$p.'</a>';
      }
      if ($page < $totalPages) { echo '<a href="'.$baseLink.($page+1).'" class="btn btn-sm btn-secondary">Дараах</a>'; }
      ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- НЭМЭХ MODAL -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-user-plus"></i> Шинэ хэрэглэгч нэмэх</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Нэвтрэх нэр *</label><input type="text" name="username" class="form-control" required></div>
          <div class="form-group"><label>Нууц үг *</label><input type="password" name="password" class="form-control" required></div>
          <div class="form-group"><label>Бүтэн нэр *</label><input type="text" name="full_name" class="form-control" required></div>
          <div class="form-group"><label>Эрх *</label>
            <select name="role_id" class="form-control" required>
              <?php foreach($modalRoles as $r): ?><option value="<?= $r['role_id'] ?>"><?= h($r['role_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Имэйл</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control"></div>
          <div class="form-group"><label>Профайл зураг *</label><input type="file" name="profile_image" class="form-control" accept="image/*" required></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- ЗАСАХ MODAL -->
<?php if($editUser): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('modalEdit'));</script>
<?php endif; ?>
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Хэрэглэгч засах</h3><button class="modal-close" onclick="closeModal('modalEdit')">×</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?? '' ?>">
        <div class="form-row">
          <div class="form-group"><label>Нэвтрэх нэр</label><input type="text" class="form-control" value="<?= h($editUser['username'] ?? '') ?>" disabled></div>
          <div class="form-group"><label>Шинэ нууц үг <span style="color:var(--muted)">(хоосон бол өөрчлөхгүй)</span></label><input type="password" name="password" class="form-control"></div>
          <div class="form-group"><label>Бүтэн нэр *</label><input type="text" name="full_name" class="form-control" value="<?= h($editUser['full_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Эрх *</label>
            <select name="role_id" class="form-control" required>
              <?php foreach($modalRoles as $r): ?><option value="<?= $r['role_id'] ?>" <?= ($editUser['role_id']??'')==$r['role_id']?'selected':'' ?>><?= h($r['role_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Имэйл</label><input type="email" name="email" class="form-control" value="<?= h($editUser['email'] ?? '') ?>"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control" value="<?= h($editUser['phone'] ?? '') ?>"></div>
          <div class="form-group"><label>Профайл зураг <span style="color:var(--muted)">(солих бол)</span></label><input type="file" name="profile_image" class="form-control" accept="image/*"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="/school_system1/pages/users/index.php" class="btn btn-secondary">Болих</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

