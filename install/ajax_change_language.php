<?php
session_start();
require_once '../app/config/config.php';
require_once '../app/models/Language.php';

header('Content-Type: application/json');

if (isset($_POST['language'])) {
    $lang = $_POST['language'];
    Language::init($lang);
    
    // Gerekli çevirileri döndür
    echo json_encode([
        'success' => true,
        'translations' => [
            'select_language' => Language::get('select_language'),
            'language_selection' => Language::get('language_selection'),
            'next' => Language::get('next'),
            'installation' => Language::get('installation')
        ]
    ]);
} else {
    echo json_encode(['success' => false]);
} 