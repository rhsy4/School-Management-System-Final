<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'manager', 'director']);

$pageTitle = 'Бүртгэл баталгаажуулах';

// --- Бүртгэлийг нээх/хаах (Admin + Director л хийж болно) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_registration') {
    verifyCsrf();
    if (isAdmin() || isDirector()) {
        $current = dbOne("SELECT setting_value FROM settings WHERE setting_key='registration_open'");
        $newVal = ($current && $current['setting_value'] === '1') ? '0' : '1';
        if ($current) {
            dbUpdate("UPDATE settings SET setting_value=? WHERE setting_key='registration_open'", [$newVal]);
        } else {
            dbUpdate("INSERT INTO settings (setting_key, setting_value) VALUES ('registration_open', ?)", [$newVal]);
        }
        $label = $newVal === '1' ? 'нээлттэй' : 'хаалттай';
        auditLog('registration_toggle', null, "Бүртгэл $label болгов");
        setFlash('success', "Бүртгэл амжилттай " . ($newVal === '1' ? '🟢 нээлттэй' : '🔴 хаалттай') . " болгогдлоо.");
    } else {
        setFlash('error', 'Танд энэ үйлдлийг хийх эрх байхгүй!');
    }
    header('Location: /school_system1/pages/approvals/index.php');
    exit;
}

// --- Батлах / Татгалзах ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    verifyCsrf();
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    $req = dbOne("SELECT * FROM pending_registrations WHERE id=? AND status='pending'", [$id]);
    $classId = (!empty($_POST['class_id'])) ? (int)$_POST['class_id'] : null;

    if ($req) {
        if ($action === 'approve') {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                $fullName  = trim($req['last_name'] . ' ' . $req['first_name']);
                $safePhone = !empty($req['phone']) ? trim($req['phone']) : '-';  // NOT NULL fallback

                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, first_name, last_name, role_id, phone, email, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $req['username'], $req['password_hash'], $fullName,
                    $req['first_name'], $req['last_name'],
                    $req['role_id'], $safePhone, $req['email'] ?: null
                ]);
                $newUserId = $pdo->lastInsertId();

                if ($req['role_id'] == 4) {
                    $birthDate = (!empty($req['birth_date']) && $req['birth_date'] !== '0000-00-00') ? $req['birth_date'] : null;
                    $stmtStu = $pdo->prepare("INSERT INTO students (user_id, last_name, first_name, register_no, gender, birth_date, class_id, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtStu->execute([
                        $newUserId, $req['last_name'], $req['first_name'], $req['register_no'] ?: null,
                        $req['gender'] ?: null, $birthDate, $classId, $req['address'] ?: null, $safePhone
                    ]);

                    // Хэрэв эцэг эх нь сурагчаас түрүүлж батлагдсан байвал автоматаар холбох
                    if (!empty($req['register_no'])) {
                        $parentReq = dbOne("SELECT username FROM pending_registrations WHERE role_id=5 AND student_register_no=? AND status='approved'", [$req['register_no']]);
                        if ($parentReq) {
                            $parentUser = dbOne("SELECT user_id FROM users WHERE username=?", [$parentReq['username']]);
                            if ($parentUser) {
                                $pdo->prepare("UPDATE students SET parent_id=? WHERE user_id=?")->execute([$parentUser['user_id'], $newUserId]);
                            }
                        }
                    }

                } elseif ($req['role_id'] == 5) {
                    $stmtPar = $pdo->prepare("INSERT INTO parents (user_id, last_name, first_name, phone, email) VALUES (?, ?, ?, ?, ?)");
                    $stmtPar->execute([
                        $newUserId, $req['last_name'], $req['first_name'], $safePhone, $req['email'] ?: null
                    ]);
                    
                    if (!empty($req['student_register_no'])) {
                        $pdo->prepare("UPDATE students SET parent_id=? WHERE register_no=?")->execute([$newUserId, $req['student_register_no']]);
                    }
                }

                $pdo->prepare("UPDATE pending_registrations SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $id]);
                $pdo->commit();
                auditLog('registration_approved', $newUserId, "Бүртгэлийг баталгаажууллаа: {$req['username']}");

                // 📧 Шинэ хэрэглэгчид нэвтрэх мэдээлэл email-ээр илгээх
                // Тэмдэглэл: pending_registrations-д plain password хадгалагдаагүй
                // тул бүртгэлийн үеийн нууц үгийг хэрэглэгч мэднэ.
                // Гэхдээ email-ээр нэвтрэх нэр + сануулга илгээнэ.
                try {
                    require_once __DIR__ . '/../../includes/notification.php';
                    $notifier = new NotificationService();
                    // Нууц үг мэдэгдэхгүй тул "Бүртгүүлэхдээ оруулсан нууц үгээ ашиглана уу" гэж илгээнэ
                    $notifier->sendWelcomeEmail((int)$newUserId, $req['username'], '(Бүртгүүлэхдээ оруулсан нууц үг)');
                } catch (Exception $mailErr) {
                    // Email алдаа нь бүртгэлд нөлөөлөхгүй
                    error_log('[WelcomeEmail Error] ' . $mailErr->getMessage());
                }

                setFlash('success', '✅ Амжилттай баталгаажуулж хэрэглэгч үүсгэлээ. Нэвтрэх мэдээлэл email-ээр илгээгдлээ.');
            } catch (Exception $e) {
                if (isset($pdo)) $pdo->rollBack();
                setFlash('error', 'Баталгаажуулах үед алдаа гарлаа: ' . $e->getMessage());
            }
        } elseif ($action === 'reject') {
            dbUpdate("UPDATE pending_registrations SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            auditLog('registration_rejected', null, "Бүртгэлээс татгалзлаа: {$req['username']}");
            setFlash('success', '❌ Бүртгэлийн хүсэлтээс татгалзлаа.');
        }
    } else {
        setFlash('error', 'Хүсэлт олдсонгүй эсвэл аль хэдийн шийдвэрлэгдсэн байна.');
    }
    header('Location: /school_system1/pages/approvals/index.php');
    exit;
}

// Бүртгэлийн нээлттэй/хаалттай төлөв
$regSetting = dbOne("SELECT setting_value FROM settings WHERE setting_key='registration_open'");
$isRegOpen = ($regSetting && $regSetting['setting_value'] === '1');

// Хүлээгдэж буй бүртгэлүүд
$pending = dbQuery("SELECT * FROM pending_registrations WHERE status='pending' ORDER BY created_at DESC");

// Ангиуд
$classes = dbQuery("SELECT class_id, class_name FROM classes ORDER BY class_name");

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Toggle Switch ── */
.reg-toggle-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px 24px;
    background: var(--card-bg);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}
.reg-status-info { display: flex; flex-direction: column; gap: 4px; }
.reg-status-label { font-size: 15px; font-weight: 700; color: var(--text); }
.reg-status-sub { font-size: 13px; color: var(--muted); }

/* Power-style toggle button */
.toggle-power-btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 22px 10px 16px;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    font-family: inherit;
    font-size: 15px;
    font-weight: 700;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    letter-spacing: 0.3px;
}
.toggle-power-btn.open-state {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    box-shadow: 0 4px 20px rgba(16,185,129,0.45);
}
.toggle-power-btn.closed-state {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    box-shadow: 0 4px 20px rgba(239,68,68,0.45);
}
.toggle-power-btn:hover { transform: translateY(-2px) scale(1.03); }
.toggle-power-btn:active { transform: scale(0.97); }

.power-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: rgba(255,255,255,0.25);
    transition: all 0.3s ease;
}
.toggle-power-btn:hover .power-icon { background: rgba(255,255,255,0.35); transform: rotate(20deg); }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.3px;
}
.pill-open  { background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.2); }
.pill-closed { background: rgba(239,68,68,0.12); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
.pulse-dot {
    width: 8px; height: 8px; border-radius: 50%;
    animation: pulse-anim 2s infinite;
}
.dot-green { background: #10b981; box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
.dot-red   { background: #ef4444; }
@keyframes pulse-anim {
    0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
    70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
    100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}

/* Detail expand */
.detail-section {
    display: none;
    background: rgba(79,70,229,0.03);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    animation: fadeSlide 0.25s ease;
}
@keyframes fadeSlide {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}
.detail-section.visible { display: block; }
.detail-row { display: flex; align-items: center; padding: 7px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.detail-row:last-child { border-bottom: none; }
.detail-key { width: 120px; color: var(--muted); font-weight: 600; flex-shrink: 0; }
.detail-val { color: var(--text); font-weight: 500; }
.detail-val.highlight { color: var(--primary); font-weight: 700; }
.detail-val.warning-val { color: var(--warning); font-weight: 700; }

.btn-detail {
    width: 100%;
    padding: 8px 14px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--bg);
    color: var(--text);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
    margin-bottom: 12px;
    font-family: inherit;
}
.btn-detail:hover { background: rgba(79,70,229,0.08); border-color: var(--primary); color: var(--primary); }
.btn-detail.expanded { background: rgba(79,70,229,0.1); border-color: var(--primary); color: var(--primary); }
</style>

<!-- ═══ БҮРТГЭЛИЙН НЭЭЛТ/ХААЛТЫН TOGGLE ═══ -->
<div class="reg-toggle-section">
    <div class="reg-status-info">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
            <span class="reg-status-label">Бүртгэлийн төлөв</span>
            <?php if($isRegOpen): ?>
            <span class="status-pill pill-open"><span class="pulse-dot dot-green"></span>НЭЭЛТТЭЙ</span>
            <?php else: ?>
            <span class="status-pill pill-closed"><span class="pulse-dot dot-red" style="animation:none;"></span>ХААЛТТАЙ</span>
            <?php endif; ?>
        </div>
        <div class="reg-status-sub">
            <?= $isRegOpen
                ? '<i class="fas fa-check-circle" style="color:#10b981"></i> Одоогоор шинэ бүртгэл авч байна.'
                : '<i class="fas fa-times-circle" style="color:#ef4444"></i> Шинэ бүртгэл хаагдсан байна. Хүмүүс бүртгүүлж чадахгүй.' ?>
        </div>
    </div>

    <?php if(isAdmin() || isDirector()): ?>
    <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="toggle_registration">
        <button type="submit"
                class="toggle-power-btn <?= $isRegOpen ? 'open-state' : 'closed-state' ?>"
                onclick="return confirm('<?= $isRegOpen ? 'Бүртгэлийг ХААХ уу?' : 'Бүртгэлийг НЭЭХ уу?' ?>')">
            <div class="power-icon">
                <i class="fas fa-power-off"></i>
            </div>
            <?= $isRegOpen ? '🔴 Бүртгэл хаах' : '🟢 Бүртгэл нээх' ?>
        </button>
    </form>
    <?php else: ?>
    <div style="color:var(--muted); font-size:13px; display:flex; align-items:center; gap:6px;">
        <i class="fas fa-lock"></i> Зөвхөн Admin / Захирал өөрчилж болно
    </div>
    <?php endif; ?>
</div>

<!-- ═══ ХҮЛЭЭГДЭЖ БУЙ БҮРТГЭЛҮҮД ═══ -->
<div class="card" style="border:none; box-shadow:0 4px 20px rgba(0,0,0,0.05); border-radius:14px; overflow:hidden;">
    <div class="card-header" style="background: linear-gradient(135deg, var(--primary), #818cf8); color:white; border:none; padding:20px 24px;">
        <div>
            <h2 style="margin:0; font-size:20px; font-weight:700;"><i class="fas fa-user-check"></i> Хүлээгдэж буй бүртгэлүүд</h2>
            <p style="margin:5px 0 0; opacity:0.85; font-size:13px;">Системд нэвтрэх хүсэлт илгээсэн шинэ хэрэглэгчдийн жагсаалт</p>
        </div>
        <div style="background:rgba(255,255,255,0.2); border-radius:30px; padding:8px 18px; font-size:22px; font-weight:800;">
            <?= count($pending) ?>
        </div>
    </div>
    <div class="card-body" style="padding:24px; background:var(--bg);">
        <?php if (!$pending): ?>
            <div style="text-align:center; padding:60px 20px; color:var(--muted);">
                <div style="width:80px; height:80px; border-radius:50%; background:rgba(16,185,129,0.1); color:var(--success); display:flex; align-items:center; justify-content:center; font-size:36px; margin:0 auto 20px;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3 style="font-size:20px; font-weight:600; color:var(--text); margin-bottom:8px;">Шинэ хүсэлт алга байна</h3>
                <p style="font-size:14px;">Одоогоор хүлээгдэж буй бүртгэлийн хүсэлт байхгүй байна.</p>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                <?php foreach($pending as $p): ?>
                <div style="background:var(--card-bg); border-radius:12px; border:1px solid var(--border); overflow:hidden; display:flex; flex-direction:column; transition:transform 0.2s, box-shadow 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.05);"
                     onmouseenter="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.1)'"
                     onmouseleave="this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">

                    <!-- Card Header -->
                    <div style="padding:14px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <?php if ($p['role_id'] == 4): ?>
                                <span class="badge badge-student" style="font-size:11px; padding:4px 10px;"><i class="fas fa-user-graduate"></i> Сурагч</span>
                            <?php elseif ($p['role_id'] == 5): ?>
                                <span class="badge badge-parent" style="font-size:11px; padding:4px 10px;"><i class="fas fa-user-friends"></i> Эцэг эх</span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size:11px; padding:4px 10px;"><i class="fas fa-user"></i> Бусад</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px; color:var(--muted); display:flex; align-items:center; gap:4px;">
                            <i class="far fa-clock"></i> <?= mnDateTime($p['created_at']) ?>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div style="padding:18px 16px; flex-grow:1;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                            <div style="width:46px; height:46px; border-radius:12px; background:linear-gradient(135deg, var(--primary), #818cf8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:800; flex-shrink:0;">
                                <?= mb_strtoupper(mb_substr($p['first_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:16px; color:var(--text); font-weight:700; line-height:1.2;">
                                    <?= h($p['last_name'] . ' ' . $p['first_name']) ?>
                                </h3>
                                <div style="color:var(--primary); font-weight:600; font-size:12px; margin-top:2px;">
                                    @<?= h($p['username']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Info -->
                        <div style="font-size:13px; color:var(--text); margin-bottom:14px; display:flex; flex-direction:column; gap:6px;">
                            <?php if($p['phone']): ?>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-phone-alt" style="width:16px; color:var(--primary); font-size:12px;"></i>
                                <span><?= h($p['phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($p['email']): ?>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-envelope" style="width:16px; color:var(--primary); font-size:12px;"></i>
                                <span><?= h($p['email']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Detail Toggle Button -->
                        <button type="button" class="btn-detail" id="btn-<?= $p['id'] ?>"
                                onclick="toggleDetail(<?= $p['id'] ?>)">
                            <i class="fas fa-eye" id="icon-<?= $p['id'] ?>"></i>
                            <span id="label-<?= $p['id'] ?>">Дэлгэрэнгүй харах</span>
                        </button>

                        <!-- Detail Section -->
                        <div class="detail-section" id="detail-<?= $p['id'] ?>">
                            <div class="detail-row">
                                <span class="detail-key">ID:</span>
                                <span class="detail-val">#<?= $p['id'] ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-key">Нэвтрэх нэр:</span>
                                <span class="detail-val highlight"><?= h($p['username']) ?></span>
                            </div>
                            <?php if ($p['role_id'] == 4): ?>
                            <div class="detail-row">
                                <span class="detail-key">РД:</span>
                                <span class="detail-val highlight"><?= h($p['register_no'] ?: '—') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-key">Хүйс:</span>
                                <span class="detail-val"><?= $p['gender'] == 'male' ? '👨 Эрэгтэй' : '👩 Эмэгтэй' ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-key">Төрсөн огноо:</span>
                                <span class="detail-val"><?= $p['birth_date'] ? mnDate($p['birth_date']) : '—' ?></span>
                            </div>
                            <?php else: ?>
                            <div class="detail-row">
                                <span class="detail-key">Хүүхдийн РД:</span>
                                <span class="detail-val warning-val"><?= h($p['student_register_no'] ?: '—') ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-key">Утас:</span>
                                <span class="detail-val"><?= h($p['phone'] ?: '—') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-key">Имэйл:</span>
                                <span class="detail-val"><?= h($p['email'] ?: '—') ?></span>
                            </div>
                            <?php if($p['address']): ?>
                            <div class="detail-row" style="flex-direction:column; align-items:flex-start; gap:4px;">
                                <span class="detail-key">Хаяг:</span>
                                <span class="detail-val" style="font-size:12px; line-height:1.5;"><?= nl2br(h($p['address'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-key">Хүсэлт:</span>
                                <span class="detail-val" style="color:var(--muted);"><?= mnDateTime($p['created_at']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div style="padding:14px 16px; background:rgba(0,0,0,0.01); border-top:1px solid var(--border);">
                        <form method="POST" style="display:flex; flex-direction:column; gap:10px;"
                              onsubmit="if(this.dataset.submitted) return false; this.dataset.submitted=true; const f=this; setTimeout(function(){ f.querySelectorAll('button[type=submit]').forEach(b => {b.disabled=true; b.style.opacity='0.6';}) }, 10); return true;">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">

                            <?php if ($p['role_id'] == 4): ?>
                            <div style="margin-bottom:0;">
                                <label style="font-size:12px; font-weight:600; color:var(--muted); margin-bottom:4px; display:block;"><i class="fas fa-chalkboard"></i> Анги сонгох:</label>
                                <select name="class_id" class="form-control" style="padding:8px 12px; font-size:13px; border-radius:8px;" required>
                                    <option value="">-- Анги сонгох --</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?= $c['class_id'] ?>"><?= h($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div style="display:flex; gap:8px;">
                                <button type="submit" name="action" value="approve" class="btn btn-success"
                                        style="flex:1; justify-content:center; padding:10px; border-radius:10px; font-size:13px;"
                                        onclick="return confirm('Энэ бүртгэлийг баталгаажуулж системд нэмэх үү?')">
                                    <i class="fas fa-check"></i> Батлах
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger"
                                        style="padding:10px 14px; border-radius:10px;"
                                        onclick="return confirm('Энэ бүртгэлээс татгалзах уу?')"
                                        title="Татгалзах">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetail(id) {
    const detail = document.getElementById('detail-' + id);
    const btn    = document.getElementById('btn-' + id);
    const icon   = document.getElementById('icon-' + id);
    const label  = document.getElementById('label-' + id);
    const isOpen = detail.classList.contains('visible');

    if (isOpen) {
        detail.classList.remove('visible');
        btn.classList.remove('expanded');
        icon.className = 'fas fa-eye';
        label.textContent = 'Дэлгэрэнгүй харах';
    } else {
        detail.classList.add('visible');
        btn.classList.add('expanded');
        icon.className = 'fas fa-eye-slash';
        label.textContent = 'Нуух';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
