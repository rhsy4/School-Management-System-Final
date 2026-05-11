<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Үйл ажиллагааны календар';
$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$isManager = isManager() || isAdmin() || isDirector();

// ── POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_event') {
        $classId = $_POST['class_id'] ?: null;
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?: null;
        
        if ($endDate && strtotime($endDate) < strtotime($startDate)) {
            setFlash('error', 'Дуусах огноо эхлэх огнооноос өмнө байж болохгүй.');
            header('Location: /school_system1/pages/events/index.php');
            exit;
        }

        dbExec("INSERT INTO events (title, description, event_type, start_date, end_date, location, target_audience, class_id, color, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)", [
            trim($_POST['title']),
            $_POST['description'] ?: null,
            $_POST['event_type'],
            $startDate,
            $endDate,
            $_POST['location'] ?: null,
            $_POST['target_audience'],
            $classId,
            $_POST['event_color'] ?: '#3b82f6',
            $user_id,
        ]);
        auditLog('event_add', null, 'title=' . $_POST['title']);
        setFlash('success', 'Үйл ажиллагаа нэмэгдлээ.');
        header('Location: /school_system1/pages/events/index.php');
        exit;
    }

    if ($action === 'edit_event') {
        $eventId = (int)$_POST['event_id'];
        $classId = $_POST['class_id'] ?: null;
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?: null;
        
        if ($endDate && strtotime($endDate) < strtotime($startDate)) {
            setFlash('error', 'Дуусах огноо эхлэх огнооноос өмнө байж болохгүй.');
            header('Location: /school_system1/pages/events/index.php');
            exit;
        }

        dbUpdate("UPDATE events SET title=?, description=?, event_type=?, start_date=?, end_date=?, location=?, target_audience=?, class_id=?, color=? WHERE event_id=?", [
            trim($_POST['title']),
            $_POST['description'] ?: null,
            $_POST['event_type'],
            $startDate,
            $endDate,
            $_POST['location'] ?: null,
            $_POST['target_audience'],
            $classId,
            $_POST['event_color'] ?: '#3b82f6',
            $eventId
        ]);
        auditLog('event_edit', $eventId, 'title=' . $_POST['title']);
        setFlash('success', 'Үйл ажиллагаа шинэчлэгдлээ.');
        header('Location: /school_system1/pages/events/index.php');
        exit;
    }

    if ($action === 'delete_event') {
        $eventId = (int)$_POST['event_id'];
        dbUpdate("DELETE FROM events WHERE event_id=?", [$eventId]);
        auditLog('event_delete', $eventId);
        setFlash('success', 'Устгагдлаа.');
        header('Location: /school_system1/pages/events/index.php');
        exit;
    }
    if ($action === 'seed_holidays') {

        $holidays = [
            ['Эх үрсийн баяр (Хүүхдийн баяр)', 'Бүх нийтийн амралт', 'holiday', '2026-06-01 00:00:00', '2026-06-01 23:59:59', 'Орон даяар', 'all', '#10b981'],
            ['Үндэсний их баяр наадам', 'Төв цэнгэлдэх хүрээлэн', 'holiday', '2026-07-11 00:00:00', '2026-07-15 23:59:59', 'Төв цэнгэлдэх', 'all', '#ef4444'],
            ['Улс тунхагласны баяр', 'Бүх нийтийн амралт', 'holiday', '2026-11-26 00:00:00', '2026-11-26 23:59:59', 'Орон даяар', 'all', '#3b82f6'],
            ['Үндэсний эрх чөлөө, тусгаар тогтнолоо сэргээсний баяр', 'Бүх нийтийн амралт', 'holiday', '2026-12-29 00:00:00', '2026-12-29 23:59:59', 'Орон даяар', 'all', '#10b981'],
            ['Шинэ жил', 'Баярын арга хэмжээ', 'holiday', '2026-12-31 00:00:00', '2027-01-01 23:59:59', 'Орон даяар', 'all', '#f59e0b']
        ];
        foreach ($holidays as $h) {
            dbExec("INSERT INTO events (title, description, event_type, start_date, end_date, location, target_audience, color, created_by) 
                    VALUES (?,?,?,?,?,?,?,?,?)", [$h[0], $h[1], $h[2], $h[3], $h[4], $h[5], $h[6], $h[7], $user_id]);
        }
        setFlash('success', '2026 оны баярууд нэмэгдлээ.');
        header('Location: /school_system1/pages/events/index.php');
        exit;
    }
}


// ── Ойрхон сар тодорхойлох ────────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prevY = $month == 1  ? $year-1 : $year;  $prevM = $month == 1  ? 12 : $month-1;
$nextY = $month == 12 ? $year+1 : $year;  $nextM = $month == 12 ? 1  : $month+1;

// ── Ирэх 30 хоногийн үйл ажиллагаа (list view) ───────────────
$roleFilter = ['all'];
if ($role === 'teacher')                $roleFilter = ['all', 'teachers'];
if ($role === 'student' || $role === 'parent') $roleFilter = ['all', 'students_parents'];
if ($isManager) $roleFilter = ['all','teachers','students_parents'];

$placeholders = implode(',', array_fill(0, count($roleFilter), '?'));
$monthStart = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
$monthEnd   = date("Y-m-t 23:59:59", strtotime($monthStart));

$allEvents = dbQuery("SELECT e.*, c.class_name, u.full_name AS creator
                      FROM events e
                      LEFT JOIN classes c ON e.class_id = c.class_id
                      LEFT JOIN users u   ON e.created_by = u.user_id
                      WHERE e.target_audience IN ($placeholders)
                        AND e.start_date <= ? AND (e.end_date >= ? OR e.start_date >= ?)
                      ORDER BY e.start_date ASC",
                     array_merge($roleFilter, [$monthEnd, $monthStart, $monthStart]));

// Календарт зориулан event-ийг өдрөөр groupлох
$calEvents = [];
foreach ($allEvents as $ev) {
    $eStart = strtotime($ev['start_date']);
    $eEnd   = $ev['end_date'] ? strtotime($ev['end_date']) : $eStart;
    
    $mStart = strtotime($monthStart);
    $mEnd   = strtotime($monthEnd);
    
    $actStart = max($eStart, $mStart);
    $actEnd   = min($eEnd, $mEnd);
    
    if ($actStart <= $actEnd) {
        $startDay = (int)date('j', $actStart);
        $endDay   = (int)date('j', $actEnd);
        for ($d = $startDay; $d <= $endDay; $d++) {
            $calEvents[$d][] = $ev;
        }
    }
}

// Ирх үйл ажиллагаа (дараагийн 30 хоног)
$upcomingEvents = dbQuery("SELECT e.*, c.class_name
                           FROM events e
                           LEFT JOIN classes c ON e.class_id = c.class_id
                           WHERE e.target_audience IN ($placeholders)
                             AND e.start_date >= NOW()
                             AND e.start_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                           ORDER BY e.start_date ASC",
                          $roleFilter);

$classes = $isManager ? dbQuery("SELECT class_id, class_name FROM classes ORDER BY class_name") : [];

$daysOfWeek  = ['Да', 'Мя', 'Лх', 'Пү', 'Ба', 'Бя', 'Ня'];
$monthNames  = ['','1-р сар','2-р сар','3-р сар','4-р сар','5-р сар','6-р сар',
                '7-р сар','8-р сар','9-р сар','10-р сар','11-р сар','12-р сар'];

$firstDay   = date('N', mktime(0,0,0,$month,1,$year)); // 1=Mon ... 7=Sun
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

include __DIR__ . '/../../includes/header.php';
?>

<style>
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.cal-header {
    background: var(--bg);
    color: var(--muted);
    text-align: center;
    padding: 12px 4px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}
.cal-cell {
    background: var(--card-bg);
    min-height: 110px;
    padding: 10px;
    vertical-align: top;
    position: relative;
    transition: background 0.2s;
}
.cal-cell:hover { background: rgba(59, 130, 246, 0.02); }
.cal-cell.today { background: rgba(59, 130, 246, 0.05); }
.cal-cell.empty { background: var(--bg); opacity:.4; }
.cal-date {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
    width: 28px; height: 28px;
    display: flex; align-items:center; justify-content:center;
    border-radius: 8px;
    transition: all 0.2s;
}
.cal-date.today-num {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 10px var(--primary-light);
}
.cal-event-dot {
    font-size: 10px;
    padding: 4px 8px;
    border-radius: 6px;
    margin-bottom: 4px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 700;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}
.cal-event-dot:hover { 
    transform: translateX(2px);
    filter: brightness(1.1);
}
.upcoming-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.upcoming-card:hover { 
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}
.upcoming-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 6px;
    background: var(--primary);
}
.event-type-tag {
    font-size: 9px;
    text-transform: uppercase;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(0,0,0,0.05);
    margin-right: 6px;
}
[data-theme="dark"] .event-type-tag { background: rgba(255,255,255,0.05); }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
    <div>
        <h1 style="font-size:22px; font-weight:700; margin:0 0 4px;">
            <i class="fas fa-calendar-alt" style="color:#8b5cf6;"></i> Үйл ажиллагааны календар
        </h1>
        <p style="color:var(--muted); margin:0; font-size:13px;">Сургуулийн ажлын хуваарь, арга хэмжээ</p>
    </div>
    <?php if ($isManager): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addEventPanel').style.display='block'; this.style.display='none';">
        <i class="fas fa-plus"></i> Үйл ажиллагаа нэмэх
    </button>
    <?php endif; ?>
</div>

<!-- Нэмэх форм -->
<?php if ($isManager): ?>
<div id="addEventPanel" style="display:none; background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:24px; margin-bottom:24px;">
    <h3 style="font-size:15px; font-weight:700; margin-bottom:16px;"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Шинэ үйл ажиллагаа</h3>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_event">
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="form-group" style="margin:0;"><label>Гарчиг <span style="color:#e53e3e">*</span></label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group" style="margin:0;"><label>Төрөл</label>
                <select name="event_type" class="form-control">
                    <option value="general">📌 Ерөнхий</option>
                    <option value="exam">📝 Шалгалт</option>
                    <option value="holiday">🎉 Амралт/Баяр</option>
                    <option value="sports">⚽ Спорт</option>
                    <option value="parent_meeting">👨‍👩‍👧 Эцэг эхийн хурал</option>
                    <option value="field_trip">🚌 Аялал</option>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="form-group" style="margin:0;"><label>Эхлэх огноо, цаг <span style="color:#e53e3e">*</span></label><input type="datetime-local" name="start_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div class="form-group" style="margin:0;"><label>Дуусах огноо, цаг</label><input type="datetime-local" name="end_date" class="form-control"></div>
            <div class="form-group" style="margin:0;"><label>Байршил</label><input type="text" name="location" class="form-control" placeholder="Актовый танхим..."></div>
            <div class="form-group" style="margin:0;"><label>Өнгө</label><input type="color" name="event_color" value="#3b82f6" class="form-control" style="height:40px; padding:2px 6px;"></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="form-group" style="margin:0;"><label>Хамрах хүрээ</label>
                <select name="target_audience" class="form-control">
                    <option value="all">👥 Бүгд</option>
                    <option value="teachers">👩‍🏫 Зөвхөн Багш нар</option>
                    <option value="students_parents">👨‍👩‍👧 Сурагч & Эцэг эх</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label>Анги (заавал биш)</label>
                <select name="class_id" class="form-control">
                    <option value="">Бүх анги</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl['class_id'] ?>"><?= h($cl['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Тайлбар</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addEventPanel').style.display='none'; document.querySelector('[onclick*=addEventPanel]').style.display='';">Болих</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start;">

    <!-- КАЛЕНДАР -->
    <div>
        <!-- Навигаци -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <a href="?year=<?= $prevY ?>&month=<?= $prevM ?>" class="btn btn-sm btn-secondary">‹ Өмнөх</a>
            <h2 style="font-size:20px; font-weight:700; margin:0;"><?= $year ?> оны <?= $monthNames[$month] ?></h2>
            <a href="?year=<?= $nextY ?>&month=<?= $nextM ?>" class="btn btn-sm btn-secondary">Дараагийн ›</a>
        </div>

        <div class="cal-grid">
            <?php foreach ($daysOfWeek as $d): ?>
            <div class="cal-header"><?= $d ?></div>
            <?php endforeach; ?>

            <?php
            $todayDay   = (int)date('j');
            $todayMonth = (int)date('m');
            $todayYear  = (int)date('Y');

            // Эхний өдрийн өмнөх хоосон нүднүүд
            $startEmpty = $firstDay - 1; // Mon=1->0, Sun=7->6
            for ($i = 0; $i < $startEmpty; $i++): ?>
            <div class="cal-cell empty"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $isToday = ($d == $todayDay && $month == $todayMonth && $year == $todayYear);
                $dayEvents = $calEvents[$d] ?? [];
            ?>
            <div class="cal-cell <?= $isToday ? 'today' : '' ?>">
                <div class="cal-date <?= $isToday ? 'today-num' : '' ?>"><?= $d ?></div>
                <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
                <div class="cal-event-dot"
                     style="background:<?= h($ev['color']) ?>22; color:<?= h($ev['color']) ?>; border-left:3px solid <?= h($ev['color']) ?>;"
                     title="<?= h($ev['title']) ?> — <?= date('H:i', strtotime($ev['start_date'])) ?>">
                    <?= h(mb_substr($ev['title'], 0, 16)) ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 3): ?>
                <div style="font-size:10px; color:var(--muted); padding:1px 4px;">+<?= count($dayEvents)-3 ?> дахин</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <?php
            $totalCells = $startEmpty + $daysInMonth;
            $remaining  = (7 - ($totalCells % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++): ?>
            <div class="cal-cell empty"></div>
            <?php endfor; ?>
        </div>

        <!-- Тайлбар -->
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; font-size:12px; color:var(--muted);">
            <?php
            $typeMeta = [
                'general'        => ['color'=>'#3b82f6','label'=>'Ерөнхий'],
                'exam'           => ['color'=>'#ef4444','label'=>'Шалгалт'],
                'holiday'        => ['color'=>'#10b981','label'=>'Амралт/Баяр'],
                'sports'         => ['color'=>'#f59e0b','label'=>'Спорт'],
                'parent_meeting' => ['color'=>'#8b5cf6','label'=>'Эцэг эхийн хурал'],
                'field_trip'     => ['color'=>'#06b6d4','label'=>'Аялал'],
            ];
            foreach ($typeMeta as $t):
            ?>
            <span style="display:flex; align-items:center; gap:4px;">
                <span style="width:10px; height:10px; border-radius:2px; background:<?= $t['color'] ?>; display:inline-block;"></span>
                <?= $t['label'] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ИРЭХ ҮЙЛ АЖИЛЛАГАА (30 хоног) -->
    <div>
        <h3 style="font-size:15px; font-weight:700; margin-bottom:16px;">
            <i class="fas fa-calendar-day" style="color:var(--primary)"></i> Ойрын үйл ажиллагаа
        </h3>
        <?php if (empty($upcomingEvents)): ?>
        <div style="text-align:center; padding:30px; color:var(--muted); background:var(--card-bg); border:1px solid var(--border); border-radius:12px;">
            <i class="fas fa-calendar-check" style="font-size:28px; opacity:.3;"></i>
            <p style="margin-top:10px; font-size:13px;">Ойрын 30 хоногт үйл ажиллагаа байхгүй.</p>
            <?php if ($isManager): ?>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="seed_holidays">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-magic"></i> 2026 оны баярууд оруулах</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($upcomingEvents as $ev):
            $typeM = $typeMeta[$ev['event_type']] ?? ['color'=>'#3b82f6','label'=>'Ерөнхий'];
            $typeIcons = ['general'=>'📌','exam'=>'📝','holiday'=>'🎉','sports'=>'⚽','parent_meeting'=>'👨‍👩‍👧','field_trip'=>'🚌'];
        ?>
        <div class="upcoming-card" style="border-left-color:<?= h($ev['color'] ?: $typeM['color']) ?>;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:6px;">
                <strong style="font-size:14px; line-height:1.4;">
                    <?= $typeIcons[$ev['event_type']] ?? '📌' ?> <?= h($ev['title']) ?>
                </strong>
                <?php if ($isManager): ?>
                <div style="display:flex; gap:5px; flex-shrink:0;">
                    <button class="btn btn-sm" style="background:none; border:none; cursor:pointer; color:var(--muted); font-size:14px; padding:0 4px;" onclick='openEditEvent(<?= json_encode($ev) ?>)' title="Засах">✏️</button>
                    <form method="POST" style="flex-shrink:0;" onsubmit="return confirm('Устгах уу?')">
                        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                        <button type="submit" style="background:none; border:none; cursor:pointer; color:var(--muted); font-size:14px; padding:0 4px;" title="Устгах">🗑️</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div style="font-size:12px; color:var(--muted); display:flex; flex-wrap:wrap; gap:10px;">
                <span><i class="fas fa-calendar"></i> <?= mnDate($ev['start_date']) ?> <?= date('H:i', strtotime($ev['start_date'])) ?></span>
                <?php if ($ev['end_date']): ?>
                <span>→ <?= date('H:i', strtotime($ev['end_date'])) ?></span>
                <?php endif; ?>
                <?php if ($ev['location']): ?>
                <span><i class="fas fa-map-marker-alt"></i> <?= h($ev['location']) ?></span>
                <?php endif; ?>
                <?php if ($ev['class_name']): ?>
                <span><i class="fas fa-chalkboard"></i> <?= h($ev['class_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($ev['description']): ?>
            <p style="font-size:13px; color:var(--text); margin:8px 0 0; line-height:1.5;"><?= h(mb_substr($ev['description'], 0, 100)) ?><?= mb_strlen($ev['description']) > 100 ? '...' : '' ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($isManager): ?>
<div id="editEventModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--card-bg); border-radius:14px; padding:24px; max-width:600px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;"><i class="fas fa-edit" style="color:var(--primary)"></i> Үйл ажиллагаа засах</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="edit_event">
            <input type="hidden" name="event_id" id="edit_event_id">
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group" style="margin:0;"><label>Гарчиг <span style="color:#e53e3e">*</span></label><input type="text" name="title" id="edit_title" class="form-control" required></div>
                <div class="form-group" style="margin:0;"><label>Төрөл</label>
                    <select name="event_type" id="edit_event_type" class="form-control">
                        <option value="general">📌 Ерөнхий</option>
                        <option value="exam">📝 Шалгалт</option>
                        <option value="holiday">🎉 Амралт/Баяр</option>
                        <option value="sports">⚽ Спорт</option>
                        <option value="parent_meeting">👨‍👩‍👧 Эцэг эхийн хурал</option>
                        <option value="field_trip">🚌 Аялал</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group" style="margin:0;"><label>Эхлэх <span style="color:#e53e3e">*</span></label><input type="datetime-local" name="start_date" id="edit_start_date" class="form-control" required></div>
                <div class="form-group" style="margin:0;"><label>Дуусах</label><input type="datetime-local" name="end_date" id="edit_end_date" class="form-control"></div>
                <div class="form-group" style="margin:0;"><label>Байршил</label><input type="text" name="location" id="edit_location" class="form-control"></div>
                <div class="form-group" style="margin:0;"><label>Өнгө</label><input type="color" name="event_color" id="edit_event_color" class="form-control" style="height:40px; padding:2px 6px;"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group" style="margin:0;"><label>Хамрах хүрээ</label>
                    <select name="target_audience" id="edit_target_audience" class="form-control">
                        <option value="all">👥 Бүгд</option>
                        <option value="teachers">👩‍🏫 Зөвхөн Багш нар</option>
                        <option value="students_parents">👨‍👩‍👧 Сурагч & Эцэг эх</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;"><label>Анги (заавал биш)</label>
                    <select name="class_id" id="edit_class_id" class="form-control">
                        <option value="">Бүх анги</option>
                        <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl['class_id'] ?>"><?= h($cl['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Тайлбар</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Хадгалах</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editEventModal').style.display='none'">Болих</button>
            </div>
        </form>
    </div>
</div>
<script>
function openEditEvent(ev) {
    document.getElementById('edit_event_id').value = ev.event_id;
    document.getElementById('edit_title').value = ev.title;
    document.getElementById('edit_event_type').value = ev.event_type;
    document.getElementById('edit_start_date').value = ev.start_date.replace(' ', 'T').slice(0,16);
    document.getElementById('edit_end_date').value = ev.end_date ? ev.end_date.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('edit_location').value = ev.location || '';
    document.getElementById('edit_event_color').value = ev.color || '#3b82f6';
    document.getElementById('edit_target_audience').value = ev.target_audience;
    document.getElementById('edit_class_id').value = ev.class_id || '';
    document.getElementById('edit_description').value = ev.description || '';
    
    document.getElementById('editEventModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
