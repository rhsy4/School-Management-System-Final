<?php
/**
 * Database Backup & Recovery System
 * 1000-2000 сурагчтай системд зориулсан
 */

require_once __DIR__ . '/config.php';

class DatabaseBackup {
    private $db;
    private $backupDir;
    
    public function __construct() {
        $this->db = getDB();
        $this->backupDir = __DIR__ . '/../../uploads/backups/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Бүрэн database backup хийх
     */
    public function fullBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_full_{$timestamp}.sql";
            $filepath = $this->backupDir . $filename;
            
            // mysqldump ашигла (shell command)
            $command = sprintf(
                'mysqldump --user=%s --password=%s %s > %s',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                // Backup файлыг сжимэх
                $this->compressBackup($filepath);
                return ['success' => true, 'file' => $filename, 'size' => filesize($filepath)];
            } else {
                return ['success' => false, 'error' => 'mysqldump алдаа'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Хүснэгтүүдийг поднор хөдөлгөх (для больших объемов данных)
     */
    public function incrementalBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupData = [];
            
            // Бүх хүснэгтыг авах
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $filename = "backup_table_{$table}_{$timestamp}.sql";
                $filepath = $this->backupDir . $filename;
                
                // Таблицын структур болон өгөгдөл хадгалах
                $result = $this->db->query("SHOW CREATE TABLE `$table`");
                $createTableSQL = $result->fetch()[1];
                
                file_put_contents($filepath, "-- Table: $table\n");
                file_put_contents($filepath, $createTableSQL . ";\n\n", FILE_APPEND);
                
                // Өгөгдөл оруулах
                $rows = $this->db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $columns = implode(',', array_keys($row));
                    $values = implode("','", array_map('addslashes', array_values($row)));
                    file_put_contents(
                        $filepath,
                        "INSERT INTO `$table` ($columns) VALUES ('$values');\n",
                        FILE_APPEND
                    );
                }
                
                $backupData[] = ['table' => $table, 'file' => $filename];
            }
            
            return ['success' => true, 'backups' => $backupData];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Backup файлыг сжимэх
     */
    private function compressBackup($filepath) {
        if (extension_loaded('zlib')) {
            $content = file_get_contents($filepath);
            $compressed = gzcompress($content, 9);
            file_put_contents($filepath . '.gz', $compressed);
            unlink($filepath); // Оригинал файлыг устгах
            return $filepath . '.gz';
        }
        return $filepath;
    }
    
    /**
     * Backup-аас сэргээх
     */
    public function restore($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                return ['success' => false, 'error' => 'Файл олдсонгүй'];
            }
            
            // Сэлтэн байвал задалах
            if (substr($filepath, -3) === '.gz') {
                $content = gzuncompress(file_get_contents($filepath));
                $tempFile = $filepath . '.tmp.sql';
                file_put_contents($tempFile, $content);
                $filepath = $tempFile;
            }
            
            // SQL файлыг ажиллуулах (Better handling of semicolons in strings)
            $sql = file_get_contents($filepath);
            
            // Note: Simple split by semicolon is risky. For a more robust solution, we use a regex 
            // that ignores semicolons within single/double quotes.
            $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql);
            
            $executed = 0;
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    $this->db->exec($stmt);
                    $executed++;
                }
            }
            
            // Temp file устгах
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return ['success' => true, 'statements_executed' => $executed];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Backup жагсаалт харуулах
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*.sql*');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => filemtime($file),
                'created_date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Шинэ сургалтаар эрэмбэлэх
        usort($backups, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return $backups;
    }
    
    /**
     * Хуучин backup-ыг устгах (өөр өдрийн төлөв)
     */
    public function cleanOldBackups($daysToKeep = 30) {
        $cutoffTime = time() - ($daysToKeep * 24 * 3600);
        $deleted = 0;
        
        $files = glob($this->backupDir . 'backup_*.sql*');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return ['deleted' => $deleted];
    }
}

// Автомат backup scheduler (Cron job буюу Windows Task)
// Өдөр бүрийн 00:00 цагт ажиллуулах
if (php_sapi_name() === 'cli') {
    $backup = new DatabaseBackup();
    $result = $backup->fullBackup();
    echo json_encode($result);
    
    // Хуучин backup-ыг устгах
    $backup->cleanOldBackups(30);
}
?>
