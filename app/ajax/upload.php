<?php
session_start();

// PHP limitlerini artır
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');
ini_set('memory_limit', '500M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

require_once '../../app/config/config.php';
require_once '../../app/models/Language.php';

header('Content-Type: application/json');

// Oturum kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => Language::get('session_not_found')]));
}

try {
    // Yedekleme dizinini kontrol et
    if (!is_dir(BACKUP_PATH)) {
        mkdir(BACKUP_PATH, 0755, true);
    }

    // Dosya yükleme kontrolü
    if (empty($_FILES['files'])) {
        // POST boyutu aşıldığında $_FILES boş gelir
        if ($_SERVER['CONTENT_LENGTH'] > 0) {
            throw new Exception(Language::get('file_too_large'));
        }
        throw new Exception(Language::get('file_not_specified'));
    }

    $uploadedFiles = [];
    $errors = [];

    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['files']['name'][$key];
        $file_size = $_FILES['files']['size'][$key];
        $file_tmp = $_FILES['files']['tmp_name'][$key];
        $file_type = $_FILES['files']['type'][$key];
        $file_error = $_FILES['files']['error'][$key];

        // Dosya uzantısını kontrol et
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'gz'])) {
            $errors[] = "Geçersiz dosya uzantısı: $file_name. Sadece .sql ve .gz dosyaları yüklenebilir.";
            continue;
        }

        // Dosya boyutunu kontrol et (max 500MB)
        if ($file_size > 500 * 1024 * 1024) {
            $errors[] = "Dosya boyutu çok büyük: $file_name. Maksimum 500MB yüklenebilir.";
            continue;
        }

        // Yükleme hatasını kontrol et
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "Dosya yükleme hatası: $file_name";
            continue;
        }

        // Dosyayı yedekleme dizinine taşı
        $target_path = BACKUP_PATH . '/' . $file_name;
        
        // Aynı isimde dosya varsa üzerine yazma
        if (file_exists($target_path)) {
            @unlink($target_path);
        }

        if (move_uploaded_file($file_tmp, $target_path)) {
            $uploadedFiles[] = [
                'name' => $file_name,
                'size' => round($file_size / (1024 * 1024), 2) . ' MB'
            ];
        } else {
            $errors[] = "Dosya taşıma hatası: $file_name";
        }
    }

    // Sonucu döndür
    echo json_encode([
        'success' => count($uploadedFiles) > 0,
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 