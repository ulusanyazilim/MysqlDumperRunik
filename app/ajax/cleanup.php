<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    // Geçici SQL dosyasını temizle
    if (isset($_SESSION['restore_path']) && isset($_SESSION['original_backup_file'])) {
        $sqlFile = $_SESSION['restore_path'];
        $originalFile = $_SESSION['original_backup_file'];
        
        if (pathinfo($originalFile, PATHINFO_EXTENSION) === 'gz' && file_exists($sqlFile)) {
            @unlink($sqlFile);
        }
    }

    // Session'ları temizle
    unset(
        $_SESSION['restore_path'], 
        $_SESSION['restore_status'], 
        $_SESSION['original_backup_file'],
        $_SESSION['restore_db']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Temizlik tamamlandı'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 