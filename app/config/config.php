<?php
// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mysql');

// Admin password hash
define('ADMIN_PASSWORD_HASH', '');

// Language settings
define('DEFAULT_LANGUAGE', 'en');
define('AVAILABLE_LANGUAGES', [
    'tr' => 'Türkçe',
    'en' => 'English',
    'ar' => 'العربية'
]);

// Paths
define('BACKUP_PATH', __DIR__ . '/../../backups');

define('BASE_PATH', dirname(dirname(__DIR__)));

// Yedekleme ayarları
define('MAX_BACKUP_SIZE', 5242880); // 5MB