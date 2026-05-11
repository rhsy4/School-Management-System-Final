<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$tid = (int)($_GET['id'] ?? 0);
if (!$tid) die('Invalid ID');

$p = dbOne("SELECT t.*, s.first_name, s.last_name, c.class_name, 
                   u.full_name AS recorder_name
            FROM tuition t
            JOIN students s ON t.student_id = s.student_id
            JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN users u ON t.recorded_by = u.user_id
            WHERE t.tuition_id = ?", [$tid]);

if (!$p) die('Payment not found');

// Security check
if (!isAdmin() && !isManager()) {
    if (isStudent()) {
        $me = dbOne("SELECT student_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
        if ($p['student_id'] != ($me['student_id'] ?? 0)) die('Access denied');
    } elseif (isParent()) {
        $rows = dbQuery("SELECT student_id FROM students WHERE parent_id=(SELECT parent_id FROM parents WHERE user_id=?)", [$_SESSION['user_id']]);
        $childIds = array_column($rows, 'student_id');
        if (!in_array($p['student_id'], $childIds)) die('Access denied');
    }
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <title>Төлбөрийн баримт - <?= h($p['receipt_no']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #333; }
        .receipt-box { max-width: 600px; margin: auto; border: 2px solid #eee; padding: 30px; border-radius: 10px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #3498db; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #3498db; }
        .title { text-align: right; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .info-label { font-size: 12px; color: #777; text-transform: uppercase; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: 600; }
        .amount-box { background: #f8fbff; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #d1e3f8; margin-bottom: 30px; }
        .amount-value { font-size: 32px; font-weight: bold; color: #2c3e50; }
        .footer { font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px; margin-top: 40px; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
            .receipt-box { border: none; }
        }
        .btn-print { 
            display: block; width: 200px; padding: 10px; background: #3498db; color: #fff; 
            text-align: center; text-decoration: none; border-radius: 5px; margin: 20px auto; 
            font-weight: bold;
        }
    </style>
</head>
<body>

    <a href="javascript:window.print()" class="btn-print">ХЭВЛЭХ</a>

    <div class="receipt-box">
        <div class="header">
            <div class="logo">ЦАХИМ СУРГУУЛЬ</div>
            <div class="title">
                <div style="font-weight: 700; font-size: 18px;">ТӨЛБӨРИЙН БАРИМТ</div>
                <div style="color: #ed2121; font-weight: bold;"><?= h($p['receipt_no']) ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div>
                <div class="info-label">Сурагч</div>
                <div class="info-value"><?= h($p['last_name'] . ' ' . $p['first_name']) ?></div>
            </div>
            <div>
                <div class="info-label">Анги</div>
                <div class="info-value"><?= h($p['class_name']) ?></div>
            </div>
            <div>
                <div class="info-label">Төлбөрийн төрөл</div>
                <div class="info-value">Сургалтын төлбөр</div>
            </div>
            <div>
                <div class="info-label">Огноо</div>
                <div class="info-value"><?= mnDate($p['paid_date']) ?></div>
            </div>
        </div>

        <div class="amount-box">
            <div class="info-label">Төлөгдсөн дүн</div>
            <div class="amount-value"><?= mnMoney($p['amount']) ?></div>
            <div style="font-size: 13px; color: #27ae60; font-weight: 600; margin-top: 5px;">✅ АМЖИЛТТАЙ ТӨЛӨГДӨВ</div>
        </div>

        <div style="font-size: 13px; line-height: 1.6;">
            <div><strong>Төлбөрийн хэлбэр:</strong> <?= h($p['payment_method'] ?? 'Шилжүүлэг') ?></div>
            <div><strong>Бүртгэсэн:</strong> <?= h($p['recorder_name'] ?? 'Систем') ?></div>
        </div>

        <div class="footer">
            Утас: +976 7011XXXX | И-мэйл: info@school.edu.mn<br>
            Энэхүү баримт нь цахим хэлбэрээр үүсгэгдсэн бөгөөд тамгагүй хүчинтэй.
        </div>
    </div>

    <script>
        // Auto print after a short delay
        window.onload = function() {
            setTimeout(function() {
                // window.print();
            }, 500);
        };
    </script>
</body>
</html>
