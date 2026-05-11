<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    auditLog('logout', null, ($_SESSION['username'] ?? '') . ' гарлаа');
}
session_destroy();
header('Location: /school_system1/index.php');
exit;

