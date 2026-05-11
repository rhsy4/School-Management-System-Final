<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= pageTitle($pageTitle ?? 'Нүүр хуудас') ?></title>
<link rel="manifest" href="/school_system1/manifest.json">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/school_system1/assets/css/style.css">
<script>
  // Instant theme apply to prevent flash
  (function() {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  })();
</script>
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/school_system1/sw.js').catch(err => console.log('SW бүртгэл бүтэлгүйтлээ:', err));
  }
</script>
</head>
<body>
<?php
// Сэшн дээрх нэр эвдэрсэн эсвэл хоцрогдсон байвал автоматаар шинэчлэх хамгаалалт (Mojibake Fix)
// 5 минутын TTL-тэй — хуудас бүр ачаалахад DB query хийхгүй
if (isset($_SESSION['user_id']) && function_exists('dbOne')) {
    if (!isset($_SESSION['_name_refreshed_at']) || (time() - $_SESSION['_name_refreshed_at'] > 300)) {
        $dbUsr = dbOne("SELECT full_name, profile_image FROM users WHERE user_id=?", [$_SESSION['user_id']]);
        if ($dbUsr && isset($dbUsr['full_name'])) {
            $_SESSION['full_name'] = $dbUsr['full_name'];
            $_SESSION['_profile_image'] = $dbUsr['profile_image'] ?? null;
            $_SESSION['_name_refreshed_at'] = time();
        }
    }
}

// Уншаагүй мессежний тоо (Message Count) - sidebar badge-д ашиглана
$unreadMsgs = 0;
if (isset($_SESSION['user_id'])) {
    $um = dbOne("SELECT COUNT(*) as cnt FROM messages WHERE receiver_id=? AND is_read=0", [$_SESSION['user_id']]);
    $unreadMsgs = $um['cnt'] ?? 0;
    
    // Хүлээгдэж буй бүртгэлийн тоо
    if (isAdmin() || isManager() || isDirector()) {
        $pnd = dbOne("SELECT COUNT(*) as cnt FROM pending_registrations WHERE status='pending'");
        $pendingRegsCount = $pnd['cnt'] ?? 0;
    }
}

// Системийн тохиргоо — session-д кэшлэх (5 минутын TTL)
$sysSchoolName = 'Цахим Сургууль';
if (isset($_SESSION['_school_name']) && isset($_SESSION['_school_name_at']) && (time() - $_SESSION['_school_name_at'] < 300)) {
    $sysSchoolName = $_SESSION['_school_name'];
} else {
    try {
        $set = dbOne("SELECT setting_value FROM settings WHERE setting_key='school_name'");
        if ($set && !empty($set['setting_value'])) {
            $sysSchoolName = $set['setting_value'];
        }
        $_SESSION['_school_name'] = $sysSchoolName;
        $_SESSION['_school_name_at'] = time();
    } catch (Exception $e) {}
}
?>
<div class="wrapper">
<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <img src="/school_system1/assets/img/logo.png" alt="Logo" style="width:35px; height:35px; object-fit:contain; border-radius:6px;">
    <span><?= h($sysSchoolName) ?></span>
  </div>
  <ul class="sidebar-nav">
    <!-- Бүгд харна -->
    <li><a href="/school_system1/dashboard.php" <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'class="active"':'' ?>><i class="fas fa-tachometer-alt"></i> <span>Хяналтын самбар</span></a></li>

    <?php if(isParent()): ?>
    <li class="nav-group"><span>ЭЦЭГ ЭХИЙН БУЛАН</span></li>
    <li><a href="/school_system1/pages/parent-portal.php" <?= strpos($_SERVER['PHP_SELF'],'parent-portal')!==false?'class="active"':'' ?>><i class="fas fa-home"></i> <span>Миний хүүхэд</span></a></li>
    <li><a href="/school_system1/pages/events/index.php" <?= strpos($_SERVER['PHP_SELF'],'events')!==false?'class="active"':'' ?>><i class="fas fa-calendar-check"></i> <span>Арга хэмжээ</span></a></li>
    <li><a href="/school_system1/pages/library/index.php" <?= strpos($_SERVER['PHP_SELF'],'library')!==false?'class="active"':'' ?>><i class="fas fa-book-reader"></i> <span>Номын сан</span></a></li>
    <li><a href="/school_system1/pages/health/index.php" <?= strpos($_SERVER['PHP_SELF'],'health')!==false?'class="active"':'' ?>><i class="fas fa-notes-medical"></i> <span>Эрүүл мэнд</span></a></li>
    <li><a href="/school_system1/pages/schedule/index.php" <?= strpos($_SERVER['PHP_SELF'],'schedule')!==false?'class="active"':'' ?>><i class="fas fa-calendar-alt"></i> <span>Хуваарь</span></a></li>
    <?php endif; ?>
    <!-- СИСТЕМ: зөвхөн admin -->
    <?php if(isAdmin()): ?>
    <li class="nav-group"><span>СИСТЕМ</span></li>
    <li><a href="/school_system1/admin/index.php" <?= strpos($_SERVER['PHP_SELF'],'admin/index.php')!==false?'class="active"':'' ?>><i class="fas fa-shield-alt"></i> <span>Админ самбар</span></a></li>
    <li><a href="/school_system1/admin/logs.php"><i class="fas fa-history"></i> <span>Audit лог</span></a></li>
    <li><a href="/school_system1/admin/import.php" <?= strpos($_SERVER['PHP_SELF'],'import')!==false?'class="active"':'' ?>><i class="fas fa-file-import"></i> <span>Excel оруулах</span></a></li>
    <li><a href="/school_system1/admin/backup.php" <?= strpos($_SERVER['PHP_SELF'],'backup')!==false?'class="active"':'' ?>><i class="fas fa-database"></i> <span>Нөөцлөлт</span></a></li>
    <li><a href="/school_system1/admin/settings.php" <?= strpos($_SERVER['PHP_SELF'],'settings')!==false?'class="active"':'' ?>><i class="fas fa-cogs"></i> <span>Тохиргоо</span></a></li>
    <?php endif; ?>

    <!-- УДИРДЛАГА: manager, director болон admin -->
    <?php if(isAdmin() || isManager() || isDirector()): ?>
    <li class="nav-group"><span>УДИРДЛАГА</span></li>
    <li>
        <a href="/school_system1/pages/approvals/index.php" <?= strpos($_SERVER['PHP_SELF'],'approvals')!==false?'class="active"':'' ?>>
            <i class="fas fa-user-check"></i> <span>Бүртгэл батлах</span>
            <?php if(!empty($pendingRegsCount)): ?><span class="badge" style="background:var(--danger);color:#fff;margin-left:auto;"><?= $pendingRegsCount ?></span><?php endif; ?>
        </a>
    </li>
    <li><a href="/school_system1/pages/users/index.php" <?= strpos($_SERVER['PHP_SELF'],'users')!==false?'class="active"':'' ?>><i class="fas fa-users-cog"></i> <span>Хэрэглэгчид</span></a></li>
    <li><a href="/school_system1/pages/classes/index.php" <?= strpos($_SERVER['PHP_SELF'],'classes')!==false?'class="active"':'' ?>><i class="fas fa-chalkboard"></i> <span>Ангиуд</span></a></li>
    <li><a href="/school_system1/pages/payments/index.php" <?= strpos($_SERVER['PHP_SELF'],'payments')!==false?'class="active"':'' ?>><i class="fas fa-money-bill-wave"></i> <span>Төлбөр</span></a></li>
    <li><a href="/school_system1/pages/reports/index.php" <?= strpos($_SERVER['PHP_SELF'],'reports')!==false?'class="active"':'' ?>><i class="fas fa-chart-bar"></i> <span>Тайлан</span></a></li>
    <?php endif; ?>

    <?php if(!isParent()): ?>
    <!-- СУРГАЛТ -->
    <li class="nav-group"><span>СУРГАЛТ</span></li>
    <?php endif; ?>



    <?php if(isAdmin() || isManager() || isDirector() || isTeacher()): ?>
    <li><a href="/school_system1/pages/students/index.php" <?= strpos($_SERVER['PHP_SELF'],'students')!==false?'class="active"':'' ?>><i class="fas fa-user-graduate"></i> <span>Сурагчид</span></a></li>
    <li><a href="/school_system1/pages/teachers/index.php" <?= strpos($_SERVER['PHP_SELF'],'teachers')!==false?'class="active"':'' ?>><i class="fas fa-chalkboard-teacher"></i> <span>Багшид</span></a></li>
    <?php endif; ?>

    <?php if(!isParent()): ?>
    <li><a href="/school_system1/pages/schedule/index.php" <?= strpos($_SERVER['PHP_SELF'],'schedule')!==false?'class="active"':'' ?>><i class="fas fa-calendar-alt"></i> <span>Хуваарь</span></a></li>
    <?php endif; ?>

    <?php if(isAdmin() || isManager() || isDirector() || isTeacher()): ?>
    <li><a href="/school_system1/pages/attendance/bulk.php" <?= strpos($_SERVER['PHP_SELF'],'bulk')!==false?'class="active"':'' ?>><i class="fas fa-clipboard-check"></i> <span>Ирц</span></a></li>
    <?php endif; ?>

    <?php if(!isParent()): ?>
    <li><a href="/school_system1/pages/grades/index.php" <?= strpos($_SERVER['PHP_SELF'],'grades')!==false?'class="active"':'' ?>><i class="fas fa-star"></i> <span>Дүн</span></a></li>
    <?php endif; ?>

    <?php if(!isParent()): ?>
    <li><a href="/school_system1/pages/assignments/index.php" <?= strpos($_SERVER['PHP_SELF'],'assignments')!==false?'class="active"':'' ?>><i class="fas fa-tasks"></i> <span>Даалгавар</span></a></li>
    <li><a href="/school_system1/pages/exams/index.php" <?= strpos($_SERVER['PHP_SELF'],'exams')!==false?'class="active"':'' ?>><i class="fas fa-laptop-code"></i> <span>Шалгалт</span></a></li>
    <?php endif; ?>

    <?php if(!isParent()): ?>
    <li><a href="/school_system1/pages/events/index.php" <?= strpos($_SERVER['PHP_SELF'],'events')!==false?'class="active"':'' ?>><i class="fas fa-calendar-check"></i> <span>Арга хэмжээ</span></a></li>
    <li><a href="/school_system1/pages/library/index.php" <?= strpos($_SERVER['PHP_SELF'],'library')!==false?'class="active"':'' ?>><i class="fas fa-book-reader"></i> <span>Номын сан</span></a></li>
    <?php endif; ?>

    <?php if(isAdmin() || isManager() || isDirector() || isTeacher()): ?>
    <li><a href="/school_system1/pages/health/index.php" <?= strpos($_SERVER['PHP_SELF'],'health')!==false?'class="active"':'' ?>><i class="fas fa-notes-medical"></i> <span>Эрүүл мэнд</span></a></li>
    <?php endif; ?>

    <?php if(isManager() || isDirector() || isTeacher() || isStudent()): ?>
    <li><a href="/school_system1/pages/leaderboard/index.php" <?= strpos($_SERVER['PHP_SELF'],'leaderboard/index')!==false?'class="active"':'' ?>><i class="fas fa-crown" style="color:#fbbf24;"></i> <span>Алдрын Танхим</span></a></li>
    <?php endif; ?>

    <?php if(isAdmin() || isTeacher() || isManager() || isDirector()): ?>
    <li><a href="/school_system1/pages/leaderboard/manage.php" <?= strpos($_SERVER['PHP_SELF'],'leaderboard/manage')!==false?'class="active"':'' ?>><i class="fas fa-plus-circle"></i> <span>Оноо олгох</span></a></li>
    <?php endif; ?>

    <?php if(isParent()): ?>
    <li><a href="/school_system1/pages/pickup/index.php" <?= strpos($_SERVER['PHP_SELF'],'pickup')!==false?'class="active"':'' ?>><i class="fas fa-car"></i> <span>Хүүхэд авах</span></a></li>
    <?php endif; ?>

    <!-- ХАРИЛЦАА -->
    <li class="nav-group"><span>ХАРИЛЦАА</span></li>
    <li><a href="/school_system1/pages/announcements/index.php" <?= strpos($_SERVER['PHP_SELF'],'announcements')!==false?'class="active"':'' ?>><i class="fas fa-bullhorn"></i> <span>Зарлалууд</span></a></li>
    <li>
        <a href="/school_system1/pages/messages/index.php" <?= strpos($_SERVER['PHP_SELF'],'messages')!==false?'class="active"':'' ?>>
            <i class="fas fa-envelope"></i> <span>Мессеж</span>
            <?php if ($unreadMsgs > 0): ?>
                <span class="badge badge-danger" style="padding:2px 6px; font-size:10px; border-radius:10px; margin-left:5px; animation: pulse 2s infinite;">
                    <?= $unreadMsgs ?>
                </span>
            <?php endif; ?>
        </a>
    </li>
    <?php if(!isStudent() && !isParent()): ?>
    <li><a href="/school_system1/pages/remarks/index.php" <?= strpos($_SERVER['PHP_SELF'],'remarks')!==false?'class="active"':'' ?>><i class="fas fa-comment-dots"></i> <span>Санал хүсэлт</span></a></li>
    <?php endif; ?>
    <li><a href="/school_system1/pages/leaves/index.php" <?= strpos($_SERVER['PHP_SELF'],'leaves')!==false?'class="active"':'' ?>><i class="fas fa-calendar-minus"></i> <span>Чөлөөний хүсэлт</span></a></li>

    <!-- ХУВИЙН -->
    <li class="nav-group"><span>ХУВИЙН</span></li>
    <li><a href="/school_system1/pages/profile/index.php" <?= strpos($_SERVER['PHP_SELF'],'profile')!==false?'class="active"':'' ?>><i class="fas fa-user-cog"></i> <span>Профайл</span></a></li>
  </ul>
</nav>
<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOP NAV / TOPBAR -->
  <header class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" title="Цэс нээх/хаах">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title"><?= h($pageTitle ?? '') ?></div>
    <div class="topbar-right">
      <?php
        $parentChildrenInfo = [];
        if(isParent()) {
            $parentChildrenInfo = dbQuery("SELECT student_id, CONCAT(last_name,' ',first_name) AS full_name FROM students WHERE parent_id=? AND is_active=1", [$_SESSION['user_id']]);
        }
      ?>
      <?php if(isParent() && !empty($parentChildrenInfo)): ?>
      <form method="GET" style="display:inline-block; margin-right: 15px;">
        <select name="switch_child_id" onchange="this.form.submit()" class="form-control" style="height: 30px; padding: 2px 10px; font-size: 13px; border-radius: 4px;">
          <?php foreach($parentChildrenInfo as $child): ?>
            <option value="<?= $child['student_id'] ?>" <?= (isset($_SESSION['active_child_id']) && $_SESSION['active_child_id'] == $child['student_id']) ? 'selected' : '' ?>>
              👧👦 <?= h($child['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>

      <a href="/school_system1/pages/messages/index.php" class="user-badge" style="position:relative; text-decoration:none; color:inherit; font-size:18px;">
        <i class="fas fa-bell"></i>
        <?php if($unreadMsgs > 0): ?>
          <span style="position:absolute; top:-5px; right:-8px; background:var(--danger); color:#fff; border-radius:50%; padding:2px 5px; font-size:10px; font-weight:bold; box-shadow:0 2px 4px rgba(0,0,0,0.2); animation: pulse 2s infinite;"><?= $unreadMsgs ?></span>
          <style>@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }</style>
        <?php endif; ?>
      </a>

      <?php
        // Session-д кэшлэгдсэн мэдээллийг ашиглах — нэмэлт DB query хийхгүй
        $currProfileImage = $_SESSION['_profile_image'] ?? null;
        $currFullName = $_SESSION['full_name'] ?? '';
      ?>
      <a href="/school_system1/pages/profile/index.php" class="user-badge" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.05);padding:4px 12px;border-radius:24px;border:1px solid rgba(255,255,255,0.1);transition:all 0.3s ease;">
        <img src="<?= getUserAvatar($currProfileImage, $currFullName ?: $_SESSION['full_name']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;box-shadow:0 2px 5px rgba(0,0,0,0.2);">
        <span style="font-weight:500;"><?= h($_SESSION['full_name'] ?? '') ?></span>
      </a>
      <?php
        $roleLabels = [
            'admin'    => 'Систем Админ',
            'manager'  => 'Менежер',
            'director' => 'Захирал',
            'teacher'  => 'Багш',
            'student'  => 'Сурагч',
            'parent'   => 'Эцэг эх'
        ];
        $roleDisplay = $roleLabels[$_SESSION['role'] ?? ''] ?? ($_SESSION['role'] ?? '');
      ?>
      <span class="role-badge"><?= h($roleDisplay) ?></span>
      <div class="theme-switch-wrapper" style="margin: 0 15px;">
        <label class="theme-switch" for="themeCheckbox" title="Горим солих" style="display:inline-block; height:28px; position:relative; width:50px; cursor:pointer;">
          <input type="checkbox" id="themeCheckbox" onchange="toggleDarkMode()" style="display:none;" />
          <div class="slider round" style="background-color:#cbd5e1; bottom:0; left:0; position:absolute; right:0; top:0; transition:.4s; border-radius:28px; display:flex; align-items:center; justify-content:space-between; padding:0 6px;">
            <i class="fas fa-sun" style="font-size:12px; color:#f59e0b; z-index:1;"></i>
            <i class="fas fa-moon" style="font-size:12px; color:#f1f5f9; z-index:1;"></i>
            <div class="knob" style="background-color:#fff; bottom:3px; content:''; height:22px; left:3px; position:absolute; transition:.4s; width:22px; border-radius:50%; z-index:2; box-shadow:0 1px 4px rgba(0,0,0,0.2);"></div>
          </div>
        </label>
      </div>
      <style>
        #themeCheckbox:checked + .slider { background-color: #6366f1 !important; }
        #themeCheckbox:checked + .slider .knob { transform: translateX(22px); }
        [data-theme="dark"] .slider { background-color: #334155 !important; }
        [data-theme="dark"] .slider .knob { background-color: #1e293b !important; }
        [data-theme="dark"] .slider .fa-sun { color: #475569 !important; }
        [data-theme="dark"] .slider .fa-moon { color: #f1c40f !important; }
      </style>
      <a href="/school_system1/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Гарах</span></a>
    </div>
  </header>
  <!-- FLASH MESSAGE -->
  <?php $flash = getFlash(); if($flash): ?>
  <div class="flash flash-<?= h($flash['type']) ?>" style="display:flex;align-items:center;justify-content:space-between;">
    <span><i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= h($flash['msg']) ?></span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;opacity:0.7;color:inherit;padding:0 4px;" title="Хаах">&times;</button>
  </div>
  <?php endif; ?>
  <div class="page-body">
