<?php
/**
 * auth/2fa_disable.php — 2FA идэвхгүй болгох хуудас
 *
 * Аюулгүй байдлын шаардлага:
 *   1. Хэрэглэгч нэвтэрсэн байх
 *   2. 2FA идэвхтэй байх
 *   3. Одоогийн нууц үгийг зөв оруулах
 *   4. Одоогийн TOTP кодыг зөв оруулах
 *
 * Хоёулан зөв байвал л 2FA-г унтраана.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/GoogleAuthenticator.php';

// Нэвтэрсэн эсэхийг шалгах
requireLogin();

$uid = $_SESSION['user_id'];
$me  = dbOne("SELECT user_id, username, password_hash, two_factor_enabled, two_factor_secret FROM users WHERE user_id=?", [$uid]);

// 2FA идэвхгүй байвал profile руу буцаана
if (!$me || $me['two_factor_enabled'] != 1) {
    setFlash('info', '2FA аль хэдийн идэвхгүй байна.');
    header('Location: /school_system1/pages/profile/index.php');
    exit;
}

$error = '';

// ── POST: Нууц үг + TOTP шалгах, 2FA унтраах ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Rate limiting: 5 минутад 5 оролдлого
    if (!checkRateLimit('2fa_disable_' . $uid, 5, 300)) {
        $error = 'Хэт олон оролдлого. 5 минут хүлээнэ үү.';
    } else {
        $password = $_POST['password'] ?? '';
        $code     = trim($_POST['code'] ?? '');

        // 1. Нууц үг шалгах
        if (empty($password)) {
            $error = 'Нууц үгийг оруулна уу.';
        } elseif (!checkPassword($password, $me['password_hash'])) {
            $error = 'Нууц үг буруу байна.';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            // 2. TOTP кодын формат шалгах
            $error = 'Яг 6 оронтой тоо оруулна уу.';
        } elseif (empty($me['two_factor_secret'])) {
            $error = '2FA нууц олдсонгүй. Профайл хуудас руу буцна уу.';
        } elseif (!GoogleAuthenticator::verifyCode($me['two_factor_secret'], $code, 1)) {
            // 3. TOTP код шалгах
            $error = 'Authenticator код буруу байна. Дахин оролдоно уу.';
        } else {
            // ✅ Хоёулан зөв — 2FA-г унтраана
            try {
                dbUpdate(
                    "UPDATE users SET two_factor_enabled=0, two_factor_secret=NULL WHERE user_id=?",
                    [$uid]
                );
                auditLog('2fa_disabled', $uid, '2FA амжилттай идэвхгүй болгогдлоо');
                setFlash('success', '🔓 2-Алхамт баталгаажуулалт идэвхгүй болголоо.');
                header('Location: /school_system1/pages/profile/index.php');
                exit;
            } catch (Exception $e) {
                $error = 'Тохиргоо хадгалахад алдаа гарлаа. Дахин оролдоно уу.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= pageTitle('2FA Идэвхгүй болгох') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
  .disable-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 16px;
    background: var(--bg-main, #f1f5f9);
  }
  .disable-card {
    width: 100%;
    max-width: 460px;
    background: var(--card-bg, #fff);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid var(--border-color, #e2e8f0);
  }
  .disable-header {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    padding: 28px 32px;
    text-align: center;
    color: #fff;
  }
  .disable-header .icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    margin: 0 auto 14px;
  }
  .disable-header h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
  .disable-header p  { margin: 0; opacity: 0.9; font-size: 13px; line-height: 1.5; }

  .warning-banner {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 10px;
    padding: 14px 16px;
    color: #dc2626;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 24px;
    display: flex; gap: 10px; align-items: flex-start;
  }
  .warning-banner i { margin-top: 2px; flex-shrink: 0; }

  .disable-body { padding: 28px 32px; }

  .form-field { margin-bottom: 18px; }
  .form-field label {
    display: block;
    font-size: 13px; font-weight: 600;
    color: var(--text-dark, #334155);
    margin-bottom: 8px;
  }
  .form-field input {
    display: block;
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--border-color, #e2e8f0);
    border-radius: 10px;
    font-size: 15px;
    background: var(--bg-main, #f8fafc);
    color: var(--text-dark, #0f172a);
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
  }
  .form-field input:focus {
    outline: none;
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
  }
  .code-field input {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.5em;
    text-align: center;
  }
  .error-msg {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px;
    padding: 12px 16px;
    color: #dc2626;
    font-size: 13px;
    margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
  }
  .btn-disable {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .btn-disable:hover  { opacity: 0.92; transform: translateY(-1px); }
  .btn-disable:active { transform: translateY(0); }
  .back-link {
    display: block; text-align: center;
    margin-top: 16px; font-size: 13px;
    color: var(--muted, #64748b); text-decoration: none;
  }
  .back-link:hover { color: #6366f1; }
  .divider { border: none; border-top: 1px solid var(--border-color, #e2e8f0); margin: 20px 0; }

  /* TOTP timer */
  .timer-row { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
  .timer-bar  { flex: 1; height: 3px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
  .timer-fill { height: 100%; background: #ef4444; transition: width 1s linear; }
  .timer-text { font-size: 11px; color: var(--muted, #94a3b8); white-space: nowrap; }
</style>
</head>
<body>
<div class="disable-wrapper">
  <div class="disable-card">

    <!-- Header -->
    <div class="disable-header">
      <div class="icon"><i class="fas fa-shield-alt"></i></div>
      <h1>2FA Идэвхгүй болгох</h1>
      <p>Энэ үйлдлийг гүйцэтгэхийн тулд нууц үг болон authenticator кодоо оруулна уу</p>
    </div>

    <!-- Body -->
    <div class="disable-body">

      <!-- Warning banner -->
      <div class="warning-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
          2FA идэвхгүй болгосноор таны бүртгэлийн аюулгүй байдал <strong>буурна</strong>.
          Нэвтрэх үед зөвхөн нууц үг шаардагдах болно.
        </div>
      </div>

      <!-- Error -->
      <?php if ($error): ?>
      <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
      <?php endif; ?>

      <!-- Form -->
      <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

        <!-- Current password -->
        <div class="form-field">
          <label for="password"><i class="fas fa-lock"></i> Одоогийн нууц үг</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
            autofocus
          >
        </div>

        <hr class="divider">

        <!-- TOTP code -->
        <div class="form-field code-field">
          <label for="code"><i class="fas fa-mobile-alt"></i> Authenticator код</label>
          <input
            type="text"
            id="code"
            name="code"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
            required
          >
          <!-- TOTP timer -->
          <div class="timer-row">
            <div class="timer-bar"><div class="timer-fill" id="timerFill"></div></div>
            <div class="timer-text">Код шинэчлэгдэх: <span id="timerSec">--</span>с</div>
          </div>
        </div>

        <button type="submit" class="btn-disable"
          onclick="return confirm('2FA-г идэвхгүй болгохдоо итгэлтэй байна уу?\nТаны бүртгэл зөвхөн нууц үгээр хамгаалагдах болно.')">
          <i class="fas fa-power-off"></i> 2FA Идэвхгүй болгох
        </button>
      </form>

      <a href="/school_system1/pages/profile/index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Цуцлах, буцах
      </a>
    </div>
  </div>
</div>

<script>
// TOTP 30 секундийн таймер
function updateTimer() {
  const now       = Math.floor(Date.now() / 1000);
  const remaining = 30 - (now % 30);
  const pct       = (remaining / 30) * 100;
  document.getElementById('timerSec').textContent = remaining;
  const fill = document.getElementById('timerFill');
  fill.style.width      = pct + '%';
  fill.style.background = remaining <= 5 ? '#7f1d1d' : '#ef4444';
}
updateTimer();
setInterval(updateTimer, 1000);

// Зөвхөн тоо оруулахыг зөвшөөрөх
document.getElementById('code').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 6);
});
</script>
</body>
</html>
