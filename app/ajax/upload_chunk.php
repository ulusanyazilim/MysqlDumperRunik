<?php
session_start();
require_once '../../app/config/config.php';
require_once '../../app/models/Language.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => Language::get('session_not_found')]));
}

try {
    $fileName = $_POST['fileName'];
    $chunkIndex = (int)$_POST['chunkIndex'];
    $totalChunks = (int)$_POST['totalChunks'];
    
    $tempDir = BACKUP_PATH . '/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $chunkFile = $tempDir . '/' . $fileName . '.part' . $chunkIndex;
    move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile);
    
    // Son chunk geldiğinde dosyayı birleştir
    if ($chunkIndex === $totalChunks - 1) {
        $finalFile = BACKUP_PATH . '/' . $fileName;
        $out = fopen($finalFile, 'wb');
        
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $tempDir . '/' . $fileName . '.part' . $i;
            $in = fopen($chunkFile, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink($chunkFile); // Chunk'ı sil
        }
        
        fclose($out);
        rmdir($tempDir); // Temp dizini sil
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 