<?php

// Enforce UTF-8 encoding for both PHP and browser
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");

// Load environment variables first
require_once __DIR__ . '/env.php';

// Configure session settings before starting the session
if (session_status() === PHP_SESSION_NONE) {
    $session_secure = config_getenv('SESSION_SECURE_COOKIE') === 'true' || config_getenv('SESSION_SECURE_COOKIE') === '1';
    $session_timeout = (int)config_getenv('SESSION_TIMEOUT', 3600);
    
    session_name('SCHOOL_SESS_ID'); // Unique name for this project
    ini_set('session.cookie_secure', $session_secure ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax'); 
    ini_set('session.cookie_path', '/school_system1/'); // Explicit path
    ini_set('session.gc_maxlifetime', $session_timeout);
    session_start();
}

require_once __DIR__ . '/security.php';

// Database Configuration
define('DB_HOST',     config_getenv('DB_HOST', '127.0.0.1'));
define('DB_PORT',     (int)config_getenv('DB_PORT', 3308));
define('DB_USER',     config_getenv('DB_USER', 'root'));
define('DB_PASS',     config_getenv('DB_PASS', ''));
define('DB_NAME',     config_getenv('DB_NAME', 'school_db'));
define('DB_CHARSET',  'utf8mb4');

// Application Configuration
define('SITE_NAME',   config_getenv('SITE_NAME', 'Нэгдсэн Цахим Сургуулийн Систем'));
define('SITE_URL',    config_getenv('SITE_URL', 'http://localhost/school_system1'));
define('SESSION_TIMEOUT', (int)config_getenv('SESSION_TIMEOUT', 3600));

// Feature Flags
define('MAIL_ENABLED', isEnvEnabled('MAIL_ENABLED', false));
define('SMS_ENABLED', isEnvEnabled('SMS_ENABLED', false));
define('API_ENABLED', isEnvEnabled('API_ENABLED', true));
define('BACKUP_ENABLED', isEnvEnabled('BACKUP_ENABLED', true));
define('REDIS_ENABLED', isEnvEnabled('REDIS_ENABLED', false));

// Security
define('BCRYPT_COST', (int)config_getenv('BCRYPT_COST', 10));
define('DEBUG_MODE', isEnvEnabled('DEBUG_MODE', false));

// Өгөгдлийн санд холбогдох
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Дотоод алдааг логт бичих, хэрэглэгчид задруулахгүй
            error_log('[DB Error] ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Өгөгдлийн сантай холбогдоход алдаа гарлаа. Дараа дахин оролдоно уу.']));
        }
    }
    return $pdo;
}
?>

