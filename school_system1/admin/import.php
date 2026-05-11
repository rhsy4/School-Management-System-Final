<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$pageTitle = 'Хэрэглэгч олноор оруулах (Excel/CSV)';

// Татаж авах загвар файл (Sample CSV)
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_sample.csv');
    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($output, ['username', 'password', 'first_name', 'last_name', 'role_id']);
    fputcsv($output, ['bat123', 'pass123', 'Бат', 'Дорж', '4']);
    fputcsv($output, ['t_tuya', 'tuya321', 'Туяа', 'Наран', '3']);
    fclose($output);
    exit;
}

$importLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    verifyCsrf();
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($fileTmp, "r");
        
        if ($handle !== FALSE) {
            // Check BOM and remove if exists
            $bom = fread($handle, 3);
            if ($bom !== b"\xEF\xBB\xBF") {
                rewind($handle);
            }
            
            $header = fgetcsv($handle, 1000, ",");
            // Assuming strict header: username, password, first_name, last_name, role_id
            
            $successCount = 0;
            $rowNum = 1;
            
            $pdo = getDB();
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowNum++;
                if (count($data) < 5) {
                    $importLog[] = "Мөр $rowNum: Дутуу мэдээлэлтэй алгаслаа.";
                    continue;
                }
                
                $username   = trim($data[0]);
                $password   = trim($data[1]);
                $first_name = trim($data[2]);
                $last_name  = trim($data[3]);
                $role_id    = (int)trim($data[4]);
                
                if (!$username || !$password || !$first_name || !$role_id) {
                    $importLog[] = "Мөр $rowNum: Заавал оруулах талбар хоосон байна ($username).";
                    continue;
                }
                
                // Check if username exists
                $exists = dbOne("SELECT user_id FROM users WHERE username=?", [$username]);
                if ($exists) {
                    $importLog[] = "Мөр $rowNum: '$username' нэвтрэх нэр бүртгэлтэй байна.";
                    continue;
                }
                
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $full_name = mb_substr($last_name, 0, 1) . '.' . $first_name;
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, first_name, last_name, full_name, role_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $hash, $first_name, $last_name, $full_name, $role_id]);
                    $newUserId = $pdo->lastInsertId();
                    
                    // Хэрэв сурагч эсвэл багш бол давхар хүснэгтэд хоосон үүсгэж өгөх (optional)
                    if ($role_id == 4) { // Student
                        $pdo->prepare("INSERT IGNORE INTO students (user_id, first_name, last_name) VALUES (?, ?, ?)")->execute([$newUserId, $first_name, $last_name]);
                    } elseif ($role_id == 3) { // Teacher
                        $pdo->prepare("INSERT IGNORE INTO teachers (user_id, first_name, last_name) VALUES (?, ?, ?)")->execute([$newUserId, $first_name, $last_name]);
                    }
                    
                    $pdo->commit();
                    $successCount++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $importLog[] = "Мөр $rowNum: Алдаа гарлаа - " . $e->getMessage();
                }
            }
            fclose($handle);
            
            auditLog('users_imported', null, "$successCount хэрэглэгч CSV-ээс орууллаа.");
            setFlash('success', "Нийт $successCount хэрэглэгч амжилттай бүртгэгдлээ.");
        } else {
            setFlash('error', 'Файл унших үед алдаа гарлаа.');
        }
    } else {
        setFlash('error', 'Файл сонгоогүй эсвэл алдаатай байна.');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:grid; grid-template-columns: 1fr 350px; gap: 24px; max-width: 1000px; margin: 0 auto;">
    
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-import"></i> Хэрэглэгч олноор оруулах</h2>
        </div>
        <div class="card-body">
            <div style="background:var(--hint-bg); color:var(--hint-text); padding:15px; border-radius:12px; margin-bottom:20px; font-size:13px;">
                <i class="fas fa-info-circle"></i> <strong>Анхааруулга:</strong> Зөвхөн доорх загвар CSV файлын дагуу бэлтгэсэн файлыг оруулна уу. 
                Role ID: 1=Admin, 2=Manager, 3=Teacher, 4=Student, 5=Parent, 6=Director.
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="import_csv">
                
                <div class="form-group">
                    <label>CSV файл сонгох (.csv)</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required style="padding:10px; background:var(--bg);">
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Файлыг оруулах</button>
            </form>

            <?php if (!empty($importLog)): ?>
            <div style="margin-top:30px;">
                <h4 style="margin-bottom:10px; color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Импортын алдааны тайлан:</h4>
                <div style="background:var(--bg); border:1px solid var(--border); padding:15px; border-radius:8px; max-height:200px; overflow-y:auto; font-size:12px; font-family:monospace; color:var(--muted);">
                    <?php foreach ($importLog as $log): ?>
                        <div style="margin-bottom:4px; padding-bottom:4px; border-bottom:1px dashed var(--border);"><?= h($log) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-download"></i> Загвар файл</h2>
        </div>
        <div class="card-body" style="text-align:center;">
            <i class="fas fa-file-csv" style="font-size:48px; color:var(--success); margin-bottom:15px;"></i>
            <p style="font-size:13px; color:var(--muted); margin-bottom:20px;">
                Та доорх загвар файлыг татаж аваад, загварын дагуу хэрэглэгчдийн мэдээллийг бөглөж оруулна уу.
            </p>
            <a href="?download_sample=1" class="btn btn-success" style="width:100%;"><i class="fas fa-download"></i> Загвар (Sample) татах</a>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
