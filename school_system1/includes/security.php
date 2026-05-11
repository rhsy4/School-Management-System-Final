<?php
/**
 * Security Headers and Middleware
 */

// Guard using define() for robustness
if (!defined('SECURITY_FUNCTIONS_LOADED')) {
    define('SECURITY_FUNCTIONS_LOADED', true);

    function setSecurityHeaders(): void {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com; img-src 'self' data:; font-src 'self' cdnjs.cloudflare.com fonts.gstatic.com; connect-src 'self'");
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        header_remove('Server');
        header_remove('X-Powered-By');
    }

    function enforceHttps(): void {
        if (config_getenv('HTTPS_REDIRECT') === 'true' || config_getenv('HTTPS_REDIRECT') === '1') {
            if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
                $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: {$url}", true, 301);
                exit;
            }
        }
    }

    function configureSecureSessions(): void {
        // Redundant - now managed centrally in config.php
        if (!isset($_SESSION['session_created'])) {
            $_SESSION['session_created'] = time();
        } elseif (time() - $_SESSION['session_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['session_created'] = time();
        }
    }

    function checkRateLimit($action, $limit = 5, $window = 300): bool {
        // Debug горимд rate limit-ийг идэвхгүй болгох
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            return true;
        }
        // IP болон session_id-г хосолсон key — session hijack-аас хамгаалах
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $sessId  = session_id() ?: 'nosession';
        $userId  = $_SESSION['user_id'] ?? 'anon';
        $keyRaw  = "rate_limit_{$action}_{$ip}_{$sessId}_{$userId}";
        $key     = 'rl_' . hash('sha256', $keyRaw); // session key хэт урт болохоос сэргийлэх

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_at' => time() + $window];
        }
        if (time() > $_SESSION[$key]['reset_at']) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_at' => time() + $window];
        }
        if ($_SESSION[$key]['attempts'] >= $limit) {
            return false;
        }
        $_SESSION[$key]['attempts']++;
        return true;
    }

    function updateLastActivity(): void {
        if (isset($_SESSION['user_id']) && function_exists('dbUpdate')) {
            dbUpdate("UPDATE users SET last_activity = NOW() WHERE user_id = ?", [$_SESSION['user_id']]);
        }
    }

    function applySecurityMiddleware(): void {
        setSecurityHeaders();
        enforceHttps();
        configureSecureSessions();
        // updateLastActivity() — auth.php-д 60 секундын throttle-тэй хувилбар байгаа тул энд дуудахгүй
    }
}

// Apply middleware - always execute
applySecurityMiddleware();
?>
