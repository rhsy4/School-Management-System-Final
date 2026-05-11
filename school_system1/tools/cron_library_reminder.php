<?php
/**
 * Cron Job: Overdue Library Loan Reminder
 * 
 * Номын зээлийн хугацаа хэтэрсэн хэрэглэгчдэд email сануулга илгээх.
 * 
 * Windows Task Scheduler:
 *   powershell -Command "& {php C:\xampp\htdocs\school_system1\tools\cron_library_reminder.php}"
 * 
 * Linux Cron (өдөр бүр 09:00):
 *   0 9 * * * /usr/bin/php /path/to/school_system1/tools/cron_library_reminder.php
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';

echo "[" . date('Y-m-d H:i:s') . "] Library reminder cron эхэллээ...\n";

try {
    $notifier = new NotificationService();
    $result = $notifier->sendOverdueLibraryReminders();

    if ($result['success']) {
        echo "✅ Амжилттай! {$result['sent']}/{$result['total']} email илгээгдлээ.\n";
        
        if (!empty($result['details'])) {
            echo "\nДэлгэрэнгүй:\n";
            echo str_repeat('-', 70) . "\n";
            foreach ($result['details'] as $d) {
                $status = $d['sent'] ? '✅' : '❌';
                echo "{$status} {$d['book']} → {$d['name']} ({$d['email']}, {$d['overdue']} хоног, ₮" . number_format($d['fine']) . ")\n";
            }
        }
    } else {
        echo "❌ Алдаа: " . ($result['error'] ?? 'Тодорхойгүй') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Дууслаа.\n";
