<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo '<h1>Өгөгдөл засах эрхгүй</h1>';
    echo '<p>Админ удирдлага шаардлагатай. <a href="/school_system1/index.php">Нэвтэрэх</a></p>';
    exit;
}

$db = getDB();
$migrations = [
    'add_remarks_table.sql' => 'Сурагчийн тэмдэглэл хүснэгт',
    'migrate_announcements.sql' => 'Зарлалуудын өөрчлөлт',
    'add_modules.sql' => 'Нэмэлт модулүүд',
];

$page_title = 'Өгөгдлийн сан миграци';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-database"></i> Өгөгдлийн сан миграци</h2>
    <p style="font-size: 13px; color: var(--muted)">SQL файлуудыг сервер дээр ажиллуулна</p>
  </div>
  <div class="card-body">
    <div style="background: #f9fafb; border: 1px solid var(--border); border-radius: 8px; padding: 20px; font-family: monospace; font-size: 13px; line-height: 1.6; color: #1f2937;">

<?php
foreach ($migrations as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "<div style=\"color: #9ca3af\"><i class=\"fas fa-forward\"></i> SKIP - $desc ($file) - файл олдсонгүй</div>\n";
        continue;
    }
    
    try {
        $sql = file_get_contents($path);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $count = 0;
        $errors = [];
        
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $db->exec($stmt);
                    $count++;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        if ($count > 0) {
            echo "<div style=\"color: #10b981\"><i class=\"fas fa-check-circle\"></i> OK - $desc ($count үйлдэл)</div>\n";
        }
        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo "<div style=\"color: #f97316\"><i class=\"fas fa-exclamation-circle\"></i> ЗАМБАРАА - $err</div>\n";
            }
        }
    } catch (Exception $e) {
        echo "<div style=\"color: #ef4444\"><i class=\"fas fa-times-circle\"></i> АЛДАА - $desc - " . h($e->getMessage()) . "</div>\n";
    }
}
?>

    </div>

    <div style="margin-top: 20px; padding: 15px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; color: #166534;">
      <i class="fas fa-check-circle"></i> <strong>Миграци дууссан!</strong> <a href="/school_system1/dashboard.php" style="color: #16a34a; text-decoration: underline;">Хяналтын самбар руу буцах →</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

