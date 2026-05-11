<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) { header('Location: /school_system1/dashboard.php'); exit; }

$success = '';
$error   = '';

// ── POST: Имэйл хүлээн авах ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Rate limiting: 15 минутад 3 оролдлого
    if (!checkRateLimit('password_reset', 3, 900)) {
        $error = 'Хэт олон оролдлого. 15 минутын дараа дахин оролдоно уу.';
    } else {
        $login = trim($_POST['login'] ?? '');
        if (!$login) {
            $error = 'Нэвтрэх нэр эсвэл имэйлээ оруулна уу.';
        } else {
            // Хэрэглэгч хайх (username эсвэл email-ээр)
            $user = dbOne(
                "SELECT user_id, email, username FROM users WHERE (username=? OR email=?) AND is_active=1",
                [$login, $login]
            );

            if ($user) {
                // Аюулгүй token үүсгэх, users хүснэгтэд хадгалах
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 цаг
                dbUpdate(
                    "UPDATE users SET reset_token=?, reset_expires=? WHERE user_id=?",
                    [$token, $expires, $user['user_id']]
                );

                $reset_link = SITE_URL . '/auth/reset.php?token=' . $token;

                // Имэйл илгээх (идэвхтэй бол)
                if (MAIL_ENABLED && !empty($user['email'])) {
                    require_once __DIR__ . '/../includes/notification.php';
                    $ns = new NotificationService();
                    $ns->sendEmail($user['email'], 'Нууц үг сэргээх',
                        "<h2>Нууц үг сэргээх</h2>
                         <p>Та нууц үг сэргээх хүсэлт илгээсэн байна.</p>
                         <p><a href='{$reset_link}' style='background:#6366f1;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Нууц үг сэргээх</a></p>
                         <p>Энэ холбоос <strong>1 цаг</strong> хүчинтэй.</p>"
                    );
                }
            }
            // User enumeration-аас хамгаалахын тулд хэрэглэгч олдсон эсэхээс үл хамааран success харуулна
            $success = 'email_sent';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Нууц үг сэргээх | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/school_system1/assets/css/style.css">
<script>
  (function() {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  })();
</script>
<style>
  /* ── Login-тэй яг ижил layout ── */
  body {
    margin: 0; font-family: 'Inter', sans-serif;
    background: radial-gradient(circle at top left, #f1f5f9 0%, #e2e8f0 100%);
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
  }
  [data-theme="dark"] body { background: #0f172a; }

  .login-wrapper {
    display: flex; width: 100%; max-width: 1300px;
    height: 100vh; background: #fff;
    box-shadow: 0 40px 120px rgba(0,0,0,0.12);
    border: 1px solid rgba(0,0,0,0.05);
  }
  @media (min-width: 1024px) {
    .login-wrapper {
      height: auto; min-height: 700px; margin: 40px;
      border-radius: 40px; overflow: hidden; padding: 15px;
      background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
      box-shadow: 0 50px 100px rgba(0,0,0,0.15);
      border: 1px solid rgba(0,0,0,0.1); gap: 15px;
    }
  }
  [data-theme="dark"] .login-wrapper {
    background: #1e293b; box-shadow: 0 25px 50px rgba(0,0,0,0.5);
    padding: 0; border: none; gap: 0;
  }

  /* Left Banner */
  .login-banner {
    display: none; position: relative;
    background: linear-gradient(135deg, #6366f1, #4f46e5); overflow: hidden;
  }
  @media (min-width: 1024px) {
    .login-banner {
      display: flex; flex: 1; flex-direction: column;
      justify-content: space-between; padding: 50px;
      color: #fff; border-radius: 24px;
    }
  }
  .banner-bg {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background-image: url('/school_system1/assets/img/dark_bg.png');
    background-size: cover; background-position: center; opacity: 1;
  }
  [data-theme="dark"] .banner-bg {
    background-image: url('/school_system1/assets/img/login_bg.png'); opacity: 0.8;
  }
  .banner-content { position: relative; z-index: 10; }
  .banner-logo {
    display: flex; align-items: center; gap: 15px;
    font-family: 'Poppins', sans-serif; font-size: 24px;
    font-weight: 700; letter-spacing: -0.5px;
  }
  .banner-logo img { width: 48px; height: 48px; border-radius: 12px; background: #fff; padding: 4px; }
  .banner-text { margin-top: auto; position: relative; z-index: 10; }
  .banner-text h2 {
    font-family: 'Poppins', sans-serif; font-size: 42px;
    font-weight: 700; line-height: 1.2; margin-bottom: 20px;
  }
  .banner-text p { font-size: 16px; opacity: 0.9; line-height: 1.6; max-width: 400px; }

  /* Right Form */
  .login-form-container {
    flex: 1.2; display: flex; flex-direction: column;
    justify-content: center; padding: 50px;
    width: 100%; max-width: 500px; margin: 0 auto;
    background: #ffffff; border-radius: 28px;
  }
  [data-theme="dark"] .login-form-container { background: #1e293b; }
  @media (min-width: 1024px) {
    .login-form-container { padding: 60px 80px; max-width: 600px; }
  }

  .form-header { margin-bottom: 36px; }
  .form-header h1 {
    font-family: 'Poppins', sans-serif; font-size: 32px;
    font-weight: 700; color: #000; margin: 0 0 10px;
    letter-spacing: -1px;
  }
  [data-theme="dark"] .form-header h1 { color: #f8fafc; }
  .form-header p { font-size: 15px; color: #475569; margin: 0; }
  [data-theme="dark"] .form-header p { color: #94a3b8; }

  .input-group { margin-bottom: 24px; }
  .input-group label {
    display: block; font-size: 14px; font-weight: 600;
    color: #334155; margin-bottom: 8px;
  }
  [data-theme="dark"] .input-group label { color: #cbd5e1; }
  .input-wrapper { position: relative; }
  .input-wrapper i {
    position: absolute; left: 18px; top: 50%;
    transform: translateY(-50%); color: #94a3b8; font-size: 18px;
  }
  .modern-input {
    width: 100%; padding: 16px 16px 16px 48px;
    border: 2px solid #e2e8f0; border-radius: 16px;
    font-size: 15px; font-family: 'Inter', sans-serif;
    background: #f8fafc; color: #0f172a;
    transition: all 0.3s ease; box-sizing: border-box;
  }
  [data-theme="dark"] .modern-input { background: #0f172a; border-color: #334155; color: #f8fafc; }
  .modern-input:focus {
    outline: none; border-color: #6366f1;
    background: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
  }
  [data-theme="dark"] .modern-input:focus { background: #0f172a; }

  .btn-primary-login {
    width: 100%; padding: 18px; border: none; border-radius: 16px;
    background: #6366f1; color: white; font-size: 16px; font-weight: 600;
    font-family: 'Inter', sans-serif; cursor: pointer;
    transition: all 0.3s ease; display: flex;
    justify-content: center; align-items: center; gap: 10px;
    margin-top: 8px; margin-bottom: 24px;
  }
  .btn-primary-login:hover {
    background: #4f46e5; transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(99,102,241,0.2);
  }
  .back-link {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    color: #64748b; text-decoration: none; font-size: 15px; font-weight: 500;
    padding: 16px; border: 2px solid #e2e8f0; border-radius: 16px;
    transition: all 0.3s ease;
  }
  [data-theme="dark"] .back-link { border-color: #334155; color: #cbd5e1; }
  .back-link:hover { border-color: #6366f1; color: #6366f1; background: rgba(99,102,241,0.04); }

  .alert-success {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px;
    padding: 18px 20px; color: #166534; margin-bottom: 24px;
    display: flex; align-items: flex-start; gap: 12px;
  }
  .alert-success i { font-size: 20px; margin-top: 1px; flex-shrink: 0; }
  .alert-error {
    background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25);
    border-radius: 14px; padding: 14px 16px; color: #dc2626;
    margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    font-size: 14px;
  }
  .debug-box {
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
    padding: 14px; font-size: 13px; color: #1e40af; margin-top: 16px;
  }

  /* Theme switch (login-тэй яг ижил) */
  .theme-switch-wrapper { position: absolute; top: 30px; right: 30px; z-index: 100; }
  .theme-switch { display: inline-block; height: 34px; position: relative; width: 60px; }
  .theme-switch input { display: none; }
  .slider {
    background-color: #cbd5e1; bottom: 0; cursor: pointer;
    left: 0; position: absolute; right: 0; top: 0;
    transition: .4s; border-radius: 34px;
    display: flex; align-items: center; justify-content: space-between; padding: 0 8px;
  }
  .slider:before {
    background-color: #fff; bottom: 4px; content: ""; height: 26px;
    left: 4px; position: absolute; transition: .4s; width: 26px;
    border-radius: 50%; z-index: 2; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }
  input:checked + .slider { background-color: #6366f1; }
  input:checked + .slider:before { transform: translateX(26px); }
  .slider i { font-size: 14px; z-index: 1; }
  .slider .fa-sun { color: #f59e0b; }
  .slider .fa-moon { color: #f1f5f9; }
  [data-theme="dark"] .slider { background-color: #334155; }
  [data-theme="dark"] .slider:before { background-color: #1e293b; }
  [data-theme="dark"] .slider .fa-sun { color: #475569; }
  [data-theme="dark"] .slider .fa-moon { color: #f1c40f; }

  .mobile-logo { display: block; text-align: center; margin-bottom: 20px; }
  .mobile-logo img { width: 64px; height: 64px; border-radius: 16px; }
  @media (min-width: 1024px) { .mobile-logo { display: none; } }
</style>
</head>
<body>

<div class="login-wrapper">
  <!-- Left Banner -->
  <div class="login-banner">
    <div class="banner-bg"></div>
    <div class="banner-content banner-logo">
      <img src="/school_system1/assets/img/logo.png" alt="Logo">
      <?= h(SITE_NAME) ?>
    </div>
    <div class="banner-text">
      <h2>Нууц үг<br>сэргээх</h2>
      <p>Имэйл хаягаараа нэвтрэх нэрээ сэргээж, системд дахин нэвтрэнэ үү.</p>
    </div>
  </div>

  <!-- Right Form -->
  <div class="login-form-container" style="position:relative;">

    <!-- Theme toggle -->
    <div class="theme-switch-wrapper">
      <label class="theme-switch" for="themeCheckbox" title="Горим солих">
        <input type="checkbox" id="themeCheckbox" onchange="toggleDarkMode()">
        <div class="slider round">
          <i class="fas fa-sun"></i>
          <i class="fas fa-moon"></i>
        </div>
      </label>
    </div>

    <!-- Mobile logo -->
    <div class="mobile-logo">
      <img src="/school_system1/assets/img/logo.png" alt="Logo">
    </div>

    <?php if ($success === 'email_sent'): ?>
      <!-- ── Амжилттай явсан ── -->
      <div class="form-header">
        <h1>Имэйл явлаа ✉️</h1>
        <p>Дараагийн алхмыг имэйлээсээ шалгана уу.</p>
      </div>

      <div class="alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
          <strong>Сэргээх холбоос явуулсан!</strong><br>
          Бүртгэлтэй имэйлд нууц үг сэргээх холбоос явлаа.
          Шуудан дутуу ирвэл <em>Spam</em> хавтсыг шалгана уу.
        </div>
      </div>

      <?php if (!MAIL_ENABLED && defined('DEBUG_MODE') && DEBUG_MODE): ?>
      <div class="debug-box">
        <strong><i class="fas fa-bug"></i> DEBUG горим:</strong><br>
        Имэйл илгээх идэвхгүй. Жинхэнэ серверт имэйл явна.<br>
        Тест хийхдээ <code>reset_token</code> колоноос token авна уу.
      </div>
      <?php endif; ?>

      <div style="margin-top: 24px;">
        <a href="/school_system1/index.php" class="back-link">
          <i class="fas fa-arrow-left"></i> Нэвтрэх хуудас руу буцах
        </a>
      </div>

    <?php else: ?>
      <!-- ── Оролтын форм ── -->
      <div class="form-header">
        <h1>Нууц үг мартсан уу? 🔑</h1>
        <p>Нэвтрэх нэр эсвэл бүртгэлтэй имэйлээ оруулна уу.</p>
      </div>

      <?php if ($error): ?>
      <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

        <div class="input-group">
          <label>Нэвтрэх нэр эсвэл имэйл</label>
          <div class="input-wrapper">
            <input type="text" name="login" class="modern-input"
                   placeholder="admin эсвэл admin@school.mn"
                   value="<?= h($_POST['login'] ?? '') ?>"
                   autofocus required>
            <i class="fas fa-user"></i>
          </div>
        </div>

        <button type="submit" class="btn-primary-login">
          <i class="fas fa-paper-plane"></i> Сэргээх холбоос авах
        </button>

        <a href="/school_system1/index.php" class="back-link">
          <i class="fas fa-arrow-left"></i> Нэвтрэх хуудас руу буцах
        </a>
      </form>
    <?php endif; ?>

  </div>
</div>

<script src="/school_system1/assets/js/main.js"></script>
</body>
</html>
