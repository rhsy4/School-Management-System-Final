<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle = 'Сурагчид';

$isManager = isManager() || isAdmin();
$isTeacher = isTeacher();
$userId = $_SESSION['user_id'] ?? 0;

// Устгах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    if (!$isManager) {
        setFlash('error', 'Эрх хүрэхгүй байна!');
        header('Location: /school_system1/pages/students/index.php'); exit;
    }
    $id = (int)$_POST['student_id'];

    dbUpdate("UPDATE students SET is_active=0 WHERE student_id=?", [$id]);
    auditLog('student_deactivated', $id, 'Сурагч идэвхгүй болгов');
    setFlash('success', 'Сурагч идэвхгүй болгогдлоо');
    header('Location: /school_system1/pages/students/index.php'); exit;
}

// Нэмэх / засах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    verifyCsrf();
    if (!$isManager && !$isTeacher) {
        setFlash('error', 'Эрх хүрэхгүй байна!');
        header('Location: /school_system1/pages/students/index.php'); exit;
    }

    $action    = $_POST['action'];
    $studentId = (int)($_POST['student_id'] ?? 0);
    $lastName  = trim($_POST['last_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $registerNo= trim($_POST['register_no'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');
    $birthDate = $_POST['birth_date'] ?? '';
    $classId   = (int)($_POST['class_id'] ?? 0);
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if ($isTeacher && !$isManager) {
        $owner = dbOne("SELECT teacher_id FROM classes WHERE class_id=?", [$classId]);
        if (!$owner || $owner['teacher_id'] != $userId) {
            setFlash('error', 'Зөвхөн өөрийн даасан ангид сурагч нэмэх/засах эрхтэй!');
            header('Location: /school_system1/pages/students/index.php'); exit;
        }
    }

    if (!$lastName || !$firstName || !$classId) {
        setFlash('error', 'Овог, нэр, анги заавал шаардлагатай!');
    } elseif ($registerNo !== '' && !isValidRegister($registerNo)) {
        setFlash('error', 'Регистрийн дугаар буруу байна (Жишээ нь: УА12345678)!');
    } elseif ($action === 'create') {
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $email     = trim($_POST['email'] ?? '');
        if (!$username || !$password) { setFlash('error', 'Нэвтрэх нэр, нууц үг оруулна уу!'); }
        else {
            $roleId  = 4; // student
            $fullName = "$lastName $firstName";
            $hash    = hashPassword($password);
            $canEditGrades = isset($_POST['can_edit_grades']) ? 1 : 0;
            $canPostAnn    = isset($_POST['can_post_announcements']) ? 1 : 0;
            
            try {
                $uid     = dbExec("INSERT INTO users (username,password_hash,role_id,full_name,email,phone,can_edit_grades,can_post_announcements) VALUES (?,?,?,?,?,?,?,?)",
                    [$username, $hash, $roleId, $fullName, $email, $phone, $canEditGrades, $canPostAnn]);
                
                $sid     = dbExec("INSERT INTO students (user_id,last_name,first_name,register_no,gender,birth_date,class_id,phone,address) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$uid, $lastName, $firstName, $registerNo, $gender, $birthDate ?: null, $classId, $phone, $address]);

                if (isset($_POST['auto_create_parent'])) {
                    $pUsername = $username . '_p';
                    $pHash = hashPassword($password); // Same password as student
                    $pFullName = "Эцэг эх: $lastName $firstName";
                    
                    // 1. Create User
                    $pUid = dbExec("INSERT INTO users (username,password_hash,role_id,full_name,phone) VALUES (?,?,?,?,?)",
                        [$pUsername, $pHash, 5, $pFullName, $phone]);
                    
                    // 2. Create Parent Record
                    $parentId = dbExec("INSERT INTO parents (user_id,last_name,first_name,phone) VALUES (?,?,?,?)",
                        [$pUid, $lastName, "Эцэг эх", $phone]);
                    
                    // 3. Link Student to Parent
                    dbUpdate("UPDATE students SET parent_id=? WHERE student_id=?", [$parentId, $sid]);
                }
                
                auditLog('student_created', $sid, "$fullName нэмэгдлээ" . (isset($pUsername) ? " (Эцэг эх хамт)" : ""));
                setFlash('success', 'Сурагч амжилттай нэмэгдлээ!' . (isset($pUsername) ? " Эцэг эхийн нэвтрэх нэр: $pUsername" : ""));
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Нэвтрэх нэр эсвэл имэйл давхардаж байна!');
                } else {
                    setFlash('error', 'Өгөгдлийн баазын алдаа: ' . $e->getMessage());
                }
            }
        }
    } else {
        if ($isTeacher && !$isManager) {
            $currStudent = dbOne("SELECT class_id FROM students WHERE student_id=?", [$studentId]);
            if (!$currStudent) {
                 setFlash('error', 'Сурагч олдсонгүй');
                 header('Location: /school_system1/pages/students/index.php'); exit;
            }
            $oldOwner = dbOne("SELECT teacher_id FROM classes WHERE class_id=?", [$currStudent['class_id']]);
            if (!$oldOwner || $oldOwner['teacher_id'] != $userId) {
                setFlash('error', 'Өөр ангийн сурагчийг засах эрхгүй!');
                header('Location: /school_system1/pages/students/index.php'); exit;
            }
        }
        try {
            dbUpdate("UPDATE students SET last_name=?,first_name=?,register_no=?,gender=?,birth_date=?,class_id=?,phone=?,address=? WHERE student_id=?",
                [$lastName, $firstName, $registerNo, $gender, $birthDate ?: null, $classId, $phone, $address, $studentId]);
            // users хүснэгтийн нэр шинэчлэх
            $sid = dbOne("SELECT user_id FROM students WHERE student_id=?", [$studentId]);
            if ($sid) dbUpdate("UPDATE users SET full_name=? WHERE user_id=?", ["$lastName $firstName", $sid['user_id']]);
            auditLog('student_updated', $studentId, "$lastName $firstName шинэчлэгдлээ");
            setFlash('success', 'Сурагчийн мэдээлэл шинэчлэгдлээ!');
        } catch (PDOException $e) {
            setFlash('error', 'Өгөгдлийн баазын алдаа: ' . $e->getMessage());
        }
    }
    header('Location: /school_system1/pages/students/index.php'); exit;
}

// Bulk Import урсгал
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    verifyCsrf();
    if (!$isManager && !$isTeacher) {
        setFlash('error', 'Эрх хүрэхгүй байна!');
        header('Location: /school_system1/pages/students/index.php'); exit;
    }
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Гарчиг алгасах
            $imported = 0;
            $failed = 0;
            
            // Ангиудыг нэрээр нь ID-руу хөрвүүлэхийн тулд cache хийх
            $classesData = dbQuery("SELECT class_id, class_name FROM classes");
            $classMap = [];
            foreach($classesData as $c) { $classMap[trim(mb_strtolower($c['class_name']))] = $c['class_id']; }
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $lastName  = trim($data[0] ?? '');
                $firstName = trim($data[1] ?? '');
                $birthDate = trim($data[2] ?? '');
                $className = trim(mb_strtolower($data[3] ?? ''));
                $phone     = trim($data[4] ?? '');
                $username  = trim($data[5] ?? '');
                $password  = trim($data[6] ?? '');
                
                if (!$lastName || !$firstName || !$className || !$username || !$password) {
                    $failed++; continue;
                }
                
                $classId = $classMap[$className] ?? 0;
                if (!$classId) { $failed++; continue; } // Анги олдсонгүй
                
                if ($isTeacher && !$isManager) {
                    $owner = dbOne("SELECT teacher_id FROM classes WHERE class_id=?", [$classId]);
                    if (!$owner || $owner['teacher_id'] != $userId) {
                        $failed++; continue; // Өөр анги руу нэмэхийг алгасах
                    }
                }
                
                // Username давхцал шалгах
                $exists = dbOne("SELECT user_id FROM users WHERE username=?", [$username]);
                if ($exists) { $failed++; continue; }
                
                $hash = hashPassword($password);
                $fullName = "$lastName $firstName";
                $uid = dbExec("INSERT INTO users (username,password_hash,role_id,full_name,phone) VALUES (?,?,?,?,?)",
                    [$username, $hash, 4, $fullName, $phone]);
                $sid = dbExec("INSERT INTO students (user_id,last_name,first_name,birth_date,class_id,phone) VALUES (?,?,?,?,?,?)",
                    [$uid, $lastName, $firstName, $birthDate ?: null, $classId, $phone]);
                $imported++;
            }
            fclose($handle);
            setFlash('success', "Амжилттай $imported сурагч импорт хийлээ. (Алдаатай эсвэл алгассан: $failed)");
        }
    } else {
        setFlash('error', 'Файл сонгогдоогүй эсвэл алдаа гарлаа.');
    }
    header('Location: /school_system1/pages/students/index.php'); exit;
}

// Жагсаалт
$classFilter = (int)($_GET['class_id'] ?? 0);
$search      = $_GET['search'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 25;

$params      = [];
$whereSql    = "WHERE s.is_active=1";

if ($classFilter) { 
    $whereSql .= " AND s.class_id=?"; 
    $params[] = $classFilter; 
}
if ($search) { 
    $whereSql .= " AND (s.last_name LIKE ? OR s.first_name LIKE ? OR u.username LIKE ? OR s.phone LIKE ? OR c.class_name LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}

// Pagination Count
$cntRow = dbOne("SELECT COUNT(s.student_id) as cnt FROM students s JOIN classes c ON s.class_id=c.class_id JOIN users u ON s.user_id=u.user_id $whereSql", $params);
$totalRows = $cntRow ? $cntRow['cnt'] : 0;
$totalPages = max(1, ceil($totalRows / $perPage));
$offset = ($page - 1) * $perPage;

$sql = "SELECT s.*, CONCAT(s.last_name,' ',s.first_name) AS full_name,
               c.class_name, u.username, u.email
        FROM students s
        JOIN classes c ON s.class_id=c.class_id
        JOIN users u ON s.user_id=u.user_id
        $whereSql
        ORDER BY c.class_name, s.last_name, s.first_name
        LIMIT $perPage OFFSET $offset";

$students = dbQuery($sql, $params);
$classes  = dbQuery("SELECT * FROM classes ORDER BY class_name");
$editableClasses = $isManager ? $classes : dbQuery("SELECT * FROM classes WHERE teacher_id=? ORDER BY class_name", [$userId]);

$editStudent = null;
if (isset($_GET['edit'])) {
    $editStudent = dbOne("SELECT s.*, u.username, u.email FROM students s JOIN users u ON s.user_id=u.user_id WHERE s.student_id=?", [(int)$_GET['edit']]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-user-graduate"></i> Сурагчдын жагсаалт</h2>
    <?php if(isManager() || isTeacher()): ?>
    <div style="display:flex;gap:10px;">
      <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Сурагч нэмэх</button>
      <button class="btn btn-success" onclick="openModal('modalImport')"><i class="fas fa-file-csv"></i> CSV Оруулах</button>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="search" class="form-control" placeholder="Дэлгэрэнгүй хайлт (нэр, утас, анги, username)" value="<?= h($search) ?>" style="width:300px">
      <select name="class_id" class="form-control">
        <option value="">Бүх анги</option>
        <?php foreach($classes as $c): ?>
        <option value="<?= $c['class_id'] ?>" <?= $classFilter==$c['class_id']?'selected':'' ?>><?= h($c['class_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
      <a href="/school_system1/pages/students/index.php" class="btn btn-secondary">Арилгах</a>
    </form>

    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Нэр</th><th>Регистр</th><th>Нэвтрэх нэр</th><th>Анги</th><th>Төрсөн огноо</th><th>Үйлдэл</th></tr></thead>
        <tbody>
        <?php foreach($students as $i => $s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= h($s['full_name']) ?></strong></td>
          <td><?= h($s['register_no'] ?? '-') ?></td>
          <td><span class="badge badge-secondary"><?= h($s['username']) ?></span></td>
          <td><?= h($s['class_name']) ?></td>
          <td><?= $s['birth_date'] ? mnDate($s['birth_date']) : '-' ?></td>
          <td style="white-space:nowrap">
            <a href="/school_system1/pages/students/view.php?id=<?= $s['student_id'] ?>" class="btn btn-sm btn-secondary" title="Харах"><i class="fas fa-eye"></i></a>
            <?php 
            $canEdit = isManager() || (isTeacher() && dbOne("SELECT class_id FROM classes WHERE class_id=? AND teacher_id=?", [$s['class_id'], $userId]));
            if($canEdit): 
            ?>
            <a href="?edit=<?= $s['student_id'] ?>" class="btn btn-sm btn-secondary" title="Засах"><i class="fas fa-edit"></i></a>
            <?php endif; ?>
            <?php if(isManager()): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <button class="btn btn-sm btn-danger" data-confirm="Сурагчийг идэвхгүй болгох уу?" title="Идэвхгүй болгох"><i class="fas fa-ban"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$students): ?><tr><td colspan="7" style="text-align:center;color:var(--muted)">Сурагч олдсонгүй</td></tr><?php endif; ?>
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
    <div class="modal-header"><h3><i class="fas fa-user-plus"></i> Шинэ сурагч нэмэх</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Овог *</label><input type="text" name="last_name" class="form-control" required></div>
          <div class="form-group"><label>Нэр *</label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group"><label>Регистрийн дугаар</label><input type="text" name="register_no" class="form-control" placeholder="УА12345678"></div>
          <div class="form-group"><label>Хүйс</label>
            <select name="gender" class="form-control">
              <option value="">Сонгох</option>
              <option value="Эр">Эр</option>
              <option value="Эм">Эм</option>
            </select>
          </div>
          <div class="form-group"><label>Анги *</label>
            <select name="class_id" class="form-control" required>
              <option value="">Анги сонгох</option>
              <?php foreach($editableClasses as $c): ?><option value="<?= $c['class_id'] ?>"><?= h($c['class_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Төрсөн огноо</label><input type="date" name="birth_date" class="form-control"></div>
          <div class="form-group">
            <label>Нэвтрэх нэр *</label>
            <div style="display:flex; gap:5px;">
              <input type="text" name="username" id="create_username" class="form-control" required>
              <button type="button" class="btn btn-secondary" onclick="generateUsername()" title="Автоматаар үүсгэх"><i class="fas fa-magic"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Нууц үг *</label>
            <div style="display:flex; gap:5px;">
              <input type="password" name="password" id="create_password" class="form-control" required>
              <button type="button" class="btn btn-secondary" onclick="document.getElementById('create_password').value='123456'" title="Стандарт нууц үг (123456)"><i class="fas fa-lock"></i></button>
            </div>
          </div>
          <div class="form-group"><label>Имэйл</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Хаяг</label><input type="text" name="address" class="form-control"></div>
          
          <div style="grid-column:1/-1; border-top:1px solid var(--border); padding-top:15px; margin-top:5px; display:grid; grid-template-columns:1fr 1fr; gap:15px;">
             <div>
               <h4 style="font-size:14px; margin-bottom:10px;"><i class="fas fa-robot"></i> Автоматжуулалт</h4>
               <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight: normal;">
                 <input type="checkbox" name="auto_create_parent" style="width:16px; height:16px;">
                 Эцэг эхийн хаяг давхар үүсгэх
               </label>
             </div>
             <div>
               <h4 style="font-size:14px; margin-bottom:10px;"><i class="fas fa-shield-alt"></i> Хандах эрх</h4>
               <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight: normal; margin-bottom:5px;">
                 <input type="checkbox" name="can_edit_grades" style="width:16px; height:16px;">
                 Дүн засах эрх (Зөвхөн онцгой тохиолдолд)
               </label>
               <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight: normal;">
                 <input type="checkbox" name="can_post_announcements" style="width:16px; height:16px;" checked>
                 Зарлал/Мэдэгдэл харах эрх
               </label>
             </div>
          </div>
        </div>
      </div>
      <script>
      function generateUsername() {
          let modal = document.getElementById('modalCreate');
          let last = modal.querySelector('input[name="last_name"]').value.trim().toLowerCase();
          let first = modal.querySelector('input[name="first_name"]').value.trim().toLowerCase();
          if(!first) { alert('Эхлээд нэрээ оруулна уу!'); return; }
          // Simple transliteration approximation or just first letter + last name
          let charMap = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'j','з':'z','и':'i','й':'i','к':'k','л':'l','м':'m','н':'n','о':'o','ө':'u','п':'p','р':'r','с':'s','т':'t','у':'u','ү':'u','ф':'f','х':'kh','ц':'ts','ч':'ch','ш':'sh','щ':'sh','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
          let trans = (str) => str.split('').map(c => charMap[c] || c).join('');
          let u = trans(first).substring(0,2) + trans(last).substring(0,2) + Math.floor(Math.random() * 900 + 100);
          document.getElementById('create_username').value = 's.' + u.replace(/[^a-z0-9]/g, '');
      }
      </script>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- IMPORT CSV MODAL -->
<div class="modal-overlay" id="modalImport">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-file-csv"></i> CSV файлаас оруулах</h3><button class="modal-close" onclick="closeModal('modalImport')">×</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="import_csv">
        <div class="form-group">
          <label>CSV файл сонгох *</label>
          <input type="file" name="csv_file" accept=".csv" class="form-control" required>
        </div>
        <div class="info-box" style="margin-top:15px;">
          <strong>Файлын бүтэц (Эхний мөр гарчиг байх ёстой бөгөөд алгасагдана, таслалаар тусгаарлагдсан байх):</strong><br>
          Багана 1: Овог<br>
          Багана 2: Нэр<br>
          Багана 3: Төрсөн огноо (YYYY-MM-DD эсвэл хоосон)<br>
          Багана 4: Анги (Жишээ нь 10А)<br>
          Багана 5: Утас<br>
          Багана 6: Нэвтрэх нэр (Монгол үсэггүй байх)<br>
          Багана 7: Нууц үг
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalImport')">Болих</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Импорт хийх</button>
      </div>
    </form>
  </div>
</div>

<!-- ЗАСАХ MODAL -->
<?php if($editStudent): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('modalEdit'));</script>
<div class="modal-overlay open" id="modalEdit">
<?php else: ?>
<div class="modal-overlay" id="modalEdit">
<?php endif; ?>
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Сурагч засах</h3><button class="modal-close" onclick="closeModal('modalEdit')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="student_id" value="<?= $editStudent['student_id'] ?? '' ?>">
        <div class="form-row">
          <div class="form-group"><label>Овог *</label><input type="text" name="last_name" class="form-control" value="<?= h($editStudent['last_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Нэр *</label><input type="text" name="first_name" class="form-control" value="<?= h($editStudent['first_name'] ?? '') ?>" required></div>
          <div class="form-group"><label>Регистрийн дугаар</label><input type="text" name="register_no" class="form-control" value="<?= h($editStudent['register_no'] ?? '') ?>"></div>
          <div class="form-group"><label>Хүйс</label>
            <select name="gender" class="form-control">
              <option value="">Сонгох</option>
              <option value="Эр" <?= ($editStudent['gender']??'')=='Эр'?'selected':'' ?>>Эр</option>
              <option value="Эм" <?= ($editStudent['gender']??'')=='Эм'?'selected':'' ?>>Эм</option>
            </select>
          </div>
          <div class="form-group"><label>Анги *</label>
            <select name="class_id" class="form-control" required>
              <?php foreach($editableClasses as $c): ?><option value="<?= $c['class_id'] ?>" <?= ($editStudent['class_id']??'')==$c['class_id']?'selected':'' ?>><?= h($c['class_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Төрсөн огноо</label><input type="date" name="birth_date" class="form-control" value="<?= h($editStudent['birth_date'] ?? '') ?>"></div>
          <div class="form-group"><label>Утас</label><input type="text" name="phone" class="form-control" value="<?= h($editStudent['phone'] ?? '') ?>"></div>
          <div class="form-group" style="grid-column:1/-1"><label>Хаяг</label><input type="text" name="address" class="form-control" value="<?= h($editStudent['address'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="/school_system1/pages/students/index.php" class="btn btn-secondary">Болих</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

