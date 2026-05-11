<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$pageTitle = 'Системийн Тохиргоо';

// Тохиргоо хадгалах
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    verifyCsrf();
    $keys = ['school_name', 'school_address', 'contact_email', 'contact_phone', 'academic_year', 'semester'];
    
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            // update or insert
            $stmt = getDB()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $val]);
        }
    }
    
    auditLog('settings_updated', null, 'Системийн тохиргоо өөрчлөгдсөн');
    setFlash('success', 'Тохиргоо амжилттай хадгалагдлаа.');
    header('Location: /school_system1/admin/settings.php');
    exit;
}

// Тохиргоог мэдээллийн сангаас унших
$settings = [];
$rows = dbQuery("SELECT setting_key, setting_value FROM settings");
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <div class="card-header">
        <h2><i class="fas fa-cogs"></i> Системийн Тохиргоо</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="save_settings">
            
            <div class="form-group">
                <label>Сургуулийн нэр</label>
                <input type="text" name="school_name" class="form-control" value="<?= h($settings['school_name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Хаяг</label>
                <input type="text" name="school_address" class="form-control" value="<?= h($settings['school_address'] ?? '') ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>И-мэйл хаяг</label>
                    <input type="email" name="contact_email" class="form-control" value="<?= h($settings['contact_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Холбоо барих утас</label>
                    <input type="text" name="contact_phone" class="form-control" value="<?= h($settings['contact_phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Хичээлийн жил</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= h($settings['academic_year'] ?? '') ?>" placeholder="2025-2026">
                </div>
                <div class="form-group">
                    <label>Идэвхтэй улирал</label>
                    <select name="semester" class="form-control">
                        <option value="1" <?= ($settings['semester'] ?? '') == '1' ? 'selected' : '' ?>>1-р улирал</option>
                        <option value="2" <?= ($settings['semester'] ?? '') == '2' ? 'selected' : '' ?>>2-р улирал</option>
                        <option value="3" <?= ($settings['semester'] ?? '') == '3' ? 'selected' : '' ?>>3-р улирал</option>
                        <option value="4" <?= ($settings['semester'] ?? '') == '4' ? 'selected' : '' ?>>4-р улирал</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Тохиргоог хадгалах</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
