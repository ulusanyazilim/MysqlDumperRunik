<?php
session_start();
require_once '../../app/config/config.php';
require_once '../../app/models/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['backup']) || !isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Geçersiz yedekleme oturumu!']));
}

try {
    $db = Database::getInstance()->getConnection();
    $backup = $_SESSION['backup'];
    $currentDb = $backup['current_db'];
    
    // Tabloları al
    $tables = $db->query("SHOW TABLES FROM `$currentDb`")->fetchAll(PDO::FETCH_COLUMN);
    
    // Tablo indeksi kontrolü
    $tableIndex = isset($_POST['tableIndex']) ? (int)$_POST['tableIndex'] : 0;
    
    if ($tableIndex >= count($tables)) {
        die(json_encode(['error' => 'Tablo indeksi geçersiz: ' . $tableIndex . ' / ' . count($tables)]));
    }
    
    $currentTable = $tables[$tableIndex];
    $offset = (int)$_POST['offset'];
    $limit = 1000; // Her seferde işlenecek satır sayısı
    
    // Tablo verilerini al
    $stmt = $db->query("SELECT * FROM `$currentDb`.`$currentTable` LIMIT $offset, $limit");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tablo tamamlandı mı?
    $isTableComplete = count($rows) < $limit;
    
    // Veritabanı tamamlandı mı?
    $isDatabaseComplete = $isTableComplete && ($tableIndex >= count($tables) - 1);
    
    // Eğer veritabanı değişmişse tablo indeksini sıfırla
    if ($isDatabaseComplete && $backup['current_db'] !== $currentDb) {
        $tableIndex = 0;
        $currentTable = $tables[$tableIndex];
    }
    
    // SQL dosyası yolu
    $sqlFileName = BACKUP_PATH . '/' . $currentDb . '_' . date('Y_m_d_His', $backup['backup_timestamp']) ;
    $sqlFile = $sqlFileName . '.sql';
    
    // İlk tablo ve ilk offset ise dosyayı oluştur
    if ($tableIndex === 0 && $offset === 0) {
        $header = "-- Runik MySQL Backup\n" .
                 "-- Tarih: " . date('Y-m-d H:i:s') . "\n" .
                 "-- Veritabanı: " . $currentDb . "\n\n" .
                 "SET FOREIGN_KEY_CHECKS=0;\n\n";
        file_put_contents($sqlFile, $header);
    }
    
    // Tablo yapısını ekle (ilk offset ise)
    if ($offset === 0) {
        $createTable = $db->query("SHOW CREATE TABLE `$currentDb`.`$currentTable`")->fetch(PDO::FETCH_ASSOC);
        $structure = $createTable['Create Table'] . ";\n\n";
        file_put_contents($sqlFile, $structure, FILE_APPEND);
    }
    
    // Verileri ekle
    if (!empty($rows)) {
        $values = [];
        foreach ($rows as $row) {
            $values[] = "('" . implode("','", array_map(function($value) use ($db) {
                return str_replace("'", "''", $value ?? '');
            }, $row)) . "')";
        }
        
        $sql = "INSERT INTO `$currentTable` VALUES " . implode(",", $values) . ";\n";
        file_put_contents($sqlFile, $sql, FILE_APPEND);
    }
    
    if ($isDatabaseComplete) {
        // SQL dosyasını sıkıştır
        $gzFile = $sqlFileName . '.gz';
        $fp = gzopen($gzFile, 'w9');
        gzwrite($fp, file_get_contents($sqlFile));
        gzclose($fp);
        
        // SQL dosyasını sil
        unlink($sqlFile);
        
        // Sonraki veritabanına geç
        $_SESSION['backup']['current_db_index']++;
        
        // Sonraki veritabanı varsa, onu hazırla
        if ($_SESSION['backup']['current_db_index'] < count($backup['databases'])) {
            $_SESSION['backup']['current_db'] = $backup['databases'][$_SESSION['backup']['current_db_index']];
            $_SESSION['backup']['current_table_index'] = 0;
            $nextDb = $_SESSION['backup']['current_db'];
            $stmt = $db->query("SHOW TABLES FROM `$nextDb`");
            $_SESSION['backup']['total_tables'] = $stmt->rowCount();
            // Tablo indeksini sıfırla
            $tableIndex = 0;
        }
    }
    
    // İlerleme bilgisini hazırla
    $response = [
        'currentTable' => $tableIndex + 1,
        'totalTables' => count($tables),
        'nextTableIndex' => $isTableComplete ? ($isDatabaseComplete ? 0 : $tableIndex + 1) : $tableIndex,
        'nextOffset' => $isTableComplete ? 0 : $offset + $limit,
        'completed' => $isDatabaseComplete && ($_SESSION['backup']['current_db_index'] >= count($backup['databases'])),
        'database' => $currentDb,
        'elapsedTime' => time() - $backup['start_time'],
        'isDatabaseComplete' => $isDatabaseComplete,
        'nextDatabase' => $isDatabaseComplete ? ($_SESSION['backup']['current_db_index'] < count($backup['databases']) ? $_SESSION['backup']['current_db'] : null) : null,
        'queryCount' => isset($_SESSION['backup']['queryCount']) ? ++$_SESSION['backup']['queryCount'] : ($_SESSION['backup']['queryCount'] = 1)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 