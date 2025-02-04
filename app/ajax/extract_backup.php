<?php
session_start();
require_once '../../app/config/config.php';
require_once '../../app/models/Language.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => Language::get('session_not_found')]));
}

if (!isset($_POST['file'])) {
    die(json_encode(['error' => Language::get('file_not_specified')]));
}

$file = BACKUP_PATH . '/' . basename($_POST['file']);

if (!file_exists($file)) {
    die(json_encode(['error' => Language::get('file_not_found')]));
}

try {
    // Orijinal dosya adını al
    $baseFileName = pathinfo($file, PATHINFO_FILENAME);
    if (substr($baseFileName, -4) === '.tar') {
        $baseFileName = substr($baseFileName, 0, -4);
    }
    
    // SQL dosyasını doğrudan backup dizinine çıkart
    $sqlFile = BACKUP_PATH . '/' . $baseFileName . '.sql';
    
    // Gzip dosyasını aç
    $gz = gzopen($file, 'rb');
    if ($gz === false) {
        throw new Exception(Language::get('cannot_read_gz'));
    }
    
    // SQL dosyasını aç
    $dest = fopen($sqlFile, 'wb');
    if ($dest === false) {
        gzclose($gz);
        throw new Exception(Language::get('cannot_write_sql'));
    }
    
    // Parça parça oku ve yaz (8MB parçalar halinde)
    $chunkSize = 8 * 1024 * 1024; // 8MB
    while (!gzeof($gz)) {
        $chunk = gzread($gz, $chunkSize);
        if ($chunk === false) {
            fclose($dest);
            gzclose($gz);
            throw new Exception(Language::get('read_error'));
        }
        if (fwrite($dest, $chunk) === false) {
            fclose($dest);
            gzclose($gz);
            throw new Exception(Language::get('write_error'));
        }
    }
    
    // Dosyaları kapat
    fclose($dest);
    gzclose($gz);
    
    // SQL dosyasını kontrol et
    if (!file_exists($sqlFile)) {
        throw new Exception(Language::get('sql_file_not_found'));
    }

    // Dosya boyutunu kontrol et
    if (filesize($sqlFile) === 0) {
        unlink($sqlFile);
        throw new Exception(Language::get('empty_sql_file'));
    }

    echo json_encode([
        'success' => true,
        'sqlFile' => $sqlFile
    ]);

} catch (Exception $e) {
    if (isset($sqlFile) && file_exists($sqlFile)) {
        unlink($sqlFile);
    }
    echo json_encode(['error' => $e->getMessage()]);
} 