<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/GoogleAuthenticator.php';
requireLogin();
$pageTitle = 'Миний профайл';
$uid = $_SESSION['user_id'];

// Нууц үг солих
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    verifyCsrf();
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2= $_POST['new_password2'] ?? '';
    $meRow = dbOne("SELECT password_hash FROM users WHERE user_id=?", [$uid]);
    $meHash = $meRow['password_hash'] ?? '';
    if (!checkPassword($old, $meHash)) {
        setFlash('error', 'Хуучин нууц үг буруу байна!');
    } elseif ($pwErr = validatePassword($new)) {
        setFlash('error', $pwErr);
    } elseif ($new !== $new2) {
        setFlash('error', 'Шинэ нууц үгнүүд тохирохгүй байна!');
    } else {
        dbUpdate("UPDATE users SET password_hash=? WHERE user_id=?", [hashPassword($new), $uid]);
        auditLog('password_changed', $uid, 'Нууц үг өөрчлөгдлөө');
        setFlash('success', 'Нууц үг амжилттай солигдлоо!');
    }
    header('Location: /school_system1/pages/profile/index.php'); exit;
}

// Мэдээлэл шинэчлэх
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verifyCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    if (!$fullName) {
        setFlash('error', 'Нэр хоосон байж болохгүй!');
    } elseif ($email && !isValidEmail($email)) {
        setFlash('error', 'Зөв имэйл хаяг оруулна уу!');
    } else {
        $me = dbOne("SELECT profile_image FROM users WHERE user_id=?", [$uid]);
        $profileImage = $me['profile_image'] ?? null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadProfileImage($_FILES['profile_image']);
            if ($uploaded) {
                // Delete old image if exists
                if ($profileImage && file_exists(__DIR__ . '/../../' . $profileImage)) {
                    unlink(__DIR__ . '/../../' . $profileImage);
                }
                $profileImage = $uploaded;
            }
        }

        dbUpdate("UPDATE users SET full_name=?, email=?, phone=?, profile_image=? WHERE user_id=?",
            [$fullName, $email, $phone, $profileImage, $uid]);
        $_SESSION['full_name'] = $fullName;
        auditLog('profile_updated', $uid, 'Профайл шинэчлэгдлээ');
        setFlash('success', 'Профайл амжилттай шинэчлэгдлээ!');
    }
    header('Location: /school_system1/pages/profile/index.php'); exit;
}

// 2FA тохиргоо dedicated хуудасуудад хийгдэнэ:
// Идэвхжүүлэх: /school_system1/auth/2fa_setup.php
// Идэвхгүй болгох: /school_system1/auth/2fa_disable.php

$me = dbOne("SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.user_id=?", [$uid]);

include __DIR__ . '/../../includes/header.php';
?>

<div style="max-width:700px;margin:0 auto;display:flex;flex-direction:column;gap:20px">

<!-- Профайл мэдээлэл -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-user-circle"></i> Миний мэдээлэл</h2>
  </div>
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
      <div class="avatar-container" style="position:relative">
         <img src="<?= getUserAvatar($me['profile_image'], $me['full_name']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary-light);box-shadow:0 4px 12px rgba(0,0,0,0.1)" alt="Avatar">
      </div>
      <div>
        <div style="font-size:20px;font-weight:700;color:var(--text-dark)"><?= h($me['full_name']) ?></div>
        <div style="color:var(--muted);margin-bottom:5px">@<?= h($me['username']) ?></div>
        <span class="badge badge-<?= h($me['role_name']) ?>" style="text-transform:uppercase;font-size:10px;letter-spacing:0.5px"><?= h($me['role_name']) ?></span>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="update">
      <div class="form-row">
        <div class="form-group">
          <label>Бүтэн нэр *</label>
          <input type="text" name="full_name" class="form-control" value="<?= h($me['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Нэвтрэх нэр</label>
          <input type="text" class="form-control" value="<?= h($me['username']) ?>" disabled style="opacity:.6">
        </div>
        <div class="form-group">
          <label>Имэйл</label>
          <input type="email" name="email" class="form-control" value="<?= h($me['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Утасны дугаар</label>
          <input type="text" name="phone" class="form-control" value="<?= h($me['phone'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column: span 2">
          <label><i class="fas fa-camera"></i> Профайл зураг солих</label>
          <input type="file" name="profile_image" class="form-control" accept="image/png, image/jpeg, image/webp">
          <small style="color:var(--muted)">Зөвшөөрөгдөх формат: JPG, PNG, WEBP. Дээд хэмжээ: 2МБ</small>
        </div>
      </div>
      <div style="margin-top:8px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
      </div>
    </form>
  </div>
</div>

<!-- Нууц үг солих -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-lock"></i> Нууц үг солих</h2>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="password">
      <div class="form-group">
        <label>Хуучин нууц үг</label>
        <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Шинэ нууц үг <small style="color:#9ca3af">(8+ тэмдэгт, үсэг + тоо)</small></label>
          <input type="password" name="new_password" class="form-control" placeholder="••••••••" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Шинэ нууц үгийг давтах</label>
          <input type="password" name="new_password2" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
      <button type="submit" class="btn btn-warning" onclick="return confirm('Нууц үг солих уу?')">
        <i class="fas fa-key"></i> Нууц үг солих
      </button>
    </form>
  </div>
</div>

<!-- Бүртгэлийн дэлгэрэнгүй -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-info-circle"></i> Системийн мэдээлэл</h2></div>
  <div class="card-body">
    <table style="width:100%;font-size:14px">
      <tr><td style="color:var(--muted);padding:8px 0;width:180px">Хэрэглэгчийн ID</td><td>#<?= $me['user_id'] ?></td></tr>
      <tr><td style="color:var(--muted);padding:8px 0">Эрхийн төрөл</td><td><span class="badge badge-<?= h($me['role_name']) ?>"><?= h($me['role_name']) ?></span></td></tr>
      <tr><td style="color:var(--muted);padding:8px 0">Бүртгэгдсэн огноо</td><td><?= mnDateTime($me['created_at']) ?></td></tr>
      <tr><td style="color:var(--muted);padding:8px 0">Имэйл</td><td><?= h($me['email'] ?? '—') ?></td></tr>
      <tr><td style="color:var(--muted);padding:8px 0">Утас</td><td><?= h($me['phone'] ?? '—') ?></td></tr>
    </table>
  </div>
</div>

<!-- 2FA Тохиргоо -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h2><i class="fas fa-shield-alt"></i> 2-Алхамт баталгаажуулалт (2FA)</h2>
    <?php if ($me['two_factor_enabled'] == 1): ?>
      <span class="badge" style="background:var(--success);color:#fff;font-size:11px;padding:4px 10px;border-radius:20px;">
        <i class="fas fa-check-circle"></i> Идэвхтэй
      </span>
    <?php else: ?>
      <span class="badge" style="background:var(--warning);color:#fff;font-size:11px;padding:4px 10px;border-radius:20px;">
        <i class="fas fa-exclamation-circle"></i> Идэвхгүй
      </span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($me['two_factor_enabled'] == 1): ?>
      <div style="display:flex;align-items:center;gap:16px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:12px;padding:16px;margin-bottom:20px;">
        <div style="font-size:32px;color:var(--success);"><i class="fas fa-shield-alt"></i></div>
        <div>
          <div style="font-weight:600;color:var(--success);margin-bottom:4px;">Бүртгэл хамгаалагдсан</div>
          <div style="font-size:13px;color:var(--muted);">Нэвтрэх бүрт Google Authenticator код шаардагдана.</div>
        </div>
      </div>
      <a href="/school_system1/auth/2fa_disable.php" class="btn btn-danger">
        <i class="fas fa-power-off"></i> 2FA Идэвхгүй болгох
      </a>
    <?php else: ?>
      <div style="display:flex;align-items:center;gap:16px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:12px;padding:16px;margin-bottom:20px;">
        <div style="font-size:32px;color:var(--warning);"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
          <div style="font-weight:600;color:var(--warning);margin-bottom:4px;">Бүртгэл хамгаалагдаагүй байна</div>
          <div style="font-size:13px;color:var(--muted);">2FA идэвхжүүлснээр бүртгэлийн аюулгүй байдлыг нэмэгдүүлнэ.</div>
        </div>
      </div>
      <a href="/school_system1/auth/2fa_setup.php" class="btn btn-primary">
        <i class="fas fa-shield-alt"></i> 2FA Идэвхжүүлэх
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if($me['role_name'] === 'student'): 
    // УР ЧАДВАРЫН ГРАФИК (Spider Chart)
    $stuRow = dbOne("SELECT student_id FROM students WHERE user_id=?", [$uid]);
    $stuId = $stuRow['student_id'] ?? 0;
    $gradesData = dbQuery("SELECT s.subject_name, g.grade_value as score 
                           FROM grades g JOIN subjects s ON g.subject_id=s.subject_id 
                           WHERE g.student_id=?", [$stuId]);
    
    $cats = [
      'Математик' => ['Математик','Геометр','Тоо'],
      'Хэл' => ['Монгол хэл','Англи','Орос','Хэл','Уран зохиол'],
      'Байгалийн ухаан' => ['Физик','Хими','Биологи','Байгаль'],
      'Нийгмийн ухаан' => ['Түүх','Нийгэм','Газар'],
      'Урлаг & Спорт' => ['Биеийн тамир','Зураг','Дуу','Мэдээлэл']
    ];

    $catScores = [];
    foreach($cats as $catName => $subjects) {
        $sum = 0; $count = 0;
        foreach($gradesData as $g) {
            $matched = false;
            foreach($subjects as $s) {
                if(mb_stripos($g['subject_name'], $s) !== false) {
                   $matched = true;
                   break;
                }
            }
            if($matched) {
                $sum += $g['score'];
                $count++;
            }
        }
        $catScores[$catName] = $count > 0 ? round($sum / $count, 1) : 0;
    }
?>

<div class="card" style="margin-top:20px; border:none; background: linear-gradient(135deg, #1e293b, #334155); color:#fff;">
    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
        <h2 style="color:#fff;"><i class="fas fa-chart-pie"></i> Ур чадварын аалзны тор график</h2>
    </div>
    <div class="card-body" style="padding:40px; position:relative; height:400px; width:100%; display:flex; justify-content:center;">
        <canvas id="skillRadar"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctxRadar = document.getElementById('skillRadar').getContext('2d');
new Chart(ctxRadar, {
    type: 'radar',
    data: {
        labels: <?= json_encode(array_keys($catScores)) ?>,
        datasets: [{
            label: 'Ур чадварын түвшин',
            data: <?= json_encode(array_values($catScores)) ?>,
            backgroundColor: 'rgba(99, 102, 241, 0.4)',
            borderColor: 'rgba(129, 140, 248, 1)',
            borderWidth: 3,
            pointBackgroundColor: 'rgba(255, 255, 255, 1)',
            pointBorderColor: '#6366f1',
            pointRadius: 4
        }]
    },
    options: {
        scales: {
            r: {
                angleLines: { color: 'rgba(255,255,255,0.1)' },
                grid: { color: 'rgba(255,255,255,0.1)' },
                pointLabels: { color: '#fff', font: { size: 14, weight: 'bold' } },
                suggestedMin: 0,
                suggestedMax: 100,
                ticks: { backdropColor: 'transparent', color: 'rgba(255,255,255,0.5)', stepSize: 20 }
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>
<?php endif; ?>


</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

