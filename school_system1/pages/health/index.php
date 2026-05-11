<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Эрүүл мэндийн бүртгэл';
$user_id   = $_SESSION['user_id'];
$isManager = isManager() || isAdmin();
$isTeacher = isTeacher();
$isStudent = isStudent();
$isParent  = isParent();

// Сурагчийн ID тодорхойлох
$myStudentId = null;
if ($isStudent) {
    $s = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user_id]);
    $myStudentId = $s['student_id'] ?? null;
}
if ($isParent) {
    $myStudentId = $_SESSION['active_child_id'] ?? null;
}

// ── POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_health') {
        $studentId = (int)$_POST['student_id'];
        $date      = $_POST['record_date'] ?? date('Y-m-d');
        
        // Ирээдүйн огноо шалгах
        if ($date > date('Y-m-d')) {
            setFlash('error', 'Бүртгэлийн огноо ирээдүйд байж болохгүй.');
            header("Location: /school_system1/pages/health/index.php?student_id=$studentId");
            exit;
        }
        
        $data      = [
            'height_cm'         => $_POST['height_cm'] ?: null,
            'weight_kg'         => $_POST['weight_kg'] ?: null,
            'blood_type'        => $_POST['blood_type'] ?: null,
            'vision_left'       => $_POST['vision_left'] ?: null,
            'vision_right'      => $_POST['vision_right'] ?: null,
            'allergies'         => $_POST['allergies'] ?: null,
            'chronic_illness'   => $_POST['chronic_illness'] ?: null,
            'emergency_contact' => $_POST['emergency_contact'] ?: null,
            'emergency_phone'   => $_POST['emergency_phone'] ?: null,
            'notes'             => $_POST['health_notes'] ?: null,
        ];

        dbExec("INSERT INTO health_records (student_id, record_date, height_cm, weight_kg, blood_type,
                vision_left, vision_right, allergies, chronic_illness, emergency_contact, emergency_phone, notes, recorded_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
               [$studentId, $date, $data['height_cm'], $data['weight_kg'], $data['blood_type'],
                $data['vision_left'], $data['vision_right'], $data['allergies'], $data['chronic_illness'],
                $data['emergency_contact'], $data['emergency_phone'], $data['notes'], $user_id]);
        auditLog('health_add', $studentId);
        setFlash('success', 'Эрүүл мэндийн бүртгэл хадгалагдлаа.');
        header("Location: /school_system1/pages/health/index.php?student_id=$studentId");
        exit;
    }

    if ($action === 'add_vax') {
        $studentId   = (int)$_POST['student_id'];
        $vaccineName = trim($_POST['vaccine_name'] ?? '');
        $givenDate   = $_POST['given_date'] ?? '';
        $nextDue     = $_POST['next_due'] ?: null;
        $notes       = trim($_POST['vax_notes'] ?? '');
        if ($vaccineName && $givenDate) {
            // Ирээдүйн огноо шалгах (given_date)
            if ($givenDate > date('Y-m-d')) {
                setFlash('error', 'Вакцины огноо ирээдүйд байж болохгүй.');
                header("Location: /school_system1/pages/health/index.php?student_id=$studentId");
                exit;
            }
            dbExec("INSERT INTO vaccinations (student_id, vaccine_name, given_date, next_due, notes, recorded_by)
                    VALUES (?,?,?,?,?,?)", [$studentId, $vaccineName, $givenDate, $nextDue, $notes, $user_id]);
            setFlash('success', 'Вакцины мэдээлэл хадгалагдлаа.');
        }
        header("Location: /school_system1/pages/health/index.php?student_id=$studentId");
        exit;
    }
}

// ── Сурагчид (менежер сонгоно) ─────────────────────────────────
$students = [];
if ($isManager || $isTeacher) {
    $students = dbQuery("SELECT s.student_id, s.last_name, s.first_name, c.class_name
                         FROM students s JOIN classes c ON s.class_id=c.class_id
                         WHERE s.is_active=1 ORDER BY c.class_name, s.last_name");
}

$selectedStudentId = (int)($_GET['student_id'] ?? $myStudentId ?? 0);

// ── Сонгосон сурагчийн мэдээлэл ────────────────────────────────
$studentInfo  = null;
$healthList   = [];
$vaxList      = [];

if ($selectedStudentId) {
    // Хандах эрх шалгах
    $allowed = false;
    if ($isManager) {
        $allowed = true;
    } elseif ($isTeacher) {
        // Зөвхөн өөрийн ангийн сурагч байвал
        $checkClass = dbOne("SELECT student_id FROM students s JOIN classes c ON s.class_id=c.class_id WHERE s.student_id=? AND c.teacher_id=?", [$selectedStudentId, $user_id]);
        if ($checkClass) $allowed = true;
    } elseif ($isStudent && $myStudentId == $selectedStudentId) {
        $allowed = true;
    } elseif ($isParent && $myStudentId == $selectedStudentId) {
        $allowed = true;
    }

    if ($allowed) {
        $studentInfo = dbOne("SELECT s.*, c.class_name,
                                     CONCAT(s.last_name,' ',s.first_name) AS full_name
                              FROM students s JOIN classes c ON s.class_id=c.class_id
                              WHERE s.student_id=?", [$selectedStudentId]);
        $healthList  = dbQuery("SELECT * FROM health_records WHERE student_id=? ORDER BY record_date DESC", [$selectedStudentId]);
        
        // Vaccination records пагинацийгүй (цөөн хэмжээ)
        $vaxList     = dbQuery("SELECT * FROM vaccinations WHERE student_id=? ORDER BY given_date DESC", [$selectedStudentId]);
        
        // Health history pagination (түүхийн хэсэгт л)
        $healthCount = (int)(dbOne("SELECT COUNT(*) AS cnt FROM health_records WHERE student_id=?", [$selectedStudentId])['cnt'] ?? 0);
        $pag = paginate($healthCount, (int)($_GET['page'] ?? 1), 10);
        $healthPaged = dbQuery("SELECT * FROM health_records WHERE student_id=? ORDER BY record_date DESC LIMIT {$pag['offset']}, {$pag['perPage']}", [$selectedStudentId]);
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.health-section {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
}
.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.health-stat {
    background: var(--bg);
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    border: 1px solid var(--border);
}
.health-stat .val { font-size: 24px; font-weight: 700; color: var(--primary); margin-bottom: 4px; }
.health-stat .lbl { font-size: 12px; color: var(--muted); }
.vax-badge {
    display: inline-block;
    background: linear-gradient(135deg,#d1fae5,#a7f3d0);
    color: #065f46;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:24px; align-items:flex-end;">
    <div style="flex:1; min-width:220px;">
        <h1 style="font-size:22px; font-weight:700; margin:0 0 4px;">
            <i class="fas fa-heartbeat" style="color:#ef4444;"></i> Эрүүл мэндийн бүртгэл
        </h1>
        <p style="color:var(--muted); margin:0; font-size:13px;">Сурагчийн эрүүл мэндийн мэдээлэл</p>
    </div>
    <?php if ($isManager || $isTeacher): ?>
    <form method="GET" style="display:flex; gap:10px; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px; color:var(--muted);">Сурагч сонгох</label>
            <select name="student_id" class="form-control" onchange="this.form.submit()" style="min-width:200px;">
                <option value="">-- Сурагч сонгох --</option>
                <?php
                $lastClass = '';
                foreach ($students as $s):
                    if ($s['class_name'] !== $lastClass) {
                        if ($lastClass) echo '</optgroup>';
                        echo '<optgroup label="' . h($s['class_name']) . '">';
                        $lastClass = $s['class_name'];
                    }
                ?>
                    <option value="<?= $s['student_id'] ?>" <?= $selectedStudentId == $s['student_id'] ? 'selected' : '' ?>>
                        <?= h($s['last_name'] . ' ' . $s['first_name']) ?>
                    </option>
                <?php endforeach; if ($lastClass) echo '</optgroup>'; ?>
            </select>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php if ($studentInfo): ?>

<!-- Сурагчийн ерөнхий мэдээлэл -->
<div class="health-section" style="border-left:4px solid #ef4444;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <h2 style="margin:0; font-size:18px;"><?= h($studentInfo['full_name']) ?></h2>
            <span style="color:var(--muted); font-size:13px;"><?= h($studentInfo['class_name']) ?></span>
        </div>
        <?php if ($isManager): ?>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addHealthForm').style.display='block'; this.style.display='none';">
            <i class="fas fa-plus"></i> Шинэ бүртгэл нэмэх
        </button>
        <?php endif; ?>
    </div>

    <!-- Сүүлийн хэмжилтийн үзүүлэлтүүд -->
    <?php if (!empty($healthList)):
        $latest = $healthList[0];
    ?>
    <div class="health-grid">
        <?php if ($latest['height_cm']): ?>
        <div class="health-stat">
            <div class="val"><?= $latest['height_cm'] ?><small style="font-size:14px;">см</small></div>
            <div class="lbl">📏 Өндөр</div>
        </div>
        <?php endif; ?>
        <?php if ($latest['weight_kg']): ?>
        <div class="health-stat">
            <div class="val"><?= $latest['weight_kg'] ?><small style="font-size:14px;">кг</small></div>
            <div class="lbl">⚖️ Жин</div>
        </div>
        <?php endif; ?>
        <?php if ($latest['blood_type']): ?>
        <div class="health-stat">
            <div class="val" style="color:#ef4444;"><?= h($latest['blood_type']) ?></div>
            <div class="lbl">🩸 Цусны бүлэг</div>
        </div>
        <?php endif; ?>
        <?php if ($latest['vision_left'] || $latest['vision_right']): ?>
        <div class="health-stat">
            <div class="val" style="font-size:18px;"><?= h($latest['vision_left'] ?? '-') ?> / <?= h($latest['vision_right'] ?? '-') ?></div>
            <div class="lbl">👁️ Харах чадвар (Зүүн/Баруун)</div>
        </div>
        <?php endif; ?>
        <?php
        // BMI тооцоолох
        if ($latest['height_cm'] && $latest['weight_kg']) {
            $h_m = $latest['height_cm'] / 100;
            $bmi = round($latest['weight_kg'] / ($h_m * $h_m), 1);
            $bmiStatus = $bmi < 18.5 ? 'Туранхай' : ($bmi < 25 ? 'Хэвийн' : ($bmi < 30 ? 'Жингийн илүүдэл' : 'Таргалалт'));
            $bmiColor = $bmi < 18.5 ? '#f59e0b' : ($bmi < 25 ? '#10b981' : '#ef4444');
        ?>
        <div class="health-stat">
            <div class="val" style="color:<?= $bmiColor ?>"><?= $bmi ?></div>
            <div class="lbl">📊 BMI — <?= $bmiStatus ?></div>
        </div>
        <?php } ?>
    </div>

    <?php if ($latest['allergies']): ?>
    <div style="background:#fef3c7; border:1px solid #fbbf24; border-radius:8px; padding:12px 16px; margin-bottom:12px;">
        <strong style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> Харшил:</strong>
        <span style="margin-left:8px;"><?= h($latest['allergies']) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($latest['chronic_illness']): ?>
    <div style="background:#fde8e8; border:1px solid #fca5a5; border-radius:8px; padding:12px 16px; margin-bottom:12px;">
        <strong style="color:#dc2626;"><i class="fas fa-heart-pulse"></i> Архаг өвчин:</strong>
        <span style="margin-left:8px;"><?= h($latest['chronic_illness']) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($latest['emergency_contact']): ?>
    <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px 16px;">
        <strong><i class="fas fa-phone-alt" style="color:var(--primary)"></i> Яаралтай холбоо:</strong>
        <span style="margin-left:8px;"><?= h($latest['emergency_contact']) ?></span>
        <?php if ($latest['emergency_phone']): ?>
        <a href="tel:<?= h($latest['emergency_phone']) ?>" style="margin-left:12px; color:var(--primary); font-weight:600;">📞 <?= h($latest['emergency_phone']) ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="font-size:12px; color:var(--muted); margin-top:8px;">Сүүлийн бүртгэл: <?= mnDate($latest['record_date']) ?></p>
    <?php else: ?>
    <p style="color:var(--muted); text-align:center; padding:20px;">Эрүүл мэндийн бүртгэл байхгүй байна.</p>
    <?php endif; ?>

    <!-- Шинэ бүртгэл нэмэх форм -->
    <?php if ($isManager): ?>
    <div id="addHealthForm" style="display:none; border-top:1px solid var(--border); padding-top:20px; margin-top:20px;">
        <h3 style="font-size:15px; font-weight:700; margin-bottom:16px;"><i class="fas fa-plus"></i> Шинэ хэмжилт нэмэх</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="save_health">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0;"><label>Огноо</label><input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group" style="margin:0;"><label>Өндөр (см)</label><input type="number" name="height_cm" class="form-control" step="0.1" min="50" max="250" placeholder="165.5"></div>
                <div class="form-group" style="margin:0;"><label>Жин (кг)</label><input type="number" name="weight_kg" class="form-control" step="0.1" min="10" max="300" placeholder="52.0"></div>
                <div class="form-group" style="margin:0;"><label>Цусны бүлэг</label>
                    <select name="blood_type" class="form-control">
                        <option value="">-- Сонгох --</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= ($latest['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;"><label>Нүдний харалт (зүүн)</label><input type="text" name="vision_left" class="form-control" placeholder="1.0"></div>
                <div class="form-group" style="margin:0;"><label>Нүдний харалт (баруун)</label><input type="text" name="vision_right" class="form-control" placeholder="1.0"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0;"><label>Харшил</label><textarea name="allergies" class="form-control" rows="2" placeholder="Хоол, эм, бусад харшилын мэдээлэл"></textarea></div>
                <div class="form-group" style="margin:0;"><label>Архаг өвчин</label><textarea name="chronic_illness" class="form-control" rows="2" placeholder="Байнгын эмчилгээ хийлгэдэг өвчин"></textarea></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0;"><label>Яаралтай холбоо барих нэр</label><input type="text" name="emergency_contact" class="form-control" placeholder="Эцэг эхийн нэр"></div>
                <div class="form-group" style="margin:0;"><label>Яаралтай утасны дугаар</label><input type="text" name="emergency_phone" class="form-control" placeholder="99XXXXXX"></div>
            </div>
            <div class="form-group"><label>Нэмэлт тэмдэглэл</label><textarea name="health_notes" class="form-control" rows="2"></textarea></div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addHealthForm').style.display='none';">Болих</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Вакцинжуулалт -->
<div class="health-section">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <h2 style="font-size:16px; font-weight:700; margin:0;"><i class="fas fa-syringe" style="color:#8b5cf6;"></i> Вакцинжуулалт</h2>
        <?php if ($isManager): ?>
        <button class="btn btn-sm btn-secondary" onclick="document.getElementById('addVaxForm').style.display='block'; this.style.display='none';">
            <i class="fas fa-plus"></i> Нэмэх
        </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($vaxList)): ?>
    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <?php foreach ($vaxList as $v): ?>
        <div class="vax-badge" title="<?= mnDate($v['given_date']) . ($v['next_due'] ? ' | Дараагийн: ' . mnDate($v['next_due']) : '') ?>">
            💉 <?= h($v['vaccine_name']) ?> — <?= mnDate($v['given_date']) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--muted);">Вакцины бүртгэл байхгүй.</p>
    <?php endif; ?>

    <?php if ($isManager): ?>
    <div id="addVaxForm" style="display:none; border-top:1px solid var(--border); padding-top:16px; margin-top:10px;">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add_vax">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group" style="margin:0;"><label>Вакцины нэр <span style="color:#e53e3e">*</span></label>
                    <input type="text" name="vaccine_name" class="form-control" placeholder="Жишээ: Гепатит В, Улаан бурхан..." required></div>
                <div class="form-group" style="margin:0;"><label>Хийсэн огноо <span style="color:#e53e3e">*</span></label>
                    <input type="date" name="given_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group" style="margin:0;"><label>Дараагийн огноо</label>
                    <input type="date" name="next_due" class="form-control"></div>
            </div>
            <div class="form-group"><label>Тэмдэглэл</label><input type="text" name="vax_notes" class="form-control"></div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addVaxForm').style.display='none';">Болих</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Бүх бүртгэлийн түүх -->
<?php if (count($healthList) > 1): ?>
<div class="health-section">
    <h2 style="font-size:16px; font-weight:700; margin-bottom:16px;"><i class="fas fa-history"></i> Бүртгэлийн түүх</h2>
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid var(--border);">
                <th style="padding:8px 12px;">Огноо</th>
                <th style="padding:8px 12px;">Өндөр</th>
                <th style="padding:8px 12px;">Жин</th>
                <th style="padding:8px 12px;">BMI</th>
                <th style="padding:8px 12px;">Цусны бүлэг</th>
                <th style="padding:8px 12px;">Харалт</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($healthPaged as $hr):
            $bmiH = $hr['height_cm'] && $hr['weight_kg'] ? round($hr['weight_kg'] / (($hr['height_cm']/100)**2), 1) : null;
        ?>
        <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 12px;"><?= mnDate($hr['record_date']) ?></td>
            <td style="padding:8px 12px;"><?= $hr['height_cm'] ? $hr['height_cm'] . ' см' : '-' ?></td>
            <td style="padding:8px 12px;"><?= $hr['weight_kg'] ? $hr['weight_kg'] . ' кг' : '-' ?></td>
            <td style="padding:8px 12px;"><?= $bmiH ?? '-' ?></td>
            <td style="padding:8px 12px;"><?= h($hr['blood_type'] ?? '-') ?></td>
            <td style="padding:8px 12px;"><?= h(($hr['vision_left'] ?? '-') . ' / ' . ($hr['vision_right'] ?? '-')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../../includes/pagination.php'; ?>
</div>
<?php endif; ?>

<?php elseif (!$selectedStudentId): ?>
<div style="text-align:center; padding:80px 20px; color:var(--muted);">
    <i class="fas fa-heartbeat" style="font-size:56px; opacity:.3; color:#ef4444;"></i>
    <p style="margin-top:16px; font-size:16px;">
        <?= ($isManager || $isTeacher) ? 'Сурагч сонгоно уу.' : 'Таны эрүүл мэндийн бүртгэл байхгүй байна.' ?>
    </p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
