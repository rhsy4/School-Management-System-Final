<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['teacher','manager','director','admin']);

$pageTitle    = 'Ирцийн бүртгэл';
$user_id      = $_SESSION['user_id'];
$isTeacher    = isTeacher();
$isManager    = isManager();

// Багшийн ID
$myTeacherId = null;
if ($isTeacher) {
    $t = dbOne("SELECT teacher_id FROM teachers WHERE user_id=?", [$user_id]);
    $myTeacherId = $t['teacher_id'] ?? null;
}

// ── POST: Бөөн ирц хадгалах ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    if ($_POST['action'] === 'bulk_save') {
        $subjectId = (int)$_POST['subject_id'];
        $date      = $_POST['att_date'];
        $statuses  = $_POST['statuses'] ?? [];
        $notes     = $_POST['notes'] ?? [];

        // Огноо хүчинтэй эсэх
        if (!$subjectId || !$date || !strtotime($date)) {
            setFlash('error', 'Хичээл болон огноог зөв сонгоно уу.');
            header("Location: /school_system1/pages/attendance/bulk.php");
            exit;
        }

        // Багш зөвхөн өөрийн хичээл дээр ирц бүртгэх эрхтэй
        if (isTeacher() && !isManager() && !isAdmin()) {
            $isMySubject = dbOne("SELECT subject_id FROM subjects WHERE subject_id=? AND teacher_id=?", [$subjectId, $user_id]);
            if (!$isMySubject) {
                setFlash('error', 'Та зөвхөн өөрийн заадаг хичээлийн ирцийг бүртгэх эрхтэй.');
                header("Location: /school_system1/pages/attendance/bulk.php");
                exit;
            }
        }

        $saved = 0;
        $errors = 0;
        foreach ($statuses as $studentId => $statusId) {
            $studentId = (int)$studentId;
            $statusId  = (int)$statusId;
            $note      = trim($notes[$studentId] ?? '');
            if (!$studentId || !$statusId) continue;

            try {
                // Давхардсан бол шинэчлэх
                $existing = dbOne("SELECT attendance_id FROM attendance WHERE student_id=? AND subject_id=? AND date=?",
                                  [$studentId, $subjectId, $date]);
                if ($existing) {
                    dbUpdate("UPDATE attendance SET status_id=?, note=?, recorded_by=? WHERE attendance_id=?",
                             [$statusId, $note, $user_id, $existing['attendance_id']]);
                } else {
                    dbExec("INSERT INTO attendance (student_id, subject_id, date, status_id, recorded_by, note) VALUES (?,?,?,?,?,?)",
                           [$studentId, $subjectId, $date, $statusId, $user_id, $note]);
                }
                $saved++;
            } catch (Exception $e) {
                $errors++;
            }
        }

        auditLog('bulk_attendance', $subjectId, "date=$date saved=$saved");
        
        // Smart Alert + Email илгээх (Зөвхөн тасалсан хүүхдүүд болон эцэг эхэд)
        require_once __DIR__ . '/../../includes/notification.php';
        $notifier = new NotificationService();

        foreach ($statuses as $studentId => $statusId) {
            if ($statusId == 2) { // 2 = Тасалсан
                $stuInfo = dbOne("SELECT s.*, sub.subject_name FROM students s, subjects sub WHERE s.student_id=? AND sub.subject_id=?", [$studentId, $subjectId]);
                if ($stuInfo) {
                    $msg = $date . "-ны өдрийн " . $stuInfo['subject_name'] . " хичээлд ирээгүй байна. Таслалт бүртгэгдлээ.";
                    sendSmartAlert($stuInfo['user_id'], $msg);
                    if ($stuInfo['parent_id']) {
                        sendSmartAlert($stuInfo['parent_id'], "Таны хүүхэд " . $stuInfo['first_name'] . " " . $date . "-ны өдрийн " . $stuInfo['subject_name'] . " хичээлийг тасалсан байна.");
                    }
                    // 📧 Эцэг эхэд email мэдэгдэл мөн илгээх
                    $notifier->notifyAbsentStudentParents((int)$studentId, $subjectId, $date);
                }
            }
        }

        $msg = $saved . ' сурагчийн ирц бүртгэгдлээ. Тасалсан сурагчдын эцэг эхэд мэдэгдэл илгээгдлээ.';
        if ($errors) $msg .= " ($errors алдаа гарлаа)";
        setFlash($errors ? 'warning' : 'success', $msg);
        header("Location: /school_system1/pages/attendance/bulk.php?subject_id=$subjectId&att_date=$date");
        exit;
    }
}

// ── Хичээлүүдийн жагсаалт ───────────────────────────────────
if ($isManager) {
    $subjects = dbQuery("SELECT sub.subject_id, sub.subject_name, c.class_name,
                                CONCAT(t.last_name,' ',t.first_name) AS teacher_name
                         FROM subjects sub
                         JOIN classes c  ON sub.class_id  = c.class_id
                         JOIN teachers t ON sub.teacher_id = t.user_id
                         ORDER BY c.class_name, sub.subject_name");
} else {
    $subjects = dbQuery("SELECT sub.subject_id, sub.subject_name, c.class_name
                         FROM subjects sub
                         JOIN classes c ON sub.class_id = c.class_id
                         WHERE sub.teacher_id = ?
                         ORDER BY c.class_name, sub.subject_name", [$user_id]);
}

// ── Сонгосон хичээлийн сурагчид ──────────────────────────────
$selSubjectId = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
$selDate      = $_GET['att_date'] ?? date('Y-m-d');

$students = [];
$existingAtt = [];
$selSubject = null;

if ($selSubjectId) {
    $selSubject = dbOne("SELECT sub.*, c.class_id, c.class_name,
                                CONCAT(t.last_name,' ',t.first_name) AS teacher_name
                         FROM subjects sub
                         JOIN classes c  ON sub.class_id  = c.class_id
                         JOIN teachers t ON sub.teacher_id = t.user_id
                         WHERE sub.subject_id = ?", [$selSubjectId]);

    if ($selSubject) {
        $students = dbQuery("SELECT s.student_id, s.first_name, s.last_name
                             FROM students s
                             WHERE s.class_id = ? AND s.is_active = 1
                             ORDER BY s.last_name, s.first_name", [$selSubject['class_id']]);

        // Байгаа ирцийн бүртгэл
        $rows = dbQuery("SELECT student_id, status_id, note FROM attendance
                         WHERE subject_id=? AND date=?", [$selSubjectId, $selDate]);
        foreach ($rows as $r) {
            $existingAtt[$r['student_id']] = $r;
        }

        // ── EXCEL EXPORT (CSV) ───────────────────────────────────
        if (isset($_GET['export']) && $_GET['export'] === 'excel') {
            $filename = "attendance_" . $selSubject['class_name'] . "_" . $selDate . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($output, ['#', 'Овог Нэр', 'Төлөв', 'Тэмдэглэл']);
            $labels = [1=>'Ирсэн', 2=>'Тасалсан', 3=>'Өвчтэй'];
            foreach ($students as $i => $s) {
                $st = $existingAtt[$s['student_id']]['status_id'] ?? 1;
                fputcsv($output, [
                    $i+1,
                    $s['last_name'] . ' ' . $s['first_name'],
                    $labels[$st] ?? 'Тодорхойгүй',
                    $existingAtt[$s['student_id']]['note'] ?? ''
                ]);
            }
            fclose($output);
            exit;
        }
    }
}

$statusList = dbQuery("SELECT * FROM attendance_status ORDER BY status_id");

include __DIR__ . '/../../includes/header.php';
?>

<style>
.bulk-table td, .bulk-table th { padding:10px 12px; vertical-align:middle; }
.bulk-table tbody tr:hover { background: var(--bg); }
.att-radio-group { display:flex; gap:6px; flex-wrap:wrap; }
.att-radio { display:none; }
.att-label {
    padding:5px 14px;
    border-radius:20px;
    border: 2px solid var(--border);
    cursor:pointer;
    font-size:12px;
    font-weight:600;
    transition: all .15s;
    white-space: nowrap;
}
.att-radio[value="1"]:checked + .att-label { background:#d1fae5; border-color:#10b981; color:#059669; }
.att-radio[value="2"]:checked + .att-label { background:#fde8e8; border-color:#ef4444; color:#dc2626; }
.att-radio[value="3"]:checked + .att-label { background:#fef3c7; border-color:#f59e0b; color:#d97706; }
.att-label:hover { border-color:var(--primary); color:var(--primary); }
.quick-btn {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--card-bg);
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all .15s;
}
.quick-btn:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
.summary-bar {
    display:flex; gap:16px; flex-wrap:wrap;
    background:var(--bg); border:1px solid var(--border);
    border-radius:10px; padding:12px 20px; margin-bottom:20px;
    font-size:13px; font-weight:600;
}
.summary-bar span { display:flex; align-items:center; gap:6px; }
@media print {
    .btn, .filter-bar, nav, footer, .card-header .btn, .summary-bar, .modal-overlay { display: none !important; }
    body { background: white !important; }
    .card { border: none !important; box-shadow: none !important; }
    .bulk-table { border: 1px solid #000 !important; }
    .bulk-table input { border: none !important; }
    .att-label { border: 1px solid #ddd !important; -webkit-print-color-adjust: exact; }
    .card-header h2 { display: block !important; }
}
@media print {
    .btn, .filter-bar, nav, footer, .card-header .btn, .summary-bar, .modal-overlay { display: none !important; }
    body { background: white !important; }
    .card { border: none !important; box-shadow: none !important; }
    .bulk-table { border: 1px solid #000 !important; }
    .bulk-table input { border: none !important; }
    .att-label { border: 1px solid #ddd !important; -webkit-print-color-adjust: exact; }
}
</style>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clipboard-list"></i> Ирцийн бүртгэл</h2>
        <a href="/school_system1/pages/attendance/index.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Ирцийн хуудас
        </a>
    </div>
    <div class="card-body">

        <!-- Хичээл & Огноо сонгох -->
        <form method="GET" id="filterForm" style="background:var(--bg); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:24px;">
            <div style="display:grid; grid-template-columns:2fr 1fr auto; gap:16px; align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label><i class="fas fa-book"></i> Хичээл сонгох</label>
                    <select name="subject_id" class="form-control" onchange="this.form.submit()" required>
                        <option value="">-- Хичээл сонгоно уу --</option>
                        <?php
                        $lastClass = '';
                        foreach ($subjects as $sub):
                            if ($sub['class_name'] !== $lastClass) {
                                if ($lastClass) echo '</optgroup>';
                                echo '<optgroup label="' . h($sub['class_name']) . '">';
                                $lastClass = $sub['class_name'];
                            }
                        ?>
                            <option value="<?= $sub['subject_id'] ?>"
                                <?= $selSubjectId == $sub['subject_id'] ? 'selected' : '' ?>>
                                <?= h($sub['subject_name']) ?>
                                <?= $isManager ? ' — ' . h($sub['teacher_name'] ?? '') : '' ?>
                            </option>
                        <?php endforeach; if ($lastClass) echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label><i class="fas fa-calendar"></i> Огноо</label>
                    <input type="date" name="att_date" class="form-control"
                        value="<?= h($selDate) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Харах
                </button>
            </div>
        </form>

        <?php if ($selSubject && !empty($students)): ?>

        <!-- LIVE SUMMARY BAR -->
        <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:150px; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:50%; background:rgba(16, 185, 129, 0.1); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-check"></i></div>
                <div><div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700;">Ирсэн</div><div style="font-size:20px; font-weight:800;" id="count-present">0</div></div>
            </div>
            <div style="flex:1; min-width:150px; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:50%; background:rgba(239, 68, 68, 0.1); color:#ef4444; display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-times"></i></div>
                <div><div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700;">Тасалсан</div><div style="font-size:20px; font-weight:800;" id="count-absent">0</div></div>
            </div>
            <div style="flex:1; min-width:150px; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:50%; background:rgba(245, 158, 11, 0.1); color:#f59e0b; display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-head-side-mask"></i></div>
                <div><div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700;">Өвчтэй</div><div style="font-size:20px; font-weight:800;" id="count-sick">0</div></div>
            </div>
        </div>

        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; box-shadow:var(--shadow);">
            <div style="padding:20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:var(--bg);">
                <div>
                    <h3 style="font-size:15px; font-weight:700; margin:0;"><?= h($selSubject['class_name']) ?> — <?= h($selSubject['subject_name']) ?></h3>
                    <div style="font-size:12px; color:var(--muted);"><?= mnDate($selDate) ?></div>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="?subject_id=<?= $selSubjectId ?>&att_date=<?= $selDate ?>&export=excel" class="btn btn-sm" style="background:#27ae60; color:white;"><i class="fas fa-file-excel"></i> Excel татах</a>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Хэвлэх/PDF</button>
                    <button type="button" class="btn btn-sm btn-success" onclick="setAll(1)" style="border-radius:20px;"><i class="fas fa-check-double"></i> Бүгдийг ирсэн</button>
                </div>
            </div>

            <form method="POST" id="bulkForm">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="bulk_save">
                <input type="hidden" name="subject_id" value="<?= $selSubjectId ?>">
                <input type="hidden" name="att_date" value="<?= h($selDate) ?>">

                <div style="overflow-x:auto;">
                <table class="bulk-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--table-head);">
                            <th style="width:60px; text-align:center; font-size:11px; color:var(--muted); text-transform:uppercase;">#</th>
                            <th style="font-size:11px; color:var(--muted); text-transform:uppercase;">Сурагч</th>
                            <th style="font-size:11px; color:var(--muted); text-transform:uppercase;">Бүртгэл (Ирсэн / Тасалсан / Өвчтэй)</th>
                            <th style="font-size:11px; color:var(--muted); text-transform:uppercase;">Нэмэлт тэмдэглэл</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $s):
                        $exist = $existingAtt[$s['student_id']] ?? null;
                        $curStatus = $exist['status_id'] ?? 1;
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="text-align:center; opacity:0.5;"><?= $i+1 ?></td>
                        <td>
                            <div style="font-weight:700; color:var(--primary);"><?= h($s['last_name'] . ' ' . $s['first_name']) ?></div>
                            <?php if ($exist): ?>
                            <div style="font-size:10px; color:var(--success);"><i class="fas fa-check"></i> Хадгалагдсан</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="att-radio-group">
                                <?php foreach ($statusList as $st): 
                                    $labels = [1=>'Ирсэн', 2=>'Тасалсан', 3=>'Өвчтэй'];
                                    $id = "r_{$s['student_id']}_{$st['status_id']}";
                                ?>
                                <input type="radio" class="att-radio"
                                       name="statuses[<?= $s['student_id'] ?>]"
                                       value="<?= $st['status_id'] ?>"
                                       id="<?= $id ?>"
                                       <?= $curStatus == $st['status_id'] ? 'checked' : '' ?>>
                                <label class="att-label" for="<?= $id ?>"><?= $labels[$st['status_id']] ?? $st['status_name'] ?></label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="notes[<?= $s['student_id'] ?>]"
                                   class="form-control" style="background:var(--input-bg); border:1px solid var(--border); font-size:12px; height:32px; border-radius:6px;"
                                   placeholder="Тайлбар..."
                                   value="<?= h($exist['note'] ?? '') ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div style="padding:25px; background:var(--card-bg); border-top:1px solid var(--border); display:flex; justify-content:flex-end;">

                    <button type="submit" class="btn btn-primary btn-lg" style="padding:12px 40px; border-radius:12px; box-shadow:0 4px 6px -1px var(--primary-light);">
                        <i class="fas fa-save"></i> БҮХ ИРЦИЙГ ХАДГАЛАХ
                    </button>
                </div>
            </form>
        </div>

        <?php elseif ($selSubjectId && empty($students)): ?>
        <div style="text-align:center; padding:60px; background:var(--card-bg); border-radius:16px; border:1px solid var(--border);">
            <i class="fas fa-user-slash" style="font-size:48px; color:var(--muted); opacity:.2;"></i>
            <p style="margin-top:15px; font-weight:600; color:var(--muted);">Сонгосон хичээлд сурагч бүртгэгдээгүй байна.</p>
        </div>
        <?php elseif (!$selSubjectId): ?>
        <div style="text-align:center; padding:80px 20px; background:var(--card-bg); border:1px solid var(--border); border-radius:16px; border:2px dashed var(--border);">
            <i class="fas fa-book-reader" style="font-size:60px; color:var(--primary); opacity:.1; margin-bottom:20px; display:block;"></i>
            <h3 style="color:var(--muted);">Хичээлээ сонгоно уу</h3>
            <p style="color:var(--muted); font-size:14px; max-width:400px; margin:0 auto;">Ирц бүртгэхийн тулд эхлээд баруун дээрх цэснээс хичээл болон огноог сонгож "Харах" товчийг дарна уу.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function setAll(statusId) {
    document.querySelectorAll('.att-radio[value="' + statusId + '"]').forEach(function(radio) {
        radio.checked = true;
    });
}

// Live counter
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('att-radio') || e.target.name?.startsWith('statuses[')) {
        updateCounter();
    }
});

function updateCounter() {
    const present = document.querySelectorAll('.att-radio[value="1"]:checked').length;
    const absent  = document.querySelectorAll('.att-radio[value="2"]:checked').length;
    const sick    = document.querySelectorAll('.att-radio[value="3"]:checked').length;
    
    document.getElementById('count-present').textContent = present;
    document.getElementById('count-absent').textContent = absent;
    document.getElementById('count-sick').textContent = sick;
}

document.addEventListener('DOMContentLoaded', updateCounter);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
