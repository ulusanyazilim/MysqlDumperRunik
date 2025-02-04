<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

function writeLog($message) {
    $logFile = BACKUP_PATH . '/backup_' . date('Y_m_d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

header('Content-Type: application/json');

try {
    $timestamp = $_SESSION['backup_timestamp'];
    $compression = $_POST['compression'] ?? 'gzip';
    $sqlFiles = glob(BACKUP_PATH . '/*_' . $timestamp . '.sql');
    
    if (empty($sqlFiles)) {
        writeLog("UYARI: SQL dosyası bulunamadı. Timestamp: $timestamp");
        echo json_encode(['error' => 'SQL dosyası bulunamadı']);
        exit;
    }

    writeLog("\nSıkıştırma işlemi başlatıldı. Timestamp: $timestamp");
    writeLog("Sıkıştırma türü: " . strtoupper($compression));
    writeLog("Bulunan SQL dosyaları:");
    foreach ($sqlFiles as $sqlFile) {
        $fileSize = round(filesize($sqlFile) / 1024 / 1024, 2);
        writeLog("- " . basename($sqlFile) . " (Boyut: $fileSize MB)");
    }
    
    $successFiles = [];
    $failedFiles = [];
    
    foreach ($sqlFiles as $sqlFile) {
        try {
            $filename = basename($sqlFile);
            $dbName = substr($filename, 0, strpos($filename, '_'));
            
            writeLog("\nİşlem: $dbName");
            writeLog("- SQL dosyası: $filename");
            
            if ($compression === 'gzip') {
                $tarFile = BACKUP_PATH . '/' . $dbName . '_' . $timestamp . '.tar';
                
                // SQL dosyasını tar'a ekle
                $phar = new PharData($tarFile);
                $phar->addFile($sqlFile, basename($sqlFile));
                
                // tar dosyasını gzip ile sıkıştır
                $phar->compress(Phar::GZ);
                
                // Orijinal tar dosyasını sil
                unlink($tarFile);
                
                // SQL dosyasını sil
                unlink($sqlFile);
                
                $gzFile = $tarFile . '.gz';
                $gzSize = round(filesize($gzFile) / 1024 / 1024, 2);
                writeLog("- TAR.GZ oluşturuldu: " . basename($gzFile) . " (Boyut: $gzSize MB)");
            } else {
                // SQL dosyasını olduğu gibi bırak
                writeLog("- SQL dosyası korundu: $filename");
            }
            
            $successFiles[] = $dbName;
        } catch (Exception $e) {
            writeLog("- HATA: Sıkıştırılamadı - " . $e->getMessage());
            $failedFiles[] = $dbName;
        }
    }
    
    writeLog("\nÖzet:");
    writeLog("- Toplam dosya: " . count($sqlFiles));
    writeLog("- Başarılı: " . implode(', ', $successFiles));
    if (!empty($failedFiles)) {
        writeLog("- Başarısız: " . implode(', ', $failedFiles));
    }
    writeLog("Sıkıştırma işlemi tamamlandı.\n");
    
    // Session değişkenlerini temizle
    unset($_SESSION['backup_timestamp']);
    unset($_SESSION['total_dbs']);
    unset($_SESSION['completed_dbs']);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    writeLog("\nGENEL HATA: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} 