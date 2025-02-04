<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die('Yetkisiz erişim');
}

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
$filePath = BACKUP_PATH . '/' . $file;

if (empty($file) || !file_exists($filePath)) {
    die('Dosya bulunamadı');
}

// Dosya boyutunu al
$fileSize = filesize($filePath);

// Dosya tipini belirle
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeTypes = [
    'sql' => 'application/sql',
    'gz' => 'application/gzip',
];
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

// Header'ları ayarla
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache');
header('Pragma: no-cache');

// Dosyayı oku ve gönder
readfile($filePath);
exit; 