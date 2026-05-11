<?php
/**
 * Environment Variable Loader - Uses define() guard
 */

// Only declare functions once using a define guard
if (!defined('ENV_FUNCTIONS_LOADED')) {
    define('ENV_FUNCTIONS_LOADED', true);

    function loadEnv($path = null): void {
        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '\'"');
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    function config_getenv($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    function isEnvEnabled($key, $default = false): bool {
        $value = config_getenv($key, $default);
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
}

// Load environment - this always executes even if functions already declared
loadEnv();
?>
