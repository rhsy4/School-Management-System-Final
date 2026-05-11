<?php
/**
 * Cron Job: Overdue Tuition Reminder
 * 
 * Хугацаа хэтэрсэн төлбөрийн сануулга эцэг эхэд email-ээр илгээх.
 * 
 * Windows Task Scheduler:
 *   powershell -Command "& {php C:\xampp\htdocs\school_system1\tools\cron_tuition_reminder.php}"
 * 
 * Linux Cron (өдөр бүр 08:00):
 *   0 8 * * * /usr/bin/php /path/to/school_system1/tools/cron_tuition_reminder.php
 */

// Session/header шаардлагагүй
define('CRON_MODE', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';

echo "[" . date('Y-m-d H:i:s') . "] Tuition reminder cron эхэллээ...\n";

try {
    $notifier = new NotificationService();
    $result = $notifier->sendOverdueTuitionReminders();

    if ($result['success']) {
        echo "✅ Амжилттай! {$result['sent']}/{$result['total']} email илгээгдлээ.\n";
        
        if (!empty($result['details'])) {
            echo "\nДэлгэрэнгүй:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($result['details'] as $d) {
                $status = $d['sent'] ? '✅' : '❌';
                echo "{$status} {$d['student']} → {$d['email']} (₮" . number_format($d['amount']) . ", {$d['overdue']} хоног хоцорсон)\n";
            }
        }
    } else {
        echo "❌ Алдаа: " . ($result['error'] ?? 'Тодорхойгүй') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Дууслаа.\n";
