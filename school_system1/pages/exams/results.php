<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam = dbOne("SELECT * FROM exams WHERE exam_id=?", [$examId]);
if (!$exam) die('Шалгалт олдсонгүй.');

$isStudent = isStudent();
$isParent  = isParent();
$isTeacher = isTeacher();
$isManager = isManager();
$isAdmin   = isAdmin();

$studentId = 0;
if ($isStudent) {
    $stuRow = dbOne("SELECT student_id, class_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    $studentId = $stuRow ? $stuRow['student_id'] : 0;
    if ($exam['class_id'] && $exam['class_id'] != $stuRow['class_id']) {
        die('Энэ шалгалт танай ангид хамааралгүй байна.');
    }
} elseif ($isParent) {
    $studentId = $_SESSION['active_child_id'] ?? 0;
    if (!$studentId) die('Хүүхэд сонгогдоогүй байна.');
    $stuRow = dbOne("SELECT class_id FROM students WHERE student_id=?", [$studentId]);
    if ($exam['class_id'] && $exam['class_id'] != $stuRow['class_id']) {
        die('Энэ шалгалт таны хүүхдийн ангид хамааралгүй байна.');
    }
} elseif ($isTeacher && !$isManager && !$isAdmin) {
    // Багш бол зөвхөн өөрийн үүсгэсэн шалгалт эсвэл өөрийн ангийн шалгалтын дүнг харах эрхтэй
    $isMyClass = false;
    if ($exam['class_id']) {
        $checkClass = dbOne("SELECT class_id FROM classes WHERE class_id=? AND teacher_id=?", [$exam['class_id'], $_SESSION['user_id']]);
        if ($checkClass) $isMyClass = true;
    }
    if ($exam['created_by'] != $_SESSION['user_id'] && !$isMyClass) {
        die('Та зөвхөн өөрийн үүсгэсэн эсвэл өөрийн хариуцсан ангийн шалгалтын дүнг харах эрхтэй.');
    }
}

$pageTitle = $exam['title'] . ' - Үр дүн';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-chart-bar"></i> Шалгалтын үр дүн: <?= h($exam['title']) ?></h2>
  </div>
  <div class="card-body">
    <?php if ($flash = getFlash()) echo '<div class="flash flash-'.h($flash['type']).'">'.h($flash['msg']).'</div>'; ?>
    
    <?php if ($isStudent || $isParent): ?>
        <?php
        $res = dbOne("SELECT * FROM exam_results WHERE exam_id=? AND student_id=?", [$examId, $studentId]);
        if (!$res || !$res['finished_at']):
        ?>
            <div style="text-align:center; padding:40px; background:var(--bg); border-radius:8px;">
                <h3 style="color:var(--danger)"><i class="fas fa-times-circle"></i> Та энэ шалгалтыг өгөөгүй эсвэл дуусгаагүй байна.</h3>
                <a href="/school_system1/pages/exams/take.php?id=<?= $examId ?>" class="btn btn-primary" style="margin-top:15px;">Яг одоо өгөх</a>
            </div>
        <?php else: 
            $mRow = dbOne("SELECT SUM(points) as m FROM exam_questions WHERE exam_id=?", [$examId]);
            $maxScore = $mRow ? $mRow['m'] : 0;
            $percent = $maxScore > 0 ? round(($res['score'] / $maxScore) * 100, 1) : 0;
        ?>
            <div style="display:flex; justify-content:center; gap:30px; flex-wrap:wrap; margin-bottom:30px;">
                <div style="background:var(--bg); padding:30px; border-radius:12px; text-align:center; min-width:200px; border:1px solid var(--border)">
                    <h4 style="margin:0; color:var(--muted)">Нийт оноо</h4>
                    <div style="font-size:48px; font-weight:bold; color:var(--primary); margin:10px 0;">
                        <?= $res['score'] ?> / <?= $maxScore ?>
                    </div>
                </div>
                <div style="background:var(--bg); padding:30px; border-radius:12px; text-align:center; min-width:200px; border:1px solid var(--border)">
                    <h4 style="margin:0; color:var(--muted)">Гүйцэтгэл</h4>
                    <div style="font-size:48px; font-weight:bold; color:<?= $percent >= 60 ? 'var(--success)' : 'var(--danger)' ?>; margin:10px 0;">
                        <?= $percent ?>%
                    </div>
                </div>
            </div>
            <p style="text-align:center; color:var(--muted)">Илгээсэн хугацаа: <?= mnDateTime($res['finished_at']) ?></p>
        <?php endif; ?>

    <?php else: // Teachers / Admins ?>
        
        <?php
        $results = dbQuery("
            SELECT r.*, s.last_name, s.first_name, c.class_name
            FROM exam_results r
            JOIN students s ON r.student_id = s.student_id
            LEFT JOIN classes c ON s.class_id = c.class_id
            WHERE r.exam_id = ? AND r.finished_at IS NOT NULL
            ORDER BY r.score DESC
        ", [$examId]);
        
        $mRow = dbOne("SELECT SUM(points) as m FROM exam_questions WHERE exam_id=?", [$examId]);
        $maxScore = $mRow ? $mRow['m'] : 0;

        // Get total expected students
        $classFilter = $exam['class_id'];
        $allStudentsSql = "SELECT s.student_id, s.last_name, s.first_name, c.class_name 
                           FROM students s 
                           LEFT JOIN classes c ON s.class_id = c.class_id";
        $allParams = [];
        if ($classFilter) {
            $allStudentsSql .= " WHERE s.class_id = ?";
            $allParams[] = $classFilter;
        }
        $allExpectedStudents = dbQuery($allStudentsSql, $allParams);
        $totalExpected = count($allExpectedStudents);
        $takenIds = array_column($results, 'student_id');
        
        $pendingStudents = array_filter($allExpectedStudents, function($s) use ($takenIds) {
            return !in_array($s['student_id'], $takenIds);
        });

        // Questions for detailed view
        $examQuestions = dbQuery("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_id", [$examId]);
        $qOptions = [];
        if ($examQuestions) {
            $qIds = array_column($examQuestions, 'question_id');
            $placeholders = str_repeat('?,', count($qIds) - 1) . '?';
            $allOpts = dbQuery("SELECT * FROM exam_options WHERE question_id IN ($placeholders)", $qIds);
            foreach ($allOpts as $o) {
                $qOptions[$o['question_id']][] = $o;
            }
        }
        ?>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:30px;">
            <div style="background:var(--card-bg); padding:20px; border-radius:12px; border:1px solid var(--border); text-align:center;">
                <h4 style="margin:0; color:var(--muted); font-size:14px;">Нийт өгөх ёстой</h4>
                <div style="font-size:32px; font-weight:bold; color:var(--text); margin:5px 0;"><?= $totalExpected ?></div>
            </div>
            <div style="background:var(--card-bg); padding:20px; border-radius:12px; border:1px solid var(--border); text-align:center;">
                <h4 style="margin:0; color:var(--success); font-size:14px;">Өгсөн</h4>
                <div style="font-size:32px; font-weight:bold; color:var(--success); margin:5px 0;"><?= count($results) ?></div>
            </div>
            <div style="background:var(--card-bg); padding:20px; border-radius:12px; border:1px solid var(--border); text-align:center;">
                <h4 style="margin:0; color:var(--danger); font-size:14px;">Өгөөгүй</h4>
                <div style="font-size:32px; font-weight:bold; color:var(--danger); margin:5px 0;"><?= count($pendingStudents) ?></div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;"><i class="fas fa-check-circle"></i> Өгсөн сурагчид</h3>
            <a href="/school_system1/pages/exams/index.php" class="btn btn-secondary btn-sm">Буцах</a>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Сурагч</th>
                        <th>Анги</th>
                        <th>Авсан оноо</th>
                        <th>Гүйцэтгэл</th>
                        <th>Илгээсэн</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($results as $i => $r): 
                        $pct = $maxScore > 0 ? round(($r['score'] / $maxScore) * 100, 1) : 0;
                        $answersJson = $r['answers'] ?: '{}';
                    ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= h($r['last_name'] . ' ' . $r['first_name']) ?></strong></td>
                            <td><?= h($r['class_name'] ?? '-') ?></td>
                            <td style="font-weight:bold; color:var(--primary)"><?= $r['score'] ?> / <?= $maxScore ?></td>
                            <td>
                                <div style="background:var(--bg); height:8px; border-radius:4px; overflow:hidden; width:100px; display:inline-block; vertical-align:middle; margin-right:10px;">
                                    <div style="background:<?= $pct >= 60 ? 'var(--success)' : 'var(--danger)' ?>; width:<?= $pct ?>%; height:100%;"></div>
                                </div>
                                <?= $pct ?>%
                            </td>
                            <td><?= mnDateTime($r['finished_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick='viewResultDetails(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>, <?= htmlspecialchars($answersJson, ENT_QUOTES) ?>)'>
                                    <i class="fas fa-eye"></i> Хариулт
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$results): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--muted)">Шалгалт өгсөн сурагч алга.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pendingStudents): ?>
        <div style="margin-top:40px;">
            <h3 style="margin-bottom:15px; color:var(--danger);"><i class="fas fa-clock"></i> Өгөөгүй сурагчид (<?= count($pendingStudents) ?>)</h3>
            <div class="table-wrap">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Сурагч</th>
                            <th>Анги</th>
                            <th>Төлөв</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingStudents as $ps): ?>
                        <tr>
                            <td><?= h($ps['last_name'] . ' ' . $ps['first_name']) ?></td>
                            <td><?= h($ps['class_name'] ?? '-') ?></td>
                            <td><span class="badge badge-warning">Өгөөгүй</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detailed View Modal -->
        <div id="detailModal" onclick="if(event.target === this) closeResultModal()" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
            <div style="background:var(--card-bg); width:90%; max-width:800px; max-height:90vh; overflow-y:auto; border-radius:12px; padding:30px; position:relative;">
                <button onclick="closeResultModal()" style="position:absolute; top:15px; right:15px; border:none; background:none; font-size:24px; cursor:pointer; color:var(--muted);">&times;</button>
                <h3 id="modalTitle" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:15px; margin-bottom:20px;"></h3>
                <div id="modalContent"></div>
                <div style="margin-top:30px; text-align:right; border-top:1px solid var(--border); padding-top:20px;">
                    <button class="btn btn-secondary" onclick="closeResultModal()">Хаах</button>
                </div>
            </div>
        </div>

        <script>
        const questions = <?= json_encode($examQuestions) ?>;
        const qOptions = <?= json_encode($qOptions) ?>;

        function viewResultDetails(res, answers) {
            document.getElementById('modalTitle').innerText = res.last_name + ' ' + res.first_name + ' - Хариултууд';
            let html = '';
            questions.forEach((q, i) => {
                const studentAns = answers[q.question_id] || null;
                const opts = qOptions[q.question_id] || [];
                
                html += `<div style="margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:15px;">`;
                html += `<p><strong>${i+1}. ${q.question_text}</strong> <span class="badge badge-secondary">${q.points} оноо</span></p>`;
                
                opts.forEach(opt => {
                    const isSelected = studentAns == opt.option_id;
                    const isCorrect = opt.is_correct == 1;
                    let style = 'padding:8px; border-radius:4px; margin-bottom:5px; display:flex; align-items:center; gap:10px; border:1px solid transparent;';
                    let icon = '<i class="far fa-circle"></i>';
                    
                    if (isSelected && isCorrect) {
                        style += 'background:rgba(16,185,129,0.1); border-color:var(--success); color:var(--success);';
                        icon = '<i class="fas fa-check-circle"></i>';
                    } else if (isSelected && !isCorrect) {
                        style += 'background:rgba(239,68,68,0.1); border-color:var(--danger); color:var(--danger);';
                        icon = '<i class="fas fa-times-circle"></i>';
                    } else if (!isSelected && isCorrect) {
                        style += 'background:rgba(16,185,129,0.05); border-color:var(--success); color:var(--success); border-style:dashed;';
                        icon = '<i class="far fa-check-circle"></i>';
                    }
                    
                    html += `<div style="${style}">${icon} ${opt.option_text}</div>`;
                });
                html += `</div>`;
            });
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeResultModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
        </script>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
