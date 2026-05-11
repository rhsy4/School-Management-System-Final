<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) { header('Location: /school_system1/dashboard.php'); exit; }

$pageTitle = 'Шинэ нууц үг зохиох';
$token = trim($_GET['token'] ?? '');

if (!$token) {
    header('Location: /school_system1/auth/forgot.php'); exit;
}

$user = dbOne("SELECT user_id, username, reset_expires FROM users WHERE reset_token=? AND is_active=1", [$token]);
$expired = !$user || strtotime($user['reset_expires']) < time();

$done = false;
$error = '';

if (!$expired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password_confirm'] ?? '';
    if (strlen($pw) < 8) {
        $error = 'Нууц үг дор хаяж 8 тэмдэгт байх ёстой.';
    } elseif ($pw !== $pw2) {
        $error = 'Нууц үгнүүд тохирохгүй байна.';
    } else {
        dbUpdate(
            "UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE user_id=?",
            [hashPassword($pw), $user['user_id']]
        );
        auditLog('password_reset', $user['user_id'], $user['username'] . ' нууц үгээ сэргээлэ');
        setFlash('success', 'Нууц үг амжилттай шинэчлэгдлээ! Шинэ нууц үгээрээ нэвтэрнэ үү.');
        header('Location: /school_system1/index.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Шинэ нууц үг | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/school_system1/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <button class="dark-toggle" id="darkModeToggle" onclick="toggleDarkMode()" title="Dark / Light горим"
    style="position:fixed;top:16px;right:16px;z-index:999">
    <i class="fas fa-moon" id="darkIcon"></i>
    <span id="darkLabel">Dark</span>
  </button>
  <div class="login-box">
    <div class="login-logo">
      <i class="fas fa-lock"></i>
      <h1>Шинэ нууц үг зохиох</h1>
      <p><?= SITE_NAME ?></p>
    </div>

    <?php if ($expired): ?>
      <div class="flash flash-error">
        <i class="fas fa-exclamation-triangle"></i>
        Холбоос хүчингүй болсон эсвэл цагийн хязгаар (1 цаг) дууссан байна.
      </div>
      <div style="text-align:center;margin-top:16px">
        <a href="/school_system1/auth/forgot.php" class="btn btn-primary">
          <i class="fas fa-redo"></i> Дахин илгээх
        </a>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
      <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Шинэ нууц үг <small style="color:#9ca3af">(дор хаяж 8 тэмдэгт)</small></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" autofocus required>
        </div>
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Нууц үгийг давтах</label>
          <input type="password" name="password_confirm" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
          <i class="fas fa-save"></i> Нууц үг хадгалах
        </button>
      </form>

      <div style="text-align:center;margin-top:16px">
        <a href="/school_system1/index.php" style="font-size:14px;color:#6b7280">
          <i class="fas fa-arrow-left"></i> Буцах
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

