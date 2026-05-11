<?php
/**
 * verify_2fa.php — Нэвтрэх үеийн 2FA баталгаажуулалт
 *
 * Урсгал:
 *   index.php → нэвтрэлт амжилттай + 2FA идэвхтэй
 *            → $_SESSION['_2fa_pending_uid'] тохируулна
 *            → энэ хуудас руу redirect
 *   POST → TOTP код шалгана → амжилттай бол dashboard руу
 *
 * Хамгаалалт:
 *   - Session-д хадгалсан pending UID шаардана
 *   - Rate limiting: 5 минутад 5 оролдлого
 *   - Lockout: 5 удаа буруу оруулбал 5 минут хаана
 *   - CSRF token
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/GoogleAuthenticator.php';

// 2FA хүлээгдэж буй session байхгүй бол login руу
if (!isset($_SESSION['_2fa_pending_uid'])) {
    header('Location: /school_system1/index.php');
    exit;
}

$uid   = (int)$_SESSION['_2fa_pending_uid'];
$error = '';

// ── Rate limit шалгах (Session-д хадгална) ───────────────────────────────────
$rlKey    = '_2fa_rl_' . $uid;
$lockKey  = '_2fa_lock_' . $uid;

// Lockout шалгах
if (isset($_SESSION[$lockKey]) && time() < $_SESSION[$lockKey]) {
    $remaining = $_SESSION[$lockKey] - time();
    $error = "Хэт олон буруу оролдлого. {$remaining} секунд хүлээнэ үү.";
}

// ── POST: TOTP код шалгах ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    verifyCsrf();

    $code = trim($_POST['code'] ?? '');

    // Оролтын формат шалгах
    if (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Яг 6 оронтой тоо оруулна уу.';
    } else {
        // Rate limit: 5 минутад 5 оролдлого
        if (!isset($_SESSION[$rlKey])) {
            $_SESSION[$rlKey] = ['attempts' => 0, 'reset_at' => time() + 300];
        }
        if (time() > $_SESSION[$rlKey]['reset_at']) {
            $_SESSION[$rlKey] = ['attempts' => 0, 'reset_at' => time() + 300];
        }

        if ($_SESSION[$rlKey]['attempts'] >= 5) {
            // 5 минут lockout
            $_SESSION[$lockKey] = time() + 300;
            unset($_SESSION[$rlKey]);
            $error = 'Хэт олон буруу оролдлого. 5 минут хүлээнэ үү.';
        } else {
            // Хэрэглэгчийг DB-ээс унших
            $user = dbOne(
                "SELECT u.*, r.role_name
                 FROM users u
                 JOIN user_roles r ON u.role_id = r.role_id
                 WHERE u.user_id = ? AND u.is_active = 1",
                [$uid]
            );

            if (!$user) {
                // Хэрэглэгч устгагдсан эсвэл идэвхгүй болсон
                unset($_SESSION['_2fa_pending_uid']);
                header('Location: /school_system1/index.php');
                exit;
            }

            if (empty($user['two_factor_secret'])) {
                $error = '2FA нууц олдсонгүй. Администратортой холбогдоно уу.';
            } elseif (GoogleAuthenticator::verifyCode($user['two_factor_secret'], $code, 1)) {
                // ✅ Зөв код — session бэлдэж dashboard руу явна

                // Rate limit тоолуурыг арилгах
                unset($_SESSION[$rlKey], $_SESSION[$lockKey], $_SESSION['_2fa_pending_uid']);

                // Session ID шинэчлэх (session fixation хамгаалалт)
                session_regenerate_id(true);

                // Хэрэглэгчийн мэдээллийг session-д хадгалах
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role_name'];
                $_SESSION['role_id']   = $user['role_id'];
                $_SESSION['can_edit_grades']          = (bool)($user['can_edit_grades']          ?? false);
                $_SESSION['can_post_announcements']   = (bool)($user['can_post_announcements']   ?? true);
                $_SESSION['last_activity'] = time();

                // Шинэ төхөөрөмж шалгах — имэйл мэдэгдэл илгээх
                $ip         = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
                $ua         = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $deviceHash = md5($ip . $ua);
                $device     = dbOne(
                    "SELECT * FROM user_devices WHERE user_id=? AND device_hash=?",
                    [$user['user_id'], $deviceHash]
                );

                if (!$device) {
                    dbExec(
                        "INSERT INTO user_devices (user_id, device_hash, ip_address, user_agent) VALUES (?,?,?,?)",
                        [$user['user_id'], $deviceHash, $ip, $ua]
                    );
                    if (!empty($user['email'])) {
                        require_once __DIR__ . '/includes/notification.php';
                        $ns = new NotificationService();
                        $ns->sendEmail(
                            $user['email'],
                            'Шинэ төхөөрөмжөөс нэвтэрлээ',
                            "Таны бүртгэлд шинэ төхөөрөмжөөс 2FA-аар нэвтэрлээ.<br>IP: {$ip}<br>Төхөөрөмж: {$ua}"
                        );
                    }
                } else {
                    dbUpdate(
                        "UPDATE user_devices SET last_used_at=NOW() WHERE device_id=?",
                        [$device['device_id']]
                    );
                }

                auditLog('login_2fa', null, $user['username'] . ' 2FA-аар нэвтэрлээ');
                header('Location: /school_system1/dashboard.php');
                exit;

            } else {
                // ❌ Буруу код — оролдлогын тоо нэмэх
                $_SESSION[$rlKey]['attempts']++;
                $left  = 5 - $_SESSION[$rlKey]['attempts'];
                $error = 'Код буруу байна.' . ($left > 0 ? " ({$left} оролдлого үлдлээ)" : '');
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
<title><?= pageTitle('2FA Баталгаажуулалт') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
  body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-main, #f1f5f9);
    padding: 20px;
  }
  .verify-card {
    width: 100%;
    max-width: 420px;
    background: var(--card-bg, #fff);
    border-radius: 24px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.12);
    overflow: hidden;
    border: 1px solid var(--border-color, #e2e8f0);
  }
  .verify-header {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    padding: 36px 32px 28px;
    text-align: center;
    color: #fff;
  }
  .verify-header .shield-icon {
    width: 68px; height: 68px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px;
    margin: 0 auto 16px;
    border: 2px solid rgba(255,255,255,0.3);
  }
  .verify-header h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
  .verify-header p  { margin: 0; opacity: 0.85; font-size: 13px; line-height: 1.5; }

  .verify-body { padding: 32px; }

  .error-msg {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 10px;
    padding: 12px 14px;
    color: #dc2626;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
  }
  .code-label {
    font-size: 13px; font-weight: 600;
    color: var(--text-dark, #334155);
    margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
  }
  .code-input {
    display: block; width: 100%;
    padding: 16px;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 0.6em;
    text-align: center;
    border: 2px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    background: var(--bg-main, #f8fafc);
    color: var(--text-dark, #0f172a);
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
    margin-bottom: 10px;
  }
  .code-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.12);
    background: #fff;
  }
  .code-input.is-error { border-color: #ef4444; }

  /* TOTP timer */
  .timer-row { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
  .timer-bar  { flex: 1; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
  .timer-fill { height: 100%; background: #6366f1; transition: width 1s linear; }
  .timer-text { font-size: 11px; color: var(--muted, #94a3b8); white-space: nowrap; flex-shrink: 0; }

  .btn-verify {
    width: 100%; padding: 15px;
    border: none; border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    font-size: 15px; font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-bottom: 16px;
  }
  .btn-verify:hover  { opacity: 0.92; transform: translateY(-1px); }
  .btn-verify:active { transform: translateY(0); }

  .back-link {
    display: block; text-align: center;
    font-size: 13px; color: var(--muted, #64748b);
    text-decoration: none;
  }
  .back-link:hover { color: #6366f1; }

  .hint-box {
    background: var(--bg-main, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    color: var(--muted, #64748b);
    line-height: 1.6;
    margin-bottom: 20px;
  }
  .hint-box i { color: #6366f1; margin-right: 4px; }
</style>
</head>
<body>
<div class="verify-card">

  <!-- Header -->
  <div class="verify-header">
    <div class="shield-icon"><i class="fas fa-shield-alt"></i></div>
    <h1>2-Алхамт Баталгаажуулалт</h1>
    <p>Google Authenticator аппаас 6 оронтой кодыг оруулна уу</p>
  </div>

  <!-- Body -->
  <div class="verify-body">

    <!-- Error -->
    <?php if ($error): ?>
    <div class="error-msg">
      <i class="fas fa-exclamation-circle"></i>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <!-- Hint -->
    <div class="hint-box">
      <i class="fas fa-mobile-alt"></i> Гар утасны <strong>Google Authenticator</strong>
      эсвэл <strong>Authy</strong> аппийг нээж, <?= h(SITE_NAME) ?>-д харгалзах 6 оронтой
      кодыг оруулна уу.
    </div>

    <!-- Form -->
    <form method="POST" action="" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <div class="code-label">
        <i class="fas fa-key" style="color:#6366f1"></i> Баталгаажуулах код
      </div>

      <input
        type="text"
        id="code"
        name="code"
        class="code-input<?= $error ? ' is-error' : '' ?>"
        inputmode="numeric"
        pattern="[0-9]{6}"
        maxlength="6"
        placeholder="• • • • • •"
        autocomplete="one-time-code"
        autofocus
        required
      >

      <!-- 30-second TOTP timer -->
      <div class="timer-row">
        <div class="timer-bar"><div class="timer-fill" id="timerFill"></div></div>
        <div class="timer-text">Шинэчлэгдэх: <span id="timerSec">--</span>с</div>
      </div>

      <button type="submit" class="btn-verify">
        <i class="fas fa-check-circle"></i> Баталгаажуулах
      </button>
    </form>

    <a href="/school_system1/index.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Нэвтрэх хуудас руу буцах
    </a>
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
  fill.style.background = remaining <= 5 ? '#ef4444' : '#6366f1';
}
updateTimer();
setInterval(updateTimer, 1000);

// Зөвхөн тоо оруулах
const inp = document.getElementById('code');
inp.addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 6);
  // 6 оронтой болмогц автоматаар submit
  if (this.value.length === 6) {
    this.closest('form').submit();
  }
});

// Paste event — тоог шүүж автоматаар submit
inp.addEventListener('paste', function(e) {
  e.preventDefault();
  const pasted = (e.clipboardData || window.clipboardData).getData('text');
  const digits = pasted.replace(/\D/g, '').slice(0, 6);
  this.value = digits;
  if (digits.length === 6) {
    this.closest('form').submit();
  }
});
</script>
</body>
</html>
