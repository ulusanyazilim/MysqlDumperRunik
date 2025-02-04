<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

// Bellek ve zaman limitleri
ini_set('memory_limit', '1024M');
set_time_limit(0);
ini_set('max_execution_time', 0);

header('Content-Type: application/json');

function writeRestoreLog($message) {
    $logFile = BACKUP_PATH . '/restore_' . date('Y_m_d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    if (!isset($_POST['file']) || !isset($_POST['database'])) {
        throw new Exception('Gerekli parametreler eksik');
    }

    $filePath = BACKUP_PATH . '/' . basename($_POST['file']);
    $database = $_POST['database'];

    if (!file_exists($filePath)) {
        throw new Exception('Yedek dosyası bulunamadı');
    }

    // Dosya uzantısına göre işlem yap
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    if ($extension === 'gz') {
        // Stream kullanarak gz dosyasını aç
        $handle = gzopen($filePath, 'rb');
        if ($handle === false) {
            throw new Exception('Arşiv dosyası açılamadı');
        }

        // SQL dosyası yolunu belirle
        $sqlFile = str_replace('.gz', '.sql', $filePath);
        
        // SQL dosyasını oluştur
        $outHandle = fopen($sqlFile, 'wb');
        if ($outHandle === false) {
            gzclose($handle);
            throw new Exception('SQL dosyası oluşturulamadı');
        }

        // Küçük parçalar halinde çıkart
        $bufferSize = 8192; // 8KB buffer
        while (!gzeof($handle)) {
            $chunk = gzread($handle, $bufferSize);
            if ($chunk === false) {
                break;
            }
            fwrite($outHandle, $chunk);
        }

        gzclose($handle);
        fclose($outHandle);

        // Dosya kontrolü
        if (!file_exists($sqlFile)) {
            throw new Exception('SQL dosyası oluşturulamadı');
        }

        $sqlFileSize = filesize($sqlFile);
        if ($sqlFileSize === 0) {
            unlink($sqlFile);
            throw new Exception('SQL dosyası boş');
        }

        writeRestoreLog("SQL dosyası oluşturuldu: " . basename($sqlFile) . " (" . round($sqlFileSize / 1024 / 1024, 2) . " MB)");

    } else {
        // Normal SQL dosyası
        $sqlFile = $filePath;
        $sqlFileSize = filesize($sqlFile);
    }

    // Session değişkenlerini ayarla
    $_SESSION['restore_path'] = $sqlFile;
    $_SESSION['restore_db'] = $database;
    $_SESSION['original_backup_file'] = $filePath;
    $_SESSION['restore_status'] = [
        'phase' => 'tables',
        'total_bytes' => $sqlFileSize,
        'processed_bytes' => 0,
        'total_tables' => 0,
        'processed_tables' => 0,
        'total_records' => 0,
        'processed_records' => 0,
        'processed_queries' => 0,
        'current_table' => '',
        'error_count' => 0,
        'start_time' => time(),
        'initialized' => false,
        'batch_size' => 1000,
        'table_counts' => [],
        'current_table_progress' => 0,
        'current_table_records' => 0
    ];

    writeRestoreLog("Restore oturumu başlatıldı: " . basename($sqlFile));

    echo json_encode([
        'success' => true,
        'total_bytes' => round($sqlFileSize / (1024 * 1024), 2),
        'sqlFile' => $sqlFile,
        'database' => $database
    ]);

} catch (Exception $e) {
    writeRestoreLog("HATA: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} 