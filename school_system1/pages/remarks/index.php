<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Санал хүсэлт';
$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];

$isTeacher = isTeacher();
$isStudent = isStudent();
$isParent  = isParent();
$isMgr     = isManager();

// ── Роль тус бүрийн ID ──────────────────────────────────────
$myTeacherId = null;
$myStudentId = null;

if ($isTeacher) {
    $t = dbOne("SELECT teacher_id FROM teachers WHERE user_id=?", [$user_id]);
    $myTeacherId = $t ? $t['teacher_id'] : null;
} elseif ($isStudent) {
    $s = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user_id]);
    $myStudentId = $s ? $s['student_id'] : null;
} elseif ($isParent) {
    $myStudentId = (int)($_SESSION['active_child_id'] ?? 0);
    $check = dbOne("SELECT student_id FROM students WHERE student_id=? AND parent_id=?", [$myStudentId, $user_id]);
    if (!$check) die('Хүүхдийн мэдээлэл олдсонгүй эсвэл хандах эрхгүй байна.');
}

// ── TABS ─────────────────────────────────────────────────────
// tab=remarks  → Багшийн тэмдэглэл
// tab=feedback → Санал хүсэлтийн тикет
$tab = $_GET['tab'] ?? 'feedback';
if (!in_array($tab, ['remarks', 'feedback'])) $tab = 'feedback';

// ─────────────────────────────────────────────────────────────
// POST handler
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // 1) Багш тэмдэглэл нэмэх
    if ($action === 'add_remark' && ($isTeacher || $isMgr)) {
        $studentId = (int)$_POST['student_id'];
        $type      = in_array($_POST['remark_type'] ?? '', ['general','academic','behavior']) ? $_POST['remark_type'] : 'general';
        $content   = trim($_POST['content'] ?? '');
        if ($studentId && $content) {
            dbExec("INSERT INTO student_remarks (student_id, teacher_id, remark_type, content) VALUES (?,?,?,?)",
                   [$studentId, $myTeacherId, $type, $content]);
            auditLog('remark_add', $studentId, 'type='.$type);
            setFlash('success', 'Тэмдэглэл амжилттай нэмэгдлээ.');
        } else {
            setFlash('error', 'Мэдээлэл дутуу байна.');
        }
        header('Location: /school_system1/pages/remarks/index.php?tab=remarks');
        exit;
    }

    // 2) Санал хүсэлт илгээх (дурын хэрэглэгч)
    if ($action === 'send_feedback') {
        $subject  = trim($_POST['subject'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $category = in_array($_POST['category'] ?? '', ['general','academic','facility','other']) ? $_POST['category'] : 'general';
        $priority = in_array($_POST['priority'] ?? '', ['low','normal','high','urgent']) ? $_POST['priority'] : 'normal';
        if ($subject && $body) {
            dbExec("INSERT INTO feedback (sender_id, subject, body, category, priority) VALUES (?,?,?,?,?)",
                   [$user_id, $subject, $body, $category, $priority]);
            auditLog('feedback_send', null, 'subj='.$subject);
            setFlash('success', 'Санал хүсэлт амжилттай илгээгдлээ. Удахгүй хариу өгнө.');
        } else {
            setFlash('error', 'Гарчиг болон агуулга заавал байх ёстой.');
        }
        header('Location: /school_system1/pages/remarks/index.php?tab=feedback');
        exit;
    }

    // 3) Менежер хариу өгөх
    if ($action === 'respond_feedback' && $isMgr) {
        $fid      = (int)$_POST['feedback_id'];
        $response = trim($_POST['response'] ?? '');
        $status   = in_array($_POST['fb_status'] ?? '', ['open','in_progress','resolved','closed']) ? $_POST['fb_status'] : 'resolved';
        if ($fid && $response) {
            dbUpdate("UPDATE feedback SET response=?, responded_by=?, responded_at=NOW(), status=? WHERE feedback_id=?",
                     [$response, $user_id, $status, $fid]);
            auditLog('feedback_respond', $fid, 'status='.$status);
            setFlash('success', 'Хариу амжилттай илгээгдлээ.');
        } else {
            setFlash('error', 'Хариу оруулна уу.');
        }
        header('Location: /school_system1/pages/remarks/index.php?tab=feedback');
        exit;
    }

    // 4) Тэмдэглэл устгах (зөвхөн эзэн багш эсвэл менежер)
    if ($action === 'delete_remark' && ($isTeacher || $isMgr)) {
        $rid = (int)$_POST['remark_id'];
        if ($rid) {
            $checkSql = $isMgr ? "DELETE FROM student_remarks WHERE remark_id=?" 
                                : "DELETE FROM student_remarks WHERE remark_id=? AND teacher_id=?";
            $params   = $isMgr ? [$rid] : [$rid, $myTeacherId];
            dbUpdate($checkSql, $params);
            auditLog('remark_delete', $rid);
            setFlash('success', 'Тэмдэглэл устгагдлаа.');
        }
        header('Location: /school_system1/pages/remarks/index.php?tab=remarks');
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// DATA FETCH
// ─────────────────────────────────────────────────────────────

// ── Багшийн тэмдэглэлийн өгөгдөл ───────────────────────────
$remarks    = [];
$myStudents = [];

if ($isTeacher || $isMgr) {
    if ($isMgr) {
        $remarks = dbQuery("SELECT r.*, s.first_name AS sname, s.last_name AS slname,
                                   c.class_name,
                                   t.first_name AS tname, t.last_name AS tlname
                            FROM student_remarks r
                            JOIN students s ON r.student_id = s.student_id
                            JOIN classes c ON s.class_id = c.class_id
                            LEFT JOIN teachers t ON r.teacher_id = t.teacher_id
                            ORDER BY r.created_at DESC LIMIT 200");
        $myStudents = dbQuery("SELECT s.student_id, s.first_name, s.last_name, c.class_name
                                FROM students s JOIN classes c ON s.class_id=c.class_id
                                WHERE s.is_active=1 ORDER BY c.class_name, s.last_name");
    } else {
        $remarks = dbQuery("SELECT r.*, s.first_name AS sname, s.last_name AS slname, c.class_name
                            FROM student_remarks r
                            JOIN students s ON r.student_id = s.student_id
                            JOIN classes c ON s.class_id = c.class_id
                            WHERE r.teacher_id = ?
                            ORDER BY r.created_at DESC", [$myTeacherId]);
        $myStudents = dbQuery("SELECT DISTINCT s.student_id, s.first_name, s.last_name, c.class_name
                                FROM students s
                                JOIN classes c ON s.class_id=c.class_id
                                JOIN subjects sub ON c.class_id=sub.class_id
                                WHERE sub.teacher_id=? AND s.is_active=1
                                ORDER BY c.class_name, s.last_name", [$myTeacherId]);
    }
} elseif ($isStudent && $myStudentId) {
    $remarks = dbQuery("SELECT r.*, t.first_name AS tname, t.last_name AS tlname
                        FROM student_remarks r
                        LEFT JOIN teachers t ON r.teacher_id = t.teacher_id
                        WHERE r.student_id=?
                        ORDER BY r.created_at DESC", [$myStudentId]);
} elseif ($isParent && $myStudentId) {
    $remarks = dbQuery("SELECT r.*, t.first_name AS tname, t.last_name AS tlname,
                               s.first_name AS sname, s.last_name AS slname
                        FROM student_remarks r
                        LEFT JOIN teachers t ON r.teacher_id = t.teacher_id
                        JOIN students s ON r.student_id = s.student_id
                        WHERE r.student_id=?
                        ORDER BY r.created_at DESC", [$myStudentId]);
}

// ── Feedback тикет өгөгдөл ──────────────────────────────────
$feedbacks = [];
if ($isMgr) {
    $filterStatus = $_GET['fs'] ?? 'all';
    $allowedStatuses = ['open','in_progress','resolved','closed'];
    $sql = "SELECT f.*, u.full_name AS sender_name, u.role_id,
                   ur.role_name AS sender_role,
                   ru.full_name AS responder_name
            FROM feedback f
            JOIN users u ON f.sender_id = u.user_id
            JOIN user_roles ur ON u.role_id = ur.role_id
            LEFT JOIN users ru ON f.responded_by = ru.user_id";
    $fbParams = [];
    if ($filterStatus !== 'all' && in_array($filterStatus, $allowedStatuses)) {
        $sql .= " WHERE f.status = ?";
        $fbParams[] = $filterStatus;
    }
    $sql .= " ORDER BY FIELD(f.priority,'urgent','high','normal','low'), f.created_at DESC LIMIT 300";
    $feedbacks = dbQuery($sql, $fbParams);
} else {
    $feedbacks = dbQuery("SELECT f.*, u.full_name AS responder_name
                          FROM feedback f
                          LEFT JOIN users u ON f.responded_by = u.user_id
                          WHERE f.sender_id=?
                          ORDER BY f.created_at DESC", [$user_id]);
}

// ── Stats ───────────────────────────────────────────────────
$fbStats = [];
if ($isMgr) {
    $fbStats = dbOne("SELECT 
        COUNT(*) AS total,
        SUM(status='open') AS open_count,
        SUM(status='in_progress') AS inprog,
        SUM(status='resolved') AS resolved,
        SUM(priority='urgent') AS urgent
        FROM feedback");
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.tab-bar {
    display: flex;
    gap: 4px;
    margin-bottom: 24px;
    background: var(--bg);
    border-radius: 12px;
    padding: 6px;
    border: 1px solid var(--border);
}
.tab-bar a {
    flex: 1;
    text-align: center;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--muted);
    font-weight: 600;
    font-size: 14px;
    transition: all .2s;
}
.tab-bar a.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.tab-bar a:hover:not(.active) { background: var(--border); color: var(--text); }

/* Stat cards */
.stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 24px; }
.stat-mini { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; }
.stat-mini .ico { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.stat-mini .val { font-size: 22px; font-weight: 700; color: var(--text); }
.stat-mini .lbl { font-size: 11px; color: var(--muted); }

/* Feedback cards */
.fb-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 14px;
    transition: box-shadow .2s;
}
.fb-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.fb-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.fb-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-open     { background: #3498db22; color: #3498db; }
.badge-inprog   { background: #f39c1222; color: #f39c12; }
.badge-resolved { background: #2ecc7122; color: #2ecc71; }
.badge-closed   { background: #95a5a622; color: #95a5a6; }
.badge-urgent   { background: #e74c3c22; color: #e74c3c; }
.badge-high     { background: #e67e2222; color: #e67e22; }
.badge-normal   { background: #3498db22; color: #3498db; }
.badge-low      { background: #27ae6022; color: #27ae60; }
.badge-general  { background: #9b59b622; color: #9b59b6; }
.badge-academic { background: #2ecc7122; color: #2ecc71; }
.badge-facility { background: #e67e2222; color: #e67e22; }
.badge-other    { background: #95a5a622; color: #95a5a6; }

.fb-body { font-size: 14px; line-height: 1.7; color: var(--text); margin-bottom: 12px; }
.fb-response { background: linear-gradient(135deg, #2ecc7110, #27ae6010); border: 1px solid #2ecc7140; border-radius: 10px; padding: 12px 16px; margin-top: 12px; }
.fb-response .resp-label { font-size: 11px; font-weight: 700; color: #2ecc71; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.fb-response p { font-size: 13px; margin: 0; line-height: 1.6; }

/* Respond form collapse */
.respond-form { display: none; margin-top: 14px; border-top: 1px solid var(--border); padding-top: 14px; }
.respond-form.open { display: block; }

/* Remark cards */
.rmk-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 15px 18px;
    margin-bottom: 12px;
    border-left: 5px solid var(--primary);
}
.rmk-card.academic { border-left-color: #2ecc71; }
.rmk-card.behavior { border-left-color: #e67e22; }
.rmk-card.general  { border-left-color: #3498db; }

/* Add form box */
.add-box { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin-bottom: 28px; }
.add-box h3 { margin: 0 0 16px; font-size: 16px; }

/* Filter bar */
.filter-bar { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
.filter-bar a { padding: 6px 16px; border-radius: 20px; font-size: 13px; text-decoration: none; border: 1px solid var(--border); color: var(--muted); transition: all .2s; }
.filter-bar a.active { background: var(--primary); color: #fff; border-color: var(--primary); }

@media (max-width: 700px) {
    .stat-row { grid-template-columns: repeat(2,1fr); }
    .tab-bar a { font-size: 12px; padding: 8px 10px; }
}
</style>

<div class="tab-bar">
    <a href="?tab=feedback" class="<?= $tab==='feedback'?'active':'' ?>">
        <i class="fas fa-paper-plane"></i> Санал хүсэлт
    </a>
    <?php if ($isTeacher || $isMgr || $isStudent || $isParent): ?>
    <a href="?tab=remarks" class="<?= $tab==='remarks'?'active':'' ?>">
        <i class="fas fa-comment-dots"></i> Багшийн тэмдэглэл
    </a>
    <?php endif; ?>
</div>

<?php /* ========================================================
        TAB 1: FEEDBACK TICKET
       ======================================================== */ ?>
<?php if ($tab === 'feedback'): ?>

    <?php if ($isMgr && $fbStats): ?>
    <div class="stat-row">
        <div class="stat-mini">
            <div class="ico" style="background:#3498db22;color:#3498db"><i class="fas fa-inbox"></i></div>
            <div><div class="val"><?= $fbStats['total'] ?></div><div class="lbl">Нийт</div></div>
        </div>
        <div class="stat-mini">
            <div class="ico" style="background:#f39c1222;color:#f39c12"><i class="fas fa-clock"></i></div>
            <div><div class="val"><?= $fbStats['open_count'] ?></div><div class="lbl">Нээлттэй</div></div>
        </div>
        <div class="stat-mini">
            <div class="ico" style="background:#2ecc7122;color:#2ecc71"><i class="fas fa-check-circle"></i></div>
            <div><div class="val"><?= $fbStats['resolved'] ?></div><div class="lbl">Шийдвэрлэсэн</div></div>
        </div>
        <div class="stat-mini">
            <div class="ico" style="background:#e74c3c22;color:#e74c3c"><i class="fas fa-fire"></i></div>
            <div><div class="val"><?= $fbStats['urgent'] ?></div><div class="lbl">Яаралтай</div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── SEND FEEDBACK FORM (бүх хэрэглэгч) ── */ ?>
    <div class="add-box">
        <h3><i class="fas fa-paper-plane" style="color:var(--primary)"></i> Санал хүсэлт илгээх</h3>
        <form method="POST" id="fbForm">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="send_feedback">
            <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0">
                    <label>Гарчиг *</label>
                    <input type="text" name="subject" class="form-control" placeholder="Санал хүсэлтийн агуулга товч..." required maxlength="255">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Ангилал</label>
                    <select name="category" class="form-control">
                        <option value="general">Ерөнхий</option>
                        <option value="academic">Сурлага</option>
                        <option value="facility">Орчин нөхцөл</option>
                        <option value="other">Бусад</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Яаралтай байдал</label>
                    <select name="priority" class="form-control">
                        <option value="low">Бага</option>
                        <option value="normal" selected>Дунд</option>
                        <option value="high">Өндөр</option>
                        <option value="urgent">Яаралтай</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Дэлгэрэнгүй *</label>
                <textarea name="body" class="form-control" style="height:100px" placeholder="Санал хүсэлтээ дэлгэрэнгүй бичнэ үү..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Илгээх
            </button>
        </form>
    </div>

    <?php /* ── FEEDBACK LIST ── */ ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0; font-size:16px;">
            <?= $isMgr ? 'Ирсэн бүх санал хүсэлтүүд' : 'Миний санал хүсэлтүүд' ?>
        </h3>
        <?php if ($isMgr): ?>
        <div class="filter-bar" style="margin:0">
            <?php $fs = $_GET['fs'] ?? 'all'; ?>
            <a href="?tab=feedback&fs=all"        class="<?= $fs==='all'?'active':'' ?>">Бүгд (<?= $fbStats['total'] ?>)</a>
            <a href="?tab=feedback&fs=open"        class="<?= $fs==='open'?'active':'' ?>">Нээлттэй</a>
            <a href="?tab=feedback&fs=in_progress" class="<?= $fs==='in_progress'?'active':'' ?>">Хариуцагч</a>
            <a href="?tab=feedback&fs=resolved"    class="<?= $fs==='resolved'?'active':'' ?>">Шийдвэрлэсэн</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($feedbacks)): ?>
        <div style="text-align:center; padding:40px; color:var(--muted)">
            <i class="fas fa-inbox" style="font-size:40px; margin-bottom:12px; display:block; opacity:.4"></i>
            Санал хүсэлт байхгүй байна.
        </div>
    <?php else: ?>
        <?php foreach ($feedbacks as $fb):
            $catLabels  = ['general'=>'Ерөнхий','academic'=>'Сурлага','facility'=>'Орчин нөхцөл','other'=>'Бусад'];
            $priLabels  = ['low'=>'Бага','normal'=>'Дунд','high'=>'Өндөр','urgent'=>'⚡ Яаралтай'];
            $stLabels   = ['open'=>'Нээлттэй','in_progress'=>'Хариуцагч','resolved'=>'Шийдвэрлэсэн','closed'=>'Хаасан'];
            $st   = $fb['status'];
            $pri  = $fb['priority'];
            $cat  = $fb['category'];
        ?>
        <div class="fb-card" id="fb-<?= $fb['feedback_id'] ?>">
            <div class="fb-card-header">
                <div>
                    <strong style="font-size:15px"><?= h($fb['subject']) ?></strong>
                    <?php if ($isMgr): ?>
                    <div style="font-size:12px; color:var(--muted); margin-top:3px">
                        <i class="fas fa-user"></i> <?= h($fb['sender_name']) ?>
                        <span class="badge badge-<?= h($fb['sender_role']) ?>" style="margin-left:4px"><?= h($fb['sender_role']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="fb-badges">
                    <span class="badge badge-<?= $st ?>"><?= $stLabels[$st] ?? $st ?></span>
                    <span class="badge badge-<?= $pri ?>"><?= $priLabels[$pri] ?? $pri ?></span>
                    <span class="badge badge-<?= $cat ?>"><?= $catLabels[$cat] ?? $cat ?></span>
                </div>
            </div>
            <div class="fb-body"><?= nl2br(h($fb['body'])) ?></div>
            <div style="font-size:12px; color:var(--muted)">
                <i class="fas fa-clock"></i> <?= mnDateTime($fb['created_at']) ?>
            </div>

            <?php if ($fb['response']): ?>
            <div class="fb-response">
                <div class="resp-label"><i class="fas fa-reply"></i> Хариу: <?= h($fb['responder_name'] ?? 'Менежер') ?> — <?= mnDateTime($fb['responded_at']) ?></div>
                <p><?= nl2br(h($fb['response'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($isMgr && $st !== 'closed'): ?>
            <div style="margin-top:12px">
                <button type="button" class="btn btn-primary" style="font-size:12px; padding:6px 14px"
                    onclick="toggleRespond(<?= $fb['feedback_id'] ?>)">
                    <i class="fas fa-reply"></i> <?= $fb['response'] ? 'Хариу засах' : 'Хариу өгөх' ?>
                </button>
            </div>
            <div class="respond-form" id="rform-<?= $fb['feedback_id'] ?>">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="respond_feedback">
                    <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
                    <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end">
                        <div class="form-group" style="margin:0">
                            <label style="font-size:13px">Хариу бичих</label>
                            <textarea name="response" class="form-control" style="height:80px" required><?= h($fb['response'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label style="font-size:13px">Төлөв</label>
                            <select name="fb_status" class="form-control">
                                <option value="in_progress" <?= $st==='in_progress'?'selected':'' ?>>Хариуцагч</option>
                                <option value="resolved"    <?= $st==='resolved'?'selected':'' ?>>Шийдвэрлэсэн</option>
                                <option value="closed"      <?= $st==='closed'?'selected':'' ?>>Хаах</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:10px; font-size:13px">
                        <i class="fas fa-check"></i> Хадгалах
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<?php /* ========================================================
        TAB 2: REMARKS (Багшийн тэмдэглэл)
       ======================================================== */ ?>
<?php if ($tab === 'remarks'): ?>

    <?php if ($isTeacher || $isMgr): ?>
    <div class="add-box">
        <h3><i class="fas fa-pen" style="color:var(--primary)"></i> Сурагчид тэмдэглэл бичих</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add_remark">
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0">
                    <label>Сурагч сонгох *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Сурагч сонгох --</option>
                        <?php foreach ($myStudents as $s): ?>
                        <option value="<?= $s['student_id'] ?>"><?= h($s['class_name'].' | '.$s['last_name'].' '.$s['first_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Тэмдэглэлийн төрөл</label>
                    <select name="remark_type" class="form-control">
                        <option value="general">Ерөнхий</option>
                        <option value="academic">Сурлага</option>
                        <option value="behavior">Сахилга бат</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Тэмдэглэл *</label>
                <textarea name="content" class="form-control" style="height:90px" placeholder="Тайлбар энд бичнэ үү..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Хадгалах
            </button>
        </form>
    </div>
    <?php endif; ?>

    <h3 style="font-size:16px; margin-bottom:14px">
        <?php if ($isTeacher || $isMgr): ?>
            <i class="fas fa-list"></i> Бүртгэгдсэн тэмдэглэлүүд
        <?php elseif ($isStudent): ?>
            <i class="fas fa-comment-dots"></i> Надад ирсэн тэмдэглэлүүд
        <?php else: ?>
            <i class="fas fa-comment-dots"></i> Хүүхдийн тэмдэглэлүүд
        <?php endif; ?>
    </h3>

    <?php if (empty($remarks)): ?>
        <div style="text-align:center; padding:40px; color:var(--muted)">
            <i class="fas fa-comment-slash" style="font-size:40px; display:block; margin-bottom:12px; opacity:.4"></i>
            Тэмдэглэл байхгүй байна.
        </div>
    <?php else: ?>
        <?php
        $typeLabels = ['general'=>'Ерөнхий','academic'=>'Сурлага','behavior'=>'Сахилга'];
        foreach ($remarks as $r):
            $rtype = $r['remark_type'] ?? 'general';
        ?>
        <div class="rmk-card <?= h($rtype) ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; margin-bottom:8px">
                <div>
                    <?php if ($isTeacher || $isMgr): ?>
                        <strong><?= h(($r['slname'] ?? '').' '.($r['sname'] ?? '')) ?></strong>
                        <span style="font-size:12px; color:var(--muted); margin-left:8px"><?= h($r['class_name'] ?? '') ?></span>
                        <?php if ($isMgr && isset($r['tname'])): ?>
                        <span style="font-size:12px; color:var(--muted); margin-left:8px">| Багш: <?= h(($r['tlname'] ?? '').' '.($r['tname'] ?? '')) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <strong>Багш: <?= h(($r['tlname'] ?? 'Тодорхойгүй').' '.($r['tname'] ?? '')) ?></strong>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:8px; align-items:center">
                    <span class="badge badge-<?= h($rtype) ?>"><?= $typeLabels[$rtype] ?? $rtype ?></span>
                    <span style="font-size:12px; color:var(--muted)"><?= mnDateTime($r['created_at']) ?></span>
                    <?php if (($isTeacher || $isMgr)): ?>
                    <form method="POST" style="margin:0" onsubmit="return confirm('Тэмдэглэл устгах уу?')">
                        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete_remark">
                        <input type="hidden" name="remark_id" value="<?= $r['remark_id'] ?>">
                        <button type="submit" class="btn" style="padding:3px 10px; font-size:12px; background:#e74c3c22; color:#e74c3c; border:1px solid #e74c3c44; border-radius:6px">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div style="font-size:14px; line-height:1.7; color:var(--text)"><?= nl2br(h($r['content'])) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<script>
function toggleRespond(id) {
    const el = document.getElementById('rform-' + id);
    el.classList.toggle('open');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
