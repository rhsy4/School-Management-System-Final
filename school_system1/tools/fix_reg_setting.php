<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// registration_open тохиргоо байхгүй бол оруулах (default: хаалттай = '0')
$exists = dbOne("SELECT setting_key FROM settings WHERE setting_key='registration_open'");
if (!$exists) {
    dbExec("INSERT INTO settings (setting_key, setting_value) VALUES ('registration_open', '0')");
    echo "OK: 'registration_open' setting inserted with default value '0' (хаалттай).\n";
} else {
    echo "OK: 'registration_open' setting already exists. Current value: " . 
         (dbOne("SELECT setting_value FROM settings WHERE setting_key='registration_open'")['setting_value'] ?? 'NULL') . "\n";
}
?>
