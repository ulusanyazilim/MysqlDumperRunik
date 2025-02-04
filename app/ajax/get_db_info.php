<?php
session_start();
require_once '../config/config.php';
require_once '../models/Database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

$database = $_POST['database'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Toplam tablo sayısı
    $tables = $db->query("SHOW TABLES FROM `$database`")->fetchAll(PDO::FETCH_COLUMN);
    $totalTables = count($tables);
    
    // Toplam kayıt sayısı
    $totalRows = 0;
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM `$database`.`$table`")->fetchColumn();
        $totalRows += $count;
    }
    
    echo json_encode([
        'total_tables' => $totalTables,
        'total_rows' => $totalRows
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 