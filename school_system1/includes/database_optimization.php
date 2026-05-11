<?php
/**
 * Database Performance Optimization & Queries
 * 1000-2000 сурагчтай системд зориулсан оптимизаци
 */

// DATABASE INDICES - Шаардлагатай индексүүд
$optimizationQueries = [
    // ── STUDENTS TABLE ──────────────────────────────
    "CREATE INDEX idx_students_class_id ON students(class_id)",
    "CREATE INDEX idx_students_parent_id ON students(parent_id)",
    "CREATE INDEX idx_students_user_id ON students(user_id)",
    "CREATE INDEX idx_students_active ON students(is_active)",
    "CREATE INDEX idx_students_class_active ON students(class_id, is_active)",
    
    // ── GRADES TABLE ────────────────────────────────
    "CREATE INDEX idx_grades_student_id ON grades(student_id)",
    "CREATE INDEX idx_grades_teacher_id ON grades(teacher_id)",
    "CREATE INDEX idx_grades_subject_id ON grades(subject_id)",
    "CREATE INDEX idx_grades_created_at ON grades(created_at)",
    "CREATE INDEX idx_grades_student_created ON grades(student_id, created_at)",
    "CREATE INDEX idx_grades_subject_created ON grades(subject_id, created_at)",
    
    // ── ATTENDANCE TABLE ────────────────────────────
    "CREATE INDEX idx_attendance_student_id ON attendance(student_id)",
    "CREATE INDEX idx_attendance_created_at ON attendance(created_at)",
    "CREATE INDEX idx_attendance_student_date ON attendance(student_id, created_at)",
    "CREATE INDEX idx_attendance_subject_id ON attendance(subject_id)",
    
    // ── USERS TABLE ────────────────────────────────
    "CREATE INDEX idx_users_username ON users(username)",
    "CREATE INDEX idx_users_email ON users(email)",
    "CREATE INDEX idx_users_role_id ON users(role_id)",
    "CREATE INDEX idx_users_active ON users(is_active)",
    
    // ── TUITION TABLE ──────────────────────────────
    "CREATE INDEX idx_tuition_student_id ON tuition(student_id)",
    "CREATE INDEX idx_tuition_status ON tuition(status)",
    "CREATE INDEX idx_tuition_created_at ON tuition(created_at)",
    "CREATE INDEX idx_tuition_student_status ON tuition(student_id, status)",
    
    // ── ANNOUNCEMENTS TABLE ─────────────────────────
    "CREATE INDEX idx_announcements_active ON announcements(is_active)",
    "CREATE INDEX idx_announcements_created_at ON announcements(created_at)",
];

// ── QUERY OPTIMIZATION ──────────────────────────────────
class DatabaseOptimization {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Бүх index-ыг үүсгэх
     */
    public function createOptimizationIndices() {
        global $optimizationQueries;
        
        $results = [];
        foreach ($optimizationQueries as $query) {
            try {
                // IF NOT EXISTS гэсэн część байхгүй бол ошибку үзүүлэх
                $this->db->exec($query);
                $results[] = ['query' => $query, 'status' => 'success'];
            } catch (Exception $e) {
                // Index аль хэдийн байгаа байж болох
                $results[] = ['query' => $query, 'status' => 'skipped', 'reason' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * Өөр хүчирхүй өөрчлөлтүүд
     */
    public function optimizeTables() {
        $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $results = [];
        foreach ($tables as $table) {
            try {
                // OPTIMIZE таблица
                $this->db->exec("OPTIMIZE TABLE `$table`");
                $results[] = ['table' => $table, 'status' => 'optimized'];
                
                // ANALYZE таблица
                $this->db->exec("ANALYZE TABLE `$table`");
                $results[] = ['table' => $table, 'status' => 'analyzed'];
            } catch (Exception $e) {
                $results[] = ['table' => $table, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * QUERY STATISTICS - Бүтээмжээд харах
     */
    public function getQueryStats() {
        return [
            'students_count' => $this->db->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn(),
            'teachers_count' => $this->db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
            'grades_count' => $this->db->query("SELECT COUNT(*) FROM grades")->fetchColumn(),
            'attendance_count' => $this->db->query("SELECT COUNT(*) FROM attendance")->fetchColumn(),
            'total_size' => $this->getTotalDatabaseSize(),
            'query_cache' => $this->getQueryCacheStats(),
        ];
    }
    
    /**
     * Database хэмжээ авах
     */
    private function getTotalDatabaseSize() {
        $result = $this->db->query(
            "SELECT SUM(data_length + index_length) as size 
             FROM information_schema.tables 
             WHERE table_schema=?"
        )->execute([DB_NAME])->fetch();
        
        $bytes = $result['size'] ?? 0;
        return $this->formatBytes($bytes);
    }
    
    /**
     * MySQL Query Cache статистик
     */
    private function getQueryCacheStats() {
        try {
            $result = $this->db->query("SHOW STATUS LIKE 'Qcache%'")->fetchAll(PDO::FETCH_KEY_PAIR);
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Slow Query Log шалгах
     */
    public function checkSlowQueries() {
        try {
            $result = $this->db->query(
                "SELECT query_time, lock_time, rows_sent, rows_examined, sql_text 
                 FROM mysql.slow_log 
                 ORDER BY query_time DESC 
                 LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC);
            
            return $result;
        } catch (Exception $e) {
            return ['error' => 'Slow log нээлттэй байхгүй'];
        }
    }
    
    /**
     * Byte-ыг сайн форматлах
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * PARTITION шинэтгэлийг зөвхөн их өгөгдөлөөр үйлдэе
     * Шаддалсан хүх үндсэн
     */
    public function setupTablePartitioning() {
        try {
            // Grades таблицыг сар бүр хуалах
            $this->db->exec("
                ALTER TABLE grades 
                PARTITION BY RANGE (YEAR_MONTH(created_at)) (
                    PARTITION p202401 VALUES LESS THAN (202402),
                    PARTITION p202402 VALUES LESS THAN (202403),
                    PARTITION p202403 VALUES LESS THAN (202404),
                    PARTITION p202405 VALUES LESS THAN (202406),
                    PARTITION p202407 VALUES LESS THAN (202408),
                    PARTITION p202409 VALUES LESS THAN (202410),
                    PARTITION p202411 VALUES LESS THAN (202412),
                    PARTITION p202412 VALUES LESS THAN (202501),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
            
            return ['success' => true, 'message' => 'Partitioning нэмэгдлээ'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// ── CONNECTION POOLING CONFIG ────────────────────────
// PHP.ini дээр оруулаа:
// pdo_mysql.max_persistent=10
// pdo_mysql.allow_persistent=1

// ── REDIS CACHING (Сонголттой) ──────────────────────
class RedisCache {
    private $redis;
    
    public function __construct() {
        if (extension_loaded('redis')) {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
    }
    
    /**
     * Query результат кешлэх
     */
    public function cacheQuery($key, $query, $params = [], $ttl = 3600) {
        if (!$this->redis) return null;
        
        // Кешд байгаа эсэх
        $cached = $this->redis->get($key);
        if ($cached) {
            return json_decode($cached, true);
        }
        
        // Query ажиллуулах
        $db = getDB();
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Кешда хадгалах
        $this->redis->setex($key, $ttl, json_encode($result));
        
        return $result;
    }
    
    /**
     * Кешда устгах
     */
    public function invalidateCache($pattern) {
        if (!$this->redis) return;
        
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}

?>
