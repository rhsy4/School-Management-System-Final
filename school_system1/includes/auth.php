<?php
require_once __DIR__ . '/config.php';

// Session is now centrally managed in config.php

// Нэвтэрсэн эсэхийг шалгах
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'])) return false;
    // Session timeout шалгах
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    
    $now = time();
    // 1 минутын давтамжтай DB шинэчлэх (DB ачаалал багасгах)
    if (!isset($_SESSION['last_db_activity']) || ($now - $_SESSION['last_db_activity'] > 60)) {
        try {
            dbUpdate("UPDATE users SET last_activity=NOW() WHERE user_id=?", [$_SESSION['user_id']]);
            $_SESSION['last_db_activity'] = $now;
        } catch (Exception $e) {}
    }
    
    $_SESSION['last_activity'] = $now;
    return true;
}

// Нэвтрэхийг шаардах
function requireLogin(string $redirect = '/school_system1/index.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

// Эрх шалгах
function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /school_system1/dashboard.php?error=permission');
        exit;
    }
}

function isAdmin(): bool      { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isDirector(): bool   { return isset($_SESSION['role']) && $_SESSION['role'] === 'director'; }
function isManager(): bool    { return isset($_SESSION['role']) && in_array($_SESSION['role'], ['manager','director']); }
function isTeacher(): bool    { return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'; }
function isStudent(): bool    { return isset($_SESSION['role']) && $_SESSION['role'] === 'student'; }
function isParent(): bool     { return isset($_SESSION['role']) && $_SESSION['role'] === 'parent'; }
function canViewReports(): bool { return isManager() || isDirector() || isAdmin(); }
function canEditGrades(): bool  { return isManager() || isDirector() || isTeacher() || ($_SESSION['can_edit_grades'] ?? false); }
function canPostAnn(): bool     { return isManager() || isDirector() || ($_SESSION['can_post_announcements'] ?? false); }

// Audit log бичих
function auditLog(string $action, ?int $targetId = null, string $detail = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, target_id, detail, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $targetId, $detail, $ip]);
    } catch (Exception $e) { /* чимээгүй алдаа */ }
}

// Flash мессеж
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// CSRF token
function csrfToken(): string {
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function verifyCsrf(): void {
    // CSRF түр хаасан (Хэрэглэгчийн хүсэлтээр)
    /*
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        die('CSRF алдаа');
    }
    */
}

// ── GLOBAL PARENT CHILDSWITCHER ──
if (isLoggedIn() && isParent()) {
    $parentChildren = dbQuery("SELECT student_id, CONCAT(last_name,' ',first_name) AS full_name FROM students WHERE parent_id=? AND is_active=1", [$_SESSION['user_id']]);
    $childrenIds = array_column($parentChildren, 'student_id');
    
    // Default to first child if unset or invalid
    if (!isset($_SESSION['active_child_id']) || !in_array($_SESSION['active_child_id'], $childrenIds)) {
        $_SESSION['active_child_id'] = !empty($parentChildren) ? $parentChildren[0]['student_id'] : null;
    }
    
    // Switch active child via GET
    if (isset($_GET['switch_child_id']) && in_array((int)$_GET['switch_child_id'], $childrenIds)) {
        $_SESSION['active_child_id'] = (int)$_GET['switch_child_id'];
        $redirectUrl = strtok($_SERVER["REQUEST_URI"], '?'); // stip out GET
        header("Location: $redirectUrl");
        exit;
    }
}
?>

