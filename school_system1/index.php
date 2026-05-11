<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Nэвтэрсэн бол dashboard руу
if (isLoggedIn()) {
    header('Location: /school_system1/dashboard.php');
    exit;
}

$error = '';

// Бүртгэлийн нээлт / хаалт төлөв DB-ээс унших
$regSetting = dbOne("SELECT setting_value FROM settings WHERE setting_key='registration_open'");
$isRegOpen  = ($regSetting && $regSetting['setting_value'] === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $error = 'Нэвтрэх нэр болон нууц үгийг оруулна уу!';
        } else {
            $user = dbOne(
                "SELECT u.*, r.role_name FROM users u
                 JOIN user_roles r ON u.role_id = r.role_id
                 WHERE u.username = ? AND u.is_active = 1", [$username]
            );

            if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $error = 'Таны бүртгэл олон удаагийн буруу оролдлогоос болж түр хаагдсан байна. ' . mnDateTime($user['locked_until']) . ' хүртэл хүлээнэ үү.';
            } elseif ($user && checkPassword($password, $user['password_hash'])) {
                // Reset failed logins
                dbUpdate("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE user_id = ?", [$user['user_id']]);
                
                if ($user['two_factor_enabled'] == 1) {
                    // Requires 2FA
                    $_SESSION['_2fa_pending_uid'] = $user['user_id'];
                    header('Location: /school_system1/verify_2fa.php');
                    exit;
                }

                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role_name'];
                $_SESSION['role_id']   = $user['role_id'];
                $_SESSION['can_edit_grades'] = (bool)($user['can_edit_grades'] ?? 0);
                $_SESSION['can_post_announcements'] = (bool)($user['can_post_announcements'] ?? 1);
                $_SESSION['last_activity'] = time();

                // Check Device (New Device Email)
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $deviceHash = md5($ip . $ua);
                $device = dbOne("SELECT * FROM user_devices WHERE user_id=? AND device_hash=?", [$user['user_id'], $deviceHash]);
                if (!$device) {
                    dbExec("INSERT INTO user_devices (user_id, device_hash, ip_address, user_agent) VALUES (?,?,?,?)", [$user['user_id'], $deviceHash, $ip, $ua]);
                    // If they have email, send alert
                    if (!empty($user['email'])) {
                        require_once __DIR__ . '/includes/notification.php';
                        $ns = new NotificationService();
                        $ns->sendEmail($user['email'], 'Шинэ төхөөрөмжөөс нэвтэрлээ', "Таны бүртгэлээр шинэ төхөөрөмж/хөтчөөс нэвтэрлээ.<br>IP: $ip<br>Төхөөрөмж: $ua");
                    }
                } else {
                    dbUpdate("UPDATE user_devices SET last_used_at = NOW() WHERE device_id=?", [$device['device_id']]);
                }

                auditLog('login', null, $username . ' нэвтэрлээ');
                header('Location: /school_system1/dashboard.php');
                exit;
            } else {
                if ($user) {
                    dbUpdate("UPDATE users SET failed_logins = failed_logins + 1, 
                              locked_until = IF(failed_logins >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL) 
                              WHERE user_id = ?", [$user['user_id']]);
                    if ($user['failed_logins'] >= 4) {
                        $error = 'Таны бүртгэл олон удаагийн буруу оролдлогоос болж 15 минут хаагдлаа.';
                    } else {
                        $error = 'Нэвтрэх нэр эсвэл нууц үг буруу байна! (' . (5 - ($user['failed_logins'] + 1)) . ' оролдлого үлдлээ)';
                    }
                } else {
                    $error = 'Нэвтрэх нэр эсвэл нууц үг буруу байна!';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Нэвтрэх | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: radial-gradient(circle at top left, #f1f5f9 0%, #e2e8f0 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    [data-theme="dark"] body {
        background: #0f172a;
    }

    .login-wrapper {
        display: flex;
        width: 100%;
        max-width: 1300px;
        height: 100vh;
        background: #fff;
        box-shadow: 0 40px 120px rgba(0,0,0,0.12);
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    @media (min-width: 1024px) {
        .login-wrapper {
            height: auto;
            min-height: 700px;
            margin: 40px;
            border-radius: 40px;
            overflow: hidden;
            padding: 15px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            box-shadow: 0 50px 100px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.1);
            gap: 15px;
        }
    }

    [data-theme="dark"] .login-wrapper {
        background: #1e293b;
        box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        padding: 0;
        border: none;
        gap: 0;
    }

    /* Left Side: Image / Branding */
    .login-banner {
        display: none;
        position: relative;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        overflow: hidden;
    }

    @media (min-width: 1024px) {
        .login-banner {
            display: flex;
            flex: 1;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px;
            color: #fff;
            border-radius: 24px;
        }
    }

    .banner-bg {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: url('/school_system1/assets/img/dark_bg.png');
        background-size: cover;
        background-position: center;
        opacity: 1;
    }
    
    [data-theme="dark"] .banner-bg {
        background-image: url('/school_system1/assets/img/login_bg.png');
        opacity: 0.8;
    }

    .banner-content {
        position: relative;
        z-index: 10;
    }

    .banner-logo {
        display: flex;
        align-items: center;
        gap: 15px;
        font-family: 'Poppins', sans-serif;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .banner-logo img {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #fff;
        padding: 4px;
    }

    .banner-text {
        margin-top: auto;
        position: relative;
        z-index: 10;
    }

    .banner-text h2 {
        font-family: 'Poppins', sans-serif;
        font-size: 42px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 20px;
    }

    .banner-text p {
        font-size: 16px;
        opacity: 0.9;
        line-height: 1.6;
        max-width: 400px;
    }

    /* Right Side: Form */
    .login-form-container {
        flex: 1.2;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 50px;
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 28px;
    }

    [data-theme="dark"] .login-form-container {
        background: #1e293b;
    }

    @media (min-width: 1024px) {
        .login-form-container {
            padding: 60px 80px;
            max-width: 600px;
        }
    }

    .form-header {
        margin-bottom: 40px;
    }

    .form-header h1 {
        font-family: 'Poppins', sans-serif;
        font-size: 32px;
        font-weight: 700;
        color: #000000; /* Sharp black */
        margin: 0 0 10px 0;
        letter-spacing: -1px;
    }

    [data-theme="dark"] .form-header h1 { color: #f8fafc; }

    .form-header p {
        font-size: 15px;
        color: #475569;
        margin: 0;
    }

    [data-theme="dark"] .form-header p {
        color: #94a3b8;
    }

    [data-theme="dark"] .form-header p { color: #94a3b8; }

    .input-group {
        margin-bottom: 24px;
    }

    .input-group label {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
    }

    [data-theme="dark"] .input-group label { color: #cbd5e1; }

    .input-wrapper {
        position: relative;
    }

    .input-wrapper i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
        transition: color 0.3s;
    }

    .modern-input {
        width: 100%;
        padding: 16px 16px 16px 48px;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        font-size: 15px;
        font-family: 'Inter', sans-serif;
        background: #f8fafc;
        color: #0f172a;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    [data-theme="dark"] .modern-input {
        background: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }

    .modern-input:focus {
        outline: none;
        border-color: #6366f1;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    [data-theme="dark"] .modern-input:focus {
        background: #0f172a;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
    }

    .modern-input:focus + i {
        color: #6366f1;
    }

    .btn-login {
        width: 100%;
        padding: 18px;
        border: none;
        border-radius: 16px;
        background: #6366f1;
        color: white;
        font-size: 16px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 30px;
        margin-bottom: 25px;
    }

    .btn-login:hover {
        background: #4f46e5;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        color: #94a3b8;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 25px;
    }

    .divider::before, .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #e2e8f0;
    }

    [data-theme="dark"] .divider::before, [data-theme="dark"] .divider::after {
        border-color: #334155;
    }

    .divider:not(:empty)::before { margin-right: 15px; }
    .divider:not(:empty)::after { margin-left: 15px; }

    .btn-register {
        width: 100%;
        padding: 16px;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        background: transparent;
        color: #475569;
        font-size: 15px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        justify-content: center;
        align-items: center;
        text-decoration: none;
        box-sizing: border-box;
    }

    [data-theme="dark"] .btn-register {
        border-color: #334155;
        color: #cbd5e1;
    }

    .btn-register:hover {
        border-color: #cbd5e1;
        color: #0f172a;
        background: #f1f5f9;
    }

    [data-theme="dark"] .btn-register:hover {
        border-color: #475569;
        color: #fff;
        background: #334155;
    }

    /* Stylish Theme Toggle Switch */
    .theme-switch-wrapper {
        position: absolute;
        top: 30px;
        right: 30px;
        display: flex;
        align-items: center;
        z-index: 100;
    }

    .theme-switch {
        display: inline-block;
        height: 34px;
        position: relative;
        width: 60px;
    }

    .theme-switch input {
        display: none;
    }

    .slider {
        background-color: #cbd5e1;
        bottom: 0;
        cursor: pointer;
        left: 0;
        position: absolute;
        right: 0;
        top: 0;
        transition: .4s;
        border-radius: 34px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 8px;
    }

    .slider:before {
        background-color: #fff;
        bottom: 4px;
        content: "";
        height: 26px;
        left: 4px;
        position: absolute;
        transition: .4s;
        width: 26px;
        border-radius: 50%;
        z-index: 2;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    input:checked + .slider {
        background-color: #6366f1;
    }

    input:checked + .slider:before {
        transform: translateX(26px);
    }

    .slider i {
        font-size: 14px;
        z-index: 1;
    }

    .slider .fa-sun { color: #f59e0b; }
    .slider .fa-moon { color: #f1f5f9; }

    [data-theme="dark"] .slider { background-color: #334155; }
    [data-theme="dark"] .slider:before { background-color: #1e293b; }
    [data-theme="dark"] .slider .fa-sun { color: #475569; }
    [data-theme="dark"] .slider .fa-moon { color: #f1c40f; }

    .mobile-logo {
        display: block;
        text-align: center;
        margin-bottom: 20px;
    }

    .mobile-logo img {
        width: 64px;
        height: 64px;
        border-radius: 16px;
    }

    @media (min-width: 1024px) {
        .mobile-logo { display: none; }
    }
</style>
<script>
  (function() {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  })();
</script>
</head>
<body>

<div class="login-wrapper">
    <!-- Left Side Banner -->
    <div class="login-banner">
        <div class="banner-bg"></div>
        <div class="banner-content banner-logo">
            <img src="assets/img/logo.png" alt="Logo">
            Цахим Сургууль
        </div>
        
        <div class="banner-text">
            <h2>Ирээдүйнхээ<br>зүг хамтдаа</h2>
            <p>Сургалтын үйл ажиллагааг хөнгөвчилж, сурагч, багш, эцэг эхийн хамтын ажиллагааг нэмэгдүүлэх нэгдсэн платформ.</p>
        </div>
    </div>

    <!-- Right Side Form -->
    <div class="login-form-container">
        
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="themeCheckbox" title="Горим солих">
                <input type="checkbox" id="themeCheckbox" onchange="toggleDarkMode()" />
                <div class="slider round">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </div>
            </label>
        </div>

        <div class="mobile-logo">
            <img src="assets/img/logo.png" alt="Logo">
        </div>

        <div class="form-header">
            <h1>Тавтай морил 👋</h1>
            <p>Систем рүү нэвтрэх мэдээллээ оруулна уу.</p>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>" style="border-radius:12px; margin-bottom:20px;">
          <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
          <?= h($flash['msg']) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="flash flash-error" style="border-radius:12px; margin-bottom:20px;">
            <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            
            <div class="input-group">
                <label>Нэвтрэх нэр</label>
                <div class="input-wrapper">
                    <input type="text" name="username" class="modern-input" placeholder="Хэрэглэгчийн нэр" value="<?= h($_POST['username'] ?? '') ?>" autofocus required>
                    <i class="fas fa-user"></i>
                </div>
            </div>
            
            <div class="input-group">
                <label>
                    <span>Нууц үг</span>
                    <a href="auth/forgot.php" style="color:#6366f1; text-decoration:none;">Мартсан уу?</a>
                </label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="modern-input" placeholder="••••••••" required>
                    <i class="fas fa-lock"></i>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                Нэвтрэх
            </button>
            
            <?php if ($isRegOpen): ?>
            <div class="divider" style="margin-bottom:16px;">эсвэл</div>
            <a href="register.php" class="btn-register">
                <i class="fas fa-user-plus" style="margin-right:6px;"></i> Шинээр бүртгүүлэх
            </a>
            <?php else: ?>
            <div style="text-align:center; margin-top:16px; padding:12px 16px; background:rgba(239,68,68,0.07); border:1px solid rgba(239,68,68,0.2); border-radius:12px;">
                <i class="fas fa-lock" style="color:#ef4444;"></i>
                <span style="color:#ef4444; font-size:13px; font-weight:600; margin-left:6px;">Бүртгэл түр хаалттай байна</span>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>

