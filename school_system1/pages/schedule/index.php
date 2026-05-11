<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification.php';
requireLogin();
$pageTitle = 'Хичээлийн хуваарь';

// Нэмэх
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();
    $classId   = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = (int)$_POST['teacher_id'];
    $day       = (int)$_POST['day_of_week'];
    $start     = $_POST['start_time'] ?? '';
    $end       = $_POST['end_time'] ?? '';
    $room      = trim($_POST['room'] ?? '');

    if (!$classId || !$subjectId || !$teacherId || !$day || !$start || !$end) {
        setFlash('error','Бүх шаардлагатай талбаруудыг бөглөнө үү!');
    } else {
        // 1-5-р ангийн багш зөвхөн өөрөө орох логик
        $classInfo = dbOne("SELECT class_name, teacher_id FROM classes WHERE class_id=?", [$classId]);
        $gradeNum = $classInfo ? (int)$classInfo['class_name'] : 0;
        
        if ($gradeNum >= 1 && $gradeNum <= 5 && $classInfo['teacher_id'] != $teacherId) {
            setFlash('error', '1-5-р ангид зөвхөн тухайн ангийн багш нь хичээл заах боломжтой!');
            header('Location: /school_system1/pages/schedule/index.php'); exit;
        }

        // 🔍 Багш + Анги + Өрөө давхцал шалгах (нэгдсэн функц)
        $conflict = checkScheduleConflict($teacherId, $classId, $room, $day, $start, $end);

        if ($conflict) { 
            setFlash('error', $conflict['message']); 
        } else {
            $sid = dbExec("INSERT INTO schedule (class_id,subject_id,teacher_id,day_of_week,start_time,end_time,room) VALUES (?,?,?,?,?,?,?)",
                [$classId, $subjectId, $teacherId, $day, $start, $end, $room]);
            auditLog('schedule_created', $sid, 'Хуваарь нэмэгдлээ');
            setFlash('success','Хуваарь амжилттай нэмэгдлээ!');
        }
    }
    header('Location: /school_system1/pages/schedule/index.php'); exit;
}

// Устгах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    if (!isManager() && !isAdmin()) {
        setFlash('error', 'Эрх хүрэхгүй байна! Төлөвлөгөө устгах эрх зөвхөн Менежерт бий.');
        header('Location: /school_system1/pages/schedule/index.php'); exit;
    }
    $id = (int)$_POST['schedule_id'];
    dbUpdate("DELETE FROM schedule WHERE schedule_id=?", [$id]);
    setFlash('success','Хуваарь устгагдлаа!');
    header('Location: /school_system1/pages/schedule/index.php'); exit;
}

$classFilter = (int)($_GET['class_id'] ?? 0);

$isStudent = isStudent();
$isParent  = isParent();
$myClassIds = [];

if ($isStudent) {
    $me = dbOne("SELECT class_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    if ($me && $me['class_id']) $myClassIds[] = $me['class_id'];
}
if ($isParent) {
    if (isset($_SESSION['active_child_id'])) {
        $c = dbOne("SELECT class_id FROM students WHERE student_id=?", [$_SESSION['active_child_id']]);
        if ($c && $c['class_id']) $myClassIds[] = $c['class_id'];
    }
}

$params = [];
$sql = "SELECT sc.*, sub.subject_name, c.class_name, CONCAT(t.last_name,' ',t.first_name) AS teacher_name
        FROM schedule sc
        JOIN subjects sub ON sc.subject_id=sub.subject_id
        JOIN classes c ON sc.class_id=c.class_id
        JOIN teachers t ON sc.teacher_id=t.user_id
        WHERE 1=1";

if ($isStudent || $isParent) {
    if (!$myClassIds) {
        $sql .= " AND 1=0"; // No classes assigned
    } else {
        $inPlaceholders = str_repeat('?,', count($myClassIds) - 1) . '?';
        $sql .= " AND sc.class_id IN ($inPlaceholders)";
        $params = array_merge($params, $myClassIds);
    }
} elseif ($classFilter) {
    $sql .= " AND sc.class_id=?"; 
    $params[] = $classFilter; 
}

$teacherFilter = (int)($_GET['teacher_id'] ?? 0);
if ($teacherFilter) {
    $sql .= " AND sc.teacher_id=?";
    $params[] = $teacherFilter;
}

$sql .= " ORDER BY sc.day_of_week, sc.start_time";
$schedules = dbQuery($sql, $params);
$classes   = dbQuery("SELECT * FROM classes ORDER BY class_name");
$subjects  = dbQuery("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON s.class_id=c.class_id ORDER BY c.class_name, s.subject_name");
$teachers  = dbQuery("SELECT t.*, CONCAT(t.last_name,' ',t.first_name) AS full_name FROM teachers t JOIN users u ON t.user_id=u.user_id WHERE u.is_active=1 ORDER BY t.last_name");

$days = [1=>'Даваа',2=>'Мягмар',3=>'Лхагва',4=>'Пүрэв',5=>'Баасан'];

// Grid хуваарь
$grid = [];
foreach ($schedules as $s) {
    $grid[$s['day_of_week']][] = $s;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-calendar-alt"></i> Хичээлийн хуваарь</h2>
    <?php if(isManager() || isAdmin()): ?>
    <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Хуваарь нэмэх</button>
    <?php endif; ?>
    <button class="btn btn-success" onclick="exportTableToExcel('scheduleTable', 'Hicheeliin_huvaari')"><i class="fas fa-file-excel"></i> Excel татах</button>
  </div>
  <div class="card-body">
    <?php if(!$isStudent && !$isParent): ?>
    <form method="GET" class="filter-bar" style="background:var(--bg); padding:20px; border-radius:12px; margin-bottom:24px; display:flex; gap:15px; align-items:end;">
      <div class="form-group" style="margin:0;">
          <label style="font-size:12px; color:var(--muted); margin-bottom:5px; display:block;">Ангиар шүүх</label>
          <select name="class_id" class="form-control" onchange="this.form.submit()" style="min-width:150px;">
            <option value="">Бүх анги</option>
            <?php foreach($classes as $c): ?><option value="<?= $c['class_id'] ?>" <?= $classFilter==$c['class_id']?'selected':'' ?>><?= h($c['class_name']) ?></option><?php endforeach; ?>
          </select>
      </div>
      
      <?php if(isManager() || isAdmin()): 
          $teacherFilter = (int)($_GET['teacher_id'] ?? 0);
      ?>
      <div class="form-group" style="margin:0;">
          <label style="font-size:12px; color:var(--muted); margin-bottom:5px; display:block;">Багшаар шүүх</label>
          <select name="teacher_id" class="form-control" onchange="this.form.submit()" style="min-width:150px;">
            <option value="">Бүх багш</option>
            <?php foreach($teachers as $t): ?><option value="<?= $t['user_id'] ?>" <?= ($teacherFilter==$t['user_id'])?'selected':'' ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
          </select>
      </div>
      <?php endif; ?>

      <a href="index.php" class="btn btn-secondary">Арилгах</a>
    </form>
    <?php endif; ?>

    <!-- ИНТЕРФЭЙСИЙН ШИНЭЧЛЭЛ -->
    <div style="display:flex; gap:20px; align-items:start;">
      
      <?php if(isManager() || isAdmin()): ?>
      <!-- Зүүн талын Control Panel -->
      <div style="width:280px; position:sticky; top:20px; background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:20px; box-shadow:var(--shadow);">
        <h3 style="font-size:16px; margin-bottom:15px;"><i class="fas fa-tools"></i> Хуваарь зохиох</h3>
        <p style="font-size:11px; color:var(--muted); margin-bottom:15px;">Эхлээд доорх мэдээллүүдийг сонгоод, хүснэгтийн хоосон нүднүүд дээр дарж шууд хадгална уу.</p>
        
        <div class="form-group">
            <label style="font-size:11px; font-weight:700;">1. Анги сонгох</label>
            <select id="sel_class" class="form-control" style="font-size:13px;" onchange="document.getElementById('sel_room').value = this.options[this.selectedIndex].getAttribute('data-room') || '';">
                <option value="" data-room="">-- Сонгох --</option>
                <?php foreach($classes as $c): ?><option value="<?= $c['class_id'] ?>" data-room="<?= h($c['room'] ?? '') ?>"><?= h($c['class_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="font-size:11px; font-weight:700;">2. Хичээл сонгох</label>
            <select id="sel_subject" class="form-control" style="font-size:13px;">
                <option value="">-- Сонгох --</option>
                <?php foreach($subjects as $s): ?><option value="<?= $s['subject_id'] ?>"><?= h($s['class_name'].' - '.$s['subject_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="font-size:11px; font-weight:700;">3. Багш сонгох</label>
            <select id="sel_teacher" class="form-control" style="font-size:13px;">
                <option value="">-- Сонгох --</option>
                <?php foreach($teachers as $t): ?><option value="<?= $t['user_id'] ?>"><?= h($t['full_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="font-size:11px; font-weight:700;">4. Өрөө</label>
            <input type="text" id="sel_room" class="form-control" placeholder="Жишээ: 201" style="font-size:13px;">
        </div>
        
        <div style="margin-top:20px; padding:10px; background:rgba(59, 130, 246, 0.05); border-radius:8px; border:1px dashed var(--primary);">
            <div style="font-size:11px; color:var(--primary); font-weight:600;"><i class="fas fa-info-circle"></i> Заавар:</div>
            <div style="font-size:10px; color:var(--muted); line-height:1.4;">Дээрх мэдээллийг сонгосны дараа баруун талын хүснэгт дээр дарахад автоматаар хадгалагдана.</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Баруун талын Grid -->
      <div style="flex:1;">
        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; box-shadow:var(--shadow);">
            <div style="overflow-x:auto;">
                <div style="display:grid; grid-template-columns: 80px repeat(5, 1fr); gap:1px; background:var(--border);">
                  <!-- Header -->
                  <div style="background:var(--bg); padding:15px; text-align:center; font-weight:bold; font-size:12px;">Цаг</div>
                  <?php foreach($days as $dayName): ?>
                    <div style="background:var(--bg); padding:15px; text-align:center; font-weight:bold; color:var(--primary);"><?= $dayName ?></div>
                  <?php endforeach; ?>

                  <!-- Time Rows -->
                  <?php 
                  $hours = range(8, 17);
                  foreach($hours as $hour): 
                    $timeLabel = sprintf("%02d:00", $hour);
                  ?>
                    <div style="background:var(--card-bg); padding:15px; text-align:center; font-size:12px; color:var(--muted); border-top:1px solid var(--border);"><?= $timeLabel ?></div>
                    
                    <?php for($d=1; $d<=5; $d++): 
                        $canAdd = isManager() || isAdmin();
                    ?>
                      <div class="schedule-cell" 
                           data-day="<?= $d ?>" data-hour="<?= $hour ?>"
                           style="background:var(--card-bg); padding:10px; border-top:1px solid var(--border); border-left:1px solid var(--border); min-height:100px; cursor:<?= $canAdd?'pointer':'default' ?>; transition:all .2s;"
                           <?= $canAdd ? "onclick='autoSave(this, $d, $hour)'" : "" ?>>
                        <?php 
                        foreach($grid[$d] ?? [] as $s): 
                            $startHour = (int)substr($s['start_time'], 0, 2);
                            if($startHour == $hour):
                        ?>
                          <div class="schedule-item" 
                               onclick="event.stopPropagation()"
                               style="background:rgba(59, 130, 246, 0.08); border-left:3px solid var(--primary); padding:8px; border-radius:8px; margin-bottom:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position:relative; overflow:hidden;">
                            <div style="font-weight:700; font-size:12px; margin-bottom:2px;"><?= h($s['subject_name']) ?></div>
                            <div style="font-size:10px; color:var(--muted); line-height:1.2;">
                                <?= h($s['class_name']) ?> | <?= h($s['room'] ?: '-') ?>
                            </div>
                            <div style="font-size:10px; color:var(--primary); font-weight:600; margin-top:2px;">
                                <?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?>
                            </div>
                            
                            <?php if($canAdd): ?>
                            <div class="delete-btn" onclick="deleteSchedule(<?= $s['schedule_id'] ?>, this.parentElement)" style="position:absolute; top:5px; right:5px; padding:2px 5px; background:rgba(239, 68, 68, 0.1); color:#ef4444; border-radius:4px; font-size:10px; cursor:pointer;">
                                <i class="fas fa-trash"></i>
                            </div>
                            <?php endif; ?>
                          </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                      </div>
                    <?php endfor; ?>
                  <?php endforeach; ?>
                </div>
            </div>
        </div>
      </div>
    </div>

    <!-- Hidden table for Excel export -->
    <table id="scheduleTable" style="display:none;">
      <thead>
        <tr>
          <th>Гариг</th>
          <th>Хичээл</th>
          <th>Анги</th>
          <th>Багш</th>
          <th>Эхлэх цаг</th>
          <th>Дуусах цаг</th>
          <th>Өрөө</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($days as $dayNum => $dayName): ?>
          <?php foreach($grid[$dayNum] ?? [] as $s): ?>
            <tr>
              <td><?= h($dayName) ?></td>
              <td><?= h($s['subject_name']) ?></td>
              <td><?= h($s['class_name']) ?></td>
              <td><?= h($s['teacher_name']) ?></td>
              <td><?= h(substr($s['start_time'],0,5)) ?></td>
              <td><?= h(substr($s['end_time'],0,5)) ?></td>
              <td><?= h($s['room']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- НЭМЭХ MODAL -->
<div class="modal-overlay" id="modalCreate">
  <div class="modal">
    <div class="modal-header"><h3><i class="fas fa-plus"></i> Хуваарь нэмэх</h3><button class="modal-close" onclick="closeModal('modalCreate')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group"><label>Анги *</label>
            <select name="class_id" class="form-control" required>
              <option value="" data-room="">Сонгох...</option>
              <?php foreach($classes as $c): ?><option value="<?= $c['class_id'] ?>" data-room="<?= h($c['room'] ?? '') ?>"><?= h($c['class_name']) ?></option><?php endforeach; ?>
            </select>
            <script>
                document.querySelector('select[name="class_id"]').addEventListener('change', function() {
                    const room = this.options[this.selectedIndex].getAttribute('data-room');
                    if (room) document.querySelector('input[name="room"]').value = room;
                });
            </script>
          </div>
          <div class="form-group"><label>Хичээл *</label>
            <select name="subject_id" class="form-control" required>
              <option value="">Сонгох...</option>
              <?php foreach($subjects as $s): ?><option value="<?= $s['subject_id'] ?>"><?= h($s['class_name'].' — '.$s['subject_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Багш *</label>
            <select name="teacher_id" class="form-control" required>
              <option value="">Сонгох...</option>
              <?php foreach($teachers as $t): ?><option value="<?= $t['user_id'] ?>"><?= h($t['full_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Гариг *</label>
            <select name="day_of_week" id="add_day" class="form-control" required>
              <?php foreach($days as $n=>$d): ?><option value="<?= $n ?>"><?= $d ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Эхлэх цаг *</label><input type="time" name="start_time" id="add_start" class="form-control" required></div>
          <div class="form-group"><label>Дуусах цаг *</label><input type="time" name="end_time" id="add_end" class="form-control" required></div>
          <div class="form-group"><label>Өрөө</label><input type="text" name="room" class="form-control" placeholder="201"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreate')">Болих</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<script>
async function autoSave(cell, day, hour) {
    const classId = document.getElementById('sel_class').value;
    const subjectId = document.getElementById('sel_subject').value;
    const teacherId = document.getElementById('sel_teacher').value;
    const room = document.getElementById('sel_room').value;

    if (!classId || !subjectId || !teacherId) {
        alert('Эхлээд Анги, Хичээл, Багшийг зүүн талын самбараас сонгоно уу!');
        return;
    }

    cell.style.opacity = '0.5';
    cell.style.pointerEvents = 'none';

    const formData = new FormData();
    formData.append('csrf', '<?= csrfToken() ?>');
    formData.append('class_id', classId);
    formData.append('subject_id', subjectId);
    formData.append('teacher_id', teacherId);
    formData.append('day', day);
    formData.append('start_time', (hour < 10 ? '0' : '') + hour + ':00');
    formData.append('room', room);

    try {
        const response = await fetch('/school_system1/api/schedule_save.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            // Instant UI update
            const item = document.createElement('div');
            item.className = 'schedule-item';
            item.style = 'background:rgba(59, 130, 246, 0.08); border-left:3px solid var(--primary); padding:8px; border-radius:8px; margin-bottom:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position:relative; overflow:hidden;';
            
            const subName = document.getElementById('sel_subject').options[document.getElementById('sel_subject').selectedIndex].text;
            const clsName = document.getElementById('sel_class').options[document.getElementById('sel_class').selectedIndex].text;
            
            item.innerHTML = `
                <div style="font-weight:700; font-size:12px; margin-bottom:2px;">${subName}</div>
                <div style="font-size:10px; color:var(--muted); line-height:1.2;">${clsName} | ${room || '-'}</div>
                <div style="font-size:10px; color:var(--primary); font-weight:600; margin-top:2px;">${(hour < 10 ? '0' : '') + hour + ':00'} - ${(hour + 1 < 10 ? '0' : '') + (hour + 1) + ':00'}</div>
                <div class="delete-btn" onclick="deleteSchedule(${result.id}, this.parentElement)" style="position:absolute; top:5px; right:5px; padding:2px 5px; background:rgba(239, 68, 68, 0.1); color:#ef4444; border-radius:4px; font-size:10px; cursor:pointer;">
                    <i class="fas fa-trash"></i>
                </div>
            `;
            cell.appendChild(item);
        } else {
            alert(result.error || 'Алдаа гарлаа');
        }
    } catch (e) {
        alert('Холболтын алдаа');
    } finally {
        cell.style.opacity = '1';
        cell.style.pointerEvents = 'auto';
    }
}

async function deleteSchedule(id, element) {
    if (!confirm('Энэ хуваарийг устгах уу?')) return;
    
    element.style.opacity = '0.3';
    const formData = new FormData();
    formData.append('csrf', '<?= csrfToken() ?>');
    formData.append('action', 'delete');
    formData.append('schedule_id', id);

    try {
        const response = await fetch('/school_system1/pages/schedule/index.php', {
            method: 'POST',
            body: formData
        });
        if (response.ok) {
            element.remove();
        } else {
            alert('Устгаж чадсангүй');
            element.style.opacity = '1';
        }
    } catch (e) {
        alert('Холболтын алдаа');
        element.style.opacity = '1';
    }
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

