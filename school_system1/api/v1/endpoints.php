<?php
/**
 * Mobile App API untuk School Management System
 * Endpoints untuk Android/iOS app
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// API версионалау
$version = $_GET['v'] ?? '1';
$endpoint = $_GET['action'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// ── TOKEN ПРОВЕРКА ────────────────────────────────────
$user = null;
if ($token && preg_match('/Bearer\s+(\S+)/', $token, $matches)) {
    $tokenValue = $matches[1];
    
    // Token-ыг hash хийгээд database-д шалгах
    $tokenHashed = hash('sha256', $tokenValue);
    $user = dbOne(
        "SELECT u.* FROM users u 
         WHERE u.api_token=? AND u.is_active=1 
         AND (u.api_token_expires IS NULL OR u.api_token_expires > NOW())",
        [$tokenHashed]
    );
    
    if (!$user) {
        http_response_code(401);
        jsonErr('Токен буруу эсвэл хугацаа нь дууссан');
    }
} else {
    // Анонимус API calls-д зөвшөөрөл өгөх
    if (!in_array($endpoint, ['login', 'announcements'])) {
        http_response_code(401);
        jsonErr('Токен шаардлагатай');
    }
}

switch ($endpoint) {
    // ── НЭВТРЭЛТ ──────────────────────────────────────
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$username || !$password) {
            jsonErr('Нэвтрэх нэр болон нууц үг оруулна уу');
        }
        
        $user = dbOne(
            "SELECT u.*, r.role_name FROM users u 
             JOIN user_roles r ON u.role_id=r.role_id 
             WHERE u.username=? AND u.is_active=1",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // API Token үүсгэх (24 цагийн хугацаатай)
            $apiToken = bin2hex(random_bytes(32));
            $expires  = date('Y-m-d H:i:s', time() + 86400);
            // DB-д SHA-256 hash хэлбэрээр хадгалах — plaintext хадгалахгүй
            $apiTokenHashed = hash('sha256', $apiToken);
            dbUpdate(
                "UPDATE users SET api_token=?, api_token_expires=? WHERE user_id=?",
                [$apiTokenHashed, $expires, $user['user_id']]
            );

            jsonOk([
                'user_id'   => $user['user_id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'role'      => $user['role_name'],
                'token'     => $apiToken  // plaintext token-ийг зөвхөн нэг удаа хэрэглэгчид өгнө
            ]);
        } else {
            http_response_code(401);
            jsonErr('Нэвтрэх нэр эсвэл нууц үг буруу');
        }
        break;
    
    // ── СУРАГЧИЙН МЭДЭЭЛЭЛ ────────────────────────────
    case 'student_info':
        if ($user['role_name'] !== 'student') {
            jsonErr('Сурагч доод ньүүнд шаардлагатай');
        }
        
        $student = dbOne(
            "SELECT s.*, c.class_name FROM students s 
             JOIN classes c ON s.class_id=c.class_id 
             WHERE s.user_id=?",
            [$user['user_id']]
        );
        
        jsonOk($student);
        break;
    
    // ── ДҮНГҮҮД АВАХ ──────────────────────────────────
    case 'student_grades':
        if (!$user) jsonErr('Хүчинтэй биш');
        
        $subject_id = $_GET['subject_id'] ?? null;
        $limit = (int)($_GET['limit'] ?? 20);
        
        $sql = "SELECT g.*, sub.subject_name, t.last_name, t.first_name 
                FROM grades g
                JOIN subjects sub ON g.subject_id=sub.subject_id
                LEFT JOIN teachers t ON g.teacher_id=t.user_id";
        $params = [];
        
        if ($user['role_name'] === 'student') {
            $student = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user['user_id']]);
            $sql .= " WHERE g.student_id=?";
            $params[] = $student['student_id'];
        } else if ($user['role_name'] === 'parent') {
            $sql .= " WHERE g.student_id IN (SELECT s.student_id FROM students s WHERE s.parent_id IN (SELECT parent_id FROM parents WHERE user_id=?))";
            $params[] = $user['user_id'];
        }
        
        if ($subject_id) {
            $sql .= " AND g.subject_id=?";
            $params[] = $subject_id;
        }
        
        $sql .= " ORDER BY g.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $grades = dbQuery($sql, $params);
        jsonOk($grades);
        break;
    
    // ── ИРЦИЙН МЭДЭЭЛЭЛ ────────────────────────────────
    case 'attendance':
        if (!$user) jsonErr('Хүчинтэй биш');
        
        $student = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user['user_id']]);
        
        $attendance = dbQuery(
            "SELECT a.*, sub.subject_name, s.subject_code
             FROM attendance a
             JOIN subjects sub ON a.subject_id=sub.subject_id
             WHERE a.student_id=?
             ORDER BY a.created_at DESC
             LIMIT 50",
            [$student['student_id']]
        );
        
        // Статистик тооцоолох
        $total = count($attendance);
        $present = count(array_filter($attendance, fn($a) => $a['status_id'] == 1));
        $absent = count(array_filter($attendance, fn($a) => $a['status_id'] == 2));
        $sick = count(array_filter($attendance, fn($a) => $a['status_id'] == 3));
        
        jsonOk([
            'records' => $attendance,
            'stats' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'sick' => $sick,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0
            ]
        ]);
        break;
    
    // ── СУРГУУЛИЙН САРЛАГУУД ──────────────────────────
    case 'announcements':
        $announcements = dbQuery(
            "SELECT announcement_id, title, content, image_url, created_at, pinned
             FROM announcements
             WHERE is_active=1
             ORDER BY pinned DESC, created_at DESC
             LIMIT 20"
        );
        
        jsonOk($announcements);
        break;
    
    // ── ТӨЛБӨРИЙН СТАТУС ──────────────────────────────
    case 'payments':
        if (!$user) jsonErr('Хүчинтэй биш');
        
        $student = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user['user_id']]);
        
        $payments = dbQuery(
            "SELECT * FROM tuition
             WHERE student_id=?
             ORDER BY created_at DESC",
            [$student['student_id']]
        );
        
        // Төлбөрийн статистик
        $paid_total = dbOne(
            "SELECT SUM(amount) as total FROM tuition WHERE student_id=? AND status='paid'",
            [$student['student_id']]
        )['total'] ?? 0;
        
        $unpaid_total = dbOne(
            "SELECT SUM(amount) as total FROM tuition WHERE student_id=? AND status='unpaid'",
            [$student['student_id']]
        )['total'] ?? 0;
        
        jsonOk([
            'payments' => $payments,
            'summary' => [
                'paid' => $paid_total,
                'unpaid' => $unpaid_total,
                'total' => $paid_total + $unpaid_total
            ]
        ]);
        break;
    
    // ── ТӨЛБӨР ХИЙХ ────────────────────────────────────
    case 'make_payment':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonErr('POST зарлагатай', 405);
        }
        
        if (!$user) jsonErr('Хүчинтэй биш');
        
        $student = dbOne("SELECT student_id FROM students WHERE user_id=?", [$user['user_id']]);
        $amount = (float)($_POST['amount'] ?? 0);
        $tuition_id = (int)($_POST['tuition_id'] ?? 0);
        
        if (!$amount || $amount <= 0) {
            jsonErr('Дүн буруу');
        }
        
        if ($tuition_id) {
            dbUpdate(
                "UPDATE tuition SET status='paid', updated_at=NOW() WHERE tuition_id=? AND student_id=?",
                [$tuition_id, $student['student_id']]
            );
            
            auditLog('payment_made', $student['student_id'], "Төлбөр: " . mnMoney($amount));
            jsonOk(['status' => 'paid', 'amount' => $amount]);
        } else {
            jsonErr('Төлбөр олдсонгүй');
        }
        break;
    
    // ── САБЖ ЖАГСААЛТ ──────────────────────────────────
    case 'subjects':
        $subjects = dbQuery("SELECT * FROM subjects ORDER BY subject_name");
        jsonOk($subjects);
        break;
    
    // ── ХЭРЭГЛЭГЧИЙН ПРОФАЙЛ ШИНЭЧЛЭХ ─────────────────
    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonErr('POST зарлагатай', 405);
        }
        
        if (!$user) jsonErr('Хүчинтэй биш');
        
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Email баталгаажуулалт
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonErr('Зөв имэйл хаяг оруулна уу');
        }
        
        dbUpdate(
            "UPDATE users SET phone=?, email=? WHERE user_id=?",
            [$phone, $email, $user['user_id']]
        );
        
        jsonOk(['status' => 'updated']);
        break;
    
    default:
        http_response_code(400);
        jsonErr('Үйл ажиллагаа олдсонгүй');
}
?>
