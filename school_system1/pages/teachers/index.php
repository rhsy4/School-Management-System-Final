<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Багшид';

// Нэмэх / засах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    verifyCsrf();
    $action    = $_POST['action'];
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $lastName  = trim($_POST['last_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $position  = trim($_POST['position'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    if (!$lastName || !$firstName) {
        setFlash('error', 'Овог, нэр заавал шаардлагатай!');
    } elseif ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$username || !$password) { setFlash('error', 'Нэвтрэх нэр, нууц үг оруулна уу!'); }
        else {
            $canEditGrades = isset($_POST['can_edit_grades']) ? 1 : 0;
            $canPostAnn    = isset($_POST['can_post_announcements']) ? 1 : 0;
            
            try {
                $uid = dbExec("INSERT INTO users (username,password_hash,role_id,full_name,email,phone,can_edit_grades,can_post_announcements) VALUES (?,?,3,?,?,?,?,?)",
                    [$username, hashPassword($password), "$lastName $firstName", $email, $phone, $canEditGrades, $canPostAnn]);
                $tid = dbExec("INSERT INTO teachers (user_id,last_name,first_name,position,phone,email) VALUES (?,?,?,?,?,?)",
                    [$uid, $lastName, $firstName, $position, $phone, $email]);
                auditLog('teacher_created', $tid, "$lastName $firstName нэмэгдлээ");
                setFlash('success', 'Багш амжилттай нэмэгдлээ!');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Нэвтрэх нэр эсвэл имэйл давхардаж байна!');
                } else {
                    setFlash('error', 'Өгөгдлийн баазын алдаа: ' . $e->getMessage());
                }
            }
        }
    } else {
        try {
            dbUpdate("UPDATE teachers SET last_name=?,first_name=?,position=?,phone=?,email=? WHERE teacher_id=?",
                [$lastName, $firstName, $position, $phone, $email, $teacherId]);
            $t = dbOne("SELECT user_id FROM teachers WHERE teacher_id=?", [$teacherId]);
            if ($t) dbUpdate("UPDATE users SET full_name=?,email=?,phone=? WHERE user_id=?", ["$lastName $firstName", $email, $phone, $t['user_id']]);
            auditLog('teacher_updated', $teacherId, "$lastName $firstName шинэчлэгдлээ");
            setFlash('success', 'Багшийн мэдээлэл шинэчлэгдлээ!');
        } catch (PDOException $e) {
            setFlash('error', 'Өгөгдлийн баазын алдаа: ' . $e->getMessage());
        }
    }
    header('Location: /school_system1/pages/teachers/index.php'); exit;
}

// Устгах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $tid = (int)$_POST['teacher_id'];
    $t = dbOne("SELECT user_id FROM teachers WHERE teacher_id=?", [$tid]);
    if ($t) dbUpdate("UPDATE users SET is_active=0 WHERE user_id=?", [$t['user_id']]);
    auditLog('teacher_deactivated', $tid);
    setFlash('success', 'Багш идэвхгүй болгогдлоо');
    header('Location: /school_system1/pages/teachers/index.php'); exit;
}

$search = $_GET['search'] ?? '';
$params = [];
$sql = "SELECT t.*, u.username, u.is_active, u.email AS user_email,
               COUNT(DISTINCT s.subject_id) AS subject_count
        FROM teachers t
        JOIN users u ON t.user_id=u.user_id
        LEFT JOIN subjects s ON s.teacher_id=t.user_id
        WHERE 1=1";
if ($search) { $sql .= " AND (t.last_name LIKE ? OR t.first_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " GROUP BY t.teacher_id ORDER BY t.last_name";
$teachers = dbQuery($sql, $params);

$editTeacher = null;
if (isset($_GET['edit'])) {
    $editTeacher = dbOne("SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id=u.user_id WHERE t.teacher_id=?", [(int)$_GET['edit']]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-chalkboard-teacher"></i> Багшдын жагсаалт</h2>
    <?php if(isManager() || isDirector()): ?>
    <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Багш нэмэх</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="search" class="form-control" placeholder="Нэрээр хайх..." value="<?= h($search) ?>">
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
      <a href="index.php" class="btn btn-secondary">Арилгах</a>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Нэр</th><th>Нэвтрэх нэр</th><th>Албан тушаал</th><th>Имэйл</th><th>Утас</th><th>Хичээл</th><th>Төлөв</th><th>Үйлдэл</th></tr></thead>
        <tbody>
        <?php foreach($teachers as $i => $t): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= h($t['last_name'].' '.$t['first_name']) ?></strong></td>
          <td><span class="badge badge-secondary"><?= h($t['username']) ?></span></td>
          <td><?= h($t['position'] ?? '-') ?></td>
          <td><?= h($t['email'] ?? '-') ?></td>
          <td><?= h($t['phone'] ?? '-') ?></td>
          <td><span class="badge badge-info"><?= $t['subject_count'] ?> хичээл</span></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-success">Идэвхтэй</span>' : '<span class="badge badge-danger">Идэвхгүй</span>' ?></td>
          <td style="white-space:nowrap">
            <?php if(isManager() || isDirector()): ?>
            <a href="?edit=<?= $t['teacher_id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="teacher_id" value="<?= $t['teacher_id'] ?>">
              <button class="btn btn-sm btn-danger" data-confirm="Багшийг идэвхгүй болгох уу?"><i class="fas fa-ban"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$teachers): ?><tr><td colspan="9" style="text-align:center;color:var(--muted)">Багш олдсонгүй</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- НЭМЭХ MODAL -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-plus"></i> Шинэ багш нэмэх</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST" data-confirm-form="Шинэ багшийг хадгалах уу?">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Овог *</label><input type="text" name="last_name" class="form-control" required></div>
          <div class="form-group"><label>Нэр *</label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group">
            <label>Нэвтрэх нэр *</label>
            <div style="display:flex; gap:5px;">
              <input type="text" name="username" id="teacher_username" class="form-control" required>
              <button type="button" class="btn btn-secondary" onclick="genTeacherUsername()" title="Автоматаар үүсгэх"><i class="fas fa-magic"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Нууц үг *</label>
            <div style="display:flex; gap:5px;">
              <input type="password" name="password" id="teacher_password" class="form-control" required>
              <button type="button" class="btn btn-secondary" onclick="document.getElementById('teacher_password').value='teacher123'" title="Стандарт нууц үг (teacher123)"><i class="fas fa-lock"></i></button>
            </div>
          </div>
          <div class="form-group"><label>Албан тушаал</label><input type="text" name="position" class="form-control" list="positionList" placeholder="Сонгох эсвэл бичих..."></div>
          <div class="form-group"><label>Имэйл</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control"></div>
          
          <div style="grid-column:1/-1; border-top:1px solid var(--border); padding-top:15px; margin-top:5px;">
             <h4 style="font-size:14px; margin-bottom:10px;"><i class="fas fa-shield-alt"></i> Хандах эрх (Permissions)</h4>
             <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
               <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight: normal;">
                 <input type="checkbox" name="can_edit_grades" style="width:16px; height:16px;" checked>
                 Дүн засах/оруулах эрх
               </label>
               <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight: normal;">
                 <input type="checkbox" name="can_post_announcements" style="width:16px; height:16px;" checked>
                 Зарлал/Мэдэгдэл оруулах эрх
               </label>
             </div>
          </div>
        </div>
      </div>
      <script>
      function genTeacherUsername() {
          let last = document.querySelector('#modalCreate input[name="last_name"]').value.trim().toLowerCase();
          let first = document.querySelector('#modalCreate input[name="first_name"]').value.trim().toLowerCase();
          if(!first) { alert('Эхлээд нэрээ оруулна уу!'); return; }
          let charMap = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'j','з':'z','и':'i','й':'i','к':'k','л':'l','м':'m','н':'n','о':'o','ө':'u','п':'p','р':'r','с':'s','т':'t','у':'u','ү':'u','ф':'f','х':'kh','ц':'ts','ч':'ch','ш':'sh','щ':'sh','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
          let trans = (str) => str.split('').map(c => charMap[c] || c).join('');
          let u = trans(first).substring(0,3) + trans(last).substring(0,1) + Math.floor(Math.random() * 900 + 100);
          document.getElementById('teacher_username').value = 't.' + u.replace(/[^a-z0-9]/g, '');
      }
      </script>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- ЗАСАХ MODAL -->
<?php if($editTeacher): ?>
<div class="modal-overlay open" id="modalEdit">
<?php else: ?>
<div class="modal-overlay" id="modalEdit">
<?php endif; ?>
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Багш засах</h3><button class="modal-close" onclick="closeModal('modalEdit')">×</button></div>
    <form method="POST" data-confirm-form="Өөрчлөлтийг хадгалах уу?">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="teacher_id" value="<?= $editTeacher['teacher_id'] ?? '' ?>">
        <div class="form-row">
          <div class="form-group"><label>Овог *</label><input type="text" name="last_name" class="form-control" value="<?= h($editTeacher['last_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Нэр *</label><input type="text" name="first_name" class="form-control" value="<?= h($editTeacher['first_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Албан тушаал</label><input type="text" name="position" class="form-control" list="positionList" value="<?= h($editTeacher['position'] ?? '') ?>" placeholder="Сонгох эсвэл бичих..."></div>
          <div class="form-group"><label>Имэйл</label><input type="email" name="email" class="form-control" value="<?= h($editTeacher['email'] ?? '') ?>"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control" value="<?= h($editTeacher['phone'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="index.php" class="btn btn-secondary">Болих</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<datalist id="positionList">
  <option value="Бага ангийн багш"></option>
  <option value="Математикийн багш"></option>
  <option value="Монгол хэл, уран зохиолын багш"></option>
  <option value="Гадаад хэлний багш"></option>
  <option value="Түүх, нийгмийн ухааны багш"></option>
  <option value="Физикийн багш"></option>
  <option value="Химийн багш"></option>
  <option value="Биологийн багш"></option>
  <option value="Газар зүйн багш"></option>
  <option value="Мэдээлэл зүйн багш"></option>
  <option value="Дуу хөгжмийн багш"></option>
  <option value="Дүрслэх урлаг, дизайн технологийн багш"></option>
  <option value="Биеийн тамирын багш"></option>
  <option value="Эрүүл мэндийн багш"></option>
  <option value="Сургалтын менежер"></option>
  <option value="Нийгмийн ажилтан"></option>
  <option value="Сэтгэл зүйч"></option>
  <option value="Тусгай сурган хүмүүжүүлэгч"></option>
</datalist>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

