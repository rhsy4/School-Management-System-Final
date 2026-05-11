<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'manager', 'director', 'teacher']);

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam = null;
$questions = [];

if ($examId) {
    $exam = dbOne("SELECT * FROM exams WHERE exam_id=? ", [$examId]);
    if (!$exam) {
        die('Шалгалт олдсонгүй.');
    }
    // Security: Only creator or manager can edit
    if (!isManager() && $exam['created_by'] != $_SESSION['user_id']) {
        die('Эрх хүрэхгүй байна.');
    }

    // Fetch Questions & Options efficiently
    $qList = dbQuery("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_id", [$examId]);
    $allOptions = dbQuery("SELECT * FROM exam_options WHERE question_id IN (SELECT question_id FROM exam_questions WHERE exam_id=?)", [$examId]);
    
    // Group options by question_id
    $optGroup = [];
    foreach ($allOptions as $o) {
        $optGroup[$o['question_id']][] = $o;
    }
    
    foreach ($qList as $q) {
        $q['options'] = $optGroup[$q['question_id']] ?? [];
        $questions[] = $q;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_exam') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $subj = (int)$_POST['subject_id'];
        $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $dur = (int)$_POST['duration_minutes'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        
        $fileUrl = $exam['file_url'] ?? null;
        if (isset($_FILES['exam_file']) && $_FILES['exam_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/exams/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_FILES['exam_file']['name']);
            if (move_uploaded_file($_FILES['exam_file']['tmp_name'], $uploadDir . $fileName)) {
                $fileUrl = '/school_system1/uploads/exams/' . $fileName;
            }
        }

        if (!$title || !$subj) {
            setFlash('error', 'Гарчиг болон хичээл сонгоно уу.');
        } else {
            if ($examId) {
                dbUpdate("UPDATE exams SET title=?, description=?, subject_id=?, class_id=?, duration_minutes=?, is_active=?, file_url=? WHERE exam_id=?", 
                    [$title, $desc, $subj, $classId, $dur, $active, $fileUrl, $examId]);
                setFlash('success', 'Шалгалтын тохиргоо хадгалагдлаа.');
            } else {
                $examId = dbExec("INSERT INTO exams (title, description, subject_id, class_id, duration_minutes, is_active, created_by, file_url) VALUES (?,?,?,?,?,?,?,?)",
                    [$title, $desc, $subj, $classId, $dur, $active, $_SESSION['user_id'], $fileUrl]);
                setFlash('success', 'Шинэ шалгалт үүслээ. Асуултуудаа оруулна уу.');
            }
            header("Location: /school_system1/pages/exams/manage.php?id=$examId");
            exit;
        }
    }
    
    if ($action === 'add_question') {
        $qText = trim($_POST['question_text']);
        $points = (int)$_POST['points'];
        if ($qText && $examId) {
            $qid = dbExec("INSERT INTO exam_questions (exam_id, question_text, points) VALUES (?,?,?)", [$examId, $qText, $points]);
            
            // Handle options
            $opts = $_POST['options'] ?? [];
            $correctIdx = (int)($_POST['correct_option'] ?? 0);
            
            foreach ($opts as $idx => $optText) {
                if (trim($optText)) {
                    $isCorrect = ($idx == $correctIdx) ? 1 : 0;
                    dbExec("INSERT INTO exam_options (question_id, option_text, is_correct) VALUES (?,?,?)", [$qid, trim($optText), $isCorrect]);
                }
            }
            setFlash('success', 'Асуулт нэмэгдлээ.');
        }
        header("Location: /school_system1/pages/exams/manage.php?id=$examId");
        exit;
    }

    if ($action === 'delete_question') {
        $qid = (int)$_POST['question_id'];
        dbUpdate("DELETE FROM exam_questions WHERE question_id=? AND exam_id=?", [$qid, $examId]);
        setFlash('success', 'Асуулт устгагдлаа.');
        header("Location: /school_system1/pages/exams/manage.php?id=$examId");
        exit;
    }

    if ($action === 'edit_question') {
        $qid = (int)$_POST['question_id'];
        $qText = trim($_POST['question_text']);
        $points = (int)$_POST['points'];
        
        if ($qid && $qText) {
            dbUpdate("UPDATE exam_questions SET question_text=?, points=? WHERE question_id=? AND exam_id=?", [$qText, $points, $qid, $examId]);
            
            // Delete old options and insert new ones
            dbUpdate("DELETE FROM exam_options WHERE question_id=?", [$qid]);
            $opts = $_POST['options'] ?? [];
            $correctIdx = (int)($_POST['correct_option'] ?? 0);
            foreach ($opts as $idx => $optText) {
                if (trim($optText)) {
                    $isCorrect = ($idx == $correctIdx) ? 1 : 0;
                    dbExec("INSERT INTO exam_options (question_id, option_text, is_correct) VALUES (?,?,?)", [$qid, trim($optText), $isCorrect]);
                }
            }
            setFlash('success', 'Асуулт шинэчлэгдлээ.');
        }
        header("Location: /school_system1/pages/exams/manage.php?id=$examId");
        exit;
    }
}

// Fetch subjects and classes for dropdowns
$subjects = dbQuery("SELECT * FROM subjects ORDER BY subject_name");
$classes = dbQuery("SELECT * FROM classes ORDER BY class_name");

$pageTitle = $exam ? 'Шалгалт засах' : 'Шинэ шалгалт';
include __DIR__ . '/../../includes/header.php';
?>

<div class="responsive-grid" style="align-items:flex-start;">

  <!-- Exam Details -->
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header">
      <h2><i class="fas fa-edit"></i> <?= $exam ? 'Шалгалтын тохиргоо' : 'Шинэ шалгалт үүсгэх' ?></h2>
    </div>
    <div class="card-body">
      <?php if ($flash = getFlash()) echo '<div class="flash flash-'.h($flash['type']).'">'.h($flash['msg']).'</div>'; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="save_exam">
        
        <div class="form-group">
          <label><i class="fas fa-heading"></i> Гарчиг *</label>
          <input type="text" name="title" class="form-control" value="<?= h($exam['title'] ?? '') ?>" placeholder="Жишээ: Математикийн түвшин тогтоох..." required>
        </div>
        
        <div class="form-group">
          <label><i class="fas fa-align-left"></i> Тайлбар</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Шалгалтын талаарх товч заавар, тайлбар..."><?= h($exam['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label><i class="fas fa-book"></i> Хичээл *</label>
            <select name="subject_id" class="form-control" required>
              <option value="">Сонгох...</option>
              <?php foreach($subjects as $s): ?>
                 <option value="<?= $s['subject_id'] ?>" <?= ($exam && $exam['subject_id']==$s['subject_id']) ? 'selected' : '' ?>><?= h($s['subject_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label><i class="fas fa-users"></i> Зориулалт (Анги)</label>
            <select name="class_id" class="form-control">
              <option value="">-- Бүх ангид (Нээлттэй) --</option>
              <?php foreach($classes as $c): ?>
                 <option value="<?= $c['class_id'] ?>" <?= ($exam && $exam['class_id']==$c['class_id']) ? 'selected' : '' ?>><?= h($c['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label><i class="fas fa-clock"></i> Үргэлжлэх хугацаа (Минут)</label>
            <input type="number" name="duration_minutes" class="form-control" value="<?= h($exam['duration_minutes'] ?? 60) ?>" min="5" required>
          </div>
          <div class="form-group" style="display:flex; align-items:center; margin-top:25px;">
            <label style="cursor:pointer; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="is_active" <?= (!$exam || $exam['is_active']) ? 'checked' : '' ?> style="width:20px; height:20px;">
                Идэвхтэй (Сурагчдад харагдах)
            </label>
          </div>
        </div>
        
        <div class="form-group">
            <label><i class="fas fa-paperclip"></i> Шалгалтын материал (Файл хавсаргах)</label>
            <input type="file" name="exam_file" class="form-control">
            <?php if (!empty($exam['file_url'])): ?>
                <div style="margin-top:5px; font-size:12px;">
                    <a href="<?= h($exam['file_url']) ?>" target="_blank" style="color:var(--primary)"><i class="fas fa-file-download"></i> Одоогийн хавсралт татах</a>
                </div>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
        <a href="/school_system1/pages/exams/index.php" class="btn btn-secondary">Буцах</a>
      </form>
    </div>
  </div>

  <!-- Questions (if exam saved) -->
  <?php if ($exam): ?>
  <div class="card" style="flex:2; min-width:400px;">
      <div class="card-header">
         <h2><i class="fas fa-list-ul"></i> Асуултууд (<?= count($questions) ?>)</h2>
      </div>
      <div class="card-body">
         <div class="q-container">
         <?php foreach ($questions as $i => $q): ?>
            <div class="card" style="margin-bottom:15px; border:1px solid var(--border); transition: transform 0.2s ease;">
                <div class="card-body" style="padding:15px; position:relative;">
                    <div style="position:absolute; top:15px; right:15px; display:flex; gap:10px; z-index:5;">
                        <button class="btn btn-sm btn-secondary" onclick='openEditModal(<?= json_encode($q) ?>)' title="Засах"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Энэ асуултыг устгах уу?');">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Устгах"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                   <div style="margin-bottom:12px; display:flex; align-items:flex-start; gap:10px; padding-right:80px;">
                       <span class="badge badge-primary" style="border-radius:6px;"><?= $i+1 ?></span>
                       <strong style="font-size:16px; color:var(--text);"><?= h($q['question_text']) ?></strong>
                       <span class="badge badge-secondary"><?= $q['points'] ?> оноо</span>
                   </div>
                   <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-top:10px;">
                       <?php foreach ($q['options'] as $o): ?>
                           <div style="padding:8px 12px; border-radius:8px; background:<?= $o['is_correct'] ? 'rgba(16,185,129,0.1)' : 'var(--input-bg)' ?>; border:1px solid <?= $o['is_correct'] ? 'var(--success)' : 'var(--border)' ?>; font-size:14px; display:flex; align-items:center; gap:8px;">
                               <i class="fas <?= $o['is_correct'] ? 'fa-check-circle text-success' : 'fa-circle-notch text-muted' ?>"></i>
                               <span style="<?= $o['is_correct'] ? 'font-weight:bold; color:var(--success);' : '' ?>"><?= h($o['option_text']) ?></span>
                           </div>
                       <?php endforeach; ?>
                   </div>
                </div>
            </div>
         <?php endforeach; ?>
         </div>
         
         <!-- Add Question Form -->
         <div style="background:var(--card-bg); border:2px dashed var(--border); padding:24px; border-radius:12px; margin-top:30px;">
             <h3 style="margin-top:0; color:var(--primary);"><i class="fas fa-plus-circle"></i> Шинэ асуулт нэмэх</h3>
             <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add_question">
                
                <div class="form-group">
                   <label><i class="fas fa-question-circle"></i> Асуулт *</label>
                   <textarea name="question_text" class="form-control" rows="3" placeholder="Асуултын текст..." required></textarea>
                </div>
                <div class="form-group">
                   <label><i class="fas fa-star"></i> Оноо</label>
                   <input type="number" name="points" class="form-control" value="1" min="1" style="width:120px;">
                </div>
                
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
                    <label style="margin:0;"><i class="fas fa-tasks"></i> Хариултын сонголтууд</label>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addOptionField('add_options_container')"><i class="fas fa-plus"></i> Сонголт нэмэх</button>
                </div>
                
                <div id="add_options_container" style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                    <!-- Initial options -->
                    <div class="option-row" style="display:flex; gap:12px; align-items:center;">
                        <input type="radio" name="correct_option" value="0" checked title="Зөв хариу" style="width:20px; height:20px; cursor:pointer;">
                        <input type="text" name="options[0]" class="form-control" placeholder="Хариулт 1..." required>
                    </div>
                    <div class="option-row" style="display:flex; gap:12px; align-items:center;">
                        <input type="radio" name="correct_option" value="1" title="Зөв хариу" style="width:20px; height:20px; cursor:pointer;">
                        <input type="text" name="options[1]" class="form-control" placeholder="Хариулт 2..." required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success" style="width:100%;"><i class="fas fa-save"></i> Асуулт хадгалах</button>
             </form>
         </div>
      </div>
  </div>
  <?php endif; ?>

</div>

<!-- Edit Question Modal -->
<div class="modal-overlay" id="modalEditQuestion">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Асуулт засах</h3>
            <button class="modal-close" onclick="closeModal('modalEditQuestion')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="edit_question">
                <input type="hidden" name="question_id" id="edit_qid">
                
                <div class="form-group">
                    <label>Асуулт *</label>
                    <textarea name="question_text" id="edit_qtext" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Оноо</label>
                    <input type="number" name="points" id="edit_points" class="form-control" value="1" min="1" style="width:100px;">
                </div>
                
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
                    <label style="margin:0;"><i class="fas fa-tasks"></i> Хариултын сонголтууд</label>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addOptionField('edit_options_container')"><i class="fas fa-plus"></i> Сонголт нэмэх</button>
                </div>
                <div id="edit_options_container" style="display:flex; flex-direction:column; gap:12px;">
                    <!-- Filled by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditQuestion')">Болих</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
            </div>
        </form>
    </div>
</div>

<script>
function addOptionField(containerId, initialValue = '', isCorrect = false) {
    const container = document.getElementById(containerId);
    const index = container.children.length;
    const row = document.createElement('div');
    row.className = 'option-row';
    row.style = 'display:flex; gap:12px; align-items:center; opacity:0; transform:translateX(-10px); transition:all 0.3s ease;';
    
    row.innerHTML = `
        <input type="radio" name="correct_option" value="${index}" ${isCorrect ? 'checked' : ''} style="width:20px; height:20px; cursor:pointer;">
        <input type="text" name="options[${index}]" class="form-control" value="${initialValue}" placeholder="Хариулт ${index + 1}..." required>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding:8px 10px;"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
    setTimeout(() => { row.style.opacity = '1'; row.style.transform = 'translateX(0)'; }, 10);
}

function openEditModal(q) {
    document.getElementById('edit_qid').value = q.question_id;
    document.getElementById('edit_qtext').value = q.question_text;
    document.getElementById('edit_points').value = q.points;
    
    const container = document.getElementById('edit_options_container');
    container.innerHTML = '';
    
    q.options.forEach((opt, idx) => {
        addOptionField('edit_options_container', opt.option_text, opt.is_correct == 1);
    });
    
    openModal('modalEditQuestion');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
