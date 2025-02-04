<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

header('Content-Type: application/json');

$file = isset($_POST['file']) ? basename($_POST['file']) : '';
$filePath = BACKUP_PATH . '/' . $file;

try {
    if (empty($file)) {
        throw new Exception('Dosya adı belirtilmedi');
    }

    if (!file_exists($filePath)) {
        throw new Exception('Dosya bulunamadı');
    }

    // Dosyayı sil
    if (unlink($filePath)) {
        // Eğer tmp klasöründe ilgili dosyalar varsa onları da temizle
        $tmpDir = BACKUP_PATH . '/tmp';
        if (is_dir($tmpDir)) {
            $tmpFiles = glob($tmpDir . '/*');
            foreach ($tmpFiles as $tmpFile) {
                if (is_file($tmpFile)) {
                    unlink($tmpFile);
                }
            }
            // Eğer tmp klasörü boşsa onu da sil
            if (count(glob($tmpDir . '/*')) === 0) {
                rmdir($tmpDir);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Dosya başarıyla silindi'
        ]);
    } else {
        throw new Exception('Dosya silinirken bir hata oluştu');
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} 