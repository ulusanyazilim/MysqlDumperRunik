<?php
session_start();
require_once '../../app/config/config.php';
require_once '../../app/models/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Oturum bulunamadı!']));
}

if (!isset($_POST['databases']) || empty($_POST['databases'])) {
    die(json_encode(['error' => 'Veritabanı seçilmedi!']));
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Yedekleme oturumu başlat
    $_SESSION['backup'] = [
        'databases' => $_POST['databases'],
        'current_db_index' => 0,
        'total_dbs' => count($_POST['databases']),
        'current_table_index' => 0,
        'total_tables' => 0,
        'backup_timestamp' => time(),
        'start_time' => time(),
        'current_db' => $_POST['databases'][0]
    ];

    // İlk veritabanının tablolarını say
    $currentDb = $_SESSION['backup']['current_db'];
    $stmt = $db->query("SHOW TABLES FROM `$currentDb`");
    $_SESSION['backup']['total_tables'] = $stmt->rowCount();
    
    // Başarılı yanıt döndür
    echo json_encode([
        'success' => true,
        'message' => 'Yedekleme başlatıldı',
        'total_tables' => $_SESSION['backup']['total_tables'],
        'database' => $currentDb
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 