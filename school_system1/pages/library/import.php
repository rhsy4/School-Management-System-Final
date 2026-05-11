<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Check permission (Admin, Manager, Director)
if (!isManager() && !isAdmin() && !isDirector()) {
    header('Location: /school_system1/pages/library/index.php');
    exit;
}

$pageTitle = 'Ном олноор оруулах (Excel/CSV)';

// Татаж авах загвар файл (Sample CSV)
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=books_sample.csv');
    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($output, ['isbn', 'title', 'author', 'publisher', 'year', 'category', 'total_copies']);
    fputcsv($output, ['978-0132350884', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', '2008', 'Мэдээлэл зүй', '5']);
    fputcsv($output, ['978-0201633610', 'Design Patterns', 'Gang of Four', 'Addison-Wesley', '1994', 'Мэдээлэл зүй', '2']);
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
            // Assuming strict header: isbn, title, author, publisher, year, category, total_copies
            
            $successCount = 0;
            $rowNum = 1;
            
            $pdo = getDB();
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowNum++;
                if (count($data) < 2) { // At least title is needed
                    $importLog[] = "Мөр $rowNum: Дутуу мэдээлэлтэй алгаслаа.";
                    continue;
                }
                
                $isbn         = trim($data[0] ?? '');
                $title        = trim($data[1] ?? '');
                $author       = trim($data[2] ?? '');
                $publisher    = trim($data[3] ?? '');
                $year         = trim($data[4] ?? '') ?: null;
                $category     = trim($data[5] ?? '') ?: null;
                $total_copies = (int)trim($data[6] ?? '1');
                
                if ($total_copies < 1) $total_copies = 1;
                
                if (!$title) {
                    $importLog[] = "Мөр $rowNum: Номын нэр (title) хоосон байна.";
                    continue;
                }
                
                // Check if book with same ISBN or title already exists (optional duplicate check)
                if ($isbn) {
                    $exists = dbOne("SELECT book_id, total_copies FROM library_books WHERE isbn=?", [$isbn]);
                    if ($exists) {
                        // Just update copies
                        dbUpdate("UPDATE library_books SET total_copies = total_copies + ?, available = available + ? WHERE book_id=?", [$total_copies, $total_copies, $exists['book_id']]);
                        $importLog[] = "Мөр $rowNum: '$title' номын тоо хэмжээ шинэчлэгдлээ.";
                        $successCount++;
                        continue;
                    }
                } else {
                    $exists = dbOne("SELECT book_id FROM library_books WHERE title=? AND author=?", [$title, $author]);
                    if ($exists) {
                        dbUpdate("UPDATE library_books SET total_copies = total_copies + ?, available = available + ? WHERE book_id=?", [$total_copies, $total_copies, $exists['book_id']]);
                        $importLog[] = "Мөр $rowNum: '$title' номын тоо хэмжээ шинэчлэгдлээ.";
                        $successCount++;
                        continue;
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO library_books (isbn, title, author, publisher, year, category, total_copies, available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$isbn ?: null, $title, $author ?: null, $publisher ?: null, $year, $category, $total_copies, $total_copies]);
                    $successCount++;
                } catch (Exception $e) {
                    $importLog[] = "Мөр $rowNum: Алдаа гарлаа - " . $e->getMessage();
                }
            }
            fclose($handle);
            
            auditLog('books_imported', null, "$successCount ном CSV-ээс орууллаа.");
            setFlash('success', "Нийт $successCount ном амжилттай нэмэгдлээ.");
        } else {
            setFlash('error', 'Файл унших үед алдаа гарлаа.');
        }
    } else {
        setFlash('error', 'Файл сонгоогүй эсвэл алдаатай байна.');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:grid; grid-template-columns: 1fr 350px; gap: 24px; max-width: 1000px; margin: 0 auto;">
    
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0;"><i class="fas fa-file-import"></i> Ном олноор оруулах</h2>
            <a href="/school_system1/pages/library/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Буцах</a>
        </div>
        <div class="card-body">
            <div style="background:var(--hint-bg); color:var(--hint-text); padding:15px; border-radius:12px; margin-bottom:20px; font-size:13px;">
                <i class="fas fa-info-circle"></i> <strong>Анхааруулга:</strong> Зөвхөн доорх загвар CSV файлын дагуу бэлтгэсэн файлыг оруулна уу. 
                Хэрэв тухайн ном өмнө нь бүртгэгдсэн байвал (ISBN эсвэл Гарчиг+Зохиолчоор танина) шинээр давхардаж орохгүй, зөвхөн хувийн тоог (total_copies) нэмэх болно.
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
                <h4 style="margin-bottom:10px; color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Импортын тайлан:</h4>
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
            <h2 style="margin:0;"><i class="fas fa-download"></i> Загвар файл</h2>
        </div>
        <div class="card-body" style="text-align:center;">
            <i class="fas fa-file-csv" style="font-size:48px; color:var(--success); margin-bottom:15px;"></i>
            <p style="font-size:13px; color:var(--muted); margin-bottom:20px;">
                Та доорх загвар файлыг татаж аваад, загварын дагуу номнуудын мэдээллийг бөглөж оруулна уу.
            </p>
            <a href="?download_sample=1" class="btn btn-success" style="width:100%;"><i class="fas fa-download"></i> Загвар (Sample) татах</a>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
