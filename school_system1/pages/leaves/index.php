<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Чөлөөний хүсэлт';
$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$isManager = isManager() || isAdmin();
$isTeacher = isTeacher();
$isStudent = isStudent();
$isParent  = isParent();

$myStudentId = null;
if ($isStudent) {
    $s = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user_id]);
    $myStudentId = $s['student_id'] ?? null;
}
if ($isParent) {
    $myStudentId = (int)($_SESSION['active_child_id'] ?? 0);
    $check = dbOne("SELECT student_id FROM students WHERE student_id=? AND parent_id=?", [$myStudentId, $user_id]);
    if (!$check) die('Хүүхдийн мэдээлэл олдсонгүй эсвэл хандах эрхгүй байна.');
}

// ── POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $action = $_POST['action'];

    if ($action === 'request_leave' && ($isStudent || $isParent)) {
        if (!$myStudentId) {
            setFlash('error', 'Сурагчийн мэдээлэл олдсонгүй. Системийн админд хандана уу.');
            header('Location: /school_system1/pages/leaves/index.php');
            exit;
        }
        $start  = $_POST['start_date'];
        $end    = !empty($_POST['end_date']) ? $_POST['end_date'] : $start;
        $reason = trim($_POST['reason'] ?? '');
        $type   = $_POST['leave_type'] ?? 'sick';

        // Дуусах огноо < Эхлэх огноо шалгах
        if ($end < $start) {
            setFlash('error', 'Дуусах огноо эхлэх огнооноос өмнө байж болохгүй.');
            header('Location: /school_system1/pages/leaves/index.php');
            exit;
        }

        // Давхардсан pending хүсэлт шалгах (ижил хугацаанд)
        $overlap = dbOne("SELECT id FROM leave_requests 
                          WHERE student_id=? AND status='pending' 
                          AND start_date <= ? AND end_date >= ?",
                         [$myStudentId, $end, $start]);
        if ($overlap) {
            setFlash('error', 'Энэ хугацаанд аль хэдийн хүлээгдэж буй чөлөөний хүсэлт байна.');
            header('Location: /school_system1/pages/leaves/index.php');
            exit;
        }

        if ($start && $reason) {
            try {
                dbExec("INSERT INTO leave_requests (student_id, request_by, start_date, end_date, reason, leave_type)
                        VALUES (?,?,?,?,?,?)",
                       [$myStudentId, $user_id, $start, $end, $reason, $type]);
                auditLog('leave_request', $myStudentId, "type=$type");
                setFlash('success', 'Чөлөөний хүсэлт илгээгдлээ. Менежер хянаж батлах болно.');
            } catch (Exception $e) {
                setFlash('error', 'Хүсэлт хадгалах үед алдаа гарлаа: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Огноо болон шалтгааныг бөглөнө үү.');
        }
        header('Location: /school_system1/pages/leaves/index.php');
        exit;
    }

    if ($action === 'review_leave' && ($isManager || $isTeacher)) {
        $leaveId  = (int)$_POST['leave_id'];
        $status   = $_POST['status'];
        $note     = trim($_POST['review_note'] ?? '');

        if (in_array($status, ['approved','rejected'])) {
            dbUpdate("UPDATE leave_requests SET status=?, reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE leave_id=?",
                     [$status, $user_id, $note, $leaveId]);

            // Хэрэв зөвшөөрсөн бол ирцэнд автомат "өвчтэй" бүртгэх
            if ($status === 'approved') {
                $lr  = dbOne("SELECT * FROM leave_requests WHERE leave_id=?", [$leaveId]);
                if ($lr) {
                    $subs = dbQuery("SELECT DISTINCT sub.subject_id FROM subjects sub
                                     JOIN students s ON s.class_id = sub.class_id
                                     WHERE s.student_id = ?", [$lr['student_id']]);
                    $dStart = new DateTime($lr['start_date']);
                    $dEnd   = new DateTime($lr['end_date']);
                    $dEnd->modify('+1 day');
                    $interval  = new DateInterval('P1D');
                    $dateRange = new DatePeriod($dStart, $interval, $dEnd);

                    $insertValues = [];
                    $insertParams = [];
                    foreach ($dateRange as $dt) {
                        $dateStr = $dt->format('Y-m-d');
                        // Амралтын өдрүүдийг алгасах (Бямба=6, Ням=7)
                        $dow = (int)$dt->format('N');
                        if ($dow >= 6) continue;

                        foreach ($subs as $sub) {
                            $insertValues[] = '(?,?,?,3,?,?)';
                            array_push($insertParams,
                                $lr['student_id'], $sub['subject_id'], $dateStr,
                                $user_id, 'Чөлөөний хүсэлт батлагдсан'
                            );
                        }
                    }

                    // Batch INSERT — N+1 query-г нэг query-д шахсан
                    if (!empty($insertValues)) {
                        $batchSql = "INSERT IGNORE INTO attendance (student_id, subject_id, date, status_id, recorded_by, note) VALUES "
                                  . implode(',', $insertValues);
                        getDB()->prepare($batchSql)->execute($insertParams);
                    }
                }
            }

            auditLog('leave_review', $leaveId, "status=$status");
            setFlash('success', $status === 'approved' ? '✅ Чөлөө зөвшөөрлөгдлөө. Ирцэнд автоматаар бүртгэгдлээ.' : '❌ Чөлөөний хүсэлт буцаагдлаа.');
        }
        header('Location: /school_system1/pages/leaves/index.php');
        exit;
    }

    if ($action === 'cancel_leave' && ($isStudent || $isParent)) {
        $leaveId = (int)$_POST['leave_id'];
        $lr = dbOne("SELECT * FROM leave_requests WHERE leave_id=? AND request_by=?", [$leaveId, $user_id]);
        if ($lr && $lr['status'] === 'pending') {
            dbUpdate("DELETE FROM leave_requests WHERE leave_id=?", [$leaveId]);
            setFlash('success', 'Хүсэлт цуцлагдлаа.');
        }
        header('Location: /school_system1/pages/leaves/index.php');
        exit;
    }
}

// ── Өгөгдөл татах ──────────────────────────────────────────────
if ($isManager || $isTeacher) {
    $statusFilter = $_GET['status'] ?? 'pending';
    $params = [];
    $where  = "1=1";
    if ($statusFilter !== 'all') {
        $where  = "lr.status = ?";
        $params[] = $statusFilter;
    }
    // Pagination тоолох
    $countLeaves = dbOne("SELECT COUNT(*) AS cnt FROM leave_requests lr WHERE $where", $params);
    $pag = paginate((int)($countLeaves['cnt'] ?? 0), (int)($_GET['page'] ?? 1), 15);
    $leaves = dbQuery("SELECT lr.*,
                              CONCAT(s.last_name,' ',s.first_name) AS student_name,
                              c.class_name,
                              u.full_name AS requester_name,
                              rv.full_name AS reviewer_name
                       FROM leave_requests lr
                       JOIN students s ON lr.student_id = s.student_id
                       JOIN classes c  ON s.class_id = c.class_id
                       JOIN users u    ON lr.request_by = u.user_id
                       LEFT JOIN users rv ON lr.reviewed_by = rv.user_id
                       WHERE $where
                       ORDER BY lr.created_at DESC
                       LIMIT {$pag['offset']}, {$pag['perPage']}", $params);
} else {
    $countLeaves = dbOne("SELECT COUNT(*) AS cnt FROM leave_requests lr WHERE lr.student_id = ?", [$myStudentId ?? 0]);
    $pag = paginate((int)($countLeaves['cnt'] ?? 0), (int)($_GET['page'] ?? 1), 15);
    $leaves = dbQuery("SELECT lr.*,
                              CONCAT(s.last_name,' ',s.first_name) AS student_name,
                              c.class_name,
                              rv.full_name AS reviewer_name
                       FROM leave_requests lr
                       JOIN students s ON lr.student_id = s.student_id
                       JOIN classes c  ON s.class_id = c.class_id
                       LEFT JOIN users rv ON lr.reviewed_by = rv.user_id
                       WHERE lr.student_id = ?
                       ORDER BY lr.created_at DESC
                       LIMIT {$pag['offset']}, {$pag['perPage']}", [$myStudentId ?? 0]);
}

$pendingCount = count(array_filter($leaves, fn($l) => $l['status'] === 'pending'));

$typeLabels = ['sick'=>'🤒 Өвчин','family'=>'👨‍👩‍👧 Гэр бүл','competition'=>'🏆 Тэмцээн','other'=>'📌 Бусад'];
$statusBadge = [
    'pending'  => ['bg'=>'#dbeafe','color'=>'#2563eb','label'=>'🔵 Хүлээгдэж байна'],
    'approved' => ['bg'=>'#d1fae5','color'=>'#059669','label'=>'✅ Зөвшөөрлөгдсөн'],
    'rejected' => ['bg'=>'#fde8e8','color'=>'#dc2626','label'=>'❌ Буцаагдсан'],
];

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
    <div>
        <h1 style="font-size:22px; font-weight:700; margin:0 0 4px;">
            <i class="fas fa-calendar-minus" style="color:#f59e0b;"></i> Чөлөөний хүсэлт
        </h1>
        <p style="color:var(--muted); margin:0; font-size:13px;">
            <?= $isManager ? 'Сурагчдын чөлөөний хүсэлтийг хянах' : 'Чөлөөний хүсэлт гаргах' ?>
        </p>
    </div>
    <?php if ($pendingCount > 0 && $isManager): ?>
    <div style="background:#fef3c7; color:#d97706; padding:8px 18px; border-radius:20px; font-weight:700; font-size:14px;">
        <i class="fas fa-clock"></i> <?= $pendingCount ?> хүсэлт хүлээгдэж байна
    </div>
    <?php endif; ?>
</div>

<?php if ($isStudent || $isParent): ?>
<!-- ХҮСЭЛТ ГАРГАХ ФОРМ -->
<div style="background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:24px; margin-bottom:24px; border-left:4px solid #f59e0b;">
    <h3 style="font-size:15px; font-weight:700; margin-bottom:16px;">
        <i class="fas fa-plus" style="color:#f59e0b;"></i> Чөлөөний хүсэлт гаргах
    </h3>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="request_leave">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="form-group" style="margin:0;">
                <label>Эхлэх огноо <span style="color:#e53e3e">*</span></label>
                <input type="date" name="start_date" class="form-control" required
                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Дуусах огноо</label>
                <input type="date" name="end_date" class="form-control"
                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Чөлөөний шалтгаан</label>
                <select name="leave_type" class="form-control">
                    <?php foreach ($typeLabels as $val => $lbl): ?>
                    <option value="<?= $val ?>"><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Нарийвчилсан шалтгаан <span style="color:#e53e3e">*</span></label>
            <textarea name="reason" class="form-control" rows="3"
                placeholder="Чөлөө авах шалтгааныг тайлбарлана уу..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Хүсэлт илгээх
        </button>
        <p style="margin-top:8px; font-size:12px; color:var(--muted);">
            <i class="fas fa-info-circle"></i> Хүсэлт зөвшөөрөгдсөний дараа ирцэнд автоматаар бүртгэгдэнэ.
        </p>
    </form>
</div>
<?php endif; ?>

<?php if ($isManager || $isTeacher): ?>
<!-- ШҮҮЛТҮҮР -->
<div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
    <?php foreach(['pending'=>'🔵 Хүлээгдэж байна','approved'=>'✅ Зөвшөөрлөгдсөн','rejected'=>'❌ Буцаагдсан','all'=>'Бүгд'] as $val=>$lbl): ?>
    <a href="?status=<?= $val ?>"
       class="btn btn-sm <?= ($_GET['status'] ?? 'pending') === $val ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $lbl ?>
        <?php if($val === 'pending' && $pendingCount): ?><span style="background:#e53e3e;color:#fff;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;margin-left:4px;"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ЖАГСААЛТ -->
<?php if (empty($leaves)): ?>
<div style="text-align:center; padding:60px; color:var(--muted); background:var(--card-bg); border:1px solid var(--border); border-radius:14px;">
    <i class="fas fa-calendar-check" style="font-size:48px; opacity:.3; color:#10b981;"></i>
    <p style="margin-top:16px; font-size:15px;">Чөлөөний хүсэлт байхгүй байна.</p>
</div>
<?php else: ?>
<div style="display:flex; flex-direction:column; gap:14px;">
<?php foreach ($leaves as $lr):
    $stBadge = $statusBadge[$lr['status']] ?? $statusBadge['pending'];
?>
<div style="background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:20px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
        <div>
            <?php if ($isManager || $isTeacher): ?>
            <strong style="font-size:16px;"><?= h($lr['student_name']) ?></strong>
            <span style="color:var(--muted); margin-left:8px; font-size:13px;"><?= h($lr['class_name']) ?></span>
            <div style="font-size:12px; color:var(--muted); margin-top:2px;">Гаргасан: <?= h($lr['requester_name']) ?> — <?= mnDate($lr['created_at']) ?></div>
            <?php else: ?>
            <strong style="font-size:14px;"><?= $typeLabels[$lr['leave_type']] ?? $lr['leave_type'] ?></strong>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span style="display:inline-block; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:700; background:<?= $stBadge['bg'] ?>; color:<?= $stBadge['color'] ?>;">
                <?= $stBadge['label'] ?>
            </span>
            <span style="background:#f3f4f6; color:#6b7280; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;">
                <?= $typeLabels[$lr['leave_type']] ?? $lr['leave_type'] ?>
            </span>
        </div>
    </div>

    <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:13px; color:var(--muted); margin-bottom:12px;">
        <span><i class="fas fa-calendar-day"></i> <?= mnDate($lr['start_date']) ?> — <?= mnDate($lr['end_date']) ?></span>
        <?php
        $sd = new DateTime($lr['start_date']);
        $ed = new DateTime($lr['end_date']);
        $days = $ed->diff($sd)->days + 1;
        ?>
        <span><i class="fas fa-clock"></i> <?= $days ?> өдөр</span>
    </div>

    <div style="background:var(--bg); border-radius:8px; padding:12px; font-size:14px; line-height:1.6; margin-bottom:12px;">
        <strong style="font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:4px;">Шалтгаан:</strong>
        <?= nl2br(h($lr['reason'])) ?>
    </div>

    <?php if ($lr['review_note']): ?>
    <div style="background:<?= $lr['status'] === 'approved' ? '#d1fae522' : '#fde8e822' ?>; border:1px solid <?= $lr['status'] === 'approved' ? '#6ee7b7' : '#fca5a5' ?>; border-radius:8px; padding:12px; margin-bottom:12px;">
        <strong style="font-size:12px;">Менежерийн тэмдэглэл:</strong>
        <span style="margin-left:8px; font-size:13px;"><?= h($lr['review_note']) ?></span>
        <?php if ($lr['reviewer_name']): ?>
        <span style="font-size:11px; color:var(--muted); margin-left:8px;">— <?= h($lr['reviewer_name']) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($lr['status'] === 'pending'):
          if ($isManager || $isTeacher): ?>
    <!-- Хянах форм -->
    <div style="border-top:1px solid var(--border); padding-top:14px;">
        <form method="POST" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="review_leave">
            <input type="hidden" name="leave_id" value="<?= $lr['leave_id'] ?>">
            <input type="text" name="review_note" class="form-control"
                   placeholder="Тэмдэглэл (заавал биш)..." style="flex:1; min-width:200px; height:36px; font-size:13px;">
            <button type="submit" name="status" value="approved" class="btn btn-sm"
                    style="background:#d1fae5; color:#059669; border:1px solid #6ee7b7; font-weight:700;">
                ✅ Зөвшөөрөх
            </button>
            <button type="submit" name="status" value="rejected" class="btn btn-sm"
                    style="background:#fde8e8; color:#dc2626; border:1px solid #fca5a5; font-weight:700;"
                    onclick="return confirm('Буцаах уу?')">
                ❌ Буцаах
            </button>
        </form>
    </div>
    <?php else: ?>
    <!-- Цуцлах (Сурагч/Эцэг эх) -->
    <form method="POST" style="display:inline;" onsubmit="return confirm('Хүсэлтийг цуцлах уу?')">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="cancel_leave">
        <input type="hidden" name="leave_id" value="<?= $lr['leave_id'] ?>">
        <button type="submit" class="btn btn-sm" style="background:#fde8e8; color:#dc2626; border:1px solid #fca5a5;">
            <i class="fas fa-times"></i> Цуцлах
        </button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/pagination.php'; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
