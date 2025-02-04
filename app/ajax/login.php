<?php
session_start();
require_once '../config/config.php';
require_once '../models/Language.php';

header('Content-Type: application/json');

if (!isset($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => Language::get('missing_parameters')]);
    exit;
}

if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
    $_SESSION['admin_logged_in'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => Language::get('invalid_password')]);
} 