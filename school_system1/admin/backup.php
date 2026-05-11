<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$pageTitle = 'Мэдээллийн сан нөөцлөх (Backup)';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_backup') {
    verifyCsrf();
    
    $filename = DB_NAME . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = sys_get_temp_dir() . '/' . $filename;
    
    // Windows/XAMPP mysqldump path (adjust if necessary or rely on PATH)
    $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (!file_exists($mysqldumpPath)) {
        $mysqldumpPath = 'mysqldump'; // Fallback to system PATH
    }
    
    $cmd = sprintf(
        '"%s" -h %s -P %s -u %s %s %s > "%s"',
        $mysqldumpPath,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        DB_PASS ? '-p' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        $filepath
    );
    
    exec($cmd, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($filepath)) {
        auditLog('db_backup', null, 'Мэдээллийн санг нөөцөлж татлаа');
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        
        unlink($filepath);
        exit;
    } else {
        setFlash('error', 'Нөөцлөлт үүсгэх үед алдаа гарлаа: ' . implode("\n", $output));
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:600px; margin:0 auto; text-align:center;">
    <div class="card-header" style="justify-content:center;">
        <h2><i class="fas fa-database"></i> Мэдээллийн Сан Нөөцлөх</h2>
    </div>
    <div class="card-body">
        <i class="fas fa-server" style="font-size:64px; color:var(--primary); margin-bottom:20px;"></i>
        <p style="color:var(--muted); margin-bottom:30px;">
            Энэхүү үйлдэл нь системийн бүх өгөгдлийг (хэрэглэгчид, дүн, ирц, хуваарь г.м) агуулсан SQL файлыг таны компьютерт татаж авах болно. 
            Нөөцлөлтийг 7 хоног бүр авч байхыг зөвлөж байна.
        </p>
        
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="download_backup">
            <button type="submit" class="btn btn-primary btn-lg" style="padding:15px 30px; font-size:16px;">
                <i class="fas fa-download"></i> Одоо нөөцлөлт татах
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
