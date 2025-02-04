<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

$backupFiles = [];

if (is_dir(BACKUP_PATH)) {
    foreach (new DirectoryIterator(BACKUP_PATH) as $file) {
        if ($file->isDot()) continue;
        if ($file->isFile() && (
            pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'gz' ||
            pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'sql'
        )) {
            $backupFiles[] = [
                'name' => $file->getFilename(),
                'size' => round($file->getSize() / 1024 / 1024, 2),
                'date' => date('d.m.Y H:i:s', $file->getMTime())
            ];
        }
    }
}

// Tarihe göre sırala (en yeni en üstte)
usort($backupFiles, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Her durumda başarılı yanıt dön (boş array olsa bile)
header('Content-Type: application/json');
echo json_encode($backupFiles); 