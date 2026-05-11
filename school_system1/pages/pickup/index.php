<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Үйлдэл боловсруулах
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action == 'request_pickup' && isParent()) {
        $student_id = (int)$_POST['student_id'];
        
        // Relationship verification
        $isMyChild = dbOne("SELECT student_id FROM students WHERE student_id=? AND parent_id=?", [$student_id, $_SESSION['user_id']]);
        if (!$isMyChild) {
            http_response_code(403);
            die('Эрх хүрэхгүй: Энэ сурагчийн эцэг эх биш байна.');
        }
        
        $p_name = trim($_POST['picker_name']) ?: 'Эцэг эх';
        $p_rel  = $_POST['picker_relation'] ?: 'Өөрөө';
        
        dbExec("INSERT INTO pickup_requests (student_id, parent_id, status, picker_name, picker_relation) 
                VALUES (?, ?, 'waiting', ?, ?)", [$student_id, $_SESSION['user_id'], $p_name, $p_rel]);
        setFlash('success', 'Хүсэлт илгээгдлээ. Төлөвийг доороос хянана уу.');
        
    } elseif ($action == 'update_status' && (isTeacher() || isManager() || isAdmin())) {
        $req_id = (int)$_POST['request_id'];
        
        // Багш зөвхөн өөрийн ангийн сурагчийн төлөвийг шинэчлэх боломжтой
        if (isTeacher() && !isManager() && !isAdmin()) {
            $isMyStudent = dbOne("SELECT p.id FROM pickup_requests p 
                                  JOIN students s ON p.student_id = s.student_id 
                                  JOIN classes c ON s.class_id = c.class_id 
                                  WHERE p.id=? AND c.teacher_id=?", [$req_id, $_SESSION['user_id']]);
            if (!$isMyStudent) {
                http_response_code(403);
                die('Та зөвхөн өөрийн ангийн сурагчийн мэдээллийг шинэчлэх боломжтой.');
            }
        }
        
        $new_status = $_POST['status'];
        $allowedStatuses = ['on_the_way', 'released'];
        if (!in_array($new_status, $allowedStatuses)) {
            setFlash('error', 'Буруу төлөв утга.');
        } else {
            dbExec("UPDATE pickup_requests SET status=? WHERE id=?", [$new_status, $req_id]);
            setFlash('success', 'Төлөв шинэчлэгдлээ.');
        }
    }
    
    header("Location: /school_system1/pages/pickup/index.php");
    exit;
}

$pageTitle = 'Ухаалаг Хүүхэд Авалт';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .airport-board { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .pickup-card { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border); padding: 20px; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s; }
    .pickup-card:hover { transform: translateY(-4px); }
    .pickup-card.waiting { border-left: 5px solid #ef4444; background: #fffcfc; }
    .pickup-card.on_the_way { border-left: 5px solid #3b82f6; background: #f0f7ff; }
    .pickup-card.released { border-left: 5px solid #10b981; opacity: 0.7; }
    .status-badge { font-size: 11px; text-transform: uppercase; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
    .bg-waiting { background: #fee2e2; color: #b91c1c; }
    .bg-transit { background: #dbeafe; color: #1e40af; }
    .bg-done { background: #d1fae5; color: #065f46; }
    .pulse-dot { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; display: inline-block; margin-right: 5px; animation: pulse 1.5s infinite; }
</style>

<?php if(isParent()): 
    // ЭЦЭГ ЭХИЙН ХЭСЭГ
    $children = dbQuery("SELECT s.student_id, CONCAT(s.last_name, ' ', s.first_name) as full_name, c.class_name 
                         FROM students s JOIN classes c ON s.class_id = c.class_id
                         WHERE s.parent_id=? AND s.is_active=1", [$_SESSION['user_id']]);
    
    $myActiveRequests = dbQuery("SELECT p.*, CONCAT(s.last_name, ' ', s.first_name) as student_name 
                                 FROM pickup_requests p JOIN students s ON p.student_id=s.student_id 
                                 WHERE p.parent_id=? AND p.status != 'released' 
                                 AND p.created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)", [$_SESSION['user_id']]);
?>
    <div class="card" style="background: linear-gradient(to right, #1e293b, #334155); color:#fff;">
        <div class="card-body">
            <h2><i class="fas fa-id-card"></i> Цахим Диппатч</h2>
            <p style="opacity:0.8;">Та гадаа ирсэн бол хүүхдээ дуудна уу. Мөн хэн авахыг зааж өгөх боломжтой.</p>
            
            <?php if(empty($children)): ?>
                <div class="alert alert-warning">Танд бүртгэлтэй хүүхэд олдсонгүй.</div>
            <?php else: ?>
                <form method="POST" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin-top:20px;">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="request_pickup">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <label>Хүүхэд:</label>
                            <select name="student_id" class="form-control" required>
                                <?php foreach($children as $child): ?>
                                    <option value="<?= $child['student_id'] ?>"><?= h($child['full_name']) ?> (<?= h($child['class_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Авах хүн:</label>
                            <input type="text" name="picker_name" class="form-control" placeholder="Жишээ: Аав нь, Өөрөө, Такси..." value="Эцэг эх">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg" style="width:100%;"><i class="fas fa-paper-plane"></i> ДУУДАХ / ИРЛЭЭ</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ТӨЛӨВ ХЯНАХ -->
    <h3 style="margin-top:30px;"><i class="fas fa-history"></i> Идэвхтэй хүсэлтүүд</h3>
    <div class="airport-board">
        <?php foreach($myActiveRequests as $req): ?>
            <div class="pickup-card <?= $req['status'] ?>">
                <div style="display:flex; justify-content:space-between; align-items:start;">
                    <h4 style="margin:0;"><?= h($req['student_name']) ?></h4>
                    <span class="status-badge <?= $req['status']=='waiting'?'bg-waiting':'bg-transit' ?>">
                        <?= $req['status']=='waiting'?'Хүлээж буй':'Гарч байна' ?>
                    </span>
                </div>
                <div style="margin-top:10px; font-size:13px; color:var(--muted);">
                    <i class="fas fa-clock"></i> Дуудсан: <?= date('H:i', strtotime($req['created_at'])) ?><br>
                    <i class="fas fa-user-check"></i> Авах хүн: <?= h($req['picker_name']) ?>
                </div>
                <?php if($req['status'] == 'on_the_way'): ?>
                    <div style="margin-top:15px; color:#1e40af; background:#e0f2fe; padding:8px; border-radius:6px; font-size:12px;">
                       <i class="fas fa-info-circle"></i> Багш хүүхдийг гадагш явууллаа. Та хүлээж аваарай.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if(isTeacher() || isManager() || isAdmin()): 
    // БАГШИЙН "ДИСПАТЧЕР" ДЭЛГЭЦ
    $sql = "SELECT p.*, CONCAT(s.last_name, ' ', s.first_name) as student_name, c.class_name, u.full_name as parent_full_name
            FROM pickup_requests p
            JOIN students s ON p.student_id = s.student_id
            JOIN classes c ON s.class_id = c.class_id
            JOIN users u ON p.parent_id = u.user_id
            WHERE p.status != 'released' AND p.created_at > DATE_SUB(NOW(), INTERVAL 4 HOUR)";
    $params = [];
    if (isTeacher() && !isManager() && !isAdmin()) {
        $sql .= " AND c.teacher_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    $sql .= " ORDER BY (CASE WHEN p.status='waiting' THEN 1 ELSE 2 END), p.created_at ASC";
    $requests = dbQuery($sql, $params);
?>
    <div class="card-header" style="background:#1e293b; color:#fff; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0;"><i class="fas fa-microchip"></i> Ухаалаг Диспатчер Самбар</h2>
        <div style="font-size:14px;"><i class="fas fa-circle" style="color:#ef4444; font-size:10px;"></i> LIVE SYSTEM <span id="refreshTimer" style="font-size:10px; opacity:0.6; margin-left:10px;">(10s)</span></div>
    </div>
    <script>
        let countdown = 10;
        setInterval(() => {
            countdown--;
            document.getElementById('refreshTimer').innerText = `(${countdown}s)`;
            if(countdown <= 0) location.reload();
        }, 1000);
    </script>
    
    <div class="airport-board">
        <?php foreach($requests as $req): ?>
            <div class="pickup-card <?= $req['status'] ?>">
                <div style="display:flex; justify-content:space-between;">
                   <span class="badge badge-info"><?= h($req['class_name']) ?></span>
                   <span style="font-size:12px; color:var(--muted);"><?= date('H:i', strtotime($req['created_at'])) ?></span>
                </div>
                <h3 style="margin:10px 0;"><?= h($req['student_name']) ?></h3>
                <div style="font-size:13px; margin-bottom:15px; border-top:1px dashed var(--border); padding-top:10px;">
                    <i class="fas fa-user-tie"></i> <?= h($req['picker_name']) ?> (<?= h($req['picker_relation']) ?>) <br>
                    <small style="color:var(--muted)">Эцэг эх: <?= h($req['parent_full_name']) ?></small>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <?php if($req['status'] == 'waiting'): ?>
                        <form method="POST" style="flex:1;">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="on_the_way">
                            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-walking"></i> ГАРГАХ</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="flex:1;">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="released">
                            <button type="submit" class="btn btn-success" style="width:100%;"><i class="fas fa-check"></i> ХҮЛЭЭЛГЭН ӨГСӨН</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
