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

// Yeni timestamp oluştur
$_SESSION['backup_timestamp'] = date('Y_m_d_H_i_s');
writeLog("Yedekleme işlemi başlatıldı. Timestamp: " . $_SESSION['backup_timestamp']);

echo json_encode(['success' => true, 'timestamp' => $_SESSION['backup_timestamp']]); 