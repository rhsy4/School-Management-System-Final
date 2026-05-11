<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['student']);

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam = dbOne("SELECT * FROM exams WHERE exam_id=? AND is_active=1", [$examId]);
if (!$exam) die('Шалгалт олдсонгүй эсвэл хаагдсан байна.');

$student = dbOne("SELECT student_id, class_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
$studentId = $student['student_id'] ?? 0;

if (!$studentId) {
    die('Таны сурагчийн бүртгэл олдсонгүй. Админд хандана уу.');
}

if ($exam['class_id'] && $exam['class_id'] != $student['class_id']) {
    die('Энэ шалгалт танай ангид зориулагдаагүй байна.');
}

// Шалгалт өгсөн эсэхийг шалгах
$check = dbOne("SELECT * FROM exam_results WHERE exam_id=? AND student_id=?", [$examId, $studentId]);
if ($check && $check['finished_at']) {
    header("Location: /school_system1/pages/exams/results.php?id=$examId");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    // ── Server-side хугацааны шалгалт ──────────────────────────────
    // JS-г disable хийсэн ч server дээр хугацаа хэтэрсэн эсэхийг шалгана
    $timeCheck = dbOne("SELECT started_at FROM exam_results WHERE exam_id=? AND student_id=?", [$examId, $studentId]);
    if ($timeCheck && $timeCheck['started_at']) {
        $elapsed = time() - strtotime($timeCheck['started_at']);
        $allowedSeconds = ($exam['duration_minutes'] * 60) + 60; // 60 секунд нэмэлт хүлцэл (сүлжээний удаашрал)
        if ($elapsed > $allowedSeconds) {
            setFlash('error', 'Шалгалтын хугацаа хэтэрсэн байна. Хариулт хүлээн авагдахгүй.');
            header("Location: /school_system1/pages/exams/results.php?id=$examId");
            exit;
        }
    }
    
    // Evaluate Result
    $totalScore = 0;
    $answers = $_POST['answers'] ?? [];
    
    foreach ($answers as $qId => $optId) {
        $q = dbOne("SELECT points FROM exam_questions WHERE question_id=? AND exam_id=?", [$qId, $examId]);
        if ($q) {
            $opt = dbOne("SELECT is_correct FROM exam_options WHERE option_id=? AND question_id=?", [$optId, $qId]);
            if ($opt && $opt['is_correct']) {
                $totalScore += $q['points'];
            }
        }
    }
    
    if ($check) {
        dbUpdate("UPDATE exam_results SET score=?, answers=?, finished_at=NOW() WHERE result_id=?", [$totalScore, json_encode($answers, JSON_UNESCAPED_UNICODE), $check['result_id']]);
    } else {
        dbExec("INSERT INTO exam_results (exam_id, student_id, score, answers, started_at, finished_at) VALUES (?,?,?,?,NOW(),NOW())", 
               [$examId, $studentId, $totalScore, json_encode($answers, JSON_UNESCAPED_UNICODE)]);
    }
    
    setFlash('success', 'Шалгалт амжилттай илгээгдлээ. Таны оноо: ' . $totalScore);
    header("Location: /school_system1/pages/exams/results.php?id=$examId");
    exit;
}

// Mark as started if not
if (!$check) {
    dbExec("INSERT INTO exam_results (exam_id, student_id, started_at) VALUES (?,?,NOW())", [$examId, $studentId]);
    $startedAt = time();
} else {
    $startedAt = strtotime($check['started_at']);
}

// Timer calculations
$elapsed = time() - $startedAt;
$remaining = ($exam['duration_minutes'] * 60) - $elapsed;
if ($remaining < 0) $remaining = 0;

$qList = dbQuery("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY RAND()", [$examId]);
$questions = [];
foreach ($qList as $q) {
    $q['options'] = dbQuery("SELECT * FROM exam_options WHERE question_id=? ORDER BY RAND()", [$q['question_id']]);
    $questions[] = $q;
}

$pageTitle = $exam['title'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="border-top:4px solid var(--primary)">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:var(--card-bg); z-index:10; border-bottom:2px solid var(--border); box-shadow:0 4px 10px rgba(0,0,0,0.05);">
    <h2 style="margin:0"><i class="fas fa-edit"></i> <?= h($exam['title']) ?></h2>
    <div style="font-size:24px; font-weight:bold; color:var(--danger)">
       <i class="fas fa-stopwatch"></i> <span id="timerText">--:--</span>
    </div>
  </div>
  
  <div class="card-body">
    <?php if ($exam['description']): ?>
      <p style="background:var(--bg); padding:15px; border-radius:8px; margin-bottom:15px;"><i class="fas fa-info-circle"></i> <?= h($exam['description']) ?></p>
    <?php endif; ?>
    
    <?php if (!empty($exam['file_url'])): ?>
      <div style="background:rgba(14, 165, 233, 0.1); border:1px solid #0ea5e9; padding:15px; border-radius:8px; margin-bottom:30px; display:flex; align-items:center; gap:15px;">
          <div style="font-size:30px; color:#0ea5e9;"><i class="fas fa-file-alt"></i></div>
          <div>
              <strong style="display:block; color:var(--text); margin-bottom:5px;">Шалгалтын материал</strong>
              <a href="<?= h($exam['file_url']) ?>" target="_blank" class="btn btn-sm btn-info" style="background:#0ea5e9; color:#fff; border:none;"><i class="fas fa-download"></i> Материалыг татах / Үзэх</a>
          </div>
      </div>
    <?php endif; ?>
    
    <form method="POST" id="examForm" action="?id=<?= $examId ?>" onsubmit="return confirm('Та шалгалтыг илгээхдээ итгэлтэй байна уу? Илгээсний дараа засах боломжгүй.');">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      
      <?php foreach ($questions as $i => $q): ?>
         <div style="margin-bottom:30px; border-bottom:1px solid var(--border); padding-bottom:20px;">
            <p style="font-size:18px; font-weight:bold; margin-bottom:15px;">
               <?= $i+1 ?>. <?= h($q['question_text']) ?> 
               <span class="badge badge-secondary" style="font-size:12px; font-weight:normal; vertical-align:middle;"><?= $q['points'] ?> оноо</span>
            </p>
            <div style="display:flex; flex-direction:column; gap:10px; padding-left:15px;">
                <?php foreach ($q['options'] as $o): ?>
                   <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:16px; padding:8px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
                      <input type="radio" name="answers[<?= $q['question_id'] ?>]" value="<?= $o['option_id'] ?>" style="width:20px; height:20px;">
                      <?= h($o['option_text']) ?>
                   </label>
                <?php endforeach; ?>
            </div>
         </div>
      <?php endforeach; ?>
      
      <div style="text-align:center; padding:20px 0;">
         <button type="submit" class="btn btn-primary" style="font-size:18px; padding:15px 40px;"><i class="fas fa-paper-plane"></i> Шалгалтыг илгээх</button>
      </div>
    </form>
  </div>
</div>

<script>
let remainingSeconds = <?= $remaining ?>;
const timerEl = document.getElementById('timerText');
const examForm = document.getElementById('examForm');

function updateTimer() {
    if (remainingSeconds <= 0) {
        timerEl.innerText = "00:00";
        alert("Хугацаа дууслаа. Таны хариултууд автоматаар илгээгдлээ.");
        // Submit instantly bypassing confirm via JS
        examForm.onsubmit = null;
        examForm.submit();
        return;
    }
    
    let m = Math.floor(remainingSeconds / 60);
    let s = remainingSeconds % 60;
    timerEl.innerText = (m < 10 ? '0'+m : m) + ':' + (s < 10 ? '0'+s : s);
    
    if (remainingSeconds <= 300) { // last 5 mins
        timerEl.parentElement.style.color = 'red';
        timerEl.parentElement.style.animation = 'pulse 1s infinite alternate';
    }
    
    remainingSeconds--;
    setTimeout(updateTimer, 1000);
}

document.head.insertAdjacentHTML("beforeend", `<style>
@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.5; transform: scale(1.05); }
}
</style>`);

updateTimer();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
