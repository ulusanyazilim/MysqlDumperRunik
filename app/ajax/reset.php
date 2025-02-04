<?php
session_start();
require_once '../config/config.php';

try {
    // Config dosyasını sıfırla
    $config = file_get_contents(__DIR__ . '/../config/config.php');
    $config = preg_replace("/define\('DB_HOST', '.*?'\);/", "define('DB_HOST', '');", $config);
    $config = preg_replace("/define\('DB_USER', '.*?'\);/", "define('DB_USER', '');", $config);
    $config = preg_replace("/define\('DB_PASS', '.*?'\);/", "define('DB_PASS', '');", $config);
    $config = preg_replace("/define\('DB_NAME', '.*?'\);/", "define('DB_NAME', '');", $config);
    $config = preg_replace("/define\('ADMIN_PASSWORD_HASH', '.*?'\);/", "define('ADMIN_PASSWORD_HASH', '');", $config);
    
    file_put_contents(__DIR__ . '/../config/config.php', $config);
    
    // Session'ları temizle
    session_destroy();
    
    // Yedek dosyalarını temizle
    if (is_dir(BACKUP_PATH)) {
        $files = glob(BACKUP_PATH . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // installed.php dosyasını sil
    if (file_exists(__DIR__ . '/../config/installed.php')) {
        unlink(__DIR__ . '/../config/installed.php');
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 