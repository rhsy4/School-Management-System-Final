<?php
// XSS хамгаалалт
function h(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

// DB query helper
function dbQuery(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function dbOne(string $sql, array $params = []): ?array {
    $rows = dbQuery($sql, $params);
    return $rows[0] ?? null;
}
function dbExec(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int)getDB()->lastInsertId();
}
function dbUpdate(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// Нууц үг шалгах (bcrypt)
function hashPassword(string $pass): string { return password_hash($pass, PASSWORD_BCRYPT, ['cost'=>10]); }
function checkPassword(string $pass, string $hash): bool { return password_verify($pass, $hash); }

/**
 * Нууц үгийн хүч чадал шалгах
 * @return string|null Алдааны мэдэгдэл, эсвэл null (зөв)
 */
function validatePassword(string $pass): ?string {
    if (mb_strlen($pass) < 8) return 'Нууц үг дор хаяж 8 тэмдэгт байх ёстой.';
    if (!preg_match('/[A-Za-zА-Яа-яӨөҮү]/u', $pass)) return 'Нууц үг үсэг агуулсан байх ёстой.';
    if (!preg_match('/[0-9]/', $pass)) return 'Нууц үг тоо агуулсан байх ёстой.';
    return null;
}

/**
 * Email хаяг баталгаажуулах
 * @return bool
 */
function isValidEmail(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Монгол огноо форматлах
function mnDate(?string $date): string {
    if (!$date) return '-';
    return date('Y оны n сарын j', strtotime($date));
}
function mnDateTime(?string $dt): string {
    if (!$dt) return '-';
    return date('Y/m/d H:i', strtotime($dt));
}

// Мөнгөн дүн форматлах
function mnMoney(mixed $amount): string {
    return '₮' . number_format((float)$amount, 0, '.', ',');
}

// Хуудасны гарчиг
function pageTitle(string $title): string {
    return h($title) . ' | ' . SITE_NAME;
}

// JSON хариу
function jsonOk(mixed $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// Pagination helper
function paginate(int $total, int $page, int $perPage = 20): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    return ['total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages, 'offset' => ($page-1)*$perPage];
}

/**
 * Хэрэглэгчийн аватарийг авах (Fallback: зураггүй бол нэрийн эхний үсэг)
 */
function getUserAvatar(?string $imagePath = null, ?string $fullName = ''): string {
    if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
        return '/school_system1/' . $imagePath;
    }
    
    // Generate initial-based avatar if no image
    $fullName = $fullName ?: 'User';
    $initial = mb_strtoupper(mb_substr($fullName, 0, 1, 'UTF-8'), 'UTF-8');
    // Using a curated set of background colors
    $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    $color = $colors[abs(crc32($fullName)) % count($colors)];
    
    return "data:image/svg+xml;base64," . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <rect width="100" height="100" fill="'.$color.'"/>
            <text x="50" y="50" dominant-baseline="middle" text-anchor="middle" fill="white" font-family="Arial" font-size="50" font-weight="bold">'.$initial.'</text>
        </svg>
    ');
}

/**
 * Профайл зураг хуулах
 */
function uploadProfileImage(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    // Файлын хэмжээ 2MB-аас хэтрэхгүй байх
    if ($file['size'] > 2 * 1024 * 1024) return null;
    
    // finfo-оор бодит MIME шалгах (Browser-оос ирсэн $file['type']-д итгэж болохгүй)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($realMime, $allowedMimes)) return null;
    
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$realMime] ?? 'bin';
    
    $filename = 'profile_' . uniqid() . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $targetFile = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return 'uploads/profiles/' . $filename;
    }
    return null;
}

/**
 * Ухаалаг мэдэгдэл илгээх (Smart Alerts)
 * @param int $receiverId Хүлээн авагчийн user_id
 * @param string $content Мэдэгдлийн агуулга
 */
/**
 * Огноогоор хичээлийн улирлыг тогтоох (Монгол ЕБС-ийн жишгээр)
 */
function getQuarter(?string $date = null): int {
    // Хэрэв тодорхой огноо заагаагүй бол Админ-ийн тохируулсан улирлыг авна
    if ($date === null) {
        static $cachedSemester = null;
        if ($cachedSemester !== null) return $cachedSemester;
        
        try {
            if (function_exists('dbOne')) {
                $set = dbOne("SELECT setting_value FROM settings WHERE setting_key='semester'");
                if ($set && is_numeric($set['setting_value'])) {
                    $cachedSemester = (int)$set['setting_value'];
                    return $cachedSemester;
                }
            }
        } catch (Exception $e) {}
    }

    $m = (int)date('n', strtotime($date ?: 'now'));
    if ($m >= 9 && $m <= 10) return 1;
    if ($m >= 11 && $m <= 12) return 2;
    if ($m >= 1 && $m <= 3) return 3;
    if ($m >= 4 && $m <= 6) return 4;
    return 0; // Зуны амралт
}

/**
 * Регистрийн дугаар шалгах (Монгол)
 */
function isValidRegister(string $reg): bool {
    return (bool)preg_match('/^[А-ЯӨҮ]{2}[0-9]{8}$/u', mb_strtoupper($reg));
}

function sendSmartAlert(int $receiverId, string $content): void {
    try {
        // Системийн админ (эхний)-ыг олох, эсвэл fallback
        $sysAdmin = dbOne("SELECT user_id FROM users WHERE role_id=1 ORDER BY user_id ASC LIMIT 1");
        $senderId = $sysAdmin ? $sysAdmin['user_id'] : null;
        
        if ($senderId) {
            dbExec("INSERT INTO messages (sender_id, receiver_id, content, is_read) VALUES (?, ?, ?, 0)", [
                $senderId,
                $receiverId, 
                $content
            ]);
        }
    } catch (Exception $e) { /* чимээгүй алдаа */ }
}
?>

