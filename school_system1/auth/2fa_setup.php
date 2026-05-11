<?php
/**
 * auth/2fa_setup.php — 2FA тохируулах хуудас
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/GoogleAuthenticator.php';

requireLogin();

$uid   = $_SESSION['user_id'];
$me    = dbOne("SELECT user_id, username, email, full_name, two_factor_enabled, two_factor_secret FROM users WHERE user_id=?", [$uid]);
$error = '';

if ($me['two_factor_enabled'] == 1) {
    setFlash('info', '2-Алхамт баталгаажуулалт аль хэдийн идэвхтэй байна.');
    header('Location: /school_system1/pages/profile/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!checkRateLimit('2fa_setup_verify_' . $uid, 5, 300)) {
        $error = 'Хэт олон оролдлого. 5 минут хүлээнэ үү.';
    } else {
        $code   = trim($_POST['code']   ?? '');
        $secret = trim($_POST['secret'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Яг 6 оронтой тоо оруулна уу.';
        } elseif (strlen($secret) !== 16 || !preg_match('/^[A-Z2-7]+$/', $secret)) {
            $error = 'Буруу нууц. Хуудсыг дахин ачааллана уу.';
        } elseif (!GoogleAuthenticator::verifyCode($secret, $code, 1)) {
            $error = 'Код буруу байна эсвэл хугацаа дууссан. Дахин оролдоно уу.';
        } else {
            try {
                dbUpdate("UPDATE users SET two_factor_secret=?, two_factor_enabled=1 WHERE user_id=?", [$secret, $uid]);
                auditLog('2fa_enabled', $uid, '2FA амжилттай идэвхжлээ');
                setFlash('success', '🔐 2-Алхамт баталгаажуулалт амжилттай идэвхжлээ!');
                header('Location: /school_system1/pages/profile/index.php');
                exit;
            } catch (Exception $e) {
                $error = 'Хадгалахад алдаа гарлаа. Дахин оролдоно уу.';
            }
        }
    }
}

$secret = $_POST['secret'] ?? GoogleAuthenticator::createSecret(16);
$label      = SITE_NAME . ' (' . ($me['username'] ?? 'user') . ')';
$otpauthUrl = GoogleAuthenticator::getOtpauthUrl($label, $secret, SITE_NAME);
$qrCode     = GoogleAuthenticator::generateQRCodeSVG($otpauthUrl, 200);
$secretForDisplay = implode(' ', str_split($secret, 4));
$pageTitle = '2FA Тохиргоо';
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= pageTitle('2FA Тохиргоо') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<script>
  (function() {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches))
      document.documentElement.setAttribute('data-theme', 'dark');
  })();
</script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', sans-serif;
  min-height: 100vh;
  background: radial-gradient(ellipse at top left, #4f46e5 0%, #7c3aed 40%, #0f172a 100%);
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
  position: relative; overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0;
  background:
    radial-gradient(circle at 20% 80%, rgba(99,102,241,0.25) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(139,92,246,0.2) 0%, transparent 50%);
  pointer-events: none;
}
.blob { position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none; animation: blobFloat 8s ease-in-out infinite; }
.blob-1 { width: 420px; height: 420px; background: rgba(99,102,241,0.3); top: -120px; left: -100px; }
.blob-2 { width: 300px; height: 300px; background: rgba(139,92,246,0.25); bottom: -80px; right: -80px; animation-delay: -4s; }
@keyframes blobFloat { 0%,100%{transform:translate(0,0) scale(1);} 50%{transform:translate(30px,20px) scale(1.05);} }

.setup-card {
  width: 100%; max-width: 920px;
  display: grid; grid-template-columns: 1fr 1fr;
  border-radius: 28px; overflow: hidden;
  box-shadow: 0 40px 120px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.08);
  position: relative; z-index: 10;
  animation: cardIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes cardIn { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
@media(max-width:680px) { .setup-card{grid-template-columns:1fr;} .setup-left{display:none;} }

/* ── LEFT ── */
.setup-left {
  background: linear-gradient(155deg, #4338ca 0%, #7c3aed 55%, #312e81 100%);
  padding: 50px 40px;
  display: flex; flex-direction: column; justify-content: space-between;
  position: relative; overflow: hidden;
}
.setup-left::before {
  content:''; position:absolute; width:340px; height:340px;
  border-radius:50%; border:60px solid rgba(255,255,255,0.06); top:-80px; right:-80px;
}
.setup-left::after {
  content:''; position:absolute; width:200px; height:200px;
  border-radius:50%; border:40px solid rgba(255,255,255,0.05); bottom:40px; left:-60px;
}
.left-brand { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.9); font-weight:700; font-size:15px; position:relative; z-index:2; }
.left-brand-icon { width:36px; height:36px; background:rgba(255,255,255,0.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; }
.left-main { position:relative; z-index:2; }
.shield-ring {
  width:110px; height:110px; border-radius:50%;
  background:rgba(255,255,255,0.12); border:2px solid rgba(255,255,255,0.2);
  display:flex; align-items:center; justify-content:center;
  font-size:48px; color:#fff; margin-bottom:28px;
  animation: shieldPulse 3s ease-in-out infinite;
}
@keyframes shieldPulse {
  0%,100% { box-shadow: 0 0 30px rgba(99,102,241,0.3); }
  50% { box-shadow: 0 0 60px rgba(139,92,246,0.6); transform:scale(1.05); }
}
.left-title { font-family:'Poppins',sans-serif; font-size:28px; font-weight:800; color:#fff; line-height:1.2; margin-bottom:14px; }
.left-desc { font-size:14px; color:rgba(255,255,255,0.75); line-height:1.7; }
.left-steps { position:relative; z-index:2; }
.left-step { display:flex; align-items:center; gap:12px; margin-bottom:14px; color:rgba(255,255,255,0.85); font-size:13px; }
.left-step-num { width:26px; height:26px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; }

/* ── RIGHT ── */
.setup-right {
  background: #fff;
  padding: 48px 44px;
  display: flex; flex-direction: column;
}
[data-theme="dark"] .setup-right { background: #0f172a; }

.right-title { font-family:'Poppins',sans-serif; font-size:22px; font-weight:800; color:#0f172a; margin-bottom:4px; }
[data-theme="dark"] .right-title { color:#f1f5f9; }
.right-sub { font-size:13px; color:#64748b; margin-bottom:28px; line-height:1.5; }
[data-theme="dark"] .right-sub { color:#94a3b8; }

/* QR + Secret */
.qr-secret-row { display:flex; gap:18px; align-items:flex-start; margin-bottom:22px; flex-wrap:wrap; }
.qr-wrap {
  background:#fff; border-radius:14px; padding:10px;
  box-shadow: 0 8px 32px rgba(79,70,229,0.18);
  border:2px solid #e0e7ff; flex-shrink:0;
}
.qr-wrap svg, .qr-wrap img { display:block; border-radius:6px; }
.secret-col { flex:1; min-width:160px; display:flex; flex-direction:column; gap:8px; }
.secret-lbl { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px; }
.secret-badge {
  font-family:'Courier New',monospace; font-size:13px; font-weight:700;
  letter-spacing:0.12em; color:#4f46e5;
  background:linear-gradient(135deg,#eef2ff,#e0e7ff);
  border:1.5px solid #c7d2fe; border-radius:10px;
  padding:12px 14px; word-break:break-all;
  cursor:pointer; transition:all 0.25s ease; user-select:all;
}
.secret-badge:hover { background:linear-gradient(135deg,#e0e7ff,#c7d2fe); transform:scale(1.01); }
.secret-badge.copied { background:linear-gradient(135deg,#d1fae5,#a7f3d0)!important; border-color:#34d399!important; color:#065f46!important; }
[data-theme="dark"] .secret-badge { background:rgba(99,102,241,0.15); border-color:rgba(99,102,241,0.3); color:#a5b4fc; }
.copy-hint { font-size:11px; color:#94a3b8; display:flex; align-items:center; gap:4px; }

/* Timer */
.timer-section { display:flex; align-items:center; gap:14px; margin-bottom:20px; padding:13px 16px; background:rgba(79,70,229,0.06); border:1px solid rgba(79,70,229,0.14); border-radius:12px; }
[data-theme="dark"] .timer-section { background:rgba(99,102,241,0.1); border-color:rgba(99,102,241,0.2); }
.timer-ring-wrap { position:relative; flex-shrink:0; }
.timer-svg { transform:rotate(-90deg); }
.timer-track { fill:none; stroke:#e2e8f0; stroke-width:4; }
[data-theme="dark"] .timer-track { stroke:#334155; }
.timer-prog { fill:none; stroke:#6366f1; stroke-width:4; stroke-linecap:round; transition:stroke-dashoffset 1s linear, stroke 0.3s; }
.timer-num { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; color:#4f46e5; }
.timer-info-title { font-size:13px; font-weight:700; color:#334155; margin-bottom:2px; }
[data-theme="dark"] .timer-info-title { color:#e2e8f0; }
.timer-info-sub { font-size:11px; color:#94a3b8; }

/* Error */
.error-banner { display:flex; align-items:center; gap:10px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:12px; padding:12px 16px; color:#dc2626; font-size:13px; font-weight:500; margin-bottom:18px; animation:shakeX 0.4s ease; }
@keyframes shakeX { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }

/* Input */
.input-lbl { font-size:13px; font-weight:700; color:#334155; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
[data-theme="dark"] .input-lbl { color:#cbd5e1; }
.code-input {
  width:100%; padding:16px;
  font-size:28px; font-weight:800; letter-spacing:0.6em; text-align:center;
  border:2px solid #e2e8f0; border-radius:14px;
  background:#f8fafc; color:#0f172a;
  transition:all 0.25s; font-family:'Poppins',monospace;
  margin-bottom:18px;
}
[data-theme="dark"] .code-input { background:#1e293b; border-color:#334155; color:#f1f5f9; }
.code-input:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 4px rgba(99,102,241,0.12); background:#fff; }
[data-theme="dark"] .code-input:focus { background:#0f172a; }
.code-input.has-error { border-color:#ef4444; box-shadow:0 0 0 4px rgba(239,68,68,0.1); }

.btn-verify {
  width:100%; padding:15px; border:none; border-radius:14px;
  background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff;
  font-size:15px; font-weight:700; cursor:pointer; font-family:inherit;
  display:flex; align-items:center; justify-content:center; gap:8px;
  transition:all 0.25s; box-shadow:0 6px 20px rgba(99,102,241,0.35);
}
.btn-verify:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(99,102,241,0.45); }
.btn-verify:active { transform:translateY(0); }

.back-link { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:18px; font-size:13px; color:#94a3b8; text-decoration:none; transition:color 0.2s; }
.back-link:hover { color:#6366f1; }
</style>
</head>
<body>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<div class="setup-card">
  <!-- LEFT -->
  <div class="setup-left">
    <div class="left-brand">
      <div class="left-brand-icon"><i class="fas fa-graduation-cap"></i></div>
      Цахим Сургууль
    </div>
    <div class="left-main">
      <div class="shield-ring"><i class="fas fa-shield-alt"></i></div>
      <div class="left-title">2-Алхамт<br>Баталгаажуулалт</div>
      <div class="left-desc">Таны бүртгэлийг нэмэлт хамгаалалтаар хамгаалж, зөвшөөрөлгүй нэвтрэлтийг бүрмөсөн хаана.</div>
    </div>
    <div class="left-steps">
      <div class="left-step"><div class="left-step-num">1</div><span>Google Authenticator татаж суулгана</span></div>
      <div class="left-step"><div class="left-step-num">2</div><span>«+» → QR код уншуулах дарна</span></div>
      <div class="left-step"><div class="left-step-num">3</div><span>Доорх QR кодыг уншуулна</span></div>
      <div class="left-step"><div class="left-step-num">4</div><span>Гарч ирсэн 6 оронтой код оруулна</span></div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="setup-right">
    <div class="right-title">🔐 2FA Тохируулах</div>
    <div class="right-sub">QR кодыг уншуулаад апп дээр гарсан 6 оронтой кодыг оруулж идэвхжүүлнэ үү</div>

    <?php if ($error): ?>
    <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <!-- QR + Secret -->
    <div class="qr-secret-row">
      <div class="qr-wrap"><?= $qrCode ?></div>
      <div class="secret-col">
        <div class="secret-lbl"><i class="fas fa-key"></i> Гараар оруулах нууц</div>
        <div class="secret-badge" id="secretBadge"
             onclick="copySecret('<?= h($secret) ?>')"
             title="Дарж хуулах"><?= h($secretForDisplay) ?></div>
        <div class="copy-hint" id="copyHint"><i class="fas fa-copy"></i> Дарж хуулах</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Аппликейшн нэмэхэд ашиглана</div>
      </div>
    </div>

    <!-- TOTP Timer ring -->
    <div class="timer-section">
      <div class="timer-ring-wrap">
        <svg class="timer-svg" width="52" height="52" viewBox="0 0 52 52">
          <circle class="timer-track" cx="26" cy="26" r="21"/>
          <circle class="timer-prog" id="timerCircle" cx="26" cy="26" r="21"
                  stroke-dasharray="132" stroke-dashoffset="0"/>
        </svg>
        <div class="timer-num" id="timerNum">30</div>
      </div>
      <div class="timer-info">
        <div class="timer-info-title">Код 30 секунд бүр шинэчлэгдэнэ</div>
        <div class="timer-info-sub">Хугацаа дуусахаас өмнө кодоо оруулна уу</div>
      </div>
    </div>

    <!-- Form -->
    <form method="POST" action="">
      <input type="hidden" name="csrf"   value="<?= csrfToken() ?>">
      <input type="hidden" name="secret" value="<?= h($secret) ?>">
      <div class="input-lbl"><i class="fas fa-mobile-alt"></i> Authenticator аппийн 6 оронтой код</div>
      <input type="text" id="code" name="code"
             class="code-input <?= $error ? 'has-error' : '' ?>"
             inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
             autocomplete="one-time-code" placeholder="••••••"
             autofocus required>
      <button type="submit" class="btn-verify">
        <i class="fas fa-shield-alt"></i> Баталгаажуулах &amp; Идэвхжүүлэх
      </button>
    </form>

    <a href="/school_system1/pages/profile/index.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Профайл руу буцах
    </a>
  </div>
</div>

<script>
// TOTP ring timer
const CIRC = 2 * Math.PI * 21;
function updateTimer() {
  const rem = 30 - (Math.floor(Date.now()/1000) % 30);
  const circle = document.getElementById('timerCircle');
  const numEl  = document.getElementById('timerNum');
  circle.style.strokeDashoffset = CIRC * (1 - rem/30);
  circle.style.stroke = rem <= 7 ? '#ef4444' : '#6366f1';
  numEl.textContent = rem;
  numEl.style.color = rem <= 7 ? '#ef4444' : '#4f46e5';
}
updateTimer();
setInterval(updateTimer, 1000);

// Digits only
document.getElementById('code').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g,'').slice(0,6);
});

// Copy secret
function copySecret(raw) {
  navigator.clipboard.writeText(raw).then(() => {
    const b = document.getElementById('secretBadge');
    const h = document.getElementById('copyHint');
    b.classList.add('copied');
    h.innerHTML = '<i class="fas fa-check"></i> Хуулагдлаа!';
    h.style.color = '#10b981';
    setTimeout(() => {
      b.classList.remove('copied');
      h.innerHTML = '<i class="fas fa-copy"></i> Дарж хуулах';
      h.style.color = '';
    }, 2000);
  });
}
</script>
</body>
</html>
