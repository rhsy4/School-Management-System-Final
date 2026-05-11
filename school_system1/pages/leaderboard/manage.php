<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['teacher', 'manager', 'director', 'admin']);

$pageTitle = 'Оноо олгох (Алдрын Танхим)';

// Оноо олгох үйлдэл
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $student_id = (int)$_POST['student_id'];
    $points = (int)$_POST['points'];
    $reason = trim($_POST['reason']);

    if ($student_id && $points && $reason) {
        try {
            getDB()->beginTransaction();
            
            // Log нэмэх
            dbExec("INSERT INTO merit_logs (student_id, teacher_id, points, reason) VALUES (?, ?, ?, ?)", 
                   [$student_id, $_SESSION['user_id'], $points, $reason]);
            
            // Сурагчийн нийт оноог шинэчлэх
            dbExec("UPDATE students SET merit_points = merit_points + ? WHERE student_id = ?", [$points, $student_id]);
            
            getDB()->commit();
            setFlash('success', 'Урамшууллын оноо амжилттай олгогдлоо!');
        } catch (Exception $e) {
            getDB()->rollBack();
            setFlash('error', 'Алдаа гарлаа: ' . $e->getMessage());
        }
    } else {
        setFlash('error', 'Бүх талбарыг бөглөнө үү.');
    }
    header("Location: /school_system1/pages/leaderboard/manage.php");
    exit;
}

// Анги болон сурагчдын жагсаалт
$classes = dbQuery("SELECT class_id, class_name FROM classes ORDER BY class_name");
$selected_class = (int)($_GET['class_id'] ?? 0);
$students = [];
if ($selected_class) {
    $students = dbQuery("SELECT student_id, CONCAT(last_name, ' ', first_name) as full_name, merit_points FROM students WHERE class_id = ? AND is_active = 1", [$selected_class]);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-plus-circle"></i> Сурагчид урамшууллын оноо өгөх</h2>
    </div>
    <div class="card-body">
        <form method="GET" action="" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
            <select name="class_id" class="form-control" style="max-width:250px;" onchange="this.form.submit()">
                <option value="">-- Анги сонгох --</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?= $c['class_id'] ?>" <?= $selected_class == $c['class_id'] ? 'selected' : '' ?>><?= h($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if($selected_class && empty($students)): ?>
            <p>Энэ ангид сурагч байхгүй байна.</p>
        <?php elseif($selected_class): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Сурагч</th>
                            <th>Одоогийн оноо</th>
                            <th>Оноо нэмэх</th>
                            <th>Шалтгаан</th>
                            <th>Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                <td><strong><?= h($s['full_name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= $s['merit_points'] ?> оноо</span></td>
                                <td>
                                    <input type="number" name="points" class="form-control" value="5" min="-50" max="50" style="width:80px;" required>
                                </td>
                                <td>
                                    <select name="reason" class="form-control" style="min-width:200px;" required>
                                        <option value="Хичээлийн идэвх сайн">Хичээлийн идэвх сайн</option>
                                        <option value="Бусдад тусалсан">Бусдад тусалсан</option>
                                        <option value="Сургуулийн арга хэмжээнд оролцсон">Сургуулийн арга хэмжээнд оролцсон</option>
                                        <option value="Анги цэвэрлэгээнд сайн оролцсон">Анги цэвэрлэгээнд сайн оролцсон</option>
                                        <option value="Бусад">Бусад</option>
                                    </select>
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Олгох</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--muted);">Дээрх жагсаалтаас анги сонгож оноо олгоно уу.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h2><i class="fas fa-history"></i> Сүүлийн оноо олголтууд</h2>
    </div>
    <div class="card-body" style="padding:0">
        <?php
        $logs = dbQuery("SELECT l.*, CONCAT(s.last_name, ' ', s.first_name) as student_name, u.full_name as teacher_name 
                         FROM merit_logs l 
                         JOIN students s ON l.student_id = s.student_id 
                         JOIN users u ON l.teacher_id = u.user_id 
                         ORDER BY l.created_at DESC LIMIT 10");
        ?>
        <table>
            <thead>
                <tr>
                    <th>Хугацаа</th>
                    <th>Сурагч</th>
                    <th>Оноо</th>
                    <th>Шалтгаан</th>
                    <th>Багш</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr>
                    <td style="font-size:12px;"><?= mnDateTime($log['created_at']) ?></td>
                    <td><?= h($log['student_name']) ?></td>
                    <td><b style="color: <?= $log['points']>=0?'var(--success)':'var(--danger)' ?>;"><?= $log['points'] > 0 ? '+'.$log['points'] : $log['points'] ?></b></td>
                    <td style="font-size:13px;"><?= h($log['reason']) ?></td>
                    <td style="font-size:12px; color:var(--muted);"><?= h($log['teacher_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
