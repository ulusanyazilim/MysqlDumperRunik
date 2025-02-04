<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

header('Content-Type: application/json');

if (isset($_POST['databases']) && is_array($_POST['databases'])) {
    $_SESSION['selected_dbs'] = $_POST['databases'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Geçersiz veri']);
} 