<?php
session_start();
require_once '../config/config.php';
require_once '../models/Language.php';

if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
    Language::init($_POST['language']);
    
    echo json_encode([
        'success' => true,
        'translations' => [
            'select_language' => Language::get('select_language'),
            'language_selection' => Language::get('language_selection'),
            'next' => Language::get('next'),
            'login' => Language::get('login')
        ]
    ]);
} else {
    echo json_encode(['success' => false]);
} 