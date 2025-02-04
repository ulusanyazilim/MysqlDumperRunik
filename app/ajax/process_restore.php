<?php
session_start();
require_once '../config/config.php';
require_once '../models/Database.php';
require_once '../models/Language.php';

// Bellek ve zaman limitleri
ini_set('memory_limit', '2048M');
set_time_limit(0);
ini_set('max_execution_time', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => Language::get('session_not_found')]));
}

// Debug fonksiyonu
function debugSession($message = '') {
    writeRestoreLog("DEBUG - $message: " . print_r($_SESSION['restore_status'] ?? [], true));
}


function writeRestoreLog($message) {
    // Sadece önemli mesajları logla
    if (strpos($message, 'SORGU HATASI') === 0 || 
        strpos($message, 'HATALI SORGU') === 0 ||
        strpos($message, 'Tablo oluşturuldu') === 0 ||
        strpos($message, 'Veritabanı hazırlandı') === 0 ||
        strpos($message, 'Tablo yapıları tamamlandı') === 0 ||
        strpos($message, 'GENEL HATA') === 0) {
        
        $logFile = BACKUP_PATH . '/restore_' . date('Y_m_d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

function getMemoryLimit() {
    $memory_limit = ini_get('memory_limit');
    if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
        if ($matches[2] == 'M') {
            return $matches[1] * 1024 * 1024;
        } else if ($matches[2] == 'K') {
            return $matches[1] * 1024;
        }
    }
    return 128 * 1024 * 1024;
}

function calculateOptimalBatchSize() {
    $memory_limit = getMemoryLimit();
    $base_size = 1000;
    
    if ($memory_limit > 2048 * 1024 * 1024) {
        return 5000;
    }
    return $base_size;
}

function initializeRestoreStatus() {
    if (!isset($_SESSION['restore_status']) || !is_array($_SESSION['restore_status'])) {
        $_SESSION['restore_status'] = [
            'phase' => 'tables',
            'total_queries' => 0,
            'processed_queries' => 0,
            'initialized' => false,
            'last_position' => 0,
            'current_table' => '',
            'error_count' => 0,
            'total_tables' => 0,
            'processed_tables' => 0,
            'start_time' => time(),
            'total_bytes' => 0,
            'processed_bytes' => 0,
            'batch_size' => calculateOptimalBatchSize(),
            'table_counts' => [],
            'current_table_progress' => 0,
            'processed_records' => 0,
            'total_records' => 0,
            'current_table_records' => 0,
            'query_count' => 0
        ];
        writeRestoreLog("Restore durumu başlatıldı");
        debugSession("Restore durumu başlatıldı");
    }
    return $_SESSION['restore_status'];
}

function ensureValidStatus() {
    if (!isset($_SESSION['restore_status'])) {
        return initializeRestoreStatus();
    }

    $defaults = [
        'phase' => 'tables',
        'total_queries' => 0,
        'processed_queries' => 0,
        'initialized' => false,
        'last_position' => 0,
        'current_table' => '',
        'error_count' => 0,
        'total_tables' => 0,
        'processed_tables' => 0,
        'start_time' => time(),
        'total_bytes' => 0,
        'processed_bytes' => 0,
        'batch_size' => calculateOptimalBatchSize(),
        'table_counts' => [],
        'current_table_progress' => 0,
        'processed_records' => 0,
        'total_records' => 0,
        'current_table_records' => 0,
        'query_count' => 0
    ];

    foreach ($defaults as $key => $value) {
        if (!isset($_SESSION['restore_status'][$key])) {
            $_SESSION['restore_status'][$key] = $value;
        }
    }

    return $_SESSION['restore_status'];
}

function optimizeDatabase($db) {
    try {
        // Temel MySQL ayarları
        $db->query("SET SESSION sql_mode = ''");
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $db->query("SET UNIQUE_CHECKS = 0");
        $db->query("SET AUTOCOMMIT = 0");
        $db->query("SET SQL_LOG_BIN = 0");
        
        // Timeout ayarları
        $timeoutSettings = [
            "SET SESSION wait_timeout = 28800",
            "SET SESSION interactive_timeout = 28800",
            "SET SESSION net_read_timeout = 28800",
            "SET SESSION net_write_timeout = 28800"
        ];
        
        foreach ($timeoutSettings as $setting) {
            try {
                $db->query($setting);
            } catch (PDOException $e) {
                // Timeout ayarları değiştirilemezse normal devam et
                writeRestoreLog("Uyarı: $setting uygulanamadı");
            }
        }

        // İsteğe bağlı performans ayarları
        $optionalSettings = [
            "SET SESSION group_concat_max_len = 1048576",
            "SET SESSION sort_buffer_size = 1048576",
            "SET SESSION tmp_table_size = 67108864"
        ];
        
        foreach ($optionalSettings as $setting) {
            try {
                $db->query($setting);
            } catch (PDOException $e) {
                // Opsiyonel ayarlar için hata loglamıyoruz
            }
        }

    } catch (Exception $e) {
        writeRestoreLog("Veritabanı optimizasyon hatası: " . $e->getMessage());
        // Kritik olmayan hataları yoksay ve devam et
    }
}

function countTableRecords($db, $tableName) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM `$tableName`");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    } catch (Exception $e) {
        writeRestoreLog("Tablo sayım hatası ($tableName): " . $e->getMessage());
        return 0;
    }
}

function processCreateTable($db, $query) {
    $query = preg_replace('/\s+/', ' ', $query);
    
    preg_match('/CREATE TABLE.*?`(.+?)`/i', $query, $matches);
    if (isset($matches[1])) {
        $tableName = $matches[1];
        $_SESSION['restore_status']['current_table'] = $tableName;
        
        try {
            $db->query("DROP TABLE IF EXISTS `$tableName`");
            $db->query($query);
            
            // Tablo kayıt sayısını hesapla
            $result = $db->query("SELECT COUNT(*) as count FROM `$tableName`");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $recordCount = (int)$row['count'];
            
            $_SESSION['restore_status']['table_counts'][$tableName] = $recordCount;
            $_SESSION['restore_status']['total_records'] += $recordCount;
            
            $_SESSION['restore_status']['processed_tables']++;
            writeRestoreLog("Tablo oluşturuldu: $tableName (Kayıt sayısı: $recordCount)");
            return true;
        } catch (Exception $e) {
            writeRestoreLog("Tablo oluşturma hatası ($tableName): " . $e->getMessage());
            return false;
        }
    }
    return false;
}

function processInsertQuery($db, $query) {
    preg_match('/INSERT (?:IGNORE )?INTO `(.+?)`/i', $query, $matches);
    if (isset($matches[1])) {
        $tableName = $matches[1];
        try {
            // Direkt INSERT yap, sayım kontrolü yapma
            $db->query($query);
            return true;
        } catch (Exception $e) {
            handleQueryError($e, $query);
            return false;
        }
    }
    return false;
}

function calculateProgress() {
    $status = ensureValidStatus();
    
    // Dosya boyutu kontrolü
    $totalBytes = max($status['total_bytes'], $status['processed_bytes']);
    $processedBytes = $status['processed_bytes'];
    
    // Tablo sayıları
    $processedTables = max(0, intval($status['processed_tables']));
    $totalTables = max(1, intval($status['total_tables']));
    $processedQueries = max(0, intval($status['processed_queries']));
    
    // Hız hesaplama
    $elapsed = max(1, time() - $status['start_time']);
    $speed = $processedQueries > 0 ? round($processedQueries / $elapsed, 2) : 0;
    
    return [
        'database' => $_SESSION['restore_db'] ?? '',
        'current_table' => $status['current_table'] ?? '',
        'processed_tables' => $processedTables,
        'total_tables' => $totalTables,
        'processed_bytes' => round($processedBytes / (1024 * 1024), 2),
        'total_bytes' => round($totalBytes / (1024 * 1024), 2),
        'elapsed_time' => $elapsed,
        'speed' => $speed,
        'phase' => $status['phase'],
        'queryCount' => isset($_SESSION['backup']['queryCount']) ? ++$_SESSION['backup']['queryCount'] : ($_SESSION['backup']['queryCount'] = 1)
    ];
}

// Tablo sayısını doğru hesapla
function countTotalTables($content) {
    preg_match_all('/CREATE TABLE.*?`(.+?)`/i', $content, $matches);
    return count($matches[1]);
}

// Paralel işleme için yeni sınıf
class RestoreWorker {
    private $db;
    private $batchSize;
    private $startOffset;
    private $endOffset;
    private $status;
    
    public function __construct($startOffset, $endOffset) {
        $this->startOffset = $startOffset;
        $this->endOffset = $endOffset;
        $this->batchSize = calculateOptimalBatchSize();
        $this->status = &$_SESSION['restore_status'];
        $this->initializeDatabase();
    }
    
    private function initializeDatabase() {
        $this->db = Database::getInstance()->getConnection();
        $this->optimizeDatabase();
    }
    
    private function optimizeDatabase() {
        try {
            // Temel optimizasyonlar
            $this->db->query("SET SESSION sql_mode = ''");
            $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->db->query("SET UNIQUE_CHECKS = 0");
            $this->db->query("SET AUTOCOMMIT = 0");
            
            // Buffer ve memory optimizasyonları
            $this->db->query("SET SESSION key_buffer_size = 256M");
            $this->db->query("SET SESSION read_buffer_size = 2M");
            $this->db->query("SET SESSION read_rnd_buffer_size = 16M");
            $this->db->query("SET SESSION sort_buffer_size = 32M");
            $this->db->query("SET SESSION join_buffer_size = 8M");
            
            // InnoDB optimizasyonları
            $this->db->query("SET SESSION innodb_flush_log_at_trx_commit = 2");
            $this->db->query("SET SESSION innodb_doublewrite = 0");
            $this->db->query("SET SESSION innodb_buffer_pool_size = 1G");
            $this->db->query("SET SESSION innodb_log_buffer_size = 64M");
            
            // Bulk insert optimizasyonları
            $this->db->query("SET SESSION bulk_insert_buffer_size = 256M");
            $this->db->query("SET SESSION myisam_sort_buffer_size = 128M");
            
            // Network optimizasyonları
            $this->db->query("SET SESSION net_read_timeout = 28800");
            $this->db->query("SET SESSION net_write_timeout = 28800");
            
        } catch (Exception $e) {
            writeRestoreLog("Optimizasyon hatası: " . $e->getMessage());
        }
    }
    
    public function processChunk() {
        $handle = fopen($_SESSION['restore_path'], 'r');
        if ($handle === false) {
            throw new Exception("Dosya açılamadı");
        }
        
        fseek($handle, $this->startOffset);
        $currentQuery = '';
        $processedQueries = 0;
        
        $this->db->query("START TRANSACTION");
        
        while (ftell($handle) < $this->endOffset && !feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;
            
            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) {
                continue;
            }
            
            $currentQuery .= $line;
            
            if (substr(trim($line), -1) === ';') {
                try {
                    if (stripos($currentQuery, 'CREATE TABLE') === 0) {
                        $this->processCreateTable($currentQuery);
                    } 
                    elseif (stripos($currentQuery, 'INSERT INTO') === 0) {
                        $this->processInsertQuery($currentQuery);
                    }
                    
                    $processedQueries++;
                    $this->status['processed_queries']++;
                    
                    // Her 1000 sorguda bir commit
                    if ($processedQueries % 1000 === 0) {
                        $this->db->query("COMMIT");
                        $this->db->query("START TRANSACTION");
                        gc_collect_cycles(); // Bellek temizliği
                    }
                    
                } catch (Exception $e) {
                    writeRestoreLog("Sorgu hatası: " . $e->getMessage());
                }
                
                $currentQuery = '';
            }
        }
        
        $this->db->query("COMMIT");
        fclose($handle);
    }
    
    private function processCreateTable($query) {
        preg_match('/CREATE TABLE.*?`(.+?)`/i', $query, $matches);
        if (isset($matches[1])) {
            $tableName = $matches[1];
            $this->status['current_table'] = $tableName;
            
            try {
                $this->db->query("DROP TABLE IF EXISTS `$tableName`");
                $this->db->query($query);
                
                $this->status['processed_tables']++;
                writeRestoreLog("Tablo oluşturuldu: $tableName");
            } catch (Exception $e) {
                writeRestoreLog("Tablo oluşturma hatası ($tableName): " . $e->getMessage());
            }
        }
    }
    
    private function processInsertQuery($query) {
        try {
            $this->db->query($query);
        } catch (Exception $e) {
            if (!$this->isIgnorableError($e->getCode())) {
                writeRestoreLog("Insert hatası: " . $e->getMessage());
            }
        }
    }
    
    private function isIgnorableError($code) {
        $ignorableCodes = [1062, 1050, 1060, 1061, 1091, 1051];
        return in_array($code, $ignorableCodes);
    }
}

// Ana işlem fonksiyonunu güncelle
function processRestore($offset) {
    try {
        $fileSize = filesize($_SESSION['restore_path']);
        $workerCount = 4; // CPU çekirdek sayısına göre ayarlanabilir
        $chunkSize = ceil(($fileSize - $offset) / $workerCount);
        
        $workers = [];
        $processes = [];
        
        // Worker'ları başlat
        for ($i = 0; $i < $workerCount; $i++) {
            $startOffset = $offset + ($i * $chunkSize);
            $endOffset = $startOffset + $chunkSize;
            
            if (function_exists('pcntl_fork')) {
                // Unix sistemlerde fork ile parallel processing
                $pid = pcntl_fork();
                if ($pid == 0) {
                    $worker = new RestoreWorker($startOffset, $endOffset);
                    $worker->processChunk();
                    exit(0);
                } else if ($pid > 0) {
                    $processes[] = $pid;
                }
            } else {
                // Windows veya fork desteklemeyen sistemlerde seri işlem
                $worker = new RestoreWorker($startOffset, $endOffset);
                $worker->processChunk();
            }
        }
        
        // Worker'ları bekle
        if (function_exists('pcntl_waitpid')) {
            foreach ($processes as $pid) {
                $status = 0; // Initialize status variable
                pcntl_waitpid($pid, $status);
            }
        } else {
            // Fallback to sequential processing
            foreach ($workers as $worker) {
                $worker->processChunk();
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        writeRestoreLog("Genel hata: " . $e->getMessage());
        return false;
    }
}

// Yanıt döndürmeden önce son durumu kontrol et ve NaN kontrolü yap
function sanitizeResponse($response) {
    // Sayısal değerleri kontrol et ve temizle
    $numericFields = [
        'processed_tables', 'total_tables', 'processed_bytes', 
        'total_bytes', 'elapsed_time', 'speed', 'queryCount'
    ];
    
    foreach ($numericFields as $field) {
        if (isset($response[$field])) {
            // NaN veya geçersiz değer kontrolü
            if (is_float($response[$field]) && (is_nan($response[$field]) || !is_finite($response[$field]))) {
                $response[$field] = 0;
            }
            // String'i sayıya çevir
            $response[$field] = floatval($response[$field]);
            // Negatif değerleri sıfırla
            if ($response[$field] < 0) {
                $response[$field] = 0;
            }
        }
    }
    
    // Özel alan kontrolleri
    if (isset($response['processed_bytes']) && isset($response['total_bytes'])) {
        if ($response['processed_bytes'] > $response['total_bytes']) {
            $response['processed_bytes'] = $response['total_bytes'];
        }
    }
    
    return $response;
}

try {
    $db = Database::getInstance()->getConnection();
    
    if (!isset($_SESSION['restore_path'])) {
        throw new Exception(Language::get('restore_session_not_found'));
    }
    
    $sqlFile = $_SESSION['restore_path'];
    if (!file_exists($sqlFile)) {
        throw new Exception(Language::get('sql_file_not_found'));
    }

    $dbName = explode('_', basename($sqlFile))[0];
    $GLOBALS['dbName'] = $dbName;
    
    $restoreStatus = ensureValidStatus();
    debugSession("İşlem başlangıcı");
    
    if (empty($restoreStatus['total_bytes'])) {
        $_SESSION['restore_status']['total_bytes'] = filesize($sqlFile);
        debugSession("Dosya boyutu ayarlandı");
    }
    
    // Veritabanı hazırlığı
    if (!$restoreStatus['initialized']) {
        try {
            optimizeDatabase($db);
            $db->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
            $db->query("USE `$dbName`");
            
            // Toplam tablo sayısını hesapla
            $fileContent = file_get_contents($sqlFile);
            $_SESSION['restore_status']['total_tables'] = countTotalTables($fileContent);
            
            $_SESSION['restore_status']['initialized'] = true;
            writeRestoreLog("Veritabanı hazırlandı: $dbName");
        } catch (Exception $e) {
            writeRestoreLog("Veritabanı hazırlama hatası: " . $e->getMessage());
            throw $e;
        }
    }

    $db->query("USE `$dbName`");
    
    // Dosya işleme
    $offset = (int)($_POST['offset'] ?? 0);
    $batchSize = $restoreStatus['batch_size'];
    
    $handle = fopen($sqlFile, 'r');
    if ($handle === false) {
        throw new Exception(Language::get('cannot_open_file'));
    }

    if ($offset > 0) {
        fseek($handle, $offset);
    }

    // İşlem değişkenleri
    $processedQueries = 0;
    $currentQuery = '';
    $errorCount = 0;
    $bytesRead = 0;

    // Transaction başlat
    $db->query("START TRANSACTION");

    while (!feof($handle) && $processedQueries < $batchSize) {
        $line = fgets($handle);
        if ($line === false) break;
        
        $lineLength = strlen($line);
        $bytesRead += $lineLength;
        $_SESSION['restore_status']['processed_bytes'] += $lineLength;
        
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;
        
        $currentQuery .= $line;
        
        if (substr(trim($line), -1) === ';') {
            try {
                if (stripos($currentQuery, 'CREATE TABLE') === 0) {
                    processCreateTable($db, $currentQuery);
                } 
                elseif (stripos($currentQuery, 'INSERT INTO') === 0) {
                    processInsertQuery($db, $currentQuery);
                }
                
                $processedQueries++;
                $_SESSION['restore_status']['processed_queries']++;
                
                // Her 1000 sorguda bir commit yap
                if ($processedQueries % 1000 === 0) {
                    $db->query("COMMIT");
                    $db->query("START TRANSACTION");
                    gc_collect_cycles();
                }
            } catch (Exception $e) {
                handleQueryError($e, $currentQuery);
                $errorCount++;
                $_SESSION['restore_status']['error_count']++;
            }
            
            $currentQuery = '';
        }
    }

    // Son transaction'ı commit et
    $db->query("COMMIT");

    $newOffset = ftell($handle);
    $isComplete = feof($handle);
    
    // Tablo fazı bittiğinde kontrolünü düzeltelim
    if ($isComplete && $restoreStatus['phase'] === 'tables') {
        // Tüm tabloların işlendiğinden emin ol
        if ($restoreStatus['processed_tables'] >= $restoreStatus['total_tables']) {
            $_SESSION['restore_status']['phase'] = 'data';
            // Sayaçları sıfırlamıyoruz, son değerleri koruyoruz
            //$_SESSION['restore_status']['processed_bytes'] = 0;
            //$_SESSION['restore_status']['processed_queries'] = 0;
            $newOffset = 0; // Dosyayı baştan oku
            
            writeRestoreLog(Language::get('tables_completed') . " (" . $_SESSION['restore_status']['processed_tables'] . " " . Language::get('tables') . ")");
        }
    }

    // Tamamlanma kontrolünü düzeltelim
    $isReallyComplete = false;

    // Dosya sonuna gelindi mi kontrol et
    if ($isComplete) {
        // Dosya pozisyonunu kontrol et
        $fileSize = filesize($sqlFile);
        $currentPosition = ftell($handle);
        
        // Eğer dosya sonuna gelindiyse ve tüm veriler işlendiyse
        if ($currentPosition >= $fileSize) {
            $isReallyComplete = true;
            
            // Temizlik işlemleri
            try {
                $db->query("SET FOREIGN_KEY_CHECKS = 1");
                $db->query("SET UNIQUE_CHECKS = 1");
                $db->query("SET AUTOCOMMIT = 1");
                
                // Session'ları temizle
                unset(
                    $_SESSION['restore_path'], 
                    $_SESSION['restore_status'], 
                    $_SESSION['original_backup_file'],
                    $_SESSION['restore_db'],
                    $_SESSION['backup']['queryCount']
                );
                
                // Geçici dosyaları temizle
                if (file_exists($sqlFile) && !empty($_SESSION['original_backup_file'])) {
                    @unlink($sqlFile);
                }
                
                writeRestoreLog("Restore işlemi başarıyla tamamlandı");
            } catch (Exception $e) {
                writeRestoreLog("Temizleme hatası: " . $e->getMessage());
            }
        }
    }

    // Dosya handle'ını kapat
    fclose($handle);

    // Yanıt döndürmeden önce son durumu kontrol et ve NaN kontrolü yap
    $progress = calculateProgress();
    $finalResponse = array_merge(
        [
            'complete' => $isReallyComplete,
            'next_offset' => $isReallyComplete ? null : $newOffset,
            'error_count' => $restoreStatus['error_count'],
            'phase' => $restoreStatus['phase'],
            'success' => $isReallyComplete,
            'message' => $isReallyComplete ? Language::get('restore_completed') : null,
            'queryCount' => isset($_SESSION['backup']['queryCount']) ? $_SESSION['backup']['queryCount'] : 0,
            'file_position' => $currentPosition ?? 0,
            'file_size' => $fileSize ?? 0
        ],
        $progress
    );

    // Yanıtı temizle ve kontrol et
    $finalResponse = sanitizeResponse($finalResponse);

    echo json_encode($finalResponse);

} catch (Exception $e) {
    writeRestoreLog("GENEL HATA: " . $e->getMessage());
    debugSession("HATA OLUŞTU");
    echo json_encode(['error' => $e->getMessage()]);
}

function handleQueryError($e, $query) {
    $error = $e->getMessage();
    $ignoredErrors = [
        '1062', // Duplicate entry
        '1050', // Table already exists
        '1060', // Duplicate column name
        '1061', // Duplicate key name
        '1091', // Can't DROP; check that column/key exists
        '1051', // Unknown table
        '1067', // Invalid default value
        '1146', // Table doesn't exist
        '1215', // Cannot add foreign key constraint
        '1217', // Cannot delete or update a parent row
        '1451', // Cannot delete or update a parent row (FK constraint)
        '1452', // Cannot add or update a child row (FK constraint)
        '1364', // Field doesn't have a default value
        '1366', // Incorrect integer value
        '1265', // Data truncated
        '1292'  // Incorrect datetime value
    ];
    
    if (!array_filter($ignoredErrors, fn($code) => strpos($error, $code) !== false)) {
        writeRestoreLog("SORGU HATASI: $error");
        writeRestoreLog("HATALI SORGU: $query");
    }
} 